# Security audit — Phase 9

| | |
|---|---|
| Scope | EZ37 Payments plugin v0.6.0 (commit `1e96521`): all of `src/`, `templates/`, `assets/`, `config/`, `uninstall.php`, release script, `.gitignore`, Git history |
| Date | 13 November 2026 |
| Result | 7 findings fixed in v0.7.0 (3 medium, 4 low); 6 informational items accepted or deferred with reasons |
| Reviewer | Claude (the same assistant that wrote the code), working only in the development copy. No production system or data was accessed. |

> **Limitation (please read).** This was a structured line-by-line review by the code's author, backed
> by automated checks. It is not a penetration test and not a review by an independent third party.
> Before going live, a second experienced WordPress/PHP developer should read this report and spot-check
> the code paths in the "Verified controls" table, and the full test matrix (Phase 10) must pass in
> Stripe test mode.

## Method

1. Manual review of every source file against the project's security requirements (instructions §5)
   and the Phase 1 attack-surface list, grouped by area: secrets/config/logging; checkout entry point;
   webhooks, reconciliation and payment state; fulfilment, downloads and email; front-end pages and
   the admin screen.
2. Automated: PHPCS with WordPress security sniffs (escaping, nonces, sanitisation, prepared SQL),
   PHPStan level 6, the full PHPUnit suite.
3. Repository hygiene: tracked-file list, `.gitignore`, and a scan of the **entire Git history** for
   anything shaped like a Stripe secret key or webhook secret (none found).
4. Each fix got a regression test (`tests/Unit/HardeningTest.php` and additions to existing tests).

## Findings fixed in v0.7.0

| ID | Severity | Area | Finding | Fix |
|---|---|---|---|---|
| SA-01 | Medium | Fulfilment / email | A fulfilment job whose 5-minute lock expired could be taken over while the first job was still sending (PHPMailer's default SMTP timeout is also 300 s). The second job created new download tokens — silently invalidating the link in the email the first job was sending — and could send a duplicate email. | The customer email is now **reserved before any link is created**; a reservation in progress is only taken over after 15 minutes. If granting fails, the reservation is released. Fulfilment lock raised to 15 minutes. |
| SA-02 | Medium | Resource delivery | `EZ37_PAYMENTS_PRIVATE_DIR` was accepted anywhere. Pointing it inside the WordPress folder (e.g. `wp-content/uploads/…`) would make paid files downloadable by URL without paying. | Folders inside `ABSPATH` are refused: delivery stops (and retries) and wp-admin shows a warning naming the setting. |
| SA-03 | Medium | Availability | All rate limits (checkout, status polling, downloads) used `REMOTE_ADDR`. Behind a CDN or reverse proxy that is the proxy's address, so all visitors would share one limit — e.g. only 10 checkouts per 10 minutes for the whole site. | New `Support\ClientIp` used everywhere, with filter `ez37_payments_client_ip` so the deployer can supply the real visitor IP from **their** proxy's header. Default stays `REMOTE_ADDR` (forwarded headers are forgeable). **Deployment action required** once the production stack is known (open item O4). |
| SA-04 | Low | Transport | Nothing stopped live mode on a site whose address is `http://`. | Live checkout is refused unless the site URL is https; wp-admin shows an error. |
| SA-05 | Low | Logging | Download-link tokens (`?ez37_access=…`) were not on the logger's redaction list. No current code path logs them; this is defence in depth. | Logger redacts `ez37_access=` values. |
| SA-06 | Low | Downloads | Error responses (404/410/429) from the download endpoint did not send no-cache headers, so a page cache could store them; buffered output from other plugins could corrupt a streamed file. | `nocache_headers()` on every denial; output buffers are discarded before streaming. Deployment checklist: exclude URLs containing `ez37_access` from page caching. |
| SA-07 | Low | Resilience | The Stripe client made no network retries, so a single transient error failed a checkout. | `max_network_retries = 2` (stripe-php option). Safe because Checkout Session creation always carries our idempotency key. |

## Informational — accepted or deferred

| ID | Item | Decision |
|---|---|---|
| SA-08 | Refunds and disputes are not reflected automatically: after a refund in Stripe the transaction stays `paid` and access stays active until an administrator clicks **Revoke access**. | Accepted for launch (decision D12, manual refunds). Recommended later: handle `charge.refunded` and `charge.dispute.created` to notify the admin and revoke access automatically. |
| SA-09 | A transaction flagged for review (Stripe data did not match) has no "resolve" action in wp-admin. | Accepted: such cases should be rare and need human judgement in the Stripe Dashboard. Resolution procedure to be added to `troubleshooting.md` in Phase 11. |
| SA-10 | Personal data: no integration with WordPress's privacy export/erase tools; event and email logs grow without limit. | Deferred to the retention decision (open item O2). Tables are kept on uninstall by design (financial records). |
| SA-11 | WordPress nonces for logged-out visitors are the same for all visitors in a 12–24 h window, so they add little CSRF protection on the public checkout form. | Accepted: the handler also requires a same-site `Origin`/`Referer`, and the worst a forged request can do is start an unpaid checkout. |
| SA-12 | The checkout form and admin buttons use small inline scripts (`onsubmit`, `pageshow`). A strict Content-Security-Policy without `'unsafe-inline'` would block them (the form still works; only double-click protection and confirm dialogs are lost). | Accepted; note for the production CSP if one is introduced. |
| SA-13 | `config/offerings.php` (with the sandbox Price ID) is part of the release build. | Accepted: Price IDs are not secrets. The live Price IDs are added there before launch (Phase 11). The placeholder brochure lives in the repository's `sample-resources/`, outside the plugin, and is not in the build. |

## Verified controls (no issue found)

| Requirement (§5) | Where | What was checked |
|---|---|---|
| Secret key protection, env-based config | `Config` | Read only from constants/env, never the database; only active-mode credentials exposed; key prefixes enforced per mode; redacted from `var_dump`; errors name settings, never values |
| Test/live separation | `Config`, `EventProcessor`, `CheckoutService` | Mode typo falls back to test; live key in test mode warns; events with wrong `livemode` ignored; Price `livemode` checked |
| No secrets in Git | repo | `.env*`, keys, `wp-config` ignored; whole history scanned clean; release script aborts on secret-shaped strings |
| Webhook signature, replay | `WebhookController` | SDK `constructEvent` on the raw body, 300 s tolerance (never 0); 413 above 512 KB; payload never logged |
| Idempotent processing, duplicates, retries | `EventRepository`, `EventProcessor` | `INSERT IGNORE` on event ID; only `processed`/`ignored` short-circuit; failures answer 500 so Stripe retries |
| Out-of-order events | `PaymentStatus`, `TransactionRepository::transition` | Conditional `UPDATE … WHERE payment_status IN (allowed)`; `paid` and `expired` final; `ez37_payments_transaction_paid` fires once |
| Server-side price, never trusting the browser | `CheckoutInput`, `CheckoutService` | Browser sends only offering key, name, email; Price fetched from Stripe and compared (amount, currency, type, active, mode); session totals re-checked on payment (mismatch → review, no fulfilment) |
| CSRF, input validation | `CheckoutRequestHandler`, `CheckoutInput`, `PaymentsPage` | POST only; Origin/Referer; nonce; honeypot; line breaks rejected (header injection); length and email checks; admin: capability + per-action, per-transaction nonce + state re-check |
| Output escaping | all templates, emails | PHPCS escaping sniffs clean; customer values escaped in HTML email; plain-text email strips line breaks |
| Prepared queries | all repositories | Every value through `$wpdb->prepare()`; dynamic SQL built only from fixed fragments; `LIKE` escaped |
| Open redirects | `CheckoutRequestHandler` | Only `https://checkout.stripe.com` or a `wp_validate_redirect()`-checked local URL |
| Success page is not proof | `ReturnPageShortcodes`, `StatusService` | Read-only; minimal data (masked email); no-cache, no-referrer, noindex |
| Secure resource delivery | `AccessController`, `PaidResource` | 256-bit tokens, only SHA-256 stored; entitlement + `paid` + not under review + not expired; `realpath()` containment; rate-limited; `nosniff`, attachment, no-store |
| Duplicate fulfilment | `TransactionRepository::claim_fulfilment`, `EmailRepository` | Atomic claim; one email per type (unique key); email failure never changes payment status |
| Admin data access | `PaymentsPage`, `Activator` | `ez37_view_payments` (administrators only); token hashes never selected for display |
| Logging without secrets | `Logger` | Keys, webhook secrets, client secrets, signatures, download tokens redacted; emails masked; newline injection stripped |

## Residual risks to manage at deployment (Phase 11)

- Configure `ez37_payments_client_ip` for the production proxy/CDN (SA-03).
- Private folder outside the web server's document root, not just outside WordPress (SA-02 cannot
  detect a document root above `ABSPATH`).
- Exclude `/checkout/`, `/payment-complete/`, `/payment-cancelled/` and any URL containing
  `ez37_access` from page/CDN caching.
- Reliable SMTP (SPF/DKIM/DMARC) and server cron for WP-Cron.
- Live keys added by the developer only, after the full test suite and manual end-to-end tests pass.
