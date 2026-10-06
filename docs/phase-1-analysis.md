# EZ37 Payments — Phase 1: Project Analysis

| | |
|---|---|
| **Project** | EZ37Solutions — custom Stripe payment module (WordPress, no WooCommerce) |
| **Phase** | 1 — Analysis & design (no implementation) |
| **Status** | Revision 3 — approved. Phase 2 (Foundation) started 6 October 2026. Open items O2–O4 remain. |
| **Prepared** | 4 October 2026 · revised 5 and 6 October 2026 |
| **Confidentiality** | Client-confidential (NDA). Contains no production data, credentials or customer information. |

Stripe-specific statements in this document were checked against Stripe's current official documentation (October 2026): Checkout fulfilment, webhooks, receipts and the `stripe-php` SDK. Sources are listed in Appendix A. Anything not yet verified is marked **[verify in Phase 3]**.

---

## 0. Confirmed decisions (revision 2, 5 October 2026)

Your answers to Section 12 are recorded here. **Where this section conflicts with an earlier section, this section wins.** The affected sections have also been updated.

| # | Decision | Effect on the design |
|---|---|---|
| D1 | The client has a Stripe account (Canada or US — to be confirmed). | The developer works in a sandbox of the client's account. Account country affects supported currencies and any future tax setup, not the architecture. |
| D2 | Currency: **USD** (confirmed 6 October 2026). | Each offering stores one currency (`usd`) and one Stripe Price per mode. Other currencies remain possible per offering later. |
| D3 | Cards and wallets only. | No delayed payment methods. The `processing` state and `async_payment_*` events are still handled defensively (cheap, and protects against a Dashboard setting being changed later), but they are not on the expected path. Checkout will restrict payment methods to card (wallets run on the card method) **[verify exact parameter in Phase 3]**. |
| D4 | Name and email collected on our form before Stripe. | Email passed to Checkout as `customer_email`. Name stored on our transaction and sent as session metadata. |
| D5 | Stripe receipts only; no paid invoices. | `invoice_creation` is **not** used (no extra Stripe fee). `stripe_invoice_id` column removed. Automatic receipts must be enabled in the Stripe Dashboard before go-live. |
| D6 | Resource delivered as **both** a download link and an email attachment. Download available for **7 days**. | Entitlement `expires_at` = issue time + 7 days. Each resource has an attachment size limit; if a file is too large it is sent as link-only and this is logged. **Note:** an attachment cannot be revoked or expired — the 7-day limit applies only to the link. |
| D7 | Tax: **NOT CONFIRMED.** No VAT/sales tax assumed, no tax rate, Stripe Tax not enabled. | See 0.1. The design records Stripe's tax breakdown and keeps tax switchable later through configuration. |
| D8 | A developer adds/edits offerings. | Offerings live in a version-controlled config file. No admin UI for offerings. |
| D9 | Success page: Option A at launch. | Read-only success page. Only verified webhooks change payment state. |
| D10 | One combined customer email + one internal sale notification. | Email types: `customer_fulfilment` (payment confirmation + access link + attachment) and `admin_sale_notice`. |
| D11 | Retention policy: to be confirmed with the client. | **Open item O2.** Tables are never dropped automatically; pruning jobs ship disabled until the policy is confirmed. |
| D12 | Refunds handled manually in the Stripe Dashboard at launch. | No refund events in v1. An admin "revoke access" action is added so a refunded customer's link can be disabled before the 7 days run out. |

### 0.1 Tax-ready design (without assuming tax)

The aim is that tax can be switched on later by configuration and Stripe settings, with no change to the payment engine or database.

1. **No tax at launch.** Checkout Sessions are created without `automatic_tax` and without tax rates. No tax figure is shown or calculated by our code.
2. **Leave each Stripe Price's tax behaviour as `unspecified`.** Stripe's API reference says that once a Price's `tax_behavior` is set to `inclusive` or `exclusive`, *"it cannot be changed"*. Leaving it unspecified keeps both options open for the accountant's decision.
3. **Record Stripe's breakdown on every transaction:** `amount_subtotal`, `amount_tax` (from `total_details.amount_tax`) and `amount_total`. Today tax will be 0. If tax is enabled later, reporting already works.
4. **Validate against Stripe's subtotal, not a hard-coded total.** The webhook checks that the line-item subtotal matches the offering's configured price and that `amount_total = amount_subtotal + amount_tax` (with discounts at 0). This stays correct whether or not tax is added later.
5. **Per-offering tax setting in config** — `'tax' => ['mode' => 'none']` now. A later value such as `'stripe_tax'` would add `automatic_tax[enabled]=true` to the session. This is a small, isolated change in `CheckoutService` only.
6. **Price display** on the site comes from the offering config with a neutral label (e.g. "USD 000"), never "tax included" or "plus tax" until confirmed.

### 0.2 Other changes from verification

- **Stripe Customer ID.** Stripe's API reference says Checkout in `payment` mode only creates a Customer object "if required", which for one-time payments means only when invoices are enabled. Since invoices are off (D5), we will set `customer_creation=always` so every paid transaction has a Stripe Customer ID, as your data model requires. This has no extra Stripe fee **[confirm in Phase 3]**.
- **Adaptive Pricing.** Stripe can show prices in the customer's local currency if Adaptive Pricing is enabled in the account. This would complicate amount checks. **Open item O3:** we need to know whether it is on. The plan is to keep it off at launch and validate against the integration currency **[verify behaviour in Phase 3]**.

### 0.3 Open items

| # | Item | Needed by |
|---|---|---|
| O1 | ~~USD or CAD~~ — **resolved: USD.** Stripe account country (Canada or US) still to be confirmed. | Before go-live |
| O2 | Record retention period / privacy policy | Phase 11 (deployment) |
| O3 | Is Adaptive Pricing enabled in the client's Stripe account? | Phase 3 |
| O4 | Section 2 environment details (at least WordPress and PHP versions, web server, caching/security plugins) | Phase 2 uses safe defaults (WordPress 6.4+, PHP 8.1+); the rest is needed before staging |

---

## Contents

0. Confirmed decisions (revision 2)
1. Project assumptions — what is known and unknown
2. Information needed from the production environment
3. Recommended development environment
4. Proposed architecture
5. Proposed payment lifecycle
6. Proposed database model
7. Proposed plugin/module structure
8. Security model
9. Testing strategy and test matrix
10. Implementation phases
11. Deployment strategy
12. Questions for you
- Appendix A — Stripe documentation sources
- Appendix B — Key design decisions (summary)

---

## 1. Project assumptions

### 1.1 What we know (from your brief)

| # | Known fact |
|---|---|
| K1 | Primary site is https://www.ez37solutions.org/, built with WordPress and Elementor. |
| K2 | Stripe is not configured on the site yet. WooCommerce must not be used. |
| K3 | Payments are one-time only for now. Customers do not need WordPress accounts. |
| K4 | Stripe Checkout (Stripe-hosted page) is the default payment experience. |
| K5 | Payment is confirmed by verified Stripe webhooks only. The success page is UX, not proof. |
| K6 | Prices will be supplied later. Nothing in this design invents prices. |
| K7 | The first fulfilment resource is a placeholder brochure. It must be replaceable without touching the payment engine. |
| K8 | The developer (you) has no production access for this work; you control deployment. I have no access to production at all. |
| K9 | ezcoaching.org runs separately today and will be merged into EZ37Solutions later. It is out of scope for the payment module except that the offering catalogue must handle future EZCoaching programmes. |

### 1.2 Working assumptions (please confirm or correct)

| # | Assumption | Why it matters |
|---|---|---|
| A1 | The client has, or will open, a Stripe account in the client's own name. The developer works only in a Stripe **sandbox**/test mode. | Ownership of funds, payouts and compliance sits with the client. |
| A2 | One currency per offering. Multi-currency pricing is not needed at launch. | Affects the offering catalogue and Stripe Price setup. |
| A3 | Card payments plus wallets (Apple Pay / Google Pay) via Checkout are enough at launch. | Delayed methods (bank debits) add an extra "processing" state. The design handles it either way. |
| A4 | One offering per checkout, quantity 1. No basket. | Keeps the transaction model to one line item. |
| A5 | The site runs over HTTPS with a single canonical host (`www.`). | Stripe live webhooks require HTTPS and treat redirects (3xx) as delivery failures. |
| A6 | The host lets us install a normal plugin that bundles Composer dependencies (the Stripe PHP SDK). | Some managed hosts restrict this. |
| A7 | A working transactional email route (SMTP or API provider) exists or will be set up. | Plain PHP `mail()` from shared hosting often lands in spam. |
| A8 | Refunds and disputes are handled manually in the Stripe Dashboard at launch. The module records them later (future phase). | Keeps the first release small. |

### 1.3 Unknowns (resolved via Sections 2 and 12)

- Hosting stack: provider, web server (Apache / Nginx / LiteSpeed), PHP and database versions.
- Caching and CDN layers (these often break webhooks and nonces).
- Security plugins/WAF that may block `POST` requests to the webhook route.
- Other plugins that bundle their own copy of the Stripe SDK (class collision risk).
- Email delivery setup.
- Currency, tax/VAT treatment, invoice requirements.
- How the client wants to manage offerings (code/config vs. admin screen).
- How long post-payment access should last.

---

## 2. Information I need from the production environment

**Never send:** passwords, API keys, `wp-config.php`, database dumps, customer data, SMTP credentials or any other secret. Everything below is non-secret configuration. Redact anything you are unsure about.

The easiest single source for most of this is **WordPress Admin → Tools → Site Health → Info**. Review it and redact paths, emails and keys before sharing extracts.

### 2.1 Platform

| # | Item | Example of an acceptable answer |
|---|---|---|
| P1 | WordPress version | 6.x.x |
| P2 | PHP version | 8.2 |
| P3 | Database engine and version | MariaDB 10.6 |
| P4 | Web server | Apache / Nginx / LiteSpeed |
| P5 | Hosting provider and plan type | Shared / VPS / managed WordPress (name the provider) |
| P6 | Multisite? | No |
| P7 | Database charset/collation | utf8mb4 / utf8mb4_unicode_ci |
| P8 | PHP `memory_limit` and `max_execution_time` | 256M / 60 |
| P9 | Can the server make outbound HTTPS calls (to `api.stripe.com`)? | Yes (most hosts allow this) |
| P10 | WP-CLI and SSH available to you? Real server cron available? | Yes / No |
| P11 | Is `DISABLE_WP_CRON` set? (Yes/No only, not the file) | No |

### 2.2 WordPress / Elementor

| # | Item |
|---|---|
| W1 | Active theme and whether a child theme is used |
| W2 | Elementor version, and whether **Elementor Pro** is installed (and version) |
| W3 | Full list of active plugins with versions (names only) |
| W4 | Any plugins that already integrate Stripe or other payments (forms plugins, donation plugins, etc.) |
| W5 | Forms plugin in use, if any (Elementor Forms, WPForms, Gravity Forms, etc.) |
| W6 | Cookie/consent plugin, if any |
| W7 | Permalink structure (e.g. `/%postname%/`) — needed for REST routes |
| W8 | Is the REST API (`/wp-json/`) blocked or restricted by any plugin? |

### 2.3 Caching, CDN and security

| # | Item |
|---|---|
| C1 | Page-cache plugin (WP Rocket, LiteSpeed Cache, W3TC, etc.) |
| C2 | Server-level cache (host Varnish, Nginx FastCGI cache, LiteSpeed server cache) |
| C3 | CDN/proxy (Cloudflare or similar) and whether its WAF/"Bot Fight" features are on |
| C4 | Security plugin (Wordfence, Sucuri, iThemes/Solid Security, etc.) and whether it has a firewall/rate limiting |
| C5 | Any HTTP basic auth, country blocking or IP restrictions on the site |
| C6 | Canonical URL behaviour: does `ez37solutions.org` redirect to `www.`? Trailing-slash redirects? |

### 2.4 Email

| # | Item |
|---|---|
| E1 | How the site sends email today (SMTP plugin name, transactional provider such as Postmark/SendGrid/Mailgun/Google Workspace/host mail) |
| E2 | Intended "From" address and name for customer emails (e.g. `no-reply@ez37solutions.org`) |
| E3 | Whether SPF, DKIM and DMARC are set up for the sending domain (yes/no; no DNS secrets needed) |
| E4 | Who should receive internal "new sale" notifications |

### 2.5 Business / Stripe (non-secret)

| # | Item |
|---|---|
| S1 | Stripe account status: exists / to be created; account country |
| S2 | Currency or currencies to charge |
| S3 | Whether VAT/sales tax must be charged or shown (and whether Stripe Tax is wanted) |
| S4 | Business details to appear on receipts: legal name, support email, support address, privacy policy URL (Stripe requires these on receipts) |
| S5 | Initial offering list (names and keys only; prices when available) |
| S6 | The placeholder brochure file (non-confidential) |
| S7 | Brand assets for emails (logo, colours) |

### 2.6 Deployment

| # | Item |
|---|---|
| D1 | How you deploy today (SFTP, Git, host's staging tool, WP-CLI) |
| D2 | Does the host offer a one-click staging site? |
| D3 | Any change-approval process the client requires before production changes |

---

## 3. Recommended development environment

### 3.1 Principles

1. **No production data.** Build the local site fresh. Do not clone the production database: it may contain customer and form data.
2. **Match versions, not data.** Use the same WordPress, PHP, database, theme and Elementor versions as production (from Section 2).
3. **Stripe sandbox only.** Test keys (`sk_test_…`) and sandbox webhook secrets. Live keys never exist on a development machine.
4. **Secrets outside Git.** Keys live in a local, git-ignored config file or environment variables.

### 3.2 Recommended local stack

| Component | Recommendation | Reason |
|---|---|---|
| Local WordPress | **DDEV** (Docker-based). LocalWP is an acceptable simpler alternative. | DDEV config is a text file you can commit, so the environment is reproducible. It pins PHP and database versions and includes **Mailpit** to catch all outgoing email. |
| Stripe | Stripe **sandbox** in the client's account (you invited with a developer role), or a sandbox in your own Stripe account until the client account exists | No live secrets are needed for development. |
| Webhooks locally | **Stripe CLI**: `stripe listen --forward-to https://ez37.ddev.site/wp-json/ez37/v1/stripe/webhook` | Stripe's documented way to test webhooks without a public URL. It also prints a local signing secret. |
| Event simulation | `stripe trigger …` and `stripe events resend …` | Replay and duplicate-delivery tests. |
| Email capture | Mailpit (bundled with DDEV) | No real emails leave the machine. |
| PHP deps | Composer (`stripe/stripe-php`, PHPUnit, PHPCS + WordPress Coding Standards) | Official SDK and WordPress conventions. |
| Code quality | PHPCS (WPCS), PHPStan (level 5–6) | Catches escaping and sanitisation issues early. |
| Version control | Private Git repository (GitHub/GitLab/Bitbucket) with branch protection on `main` | Commit history per phase as specified. |

### 3.3 Reproducing the site without production data

1. Install the same WordPress version, theme (and child theme), Elementor/Elementor Pro versions.
2. Recreate only the pages needed for testing (an offering page, the checkout page, success and cancel pages). Optionally, you can export **specific Elementor page templates** (JSON) from production. They contain layout only, but check them for embedded personal data before importing.
3. Install the plugins from W3 that touch caching, security, forms, SMTP and REST API behaviour, so conflicts show up locally rather than in production.

### 3.4 Staging

- Use the host's staging feature if available, or a separate subdomain with its own database. Never share a database with production.
- Staging uses **Stripe test keys** and its **own** webhook endpoint registered in the Stripe sandbox (its own `whsec_` secret).
- Protect staging from the public (basic auth or IP restriction). **The webhook route must be exempt** from the protection, or Stripe cannot deliver events.
- Set `noindex` on staging.

### 3.5 Repository layout (development workspace)

```
ez37-stripe/                      ← Git repository root
├── .ddev/                        ← local environment config (committed, no secrets)
├── ez37-payments/                ← the plugin (the only thing deployed)
├── docs/                         ← project documentation (not deployed)
│   ├── requirements.md  architecture.md  payment-flow.md  database.md
│   ├── security.md  webhook-events.md  fulfilment.md  testing.md
│   └── deployment.md  troubleshooting.md
├── bin/build-release.sh          ← builds a clean plugin zip (composer --no-dev)
├── .env.example                  ← variable names only, no values
├── .gitignore                    ← ignores .env, local config, vendor/ (dev), build/
└── README.md
```

---

## 4. Proposed architecture

### 4.1 Component diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│ PRESENTATION — WordPress + Elementor                                     │
│                                                                          │
│  Offering page (Elementor)                                               │
│    "Enrol now" button ──link──▶ /checkout/?offering=leadership-coaching  │
│                                                                          │
│  Checkout page ── [ez37_checkout] shortcode (form: name, email, consent) │
│  Success page  ── [ez37_payment_status]  (reads status; never decides)   │
│  Cancel page   ── static Elementor content                               │
└───────────────┬──────────────────────────────────────────────────────────┘
                │ POST (nonce, offering key, name, email)
                ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ EZ37 PAYMENTS PLUGIN (independent of Elementor)                          │
│                                                                          │
│  Checkout Controller (REST) ──▶ Offering Catalogue (server-side prices)  │
│          │                                                               │
│          ▼                                                               │
│  Checkout Service ──▶ Transaction Repository ──▶ [ez37_transactions]     │
│          │                                                               │
│          ▼                                                               │
│  Stripe Gateway (only class that talks to the Stripe SDK)                │
│          ▲                                                               │
│          │ retrieve session (verify)                                     │
│  Webhook Controller (REST) ──▶ Event Processor ──▶ [ez37_stripe_events]  │
│                                     │                                    │
│                                     ▼ mark paid (atomic)                 │
│                              Fulfilment Service (queued, retryable)      │
│                                     │                                    │
│                       ┌─────────────┴──────────────┐                     │
│                       ▼                            ▼                     │
│          Fulfilment handler(s)               Mailer + templates          │
│          e.g. "resource_access"              [ez37_email_log]            │
│                       │                                                  │
│                       ▼                                                  │
│          Entitlements [ez37_entitlements] ──▶ Secure download endpoint   │
│                                                                          │
│  Admin screen (read-only list, retry fulfilment, resend email)           │
│  Config (mode test/live, keys from env) · Logger (redacted)              │
└───────────────┬──────────────────────────────────────────────────────────┘
                │ HTTPS (TLS 1.2+)
                ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ STRIPE — Checkout Sessions · Prices · Customers · Receipts/Invoices ·    │
│          Webhook event destination                                       │
└──────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Key architectural decisions

| Decision | Choice | Reason |
|---|---|---|
| Payment UI | Stripe-hosted Checkout (`mode=payment`) | Lowest PCI scope (card data never touches WordPress), Stripe maintains it, supports wallets and receipts. |
| Price authority | Stripe **Price IDs** mapped from server-side offering config, one per mode (test/live) | Browser sends only an offering key. The amount comes from Stripe/config. We also store the expected amount and check it against the webhook. |
| Payment authority | Verified webhook (`checkout.session.completed` / `checkout.session.async_payment_succeeded`) **plus** a server-to-server retrieval of the Checkout Session | Matches Stripe's documented fulfilment pattern. The browser is never trusted. |
| Elementor integration | Elementor buttons are plain links to a checkout page; a shortcode renders the form | Elementor stays presentation-only. No custom Elementor widget needed (it can be added later). Works with or without Elementor Pro. |
| HTTP endpoints | WordPress REST API routes under `ez37/v1` | Standard routing, explicit `permission_callback`, raw body available for signature checks. |
| Webhook speed | Verify → record event → mark paid (fast DB writes) → return `200`. Fulfilment (email) runs separately. | Stripe requires a fast 2xx. Checkout also waits up to 10 seconds for the `checkout.session.completed` response before redirecting the customer. |
| Background work | WP-Cron single event scheduled immediately, plus a recurring "sweeper" that retries stuck/failed fulfilment. A real server cron is recommended in production. | No new dependency (no Action Scheduler, no WooCommerce). |
| Storage | Custom tables (see Section 6) | Unique constraints, indexes and atomic state transitions that `postmeta` cannot give reliably. |
| SDK | Official `stripe/stripe-php` via Composer, bundled in the release zip | Stripe's official library. Requires PHP 7.4+ (we will target PHP 8.1+ to match modern hosting). |

### 4.3 The success-page question — **decided: Option A at launch (D9)**

Stripe's own guide recommends calling the **same idempotent fulfilment function** from the success page, so a customer who arrives before the webhook still gets an instant result. This does not trust the browser: the browser supplies only the Checkout Session ID, and the **server retrieves the session directly from Stripe** and checks `payment_status` before doing anything.

Two options:

- **Option A (recommended): read-only success page.** It shows the status from our database. If the webhook has not arrived yet, it shows "Payment received, confirming…" and polls our status endpoint for a short time. Only webhooks change state. This matches your brief exactly.
- **Option B (Stripe's suggested optimisation):** if the transaction is still pending when the success page loads, the server retrieves the session from Stripe and runs the same idempotent `confirm_payment()` routine the webhook uses.

Option A is simpler to audit. Option B is a small addition we can make later without redesign.

---

## 5. Proposed payment lifecycle

### 5.1 End-to-end sequence

```
Customer        Elementor page      WP: EZ37 Payments                 Stripe
   │                  │                     │                           │
 1 │ clicks "Enrol" ─▶│                     │                           │
   │                  │ link to /checkout/?offering=KEY                 │
 2 │◀── checkout form (name, email, consent, nonce, honeypot) ──────────│
 3 │ submits form ─────────────────────────▶│ POST /wp-json/ez37/v1/checkout
   │                                        │ a. verify nonce + rate limit
   │                                        │ b. validate name/email
   │                                        │ c. look up KEY in catalogue:
   │                                        │    exists? active? price for mode?
   │                                        │ d. INSERT transaction
   │                                        │    payment_status = pending
   │                                        │ e. create Checkout Session ───▶│
   │                                        │    price ID, qty 1, mode=payment,
   │                                        │    customer_email, client_reference_id
   │                                        │    = txn public ID, metadata, success/
   │                                        │    cancel URLs, Idempotency-Key
   │                                        │◀──── session id + url ─────────│
   │                                        │ f. store session id on txn
 4 │◀──────────── redirect (303) to Stripe Checkout URL ────────────────────│
 5 │ pays on Stripe-hosted page ────────────────────────────────────────────▶│
   │                                        │                                │
 6 │                                        │◀── webhook checkout.session.completed
   │                                        │ g. verify Stripe-Signature (raw body,
   │                                        │    5-min tolerance)
   │                                        │ h. INSERT event id (UNIQUE) → if
   │                                        │    duplicate: return 200, stop
   │                                        │ i. check livemode matches config
   │                                        │ j. find txn by session id AND
   │                                        │    client_reference_id (must agree)
   │                                        │ k. check amount_total, currency,
   │                                        │    payment_status == 'paid'
   │                                        │ l. atomic UPDATE pending → paid
   │                                        │    (+ payment_intent, customer id,
   │                                        │    paid_at); fulfilment = pending
   │                                        │ m. schedule fulfilment job
   │                                        │──── 200 OK ────────────────────▶│
 7 │◀──────────── redirect to success_url?session_id={CHECKOUT_SESSION_ID} ──│
   │ success page shows status from DB      │                                │
   │                                        │                                │
 8 │                                        │ FULFILMENT JOB (separate request)
   │                                        │ n. claim txn (atomic lock)
   │                                        │ o. run handler for offering:
   │                                        │    create entitlement + token
   │                                        │ p. send confirmation/access email
   │                                        │    (email_log UNIQUE per type)
   │                                        │ q. fulfilment = completed
   │                                        │    (or failed → retried by sweeper)
 9 │◀── email: confirmation + secure access link                             │
10 │◀── Stripe receipt / invoice email (sent by Stripe, live mode) ──────────│
11 │ clicks access link ───────────────────▶│ GET /ez37-access/{token}
   │                                        │ hash token → entitlement active?
   │◀────────────── file streamed (no public URL) ──────────────────────────│
```

### 5.2 Payment status state machine

```
             create session
  (none) ───────────────────▶ pending
                                │
     ┌──────────────────────────┼─────────────────────────────┐
     │ completed &              │ completed &                 │ checkout.session.expired
     │ payment_status=paid      │ payment_status=unpaid       │ (abandoned / timed out)
     ▼                          ▼ (delayed method)            ▼
   paid ◀── async_payment_── processing ──async_payment_──▶ failed        expired
           succeeded                       failed
     │
     └──▶ refunded (future phase, via refund events or manual admin action)
```

Rules:

- Transitions are **forward-only** and done with a conditional `UPDATE … WHERE payment_status IN (allowed previous states)`. Out-of-order or repeated events cannot move a transaction backwards (e.g. a late `expired` cannot override `paid`).
- **Session-creation failure:** the transaction is marked `failed` with a sanitised reason. The customer sees a friendly error and can retry.
- **Cancelled checkout:** the customer clicks back and lands on the cancel page. The transaction stays `pending` until Stripe sends `checkout.session.expired` (we will set a shorter session expiry, e.g. 1–2 hours **[verify allowed range in Phase 3]**).

### 5.3 Fulfilment status state machine (independent of payment)

```
not_started ──(payment confirmed)──▶ pending ──claim──▶ in_progress ──▶ completed
                                        ▲                    │
                                        │                    ▼
                                        └── retry (sweeper, ── failed (attempts < max)
                                            backoff, admin)         │
                                                                    ▼
                                                     failed_permanent (needs admin)
```

- A fulfilment or email failure **never** changes `payment_status`. A paid transaction stays paid.
- `in_progress` carries a `locked_until` timestamp so a crashed job is picked up again after the lock expires.

### 5.4 Webhook events we subscribe to (only these)

| Event | Action |
|---|---|
| `checkout.session.completed` | If `payment_status = paid` → mark paid and queue fulfilment. If `unpaid` (delayed method) → mark `processing`. |
| `checkout.session.async_payment_succeeded` | `processing` → `paid`, queue fulfilment. |
| `checkout.session.async_payment_failed` | `processing` → `failed`. Optional email to the customer. |
| `checkout.session.expired` | `pending` → `expired`. |
| *(future)* `charge.refunded`, `charge.dispute.created` | Record refund/dispute and optionally revoke the entitlement. Not in the first release. |

All other event types: verify the signature, log as `ignored`, return `200`.

### 5.5 Receipts and invoices

Verified against Stripe's receipts documentation:

- **Stripe receipts:** with **Settings → Business → Customer emails → Successful payments** turned on, Stripe emails a receipt automatically after a successful payment. Stripe does **not** send these automatically for test payments; test receipts can be sent manually from the Dashboard.
- **Paid invoices — not used (D5).** Setting `invoice_creation[enabled]=true` would make Stripe email an invoice with PDF links, but Stripe prices this separately. The client chose receipts only.
- **Business details:** Stripe requires a legal business name, support address, support email and privacy policy URL on receipts.
- **Our own email** covers payment confirmation and resource access. It does not try to be a tax invoice.

---

## 6. Proposed database model

### 6.1 Custom tables vs. posts/postmeta — evaluation

| Criterion | Custom post type + postmeta | Custom tables |
|---|---|---|
| Uniqueness (one row per Stripe session/event) | Not enforceable — `meta_value` has no unique index | `UNIQUE` constraints at database level |
| Atomic state transitions (`pending → paid` only once) | Hard. Meta updates are not conditional. | One conditional `UPDATE … WHERE status = 'pending'` |
| Query/index performance | `meta_value` is unindexed `LONGTEXT` | Proper indexes on email, status, dates |
| Admin UI for free | Yes | Small custom list screen needed |
| Exposure risk | Can leak through REST/search/sitemaps if misconfigured | Not exposed by WordPress core |

**Recommendation:** four small custom tables, created with `dbDelta()` and versioned by a schema-version option. This is the minimum that gives database-enforced idempotency. All tables use the site's table prefix; all timestamps are UTC `DATETIME`; amounts are integers in the currency's **minor unit** (as Stripe uses).

### 6.2 Entity relationships

```
 ez37_transactions 1 ──── * ez37_stripe_events     (events that touched it)
        │ 1
        ├──────────── * ez37_entitlements          (normally 1 per resource)
        │ 1
        └──────────── * ez37_email_log             (max 1 per email type)

 Offerings and resources are configuration (code), not tables — see 7.3.
```

### 6.3 Table definitions (draft)

**`{prefix}ez37_transactions`** — one row per checkout attempt

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | Internal only, never shown publicly |
| `public_id` | CHAR(26) UNIQUE | Random, non-sequential ID. Used as Stripe `client_reference_id` and in metadata. |
| `offering_key` | VARCHAR(64) INDEX | e.g. `leadership-coaching` |
| `offering_name` | VARCHAR(191) | Snapshot at time of purchase |
| `stripe_price_id` | VARCHAR(255) | Snapshot |
| `amount` | BIGINT UNSIGNED | Expected price from offering config (minor units, before tax) |
| `currency` | CHAR(3) | ISO 4217, lowercase as Stripe returns |
| `customer_name` | VARCHAR(191) | |
| `customer_email` | VARCHAR(254) INDEX | |
| `stripe_mode` | VARCHAR(4) | `test` / `live` — prevents mixing |
| `stripe_checkout_session_id` | VARCHAR(255) UNIQUE NULL | |
| `stripe_payment_intent_id` | VARCHAR(255) UNIQUE NULL | |
| `stripe_customer_id` | VARCHAR(255) NULL | |
| `amount_subtotal` | BIGINT UNSIGNED NULL | From Stripe session `amount_subtotal` |
| `amount_tax` | BIGINT UNSIGNED NULL | From Stripe `total_details.amount_tax` (0 at launch — see 0.1) |
| `amount_paid` | BIGINT UNSIGNED NULL | From Stripe `amount_total`, for reconciliation |
| `payment_status` | VARCHAR(20) INDEX | `pending`, `processing`, `paid`, `failed`, `expired`, (`refunded`) |
| `fulfilment_status` | VARCHAR(20) INDEX | `not_started`, `pending`, `in_progress`, `completed`, `failed`, `failed_permanent` |
| `fulfilment_attempts` | SMALLINT UNSIGNED | |
| `fulfilment_locked_until` | DATETIME NULL | Crash-safe lock |
| `failure_code` | VARCHAR(64) NULL | Machine-readable |
| `failure_message` | VARCHAR(500) NULL | Sanitised; no secrets or card data |
| `created_at`, `updated_at` | DATETIME | |
| `paid_at`, `fulfilled_at` | DATETIME NULL | |

Indexes: `(payment_status, created_at)`, `(fulfilment_status, fulfilment_locked_until)` for the sweeper, `customer_email`.

**`{prefix}ez37_stripe_events`** — idempotency and audit trail for webhooks

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `stripe_event_id` | VARCHAR(255) **UNIQUE** | Duplicate delivery = insert fails = skip |
| `event_type` | VARCHAR(100) | |
| `livemode` | TINYINT(1) | |
| `stripe_object_id` | VARCHAR(255) INDEX | e.g. session ID |
| `transaction_id` | BIGINT UNSIGNED NULL INDEX | |
| `processing_status` | VARCHAR(20) | `received`, `processed`, `ignored`, `failed` |
| `error_message` | VARCHAR(500) NULL | |
| `received_at`, `processed_at` | DATETIME | |

We deliberately **do not store full event payloads**. They contain personal data and are retrievable from Stripe for 30 days if needed.

**`{prefix}ez37_entitlements`** — what a paid customer may access

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `transaction_id` | BIGINT UNSIGNED | **UNIQUE (`transaction_id`, `resource_key`)** — no duplicate grants |
| `resource_key` | VARCHAR(64) | e.g. `leadership-coaching-brochure` |
| `customer_email` | VARCHAR(254) INDEX | |
| `access_token_hash` | CHAR(64) UNIQUE | SHA-256 of a random 32-byte token. The raw token exists only in the email. |
| `status` | VARCHAR(20) | `active`, `revoked`, `expired` |
| `expires_at` | DATETIME | Issue time + 7 days (D6); period set in config |
| `access_count` | INT UNSIGNED | |
| `max_access` | INT UNSIGNED NULL | Optional cap |
| `last_accessed_at`, `created_at` | DATETIME | |

**`{prefix}ez37_email_log`** — prevents duplicate emails, supports resend

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `transaction_id` | BIGINT UNSIGNED | **UNIQUE (`transaction_id`, `email_type`)** |
| `email_type` | VARCHAR(40) | `customer_fulfilment`, `admin_sale_notice` (D10) |
| `attachment_included` | TINYINT(1) | 0 if the file exceeded the attachment limit and was sent as link-only |
| `recipient` | VARCHAR(254) | |
| `status` | VARCHAR(20) | `sending`, `sent`, `failed` |
| `attempts` | SMALLINT UNSIGNED | |
| `last_error` | VARCHAR(500) NULL | |
| `sent_at`, `created_at` | DATETIME | |

An admin "resend" is an explicit, logged action that does not break the uniqueness rule. It updates the row and increments `attempts`.

### 6.4 Retention

- Transactions: keep for the client's accounting retention period (Q11). `uninstall.php` will **not** drop tables unless an explicit constant is set, so financial records cannot be deleted by accident.
- Stripe events: prune after e.g. 90 days (scheduled job).
- Pending/expired transactions with no payment: personal data can be anonymised after e.g. 30 days.

---

## 7. Proposed plugin/module structure

### 7.1 Evaluation of the suggested structure

The structure in the brief is a sound starting point. I recommend these changes:

| Suggested | Recommendation | Why |
|---|---|---|
| `includes/class-*.php` (WordPress file naming) | `src/` with **PSR-4 autoloading** under namespace `EZ37\Payments` | Composer is needed anyway for the Stripe SDK, so its autoloader is free. Gives clean namespaces, as the brief asks. |
| `class-security.php` | **Remove** as a standalone class | A "security" grab-bag spreads responsibility. Each concern lives where it applies: nonce in the checkout controller, signature in the webhook controller, escaping in templates. |
| `class-checkout.php` doing everything | Split **Controller** (HTTP/REST) from **Service** (business logic) | Services can be unit-tested without HTTP. |
| No Stripe boundary | Add **`StripeGateway`** — the only class that calls the SDK | Single place to mock in tests, set API version, handle Stripe errors. |
| `tests/` inside the plugin | Keep tests in the plugin repo but **exclude from the release zip** | Production gets only runtime code. |
| `README.md` | Keep, plus `docs/` at repo root (not deployed) | Matches Section 13 of the brief. |

### 7.2 Recommended structure

```
ez37-payments/
├── ez37-payments.php            Plugin header, constants, requirements check, bootstrap
├── uninstall.php                Removes options only; tables kept unless explicitly allowed
├── composer.json / composer.lock
├── config/
│   ├── offerings.php            Offering catalogue (keys, names, price IDs per mode, fulfilment)
│   └── resources.php            Resource registry (key → private file / page / handler)
├── src/
│   ├── Plugin.php               Wires hooks; no business logic
│   ├── Config.php               Mode (test/live), keys from env/constants, sanity checks
│   ├── Install/Schema.php       dbDelta tables, schema versioning/migrations
│   ├── Offerings/
│   │   ├── Offering.php         Immutable value object
│   │   └── OfferingCatalogue.php  Load, validate, find active offering by key
│   ├── Stripe/StripeGateway.php   createCheckoutSession(), retrieveCheckoutSession(), verifyWebhook()
│   ├── Checkout/
│   │   ├── CheckoutController.php REST POST /ez37/v1/checkout (nonce, validation, rate limit)
│   │   └── CheckoutService.php    Create transaction → Stripe session → store IDs
│   ├── Webhooks/
│   │   ├── WebhookController.php  REST POST /ez37/v1/stripe/webhook (raw body, signature)
│   │   └── EventProcessor.php     Dedupe, validate, dispatch by event type
│   ├── Transactions/
│   │   ├── TransactionRepository.php  All SQL ($wpdb->prepare), atomic transitions
│   │   ├── PaymentStatus.php          Allowed states + transitions
│   │   └── FulfilmentStatus.php
│   ├── Fulfilment/
│   │   ├── FulfilmentService.php      Claim, run handler, record result, retries
│   │   ├── FulfilmentHandler.php      Interface: fulfil(Transaction): Result
│   │   ├── Handlers/ResourceAccessHandler.php  Creates entitlement + queues access email
│   │   └── Scheduler.php              WP-Cron single events + recurring sweeper
│   ├── Resources/
│   │   ├── EntitlementRepository.php
│   │   └── AccessController.php       Token → entitlement check → stream file
│   ├── Email/
│   │   ├── Mailer.php                 wp_mail wrapper, email_log idempotency
│   │   └── EmailRepository.php
│   ├── Frontend/
│   │   ├── Shortcodes.php             [ez37_checkout], [ez37_payment_status], [ez37_offering_price]
│   │   └── StatusController.php       GET status for success page (minimal data)
│   ├── Admin/
│   │   └── TransactionsPage.php       List/filter, retry fulfilment, resend email, revoke access (capability-gated)
│   └── Support/Logger.php             Redacting logger (no keys, no full emails at info level)
├── templates/
│   ├── checkout-form.php   payment-status.php
│   └── emails/  payment-confirmation.php  resource-access.php  admin-sale-notice.php
├── assets/  checkout.js  checkout.css   (small; no framework)
├── vendor/                     (release zip only; built with composer --no-dev)
└── tests/                      (excluded from release)
    ├── Unit/      offerings, state transitions, signature handling, validation
    └── Integration/ REST routes + DB using the WordPress test suite
```

### 7.3 Offering and resource configuration (illustrative — no real prices)

```php
// config/offerings.php — illustrative shape only
return [
    'leadership-coaching' => [
        'name'        => 'Leadership / Executive Coaching',
        'active'      => true,
        'currency'    => 'usd',               // D2
        'amount'      => null,                // provided by client; must match Stripe Price
        'price_ids'   => [
            'test' => 'price_TEST_PLACEHOLDER',
            'live' => 'price_LIVE_PLACEHOLDER',
        ],
        'tax'         => [ 'mode' => 'none' ], // NOT CONFIRMED — see 0.1. No tax applied.
        'fulfilment'  => [
            'handler'   => 'resource_access',
            'resources' => [ 'leadership-coaching-brochure' ],
        ],
    ],
];

// config/resources.php
return [
    'leadership-coaching-brochure' => [
        'type'        => 'private_file',
        'label'       => 'Programme brochure',
        'path'        => 'leadership-coaching/brochure-placeholder.pdf', // relative to private storage dir
        'attach'      => true,       // D6: also attach to the email…
        'max_attach'  => 5242880,    // …if under 5 MB; otherwise link-only
        'access_days' => 7,          // D6: download link lifetime
    ],
];
```

Replacing the brochure with real course material is a config/file change. Adding a new kind of fulfilment (e.g. private page, LMS enrolment) is a new class implementing `FulfilmentHandler`. **The payment engine does not change.**

On startup (and in an admin health check) the catalogue verifies that each active offering's Stripe Price exists, is active, and has the expected amount and currency in the current mode. A mismatch disables checkout for that offering and logs an error.

### 7.4 Elementor integration (Phase 8 preview)

1. An Elementor **Button** links to `/checkout/?offering=leadership-coaching`. No payment data in Elementor.
2. The `/checkout/` page (Elementor-designed) contains the `[ez37_checkout]` shortcode. It reads the offering key, validates it server-side and shows the offering name/price from config, plus the form.
3. Optional: `[ez37_offering_price offering="…"]` shows the correct price on marketing pages, so prices are never typed into Elementor by hand.

---

## 8. Security model

### 8.1 Attack surfaces

| # | Surface | Main threats |
|---|---|---|
| AS1 | `POST /wp-json/ez37/v1/checkout` (public) | Price/offering tampering, CSRF, spam/abuse creating many sessions, injection, email header injection |
| AS2 | `POST /wp-json/ez37/v1/stripe/webhook` (public) | Forged events, replay, duplicate delivery, out-of-order events, test events hitting live, payload/DoS |
| AS3 | Success page / status endpoint (public) | Enumerating transactions, leaking customer data via `session_id` |
| AS4 | Resource access URL (public) | Accessing paid material without paying, link sharing, guessing direct file URLs |
| AS5 | Admin screens | Privilege escalation, CSRF on "retry"/"resend", stored XSS from customer-entered names |
| AS6 | Secrets and configuration | Key leakage via Git, logs, error pages, backups; test/live mix-up |
| AS7 | Email | Spoofing, header injection, duplicate sends, sensitive data in email |
| AS8 | Database | SQL injection, data exposure, accidental deletion of records |
| AS9 | Dependencies | Outdated Stripe SDK, class collision with another plugin's bundled SDK |

### 8.2 Protections

| Threat | Protection |
|---|---|
| **Price manipulation** | Browser sends only `offering` key + name + email. Price ID, amount and currency come from server config. The webhook re-checks `amount_total` and `currency` against the stored transaction. |
| **Offering manipulation** | Key must match `^[a-z0-9-]{1,64}$` and exist in the catalogue with `active = true` and a price for the current mode. Otherwise a generic 400 response. |
| **CSRF on checkout** | WordPress nonce (`wp_rest`/custom action) + `Origin`/`Referer` check against the site host. Note: page caching can serve stale nonces to anonymous visitors, so the checkout page must be excluded from page cache, or the nonce fetched fresh via JS. Impact of CSRF here is low (it can only start a checkout the victim still has to pay for), but we still block it. |
| **Abuse / spam** | Per-IP and per-email rate limits (transients), honeypot field, short session expiry. Optional Cloudflare Turnstile/reCAPTCHA if abuse appears. |
| **Input validation** | `sanitize_text_field`, length limits, `is_email()`, rejecting CR/LF in name/email (header injection). |
| **Output escaping** | `esc_html`, `esc_attr`, `esc_url` in all templates and admin screens; email templates escape customer-provided values. |
| **SQL injection** | All queries via `$wpdb->prepare()` in repository classes only. No SQL elsewhere. |
| **Forged webhooks** | `Stripe\Webhook::constructEvent()` with the raw body (`$request->get_body()`), `Stripe-Signature` header and endpoint secret. Failure → 400, logged without payload. |
| **Replay** | SDK default 5-minute timestamp tolerance (never 0). Server clock synced (NTP). Event ID uniqueness blocks re-processing even inside the window. Optional extra layer: Stripe's published webhook IP ranges at firewall level. |
| **Duplicate / retried events** | `UNIQUE(stripe_event_id)` + forward-only conditional state updates. Stripe notes the *same* change can also arrive as two different events, so state guards (not just event IDs) prevent double fulfilment. |
| **Out-of-order events** | No reliance on event order or `created` timestamps. State machine accepts only valid forward transitions. Where needed, the Checkout Session is re-retrieved from Stripe. |
| **Test event hits live (or vice versa)** | Reject if `event.livemode` ≠ configured mode. Transactions store `stripe_mode`. |
| **Mismatched session ↔ transaction** | `client_reference_id` and stored `stripe_checkout_session_id` must both match. Otherwise flag for review and do not fulfil. |
| **Webhook route blocked/redirected** | Excluded from page cache, security-plugin firewall rules and staging basic auth. Registered with the exact canonical URL (no redirect — Stripe treats 3xx as failure). Documented in `deployment.md`. |
| **Success page leakage** | Shows only status and a masked email (`t***@domain.com`). No resource link on the page by default. `Referrer-Policy: no-referrer` and `noindex` on success/access pages. |
| **Unentitled resource access** | Files stored outside the web root (or in a private directory with deny rules — Apache `.htaccess` **and** an Nginx rule if Nginx). Served only via the access controller after hashing the token, checking `active`, expiry and optional access cap. `Cache-Control: private, no-store`. Tokens are 256-bit random; only the hash is stored. |
| **Admin access** | Custom capability `ez37_view_payments` (granted to administrators by default). Nonces on every admin action. Customer data escaped on output. |
| **Secrets** | Read from environment variables or `wp-config.php` constants **outside** Git. Never stored in `wp_options`, never logged. Startup check: mode `live` requires `sk_live_`/`rk_live_` key and vice versa, otherwise the plugin refuses to create sessions. Recommend a Stripe **restricted key** limited to the permissions we use **[define exact permissions in Phase 3]**. |
| **Logging** | Logger redacts anything matching key patterns (`sk_`, `rk_`, `whsec_`) and masks emails at info level. Logs go to a protected location or the PHP error log, not a public file. `WP_DEBUG_DISPLAY` must be off in production. |
| **Transport** | HTTPS only. Stripe requires TLS 1.2+ for webhooks. HSTS recommended at the host. |
| **Dependency collision** | On activation, check whether `\Stripe\StripeClient` is already loaded by another plugin. If version conflicts are found, prefix our copy with PHP-Scoper. |
| **Accidental data loss** | `uninstall.php` keeps financial tables unless explicitly allowed. |
| **Card data / PCI** | Card data never touches WordPress (Stripe-hosted Checkout). This keeps the client in the simplest PCI validation category **[client to confirm with Stripe/acquirer]**. |

### 8.3 Privacy

- Data minimisation: name, email, offering, amounts and Stripe IDs only.
- Update the site's privacy policy to mention Stripe as a payment processor.
- Customers may be in the UK/EU (GDPR), Nigeria (NDPA) and elsewhere. A retention and deletion policy (Q11) is needed, not legal advice from me — the client should confirm with their adviser.

---

## 9. Testing strategy

### 9.1 Layers

| Layer | Tooling | Covers |
|---|---|---|
| Unit | PHPUnit (+ Brain Monkey for WP functions) | Catalogue validation, state machine transitions, input validation, signature failure handling, token hashing |
| Integration | WordPress PHPUnit test suite, real DB, `StripeGateway` mocked | REST routes, DB constraints, idempotency, concurrency guards |
| Stripe sandbox end-to-end | Stripe CLI (`listen`, `trigger`, `events resend`), Stripe test cards | Real Checkout, real webhooks, retries, expired sessions |
| Manual UX | Browser + Mailpit (local) / real inbox (staging) | Customer journey, emails, receipts, mobile layout |
| Security review | PHPCS-WPCS, PHPStan, manual audit (Phase 9) | Escaping, nonces, capabilities, secrets |

Test card numbers and scenarios (e.g. `4242…` success, decline cards, 3-D Secure cards) will be taken from Stripe's testing documentation when the cases are scripted **[verify current list in Phase 10]**.

### 9.2 Test matrix

*Actual result* and *Pass/Fail* are filled in during Phase 10.

#### Checkout

| ID | Scenario | Input | Expected result | Actual | P/F |
|---|---|---|---|---|---|
| CHK-01 | Valid offering | Active key, valid name/email | Transaction `pending`; Stripe session created with configured price ID; 303 redirect to Stripe | | |
| CHK-02 | Invalid offering | `offering=does-not-exist` | 400 generic error; no transaction; no Stripe call | | |
| CHK-03 | Inactive offering | Key with `active=false` | Same as CHK-02; checkout page shows "not currently available" | | |
| CHK-04 | Offering with no price for current mode | Missing `price_ids.test` | Checkout refused; error logged; health check flags it | | |
| CHK-05 | Malformed offering key | `../x`, `<script>`, 200 chars | 400; nothing stored; input not reflected unescaped | | |
| CHK-06 | Valid customer info | Name + valid email | Values stored sanitised; email passed as `customer_email` | | |
| CHK-07 | Invalid email | `abc`, `a@`, empty | 400 with field error; no transaction | | |
| CHK-08 | Missing/oversized name | Empty / 500 chars | Field error | | |
| CHK-09 | Header injection attempt | Email containing `\r\nBcc:` | Rejected | | |
| CHK-10 | Stripe API failure | Gateway forced to throw (mock) / invalid price ID in sandbox | Transaction `failed` with sanitised reason; customer sees friendly retry message; no secret in logs | | |
| CHK-11 | Stripe network timeout | Simulated timeout | Same as CHK-10; retry with same idempotency key does not create two sessions | | |
| CHK-12 | Price mismatch with Stripe | Config amount ≠ Stripe Price amount | Checkout disabled for that offering; admin warning | | |

#### Payment

| ID | Scenario | Input | Expected result | Actual | P/F |
|---|---|---|---|---|---|
| PAY-01 | Successful payment | Stripe success test card | Webhook → `paid`, `paid_at` set, IDs stored; fulfilment queued | | |
| PAY-02 | Declined card | Stripe decline test card | Checkout shows decline; customer can retry on the same session; transaction stays `pending` | | |
| PAY-03 | 3-D Secure required, completed | Stripe 3DS test card, authenticate | As PAY-01 | | |
| PAY-04 | 3-D Secure failed | Stripe 3DS card, fail auth | No payment; stays `pending` | | |
| PAY-05 | Cancelled checkout | Click back/cancel on Stripe page | Lands on cancel page; transaction `pending` → `expired` on `checkout.session.expired` | | |
| PAY-06 | Payment interruption | Close tab after paying, before redirect | Webhook still marks `paid`; emails still sent | | |
| PAY-07 | Duplicate checkout attempt | Submit form twice quickly / two tabs | Two separate `pending` transactions max; only the paid one is fulfilled; other expires | | |
| PAY-08 | Double form submit (same request retried) | Network retry of the same POST | Idempotency key prevents duplicate Stripe session | | |
| PAY-09 | Delayed payment method (only if enabled, Q3) | Async test method | `processing` → `paid` on `async_payment_succeeded`; fulfilment only then | | |
| PAY-10 | Delayed payment fails | Async failure test | `processing` → `failed`; no fulfilment | | |
| PAY-11 | Session expires | Use `expires_at` minimum / `stripe trigger checkout.session.expired` | `expired` | | |

#### Webhooks

| ID | Scenario | Input | Expected result | Actual | P/F |
|---|---|---|---|---|---|
| WH-01 | Valid webhook | Real signed event via Stripe CLI | 200; event row `processed` | | |
| WH-02 | Invalid signature | Tampered body or wrong secret | 400; no state change; logged without payload | | |
| WH-03 | Missing signature header | POST without `Stripe-Signature` | 400 | | |
| WH-04 | Stale timestamp (replay) | Valid signature older than tolerance | 400 | | |
| WH-05 | Unknown event type | e.g. `customer.created` | 200; event logged `ignored` | | |
| WH-06 | Duplicate webhook | `stripe events resend <id>` | 200; no second state change, no second email | | |
| WH-07 | Stripe retry after our 500 | Force handler error once, then allow retry | First attempt fails cleanly; retry processes once | | |
| WH-08 | Concurrent duplicate delivery | Two identical events in parallel | Exactly one transition; one fulfilment | | |
| WH-09 | Out-of-order: `expired` after `paid` | Inject expired event for paid session | Ignored; stays `paid` | | |
| WH-10 | Out-of-order: async succeeded before completed | Inject in reverse order | Ends `paid`; fulfilled once | | |
| WH-11 | Unknown session | Signed event for session not in our DB | 200; logged as `ignored`/review; no fulfilment | | |
| WH-12 | Amount/currency mismatch | Event `amount_total` ≠ stored amount | Not fulfilled; flagged for review; admin alert | | |
| WH-13 | Livemode mismatch | Test event to live-configured site | Rejected/ignored; logged | | |
| WH-14 | `client_reference_id` mismatch | Session ID of txn A with reference of txn B | Not fulfilled; flagged | | |
| WH-15 | Malformed JSON | Garbage body with valid-looking header | 400 | | |
| WH-16 | Oversized body | Very large POST | Rejected early; no memory exhaustion | | |
| WH-17 | Response time | Normal paid event | 2xx well under 10 s (target < 2 s); email not sent inline | | |
| WH-18 | Route not redirected/cached | Request to exact registered URL | Direct 2xx, no 3xx, not served from cache | | |

#### Security

| ID | Scenario | Input | Expected result | Actual | P/F |
|---|---|---|---|---|---|
| SEC-01 | Manipulated price | Add `amount=1`/`price=price_x` to POST | Ignored; configured price used | | |
| SEC-02 | Manipulated offering ID | Change hidden offering field to inactive/other key | Validated server-side; inactive rejected | | |
| SEC-03 | CSRF | Cross-origin form POST without nonce | 403 | | |
| SEC-04 | Expired/invalid nonce | Old nonce | 403 with "please reload" message | | |
| SEC-05 | Unauthorised admin access | Subscriber/logged-out user requests admin page or retry action | Denied | | |
| SEC-06 | Admin action without nonce | Forged "resend email" link | Denied | | |
| SEC-07 | Stored XSS | Name `<script>alert(1)</script>` | Escaped in admin, emails, success page | | |
| SEC-08 | SQL injection | `' OR 1=1 --` in fields and status lookup | No effect; prepared statements | | |
| SEC-09 | Rate limiting | 30 checkout POSTs/minute from one IP | Throttled with 429 | | |
| SEC-10 | Protected resource without entitlement | Random/invalid token | 404 (no information leak) | | |
| SEC-11 | Direct file URL guess | Request private file path directly | 403/404 from web server | | |
| SEC-12 | Revoked/expired entitlement | Valid token, status `revoked` or past `expires_at` | Access denied with friendly message | | |
| SEC-13 | Status endpoint enumeration | Iterate session IDs / public IDs | No personal data returned; rate-limited | | |
| SEC-14 | Secrets not exposed | Grep repo, logs, HTML source, error output | No `sk_`, `rk_`, `whsec_` anywhere | | |
| SEC-15 | Live/test key mismatch | `mode=live` with `sk_test_` key | Plugin refuses checkout; admin notice | | |
| SEC-16 | Debug output | Force an exception with `WP_DEBUG_DISPLAY` off | Generic error to user; details only in protected log | | |

#### Fulfilment

| ID | Scenario | Input | Expected result | Actual | P/F |
|---|---|---|---|---|---|
| FUL-01 | Successful fulfilment | Paid transaction | Entitlement created; emails sent; `completed` | | |
| FUL-02 | Email failure | Mailer forced to fail | `payment_status` stays `paid`; fulfilment `failed`; retried by sweeper | | |
| FUL-03 | Resource unavailable | Configured file missing | Fulfilment `failed`; admin alerted; payment untouched | | |
| FUL-04 | Duplicate fulfilment | Run fulfilment job twice | One entitlement, one email of each type (unique keys) | | |
| FUL-05 | Concurrent fulfilment | Two jobs at once | Only one claims the lock | | |
| FUL-06 | Retry fulfilment (automatic) | Failure then success | Attempts incremented; ends `completed` | | |
| FUL-07 | Retry fulfilment (admin) | Admin clicks "Retry" | Completes; action logged | | |
| FUL-08 | Max retries reached | Persistent failure | `failed_permanent`; admin notified | | |
| FUL-09 | Crashed job | Kill process mid-fulfilment | Lock expires; sweeper resumes | | |
| FUL-10 | Customer refreshes success page | Reload 10 times | No extra emails or entitlements | | |
| FUL-11 | Resource replaced | Swap brochure file in config | New purchases get new resource; payment code unchanged | | |
| FUL-12 | WP-Cron not running | Disable WP-Cron | Server cron (or next page load) processes queue; documented | | |
| FUL-13 | Attachment within limit | Brochure under `max_attach` | Email contains attachment **and** link; `attachment_included=1` | | |
| FUL-14 | Attachment over limit | File over `max_attach` | Email sent with link only; logged; `attachment_included=0`; fulfilment still `completed` | | |
| FUL-15 | Link valid within 7 days | Use link on day 6 (clock mocked) | Download succeeds | | |
| FUL-16 | Link expired after 7 days | Use link on day 8 (clock mocked) | Friendly "link expired" page with contact details; no file | | |
| FUL-17 | Admin revokes access (manual refund) | Admin clicks "Revoke" | Link stops working immediately; action logged | | |
| FUL-18 | Admin resends access | Admin clicks "Resend" after expiry | New token, new 7-day window; old token invalid | | |

#### Tax readiness (D7 — tax NOT CONFIRMED)

| ID | Scenario | Input | Expected result | Actual | P/F |
|---|---|---|---|---|---|
| TAX-01 | No tax applied | Any offering with `tax.mode = none` | Session has no `automatic_tax` and no tax rates; `amount_tax = 0`; total = configured price | | |
| TAX-02 | Tax breakdown recorded | Paid session | `amount_subtotal`, `amount_tax`, `amount_paid` stored from Stripe | | |
| TAX-03 | Price tax behaviour untouched | Inspect sandbox Prices | `tax_behavior = unspecified` | | |
| TAX-04 | Validation tolerates future tax | Simulated event with subtotal = price and tax > 0 | Accepted as consistent (subtotal checked, totals add up); no false mismatch | | |
| TAX-05 | No tax wording | Offering, checkout and email pages | No "tax included"/"plus tax" text | | |

#### UX

| ID | Scenario | Input | Expected result | Actual | P/F |
|---|---|---|---|---|---|
| UX-01 | Customer completes payment | Full journey | Clear confirmation page; no errors | | |
| UX-02 | Customer cancels | Cancel on Stripe | Friendly cancel page with link back to offering | | |
| UX-03 | Return before webhook arrives | Delay webhook (stop CLI briefly) | Success page shows "confirming…" then updates/advises email | | |
| UX-04 | Confirmation email received | Paid | Correct name, offering, amount, currency; branded; passes spam checks on staging | | |
| UX-05 | Resource email/link works | Click link | File downloads; correct filename | | |
| UX-06 | Receipt | Manual receipt sent from Dashboard in test mode; automatic in live smoke test | Stripe receipt with correct business details; no invoice (D5) | | |
| UX-07 | Mobile | Phone-width browser | Form and pages usable | | |
| UX-08 | Accessibility basics | Keyboard only, screen reader labels | Form fields labelled; errors announced | | |
| UX-09 | Price display consistency | Compare offering page, checkout page, Stripe page | Same amount and currency everywhere | | |

---

## 10. Implementation phases

Each phase ends with passing tests, updated docs and a single logical commit (or small series).

| Phase | Scope | Done when | Commit(s) |
|---|---|---|---|
| **2 — Foundation** | Repo, DDEV, Composer, plugin bootstrap, `Config` (mode/keys from env, sanity checks), `Schema` (4 tables), `Logger`, PHPCS/PHPStan/PHPUnit wiring | Plugin activates on matching WP/PHP; tables created; config checks pass; CI lint green | `chore: scaffold ez37-payments plugin` · `feat: add payment data model and schema` |
| **3 — Stripe Checkout** | `OfferingCatalogue`, `StripeGateway`, `CheckoutService`, `CheckoutController`, checkout form shortcode (basic) | CHK-01…12, SEC-01…04 pass in sandbox | `feat: add offering catalogue` · `feat: implement Stripe checkout session creation` |
| **4 — Webhooks** | `WebhookController`, `EventProcessor`, event dedupe table | WH-01…18 pass with Stripe CLI | `feat: add Stripe webhook processing` · `test: add webhook idempotency tests` |
| **5 — Transactions** | State machine, atomic transitions, expiry handling, status endpoint, success/cancel shortcodes | PAY-01…11 pass | `feat: implement payment state management` |
| **6 — Fulfilment** | `FulfilmentService`, handler interface, `ResourceAccessHandler`, entitlements, private storage, access controller, scheduler/sweeper | FUL-01…12, SEC-10…12 pass | `feat: implement payment fulfilment and secure resource access` |
| **7 — Email** | `Mailer`, templates, email log, admin sale notice | FUL-02/04, UX-04/05 pass; Mailpit review | `feat: add confirmation and access emails` |
| **8 — Elementor integration** | Final shortcodes, price display shortcode, styling hooks, how-to for editors | UX-01…09 pass on local Elementor build | `feat: add Elementor-friendly checkout integration` |
| **8b — Admin** | Read-only transactions screen, retry/resend actions | SEC-05/06/07 pass | `feat: add payments admin screen` |
| **9 — Security audit** | Independent review of all code paths against Section 8; dependency check | Findings fixed or accepted in writing | `security: …` (one per fix) |
| **10 — Testing** | Full matrix on staging with Stripe sandbox; results recorded | All tests pass; matrix filled in | `test: record phase 10 results` |
| **11 — Deployment prep** | Release zip build, deployment & rollback checklist, live-mode runbook | Checklist signed off by you | `docs: add production deployment checklist` |

---

## 11. Deployment strategy

### 11.1 Environments

```
 Local (DDEV)  ──▶  Staging (host staging / subdomain)  ──▶  Production
 Stripe sandbox      Stripe sandbox                          Stripe live
 Stripe CLI          Registered sandbox webhook endpoint     Registered live webhook endpoint
 Mailpit             Real transactional email (test inbox)   Real transactional email
 Developer           You                                     You (with client approval)
```

### 11.2 Release artefact

- `bin/build-release.sh` produces `ez37-payments-x.y.z.zip`: runtime code + `vendor/` (built with `composer install --no-dev --optimize-autoloader`). No tests, docs, `.env` or dev tooling.
- Semantic versioning; tagged in Git; changelog in `README.md`.

### 11.3 Staging

1. Install the zip on staging (same plugin set and versions as production).
2. Configure **test** keys and the staging webhook secret via environment/`wp-config.php`.
3. Register the staging webhook endpoint in the Stripe sandbox with only the events in 5.4.
4. Exclude `/wp-json/ez37/v1/stripe/webhook`, `/checkout/` and the access route from caching, firewall challenges and basic auth.
5. Run the full Phase 10 matrix and manual end-to-end tests.

### 11.4 Production go-live (outline — full checklist in Phase 11)

1. Client completes Stripe account activation; business details, branding and receipt settings are set; live Products/Prices created.
2. Backup production files and database (your normal process).
3. Install the same tested zip. Plugin activates in **test mode** first: the webhook endpoint is reachable but checkout buttons are not yet linked.
4. **You or the client** add the live secret key (preferably a restricted key) and live webhook signing secret to the production config. These are never sent to me or committed to Git.
5. Register the live webhook endpoint (exact canonical URL, the events in 5.4).
6. Switch mode to `live`; the plugin health check confirms key prefix, price IDs and webhook secret are present.
7. Smoke test: one real low-value purchase on a test offering, then refund it in the Stripe Dashboard.
8. Link the Elementor buttons to the checkout page.
9. Monitor: Stripe webhook delivery dashboard, plugin admin screen and logs for the first days.

### 11.5 Rollback

- Disable checkout via a single setting/constant (buttons show "temporarily unavailable"). Deactivating the plugin does not delete data.
- Previous plugin version zip retained. Schema changes are additive only so older versions still work.
- If the webhook is unreachable, Stripe retries live events for up to three days. Once fixed, events are processed in order of arrival, safely because of idempotency.

---

## 12. Questions for you — **answered 5 October 2026**

Answers and their effects are in **Section 0** (D1–D12). Remaining open items are O1–O4 in 0.3. The original questions are kept below for the record.

| # | Question | Why it matters | My recommendation |
|---|---|---|---|
| **Q1** | Does the client already have a Stripe account? Which country is it registered in, and who will be the account owner? | Determines available payment methods, currencies, tax features, and how I get sandbox access | Client owns the account; you get a developer-level user for the sandbox only |
| **Q2** | Which currency (or currencies) will each offering be charged in? | Price model and catalogue shape | One currency per offering at launch |
| **Q3** | Cards and wallets only, or also delayed methods (bank debits/transfers)? | Adds the `processing` state and async events to the critical path | Cards + wallets at launch; design still handles async |
| **Q4** | Should name and email be collected on **our** form before Stripe, or only in Stripe Checkout? | Our form gives clean data before payment and lets us pre-fill Stripe; Checkout-only is fewer steps | Our short form (name, email, consent) → pre-filled Checkout |
| **Q5** | Are Stripe receipts enough, or does the client need formal **paid invoices** (company name, tax ID, invoice number)? Stripe charges separately for post-payment invoices. | Enables `invoice_creation` and the `stripe_invoice_id` field | Receipts at launch unless the client sells to businesses needing invoices |
| **Q6** | How should the resource be delivered: secure download link in email, attachment, or both? How long should access last (e.g. 30 days, 1 year, forever)? | Entitlement expiry and email design | Secure link only (no attachment), long-lived access with "resend my link" later |
| **Q7** | Is VAT/sales tax charged or shown on any offering? | May need Stripe Tax or tax-inclusive prices | Confirm with the client's accountant |
| **Q8** | Who adds or edits offerings later: a developer (config file in Git) or a non-technical client user (admin screen)? | An admin UI is significant extra scope and attack surface | Config file at launch; admin UI as a later phase if needed |
| **Q9** | Success page: Option A (read-only, webhook-only) or Option B (also confirms via server-side Stripe retrieval)? See 4.3. | Changes the success page logic | Option A at launch |
| **Q10** | One combined "Payment confirmed — here is your access" email, or separate confirmation and access emails? Should the client receive a "new sale" email? | Email templates and log types | One combined customer email + an internal sale notice |
| **Q11** | How long must payment records be kept, and is there a data-retention/privacy policy to follow? | Retention jobs, anonymisation, uninstall behaviour | Client to confirm with their adviser |
| **Q12** | Are refunds expected to revoke access automatically, or handled manually for now? | Whether refund events are in the first release | Manual at launch; automatic in a later phase |

Once you answer these and approve this design, I will start **Phase 2 — Foundation** and turn the relevant parts of this document into the `docs/` set.

---

## Appendix A — Stripe documentation sources (checked 4 October 2026)

- Fulfil orders (Checkout, hosted page): https://docs.stripe.com/checkout/fulfillment?payment-ui=stripe-hosted
- Receive Stripe events in your webhook endpoint: https://docs.stripe.com/webhooks
- Receipts and paid invoices: https://docs.stripe.com/receipts
- Stripe PHP library: https://github.com/stripe/stripe-php
- The Price object (`tax_behavior`): https://docs.stripe.com/api/prices/object
- The Checkout Session object (`customer_creation`, `amount_subtotal`, `total_details`, `adaptive_pricing`): https://docs.stripe.com/api/checkout/sessions/object

Key points used in this document:

- Fulfilment must be driven by webhooks; the landing page alone is not reliable. The fulfilment function must be safe to call multiple times, even concurrently, for the same Checkout Session, and should check `payment_status`.
- Handle `checkout.session.completed` and `checkout.session.async_payment_succeeded`; optionally `checkout.session.async_payment_failed`.
- With a `success_url` and a webhook listening for `checkout.session.completed`, Checkout waits up to 10 seconds for the webhook response before redirecting.
- Verify signatures with the official library using the raw body; default tolerance 5 minutes; never use tolerance 0.
- Events can be duplicated and are not delivered in order; track event IDs; don't use `created` for ordering.
- Live events are retried for up to three days; sandbox events three times over a few hours.
- Return 2xx quickly; 3xx redirects count as delivery failures; webhook HTTPS requires TLS 1.2+.
- Automatic receipts are enabled in Dashboard settings; test-mode receipts are sent manually. Post-payment invoices via Checkout are priced separately.
- `stripe-php` requires PHP 7.4+.

## Appendix B — Key design decisions (summary)

1. Stripe-hosted Checkout, one offering per session, quantity 1.
2. Browser sends an offering key only; server owns price via Stripe Price IDs per mode.
3. Verified webhooks are the only thing that marks a transaction paid.
4. Payment status and fulfilment status are separate state machines.
5. Four custom tables with database-enforced uniqueness for idempotency.
6. Fulfilment is pluggable (handler interface); resources are configuration.
7. Elementor links to a checkout page; a shortcode renders the form. No payment logic in Elementor.
8. Secrets from environment/`wp-config.php`, never in Git or the database; strict test/live separation.
