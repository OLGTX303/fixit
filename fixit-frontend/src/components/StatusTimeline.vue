<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'
import { STATUS_FLOW } from '../stores/bookings'

// Renders the job status timeline (requested → … → reviewed) from a current status.
const props = defineProps({
  status: { type: String, required: true },
  timestamps: { type: Object, default: () => ({}) },
})

const labels = {
  requested: 'Requested', accepted: 'Accepted', in_progress: 'In Progress',
  completed: 'Completed', reviewed: 'Reviewed',
}
const currentIndex = computed(() => STATUS_FLOW.indexOf(props.status))
const steps = computed(() => STATUS_FLOW.map((key, i) => ({
  key,
  label: labels[key],
  sub: props.timestamps[key] || (i <= currentIndex.value ? 'Done' : 'Pending'),
  done: i < currentIndex.value,
  active: i === currentIndex.value,
})))
</script>

<template>
  <div>
    <div v-for="(step, i) in steps" :key="step.key" class="d-flex" style="gap:14px">
      <div class="d-flex flex-column align-items-center" style="width:32px">
        <div class="d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;flex-shrink:0"
             :style="{
               background: step.done ? 'var(--fx-success)' : step.active ? 'var(--fx-accent)' : 'var(--fx-border-soft)',
               boxShadow: step.active ? '0 0 0 4px var(--fx-accent-soft)' : 'none',
             }">
          <AppIcon v-if="step.done" name="check" :size="15" style="color:#fff" />
          <div v-else style="width:10px;height:10px;border-radius:50%"
               :style="{ background: step.active ? '#fff' : 'var(--fx-border)' }" />
        </div>
        <div v-if="i < steps.length - 1" style="width:2px;flex:1;min-height:24px;margin-top:2px"
             :style="{ background: step.done ? 'var(--fx-success)' : 'var(--fx-border)' }" />
      </div>
      <div :style="{ paddingBottom: i < steps.length - 1 ? '20px' : '0', paddingTop: '4px' }">
        <div :style="{
          fontSize: '14px',
          fontWeight: step.active ? 700 : step.done ? 600 : 500,
          color: step.done || step.active ? 'var(--fx-text)' : 'var(--fx-muted-soft)',
        }">{{ step.label }}</div>
        <div style="font-size:12px;color:var(--fx-muted);margin-top:2px">{{ step.sub }}</div>
      </div>
    </div>
  </div>
</template>
