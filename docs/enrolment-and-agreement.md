# Enrolment fields and agreement acceptance

Added after Phase 10 for the Professional Coach Certification Programme (PCCP). It extends the existing
checkout flow; there is no second payment path.

## What an offering can define

In `config/offerings.php`:

- `enrolment`: a list of fields (`key`, `label`, `type` text/tel/select/textarea, `required`, `options`
  for selects, `max`, `autocomplete`, `half`). The checkout form renders them, the server validates them
  (`Checkout\EnrolmentForm`): unknown keys are dropped, a select value must be one of the options, phone
  numbers must look like phone numbers, line breaks are refused in single-line fields.
- `agreement`: the key of an agreement in `config/agreements.php`.

If both `first_name` and `last_name` are configured, the form does not ask for a separate full name; the
customer name is composed from them.

## Agreement

`config/agreements.php` holds versioned text. The customer ticks a box on the checkout form. With
JavaScript, ticking the box (or following "read the agreement") opens a drawer fixed to the right of the
screen with the full text and the button "I have read and agree", which ticks the box. Without JavaScript
the link jumps to the same panel (CSS `:target`) and the box can be ticked normally.

The browser is never trusted for this. On submit the server requires the box AND the agreement version the
form showed to equal the current version (so nobody accepts wording that changed while the page was open).
Failure returns `ez37_error=agreement_required` and creates no order and no Stripe call.

## What is stored with the order

`ez37_transactions`: `enrolment_json`, `agreement_key`, `agreement_version`, `agreement_hash` (SHA-256 of
the exact text), `agreement_accepted_at` (UTC, server time). The admin transaction screen shows them.
Changing the wording means changing `version`; old orders keep the version and hash they accepted.
Not stored: IP address or browser details (add only if the client's counsel asks for it, and update the privacy notice).

## Order of checks in `CheckoutService::start`

checkout enabled, https (live), offering purchasable, **enrolment fields valid, agreement accepted and current**,
Stripe Price verified, order created, Checkout Session created.

## Before go-live

- The agreement text in `config/agreements.php` is the client's Abridged Participant Enrolment Agreement (24 Nov 2026),
  plus the client's Refund Policy and Transfer of Credit Policy as sections. Change `version` whenever wording changes.
- Enrolment fields follow the client's Google form. Not collected here because the Stripe flow does not support them:
  preferred payment currency (checkout is USD only, D2), instalment options (one-time payment only), first-payment
  date and invoice details (Stripe receipts only, D5). Customers wanting plans are sent to a discovery call.
- **Price conflict to resolve with the client:** product page and Google form say $2,699; the Fees tab says payment in full $2,599
  (4 x $649.75). `config/offerings.php` currently uses $2,699.00.
- Create the PCCP Price (one-time, USD) in the Stripe sandbox, then in the live account; add the IDs to `config/offerings.php`.
- Put the PCCP resource file at `EZ37_PAYMENTS_PRIVATE_DIR/pccp/brochure-placeholder.pdf`.
- Confirm lawful basis and privacy notice cover the enrolment data (gender, phone, country, objective).
