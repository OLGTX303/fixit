<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const prefs = ref({
  locationSharing:    true,
  profileVisible:     true,
  reviewsVisible:     true,
  marketingEmails:    false,
  pushNotifications:  true,
  dataAnalytics:      true,
})
</script>

<template>
  <div class="psv-root">
    <div class="psv-header">
      <button class="psv-back" @click="router.back()">
        <span class="material-symbols-outlined">arrow_back_ios</span>
      </button>
      <span class="psv-title">Privacy Settings</span>
      <div style="width:36px"></div>
    </div>

    <div class="psv-section-label">Visibility</div>
    <div class="psv-group">
      <div class="psv-row">
        <div class="psv-row-left">
          <div class="psv-row-icon" style="background:rgba(255,102,53,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#FF6635;font-variation-settings:'FILL' 1">location_on</span>
          </div>
          <div>
            <div class="psv-row-lbl">Location Sharing</div>
            <div class="psv-row-sub">Allow providers to see your area</div>
          </div>
        </div>
        <label class="psv-toggle">
          <input type="checkbox" v-model="prefs.locationSharing" />
          <span class="psv-slider"></span>
        </label>
      </div>
      <div class="psv-sep"></div>
      <div class="psv-row">
        <div class="psv-row-left">
          <div class="psv-row-icon" style="background:rgba(37,99,235,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#2563eb;font-variation-settings:'FILL' 1">person</span>
          </div>
          <div>
            <div class="psv-row-lbl">Public Profile</div>
            <div class="psv-row-sub">Show your profile to providers</div>
          </div>
        </div>
        <label class="psv-toggle">
          <input type="checkbox" v-model="prefs.profileVisible" />
          <span class="psv-slider"></span>
        </label>
      </div>
      <div class="psv-sep"></div>
      <div class="psv-row">
        <div class="psv-row-left">
          <div class="psv-row-icon" style="background:rgba(234,179,8,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#ca8a04;font-variation-settings:'FILL' 1">star</span>
          </div>
          <div>
            <div class="psv-row-lbl">Public Reviews</div>
            <div class="psv-row-sub">Show your reviews publicly</div>
          </div>
        </div>
        <label class="psv-toggle">
          <input type="checkbox" v-model="prefs.reviewsVisible" />
          <span class="psv-slider"></span>
        </label>
      </div>
    </div>

    <div class="psv-section-label">Communications</div>
    <div class="psv-group">
      <div class="psv-row">
        <div class="psv-row-left">
          <div class="psv-row-icon" style="background:rgba(14,165,233,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#0ea5e9;font-variation-settings:'FILL' 1">mail</span>
          </div>
          <div>
            <div class="psv-row-lbl">Marketing Emails</div>
            <div class="psv-row-sub">Promotions &amp; offers</div>
          </div>
        </div>
        <label class="psv-toggle">
          <input type="checkbox" v-model="prefs.marketingEmails" />
          <span class="psv-slider"></span>
        </label>
      </div>
      <div class="psv-sep"></div>
      <div class="psv-row">
        <div class="psv-row-left">
          <div class="psv-row-icon" style="background:rgba(124,58,237,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#7c3aed;font-variation-settings:'FILL' 1">notifications</span>
          </div>
          <div>
            <div class="psv-row-lbl">Push Notifications</div>
            <div class="psv-row-sub">Booking &amp; message alerts</div>
          </div>
        </div>
        <label class="psv-toggle">
          <input type="checkbox" v-model="prefs.pushNotifications" />
          <span class="psv-slider"></span>
        </label>
      </div>
    </div>

    <div class="psv-section-label">Data</div>
    <div class="psv-group">
      <div class="psv-row">
        <div class="psv-row-left">
          <div class="psv-row-icon" style="background:rgba(16,185,129,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#10b981;font-variation-settings:'FILL' 1">analytics</span>
          </div>
          <div>
            <div class="psv-row-lbl">Usage Analytics</div>
            <div class="psv-row-sub">Help improve the app</div>
          </div>
        </div>
        <label class="psv-toggle">
          <input type="checkbox" v-model="prefs.dataAnalytics" />
          <span class="psv-slider"></span>
        </label>
      </div>
    </div>

    <div class="psv-section-label">Legal</div>
    <div class="psv-group">
      <button class="psv-link-row" @click="router.push({ name: 'legal-privacy' })">
        <div class="psv-row-left">
          <div class="psv-row-icon" style="background:rgba(100,116,139,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#64748b;font-variation-settings:'FILL' 1">policy</span>
          </div>
          <span class="psv-row-lbl">Privacy Policy</span>
        </div>
        <span class="material-symbols-outlined psv-chevron">chevron_right</span>
      </button>
    </div>

    <p class="psv-note">Preferences are saved locally. Account-level data deletion can be requested via legal@fixit.com.</p>
  </div>
</template>

<style scoped>
.psv-root { background: var(--fx-bg); min-height: 100vh; padding-bottom: 40px; }

.psv-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 56px 16px 16px;
  position: sticky; top: 0; z-index: 10;
  background: rgba(255,255,255,0.60);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-bottom: 0.5px solid rgba(255,255,255,0.55);
}
.psv-back  { background: none; border: none; cursor: pointer; display: flex; align-items: center; color: var(--fx-text); padding: 4px; }
.psv-back .material-symbols-outlined { font-size: 20px; }
.psv-title { font-size: 17px; font-weight: 700; color: var(--fx-text); }

.psv-section-label {
  font-size: 12px; font-weight: 600; color: var(--fx-muted);
  padding: 18px 28px 6px; letter-spacing: 0.04em; text-transform: uppercase;
}
.psv-group {
  background:
    radial-gradient(ellipse 44% 30% at 16% 7%, rgba(255,255,255,0.28) 0%, transparent 62%),
    linear-gradient(to bottom, rgba(255,255,255,0.22) 0%, transparent 26%),
    rgba(255,255,255,0.06);
  border: 0.5px solid rgba(255,255,255,0.55);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 4px 20px rgba(0,0,0,0.05);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-radius: 20px; overflow: hidden;
  margin: 0 16px 4px;
}
.psv-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px;
}
.psv-link-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px; width: 100%; border: none; background: transparent; cursor: pointer;
}
.psv-row-left { display: flex; align-items: center; gap: 12px; flex: 1; }
.psv-row-icon {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.psv-row-lbl { font-size: 15px; color: var(--fx-text); font-weight: 500; text-align: left; }
.psv-row-sub { font-size: 12px; color: var(--fx-muted); margin-top: 1px; text-align: left; }
.psv-chevron { font-size: 18px; color: var(--fx-muted); }
.psv-sep     { height: 0.5px; background: rgba(255,255,255,0.45); margin: 0 16px; }

/* iOS-style toggle */
.psv-toggle { position: relative; width: 44px; height: 26px; flex-shrink: 0; cursor: pointer; }
.psv-toggle input { opacity: 0; width: 0; height: 0; }
.psv-slider {
  position: absolute; inset: 0; border-radius: 13px;
  background: rgba(0,0,0,0.15); transition: background 0.2s;
}
.psv-slider::before {
  content: ''; position: absolute;
  width: 22px; height: 22px; border-radius: 50%;
  left: 2px; top: 2px;
  background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.25);
  transition: transform 0.2s;
}
.psv-toggle input:checked + .psv-slider { background: #FF6635; }
.psv-toggle input:checked + .psv-slider::before { transform: translateX(18px); }

.psv-note {
  margin: 20px 24px 0; font-size: 12px; color: var(--fx-muted);
  line-height: 1.5; text-align: center;
}
</style>
