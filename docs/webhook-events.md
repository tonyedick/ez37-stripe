# Webhook events

> **Status: Phase 4 built: verified webhooks mark transactions paid, processing, failed or expired.**
> Fulfilment (Phase 6) and emails (Phase 7) are not built yet. When a transaction becomes paid the
> plugin fires the WordPress action `ez37_payments_transaction_paid` and sets
> `fulfilment_status = pending`; nothing listens to it yet.

## Endpoint

```
POST https://<site>/wp-json/ez37/v1/stripe/webhook
```

Registered in `WebhookController` as a public REST route (`permission_callback: __return_true`):
authenticity comes from the Stripe signature, not from a WordPress login.

## Verification (every request)

Implemented in `WebhookController::receive()` with the official SDK (`Stripe\Webhook::constructEvent`).

| Step | Failure → response |
|---|---|
| Body ≤ 512 KB | 413 |
| Signing secret configured for the active mode (`EZ37_STRIPE_<MODE>_WEBHOOK_SECRET`) | 500 (Stripe retries once fixed) |
| `Stripe-Signature` header present | 400 |
| HMAC-SHA256 signature over the **raw** body matches (`v1` scheme only, constant-time compare, done by the SDK) | 400 |
| Signature timestamp within **300 s** (replay protection; never 0) | 400 |
| Body is a valid event | 400 |
| Processing succeeds | 500 on an internal error (Stripe retries) |
| Otherwise | **200** with `{"result": "processed" \| "ignored" \| "duplicate" \| "review"}` |

Rejected payloads are never logged (they are unauthenticated input).

## Events handled

Only these four are processed (`EventProcessor::HANDLED_TYPES`). Any other type is answered 200 and
recorded as `ignored`. Subscribe the Stripe endpoint to these four only.

| Event | Condition | Effect (conditional, forward-only) |
|---|---|---|
| `checkout.session.completed` | `payment_status = paid` | `pending → paid`; store PaymentIntent ID, Customer ID, subtotal, tax, total, `paid_at`; `fulfilment_status → pending`; fire `ez37_payments_transaction_paid` once |
| `checkout.session.completed` | `payment_status = unpaid` (delayed method) | `pending → processing` |
| `checkout.session.async_payment_succeeded` | — | `pending/processing → paid` (as above) |
| `checkout.session.async_payment_failed` | — | `processing → failed` (`failure_code = async_payment_failed`) |
| `checkout.session.expired` | — | `pending → expired` |

Cards and wallets only (D3) means `processing` and the async events should not occur. They are
handled defensively in case payment methods change in the Dashboard.

## Validation before any state change

`EventProcessor::check_session()` compares the session with the stored transaction:

| Check | Applies to |
|---|---|
| `client_reference_id` = transaction `public_id` | all |
| `metadata.ez37_transaction` = transaction `public_id` | all |
| `mode = payment` | all |
| session `livemode` = transaction `stripe_mode` | all |
| `currency` = transaction currency | paying events |
| `amount_subtotal` = configured price (`amount`) | paying events |
| no discount, no shipping | paying events |
| `amount_total = amount_subtotal + total_details.amount_tax` | paying events (tax-ready, D7) |

Any failure: `review_required = 1`, `failure_code = webhook_mismatch`, an error is logged, and the
transaction is **not** marked paid or fulfilled. The event is answered 200 (`review`) because
retrying would not change the outcome.

Before that, the whole event is ignored if its `livemode` does not match the configured mode, or
if no transaction has that Checkout Session ID.

## Idempotency and ordering

| Situation | Handling |
|---|---|
| Same event delivered twice (retry, resend) | `ez37_stripe_events.stripe_event_id` is UNIQUE (`INSERT IGNORE`). If the earlier attempt finished (`processed`/`ignored`) → `duplicate`, nothing happens. |
| Earlier attempt crashed (`received`/`failed`) | Processed again; safe because transitions are conditional. |
| Two deliveries at the same moment | Only one conditional `UPDATE` can match; the other changes nothing. |
| Out of order (e.g. `expired` after `paid`) | `expired` is only allowed from `pending`, so a paid transaction is untouched. |
| Different events for the same change (e.g. `completed` + `async_payment_succeeded`) | The second finds the row already `paid`; nothing changes and the paid action is not fired again. |

`ez37_stripe_events` stores ID, type, livemode, object ID, transaction ID, outcome and a short note.
It never stores payloads.

## Local testing with the Stripe CLI

```bash
stripe listen \
  --events checkout.session.completed,checkout.session.async_payment_succeeded,checkout.session.async_payment_failed,checkout.session.expired \
  --forward-to https://ez37.ddev.site:8443/wp-json/ez37/v1/stripe/webhook
```

The command prints a signing secret (`whsec_…`). Put it in `.ddev/.env` as
`EZ37_STRIPE_TEST_WEBHOOK_SECRET`, run `ddev restart`, and keep `stripe listen` running while testing.
If the CLI refuses the local certificate, add `--skip-verify` (local only).

Useful commands:

```bash
stripe events resend evt_…        # duplicate delivery (WH-06)
stripe trigger checkout.session.completed   # creates an unrelated session → "ignored" (WH-11)
```

## Staging / production

Register an endpoint under **Workbench → Webhooks → Create an event destination** with the exact
canonical URL (no redirects: Stripe treats 3xx as failure), only the four events above, and put its
signing secret in `wp-config.php`. Exclude the route from page caching, security-plugin firewalls and
staging basic auth (see deployment.md).
