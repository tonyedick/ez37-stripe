# Fulfilment and email

> **Status: Phases 6–7 built (v0.5.0); administrator actions added in Phase 8 (v0.6.0).** Built together because decision D10 makes one customer
> email both the confirmation and the delivery: fulfilment is complete only when that email is sent.

## Model

```
PAYMENT (paid, verified by webhook or reconciliation)
   │  action ez37_payments_transaction_paid
   ▼
FULFILMENT JOB  (WP-Cron single event ez37_payments_fulfil, plus hourly sweeper)
   │  claim → offering → handler "resource_access" → resources
   ▼
ENTITLEMENT  (ez37_entitlements: one per transaction + resource, hashed token, expiry)
   ▼
RESOURCE  (file in EZ37_PAYMENTS_PRIVATE_DIR, served only by the access controller)
   ▼
EMAIL  (one customer email: confirmation + link + attachment; one admin sale notice)
```

Changing what customers receive is configuration only:

| Change | Where |
|---|---|
| Replace the placeholder brochure | Put the new file in the private folder and update `path` (and `label`) in `config/resources.php` |
| Give an offering different or more resources | `config/offerings.php` → `fulfilment.resources` |
| New kind of fulfilment (e.g. private page, LMS enrolment) | New `handler` value + code in `FulfilmentService::deliver()`; payment code unchanged |

## Configuration

### `config/resources.php`

| Field | Meaning |
|---|---|
| key | `a-z0-9-`, referenced from offerings |
| `type` | `private_file` |
| `label` | Shown in the email |
| `path` | Relative to the private folder; simple segments only, no `..` |
| `attach` | Also attach to the customer email (D6) |
| `max_attach` | Bytes; larger files are sent as link only (and logged) |
| `access_days` | Link lifetime; **7** (D6) |

Invalid entries are disabled and listed in the wp-admin notice.

### `EZ37_PAYMENTS_PRIVATE_DIR`

Absolute path to a folder **outside the web root**. Nothing in it is reachable by URL; files are
streamed only by `AccessController`. If it is missing, fulfilment fails (and retries) and wp-admin
shows a warning. There is deliberately no fallback inside `wp-content/uploads`, because `.htaccess`
rules do not protect files on Nginx hosts.

Local DDEV: `EZ37_PAYMENTS_PRIVATE_DIR=/var/www/html/sample-resources` (the repository's
`sample-resources/` folder, which holds the placeholder brochure).

## Fulfilment job (`FulfilmentService`)

| Step | Detail |
|---|---|
| Trigger | `ez37_payments_transaction_paid` → `wp_schedule_single_event(now, ez37_payments_fulfil, [id])`. The webhook only schedules; it never waits for email. |
| Claim | Atomic `UPDATE`: paid, not flagged for review, fulfilment `pending`/`failed`, or `in_progress` with an expired 5-minute lock. Increments `fulfilment_attempts`. Concurrent jobs cannot both win. |
| Already emailed? | If the customer email is logged as `sent`, complete without issuing new links (a crash after sending never invalidates the emailed link). |
| Grant | For each resource: check the file exists inside the private folder, create a 256-bit token, store only its SHA-256 with `expires_at = now + access_days`. Existing active entitlement → new token (the old one was never emailed). Revoked → error. |
| Email | Customer email (must succeed), then admin notice (failure only logged). |
| Complete | `fulfilment_status = completed`, `fulfilled_at` set. |
| Failure | `failed` (retried by the sweeper) or, at attempt 5, `failed_permanent`. **Payment status never changes.** |
| Sweeper | Hourly `ez37_payments_fulfilment_sweep`: up to 20 due transactions. |

## Emails (`Mailer`, `EmailRepository`)

Sent with `wp_mail()`, so the site's SMTP plugin/provider applies. Locally DDEV delivers to Mailpit
(`ddev launch -m`).

| Email | To | Content | Once? |
|---|---|---|---|
| `customer_fulfilment` (HTML) | customer | "Your payment is confirmed", programme, amount, short reference, a **Download** button per resource with the expiry date/time, note that the file is attached (when it is), note that Stripe sends the receipt | `UNIQUE (transaction_id, email_type)`; a `sent` row is never re-sent |
| `admin_sale_notice` (plain text) | `EZ37_PAYMENTS_ADMIN_EMAIL` | programme, amount, customer name and email, paid time, reference, Stripe PaymentIntent, mode (subject tagged `[TEST]`/`[LIVE]`) | same |

All customer-provided values are escaped in the HTML email; the plain-text email strips line breaks.

## Download endpoint (`AccessController`)

`https://<site>/?ez37_access=<64 hex chars>`

| Check | Result if it fails |
|---|---|
| Rate limit (30 / 10 min per IP) | 429 |
| Token format | 404 "not valid" (no database query) |
| Entitlement exists and is `active` | 404 |
| Transaction is `paid` and not flagged for review | 404 |
| `expires_at` in the future | **410 "This download link has expired. Please contact us…"** |
| Resource configured and file inside the private folder | 404 (error logged) |
| `max_access` (if set) not reached | 404 |
| Otherwise | File streamed with `Content-Disposition: attachment`, `no-store`, `noindex`, `nosniff`, `no-referrer`; `access_count` incremented atomically |

## Administrator actions (Payments screen, Phase 8)

**wp-admin → Payments** lists every transaction (search by email, name or reference; filter by
status). Opening one shows the summary, access (entitlements, without tokens), the email log, the
Stripe events and a link to the payment in the Stripe Dashboard.

| Action | Offered when | What happens |
|---|---|---|
| **Retry delivery now** | paid, not under review, delivery `failed` or `failed_permanent`, access not revoked | `reset_fulfilment()` (back to `pending`, attempts 0) then the normal fulfilment job runs immediately |
| **Resend email with a new link** | paid, not under review, delivery `completed`, access not revoked | reset to `pending`, the customer email row is reopened, fulfilment runs: **new token and new 7-day expiry**, previous link stops working, file attached again. The internal sale notice is *not* re-sent. |
| **Revoke access** | at least one active entitlement | every active entitlement → `revoked`; the download link answers 404 at once. Used after refunding in the Stripe Dashboard (D12). Cannot be undone from the screen. |

Rules that hold for all three:

- Only users with `ez37_view_payments` (administrators) can see the screen or act.
- Each action is a POST to `admin-post.php` with a nonce bound to **that action and that transaction**.
- The server re-checks that the action is valid for the transaction's *current* state before doing
  anything (a stale page cannot, for example, resend after access was revoked).
- Payment status is never changed by these actions; refunds happen only in Stripe.
- Every action is logged (`Administrator action on payment.` with action, reference, user ID, result).
- If a resend's email fails, the transaction becomes `failed` and the hourly sweeper retries it as
  usual (with another new link, because the failed email never reached the customer).
