<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LEGAL_DOCUMENTS } from '../../content/legal.js'
import AppIcon from '../../components/AppIcon.vue'
import LegalFooter from '../../components/LegalFooter.vue'

const route = useRoute()
const router = useRouter()

const doc = computed(() => LEGAL_DOCUMENTS[route.meta.legalKey] || null)

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push({ name: 'login' })
}
</script>

<template>
  <div v-if="doc" class="fx-legal">
    <header class="fx-legal-header">
      <button type="button" class="fx-legal-back" @click="goBack">
        <AppIcon name="back" :size="18" />
        <span>Back</span>
      </button>
      <div class="fx-legal-brand">
        <img src="/fixit-logo.svg" alt="FixIt" class="fx-legal-logo-img" />
      </div>
    </header>

    <article class="fx-legal-body">
      <div class="fx-legal-hero fx-card">
        <p class="fx-legal-kicker">Legal</p>
        <h1>{{ doc.title }}</h1>
        <p class="fx-legal-sub">{{ doc.subtitle }}</p>
        <div class="fx-legal-meta">
          <span>Last updated: {{ doc.lastUpdated }}</span>
          <span>Version {{ doc.version }}</span>
        </div>
      </div>

      <nav class="fx-legal-toc fx-card" aria-label="Table of contents">
        <div class="fx-legal-toc-title">Contents</div>
        <ol>
          <li v-for="s in doc.sections" :key="s.id">
            <a :href="`#${s.id}`">{{ s.title }}</a>
          </li>
        </ol>
      </nav>

      <section
        v-for="s in doc.sections"
        :key="s.id"
        :id="s.id"
        class="fx-legal-section fx-card"
      >
        <h2>{{ s.title }}</h2>
        <p v-for="(p, i) in s.paragraphs" :key="i">{{ p }}</p>
      </section>

      <LegalFooter class="fx-legal-footer" />
    </article>
  </div>

  <div v-else class="fx-page text-center py-5">
    <p>Document not found.</p>
    <router-link :to="{ name: 'login' }" class="text-accent">Return to login</router-link>
  </div>
</template>

<style scoped>
.fx-legal {
  min-height: 100%;
  background: var(--fx-bg);
}
.fx-legal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--fx-border);
  background: var(--fx-surface);
  position: sticky;
  top: 0;
  z-index: 10;
}
.fx-legal-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: none;
  color: var(--fx-muted);
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  padding: 6px 0;
}
.fx-legal-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 800;
  font-size: 18px;
}
.fx-legal-logo-img {
  width: 48px;
  height: 48px;
  display: block;
}
.fx-legal-body {
  max-width: 760px;
  margin: 0 auto;
  padding: 20px 20px 40px;
}
.fx-legal-hero {
  margin-bottom: 16px;
  padding: 24px;
}
.fx-legal-kicker {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: var(--fx-accent);
}
.fx-legal-hero h1 {
  margin: 0 0 8px;
  font-size: 28px;
  letter-spacing: -0.5px;
}
.fx-legal-sub {
  margin: 0 0 14px;
  color: var(--fx-muted);
  font-size: 15px;
  line-height: 1.5;
}
.fx-legal-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 12px;
  color: var(--fx-muted-soft);
}
.fx-legal-toc {
  margin-bottom: 16px;
  padding: 18px 22px;
}
.fx-legal-toc-title {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--fx-muted);
  margin-bottom: 10px;
}
.fx-legal-toc ol {
  margin: 0;
  padding-left: 0;
  list-style: none;
  font-size: 14px;
  line-height: 1.7;
}
.fx-legal-toc a {
  color: var(--fx-text);
  text-decoration: none;
}
.fx-legal-toc a:hover {
  color: var(--fx-accent);
}
.fx-legal-section {
  margin-bottom: 12px;
  padding: 22px 24px;
  scroll-margin-top: 72px;
}
.fx-legal-section h2 {
  margin: 0 0 12px;
  font-size: 17px;
}
.fx-legal-section p {
  margin: 0 0 10px;
  font-size: 14px;
  line-height: 1.65;
  color: var(--fx-text);
}
.fx-legal-section p:last-child {
  margin-bottom: 0;
}
.fx-legal-footer {
  margin-top: 24px;
}
@media (min-width: 992px) {
  .fx-legal-body { padding: 32px 32px 48px; }
  .fx-legal-hero h1 { font-size: 32px; }
}
</style>