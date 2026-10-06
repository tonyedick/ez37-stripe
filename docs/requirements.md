# Requirements

Source: project brief and the approved Phase 1 analysis ([phase-1-analysis.md](phase-1-analysis.md)).
This file is the short, current statement of what the system must do.

## Business requirements

- One-time payments for professional services/programmes (coaching, training, L&D).
- Customers do not need WordPress accounts.
- Customer name and email collected on the EZ37 form **before** Stripe (D4).
- Payment through **Stripe-hosted Checkout**, cards and wallets only (D3).
- Currency: **USD** (D2). One currency per offering.
- After a confirmed payment, the customer receives **one combined email** with payment
  confirmation, a secure download link and the resource as an attachment (D6, D10).
- The download link works for **7 days** (D6). Attachments over a size limit are sent link-only.
- Receipts: **Stripe automatic receipts only**, no Stripe invoices (D5).
- One **internal sale notification** email per paid transaction (D10).
- The first resource is a placeholder brochure. It must be replaceable by real programme material
  without changing the payment engine.
- New offerings are added by a **developer** in a version-controlled config file (D8).
- Refunds are processed **manually** in the Stripe Dashboard at launch (D12). An admin can revoke a
  customer's access link.

## Tax (D7)

**Not confirmed.** No VAT/sales tax is assumed, no rate is configured, and Stripe Tax is not
enabled. The design must allow tax to be introduced later without redesign — see
[phase-1-analysis.md §0.1](phase-1-analysis.md#01-tax-ready-design-without-assuming-tax).

## Non-negotiable technical rules

1. A browser redirect is never proof of payment. Only a verified Stripe webhook marks a
   transaction paid. The success page is read-only (D9).
2. The browser never supplies a price. The server determines price and currency.
3. Stripe secrets live only in server configuration — never in code, Git or the database.
4. Webhook processing and fulfilment are idempotent: no double payment state changes,
   no duplicate emails, no duplicate entitlements.
5. A fulfilment/email failure never changes a paid transaction's payment status.
6. Test and live credentials are strictly separated.
7. No WooCommerce. Payment logic never lives in Elementor content.

## Open items

| # | Item | Needed by |
|---|---|---|
| O2 | Record retention period / privacy policy | Phase 11 |
| O3 | Whether Adaptive Pricing is enabled in the client's Stripe account | Phase 3 |
| O4 | Production environment details (WP/PHP versions, server, caching, security plugins, email) | Before staging |
| — | Stripe account country (Canada or US) | Before go-live |
| — | Offering list and prices | Phase 3 (test prices can be placeholders) |
