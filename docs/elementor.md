# Elementor integration

> **Status: Phase 8 built (v0.6.0).** Elementor stays the presentation layer. It never holds a price,
> a Stripe ID or any payment logic: pages only *name* an offering, and the plugin does the rest.

## How the pieces fit

```
Programme page (Elementor)                       Checkout page          Stripe          Return pages
┌──────────────────────────────┐   link     ┌──────────────────┐  303  ┌───────┐    ┌───────────────────────┐
│ Heading, text, images …      │ ─────────▶ │ [ez37_checkout]  │ ────▶ │ pay   │ ─▶ │ [ez37_payment_status] │
│ Price: [ez37_offering_price] │            │ name + email     │       └───────┘    │ [ez37_payment_cancelled]
│ Button → /checkout/?offering=│            └──────────────────┘                    └───────────────────────┘
└──────────────────────────────┘
```

## 1. "Enrol now" buttons

Use a normal Elementor **Button** widget. Set its **Link** to:

```
/checkout/?offering=leadership-coaching
```

The value after `offering=` is the offering **key** from `config/offerings.php`. Unknown or inactive
keys show "This programme is not available for online purchase at the moment" — they never error.

Do not add the price, amount or currency to the link. The server ignores them anyway.

## 2. Showing the price on a page

Insert a **Shortcode** widget (or type the shortcode inside a Text Editor widget):

```
[ez37_offering_price offering="leadership-coaching"]
```

Output: `<span class="ez37-price">$1.00 USD</span>`. It prints nothing for an unknown or inactive
offering, so a withdrawn programme never shows a stale price. Change prices only in
`config/offerings.php` (and the matching Stripe Price) — every page updates.

## 3. The three plugin pages

| Page | Slug | Elementor content |
|---|---|---|
| Checkout | `checkout` | Shortcode widget: `[ez37_checkout]` |
| Payment complete | `payment-complete` | Shortcode widget: `[ez37_payment_status]` |
| Payment cancelled | `payment-cancelled` | Shortcode widget: `[ez37_payment_cancelled]` |

Design the rest of each page freely (header, footer, intro text, contact details). All three must
be **excluded from page caching**.

A programme can also have its own checkout block: `[ez37_checkout offering="leadership-coaching"]`
on the programme page itself. The attribute wins over `?offering=`.

## 4. Styling to match the site

`assets/ez37-payments.css` loads automatically on pages that render one of the shortcodes. It is
deliberately light: fonts and text colour come from the theme / Elementor kit. To match the brand,
override the custom properties in **Elementor → Site Settings → Custom CSS** (Elementor Pro) or
**Appearance → Customize → Additional CSS**:

```css
:root {
	--ez37-accent: #1d3b6f;       /* button and focus colour */
	--ez37-accent-text: #ffffff;  /* button text */
	--ez37-radius: 4px;           /* corners */
	--ez37-border: #d0d5dd;
	--ez37-surface: #ffffff;      /* card background */
	--ez37-max-width: 520px;      /* form width */
}
```

| Variable | Used for |
|---|---|
| `--ez37-accent`, `--ez37-accent-text` | Pay button, "Try again" button, input focus ring |
| `--ez37-border`, `--ez37-surface`, `--ez37-radius`, `--ez37-max-width` | The card around the form / status / cancel message |
| `--ez37-muted` | Small print under the button |
| `--ez37-error-bg`, `--ez37-error-text` | Error box, "Payment not completed" heading |
| `--ez37-success` | "Payment confirmed" heading |
| `--ez37-notice-bg`, `--ez37-notice-text` | "Test mode" banner |

Class names (`.ez37-checkout`, `.ez37-checkout__field`, `.ez37-status--paid`, `.ez37-cancelled__retry`,
…) are stable and can be targeted for anything the variables do not cover. To switch the plugin's
styles off completely and style everything in Elementor:

```php
add_filter( 'ez37_payments_load_styles', '__return_false' );
```

The stylesheet is enqueued while the shortcode renders, so WordPress prints it in the footer. On a
slow connection this can cause a brief unstyled flash; if that is noticeable, enqueue the
`ez37-payments` style handle on those pages from the theme.

## 5. Behaviour built into the form

- The pay button disables itself on submit (prevents double submissions) and re-enables if the
  customer returns with the browser's Back button.
- A "Test mode: no real payment will be taken" banner appears whenever the plugin is in test mode.
- Errors (invalid input, expired session, too many attempts, Stripe unavailable) come back as a
  short message above the form; details are only in the server log.

## What not to do

- Do not use Elementor Pro **Form** actions, custom HTML forms or JavaScript to start payments.
- Do not hard-code prices in text widgets — use `[ez37_offering_price]`.
- Do not link "Enrol" buttons straight to Stripe Payment Links; payments made that way bypass the
  transaction record, fulfilment and emails.
