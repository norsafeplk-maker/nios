# retry.py

import time
from typing import Callable, Any
from logger import info, error


def retry_request(func: Callable[[], Any], attempts: int = 3, delay: int = 2) -> Any:
    """
    Retry wrapper for network/API calls.

    Rules:
    - Executes function up to `attempts` times
    - Linear backoff: delay * attempt_number
    - Logs every failure
    - Raises final exception if all attempts fail
    """

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
            info(f"[RETRY] Waiting {sleep_time}s before next attempt")
            time.sleep(sleep_time)

    # Final failure — raise original exception
    raise last_exception
