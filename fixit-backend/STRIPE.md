# Stripe test-mode payments

Save and reuse a Stripe **sandbox test card** without storing raw card data in FixIt.

## Flow

```
First visit                    Return visit
───────────                    ────────────
POST /customer  → cus_...      GET /config → shows saved pm_ + last4
POST /setup-intent             POST /pay-with-saved-method → pi_...
Payment Element + confirmSetup
POST /save-payment-method → pm_...
```

1. **Customer** — `POST /api/payments/stripe/customer` creates `cus_...` once per user.
2. **Save card** — `POST /api/payments/stripe/setup-intent` returns `client_secret`. Frontend uses Stripe Payment Element + `confirmSetup`. Then `POST /api/payments/stripe/save-payment-method` stores `pm_...`, brand, last4.
3. **Reuse** — `POST /api/payments/stripe/pay-with-saved-method` charges the saved `pm_` off-session. If 3DS is required, frontend completes with `stripe.confirmCardPayment(client_secret)`.
4. **Remove** — `DELETE /api/payments/stripe/saved-payment-method` detaches `pm_` and clears DB fields.

## Environment variables

```env
STRIPE_MODE=test
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

- **Test only:** `StripeService` rejects `sk_live_` keys and `STRIPE_MODE != test`.
- Never commit real keys. Use `.env` locally.

## Webhooks

Forward events to `POST /api/payments/stripe/webhook`:

```bash
stripe listen --forward-to localhost:8080/api/payments/stripe/webhook
```

Handled events:

| Event | Action |
|-------|--------|
| `setup_intent.succeeded` | Backup save of `pm_` |
| `payment_method.attached` | (logged via setup flow) |
| `payment_intent.succeeded` | Update `StripePayment` row |
| `payment_intent.payment_failed` | Record failure message |

## Database

User table fields (test mode only):

- `stripe_test_customer_id`
- `stripe_test_default_payment_method_id`
- `stripe_test_payment_method_last4`
- `stripe_test_payment_method_brand`
- `stripe_test_payment_method_created_at`

Run migration:

```bash
mysql -u root -p fixit < migrations/004_stripe_payments.sql
composer install
```

## Test cards (Stripe sandbox)

| Card | Result |
|------|--------|
| `4242 4242 4242 4242` | Success |
| `4000 0025 0000 3155` | Requires 3D Secure |
| `4000 0000 0000 9995` | Declined |

Use any future expiry and any 3-digit CVC.

## Test steps

1. Set Stripe test keys in `fixit-backend/.env`.
2. Run migration `004_stripe_payments.sql`.
3. Start backend + frontend.
4. Log in as customer (`alex@email.com` / `password123`).
5. **Payment** tab → save card with `4242...`.
6. Book a provider → redirected to pay with saved card.
7. Optional: `stripe listen` for webhook verification.

## Going live later

- Swap to `sk_live_` / `pk_live_` and separate DB columns (`stripe_live_*`).
- Keep test and live customers strictly separated.
- Enable Stripe Radar and production webhook endpoints.