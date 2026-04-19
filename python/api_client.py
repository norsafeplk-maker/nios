import json
import requests
from config import NIOS_API_URL, NIOS_API_KEY


def _normalize(payload: dict) -> dict:
    so_number = payload.get("so_number")
    customer = payload.get("customer")
    creation_date = payload.get("creation_date")

    lines = payload.get("lines") or []
    if not isinstance(lines, list):
        lines = []

    normalized_lines = []
    for ln in lines:
        normalized_lines.append({
            "product_name": ln.get("product_name") or "",
            "quantity": int(ln.get("quantity") or 0)
        })

    return {
        "so_number": so_number,
        "customer": customer,
        "creation_date": creation_date,
        "lines": normalized_lines
    }


def send_to_api(payload: dict):
    headers = {
        "Content-Type": "application/json",
        "X-NIOS-KEY": NIOS_API_KEY
    }

    clean = _normalize(payload)

    if not clean["so_number"]:
        raise Exception("Payload missing so_number")

    if not clean["customer"]:
        raise Exception("Payload missing customer")

    if not clean["creation_date"]:
        raise Exception("Payload missing creation_date")

    if not clean["lines"]:
        raise Exception("Payload has no lines")

    wrapped = {
        "orders": [clean]
    }

    print("\n=== PAYLOAD TO API ===")
    print(json.dumps(wrapped, indent=2))
    print("======================\n")

    response = requests.post(
        NIOS_API_URL,
        json=wrapped,
        headers=headers,
        timeout=10
    )

    if response.status_code != 200:
        print("\n=== API ERROR RESPONSE ===")
        print(response.text)
        print("==========================\n")
        raise Exception(f"API Error: {response.status_code} {response.text}")

    return response.json()
