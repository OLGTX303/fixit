<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

// v-model bound registration form. PR2 has no persistence, so on submit we just
// sign the matching demo role in (or fall back to customer).
const form = ref({ name: '', email: 'alex@email.com', password: '', role: 'customer' })
const landing = { customer: 'home', provider: 'pro-requests', admin: 'admin-verify' }

async function submit() {
  try {
    const user = await auth.login(form.value.email, form.value.password)
    router.push({ name: landing[user.role] })
  } catch {
    await auth.loginAs(form.value.role)
    router.push({ name: landing[form.value.role] })
  }
}
</script>

<template>
  <div class="fx-page" style="max-width:420px;padding-top:24px">
    <div class="d-flex align-items-center gap-2 mb-4">
      <div class="d-flex align-items-center justify-content-center"
           style="width:42px;height:42px;border-radius:12px;background:var(--fx-accent);color:#fff">
        <AppIcon name="tool" :size="22" />
      </div>
      <span style="font-size:26px;font-weight:800;letter-spacing:-0.5px">Create account</span>
    </div>

    <form @submit.prevent="submit" class="d-flex flex-column gap-3">
      <input class="fx-input" v-model="form.name" placeholder="Full name" required />
      <input class="fx-input" type="email" v-model="form.email" placeholder="Email address" required />
      <input class="fx-input" type="password" v-model="form.password" placeholder="Password" required />

      <div>
        <label class="fx-label">I am a…</label>
        <div class="d-flex gap-2">
          <label v-for="r in ['customer','provider']" :key="r" class="fx-chip flex-fill justify-content-center"
                 :class="{ active: form.role === r }">
            <input type="radio" class="d-none" :value="r" v-model="form.role" />
            {{ r }}
          </label>
        </div>
      </div>

      <button class="btn btn-primary w-100" type="submit">Create Account</button>
    </form>

    <div class="text-center mt-3" style="font-size:13px;color:var(--fx-muted)">
      Already have an account?
      <router-link :to="{ name: 'login' }" class="text-accent fw-semibold text-decoration-none">Sign in</router-link>
    </div>
  </div>
</template>
