<script setup>
// Reactive filter controls for the customer Search view. Uses v-model on each
// field; parent recomputes the filtered provider list from these values.
import AppIcon from './AppIcon.vue'

defineProps({
  categories: { type: Array, default: () => [] },
  modelCategory: { type: [Number, null], default: null },
  modelMaxDistance: { type: Number, default: 5 },
  modelMaxPrice: { type: Number, default: 100 },
  modelMinRating: { type: Number, default: 0 },
})
const emit = defineEmits([
  'update:modelCategory', 'update:modelMaxDistance', 'update:modelMaxPrice', 'update:modelMinRating',
])
</script>

<template>
  <div class="fx-card mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
      <AppIcon name="filter" :size="16" class="text-accent" />
      <span class="fw-bold" style="font-size:14px">Filters</span>
    </div>

    <!-- Category chips (reactive v-for) -->
    <div class="d-flex flex-wrap gap-2 mb-3">
      <span class="fx-chip sm" :class="{ active: modelCategory === null }"
            @click="emit('update:modelCategory', null)">All</span>
      <span v-for="c in categories" :key="c.id" class="fx-chip sm"
            :class="{ active: modelCategory === c.id }"
            @click="emit('update:modelCategory', c.id)">{{ c.icon_url }} {{ c.name }}</span>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-4">
        <label class="fx-label">Max distance: {{ modelMaxDistance }} km</label>
        <input type="range" class="form-range" min="1" max="10" step="0.5"
               :value="modelMaxDistance"
               @input="emit('update:modelMaxDistance', Number($event.target.value))" />
      </div>
      <div class="col-12 col-md-4">
        <label class="fx-label">Max price: RM{{ modelMaxPrice }}/hr</label>
        <input type="range" class="form-range" min="20" max="100" step="5"
               :value="modelMaxPrice"
               @input="emit('update:modelMaxPrice', Number($event.target.value))" />
      </div>
      <div class="col-12 col-md-4">
        <label class="fx-label">Min rating: {{ modelMinRating }}★</label>
        <input type="range" class="form-range" min="0" max="5" step="0.5"
               :value="modelMinRating"
               @input="emit('update:modelMinRating', Number($event.target.value))" />
      </div>
    </div>
  </div>
</template>
