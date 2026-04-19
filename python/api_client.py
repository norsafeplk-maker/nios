import requests
from config import NIOS_API_URL, NIOS_API_KEY

def send_to_api(payload):
    headers = {
        "Content-Type": "application/json",
        "X-NIOS-KEY": NIOS_API_KEY
    }

    response = requests.post(NIOS_API_URL, json=payload, headers=headers)

    if response.status_code != 200:
        raise Exception(f"API Error: {response.status_code} {response.text}")

    return response.json()
