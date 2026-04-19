from __future__ import annotations
from pathlib import Path
from datetime import datetime

LOG_DIR = Path("logs")
LOG_DIR.mkdir(exist_ok=True)

def _write(file: str, message: str):
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    path = LOG_DIR / file

    with open(path, "a", encoding="utf-8") as f:
        f.write(f"[{ts}] {message}\n")

def info(msg: str):
    print(msg)
    _write("events.log", msg)

def error(msg: str):
    print(msg)
    _write("errors.log", msg)
