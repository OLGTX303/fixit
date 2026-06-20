<script setup>
defineProps({
  categories:       { type: Array,          default: () => [] },
  modelCategory:    { type: [Number, null], default: null },
  modelMaxDistance: { type: Number,         default: 5 },
  modelMaxPrice:    { type: Number,         default: 100 },
  modelMinRating:   { type: Number,         default: 0 },
})
const emit = defineEmits([
  'update:modelCategory','update:modelMaxDistance','update:modelMaxPrice','update:modelMinRating',
])
</script>

<template>
  <div class="fx-card fb-wrap">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
      <span class="material-symbols-outlined" style="font-size:18px;color:var(--fx-accent)">tune</span>
      <span style="font-size:14px;font-weight:700">Filters</span>
    </div>

    <!-- Category chips -->
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px">
      <span class="fx-chip sm" :class="{ active: modelCategory === null }"
            @click="emit('update:modelCategory', null)">All</span>
      <span v-for="c in categories" :key="c.id" class="fx-chip sm"
            :class="{ active: modelCategory === c.id }"
            @click="emit('update:modelCategory', c.id)">
        {{ c.icon_url }} {{ c.name }}
      </span>
    </div>

    <!-- Sliders -->
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <label class="fx-label-caps" style="margin-bottom:4px">Max distance: {{ modelMaxDistance }} km</label>
        <input type="range" class="form-range" min="1" max="10" step="0.5"
               :value="modelMaxDistance"
               @input="emit('update:modelMaxDistance', Number($event.target.value))" />
      </div>
      <div class="col-12 col-md-4">
        <label class="fx-label-caps" style="margin-bottom:4px">Max price: RM{{ modelMaxPrice }}/hr</label>
        <input type="range" class="form-range" min="20" max="100" step="5"
               :value="modelMaxPrice"
               @input="emit('update:modelMaxPrice', Number($event.target.value))" />
      </div>
      <div class="col-12 col-md-4">
        <label class="fx-label-caps" style="margin-bottom:4px">Min rating: {{ modelMinRating }}★</label>
        <input type="range" class="form-range" min="0" max="5" step="0.5"
               :value="modelMinRating"
               @input="emit('update:modelMinRating', Number($event.target.value))" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.fb-wrap { margin-bottom: 14px; }
</style>
