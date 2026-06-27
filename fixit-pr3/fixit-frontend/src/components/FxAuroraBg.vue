<script setup>
import { computed, onMounted, ref } from 'vue'
import Aurora from './vue-bits/Aurora.vue'

/** FixIt aurora — vue-bits Aurora tinted with brand orange, not the default green. */
const FIXIT_STOPS = ['#fbf9f8', '#FF6635', '#FFB59F']

const reduceMotion = ref(false)

onMounted(() => {
  reduceMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
})

const colorStops = computed(() => FIXIT_STOPS)
</script>

<template>
  <div class="fx-aurora-bg" aria-hidden="true">
    <div v-if="reduceMotion" class="fx-aurora-fallback" />
    <Aurora
      v-else
      class="fx-aurora-canvas"
      :color-stops="colorStops"
      :amplitude="0.85"
      :blend="0.42"
      :speed="0.65"
    />
    <div class="fx-aurora-veil" />
  </div>
</template>

<style scoped>
.fx-aurora-bg {
  position: fixed;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  overflow: hidden;
}
.fx-aurora-canvas {
  position: absolute;
  inset: -8% -4%;
  width: 108%;
  height: 116%;
  opacity: 0.72;
}
.fx-aurora-fallback {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 55% at 12% 8%, rgba(255, 102, 53, 0.22), transparent 58%),
    radial-gradient(ellipse 70% 50% at 88% 18%, rgba(255, 181, 159, 0.28), transparent 55%),
    radial-gradient(ellipse 65% 45% at 50% 100%, rgba(255, 219, 208, 0.35), transparent 60%),
    var(--fx-bg, #fbf9f8);
}
.fx-aurora-veil {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    rgba(251, 249, 248, 0.35) 0%,
    rgba(251, 249, 248, 0.12) 42%,
    rgba(251, 249, 248, 0.55) 100%
  );
}
</style>