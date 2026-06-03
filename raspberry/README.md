# ÖMM Fotobox Upload — Raspberry Pi

## Installation

```bash
# Auf dem Raspberry Pi:
pip3 install requests RPi.GPIO

# Dateien ins Fotobox-Verzeichnis kopieren:
cp oemm_fotobox_upload.py /home/pi/fotobox/
cp config.ini             /home/pi/fotobox/
```

## Konfiguration

`config.ini` öffnen und API Key eintragen:
```
api_key = HIER_DEN_KEY_AUS_WP_ADMIN_EINTRAGEN
```

API Key findest du unter: **WP Admin → ÖMM XXVI → Einstellungen → Fotobox API Key**

## Integration ins bestehende Script

```python
from oemm_fotobox_upload import FotoboxUploader

uploader = FotoboxUploader()

# Beim Programmstart — Offline-Queue abarbeiten
uploader.process_queue()

# --- Dein bestehender Code ---
# qr_token = qr_scanner.scan()
# foto_path = camera.capture("/tmp/foto.jpg")
# --- Ende bestehender Code ---

# NEU: nach dem Foto machen
uploader.upload(foto_path=foto_path, qr_token=qr_token)
```

## LED-Feedback

| Farbe | Bedeutung | GPIO Pin |
|-------|-----------|----------|
| 🟢 Grün blinkt 3x | Upload erfolgreich | GPIO 17 |
| 🔴 Rot 2 Sek | Fehler (Token ungültig, etc.) | GPIO 27 |
| 🟡 Gelb blinkt 5x | Kein Internet — offline gespeichert | GPIO 22 |

## Offline-Queue

Wenn kein Internet verfügbar ist, wird das Foto lokal in `/tmp/oemm_queue.json` gespeichert.
Beim nächsten Programmstart werden alle Queue-Einträge automatisch hochgeladen.

## Log-Datei

```bash
tail -f /tmp/oemm_fotobox.log
```

## Test

```bash
# Direkter Test mit einem Foto:
python3 oemm_fotobox_upload.py /path/to/test.jpg DEIN_QR_TOKEN
```
