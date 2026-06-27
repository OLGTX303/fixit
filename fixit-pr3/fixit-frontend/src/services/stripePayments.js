/**
 * Stripe test-mode payments — card data never touches our backend.
 * Only SetupIntent / PaymentIntent client_secrets and pm_ IDs are exchanged.
 */

import { loadStripe } from '@stripe/stripe-js'
import * as api from './api'

let stripePromise = null

export function formatSavedCard(saved, { style = 'dots', fallback = null } = {}) {
  if (!saved?.has_saved_payment_method) return fallback
  const brand = (saved.brand || 'card').replace(/^./, (c) => c.toUpperCase())
  return style === 'ending' ? `${brand} ending in ${saved.last4}` : `${brand} •••• ${saved.last4}`
}

export async function getStripe() {
  const config = await api.getStripeConfig()
  if (!config.configured || !config.publishable_key) {
    throw new Error('Stripe test mode is not configured on the server')
  }
  if (!stripePromise) {
    stripePromise = loadStripe(config.publishable_key)
  }
  return { stripe: await stripePromise, config }
}

/** Mount Payment Element for saving a test card (SetupIntent). */
export async function mountSaveCardElement(containerEl, { returnUrl } = {}) {
  await api.ensureStripeCustomer()
  const { client_secret: clientSecret } = await api.createStripeSetupIntent()
  const { stripe } = await getStripe()

  const elements = stripe.elements({
    clientSecret,
    appearance: {
      theme: 'stripe',
      variables: {
        colorPrimary: '#FF6635',
        colorBackground: '#FFFFFF',
        colorText: '#1a1a1a',
        colorDanger: '#ef4444',
        borderRadius: '12px',
        fontFamily: 'system-ui, -apple-system, sans-serif',
      },
      rules: {
        '.Input': {
          backgroundColor: '#FFFFFF',
          border: '1px solid rgba(255, 102, 53, 0.18)',
          boxShadow: 'none',
        },
        '.Tab': {
          backgroundColor: 'rgba(255, 255, 255, 0.9)',
          border: '1px solid rgba(255, 255, 255, 0.65)',
        },
        '.Tab--selected': {
          backgroundColor: 'rgba(255, 102, 53, 0.08)',
          borderColor: 'rgba(255, 102, 53, 0.35)',
        },
      },
    },
  })
  const paymentElement = elements.create('payment', {
    layout: 'tabs',
  })
  paymentElement.mount(containerEl)

  return {
    stripe,
    elements,
    clientSecret,
    async confirmSave() {
      const { error: submitError } = await elements.submit()
      if (submitError) throw new Error(submitError.message)

      const { error, setupIntent } = await stripe.confirmSetup({
        elements,
        clientSecret,
        redirect: 'if_required',
        confirmParams: {
          return_url: returnUrl || `${window.location.origin}/payment?setup=complete`,
        },
      })
      if (error) throw new Error(error.message)

      const pmId = typeof setupIntent?.payment_method === 'string'
        ? setupIntent.payment_method
        : setupIntent?.payment_method?.id

      if (!pmId) throw new Error('No payment method returned from SetupIntent')

      return api.saveStripePaymentMethod(pmId)
    },
    destroy() {
      paymentElement.destroy()
    },
  }
}

async function confirm3dsIfNeeded(stripe, result) {
  if (!result.requires_action || !result.client_secret) return result
  const { error, paymentIntent } = await stripe.confirmCardPayment(result.client_secret)
  if (error) throw new Error(error.message)
  return {
    ...result,
    status: paymentIntent?.status ?? result.status,
    paid: paymentIntent?.status === 'succeeded',
  }
}

/** Pay using saved pm_ without re-entering card details. */
export async function payWithSavedCard({ amountCents, bookingId, currency = 'myr' }) {
  const { stripe } = await getStripe()
  const result = await api.payWithStripeSavedMethod({
    amount_cents: amountCents,
    booking_id: bookingId,
    currency,
  })
  return confirm3dsIfNeeded(stripe, result)
}

/** Pay booking with wallet balance, card, or both (wallet applied first). */
export async function payBooking({ bookingId, useWallet = true }) {
  const { stripe } = await getStripe()
  const result = await api.payBooking({
    booking_id: bookingId,
    use_wallet: useWallet,
  })
  return confirm3dsIfNeeded(stripe, result)
}