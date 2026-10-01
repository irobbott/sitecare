#!/usr/bin/env python3
"""Send a signed example backup event to a SiteCare development instance."""

import hashlib
import hmac
import json
import os
import time
import urllib.error
import urllib.request
import uuid

base_url = os.environ.get("SITECARE_URL", "http://localhost:8000").rstrip("/")
website_id = os.environ["SITECARE_WEBSITE_ID"]
secret = os.environ["SITECARE_BACKUP_SECRET"].encode("utf-8")
timestamp = str(int(time.time()))
event_id = str(uuid.uuid4())
payload = {
    "type": "full_site",
    "status": "completed",
    "completed_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    "verified": False,
    "notes": "Sent by the SiteCare sample integration.",
}
body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
message = timestamp.encode() + b"\n" + event_id.encode() + b"\n" + body
signature = hmac.new(secret, message, hashlib.sha256).hexdigest()
request = urllib.request.Request(
    f"{base_url}/api/v1/webhooks/websites/{website_id}/backups",
    data=body,
    headers={
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-SiteCare-Timestamp": timestamp,
        "X-SiteCare-Event-Id": event_id,
        "X-SiteCare-Signature": signature,
    },
    method="POST",
)

try:
    with urllib.request.urlopen(request, timeout=10) as response:
        print(f"SiteCare accepted the event (HTTP {response.status}).")
        print(response.read().decode("utf-8"))
except urllib.error.HTTPError as error:
    print(f"SiteCare rejected the event (HTTP {error.code}).")
    print(error.read().decode("utf-8"))
