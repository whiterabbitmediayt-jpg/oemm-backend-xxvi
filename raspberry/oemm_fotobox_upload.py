#!/usr/bin/env python3
"""
oemm_fotobox_upload.py
======================
Ergänzungsmodul für das bestehende Fotobox-Script.

VERWENDUNG im bestehenden Script:
    from oemm_fotobox_upload import FotoboxUploader

    uploader = FotoboxUploader()

    # Nach dem Foto machen:
    uploader.upload(foto_path="/tmp/foto.jpg", qr_token="abc123...")

    # Beim Programmstart (Queue abarbeiten):
    uploader.process_queue()

LED-Feedback (optional, falls GPIO verfügbar):
    Grün  (GPIO 17) = Upload OK
    Rot   (GPIO 27) = Upload fehlgeschlagen
    Gelb  (GPIO 22) = Offline gespeichert (Queue)
"""

import os
import json
import time
import logging
import configparser
import requests
from datetime import datetime
from pathlib import Path

# Logging einrichten
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler("/tmp/oemm_fotobox.log"),
    ],
)
log = logging.getLogger("oemm_fotobox")

# GPIO optional (läuft auch ohne Pi-Hardware)
try:
    import RPi.GPIO as GPIO
    GPIO_AVAILABLE = True
except ImportError:
    GPIO_AVAILABLE = False

# --- GPIO Pins ---
PIN_GREEN  = 17  # Upload OK
PIN_RED    = 27  # Fehler
PIN_YELLOW = 22  # Offline gespeichert


class FotoboxUploader:
    """Kümmert sich um Upload zum WordPress Plugin + Offline-Queue."""

    def __init__(self, config_path: str = None):
        # Config laden
        self.cfg = configparser.ConfigParser()
        if config_path is None:
            # Suche config.ini im selben Verzeichnis wie dieses Script
            script_dir = Path(__file__).parent
            config_path = str(script_dir / "config.ini")

        if not self.cfg.read(config_path):
            log.warning(f"Config nicht gefunden: {config_path} — verwende Defaults")

        self.api_url    = self.cfg.get("fotobox", "api_url",
                                        fallback="https://mopedmarathon.at/wp-json/oemm-xxvi/v1/foto/upload")
        self.api_key    = self.cfg.get("fotobox", "api_key", fallback="")
        self.queue_file = self.cfg.get("fotobox", "queue_file", fallback="/tmp/oemm_queue.json")
        self.max_bytes  = self.cfg.getint("fotobox", "max_file_mb", fallback=25) * 1024 * 1024
        self.timeout    = self.cfg.getint("fotobox", "timeout_sec", fallback=30)
        self.retries    = self.cfg.getint("fotobox", "retry_count", fallback=3)

        # GPIO initialisieren
        if GPIO_AVAILABLE:
            GPIO.setmode(GPIO.BCM)
            GPIO.setwarnings(False)
            for pin in (PIN_GREEN, PIN_RED, PIN_YELLOW):
                GPIO.setup(pin, GPIO.OUT)
                GPIO.output(pin, GPIO.LOW)

    # ------------------------------------------------------------------
    # PUBLIC API
    # ------------------------------------------------------------------

    def upload(self, foto_path: str, qr_token: str) -> bool:
        """
        Lädt ein Foto zum WordPress Plugin hoch.
        Bei Fehler: in Offline-Queue speichern.

        Args:
            foto_path:  Pfad zur JPEG/PNG Datei
            qr_token:   Inhalt des gescannten QR Codes

        Returns:
            True wenn Upload erfolgreich, False wenn in Queue gespeichert
        """
        log.info(f"Upload: {foto_path} | Token: {qr_token[:12]}...")

        # Datei-Checks
        if not os.path.isfile(foto_path):
            log.error(f"Datei nicht gefunden: {foto_path}")
            self._led_error()
            return False

        file_size = os.path.getsize(foto_path)
        if file_size > self.max_bytes:
            log.error(f"Datei zu gross: {file_size / 1024 / 1024:.1f} MB")
            self._led_error()
            return False

        if not self.api_key:
            log.error("Kein API Key konfiguriert!")
            self._led_error()
            return False

        # Upload versuchen
        shot_at = datetime.now().isoformat()
        success = self._try_upload(foto_path, qr_token, shot_at)

        if success:
            log.info(f"Upload OK: {os.path.basename(foto_path)}")
            self._led_ok()
            return True
        else:
            # In Queue speichern
            self._queue_add(foto_path, qr_token, shot_at)
            log.warning(f"Upload fehlgeschlagen — in Queue gespeichert: {foto_path}")
            self._led_offline()
            return False

    def process_queue(self) -> int:
        """
        Arbeitet die Offline-Queue ab (beim Programmstart aufrufen).

        Returns:
            Anzahl erfolgreich hochgeladener Einträge
        """
        queue = self._queue_load()
        if not queue:
            return 0

        log.info(f"Queue: {len(queue)} Einträge gefunden, starte Upload...")
        success_count = 0
        remaining     = []

        for entry in queue:
            foto_path = entry.get("foto_path", "")
            qr_token  = entry.get("qr_token", "")
            shot_at   = entry.get("shot_at", datetime.now().isoformat())

            if not os.path.isfile(foto_path):
                log.warning(f"Queue-Datei nicht mehr vorhanden: {foto_path} — übersprungen")
                continue

            if self._try_upload(foto_path, qr_token, shot_at):
                log.info(f"Queue-Upload OK: {foto_path}")
                success_count += 1
            else:
                log.warning(f"Queue-Upload fehlgeschlagen: {foto_path} — bleibt in Queue")
                remaining.append(entry)

            time.sleep(1)  # kurze Pause zwischen Queue-Uploads

        self._queue_save(remaining)
        log.info(f"Queue abgearbeitet: {success_count} OK, {len(remaining)} verbleibend")
        return success_count

    def queue_count(self) -> int:
        """Anzahl der Einträge in der Offline-Queue."""
        return len(self._queue_load())

    def cleanup_gpio(self):
        """GPIO aufräumen (beim Programmende aufrufen)."""
        if GPIO_AVAILABLE:
            GPIO.cleanup()

    # ------------------------------------------------------------------
    # PRIVAT
    # ------------------------------------------------------------------

    def _try_upload(self, foto_path: str, qr_token: str, shot_at: str) -> bool:
        """Einzelner Upload-Versuch mit Retries."""
        for attempt in range(1, self.retries + 1):
            try:
                with open(foto_path, "rb") as f:
                    response = requests.post(
                        self.api_url,
                        headers={"X-OEMM-Foto-Key": self.api_key},
                        data={
                            "token":   qr_token,
                            "shot_at": shot_at,
                        },
                        files={"foto": (os.path.basename(foto_path), f, "image/jpeg")},
                        timeout=self.timeout,
                    )

                if response.status_code == 200:
                    data = response.json()
                    if data.get("success"):
                        log.debug(f"API Response: foto_id={data.get('foto_id')} user_id={data.get('user_id')} "
                                  f"token_type={data.get('token_type')} upload_ms={data.get('upload_ms')}")
                        return True
                    else:
                        log.error(f"API Fehler: {data}")
                        return False  # API-Fehler nicht wiederholen (z.B. Token nicht gefunden)

                elif response.status_code == 429:
                    log.warning(f"Rate Limit — warte 6 Sekunden (Versuch {attempt}/{self.retries})")
                    time.sleep(6)
                    continue

                elif response.status_code in (401, 403, 404):
                    log.error(f"HTTP {response.status_code} — nicht wiederholbar: {response.text[:200]}")
                    return False  # Authentifizierung / Token-Fehler nicht wiederholen

                else:
                    log.warning(f"HTTP {response.status_code} (Versuch {attempt}/{self.retries}): {response.text[:100]}")

            except requests.exceptions.ConnectionError:
                log.warning(f"Verbindungsfehler (Versuch {attempt}/{self.retries}) — kein Internet?")
            except requests.exceptions.Timeout:
                log.warning(f"Timeout nach {self.timeout}s (Versuch {attempt}/{self.retries})")
            except Exception as e:
                log.error(f"Unerwarteter Fehler: {e}")
                return False

            if attempt < self.retries:
                time.sleep(2 * attempt)  # exponentielles Backoff

        return False

    def _queue_load(self) -> list:
        """Lädt die Queue aus der JSON-Datei."""
        if not os.path.isfile(self.queue_file):
            return []
        try:
            with open(self.queue_file, "r") as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            return []

    def _queue_save(self, queue: list):
        """Speichert die Queue in die JSON-Datei."""
        try:
            with open(self.queue_file, "w") as f:
                json.dump(queue, f, indent=2)
        except IOError as e:
            log.error(f"Queue speichern fehlgeschlagen: {e}")

    def _queue_add(self, foto_path: str, qr_token: str, shot_at: str):
        """Fügt einen Eintrag zur Queue hinzu."""
        queue = self._queue_load()
        queue.append({
            "foto_path": foto_path,
            "qr_token":  qr_token,
            "shot_at":   shot_at,
            "queued_at": datetime.now().isoformat(),
        })
        self._queue_save(queue)

    # --- LED Feedback ---
    def _led_on(self, pin: int, duration: float = 1.5):
        if not GPIO_AVAILABLE:
            return
        GPIO.output(pin, GPIO.HIGH)
        time.sleep(duration)
        GPIO.output(pin, GPIO.LOW)

    def _led_blink(self, pin: int, times: int = 3, interval: float = 0.3):
        if not GPIO_AVAILABLE:
            return
        for _ in range(times):
            GPIO.output(pin, GPIO.HIGH)
            time.sleep(interval)
            GPIO.output(pin, GPIO.LOW)
            time.sleep(interval)

    def _led_ok(self):
        """Grün blinken = Upload OK."""
        self._led_blink(PIN_GREEN, times=3, interval=0.2)

    def _led_error(self):
        """Rot dauerhaft = Fehler."""
        self._led_on(PIN_RED, duration=2.0)

    def _led_offline(self):
        """Gelb blinken = offline gespeichert."""
        self._led_blink(PIN_YELLOW, times=5, interval=0.15)


# ------------------------------------------------------------------
# DIREKTER AUFRUF (Test / Standalone)
# ------------------------------------------------------------------
if __name__ == "__main__":
    import sys

    uploader = FotoboxUploader()

    # Beim Start: Queue abarbeiten
    queue_count = uploader.queue_count()
    if queue_count > 0:
        log.info(f"Starte mit {queue_count} Einträgen in der Queue...")
        uploader.process_queue()

    # Argument-Modus: python3 oemm_fotobox_upload.py /path/foto.jpg QR_TOKEN
    if len(sys.argv) == 3:
        foto_path = sys.argv[1]
        qr_token  = sys.argv[2]
        result = uploader.upload(foto_path, qr_token)
        sys.exit(0 if result else 1)
    else:
        print("Verwendung: python3 oemm_fotobox_upload.py <foto_path> <qr_token>")
        print("Oder als Modul: from oemm_fotobox_upload import FotoboxUploader")
        sys.exit(0)
