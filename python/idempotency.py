from __future__ import annotations

import json
import hashlib
from pathlib import Path
from typing import Set

STORE_FILE = Path("processed_store.json")


def _load() -> Set[str]:
    if not STORE_FILE.exists():
        return set()

    try:
        data = json.loads(STORE_FILE.read_text(encoding="utf-8"))
        return set(data)
    except Exception:
        return set()


def _save(store: Set[str]) -> None:
    STORE_FILE.write_text(
        json.dumps(list(store), indent=2),
        encoding="utf-8"
    )


def generate_key(so_number: str, content: str) -> str:
    base = f"{so_number}:{content}"
    return hashlib.sha256(base.encode("utf-8")).hexdigest()


def is_processed(key: str) -> bool:
    store = _load()
    return key in store


def mark_processed(key: str) -> None:
    store = _load()
    store.add(key)
    _save(store)
