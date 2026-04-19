import time
from logger import info, error

def retry_request(func, attempts=3, delay=2):
    for i in range(1, attempts + 1):
        try:
            return func()
        except Exception as e:
            error(f"[RETRY {i}] Failed: {e}")

            if i == attempts:
                error("? FINAL FAILURE — giving up")
                raise

            sleep_time = delay * i
            info(f"? Retrying in {sleep_time}s...")
            time.sleep(sleep_time)
