<script setup>
import { LEGAL_COMPANY, LEGAL_CONTACT_EMAIL } from '../content/legal.js'
</script>

<template>
  <footer class="fx-legal-foot">
    <nav class="fx-legal-links" aria-label="Legal">
      <router-link :to="{ name: 'legal-terms' }">Terms of Service</router-link>
      <span class="fx-legal-sep">·</span>
      <router-link :to="{ name: 'legal-privacy' }">Privacy Policy</router-link>
    </nav>
    <p class="fx-legal-copy">
      © {{ new Date().getFullYear() }} {{ LEGAL_COMPANY }}. All rights reserved.
    </p>
    <p class="fx-legal-contact">
      <a :href="`mailto:${LEGAL_CONTACT_EMAIL}`">{{ LEGAL_CONTACT_EMAIL }}</a>
    </p>
  </footer>
</template>

<style scoped>
.fx-legal-foot {
  text-align: center;
  padding: 16px 0 8px;
}
.fx-legal-links {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
}
.fx-legal-links a {
  color: var(--fx-muted);
  text-decoration: none;
}
.fx-legal-links a:hover,
.fx-legal-links a.router-link-active {
  color: var(--fx-accent);
}
.fx-legal-sep {
  color: var(--fx-border);
}
.fx-legal-copy,
.fx-legal-contact {
  margin: 8px 0 0;
  font-size: 11px;
  color: var(--fx-muted-soft);
}
.fx-legal-contact a {
  color: var(--fx-muted);
  text-decoration: none;
}
</style>