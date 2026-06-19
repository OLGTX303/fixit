<script setup>
import { computed } from 'vue'
const props = defineProps({
  rating: { type: Number, default: 0 },
  size: { type: Number, default: 14 },
  interactive: { type: Boolean, default: false },
  modelValue: { type: Number, default: 0 },
})
const emit = defineEmits(['update:modelValue'])
const value = computed(() => (props.interactive ? props.modelValue : props.rating))
</script>

<template>
  <div class="d-inline-flex" style="gap:2px">
    <svg v-for="i in 5" :key="i" :width="size" :height="size" viewBox="0 0 24 24"
         :style="{ cursor: interactive ? 'pointer' : 'default' }"
         @click="interactive && emit('update:modelValue', i)">
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
            :fill="i <= Math.round(value) ? '#FBBF24' : '#E5E7EB'" />
    </svg>
  </div>
</template>
