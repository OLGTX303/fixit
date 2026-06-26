/**
 * Stripe test-mode payments — card data never touches our backend.
 * Only SetupIntent / PaymentIntent client_secrets and pm_ IDs are exchanged.
 */

import { loadStripe } from '@stripe/stripe-js'
import * as api from './api'

let stripePromise = null

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
export async function mountSaveCardElement(containerEl) {
  await api.ensureStripeCustomer()
  const { client_secret: clientSecret } = await api.createStripeSetupIntent()
  const { stripe } = await getStripe()

  const elements = stripe.elements({
    clientSecret,
    appearance: { theme: 'stripe', variables: { colorPrimary: '#FF6635' } },
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
          return_url: `${window.location.origin}/payment?setup=complete`,
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

/** Pay using saved pm_ without re-entering card details. */
export async function payWithSavedCard({ amountCents, bookingId, currency = 'myr' }) {
  const { stripe } = await getStripe()
  const result = await api.payWithStripeSavedMethod({
    amount_cents: amountCents,
    booking_id: bookingId,
    currency,
  })

  if (result.requires_action && result.client_secret) {
    const { error, paymentIntent } = await stripe.confirmCardPayment(result.client_secret)
    if (error) throw new Error(error.message)
    return { ...result, status: paymentIntent?.status ?? result.status, paid: paymentIntent?.status === 'succeeded' }
  }

  return result
}

/** Pay booking with wallet balance, card, or both (wallet applied first). */
export async function payBooking({ bookingId, useWallet = true }) {
  const { stripe } = await getStripe()
  const result = await api.payBooking({
    booking_id: bookingId,
    use_wallet: useWallet,
  })

  if (result.requires_action && result.client_secret) {
    const { error, paymentIntent } = await stripe.confirmCardPayment(result.client_secret)
    if (error) throw new Error(error.message)
    return {
      ...result,
      status: paymentIntent?.status ?? result.status,
      paid: paymentIntent?.status === 'succeeded',
    }
  }

  return result
}