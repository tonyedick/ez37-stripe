#!/usr/bin/env python3
"""
EZ37 Payments — Phase 10 automated checks against the LOCAL DDEV site.

Runs the parts of the test matrix (docs/test-matrix.md) that can be checked
without a person: HTTP-level checkout validation, signed and unsigned webhook
requests, fulfilment, downloads, access control, the status endpoint and a
secrets scan. Everything else is in the manual part of the matrix.

Usage (from the repository root, with `ddev start` done and checkout enabled):

    python3 bin/phase10-local.py

Safety:
- Talks only to the local DDEV site and the Stripe SANDBOX (via the plugin).
- Refuses to run unless the plugin is in test mode.
- Reads the sandbox webhook signing secret from the DDEV web container to sign
  test events. The secret is kept in memory only and is NEVER printed or saved.
- Creates test transactions with customer emails phase10+<run>@example.com.
  Some are marked "paid" by signed test events (no real payment). They are
  sandbox data only; delete them with `ddev wp db query` if you want a clean list.
- Writes a results file to build/phase10-results.md (git-ignored).

Standard library only (Python 3.9+; macOS's python3 is fine).
"""

from __future__ import annotations

import hashlib
import hmac
import http.client
import json
import os
import re
import secrets
import ssl
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request

RUN = secrets.token_hex(3)
OFFERING = "leadership-coaching"
CHECKOUT_PATH = "/checkout/"
SUCCESS_PATH = "/payment-complete/"
WEBHOOK_PATH = "/wp-json/ez37/v1/stripe/webhook"
STATUS_PATH = "/wp-json/ez37/v1/payment-status"
SECRET_PATTERN = re.compile(r"(sk|rk)_(test|live)_[A-Za-z0-9]{10,}|whsec_[A-Za-z0-9]{10,}|ez37_access=[a-f0-9]{64}")

results: list[tuple[str, str, str, str]] = []  # (id, scenario, PASS/FAIL/SKIP, detail)


# --------------------------------------------------------------------------
# Small helpers
# --------------------------------------------------------------------------

def record(test_id: str, scenario: str, ok: bool | None, detail: str = "") -> None:
    status = "SKIP" if ok is None else ("PASS" if ok else "FAIL")
    results.append((test_id, scenario, status, detail))
    mark = {"PASS": "\033[32mPASS\033[0m", "FAIL": "\033[31mFAIL\033[0m", "SKIP": "\033[33mSKIP\033[0m"}[status]
    print(f"  {mark}  {test_id:<7} {scenario}" + (f" — {detail}" if detail else ""))


def run(cmd: list[str], check: bool = True) -> str:
    proc = subprocess.run(cmd, capture_output=True, text=True)
    if check and proc.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd[:3])} …\n{proc.stderr.strip()[:500]}")
    return proc.stdout


def die(message: str) -> None:
    print(f"\n\033[31mCannot run:\033[0m {message}")
    sys.exit(2)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):  # noqa: D401
        return None


class Http:
    def __init__(self, base: str, cafile: str | None):
        self.base = base.rstrip("/")
        ctx = ssl.create_default_context(cafile=cafile) if cafile else ssl.create_default_context()
        self.opener = urllib.request.build_opener(NoRedirect, urllib.request.HTTPSHandler(context=ctx))

    def request(self, method: str, path: str, data: bytes | None = None, headers: dict | None = None):
        url = path if path.startswith("http") else self.base + path
        req = urllib.request.Request(url, data=data, method=method, headers=headers or {})
        start = time.monotonic()
        try:
            resp = self.opener.open(req, timeout=60)
            status, hdrs, body = resp.status, resp.headers, resp.read()
        except urllib.error.HTTPError as e:
            status, hdrs, body = e.code, e.headers, e.read()
        return status, hdrs, body, time.monotonic() - start


# --------------------------------------------------------------------------
# Environment discovery
# --------------------------------------------------------------------------

def discover():
    if not os.path.isdir(".ddev"):
        die("run this from the repository root (the folder that contains .ddev/).")

    try:
        desc = json.loads(run(["ddev", "describe", "-j"]))
    except Exception as e:  # noqa: BLE001
        die(f"`ddev describe` failed — is DDEV running? ({e})")

    raw = desc.get("raw", {})
    base = raw.get("primary_url") or raw.get("httpsurl") or ""
    if not base.startswith("https://"):
        die("could not find the site's https URL from `ddev describe`.")

    cafile = None
    try:
        caroot = run(["mkcert", "-CAROOT"]).strip()
        if os.path.isfile(os.path.join(caroot, "rootCA.pem")):
            cafile = os.path.join(caroot, "rootCA.pem")
    except Exception:  # noqa: BLE001
        pass

    prefix = run(["ddev", "wp", "db", "prefix", "--quiet"]).strip() or "wp_"

    mode = run(["ddev", "exec", "printenv", "EZ37_STRIPE_MODE"], check=False).strip() or "test"
    if mode.lower() != "test":
        die("EZ37_STRIPE_MODE is not 'test'. These checks only run against the sandbox.")

    secret = run(["ddev", "exec", "printenv", "EZ37_STRIPE_TEST_WEBHOOK_SECRET"], check=False).strip()
    if not secret.startswith("whsec_"):
        die("EZ37_STRIPE_TEST_WEBHOOK_SECRET is not visible in the web container (check .ddev/.env, then `ddev restart`).")

    return base, cafile, prefix, secret


def db(prefix: str, sql: str) -> list[list[str]]:
    out = run(["ddev", "mysql", "-N", "-B", "-e", sql.replace("{p}", prefix)])
    return [line.split("\t") for line in out.splitlines() if line.strip()]


def db1(prefix: str, sql: str) -> str | None:
    rows = db(prefix, sql)
    return rows[0][0] if rows and rows[0] else None


def sql_str(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def reset_rate_limits(prefix: str) -> None:
    db(prefix, "DELETE FROM {p}options WHERE option_name LIKE '%ez37_rl_%'")


def run_cron() -> None:
    run(["ddev", "wp", "cron", "event", "run", "--due-now", "--quiet"], check=False)


def mailpit_messages_to(address: str) -> list[dict]:
    out = run(["ddev", "exec", "curl", "-s", "http://127.0.0.1:8025/api/v1/messages?limit=100"], check=False)
    try:
        data = json.loads(out or "{}")
    except json.JSONDecodeError:
        return []
    found = []
    for message in data.get("messages", []):
        if any((to.get("Address") or "").lower() == address.lower() for to in message.get("To", [])):
            found.append(message)
    return found


def mailpit_message(message_id: str) -> dict:
    out = run(["ddev", "exec", "curl", "-s", f"http://127.0.0.1:8025/api/v1/message/{message_id}"], check=False)
    try:
        return json.loads(out or "{}")
    except json.JSONDecodeError:
        return {}


# --------------------------------------------------------------------------
# Site operations
# --------------------------------------------------------------------------

class Site:
    def __init__(self, http: Http, prefix: str, secret: str):
        self.http = http
        self.prefix = prefix
        self._secret = secret  # never printed
        host = urllib.parse.urlparse(http.base).netloc
        self.origin = f"https://{host}"
        self.checkout_url = http.base + CHECKOUT_PATH + "?offering=" + OFFERING

    # --- checkout ---------------------------------------------------------

    def nonce(self) -> str:
        status, _, body, _ = self.http.request("GET", self.checkout_url)
        match = re.search(rb'name="_ez37_nonce" value="([a-f0-9]+)"', body)
        if status != 200 or not match:
            die("the checkout page did not show the form. Is EZ37_PAYMENTS_CHECKOUT_ENABLED=1 and is the page /checkout/ with [ez37_checkout]?")
        return match.group(1).decode()

    def post_checkout(self, fields: dict, origin: str | None = "self", nonce: str | None = None, referer: bool = True):
        reset_rate_limits(self.prefix)
        data = {
            "action": "ez37_checkout",
            "offering": OFFERING,
            "customer_name": "Phase Ten",
            "customer_email": f"phase10+{RUN}@example.com",
            "_ez37_nonce": nonce if nonce is not None else self.nonce(),
            "ez37_website": "",
        }
        data.update(fields)
        headers = {"Content-Type": "application/x-www-form-urlencoded"}
        if origin == "self":
            headers["Origin"] = self.origin
        elif origin:
            headers["Origin"] = origin
        if referer and origin == "self":
            headers["Referer"] = self.checkout_url
        status, hdrs, _, _ = self.http.request("POST", "/wp-admin/admin-post.php", urllib.parse.urlencode(data).encode(), headers)
        return status, hdrs.get("Location", "")

    @staticmethod
    def error_code(location: str) -> str:
        query = urllib.parse.parse_qs(urllib.parse.urlparse(location).query)
        return (query.get("ez37_error") or [""])[0]

    def transaction_count(self) -> int:
        return int(db1(self.prefix, "SELECT COUNT(*) FROM {p}ez37_transactions") or 0)

    def latest_transaction(self, email: str) -> dict | None:
        rows = db(
            self.prefix,
            "SELECT id, public_id, stripe_checkout_session_id, amount, stripe_price_id, customer_name, payment_status "
            f"FROM {{p}}ez37_transactions WHERE customer_email = {sql_str(email)} ORDER BY id DESC LIMIT 1",
        )
        if not rows:
            return None
        keys = ["id", "public_id", "session", "amount", "price", "name", "payment_status"]
        return dict(zip(keys, rows[0]))

    def new_pending(self, label: str, **fields) -> dict | None:
        email = f"phase10+{RUN}-{label}@example.com"
        status, location = self.post_checkout({"customer_email": email, **fields})
        txn = self.latest_transaction(email)
        if status != 303 or "checkout.stripe.com" not in location or txn is None:
            return None
        return {**txn, "email": email}

    # --- webhooks ---------------------------------------------------------

    def sign(self, payload: bytes, timestamp: int | None = None) -> str:
        ts = int(time.time()) if timestamp is None else timestamp
        mac = hmac.new(self._secret.encode(), f"{ts}.".encode() + payload, hashlib.sha256).hexdigest()
        return f"t={ts},v1={mac}"

    def webhook(self, payload: bytes, signature: str | None):
        headers = {"Content-Type": "application/json"}
        if signature is not None:
            headers["Stripe-Signature"] = signature
        status, _, body, elapsed = self.http.request("POST", WEBHOOK_PATH, payload, headers)
        try:
            result = json.loads(body or b"{}").get("result", "")
        except json.JSONDecodeError:
            result = ""
        return status, result, elapsed

    def send_event(self, event: dict, signed: bool = True, timestamp: int | None = None):
        payload = json.dumps(event).encode()
        return self.webhook(payload, self.sign(payload, timestamp) if signed else None)

    @staticmethod
    def event(event_type: str, session: dict, livemode: bool = False, event_id: str | None = None) -> dict:
        return {
            "id": event_id or f"evt_phase10_{RUN}_{secrets.token_hex(6)}",
            "object": "event",
            "type": event_type,
            "livemode": livemode,
            "created": int(time.time()),
            "api_version": "2026-10-28.endive",
            "data": {"object": session},
        }

    @staticmethod
    def session_for(txn: dict, **overrides) -> dict:
        session = {
            "id": txn["session"],
            "object": "checkout.session",
            "mode": "payment",
            "status": "complete",
            "payment_status": "paid",
            "livemode": False,
            "client_reference_id": txn["public_id"],
            "metadata": {"ez37_transaction": txn["public_id"], "ez37_offering": OFFERING},
            "currency": "usd",
            "amount_subtotal": int(txn["amount"]),
            "amount_total": int(txn["amount"]),
            "total_details": {"amount_tax": 0, "amount_discount": 0, "amount_shipping": 0},
            "payment_intent": f"pi_phase10_{RUN}_{secrets.token_hex(4)}",
            "customer": f"cus_phase10_{RUN}",
        }
        session.update(overrides)
        return session

    def event_status(self, event_id: str) -> str | None:
        return db1(self.prefix, f"SELECT processing_status FROM {{p}}ez37_stripe_events WHERE stripe_event_id = {sql_str(event_id)}")

    def payment_status(self, txn_id: str) -> tuple[str | None, str | None]:
        rows = db(self.prefix, f"SELECT payment_status, review_required FROM {{p}}ez37_transactions WHERE id = {int(txn_id)}")
        return (rows[0][0], rows[0][1]) if rows else (None, None)

    def email_counts(self, txn_id: str) -> dict:
        rows = db(self.prefix, f"SELECT email_type, status, attempts, attachment_included FROM {{p}}ez37_email_log WHERE transaction_id = {int(txn_id)}")
        return {r[0]: {"status": r[1], "attempts": r[2], "attachment": r[3]} for r in rows}


# --------------------------------------------------------------------------
# The checks
# --------------------------------------------------------------------------

def webhook_basics(site: Site) -> None:
    print("\nWebhooks — authentication and input")
    session = {"id": f"cs_test_phase10unknown{RUN}", "object": "checkout.session", "payment_status": "paid"}
    event = site.event("checkout.session.completed", session)
    payload = json.dumps(event).encode()

    status, _, _ = site.webhook(payload, "t=%d,v1=%s" % (int(time.time()), "0" * 64))
    record("WH-02", "Invalid signature", status == 400, f"HTTP {status}")

    tampered_sig = site.sign(payload)
    status, _, _ = site.webhook(payload.replace(b"paid", b"PAID"), tampered_sig)
    record("WH-02b", "Tampered body", status == 400, f"HTTP {status}")

    status, _, _ = site.webhook(payload, None)
    record("WH-03", "Missing signature header", status == 400, f"HTTP {status}")

    status, _, _ = site.webhook(payload, site.sign(payload, int(time.time()) - 600))
    record("WH-04", "Stale timestamp (10 min old, correctly signed)", status == 400, f"HTTP {status}")

    unknown = site.event("customer.created", {"id": f"cus_phase10_{RUN}", "object": "customer"})
    status, result, _ = site.send_event(unknown)
    record("WH-05", "Unknown event type", status == 200 and site.event_status(unknown["id"]) == "ignored", f"HTTP {status}, result {result}")

    status, result, _ = site.send_event(event)
    record("WH-11", "Signed event for unknown session", status == 200 and result == "ignored", f"HTTP {status}, result {result}")

    live = site.event("checkout.session.completed", session, livemode=True)
    status, result, _ = site.send_event(live)
    record("WH-13", "Livemode mismatch (live event, test site)", status == 200 and result == "ignored", f"HTTP {status}, result {result}")

    garbage = b"{not json"
    status, _, _ = site.webhook(garbage, site.sign(garbage))
    record("WH-15", "Malformed JSON with valid signature", status == 400, f"HTTP {status}")

    big = b'{"x":"' + b"a" * 600_000 + b'"}'
    status, _, _ = site.webhook(big, site.sign(big))
    record("WH-16", "Oversized body (600 KB)", status == 413, f"HTTP {status}")

    status, _, _, _ = site.http.request("GET", WEBHOOK_PATH)
    record("WH-18", "Webhook route answers directly (no redirect)", status not in (301, 302, 303, 307, 308), f"GET → HTTP {status} (POST-only route)")


def checkout_validation(site: Site) -> None:
    print("\nCheckout — validation and abuse (no Stripe calls expected)")
    before = site.transaction_count()

    status, loc = site.post_checkout({}, origin="https://evil.example")
    record("SEC-03", "Cross-origin POST", site.error_code(loc) == "security", f"HTTP {status}, ez37_error={site.error_code(loc) or '-'}")

    status, loc = site.post_checkout({}, origin=None, referer=False)
    record("SEC-03b", "POST with no Origin/Referer", site.error_code(loc) == "security" or status in (400, 403), f"HTTP {status}, ez37_error={site.error_code(loc) or '-'}")

    status, loc = site.post_checkout({}, nonce="0123456789")
    record("SEC-04", "Invalid nonce", site.error_code(loc) == "security", f"ez37_error={site.error_code(loc) or '-'}")

    status, loc = site.post_checkout({"offering": "does-not-exist"})
    record("CHK-02", "Unknown offering", site.error_code(loc) == "unavailable", f"ez37_error={site.error_code(loc) or '-'}")

    bad_keys = ["../x", "<script>alert(1)</script>", "a" * 200]
    codes = [site.error_code(site.post_checkout({"offering": k})[1]) for k in bad_keys]
    record("CHK-05", "Malformed offering keys", all(c in ("unavailable", "invalid_input") for c in codes), ", ".join(codes))

    _, _, body, _ = site.http.request("GET", CHECKOUT_PATH + "?offering=" + urllib.parse.quote("<script>alert(1)</script>") + "&ez37_error=" + urllib.parse.quote("<b>x</b>"))
    record("CHK-05b", "Hostile query string not reflected", b"<script>alert(1)" not in body and b"<b>x</b>" not in body)

    codes = [site.error_code(site.post_checkout({"customer_email": e})[1]) for e in ["abc", "a@", ""]]
    record("CHK-07", "Invalid emails", all(c == "invalid_input" for c in codes), ", ".join(codes))

    codes = [site.error_code(site.post_checkout({"customer_name": n})[1]) for n in ["", "x" * 500]]
    record("CHK-08", "Missing / oversized name", all(c == "invalid_input" for c in codes), ", ".join(codes))

    _, loc = site.post_checkout({"customer_email": "a@example.com\r\nBcc: victim@example.com"})
    record("CHK-09", "Header injection in email", site.error_code(loc) == "invalid_input", f"ez37_error={site.error_code(loc) or '-'}")

    record("CHK-02b", "No transactions created by refused requests", site.transaction_count() == before, f"{site.transaction_count() - before} new")

    # Rate limit: 10 attempts / 10 min per IP; the 11th is refused.
    reset_rate_limits(site.prefix)
    nonce = site.nonce()
    codes = []
    for _ in range(11):
        data = urllib.parse.urlencode({"action": "ez37_checkout", "offering": OFFERING, "customer_name": "Rate Limit", "customer_email": "not-an-email", "_ez37_nonce": nonce, "ez37_website": ""}).encode()
        _, hdrs, _, _ = site.http.request("POST", "/wp-admin/admin-post.php", data, {"Content-Type": "application/x-www-form-urlencoded", "Origin": site.origin, "Referer": site.checkout_url})
        codes.append(Site.error_code(hdrs.get("Location", "")))
    reset_rate_limits(site.prefix)
    record("SEC-09", "Rate limiting (11 attempts)", codes[-1] == "rate_limited" and codes[0] == "invalid_input", f"first {codes[0]}, last {codes[-1]} (as built: redirect with message, not 429)")


def valid_checkouts(site: Site) -> dict:
    print("\nCheckout — valid requests (creates Stripe sandbox sessions)")
    txns = {}

    email = f"phase10+{RUN}-a@example.com"
    status, loc = site.post_checkout({"customer_email": email})
    txn = site.latest_transaction(email)
    ok = status == 303 and urllib.parse.urlparse(loc).netloc == "checkout.stripe.com" and txn is not None and txn["payment_status"] == "pending" and (txn["session"] or "").startswith("cs_test_")
    record("CHK-01", "Valid offering → pending transaction, 303 to Stripe", ok, f"HTTP {status}, host {urllib.parse.urlparse(loc).netloc or '-'}")
    if txn:
        txns["a"] = {**txn, "email": email}
        record("CHK-06", "Customer values stored", txn["name"] == "Phase Ten", f"name stored as {txn['name']!r}")

    b = site.new_pending("b", amount="1", price="price_fake", currency="eur", unit_amount="1")
    record("SEC-01", "Manipulated price fields ignored", b is not None and b["amount"] == "100" and b["price"].startswith("price_") and b["price"] != "price_fake", f"stored amount {b and b['amount']}, price {b and b['price'][:12]}…")
    if b:
        txns["b"] = b

    c = site.new_pending("c", customer_name="<script>alert(1)</script>Eve")
    record("SEC-07", "Script in name is not stored as markup", c is not None and "<script" not in c["name"], f"stored as {c and c['name']!r}")

    d = site.new_pending("d", customer_name="O'Brien' OR 1=1 --")
    record("SEC-08", "SQL-injection text stored literally", d is not None and d["name"] == "O'Brien' OR 1=1 --", f"stored as {d and d['name']!r}")
    if d:
        txns["d"] = d

    e = site.new_pending("e")
    if e:
        txns["e"] = e
    return txns


def paid_path(site: Site, txns: dict) -> None:
    print("\nWebhooks — payment state (signed test events)")
    a, b, d = txns.get("a"), txns.get("b"), txns.get("d")
    if not a:
        record("WH-08", "Concurrent duplicate delivery", None, "no transaction from CHK-01")
        return

    # WH-08 / WH-10: async_payment_succeeded delivered twice at the same time, before 'completed'.
    succeeded = site.event("checkout.session.async_payment_succeeded", site.session_for(a))
    outcomes = []

    def deliver():
        outcomes.append(site.send_event(succeeded))

    threads = [threading.Thread(target=deliver) for _ in range(2)]
    for t in threads:
        t.start()
    for t in threads:
        t.join()

    status, _ = site.payment_status(a["id"])
    codes = sorted(o[0] for o in outcomes)
    record("WH-08", "Same event delivered twice concurrently", codes == [200, 200] and status == "paid", f"HTTP {codes}, results {[o[1] for o in outcomes]}, txn {status}")
    record("WH-17", "Webhook response time", max(o[2] for o in outcomes) < 2.0, f"{max(o[2] for o in outcomes):.2f}s (target < 2 s; email not sent inline)")
    record("WH-01", "Valid signed event processed", site.event_status(succeeded["id"]) == "processed", f"event row {site.event_status(succeeded['id'])}")

    status_code, result, _ = site.send_event(succeeded)
    record("WH-06", "Duplicate event (resend)", status_code == 200 and result == "duplicate", f"HTTP {status_code}, result {result}")

    completed = site.event("checkout.session.completed", site.session_for(a))
    status_code, result, _ = site.send_event(completed)
    record("WH-10", "Out of order: completed after async_succeeded", status_code == 200 and site.payment_status(a["id"])[0] == "paid", f"result {result}, txn {site.payment_status(a['id'])[0]}")

    expired = site.event("checkout.session.expired", site.session_for(a, status="expired", payment_status="unpaid"))
    site.send_event(expired)
    record("WH-09", "Out of order: expired after paid", site.payment_status(a["id"])[0] == "paid", f"txn {site.payment_status(a['id'])[0]}")

    if b:
        mismatch = site.event("checkout.session.completed", site.session_for(b, amount_subtotal=1, amount_total=1))
        _, result, _ = site.send_event(mismatch)
        status, review = site.payment_status(b["id"])
        record("WH-12", "Amount mismatch → review, not paid", result == "review" and status == "pending" and review == "1", f"result {result}, txn {status}, review {review}")

    if d and b:
        swapped = site.event("checkout.session.completed", site.session_for(d, client_reference_id=b["public_id"]))
        _, result, _ = site.send_event(swapped)
        status, review = site.payment_status(d["id"])
        record("WH-14", "client_reference_id mismatch → review", result == "review" and status == "pending" and review == "1", f"result {result}, txn {status}, review {review}")


def fulfilment(site: Site, txns: dict) -> str | None:
    print("\nFulfilment, emails and downloads")
    a = txns.get("a")
    if not a or site.payment_status(a["id"])[0] != "paid":
        record("FUL-01", "Successful fulfilment", None, "transaction A is not paid")
        return None

    for _ in range(3):
        run_cron()
        state = db1(site.prefix, f"SELECT fulfilment_status FROM {{p}}ez37_transactions WHERE id = {int(a['id'])}")
        if state == "completed":
            break
        time.sleep(2)

    ents = db(site.prefix, f"SELECT status, expires_at FROM {{p}}ez37_entitlements WHERE transaction_id = {int(a['id'])}")
    emails = site.email_counts(a["id"])
    customer = emails.get("customer_fulfilment", {})
    record("FUL-01", "Paid → entitlement + emails → completed", state == "completed" and len(ents) == 1 and customer.get("status") == "sent", f"fulfilment {state}, entitlements {len(ents)}, customer email {customer.get('status')}")
    record("FUL-13", "Attachment within limit", customer.get("attachment") == "1", f"attachment_included={customer.get('attachment')}")
    admin = emails.get("admin_sale_notice")
    record("UX-04b", "Internal sale notice sent", admin is None or admin.get("status") == "sent", "not configured" if admin is None else f"status {admin.get('status')}")

    tax = db(site.prefix, f"SELECT amount_subtotal, amount_tax, amount_paid FROM {{p}}ez37_transactions WHERE id = {int(a['id'])}")
    record("TAX-02", "Subtotal / tax / paid stored", bool(tax) and tax[0] == [a["amount"], "0", a["amount"]], f"{tax and tax[0]}")

    # FUL-04: run the sweeper and cron again — nothing new may happen.
    run(["ddev", "wp", "cron", "event", "run", "ez37_payments_fulfilment_sweep", "--quiet"], check=False)
    run_cron()
    again = site.email_counts(a["id"])
    ents_again = db(site.prefix, f"SELECT COUNT(*) FROM {{p}}ez37_entitlements WHERE transaction_id = {int(a['id'])}")[0][0]
    record("FUL-04", "Duplicate fulfilment run", again.get("customer_fulfilment", {}).get("attempts") == customer.get("attempts") and ents_again == "1", f"customer email attempts {again.get('customer_fulfilment', {}).get('attempts')}, entitlements {ents_again}")

    # FUL-10: refresh the success page 10 times.
    for _ in range(10):
        site.http.request("GET", SUCCESS_PATH + "?session_id=" + a["session"])
    status, _, body, _ = site.http.request("GET", SUCCESS_PATH + "?session_id=" + a["session"])
    record("FUL-10", "Success page refreshed 10× — no new emails", site.email_counts(a["id"]) == again, "")
    record("UX-01", "Success page shows 'Payment confirmed'", status == 200 and b"Payment confirmed" in body, f"HTTP {status}")

    # Token from the email in Mailpit.
    messages = mailpit_messages_to(a["email"])
    token = None
    attachment_names = []
    if messages:
        full = mailpit_message(messages[0]["ID"])
        match = re.search(r"ez37_access=([a-f0-9]{64})", full.get("HTML", "") + full.get("Text", ""))
        token = match.group(1) if match else None
        attachment_names = [att.get("FileName") for att in full.get("Attachments", [])]
    record("UX-04", "Customer email in Mailpit with link and attachment", token is not None and bool(attachment_names), f"{len(messages)} message(s), attachments {attachment_names}")
    if not token:
        return None

    status, hdrs, body, _ = site.http.request("GET", "/?ez37_access=" + token)
    disposition = hdrs.get("Content-Disposition", "")
    record("UX-05", "Download link serves the PDF", status == 200 and body.startswith(b"%PDF") and "brochure-placeholder.pdf" in disposition, f"HTTP {status}, {disposition}")
    record("UX-05b", "Download is not cacheable / sniffable", "no-store" in (hdrs.get("Cache-Control") or "") and hdrs.get("X-Content-Type-Options") == "nosniff", f"Cache-Control {hdrs.get('Cache-Control')}")

    token_hash = hashlib.sha256(token.encode()).hexdigest()
    original = db1(site.prefix, f"SELECT expires_at FROM {{p}}ez37_entitlements WHERE access_token_hash = {sql_str(token_hash)}")
    db(site.prefix, f"UPDATE {{p}}ez37_entitlements SET expires_at = UTC_TIMESTAMP() + INTERVAL 1 DAY WHERE access_token_hash = {sql_str(token_hash)}")
    status, _, _, _ = site.http.request("GET", "/?ez37_access=" + token)
    record("FUL-15", "Link valid on day 6", status == 200, f"HTTP {status}")

    db(site.prefix, f"UPDATE {{p}}ez37_entitlements SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 DAY WHERE access_token_hash = {sql_str(token_hash)}")
    status, _, body, _ = site.http.request("GET", "/?ez37_access=" + token)
    record("FUL-16", "Link expired after 7 days", status == 410 and b"%PDF" not in body, f"HTTP {status}")
    db(site.prefix, f"UPDATE {{p}}ez37_entitlements SET expires_at = {sql_str(original or '2099-01-01 00:00:00')} WHERE access_token_hash = {sql_str(token_hash)}")
    return token


def access_control(site: Site, txns: dict, token: str | None) -> None:
    print("\nAccess control")
    for label, value in (("random", secrets.token_hex(32)), ("malformed", "../../wp-config.php")):
        status, _, body, _ = site.http.request("GET", "/?ez37_access=" + urllib.parse.quote(value))
        record("SEC-10" if label == "random" else "SEC-10b", f"Download with {label} token", status == 404 and b"%PDF" not in body, f"HTTP {status}")

    a = txns.get("a")
    if a:
        revoked_token = secrets.token_hex(32)
        db(
            site.prefix,
            "INSERT INTO {p}ez37_entitlements (transaction_id, resource_key, customer_email, access_token_hash, status, expires_at, access_count, created_at) "
            f"VALUES ({int(a['id'])}, 'phase10-revoked-{RUN}', 'phase10@example.com', {sql_str(hashlib.sha256(revoked_token.encode()).hexdigest())}, 'revoked', UTC_TIMESTAMP() + INTERVAL 7 DAY, 0, UTC_TIMESTAMP())",
        )
        status, _, _, _ = site.http.request("GET", "/?ez37_access=" + revoked_token)
        record("SEC-12", "Revoked entitlement", status == 404, f"HTTP {status}")
        db(site.prefix, f"DELETE FROM {{p}}ez37_entitlements WHERE resource_key = 'phase10-revoked-{RUN}'")

    for path in ("/sample-resources/leadership-coaching/brochure-placeholder.pdf", "/../sample-resources/leadership-coaching/brochure-placeholder.pdf"):
        status, _, body, _ = site.http.request("GET", path)
        record("SEC-11", f"Direct file URL {path[:24]}…", not body.startswith(b"%PDF"), f"HTTP {status}")

    status, hdrs, _, _ = site.http.request("GET", "/wp-admin/admin.php?page=ez37-payments")
    record("SEC-05", "Logged-out visitor opens Payments screen", status in (301, 302) and "wp-login" in hdrs.get("Location", ""), f"HTTP {status} → {hdrs.get('Location', '')[:40]}")

    if a and token:
        data = urllib.parse.urlencode({"action": "ez37_payments_admin", "ez37_do": "revoke", "transaction": a["id"], "_wpnonce": "forged"}).encode()
        site.http.request("POST", "/wp-admin/admin-post.php", data, {"Content-Type": "application/x-www-form-urlencoded"})
        still = db1(site.prefix, f"SELECT status FROM {{p}}ez37_entitlements WHERE access_token_hash = {sql_str(hashlib.sha256(token.encode()).hexdigest())}")
        record("SEC-06", "Forged admin 'revoke' (logged out, bad nonce)", still == "active", f"entitlement {still}")


def status_endpoint(site: Site, txns: dict) -> None:
    print("\nStatus endpoint")
    reset_rate_limits(site.prefix)
    probes = ["' OR 1=1 --", "cs_test_" + "A" * 30, "x" * 300]
    states = []
    for probe in probes:
        status, hdrs, body, _ = site.http.request("GET", STATUS_PATH + "?session_id=" + urllib.parse.quote(probe))
        data = json.loads(body or b"{}")
        states.append((status, sorted(data.keys()), data.get("state"), hdrs.get("Cache-Control", "")))
    ok = all(s == 200 and k == ["state"] and st == "not_found" and "no-store" in cc for s, k, st, cc in states)
    record("SEC-13", "Unknown / malicious session IDs", ok, f"{[(s[0], s[2]) for s in states]}")

    a = txns.get("a")
    if a:
        _, _, body, _ = site.http.request("GET", STATUS_PATH + "?session_id=" + a["session"])
        data = json.loads(body or b"{}")
        record("SEC-13b", "Known session reveals only its state", list(data.keys()) == ["state"] and data.get("state") == "paid", f"{data}")
    reset_rate_limits(site.prefix)


def secrets_and_tax(site: Site) -> None:
    print("\nSecrets and tax")
    tracked = run(["git", "grep", "-I", "-l", "-E", SECRET_PATTERN.pattern], check=False).strip()
    record("SEC-14", "No secrets in tracked files", tracked == "", tracked.replace("\n", ", ") or "none found")

    _, _, body, _ = site.http.request("GET", site.checkout_url)
    record("SEC-14b", "No secrets in checkout page HTML", not SECRET_PATTERN.search(body.decode("utf-8", "replace")))

    logs = run(["ddev", "logs", "-s", "web", "--tail", "5000"], check=False)
    debug_log = os.path.join("wordpress", "wp-content", "debug.log")
    if os.path.isfile(debug_log):
        with open(debug_log, encoding="utf-8", errors="replace") as fh:
            logs += fh.read()[-2_000_000:]
    hits = len(SECRET_PATTERN.findall(logs))
    record("SEC-14c", "No secrets or download tokens in logs", hits == 0, f"{hits} match(es) (values not shown)")

    real = db(
        site.prefix,
        "SELECT amount, amount_subtotal, amount_tax, amount_paid FROM {p}ez37_transactions WHERE payment_status = 'paid' "
        "AND stripe_payment_intent_id NOT LIKE 'pi_phase10_%' ORDER BY id DESC LIMIT 1",
    )
    if real:
        amount, subtotal, tax, paid = real[0]
        record("TAX-01", "Real sandbox payment: no tax, total = price", tax == "0" and subtotal == amount and paid == amount, f"amount {amount}, subtotal {subtotal}, tax {tax}, paid {paid}")
    else:
        record("TAX-01", "Real sandbox payment: no tax, total = price", None, "no real paid transaction yet — make one card payment and re-run")

    wording = []
    tax_words = re.compile(r"tax included|incl\.? tax|plus tax|excl\.? tax|\bVAT\b", re.IGNORECASE)
    for root, _, files in os.walk(os.path.join("ez37-payments", "templates")):
        for name in files:
            with open(os.path.join(root, name), encoding="utf-8") as fh:
                if tax_words.search(fh.read()):
                    wording.append(name)
    record("TAX-05", "No tax wording in customer templates", not wording, ", ".join(wording) or "none")

    price_id = None
    with open(os.path.join("ez37-payments", "config", "offerings.php"), encoding="utf-8") as fh:
        match = re.search(r"'test'\s*=>\s*'(price_[A-Za-z0-9]+)'", fh.read())
        price_id = match.group(1) if match else None
    try:
        price = json.loads(run(["stripe", "prices", "retrieve", price_id]) if price_id else "{}")
        record("TAX-03", "Sandbox Price tax_behavior", price.get("tax_behavior") == "unspecified", f"tax_behavior={price.get('tax_behavior')}")
    except Exception:  # noqa: BLE001
        record("TAX-03", "Sandbox Price tax_behavior", None, "Stripe CLI not available/logged in — check in the Dashboard")


def write_report() -> None:
    os.makedirs("build", exist_ok=True)
    path = os.path.join("build", "phase10-results.md")
    passed = sum(1 for r in results if r[2] == "PASS")
    failed = sum(1 for r in results if r[2] == "FAIL")
    skipped = sum(1 for r in results if r[2] == "SKIP")
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(f"# Phase 10 automated checks — run {RUN}, {time.strftime('%Y-%m-%d %H:%M %Z')}\n\n")
        fh.write(f"{passed} passed, {failed} failed, {skipped} skipped.\n\n| ID | Scenario | Result | Detail |\n|---|---|---|---|\n")
        for test_id, scenario, status, detail in results:
            fh.write(f"| {test_id} | {scenario} | {status} | {detail.replace('|', '/')} |\n")
    print(f"\n{passed} passed, {failed} failed, {skipped} skipped. Report: {path}")


def main() -> None:
    print(f"EZ37 Payments — Phase 10 local checks (run {RUN})")
    base, cafile, prefix, secret = discover()
    site = Site(Http(base, cafile), prefix, secret)
    del secret
    print(f"Site {base} · table prefix {prefix} · test mode · webhook secret loaded (not shown)")

    site.nonce()  # fails early with a clear message if the form is not there

    try:
        webhook_basics(site)
        checkout_validation(site)
        txns = valid_checkouts(site)
        paid_path(site, txns)
        token = fulfilment(site, txns)
        access_control(site, txns, token)
        status_endpoint(site, txns)
        secrets_and_tax(site)
    except (http.client.HTTPException, OSError, RuntimeError) as e:
        print(f"\n\033[31mStopped early:\033[0m {e}")
    finally:
        reset_rate_limits(prefix)
        write_report()
    sys.exit(1 if any(r[2] == "FAIL" for r in results) else 0)


if __name__ == "__main__":
    main()
