# Test matrix — Phase 10

The formal matrix from the approved plan (`phase-1-analysis.md` §9.2), with how each test is run
against **v0.7.0** in Stripe **test mode** on the local DDEV site, and its result.

**How each test is run**

| Method | Meaning |
|---|---|
| **Script** | `python3 bin/phase10-local.py` (repository root, DDEV running). Talks to the local site and the Stripe sandbox only; refuses to run outside test mode. Report: `build/phase10-results.md`. |
| **Unit** | PHPUnit (`composer test`); the test name is given. |
| **Manual** | A person in the browser, following the steps in [Manual test session](#manual-test-session) below. |
| **Evidence** | Already verified in an earlier phase and recorded in `testing.md`; re-checked where later changes touched it. |
| **N/A** | Not applicable to the confirmed decisions; reason given. |

**Expected (as built)** notes where the implementation's agreed behaviour differs from the wording of
the original plan (for example, refused checkout requests redirect back to the form with a friendly
message instead of returning a bare 400/403/429 page).

Result legend: ✅ pass · ❌ fail · ⏳ not run yet · — not applicable.

## Checkout

| ID | Scenario | Method | Expected (as built) | Actual | P/F |
|---|---|---|---|---|---|
| CHK-01 | Valid offering | Script | Transaction `pending` with a `cs_test_` session; 303 to `checkout.stripe.com` | | ⏳ |
| CHK-02 | Invalid offering | Script | Redirect back with `ez37_error=unavailable` ("not available" message); no transaction; no Stripe call | | ⏳ |
| CHK-03 | Inactive offering | Unit | `CheckoutServiceTest::test_inactive_offering`, `OfferingCatalogueTest::test_inactive_offering_is_not_purchasable` | Unit suite passing | ✅ |
| CHK-04 | No price for current mode | Unit | `CheckoutServiceTest::test_offering_without_price_for_mode`; wp-admin notice names the offering | Unit suite passing | ✅ |
| CHK-05 | Malformed offering key | Script | Refused (`unavailable`/`invalid_input`); nothing stored; hostile query string not reflected | | ⏳ |
| CHK-06 | Valid customer info | Script | Name stored as typed (sanitised); email sent to Stripe as `customer_email` | | ⏳ |
| CHK-07 | Invalid email | Script + Unit | `invalid_input`; no transaction (`CheckoutInputTest::test_invalid_emails`) | | ⏳ |
| CHK-08 | Missing / oversized name | Script + Unit | `invalid_input` (`CheckoutInputTest::test_invalid_names`) | | ⏳ |
| CHK-09 | Header injection | Script | Email containing `\r\nBcc:` refused | | ⏳ |
| CHK-10 | Stripe API failure | Unit + Evidence | `test_session_creation_failure_marks_transaction_failed`; Phase 3: wrong-sandbox Price blocked with friendly message, no secret in log | Unit suite passing; Phase 3 log | ✅ |
| CHK-11 | Network timeout / retry | Unit + design | Every session request carries `ez37-checkout-<public_id>` as idempotency key; SDK retries twice (SA-07) reuse it | Unit suite passing | ✅ |
| CHK-12 | Price mismatch with Stripe | Unit | `test_price_mismatch_blocks_checkout` (amount, currency, archived, recurring, wrong mode) | Unit suite passing | ✅ |

## Payment

| ID | Scenario | Method | Expected (as built) | Actual | P/F |
|---|---|---|---|---|---|
| PAY-01 | Successful payment | Manual (M1) | Webhook → `paid`, IDs stored, fulfilment runs | | ⏳ |
| PAY-02 | Declined card | Manual (M2) | Stripe shows the decline; customer can retry on the same page; transaction stays `pending` | | ⏳ |
| PAY-03 | 3-D Secure completed | Manual (M3) | As PAY-01 | | ⏳ |
| PAY-04 | 3-D Secure failed | Manual (M3) | No payment; stays `pending` | | ⏳ |
| PAY-05 | Cancelled checkout | Manual (M4) + Evidence | Cancel page "Nothing was charged"; transaction becomes `expired` when the session expires (PAY-11) | Phase 5: cancel page verified | ⏳ |
| PAY-06 | Interruption after paying | Manual (M5) | Close the tab before the redirect: still `paid`, email still sent | | ⏳ |
| PAY-07 | Duplicate checkout attempt | Manual (M6) + Evidence | Two tabs → two `pending` transactions; only the paid one is fulfilled; double-click gives one (Phase 8 step 4) | Phase 8: double-click → one transaction | ⏳ |
| PAY-08 | Same request retried | Unit + design | Retries of the same Stripe request reuse the idempotency key. As built, a *new* form submission is a new transaction by design (button disabled after one click). | Unit suite passing | ✅ |
| PAY-09 | Delayed payment method | N/A + Unit | Cards and wallets only (D3); no delayed methods enabled. Event handling still covered: `test_async_payment_succeeded_marks_paid` | — | — |
| PAY-10 | Delayed payment fails | N/A + Unit | As PAY-09: `test_async_payment_failed_marks_failed` | — | — |
| PAY-11 | Session expires | Manual (M7) | Expire a pending session via Stripe CLI → `checkout.session.expired` → `expired` | | ⏳ |

## Webhooks

| ID | Scenario | Method | Expected (as built) | Actual | P/F |
|---|---|---|---|---|---|
| WH-01 | Valid webhook | Script + Evidence | 200; event row `processed` | Phase 4: real event 200, pending → paid | ⏳ |
| WH-02 | Invalid signature / tampered body | Script | 400; no state change | | ⏳ |
| WH-03 | Missing signature header | Script | 400 | | ⏳ |
| WH-04 | Stale timestamp (replay) | Script | Correctly signed but 10 min old → 400 | | ⏳ |
| WH-05 | Unknown event type | Script | 200; event row `ignored` | | ⏳ |
| WH-06 | Duplicate webhook | Script | 200 `duplicate`; no second change or email | | ⏳ |
| WH-07 | Retry after our 500 | Unit | `test_internal_error_marks_event_failed_and_rethrows`, `test_redelivery_after_failed_attempt_is_processed_again` | Unit suite passing | ✅ |
| WH-08 | Concurrent duplicate delivery | Script | Same event twice at once: both 200, one transition, one fulfilment | | ⏳ |
| WH-09 | `expired` after `paid` | Script | Stays `paid` | | ⏳ |
| WH-10 | `async_payment_succeeded` before `completed` | Script | Ends `paid`; fulfilled once | | ⏳ |
| WH-11 | Unknown session | Script | 200 `ignored`; nothing fulfilled | | ⏳ |
| WH-12 | Amount mismatch | Script | `review`: not paid, flagged, nothing delivered | | ⏳ |
| WH-13 | Livemode mismatch | Script | 200 `ignored` | | ⏳ |
| WH-14 | `client_reference_id` mismatch | Script | `review`: not paid, flagged | | ⏳ |
| WH-15 | Malformed JSON | Script | 400 | | ⏳ |
| WH-16 | Oversized body | Script | 413 before processing | | ⏳ |
| WH-17 | Response time | Script | < 2 s; email sent in the background job, not inline | | ⏳ |
| WH-18 | Not redirected / cached | Script | Route answers directly, never 3xx | | ⏳ |

## Security

| ID | Scenario | Method | Expected (as built) | Actual | P/F |
|---|---|---|---|---|---|
| SEC-01 | Manipulated price | Script | Extra `amount`/`price`/`currency` fields ignored; stored amount and Price ID from config | | ⏳ |
| SEC-02 | Manipulated offering ID | Script + Unit | Unknown → `unavailable` (CHK-02); inactive refused (CHK-03) | | ⏳ |
| SEC-03 | CSRF | Script | Cross-origin, or no Origin/Referer → `ez37_error=security` (redirect, not 403) | | ⏳ |
| SEC-04 | Invalid / expired nonce | Script | `security` → "Your session expired. Please try again." | | ⏳ |
| SEC-05 | Unauthorised admin access | Script + Evidence | Logged out → login redirect; Editor refused (Phase 8 step 9) | Phase 8: Editor refused | ⏳ |
| SEC-06 | Admin action without nonce | Script + Unit | Forged revoke has no effect; `PaymentsPageTest` capability/nonce/state tests | | ⏳ |
| SEC-07 | Stored XSS | Script + Unit + Manual (M8) | Tags stripped on input; escaped in emails (`MailerTest`) and admin (look at the transaction in M8) | | ⏳ |
| SEC-08 | SQL injection | Script | Name `O'Brien' OR 1=1 --` stored literally; status lookup returns `not_found` | | ⏳ |
| SEC-09 | Rate limiting | Script | 11th attempt in 10 min → `rate_limited` message (redirect, not 429) | | ⏳ |
| SEC-10 | Resource without entitlement | Script | Random / malformed token → 404, no file | | ⏳ |
| SEC-11 | Direct file URL | Script | Private file not reachable by URL | | ⏳ |
| SEC-12 | Revoked / expired entitlement | Script | Revoked → 404; expired → 410 friendly page (FUL-16) | | ⏳ |
| SEC-13 | Status endpoint enumeration | Script | Only `{"state": …}`; unknown/malicious → `not_found`; `no-store`; rate-limited | | ⏳ |
| SEC-14 | Secrets not exposed | Script + Evidence | None in tracked files, page HTML or logs (values never printed); Git history clean (Phase 9) | Phase 9: history scan clean | ⏳ |
| SEC-15 | Live/test key mismatch | Unit | `ConfigTest::test_live_mode_rejects_test_key`; live also requires https (SA-04) | Unit suite passing | ✅ |
| SEC-16 | Debug output | Manual (M9) + Unit | Visitors see generic messages only (`test_error_messages_are_generic…`); details only in the server log | | ⏳ |

## Fulfilment

| ID | Scenario | Method | Expected (as built) | Actual | P/F |
|---|---|---|---|---|---|
| FUL-01 | Successful fulfilment | Script + Manual (M1) | Entitlement, customer + admin email, `completed` | | ⏳ |
| FUL-02 | Email failure | Unit | `test_email_failure_fails_retryably`: payment stays `paid`, fulfilment `failed`, retried | Unit suite passing | ✅ |
| FUL-03 | Resource unavailable | Manual (M10) + Unit + Evidence | `failed`, payment untouched (`test_missing_file_fails_retryably…`); Phase 8 step 8 | Phase 8: retry after failure | ⏳ |
| FUL-04 | Duplicate fulfilment | Script | Sweep + cron again: no new email or entitlement | | ⏳ |
| FUL-05 | Concurrent fulfilment | Script (WH-08) + Unit + review | Atomic claim; SA-01 reservation (`test_no_links_are_issued_while_another_job_is_sending_the_email`) | | ⏳ |
| FUL-06 | Automatic retry | Manual (M10) + Unit | Attempts increase; ends `completed` (`test_sweep_retries_due_transactions`) | | ⏳ |
| FUL-07 | Admin "Retry" | Manual (M10) | Completes; action logged | | ⏳ |
| FUL-08 | Max retries | Unit | `test_last_attempt_fails_permanently` → `failed_permanent` | Unit suite passing | ✅ |
| FUL-09 | Crashed job | Unit + review | Expired lock (15 min) is reclaimable; email reservation stale after 15 min (SA-01). Killing PHP mid-send is not reproducible safely on DDEV. | Code review (Phase 9) | ✅ |
| FUL-10 | Success page refreshed | Script | 10 reloads: no new emails or entitlements | | ⏳ |
| FUL-11 | Resource replaced | Manual (M11) | New purchases get the new file; no code change | | ⏳ |
| FUL-12 | WP-Cron not running | N/A locally | Server-cron setup is a deployment step (Phase 11 checklist); queue is processed by `wp cron event run --due-now` | — | — |
| FUL-13 | Attachment within limit | Script | `attachment_included = 1`; Mailpit shows the PDF attached | | ⏳ |
| FUL-14 | Attachment over limit | Unit | `test_large_file_is_sent_as_link_only` | Unit suite passing | ✅ |
| FUL-15 | Link valid on day 6 | Script | Expiry moved to +1 day → download works | | ⏳ |
| FUL-16 | Link expired | Script | Expiry in the past → 410 "link has expired" page | | ⏳ |
| FUL-17 | Admin revokes access | Manual (M12) + Evidence | Link refused at once; logged (Phase 8 step 7) | Phase 8: passed | ⏳ |
| FUL-18 | Admin resends access | Manual (M12) | New email, new 7-day link; old link refused. **Re-run required after SA-01.** | | ⏳ |

## Tax readiness (D7 — tax NOT confirmed)

| ID | Scenario | Method | Expected (as built) | Actual | P/F |
|---|---|---|---|---|---|
| TAX-01 | No tax applied | Script | Latest real sandbox payment: `amount_tax = 0`, subtotal = total = price | | ⏳ |
| TAX-02 | Tax breakdown recorded | Script | `amount_subtotal`, `amount_tax`, `amount_paid` stored | | ⏳ |
| TAX-03 | Price tax behaviour | Script (Stripe CLI) | `tax_behavior = unspecified` | | ⏳ |
| TAX-04 | Future tax tolerated | Unit | `EventProcessorTest::test_consistent_tax_is_accepted` | Unit suite passing | ✅ |
| TAX-05 | No tax wording | Script | No "tax included / plus tax / VAT" text in templates | | ⏳ |

## UX

| ID | Scenario | Method | Expected (as built) | Actual | P/F |
|---|---|---|---|---|---|
| UX-01 | Completes payment | Manual (M1) + Script | "Payment confirmed" page | | ⏳ |
| UX-02 | Cancels | Manual (M4) + Evidence | Cancel page with "Try again" | Phase 5: passed | ⏳ |
| UX-03 | Returns before webhook | Manual (M5) | "Confirming your payment…", then updates or advises email | | ⏳ |
| UX-04 | Confirmation email | Manual (M1) + Script | Name, programme, amount, reference, Download button, attachment | | ⏳ |
| UX-05 | Download link | Script | PDF with correct filename | | ⏳ |
| UX-06 | Stripe receipt | Manual (M13) | Receipt from the Dashboard (test mode does not email automatically) shows correct business details; no invoice (D5) | | ⏳ |
| UX-07 | Mobile | Manual (M14) | Form, Stripe page and return pages usable at phone width | | ⏳ |
| UX-08 | Accessibility basics | Manual (M14) | Keyboard only works; fields labelled; errors announced | | ⏳ |
| UX-09 | Price consistency | Manual (M14) | Same amount on the programme page (`[ez37_offering_price]`), checkout, Stripe and email | | ⏳ |

---

## Manual test session

About 45 minutes. Before starting: `ddev start`, `stripe listen --events checkout.session.completed,checkout.session.async_payment_succeeded,checkout.session.async_payment_failed,checkout.session.expired --forward-to https://ez37.ddev.site:8443/wp-json/ez37/v1/stripe/webhook`
running in a second terminal, and Mailpit open (`ddev launch -m`).

Test cards (from [Stripe's testing documentation](https://docs.stripe.com/testing), checked 13 Nov 2026).
Use any future expiry (e.g. 12/34), any 3-digit CVC, any postcode.

| Card | Behaviour |
|---|---|
| `4242 4242 4242 4242` | Succeeds |
| `4000 0000 0000 0002` | Generic decline |
| `4000 0000 0000 9995` | Declined: insufficient funds |
| `4000 0027 6000 3184` | Always asks for 3-D Secure |

| Step | Do | Check (tests) |
|---|---|---|
| **M1** | Pay with `4242…` using an email you will recognise | "Payment confirmed"; Mailpit: customer email with Download button + PDF attached, admin "New sale … [TEST]"; Download works; wp-admin → Payments shows Paid / Delivered (PAY-01, FUL-01, UX-01, UX-04) |
| **M2** | New checkout; pay with `…0002`, then `…9995`, then `4242…` on the same Stripe page | Stripe shows each decline; the last succeeds; one transaction, paid (PAY-02) |
| **M3** | New checkout with `4000 0027 6000 3184`; **complete** the authentication. Then another one and **fail** it | First → paid. Second → no payment, stays pending (PAY-03, PAY-04) |
| **M4** | New checkout; click the back arrow on the Stripe page | Cancel page "Nothing was charged" + "Try again" (PAY-05, UX-02) |
| **M5** | Stop `stripe listen`. Pay with `4242…`. Note the success page, close the tab. Start `stripe listen` again, then `stripe events resend <event id>` for the `checkout.session.completed` event shown in the Stripe Dashboard → Developers → Events (or wait for reconciliation: `ddev wp cron event run ez37_payments_reconcile` after 15 minutes) | Success page showed "Confirming your payment…"; afterwards the transaction is paid and the email arrives (UX-03, PAY-06) |
| **M6** | Open the checkout in two tabs, submit both, pay only one | Two pending transactions; one paid and delivered; the other stays pending (PAY-07) |
| **M7** | Take the session ID of the unpaid transaction from M6 (Payments → transaction, or the script output) and run `stripe post /v1/checkout/sessions/<cs_test_…>/expire` | Transaction becomes Expired (PAY-11, PAY-05) |
| **M8** | wp-admin → Payments → open the transaction whose name was `<script>alert(1)</script>Eve` (made by the script) | Shown as plain text, no alert (SEC-07) |
| **M9** | Confirm `WP_DEBUG_DISPLAY` is false (`ddev wp config get WP_DEBUG_DISPLAY`). The friendly messages seen in CHK tests are the only output visitors get | No PHP errors or stack traces on any page you saw today (SEC-16) |
| **M10** | Rename `sample-resources/leadership-coaching/brochure-placeholder.pdf` to `…pdf.off`. Pay with `4242…`. Run `ddev wp cron event run --due-now`: Payments shows **Delivery failed**. Rename the file back. Either run `ddev wp cron event run ez37_payments_fulfilment_sweep` (automatic retry) or click **Retry delivery now** | Payment stays Paid throughout; after the retry the email arrives and the status is Delivered; the action appears in `ddev logs -s web` (FUL-03, FUL-06, FUL-07) |
| **M11** | Copy the PDF to `brochure-v2.pdf`, change `path` in `config/resources.php`, pay again; then change it back | The new purchase's email attaches `brochure-v2.pdf` (FUL-11) |
| **M12** | On the M1 payment: **Resend email with a new link** → check Mailpit → try the *old* link and the *new* link. Then **Revoke access** → try the new link | Resend: new email arrives, old link "not valid", new link downloads. Revoke: link "not valid", Resend/Retry no longer offered (FUL-18, FUL-17) — this is also the post-SA-01 regression check |
| **M13** | Stripe Dashboard → Payments → the M1 payment → send receipt to yourself | Receipt shows the business name/details expected for the client; no invoice was created (UX-06) |
| **M14** | Phone-width browser (or the browser's device toolbar): programme page → checkout → Stripe → success. Then the checkout form with the keyboard only (Tab, Enter) and an invalid email | Usable at phone width; every field reachable and labelled; the error is shown above the form; same price everywhere (UX-07, UX-08, UX-09) |

Report results by test ID (or "all passed") and I'll fill in the Actual and P/F columns.
