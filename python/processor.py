import requests

from extract_sales_order import extract_sales_order
from validation import validate_order
from idempotency import generate_key, is_processed, mark_processed
from retry import retry_request
from logger import info, error

API_URL = "https://nios.norsafe.co.za/wp-json/nios/v1/order"
API_KEY = "test123"


def process_email(msg):
    info("=== NIOS PIPELINE START ===")

    for part in msg.walk():
        filename = part.get_filename()

        if filename and filename.lower().endswith(".txt"):
            info(f"[ATTACHMENT] {filename}")

            try:
                raw_bytes = part.get_payload(decode=True)
                content = raw_bytes.decode(errors="ignore")
            except Exception as e:
                error(f"Decode failed: {e}")
                return

            # -----------------------
            # PARSE
            # -----------------------
            order = extract_sales_order(content)

            if not order:
                error("Extraction failed")
                return

            info(f"[PARSED] {order.get('so_number')}")

            # -----------------------
            # IDEMPOTENCY
            # -----------------------
            key = generate_key(order.get("so_number"), raw_bytes)

            if is_processed(key):
                info(f"[SKIP] Duplicate: {order.get('so_number')}")
                return

            # -----------------------
            # VALIDATION
            # -----------------------
            try:
                validate_order(order)
                info("Validation passed")
            except Exception as e:
                error(f"Validation failed: {e}")
                return

            # -----------------------
            # SEND WITH RETRY
            # -----------------------
            payload = {"orders": [order]}

            def do_post():
                res = requests.post(
                    API_URL,
                    json=payload,
                    headers={
                        "Content-Type": "application/json",
                        "X-NIOS-KEY": API_KEY
                    },
                    timeout=10
                )

                if res.status_code >= 400:
                    raise Exception(f"{res.status_code} {res.text}")

                return res

            try:
                response = retry_request(do_post)

                info(f"[POST OK] {response.status_code}")

                mark_processed(key)
                info("Marked as processed")

            except Exception as e:
                error(f"POST failed permanently: {e}")

            return

    error("No valid .txt attachment found")
