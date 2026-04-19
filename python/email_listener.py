import imaplib
import email
import time
import os

from config import *
from parser import parse_pdf
from validation import validate
from idempotency import generate_key, is_processed, mark_processed
from api_client import send_to_api

def connect():
    mail = imaplib.IMAP4_SSL(IMAP_SERVER, IMAP_PORT)
    mail.login(EMAIL_USER, EMAIL_PASS)
    return mail

def process_email(msg):
    subject = msg["subject"]

    if not subject.startswith("Quote -"):
        return

    for part in msg.walk():
        if part.get_content_disposition() == "attachment":
            filename = part.get_filename()
            if filename.endswith(".pdf"):
                filepath = os.path.join(DOWNLOAD_DIR, filename)

                with open(filepath, "wb") as f:
                    f.write(part.get_payload(decode=True))

                with open(filepath, "rb") as f:
                    content = f.read()

                data = parse_pdf(filepath)
                validate(data)

                key = generate_key(data["quote_number"], content)

                if is_processed(key):
                    print("Duplicate skipped:", key)
                    return

                send_to_api(data)
                mark_processed(key)

                print("Processed:", data["quote_number"])

def run():
    while True:
        try:
            mail = connect()
            mail.select("inbox")

            _, messages = mail.search(None, "UNSEEN")

            for num in messages[0].split():
                _, data = mail.fetch(num, "(RFC822)")
                msg = email.message_from_bytes(data[0][1])
                process_email(msg)

            mail.logout()

        except Exception as e:
            print("ERROR:", e)

        time.sleep(POLL_SECONDS)

if __name__ == "__main__":
    run()
