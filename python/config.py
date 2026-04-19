import os
from dotenv import load_dotenv

load_dotenv()

IMAP_SERVER = os.getenv("IMAP_SERVER")
IMAP_PORT = int(os.getenv("IMAP_PORT", 993))
EMAIL_USER = os.getenv("EMAIL_USER")
EMAIL_PASS = os.getenv("EMAIL_PASS")

NIOS_API_URL = os.getenv("NIOS_API_URL")
NIOS_API_KEY = os.getenv("NIOS_API_KEY")

POLL_SECONDS = int(os.getenv("POLL_SECONDS", 60))
DOWNLOAD_DIR = os.getenv("DOWNLOAD_DIR", "downloads")
BACKUP_OUTPUT = os.getenv("BACKUP_OUTPUT", "output/order_feed.json")
