<script setup>
import { ref, onMounted, computed } from 'vue'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/AppIcon.vue'

const providersStore = useProvidersStore()
const auth = useAuthStore()

// Editable provider profile (v-model). Defaults seeded from the logged-in
// provider's record once stores load.
const form = ref({ name: '', bio: '', location: '', base_rate: 45, available: true })
const allServices = ['Pipe Repair', 'Drain Cleaning', 'Leak Detection', 'Installation', 'Boiler Service']
const selectedServices = ref([])
const saved = ref(false)

const myProfile = computed(() =>
  providersStore.providers.find(p => p.user_id === auth.user?.id))

onMounted(async () => {
  await providersStore.load()
  if (myProfile.value) {
    form.value = {
      name: myProfile.value.name,
      bio: myProfile.value.bio,
      location: myProfile.value.location,
      base_rate: myProfile.value.base_rate,
      available: true,
    }
    selectedServices.value = [...myProfile.value.services]
  }
})

function toggleService(s) {
  const i = selectedServices.value.indexOf(s)
  i === -1 ? selectedServices.value.push(s) : selectedServices.value.splice(i, 1)
}
function save() {
  saved.value = true
  setTimeout(() => (saved.value = false), 2000)
}
</script>

<template>
  <div class="fx-page" style="max-width:560px">
    <h1 class="fw-bold mb-4" style="font-size:20px">My Profile</h1>

    <div class="text-center mb-4">
      <div class="position-relative d-inline-block">
        <div class="fx-avatar" style="width:76px;height:76px;font-size:28px;border:3px solid var(--fx-border)">
          {{ (form.name || '?').split(' ').map(w => w[0]).join('') }}
        </div>
        <div class="position-absolute d-flex align-items-center justify-content-center"
             style="bottom:0;right:0;width:26px;height:26px;border-radius:50%;background:var(--fx-accent);color:#fff;border:2px solid #fff">
          <AppIcon name="user" :size="13" />
        </div>
      </div>
    </div>

    <div class="d-flex flex-column gap-3">
      <div>
        <label class="fx-label">Full Name</label>
        <input class="fx-input" v-model="form.name" />
      </div>
      <div>
        <label class="fx-label">Bio</label>
        <textarea class="fx-input" rows="3" v-model="form.bio" style="resize:none"></textarea>
      </div>
      <div>
        <label class="fx-label">Location</label>
        <input class="fx-input" v-model="form.location" />
      </div>
      <div>
        <label class="fx-label">Hourly Rate</label>
        <div class="fx-input d-flex align-items-center gap-2">
          <span class="fw-bold text-accent" style="font-size:18px">$</span>
          <span class="fw-bold" style="font-size:22px">{{ form.base_rate }}</span>
          <span style="color:var(--fx-muted)">/hr</span>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light" style="width:32px;height:32px;padding:0;border-radius:8px"
                    @click="form.base_rate = Math.max(10, form.base_rate - 5)">−</button>
            <button class="btn btn-primary" style="width:32px;height:32px;padding:0;border-radius:8px"
                    @click="form.base_rate += 5">+</button>
          </div>
        </div>
      </div>
      <div>
        <label class="fx-label">Services Offered</label>
        <div class="d-flex flex-wrap gap-2">
          <span v-for="s in allServices" :key="s" class="fx-chip sm" :class="{ active: selectedServices.includes(s) }"
                @click="toggleService(s)">{{ s }}</span>
        </div>
      </div>

      <div class="fx-card d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-semibold" style="font-size:14px">Available for Bookings</div>
          <div style="font-size:12px;color:var(--fx-muted)">Customers can book your services</div>
        </div>
        <div class="form-check form-switch m-0">
          <input class="form-check-input" type="checkbox" v-model="form.available" style="cursor:pointer" />
        </div>
      </div>
    </div>

    <button class="btn btn-primary w-100 mt-4" @click="save">
      {{ saved ? '✓ Saved' : 'Save Changes' }}
    </button>
  </div>
</template>
