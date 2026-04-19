from __future__ import annotations

import json
import hashlib
from pathlib import Path

STORE_FILE = Path("processed_store.json")


def _load():
    if not STORE_FILE.exists():
        return set()

    try:
        data = json.loads(STORE_FILE.read_text())
        return set(data)
    except:
        return set()


def _save(store: set):
    STORE_FILE.write_text(json.dumps(list(store), indent=2))


def generate_key(so_number: str, content: bytes) -> str:
    checksum = hashlib.md5(content).hexdigest()
    return f"{so_number}:{checksum}"


def is_processed(key: str) -> bool:
    store = _load()
    return key in store


def mark_processed(key: str):
    store = _load()
    store.add(key)
    _save(store)
