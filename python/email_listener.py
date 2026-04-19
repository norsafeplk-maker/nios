# email_listener.py

import imaplib
import email
import os
import time

from config import (
    IMAP_SERVER,
    IMAP_PORT,
    EMAIL_USER,
    EMAIL_PASS,
    DOWNLOAD_DIR,
    POLL_SECONDS,
)

from extract_sales_order import extract_sales_order
from validation import validate_order
from idempotency import is_processed, mark_processed, generate_key
from retry import retry_request
from api_client import send_to_api
from logger import info, error


def connect():
    mail = imaplib.IMAP4_SSL(IMAP_SERVER, IMAP_PORT)
    mail.login(EMAIL_USER, EMAIL_PASS)
    mail.select("inbox")
    return mail


def process_email(msg):
    for part in msg.walk():
        if part.get_content_disposition() == "attachment":
            filename = part.get_filename()

            if filename and filename.lower().endswith(".txt"):
                filepath = os.path.join(DOWNLOAD_DIR, filename)

                # Save attachment
                with open(filepath, "wb") as f:
                    f.write(part.get_payload(decode=True))

                # Read as TEXT (NOT binary)
                with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()

                info(f"[ATTACHMENT FOUND] {filename}")

                # Extract structured data
                data = extract_sales_order(content)

                so_number = data.get("so_number")

                if not so_number:
                    error("Missing so_number - skipping")
                    return

                # Validate
                validate_order(data)

                # Idempotency key
                key = generate_key(so_number, content)

                if is_processed(key):
                    info(f"[SKIP] Duplicate: {so_number}")
                    return

                # Send to API with retry
                def send():
                    return send_to_api(data)

                response = retry_request(send)

                info(f"[POST OK] {response}")

                # Mark processed ONLY after success
                mark_processed(key)

                info(f"Marked as processed: {so_number}")


def main():
    info("=== NIOS PIPELINE START ===")

    while True:
        try:
            mail = connect()

            status, messages = mail.search(None, "UNSEEN")

            if status != "OK":
                error("Failed to search inbox")
                time.sleep(POLL_SECONDS)
                continue

            email_ids = messages[0].split()

            if not email_ids:
                info("Checking inbox... No new emails")
                time.sleep(POLL_SECONDS)
                continue

            for eid in email_ids:
                status, msg_data = mail.fetch(eid, "(RFC822)")

                if status != "OK":
                    error(f"Failed to fetch email ID {eid}")
                    continue

                msg = email.message_from_bytes(msg_data[0][1])

                process_email(msg)

                info("Processed email")

        except Exception as e:
            error(f"Pipeline error: {str(e)}")

        time.sleep(POLL_SECONDS)


if __name__ == "__main__":
    main()