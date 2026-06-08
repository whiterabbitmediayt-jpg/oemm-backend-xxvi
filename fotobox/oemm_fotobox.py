#!/usr/bin/env python3
"""
oemm_fotobox.py — ÖMM XXVI Fotobox
=====================================
Vollständiges Script für die ÖMM-Fotobox.

Ablauf:
  1. Beim Start: Offline-Queue abarbeiten
  2. Dauerschleife:
     a) Warte auf QR-Code-Scan (Kamera-Feed)
     b) Mache Foto
     c) Lade Foto zum ÖMM Dashboard des Users hoch
     d) LED/Sound Feedback
     e) Weiter mit nächstem Gast

Installationsanleitung: siehe README.md
Konfiguration:          siehe config.ini

Autor: WhiteRabbitMedia für ÖMM XXVI
"""

import os
import sys
import json
import time
import logging
import signal
import configparser
import subprocess
import threading
from datetime import datetime
from pathlib import Path

import requests

# QR-Code Erkennung
try:
    import cv2
    from pyzbar.pyzbar import decode as qr_decode
    CV2_AVAILABLE = True
except ImportError:
    CV2_AVAILABLE = False

# GPIO (Raspberry Pi LED-Feedback)
try:
    import RPi.GPIO as GPIO
    GPIO_AVAILABLE = True
except ImportError:
    GPIO_AVAILABLE = False


# ---------------------------------------------------------------------------
# LOGGING
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler("/tmp/oemm_fotobox.log", encoding="utf-8"),
    ],
)
log = logging.getLogger("oemm_fotobox")


# ---------------------------------------------------------------------------
# KONFIGURATION
# ---------------------------------------------------------------------------
CONFIG_PATH = Path(__file__).parent / "config.ini"

def load_config() -> configparser.ConfigParser:
    cfg = configparser.ConfigParser()
    if not cfg.read(CONFIG_PATH):
        log.error(f"config.ini nicht gefunden: {CONFIG_PATH}")
        log.error("Bitte config.ini im selben Verzeichnis erstellen (siehe README.md)")
        sys.exit(1)
    return cfg

CFG = load_config()

# API
API_URL     = CFG.get("api", "url",     fallback="https://mopedmarathon.at/wp-json/oemm-xxvi/v1/foto/upload")
API_KEY     = CFG.get("api", "key",     fallback="")
API_TIMEOUT = CFG.getint("api", "timeout_sec",  fallback=30)
API_RETRIES = CFG.getint("api", "retry_count",  fallback=3)

# Kamera
CAM_INDEX       = CFG.getint("camera", "index",           fallback=0)
CAM_RESOLUTION  = CFG.get(   "camera", "resolution",      fallback="1920x1080")
CAM_FOTO_DIR    = CFG.get(   "camera", "foto_dir",        fallback="/tmp/oemm_fotos")
CAM_BACKEND     = CFG.get(   "camera", "backend",         fallback="opencv")   # opencv | libcamera | fswebcam
CAM_COUNTDOWN   = CFG.getint("camera", "countdown_sec",   fallback=3)
CAM_WARMUP      = CFG.getfloat("camera", "warmup_sec",    fallback=1.5)

# QR
QR_DEBOUNCE_SEC = CFG.getfloat("qr", "debounce_sec", fallback=5.0)
QR_MIN_LEN      = CFG.getint(  "qr", "min_token_len", fallback=8)
QR_PREFIX       = CFG.get(     "qr", "token_prefix",  fallback="")  # optional: nur QR mit diesem Prefix akzeptieren

# Offline-Queue
QUEUE_FILE = CFG.get("queue", "file", fallback="/tmp/oemm_queue.json")
MAX_FILE_MB = CFG.getint("queue", "max_file_mb", fallback=25)

# GPIO Pins
PIN_GREEN  = CFG.getint("gpio", "pin_green",  fallback=17)
PIN_RED    = CFG.getint("gpio", "pin_red",    fallback=27)
PIN_YELLOW = CFG.getint("gpio", "pin_yellow", fallback=22)

# Sound
SOUND_OK      = CFG.get("sound", "ok",      fallback="")   # Pfad zur WAV-Datei
SOUND_ERROR   = CFG.get("sound", "error",   fallback="")
SOUND_SHUTTER = CFG.get("sound", "shutter", fallback="")


# ---------------------------------------------------------------------------
# GPIO
# ---------------------------------------------------------------------------
def gpio_setup():
    if not GPIO_AVAILABLE:
        return
    GPIO.setmode(GPIO.BCM)
    GPIO.setwarnings(False)
    for pin in (PIN_GREEN, PIN_RED, PIN_YELLOW):
        GPIO.setup(pin, GPIO.OUT)
        GPIO.output(pin, GPIO.LOW)

def gpio_cleanup():
    if GPIO_AVAILABLE:
        GPIO.cleanup()

def led_blink(pin: int, times: int = 3, interval: float = 0.25):
    if not GPIO_AVAILABLE:
        return
    def _blink():
        for _ in range(times):
            GPIO.output(pin, GPIO.HIGH)
            time.sleep(interval)
            GPIO.output(pin, GPIO.LOW)
            time.sleep(interval)
    threading.Thread(target=_blink, daemon=True).start()

def led_on(pin: int, duration: float = 2.0):
    if not GPIO_AVAILABLE:
        return
    def _on():
        GPIO.output(pin, GPIO.HIGH)
        time.sleep(duration)
        GPIO.output(pin, GPIO.LOW)
    threading.Thread(target=_on, daemon=True).start()

def led_ok():
    """Grün 3x blinken = Upload erfolgreich."""
    log.debug("LED: OK (grün)")
    led_blink(PIN_GREEN, times=3, interval=0.2)

def led_error():
    """Rot dauerhaft = Fehler."""
    log.debug("LED: ERROR (rot)")
    led_on(PIN_RED, duration=2.5)

def led_offline():
    """Gelb schnell blinken = offline gespeichert."""
    log.debug("LED: OFFLINE (gelb)")
    led_blink(PIN_YELLOW, times=5, interval=0.15)

def led_ready():
    """Grün kurz aufleuchten = bereit für nächsten Gast."""
    led_blink(PIN_GREEN, times=1, interval=0.5)

def play_sound(path: str):
    """Spielt optional einen Sound ab (non-blocking)."""
    if not path or not os.path.isfile(path):
        return
    try:
        subprocess.Popen(["aplay", path], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except FileNotFoundError:
        pass  # aplay nicht installiert


# ---------------------------------------------------------------------------
# KAMERA
# ---------------------------------------------------------------------------
def foto_dir_ensure() -> str:
    os.makedirs(CAM_FOTO_DIR, exist_ok=True)
    return CAM_FOTO_DIR

def foto_path_new() -> str:
    """Generiert einen eindeutigen Dateipfad für das nächste Foto."""
    ts = datetime.now().strftime("%Y%m%d_%H%M%S_%f")
    return os.path.join(foto_dir_ensure(), f"oemm_{ts}.jpg")

def make_foto_opencv() -> str | None:
    """Foto mit OpenCV (USB-Kamera oder integrierte Kamera)."""
    cap = None
    try:
        cap = cv2.VideoCapture(CAM_INDEX)
        if not cap.isOpened():
            log.error(f"Kamera {CAM_INDEX} konnte nicht geöffnet werden")
            return None

        # Auflösung setzen
        try:
            w, h = CAM_RESOLUTION.split("x")
            cap.set(cv2.CAP_PROP_FRAME_WIDTH,  int(w))
            cap.set(cv2.CAP_PROP_FRAME_HEIGHT, int(h))
        except ValueError:
            pass

        # Kamera warmlaufen lassen
        time.sleep(CAM_WARMUP)

        # Mehrere Frames lesen damit die Belichtung stimmt
        for _ in range(5):
            cap.read()

        ret, frame = cap.read()
        if not ret:
            log.error("Kein Frame von Kamera erhalten")
            return None

        path = foto_path_new()
        cv2.imwrite(path, frame, [cv2.IMWRITE_JPEG_QUALITY, 92])
        log.info(f"Foto gespeichert: {path}")
        return path

    except Exception as e:
        log.error(f"Fehler beim Foto (OpenCV): {e}")
        return None
    finally:
        if cap:
            cap.release()

def make_foto_libcamera() -> str | None:
    """Foto mit libcamera-still (Raspberry Pi Kameramodul)."""
    path = foto_path_new()
    try:
        w, h = CAM_RESOLUTION.split("x")
        result = subprocess.run(
            ["libcamera-still", "-o", path, "--width", w, "--height", h,
             "--nopreview", "--timeout", "2000"],
            capture_output=True, timeout=15
        )
        if result.returncode == 0 and os.path.isfile(path):
            log.info(f"Foto gespeichert: {path}")
            return path
        else:
            log.error(f"libcamera-still Fehler: {result.stderr.decode()[:200]}")
            return None
    except Exception as e:
        log.error(f"Fehler beim Foto (libcamera): {e}")
        return None

def make_foto_fswebcam() -> str | None:
    """Foto mit fswebcam (einfache USB-Kamera)."""
    path = foto_path_new()
    try:
        result = subprocess.run(
            ["fswebcam", "-r", CAM_RESOLUTION, "--jpeg", "92",
             "--no-banner", "-d", f"/dev/video{CAM_INDEX}", path],
            capture_output=True, timeout=15
        )
        if result.returncode == 0 and os.path.isfile(path):
            log.info(f"Foto gespeichert: {path}")
            return path
        else:
            log.error(f"fswebcam Fehler: {result.stderr.decode()[:200]}")
            return None
    except Exception as e:
        log.error(f"Fehler beim Foto (fswebcam): {e}")
        return None

def make_foto() -> str | None:
    """Macht ein Foto je nach konfiguriertem Backend."""
    log.info(f"Foto wird gemacht (Backend: {CAM_BACKEND}, Countdown: {CAM_COUNTDOWN}s)...")

    if CAM_COUNTDOWN > 0:
        for i in range(CAM_COUNTDOWN, 0, -1):
            log.info(f"  ... {i}")
            time.sleep(1)

    play_sound(SOUND_SHUTTER)

    if CAM_BACKEND == "libcamera":
        return make_foto_libcamera()
    elif CAM_BACKEND == "fswebcam":
        return make_foto_fswebcam()
    else:
        return make_foto_opencv()


# ---------------------------------------------------------------------------
# QR-CODE SCANNER
# ---------------------------------------------------------------------------
def scan_qr_from_frame(frame) -> str | None:
    """Liest einen QR-Code aus einem OpenCV-Frame."""
    try:
        codes = qr_decode(frame)
        for code in codes:
            data = code.data.decode("utf-8").strip()
            if len(data) >= QR_MIN_LEN:
                if QR_PREFIX and not data.startswith(QR_PREFIX):
                    continue
                return data
    except Exception:
        pass
    return None

def wait_for_qr() -> str:
    """
    Öffnet Kamera-Feed und wartet auf QR-Code-Scan.
    Blockiert bis ein gültiger QR gescannt wurde.
    Returns: Token-String aus dem QR-Code
    """
    if not CV2_AVAILABLE:
        # Fallback: Token manuell eingeben (für Tests ohne Kamera)
        log.warning("OpenCV/pyzbar nicht verfügbar — manueller Token-Input-Modus")
        token = input("QR-Token manuell eingeben: ").strip()
        return token

    log.info("Warte auf QR-Code...")
    led_ready()

    cap = cv2.VideoCapture(CAM_INDEX)
    if not cap.isOpened():
        log.error(f"QR-Kamera {CAM_INDEX} konnte nicht geöffnet werden")
        sys.exit(1)

    last_token = None
    last_scan_time = 0.0

    try:
        while True:
            ret, frame = cap.read()
            if not ret:
                time.sleep(0.1)
                continue

            token = scan_qr_from_frame(frame)
            now = time.time()

            if token and token != last_token or (token == last_token and now - last_scan_time > QR_DEBOUNCE_SEC):
                last_token    = token
                last_scan_time = now
                log.info(f"QR-Code erkannt: {token[:16]}...")
                return token

            time.sleep(0.05)  # CPU schonen

    finally:
        cap.release()


# ---------------------------------------------------------------------------
# UPLOAD
# ---------------------------------------------------------------------------
def try_upload(foto_path: str, qr_token: str, shot_at: str) -> bool:
    """Einzelner Upload-Versuch mit Retries."""
    for attempt in range(1, API_RETRIES + 1):
        try:
            with open(foto_path, "rb") as f:
                resp = requests.post(
                    API_URL,
                    headers={"X-OEMM-Foto-Key": API_KEY},
                    data={"token": qr_token, "shot_at": shot_at},
                    files={"foto": (os.path.basename(foto_path), f, "image/jpeg")},
                    timeout=API_TIMEOUT,
                )

            if resp.status_code == 200:
                data = resp.json()
                if data.get("success"):
                    log.info(f"  ✓ Upload OK → foto_id={data.get('foto_id')} user_id={data.get('user_id')}")
                    return True
                else:
                    log.error(f"  ✗ API Fehler: {data.get('message', data)}")
                    return False  # nicht wiederholen

            elif resp.status_code == 429:
                log.warning(f"  Rate-Limit (Versuch {attempt}/{API_RETRIES}) — warte 6s...")
                time.sleep(6)
                continue

            elif resp.status_code in (401, 403):
                log.error(f"  HTTP {resp.status_code} — API Key ungültig?")
                return False  # nicht wiederholen

            elif resp.status_code == 404:
                log.error(f"  HTTP 404 — Token unbekannt oder API-URL falsch")
                return False

            else:
                log.warning(f"  HTTP {resp.status_code} (Versuch {attempt}/{API_RETRIES})")

        except requests.exceptions.ConnectionError:
            log.warning(f"  Kein Internet (Versuch {attempt}/{API_RETRIES})")
        except requests.exceptions.Timeout:
            log.warning(f"  Timeout nach {API_TIMEOUT}s (Versuch {attempt}/{API_RETRIES})")
        except Exception as e:
            log.error(f"  Unerwarteter Fehler: {e}")
            return False

        if attempt < API_RETRIES:
            time.sleep(2 * attempt)

    return False


# ---------------------------------------------------------------------------
# OFFLINE QUEUE
# ---------------------------------------------------------------------------
def queue_load() -> list:
    if not os.path.isfile(QUEUE_FILE):
        return []
    try:
        with open(QUEUE_FILE) as f:
            return json.load(f)
    except Exception:
        return []

def queue_save(queue: list):
    try:
        with open(QUEUE_FILE, "w") as f:
            json.dump(queue, f, indent=2)
    except Exception as e:
        log.error(f"Queue speichern fehlgeschlagen: {e}")

def queue_add(foto_path: str, qr_token: str, shot_at: str):
    q = queue_load()
    q.append({"foto_path": foto_path, "qr_token": qr_token,
               "shot_at": shot_at, "queued_at": datetime.now().isoformat()})
    queue_save(q)
    log.info(f"In Offline-Queue gespeichert ({len(q)} gesamt): {foto_path}")

def queue_process():
    """Queue beim Start abarbeiten."""
    q = queue_load()
    if not q:
        return
    log.info(f"Offline-Queue: {len(q)} Einträge werden hochgeladen...")
    remaining = []
    for entry in q:
        fp  = entry.get("foto_path", "")
        tok = entry.get("qr_token", "")
        sat = entry.get("shot_at", datetime.now().isoformat())
        if not os.path.isfile(fp):
            log.warning(f"Queue-Datei nicht mehr vorhanden: {fp}")
            continue
        if try_upload(fp, tok, sat):
            log.info(f"Queue-Upload OK: {fp}")
        else:
            remaining.append(entry)
        time.sleep(1)
    queue_save(remaining)
    log.info(f"Queue abgearbeitet: {len(q) - len(remaining)} OK, {len(remaining)} verbleibend")


# ---------------------------------------------------------------------------
# HAUPTSCHLEIFE
# ---------------------------------------------------------------------------
RUNNING = True

def handle_signal(sig, frame):
    global RUNNING
    log.info("Beende Fotobox...")
    RUNNING = False

def main():
    global RUNNING

    log.info("=" * 50)
    log.info("  ÖMM XXVI Fotobox gestartet")
    log.info(f"  API: {API_URL}")
    log.info(f"  Backend: {CAM_BACKEND} | Kamera: {CAM_INDEX} | {CAM_RESOLUTION}")
    log.info("=" * 50)

    if not API_KEY:
        log.error("FEHLER: Kein API-Key in config.ini eingetragen!")
        sys.exit(1)

    # Graceful Shutdown
    signal.signal(signal.SIGINT,  handle_signal)
    signal.signal(signal.SIGTERM, handle_signal)

    gpio_setup()

    # Queue abarbeiten (Fotos vom letzten Offline-Betrieb)
    queue_process()

    log.info("Fotobox bereit — warte auf Gäste...")
    led_ready()

    foto_counter = 0

    while RUNNING:
        try:
            # 1. Warte auf QR-Code
            qr_token = wait_for_qr()
            if not qr_token or not RUNNING:
                continue

            # 2. Foto machen
            shot_at   = datetime.now().isoformat()
            foto_path = make_foto()

            if not foto_path:
                log.error("Foto fehlgeschlagen — übersprungen")
                led_error()
                play_sound(SOUND_ERROR)
                time.sleep(2)
                continue

            foto_counter += 1
            log.info(f"Foto #{foto_counter}: {os.path.basename(foto_path)}")

            # 3. Hochladen
            log.info(f"Lade hoch für Token: {qr_token[:16]}...")
            ok = try_upload(foto_path, qr_token, shot_at)

            if ok:
                led_ok()
                play_sound(SOUND_OK)
                log.info("✓ Foto erfolgreich ins Dashboard des Gastes geladen!")
            else:
                # Offline-Queue
                queue_add(foto_path, qr_token, shot_at)
                led_offline()
                play_sound(SOUND_ERROR)
                log.warning("✗ Upload fehlgeschlagen — in Offline-Queue gespeichert")

            # Kurze Pause bevor nächster Gast
            time.sleep(2)
            log.info("-" * 40)
            log.info("Bereit für nächsten Gast...")

        except KeyboardInterrupt:
            break
        except Exception as e:
            log.error(f"Unerwarteter Fehler in Hauptschleife: {e}", exc_info=True)
            time.sleep(3)

    log.info(f"Fotobox beendet. {foto_counter} Fotos gemacht.")
    gpio_cleanup()


if __name__ == "__main__":
    main()
