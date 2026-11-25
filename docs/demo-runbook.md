# EZ37 Payments: demo runbook (local DDEV, Stripe TEST mode)

Everything here runs on your Mac. Nothing touches the client's production site or live Stripe.
Project folder: `/Users/tonyedick/Documents/Claude/Projects/ez37-stripe`

## A. One-time setup (do once, tonight)

**1. Install the Stripe CLI and log in** (skip if `stripe --version` already works)

```bash
brew install stripe/stripe-cli/stripe
stripe login
```

Log in to the same sandbox account that owns your test keys.

**2. Get the webhook secret**

```bash
stripe listen --print-secret
```

Copy the `whsec_…` value it prints.

**3. Edit `.ddev/.env`** (hidden file: in Finder press Cmd+Shift+. or run `open -e .ddev/.env` from the project folder). It must contain these lines, with no quotes and no spaces:

```
EZ37_STRIPE_MODE=test
EZ37_STRIPE_TEST_SECRET_KEY=sk_test_...          # your sandbox secret key
EZ37_STRIPE_TEST_WEBHOOK_SECRET=whsec_...        # the value from step 2
EZ37_PAYMENTS_CHECKOUT_ENABLED=1
EZ37_PAYMENTS_PRIVATE_DIR=/var/www/html/sample-resources
```

**4. Put the sandbox Price ID in the offering.** In the Stripe sandbox dashboard create a one-time Price of USD 2,699.00 for the programme (Product catalogue, Add product). Copy its `price_…` ID into `ez37-payments/config/offerings.php`, in the `pccp` entry under `price_ids` → `test`.

**5. Restart DDEV so it reads `.env`**

```bash
cd /Users/tonyedick/Documents/Claude/Projects/ez37-stripe
ddev restart
```

**6. Rebuild the pages once** (applies all the latest design changes)

```bash
ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/programme-local.php
ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/enrol-local.php
```

**7. Run the preflight check**

```bash
ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/demo-preflight.php
```

It prints `[ OK ]` or `[FAIL]` for each requirement, with the exact fix next to every failure. Run it again until it says "All checks passed". It also warms the Stripe price check, so the first checkout is quick.

## B. Before each demo (2 minutes)

Open three Terminal windows or tabs.

**Terminal 1: forward Stripe events to your site. Leave it running the whole demo.**

```bash
stripe listen \
  --events checkout.session.completed,checkout.session.async_payment_succeeded,checkout.session.async_payment_failed,checkout.session.expired \
  --forward-to https://ez37.ddev.site:8443/wp-json/ez37/v1/stripe/webhook \
  --skip-verify
```

Check the first line says it is ready. Its signing secret must be the same one in `.ddev/.env`.

**Terminal 2: DDEV running.**

```bash
cd /Users/tonyedick/Documents/Claude/Projects/ez37-stripe
ddev start
ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/demo-preflight.php
```

**Browser tabs:**
- Site: https://ez37.ddev.site:8443/pccp-v2/
- Mailpit (email inbox): https://ez37.ddev.site:8026 (or run `ddev launch -m`). Click "Delete all" first so only demo emails show.
- Optional: Stripe sandbox dashboard, Payments page (make sure the Test mode switch is on).

## C. The demo flow (about 90 seconds)

1. On the PCCP page click **Enrol Now**. The checkout opens at `/checkout/?offering=pccp`.
2. Fill in the enrolment form. Use a throwaway name and your own email. Every field has "Your answer" as its placeholder.
3. Tick **I have read and agree** (or click **read the agreement**, read, click **I Agree**).
4. Click **Continue to secure payment**. You land on Stripe's hosted page.
5. Pay with the test card:
   - Card number `4242 4242 4242 4242`
   - Any future expiry (for example `12/34`), any 3-digit CVC, any postcode
6. You return to the Thank You page. It shows "Confirming your payment…" for 1 to 3 seconds, then switches to **Payment confirmed** by itself.
7. In Mailpit the confirmation email arrives within a second or two, with the receipt details and the brochure PDF attached. A second "New sale" email goes to the sales address.

What to point out: the price is decided on the server, not by the browser; payment is confirmed by Stripe's signed webhook, not by the success page; the customer needs no WordPress account; the email is sent only once, even if the page is refreshed.

Other test cards (optional): `4000 0000 0000 0002` is declined; `4000 0027 6000 3184` asks for 3-D Secure.

## D. If something goes wrong

| What you see | Cause | Fix |
|---|---|---|
| Page stays on "Confirming your payment…" | Webhook not reaching the site | Terminal 1 must be running. Look for `[200]`. If `[400]`, the secret in `.ddev/.env` does not match `stripe listen --print-secret`: fix it and run `ddev restart`. |
| Terminal 1 shows `[500]` | Webhook secret missing from `.env` | Add it, then `ddev restart` |
| Terminal 1 shows nothing at all | `stripe listen` was started after the payment | Make a new payment, or `stripe events resend evt_…` using an ID from the Stripe dashboard (Developers, Events, `checkout.session.completed`) |
| Enrol shows "unavailable" | No test Price ID, checkout disabled, or key missing | Run the preflight; it names the exact line to fix |
| Payment confirmed but no email | Mailpit not the mail catcher, or fulfilment failed | Run the preflight (it sends a test email); check WordPress admin, Payments, "Delivery" column; use **Retry delivery now** |
| Mailpit download link opens a black tab | Mailpit's preview sandbox blocks downloads | Right-click the button, Copy link address, paste into a new tab. Or use the attached PDF. |
| Design looks old | Pages not rebuilt, or browser cache | Re-run the two scripts in A6, then hard-refresh (Cmd+Shift+R) |

## E. Never do these

- Never paste a live Stripe key (`sk_live_…`) anywhere in this setup.
- Never commit `.ddev/.env` (it is already git-ignored).
- `--skip-verify` is for local DDEV only.
- Remove the "Nav: Flat (tap to switch)" dev button before the site goes anywhere public.
