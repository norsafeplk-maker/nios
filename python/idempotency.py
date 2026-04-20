from __future__ import annotations

def generate_key(so_number: str, content: str = "") -> str:
    # 🔴 Keep interface alive but not used
    return so_number


def is_processed(so_number: str) -> bool:
    # 🔴 TEMP OVERRIDE: allow ALL orders through
    return False


def mark_processed(key: str):
    # 🔴 DISABLED
    pass
