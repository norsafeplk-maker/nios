import time
from typing import Callable, Any
from logger import info, error


def retry_request(func: Callable[[], Any], attempts: int = 3, delay: int = 2) -> Any:
    if attempts < 1:
        raise ValueError("attempts must be >= 1")

    last_exception = None

    for i in range(1, attempts + 1):
        try:
            return func()
        except Exception as e:
            last_exception = e
            error(f"[RETRY {i}] Failed: {str(e)}")

            if i == attempts:
                error("FINAL FAILURE - giving up")
                break

            sleep_time = delay * i
            info(f"[RETRY] Waiting {sleep_time}s before retry")
            time.sleep(sleep_time)

    raise last_exception
