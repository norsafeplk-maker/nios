from __future__ import annotations

import re
from typing import Optional


# =========================
# HELPERS
# =========================

def clean(value: str) -> str:
    return re.sub(r"\s+", " ", (value or "").strip())


def normalize_date(raw: str) -> str:
    match = re.match(r"^(\d{2})/(\d{2})/(\d{4})$", (raw or "").strip())
    if match:
        d, m, y = match.groups()
        return f"{y}-{m}-{d}"
    return raw


def split_cols(line: str):
    return [p.strip() for p in re.split(r"\t+|\s{2,}", line) if p.strip()]


# =========================
# CORE FIELD EXTRACTION
# =========================

def extract_so_number(text: str) -> Optional[str]:
    m = re.search(r"NUMBER:\s*(SO\d+)", text, re.IGNORECASE)
    return m.group(1).upper() if m else None


def extract_date(text: str) -> Optional[str]:
    m = re.search(r"DATE:\s*(\d{2}/\d{2}/\d{4})", text, re.IGNORECASE)
    return normalize_date(m.group(1)) if m else None


def extract_customer(text: str) -> Optional[str]:
    lines = text.splitlines()

    for i, line in enumerate(lines):
        if "FROM" in line.upper() and "TO" in line.upper():

            for j in range(i + 1, i + 10):
                if j >= len(lines):
                    break

                cols = split_cols(lines[j])

                if len(cols) >= 2:
                    candidate = clean(cols[-1])

                    blocked = [
                        "VAT", "ADDRESS", "TEL", "CELL",
                        "POSTAL", "DELIVERY"
                    ]

                    if candidate and not any(b in candidate.upper() for b in blocked):
                        return candidate

    return None


# =========================
# LINE EXTRACTION (STRICT)
# =========================

def extract_lines(text: str) -> list[dict]:
    results = []
    lines = text.splitlines()

    in_table = False

    for raw in lines:
        line = raw.strip()
        upper = line.upper()

        if not line:
            continue

        # Detect start of item table
        if "DESCRIPTION" in upper and "QUANTITY" in upper:
            in_table = True
            continue

        if not in_table:
            continue

        # Stop at totals section
        if any(x in upper for x in [
            "TOTAL DISCOUNT",
            "TOTAL EXCLUSIVE",
            "TOTAL VAT",
            "SUB TOTAL",
            "GRAND TOTAL",
            "BALANCE DUE"
        ]):
            break

        cols = split_cols(raw)

        if len(cols) < 2:
            continue

        description = clean(cols[0])
        qty_raw = cols[1].replace(",", "").strip()

        if not re.fullmatch(r"\d+", qty_raw):
            continue

        quantity = int(qty_raw)

        results.append({
            "product_name": description,
            "quantity": quantity
        })

    return results


# =========================
# INDICATOR DETECTION
# =========================

def detect_indicators(lines: list[dict]) -> dict:
    indicators = {
        "EMB": False,
        "SEW": False,
        "CON-DE": False,
        "PRINT": False,
        "SUB": False
    }

    for line in lines:
        name = str(line.get("product_name", "")).upper()

        if "EMB" in name or "EMBROIDERY" in name:
            indicators["EMB"] = True

        if "SEW" in name or "ALTERATION" in name:
            indicators["SEW"] = True

        if "CON-DE" in name or "DESIGN" in name:
            indicators["CON-DE"] = True

        if "PRINT" in name:
            indicators["PRINT"] = True

        if "SUB" in name or "SUBLIMATION" in name:
            indicators["SUB"] = True

    return indicators


# =========================
# MAIN ENTRY
# =========================

def extract_sales_order(content: str) -> Optional[dict]:
    try:
        so_number = extract_so_number(content)
        customer = extract_customer(content)
        creation_date = extract_date(content)
        lines = extract_lines(content)

        if not so_number:
            print("❌ Missing SO number")
            return None

        if not customer:
            customer = "UNKNOWN CUSTOMER"

        if not creation_date:
            creation_date = ""

        if not lines:
            print("❌ No valid line items")
            return None

        indicators = detect_indicators(lines)

        return {
            "so_number": so_number,
            "customer": customer,
            "creation_date": creation_date,
            "lines": lines,
            "indicators": indicators
        }

    except Exception as e:
        print(f"❌ Extraction error: {e}")
        return None
