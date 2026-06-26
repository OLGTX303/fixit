<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PaymentSheet from '../../components/PaymentSheet.vue'

const route = useRoute()
const router = useRouter()

const bookingId = computed(() => {
  const id = route.query.booking_id
  return id ? Number(id) : null
})
const amount = computed(() => {
  const raw = route.query.amount
  return raw ? parseFloat(raw) : null
})
const setupComplete = computed(() => route.query.setup === 'complete')
const sheetOpen = computed(() => !!(bookingId.value && amount.value))

onMounted(() => {
  if (!sheetOpen.value) {
    router.replace({ name: 'job-tracker' })
  }
})

function onClose() {
  router.replace({ name: 'job-tracker' })
}

function onPaid(id) {
  router.push({ name: 'job-tracker', query: { id, paid: '1' } })
}
</script>

<template>
  <!-- Stripe 3DS / card-save return — sheet only, no full page chrome -->
  <PaymentSheet
    :open="sheetOpen"
    :booking-id="bookingId"
    :amount="amount"
    :setup-complete="setupComplete"
    @close="onClose"
    @paid="onPaid"
  />
</template>