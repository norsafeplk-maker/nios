from __future__ import annotations

import re


# =========================
# CORE VALIDATION
# =========================

def validate_so_number(value: str):
    if not value:
        raise ValueError("Missing SO number")

    if not re.fullmatch(r"SO\d{4,}", value):
        raise ValueError(f"Invalid SO format: {value}")


def validate_customer(value: str):
    if not value:
        raise ValueError("Missing customer")

    if value.upper() in ["UNKNOWN CUSTOMER", "N/A", "NONE"]:
        raise ValueError("Invalid customer value")


def validate_date(value: str):
    if not value:
        raise ValueError("Missing creation date")

    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", value):
        raise ValueError(f"Invalid date format: {value}")


def validate_lines(lines: list):
    if not isinstance(lines, list) or len(lines) == 0:
        raise ValueError("No line items found")

    for i, line in enumerate(lines):

        name = str(line.get("product_name", "")).strip()
        qty = line.get("quantity")

        if not name:
            raise ValueError(f"Line {i}: Missing product_name")

        if not isinstance(qty, int) or qty <= 0:
            raise ValueError(f"Line {i}: Invalid quantity")


# =========================
# MAIN ENTRY
# =========================

def validate_order(order: dict):
    if not isinstance(order, dict):
        raise ValueError("Order is not a dict")

    validate_so_number(order.get("so_number"))
    validate_customer(order.get("customer"))
    validate_date(order.get("creation_date"))
    validate_lines(order.get("lines"))

    return True
