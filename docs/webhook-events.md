# Webhook events

> **Status: not implemented yet (Phase 4).**
> The approved design is in [phase-1-analysis.md](phase-1-analysis.md#54-webhook-events-we-subscribe-to-only-these).
> This document will describe the actual implementation once it is built, as required by the project rules.

What exists today that this area will use:

- Payment and fulfilment state machines: `src/Transactions/PaymentStatus.php`, `src/Transactions/FulfilmentStatus.php` — see [database.md](database.md#state-machines).
- Tables: see [database.md](database.md).
