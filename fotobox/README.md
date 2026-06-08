# ÖMM XXVI Fotobox — Installationsanleitung

## Was das Script macht

1. Kamera-Feed öffnen und auf QR-Code warten
2. Gast scannt seinen QR-Code aus der **Besenwagen App**
3. Countdown (3 Sekunden) → Foto
4. Foto wird automatisch ins **ÖMM Dashboard** des Gastes hochgeladen
5. LED-Feedback: ✅ Grün = OK | ❌ Rot = Fehler | 🟡 Gelb = offline gespeichert
6. Wenn kein Internet: Foto wird in Offline-Queue gespeichert und beim nächsten Start automatisch hochgeladen

---

## Installation

### 1. Abhängigkeiten installieren

```bash
# System-Pakete (einmalig)
sudo apt-get update
sudo apt-get install -y python3-pip libzbar0

# Python-Pakete
pip3 install -r requirements.txt

# Nur auf Raspberry Pi (für LED-Feedback):
pip3 install RPi.GPIO
```

### 2. config.ini anpassen

Die `config.ini` ist bereits mit dem richtigen API-Key vorkonfiguriert.

**Wichtig:** `backend` je nach Hardware anpassen:
- `opencv` → USB-Kamera / Webcam (Standard)
- `libcamera` → Raspberry Pi Kameramodul (ribbon cable)
- `fswebcam` → einfache USB-Kamera

```ini
[camera]
backend = opencv    # oder: libcamera, fswebcam
index = 0           # 0 = erste Kamera
resolution = 1920x1080
countdown_sec = 3
```

### 3. Script starten

```bash
python3 oemm_fotobox.py
```

### 4. Autostart beim Boot (optional)

```bash
# systemd Service erstellen
sudo nano /etc/systemd/system/oemm-fotobox.service
```

Inhalt:
```ini
[Unit]
Description=OMM XXVI Fotobox
After=network.target

[Service]
ExecStart=/usr/bin/python3 /home/pi/oemm-fotobox/oemm_fotobox.py
WorkingDirectory=/home/pi/oemm-fotobox
Restart=always
RestartSec=5
User=pi

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable oemm-fotobox
sudo systemctl start oemm-fotobox

# Status prüfen:
sudo systemctl status oemm-fotobox

# Logs ansehen:
journalctl -u oemm-fotobox -f
```

---

## LED-Verkabelung (Raspberry Pi)

| Farbe  | GPIO (BCM) | Bedeutung              |
|--------|-----------|------------------------|
| Grün   | 17        | Upload OK ✅           |
| Rot    | 27        | Fehler ❌              |
| Gelb   | 22        | Offline gespeichert 🟡 |

Widerstand: 330Ω pro LED zwischen GPIO-Pin und LED-Anode.

---

## Testen ohne Kamera

```bash
# Test-Upload direkt (Foto-Pfad + Token manuell)
python3 oemm_fotobox.py
# → fragt nach Token wenn OpenCV nicht verfügbar
```

Oder den Upload-Test direkt:
```bash
# Einzelnen Upload testen
python3 -c "
import sys; sys.path.insert(0,'.')
from oemm_fotobox import try_upload
ok = try_upload('test.jpg', 'DEIN_TOKEN_HIER', '2026-06-26T10:00:00')
print('OK' if ok else 'FEHLER')
"
```

---

## Logs

```bash
# Live-Logs
tail -f /tmp/oemm_fotobox.log

# Offline-Queue anzeigen
cat /tmp/oemm_queue.json
```

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| `Kamera 0 konnte nicht geöffnet werden` | `ls /dev/video*` prüfen, ggf. `index = 1` in config.ini |
| `API Key ungültig` | Key in `config.ini` prüfen |
| `Token unbekannt` | Gast ist kein ÖMM-Teilnehmer oder QR-Code falsch |
| `libzbar0 fehlt` | `sudo apt-get install libzbar0` |
| Kein Foto bei libcamera | `libcamera-hello` zum Testen, ggf. `sudo raspi-config` → Camera aktivieren |

---

## Kontakt

Technische Fragen: WhiteRabbitMedia / Manuel Ribis
API-Dokumentation: `https://mopedmarathon.at/wp-json/oemm-xxvi/v1/`
