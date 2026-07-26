<template>
  <div class="zav-root">

    <!-- LEFT PANEL — Branding -->
    <div class="zav-left hidden lg:flex">
      <div class="zav-grid-bg"></div>
      <div class="zav-glow-teal"></div>
      <div class="zav-glow-gold"></div>
      <div class="zav-diag"></div>

      <div class="zav-brand">
        <div class="zav-logo-mark">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
            <polyline points="16 7 22 7 22 13"/>
          </svg>
        </div>
        <div>
          <div class="zav-brand-name">VERO</div>
          <div class="zav-brand-sub">Property Management</div>
        </div>
      </div>

      <div class="zav-mid">
        <p class="zav-eyebrow">Performance &amp; Financial Intelligence</p>
        <h1 class="zav-headline">
          It Is More Than Numbers<br/>
          <span class="zav-headline-accent"> It Is The Story They Tell</span>
        </h1>
        <p class="zav-tagline">
          Built for Property Management Companies<br/>
          Plan, Monitor & Grow.
        </p>

        <p class="zav-modules-label">12 Active Modules</p>
        <div class="zav-modules-grid">
          <div
            v-for="mod in modules"
            :key="mod.name"
            class="zav-mod"
            :class="{ 'zav-mod-gold': mod.gold }"
          >
            <span class="zav-mod-dot" :class="{ 'zav-mod-dot-gold': mod.gold }"></span>
            {{ mod.name }}
          </div>
        </div>
      </div>

      <div class="zav-bottom">
        <div class="zav-stats">
          <div v-for="stat in stats" :key="stat.label">
            <div class="zav-stat-num" :class="{ 'zav-stat-gold': stat.gold }">{{ stat.value }}</div>
            <div class="zav-stat-label">{{ stat.label }}</div>
          </div>
        </div>
        <p class="zav-footer-left">
          © {{ year }} VERO Property Management · Built by
          <span class="zav-footer-squad">SQUAD Business Consulting</span>
          · Cairo, Egypt
        </p>
      </div>
    </div>

    <!-- RIGHT PANEL — Form slot -->
    <div class="zav-right">
      <div class="zav-logo-area">
        <img
          src="/images/vero_pm_logo.png"
          alt="VERO Property Management"
          class="zav-logo-img"
        />
      </div>

      <div class="zav-form-wrap">
        <div class="zav-form-inner">
          <div class="flex justify-center mb-8 lg:hidden">
            <img src="/images/vero_pm_logo.png" alt="VERO Property Management" class="h-12 w-auto" />
          </div>

          <div class="zav-form-header">
            <h2 class="zav-welcome">{{ title }}</h2>
            <div class="zav-welcome-line"></div>
            <p v-if="subtitle" class="zav-welcome-sub">{{ subtitle }}</p>
          </div>

          <slot />
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
})

const year = new Date().getFullYear()

const modules = [
  { name: 'Dashboard' },
  { name: 'Property Mg' },
  { name: 'Contract Analysis' },
  { name: 'Revenues' },
  { name: 'Expenses' },
  { name: 'Profitability' },
  { name: 'Cash Forecast' },
  { name: 'Fin. Studies' },
  { name: 'Projects & Tasks' },
  { name: 'Statistica' },
  { name: 'Keep Or Sell' },
  { name: 'Loan Engine' },
]

const stats = [
  { value: '12', label: 'Modules' },
  { value: '5', label: 'User Roles' },
  { value: '100%', label: 'Secure & Private', gold: true },
]
</script>

<style>
/* Namespaced under .zav-root so slot content inherits brand styles */
.zav-root {
  min-height: 100vh;
  display: flex;
  background-color: #0C1829;
}

.zav-root .zav-left {
  width: 52%;
  background-color: #0C1829;
  padding: 1.25rem 3rem 1.5rem;
  flex-direction: column;
  justify-content: flex-start;
  gap: 1.25rem;
  position: relative;
  overflow: hidden;
}

.zav-root .zav-grid-bg {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(20,144,168,0.055) 1px, transparent 1px),
    linear-gradient(90deg, rgba(20,144,168,0.055) 1px, transparent 1px);
  background-size: 38px 38px;
}

.zav-root .zav-glow-teal {
  position: absolute;
  top: -80px; right: -80px;
  width: 340px; height: 340px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(20,144,168,0.16) 0%, transparent 70%);
  pointer-events: none;
}
.zav-root .zav-glow-gold {
  position: absolute;
  bottom: -100px; left: -60px;
  width: 300px; height: 300px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(186,117,23,0.13) 0%, transparent 70%);
  pointer-events: none;
}

.zav-root .zav-diag {
  position: absolute;
  top: 0; right: 140px;
  width: 1.5px;
  height: 100%;
  background: linear-gradient(to bottom, transparent, rgba(25, 221, 7, 0.22), transparent);
  transform: rotate(16deg) translateX(40px);
  pointer-events: none;
}

.zav-root .zav-brand {
  position: relative; z-index: 2;
  display: flex; align-items: center; gap: 0.75rem;
}
.zav-root .zav-logo-mark {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, #074b12, #328f46);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.zav-root .zav-brand-name {
  font-size: 1.1rem;
  font-weight: 800;
  color: #F1F5F9;
  letter-spacing: 0.06em;
  line-height: 1.1;
}
.zav-root .zav-brand-sub {
  font-size: 0.6rem;
  font-weight: 500;
  color: #8bc494;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  margin-top: 1px;
}

.zav-root .zav-mid {
  position: relative; z-index: 2;
}
.zav-root .zav-eyebrow {
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #0dafe0;
  margin-bottom: 0.8rem;
}
.zav-root .zav-headline {
  font-size: 1.75rem;
  font-weight: 600;
  color: #F1F5F9;
  line-height: 1.5;
  margin-bottom: 0.8rem;
  letter-spacing: 0.05em;
  padding-top: 5px;
  padding-bottom: 5px;
}
.zav-root .zav-headline-accent { color: #289236; }
.zav-root .zav-tagline {
  font-size: 1rem;
  color: #ffffff;
  line-height: 1.7;
  margin-bottom: 1.5rem;
}
.zav-root .zav-modules-label {
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #87883f;
  margin-bottom: 0.6rem;
}
.zav-root .zav-modules-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}
.zav-root .zav-mod {
  background: rgba(17,34,64,0.75);
  border: 1px solid #1b5834;
  border-radius: 7px;
  padding: 7px 10px;
  font-size: 0.8rem;
  color: #ffffff;
  transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease;
  display: flex; align-items: center; gap: 6px;
  cursor: default;
}
.zav-root .zav-mod:hover {
  border-color: rgba(20,144,168,0.45);
  color: #48C4D8;
  background: rgba(20,144,168,0.05);
}
.zav-root .zav-mod-gold:hover {
  border-color: rgba(186,117,23,0.45);
  color: #FAC775;
  background: rgba(186,117,23,0.05);
}
.zav-root .zav-mod-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: #1490A8;
  opacity: 0.7;
  flex-shrink: 0;
}
.zav-root .zav-mod-dot-gold { background: #BA7517; }

.zav-root .zav-bottom {
  position: relative; z-index: 2;
}
.zav-root .zav-stats {
  display: flex;
  gap: 2rem;
  padding-top: 1.1rem;
  border-top: 1px solid #1B3558;
  margin-bottom: 0.75rem;
}
.zav-root .zav-stat-num {
  font-size: 1.5rem;
  font-weight: 800;
  color: #1490A8;
  line-height: 1.1;
}
.zav-root .zav-stat-gold { color: #289236; }
.zav-root .zav-stat-label {
  font-size: 0.65rem;
  color: #6B96B8;
  margin-top: 2px;
}
.zav-root .zav-footer-left {
  font-size: 0.65rem;
  color: #1490A8;
}
.zav-root .zav-footer-squad { color: #ffffff; }

.zav-root .zav-right {
  width: 48%;
  background-color: #0E1E34;
  border-left: 1px solid #1B3558;
  display: flex;
  flex-direction: column;
}

.zav-root .zav-logo-area {
  padding: 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-bottom: -70px;
}
.zav-root .zav-logo-img {
  height: 300px;
  width: auto;
  max-width: 400px;
  object-fit: contain;
  margin-bottom: 10px;
}

.zav-root .zav-form-wrap {
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 0 2.5rem 1.5rem;
}
.zav-root .zav-form-inner {
  width: 100%;
  max-width: 360px;
}

.zav-root .zav-form-header { margin-bottom: 0.75rem; }
.zav-root .zav-welcome {
  font-size: 1.4rem;
  font-weight: 800;
  color: #F1F5F9;
  letter-spacing: -0.01em;
  line-height: 1.2;
}
.zav-root .zav-welcome-line {
  height: 2px;
  width: 300px;
  background: linear-gradient(90deg, #1490A8, transparent);
  border-radius: 2px;
  margin: 5px 0 6px;
}
.zav-root .zav-welcome-sub {
  font-size: 0.78rem;
  color: #6B96B8;
}

.zav-root .zav-form { display: flex; flex-direction: column; gap: 0; }
.zav-root .zav-field { margin-bottom: 1rem; }
.zav-root .zav-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: #48C4D8;
  margin-bottom: 0.45rem;
}
.zav-root .zav-input {
  width: 100%;
  background-color: #112240;
  border: 1px solid #1B3558;
  border-radius: 8px;
  padding: 0.65rem 0.9rem;
  font-size: 0.85rem;
  color: #F1F5F9;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
  outline: none;
}
.zav-root .zav-input::placeholder { color: #1B3558; }
.zav-root .zav-input:focus {
  border-color: #1490A8;
  box-shadow: 0 0 0 3px rgba(20,144,168,0.12);
}
.zav-root .zav-error {
  font-size: 0.7rem;
  color: #f87171;
  margin-top: 4px;
}

.zav-root .zav-forgot {
  font-size: 0.9rem;
  color: #ffffff;
  transition: color 0.15s ease;
  text-decoration: none;
}
.zav-root .zav-forgot:hover { color: #1490A8; }

.zav-root .zav-status {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.78rem;
  background: rgba(16,185,129,0.08);
  border: 1px solid rgba(16,185,129,0.25);
  color: #6ee7b7;
  margin-bottom: 1rem;
}

.zav-root .zav-btn-submit {
  width: 100%;
  background: linear-gradient(135deg, #095309, #063d06);
  border: 1px solid rgba(83, 214, 105, 0.4);
  border-radius: 8px;
  padding: 0.75rem;
  font-size: 0.88rem;
  font-weight: 700;
  color: #FAEEDA;
  letter-spacing: 0.03em;
  cursor: pointer;
  box-shadow: 0 4px 18px rgba(1, 17, 3, 0.4);
  transition: all 0.2s ease;
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.zav-root .zav-btn-submit:hover:not(:disabled) {
  background: linear-gradient(135deg, #07148b, #323c92);
  box-shadow: 0 6px 26px rgba(50, 127, 199, 0.38);
  transform: translateY(-1px);
}
.zav-root .zav-btn-submit:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: 0 2px 10px rgba(186,117,23,0.22);
}
.zav-root .zav-btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.zav-root .zav-back-wrap {
  margin-top: 1.25rem;
  text-align: center;
}

@media (max-width: 1023px) {
  .zav-root .zav-right {
    width: 100%;
    border-left: none;
  }
  .zav-root .zav-logo-area {
    margin-bottom: -40px;
  }
  .zav-root .zav-logo-img {
    height: 180px;
  }
}
</style>
