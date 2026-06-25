<script setup>
import { computed } from 'vue'
import RatingStars from './RatingStars.vue'
import { categoryIcon, categoryTint } from '../services/categoryIcons'
import { useAuthStore } from '../stores/auth'
import { useFavoritesStore } from '../stores/favorites'

const props = defineProps({
  provider: { type: Object, required: true },
  showFavorite: { type: Boolean, default: false },
})
defineEmits(['select'])

const auth = useAuthStore()
const favorites = useFavoritesStore()

const cover    = computed(() => props.provider.cover_url || null)
const avatar   = computed(() => props.provider.avatar_url || null)
const category = computed(() => props.provider.category_names?.[0] || 'Service')
const catIcon  = computed(() => categoryIcon(category.value))
const catTint  = computed(() => categoryTint(category.value))
const initials = computed(() =>
  (props.provider.name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase())
const isFav = computed(() => favorites.has(props.provider.id))

async function toggleFavorite(e) {
  e.stopPropagation()
  if (auth.role !== 'customer') return
  try {
    await favorites.toggle(props.provider.id)
  } catch { /* non-fatal */ }
}
</script>

<template>
  <div class="pgc" role="button" @click="$emit('select', provider)">
    <div class="pgc-img">
      <img v-if="cover" :src="cover" :alt="provider.name" class="pgc-cover" loading="lazy" />
      <div v-else class="pgc-iconbg" :style="{ background: catTint }">
        <img :src="catIcon" :alt="category" class="pgc-icon" />
      </div>

      <button
        v-if="showFavorite && auth.role === 'customer'"
        class="pgc-fav"
        :class="{ active: isFav }"
        :aria-label="isFav ? 'Remove from favourites' : 'Add to favourites'"
        @click="toggleFavorite"
      >
        <span class="material-symbols-outlined" style="font-size:18px">favorite</span>
      </button>

      <span class="pgc-cat">{{ category }}</span>

      <div class="pgc-avatar">
        <img v-if="avatar" :src="avatar" :alt="provider.name" loading="lazy" />
        <span v-else>{{ initials }}</span>
      </div>
    </div>

    <div class="pgc-body">
      <div class="pgc-name">{{ provider.name }}</div>
      <div class="pgc-meta">
        <RatingStars :rating="provider.avg_rating" :size="11" />
        <span class="pgc-rating">{{ Number(provider.avg_rating).toFixed(1) }}</span>
        <span class="pgc-reviews">({{ provider.review_count }})</span>
      </div>
      <div class="pgc-rate">RM{{ provider.base_rate }}<span class="pgc-unit">/hr</span></div>
    </div>
  </div>
</template>

<style scoped>
.pgc {
  display: flex; flex-direction: column;
  background: #fff; border-radius: 16px; overflow: hidden;
  border: 1px solid var(--fx-border-soft);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  cursor: pointer; transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.pgc:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.10); }
.pgc:active { transform: scale(0.98); }

.pgc-img { position: relative; width: 100%; aspect-ratio: 1 / 1; overflow: hidden; }
.pgc-cover { width: 100%; height: 100%; object-fit: cover; }

.pgc-iconbg {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
}
.pgc-icon {
  width: 46%; height: 46%; object-fit: contain;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.14));
}

.pgc-fav {
  position: absolute; left: 6px; top: 6px; z-index: 2;
  width: 32px; height: 32px; border-radius: 50%;
  border: none; background: rgba(255,255,255,0.92);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--fx-muted);
  box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.pgc-fav.active { color: #ef4444; }
.pgc-fav.active .material-symbols-outlined { font-variation-settings: 'FILL' 1; }

.pgc-cat {
  position: absolute; right: 6px; top: 6px;
  background: rgba(0,0,0,0.55); color: #fff;
  font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 8px;
  backdrop-filter: blur(4px);
}

.pgc-avatar {
  position: absolute; left: 8px; bottom: 8px;
  width: 36px; height: 36px; border-radius: 50%; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #FF7D54, #FF6635);
  color: #fff; font-size: 13px; font-weight: 800;
  border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}
.pgc-avatar img { width: 100%; height: 100%; object-fit: cover; }

.pgc-body { padding: 8px 10px 10px; display: flex; flex-direction: column; gap: 3px; }
.pgc-name { font-size: 13px; font-weight: 700; color: var(--fx-text);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pgc-meta { display: flex; align-items: center; gap: 3px; }
.pgc-rating { font-size: 11px; font-weight: 700; color: var(--fx-text); }
.pgc-reviews { font-size: 10px; color: var(--fx-muted); }
.pgc-rate { font-size: 15px; font-weight: 800; color: var(--fx-accent); }
.pgc-unit { font-size: 11px; font-weight: 600; }
</style>