# Payment flow

> **Status: Phases 3–4 built: checkout start, and payment confirmation by verified webhook**
> (see [webhook-events.md](webhook-events.md)). Success-page status (Phase 5), fulfilment (Phase 6)
> and email (Phase 7) are not built yet.

## 1. Customer journey (implemented part)

```
Elementor page ──link──▶ /checkout/?offering=leadership-coaching
                            │  [ez37_checkout] renders: offering name + price (from config),
                            │  name, email, nonce, honeypot. No price field is ever submitted.
                            ▼
              POST /wp-admin/admin-post.php  (action=ez37_checkout)
                            │  CheckoutRequestHandler::process()
                            │   1. POST only
                            │   2. Origin (or Referer) host == this site
                            │   3. nonce "ez37_checkout" valid
                            │   4. honeypot empty
                            │   5. IP rate limit (10 / 10 min)
                            │   6. CheckoutInput: name 2–100 chars, valid email, no CR/LF
                            │   7. email rate limit (10 / 10 min)
                            ▼
              CheckoutService::start()
                            │   8. checkout enabled AND configuration valid
                            │   9. offering exists, active, has a Price ID for the current mode
                            │  10. Stripe Price matches config (active, one_time, currency,
                            │      amount, mode). Cached 10 min after a successful check.
                            │  11. INSERT ez37_transactions (payment_status = pending)
                            │  12. Create Checkout Session (idempotency key ez37-checkout-<public_id>)
                            │  13. store session ID on the transaction
                            ▼
              303 redirect to https://checkout.stripe.com/… (host verified)
                            ▼
              Customer pays on Stripe (test card 4242 4242 4242 4242 in test mode)
                            ▼
              Stripe redirects to /payment-complete/?session_id=cs_…   (UX only)
              or, if they go back, /payment-cancelled/?offering=…
```

Any failure in steps 1–13 redirects back to the checkout page with `?ez37_error=<reason>` and a
generic message. The detailed reason goes to the log only.

| Reason code | Customer sees |
|---|---|
| `invalid_input` | Please enter your full name and a valid email address. |
| `security` | Your session expired. Please try again. |
| `rate_limited` | Too many attempts. Please wait a few minutes and try again. |
| `unavailable`, `disabled` | This programme is not available for online purchase at the moment. |
| `stripe_error` (and anything else) | We could not start the payment. No money has been taken. Please try again shortly. |

## 2. Checkout Session parameters

Built in `CheckoutService::session_params()`:

| Parameter | Value | Why |
|---|---|---|
| `mode` | `payment` | One-time payments |
| `line_items` | `[{price: <configured Price ID>, quantity: 1}]` | Server-side price only |
| `customer_email` | From our form | D4; prefilled in Checkout |
| `customer_creation` | `always` | Stripe only creates a Customer in payment mode "if required" (i.e. with invoices, which we don't use, D5). `always` gives every payment a Customer ID. |
| `client_reference_id` | Transaction `public_id` | Reconciliation (checked by the webhook in Phase 4) |
| `metadata`, `payment_intent_data.metadata` | `ez37_transaction`, `ez37_offering` | Visible in the Stripe Dashboard on the session and the payment |
| `allowed_payment_method_types` | `['card']` | D3: cards; Apple Pay / Google Pay run on the card method. Filters the payment methods enabled in **Dashboard → Settings → Payment methods**. (`payment_method_types` is rejected by Stripe API `2026-10-28.endive`, which `stripe-php` 23 uses: "no longer supported when creating a Checkout Session".) |
| `adaptive_pricing.enabled` | `false` | Always charge USD (D2), whatever the account's Adaptive Pricing setting is. Stripe: the parameter "defaults to your dashboard setting". |
| `expires_at` | now + 1 hour | Stripe allows 30 min – 24 h; abandoned sessions expire sooner |
| `success_url` | `<site>/payment-complete/?session_id={CHECKOUT_SESSION_ID}` | UX only (D9) |
| `cancel_url` | `<site>/payment-cancelled/?offering=<key>` | Lets the page link back to the offering |
| *(not sent)* `automatic_tax`, `invoice_creation` | — | Tax not confirmed (D7); receipts only (D5) |

Return paths can be changed with `EZ37_PAYMENTS_SUCCESS_PATH` / `EZ37_PAYMENTS_CANCEL_PATH`
(site-relative paths only; anything else falls back to the default with a warning).

## 3. Offerings

Defined in `ez37-payments/config/offerings.php` (developer-edited, D8) and validated by
`OfferingCatalogue`. An invalid definition is skipped and reported in the wp-admin notice.

| Field | Rule |
|---|---|
| key | `a-z`, `0-9`, `-`; 1–64 chars |
| `name` | non-empty, ≤ 191 chars |
| `active` | boolean |
| `currency` | `usd` only for now |
| `amount` | positive integer, cents |
| `price_ids.test` / `.live` | empty or `price_…`; empty = not purchasable in that mode |
| `tax` | `none` only (D7) |
| `fulfilment` | array (used from Phase 6) |

## 4. Pages the site needs

| Page | Content | Notes |
|---|---|---|
| Checkout, e.g. `/checkout/` | `[ez37_checkout]` | Must be **excluded from page caching** (nonce). Elementor buttons link to `/checkout/?offering=<key>`. Alternatively `[ez37_checkout offering="<key>"]` on any page. |
| `/payment-complete/` | Thank-you text (status shortcode added in Phase 5) | Never treated as proof of payment |
| `/payment-cancelled/` | "Payment cancelled, nothing was charged" + link back | |

## 5. Not yet implemented

- Success-page status display (Phase 5).
- Fulfilment, entitlements, emails (Phases 6–7).
