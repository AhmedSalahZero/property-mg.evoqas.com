<template>
  <Head title="Sign In — VERO Property Management" />

  <div class="zav-root">

    <!-- ═══════════════════════════════════════════════════════════
         LEFT PANEL — Branding & Module Showcase
    ════════════════════════════════════════════════════════════ -->
    <div class="zav-left hidden lg:flex">

      <!-- Background layers -->
      <div class="zav-grid-bg"></div>
      <div class="zav-glow-teal"></div>
      <div class="zav-glow-gold"></div>
      <div class="zav-diag"></div>

      <!-- TOP: Brand mark -->
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

      <!-- MIDDLE: Headline + Modules -->
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
          <div v-for="mod in modules" :key="mod.name"
            class="zav-mod"
            :class="{ 'zav-mod-gold': mod.gold }">
            <span class="zav-mod-dot" :class="{ 'zav-mod-dot-gold': mod.gold }"></span>
            {{ mod.name }}
          </div>
        </div>
      </div>

      <!-- BOTTOM: Stats + Footer -->
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

    <!-- ═══════════════════════════════════════════════════════════
         RIGHT PANEL — Login Form
    ════════════════════════════════════════════════════════════ -->
    <div class="zav-right">

      <!-- Logo — pinned to top -->
      <div class="zav-logo-area">
        <img
          src="/images/vero_pm_logo.png"
          alt="VERO Property Management"
          class="zav-logo-img"
        />
      </div>

      <!-- Form card — vertically centered in remaining space -->
      <div class="zav-form-wrap">
        <div class="zav-form-inner">

          <!-- Mobile logo -->
          <div class="flex justify-center mb-8 lg:hidden">
            <img src="/images/vero_pm_logo.png" alt="VERO Property Management" class="h-12 w-auto" />
          </div>

          <!-- Header -->
          <div class="zav-form-header">
            <h2 class="zav-welcome">Welcome back</h2>
            <div class="zav-welcome-line"></div>
            <p class="zav-welcome-sub">Sign in to your workspace to continue</p>
          </div>

          <!-- Form -->
          <form @submit.prevent="submit" class="zav-form">

            <!-- Email -->
            <div class="zav-field">
              <label class="zav-label">Email Address</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="your@email.com"
                autocomplete="username"
                class="zav-input"
              />
              <p v-if="form.errors.email" class="zav-error">{{ form.errors.email }}</p>
            </div>

            <!-- Password -->
            <div class="zav-field">
              <div class="zav-pw-label-row">
                <label class="zav-label">Password</label>
                <Link
                  v-if="canResetPassword"
                  :href="route('password.request')"
                  class="zav-forgot"
                >
                  Forgot password?
                </Link>
              </div>
              <div class="zav-input-wrap">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  class="zav-input pr-11"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="zav-eye-btn"
                  tabindex="-1"
                >
                  <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
              <p v-if="form.errors.password" class="zav-error">{{ form.errors.password }}</p>
            </div>

            <!-- Remember me -->
            <div class="zav-remember">
              <button
                type="button"
                @click="form.remember = !form.remember"
                class="zav-checkbox"
                :class="{ 'zav-checkbox-checked': form.remember }"
              >
                <svg v-if="form.remember" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </button>
              <span class="zav-remember-text">Remember me for 30 days</span>
            </div>

            <!-- Status message -->
            <div v-if="status" class="zav-status">
              {{ status }}
            </div>

            <!-- Submit -->
            <button
              type="submit"
              :disabled="form.processing"
              class="zav-btn-submit"
            >
              <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ form.processing ? 'Signing in...' : 'Sign In to VERO PM' }}
            </button>

          </form>

          <!-- Footer note -->
          <div class="zav-access-note">
            <div class="zav-access-divider">
              <span class="zav-access-line"></span>
              <span class="zav-access-text">invitation only</span>
              <span class="zav-access-line"></span>
            </div>
            <p class="zav-access-copy">
              Access is by invitation only.<br/>
              Contact your administrator if you need access.
            </p>
          </div>

        </div>
      </div>

    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({
  canResetPassword: Boolean,
  status: String,
})

const year = new Date().getFullYear()
const showPassword = ref(false)

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
  { name: 'Keep Or Sell'},
  { name: 'Loan Engine'},
]

const stats = [
  { value: '12',   label: 'Modules' },
  { value: '5',    label: 'User Roles' },
  { value: '100%', label: 'Secure & Private', gold: true },
]

const form = useForm({
  email:    '',
  password: '',
  remember: false,
})

function submit() {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<style scoped>
/* ── Root layout ───────────────────────────────────────────── */
.zav-root {
  min-height: 100vh;
  display: flex;
  background-color: #0C1829;
}

/* ── LEFT PANEL ────────────────────────────────────────────── */
.zav-left {
  width: 52%;
  background-color: #0C1829;
  padding: 1.25rem 3rem 1.5rem;
  flex-direction: column;
  justify-content: flex-start;
  gap: 1.25rem;
  position: relative;
  overflow: hidden;
}

/* Background grid */
.zav-grid-bg {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(20,144,168,0.055) 1px, transparent 1px),
    linear-gradient(90deg, rgba(20,144,168,0.055) 1px, transparent 1px);
  background-size: 38px 38px;
}

/* Radial glows */
.zav-glow-teal {
  position: absolute;
  top: -80px; right: -80px;
  width: 340px; height: 340px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(20,144,168,0.16) 0%, transparent 70%);
  pointer-events: none;
}
.zav-glow-gold {
  position: absolute;
  bottom: -100px; left: -60px;
  width: 300px; height: 300px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(186,117,23,0.13) 0%, transparent 70%);
  pointer-events: none;
}

/* Diagonal gold accent line */
.zav-diag {
  position: absolute;
  top: 0; right: 140px;
  width: 1.5px;
  height: 100%;
  background: linear-gradient(to bottom, transparent, rgba(25, 221, 7, 0.22), transparent);
  transform: rotate(16deg) translateX(40px);
  pointer-events: none;
}

/* Brand mark */
.zav-brand {
  position: relative; z-index: 2;
  display: flex; align-items: center; gap: 0.75rem;
}
.zav-logo-mark {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, #074b12, #328f46);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.zav-brand-name {
  font-size: 1.1rem;
  font-weight: 800;
  color: #F1F5F9;
  letter-spacing: 0.06em;
  line-height: 1.1;
  
}
.zav-brand-sub {
  font-size: 0.6rem;
  font-weight: 500;
  color: #8bc494;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  margin-top: 1px;
  
}

/* Middle content */
.zav-mid {
  position: relative; z-index: 2;
  margin-top: 0;
}
.zav-eyebrow {
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #0dafe0;
  margin-bottom: 0.8rem;
}
.zav-headline {
  font-size: 1.75rem;
  font-weight: 600;
  color: #F1F5F9;
  line-height: 1.5;
  margin-bottom: 0.8rem;
  letter-spacing: 0.05em;
  padding-top: 5px;
  padding-bottom: 5px;
}
.zav-headline-accent { color: #289236; }
.zav-tagline {
  font-size: 1rem;
  color: #ffffff;
  line-height: 1.7;
  margin-bottom: 1.5rem;
}
.zav-modules-label {
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #87883f;
  margin-bottom: 0.6rem;
}
.zav-modules-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}
.zav-mod {
  background: rgba(17,34,64,0.75);
  border: 1px solid #1b5834;
  border-radius: 7px;
  padding: 7px 10px;
  font-size: 0.8rem;
  color: #ffffff;
  font-weight: 0;
  transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease;
  display: flex; align-items: center; gap: 6px;
  cursor: default;
}
.zav-mod:hover {
  border-color: rgba(20,144,168,0.45);
  color: #48C4D8;
  background: rgba(20,144,168,0.05);
}
.zav-mod-gold:hover {
  border-color: rgba(186,117,23,0.45);
  color: #FAC775;
  background: rgba(186,117,23,0.05);
}
.zav-mod-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: #1490A8;
  opacity: 0.7;
  flex-shrink: 0;
}
.zav-mod-dot-gold { background: #BA7517; }

/* Bottom stats */
.zav-bottom {
  position: relative; z-index: 2;
}
.zav-stats {
  display: flex;
  gap: 2rem;
  padding-top: 1.1rem;
  border-top: 1px solid #1B3558;
  margin-bottom: 0.75rem;
}
.zav-stat-num {
  font-size: 1.5rem;
  font-weight: 800;
  color: #1490A8;
  line-height: 1.1;
}
.zav-stat-gold { color: #289236; }
.zav-stat-label {
  font-size: 0.65rem;
  color: #6B96B8;
  margin-top: 2px;
}
.zav-footer-left {
  font-size: 0.65rem;
  color: #1490A8;
}
.zav-footer-squad { color: #ffffff; }

/* ── RIGHT PANEL ───────────────────────────────────────────── */
.zav-right {
  width: 48%;
  background-color: #0E1E34;
  border-left: 1px solid #1B3558;
  display: flex;
  flex-direction: column;
  /* Logo at top, form centered in remaining space */
}

/* Logo — top of panel */
.zav-logo-area {
  padding: 5px ;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-bottom: -70px;
}
.zav-logo-img {
  height: 300px;
  width: auto;
  max-width: 400px;
  object-fit: contain;
  margin-top: 0px ;
  margin-bottom: 10px;
}

/* Form — sits directly below logo, no centering gap */
.zav-form-wrap {
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 0 2.5rem 1.5rem;
}
.zav-form-inner {
  width: 100%;
  max-width: 360px;
}

/* Form header */
.zav-form-header { margin-bottom: 0.75rem; }
.zav-welcome {
  font-size: 1.4rem;
  font-weight: 800;
  color: #F1F5F9;
  letter-spacing: -0.01em;
  line-height: 1.2;
}
.zav-welcome-line {
  height: 2px;
  width: 300px;
  background: linear-gradient(90deg, #1490A8, transparent);
  border-radius: 2px;
  margin: 5px 0 6px;
}
.zav-welcome-sub {
  font-size: 0.78rem;
  color: #6B96B8;
}

/* Form fields */
.zav-form { display: flex; flex-direction: column; gap: 0; }
.zav-field { margin-bottom: 1rem; }
.zav-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: #48C4D8;
  margin-bottom: 0.45rem;
}
.zav-input {
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
.zav-input::placeholder { color: #1B3558; }
.zav-input:focus {
  border-color: #1490A8;
  box-shadow: 0 0 0 3px rgba(20,144,168,0.12);
}
.zav-error {
  font-size: 0.7rem;
  color: #f87171;
  margin-top: 4px;
}

/* Password row */
.zav-pw-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.45rem;
}
.zav-forgot {
  font-size: 0.9rem;
  color: #ffffff;
  transition: color 0.15s ease;
  text-decoration: none;
}
.zav-forgot:hover { color: #1490A8; }
.zav-input-wrap { position: relative; }
.zav-eye-btn {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  cursor: pointer;
  color: #1B3558;
  transition: color 0.15s ease;
  padding: 0;
  display: flex; align-items: center;
}
.zav-eye-btn:hover { color: #6B96B8; }

/* Remember me */
.zav-remember {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1.2rem;
}
.zav-checkbox {
  width: 18px; height: 18px;
  border-radius: 4px;
  background: transparent;
  border: 1.5px solid #1B3558;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
  flex-shrink: 0;
  padding: 0;
}
.zav-checkbox-checked {
  background: #1490A8;
  border-color: #1490A8;
}
.zav-remember-text {
  font-size: 0.78rem;
  color: #6B96B8;
}

/* Status message */
.zav-status {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.78rem;
  background: rgba(16,185,129,0.08);
  border: 1px solid rgba(16,185,129,0.25);
  color: #6ee7b7;
  margin-bottom: 1rem;
}

/* Gold submit button */
.zav-btn-submit {
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
.zav-btn-submit:hover:not(:disabled) {
  background: linear-gradient(135deg, #07148b, #323c92);
  box-shadow: 0 6px 26px rgba(50, 127, 199, 0.38);
  transform: translateY(-1px);
}
.zav-btn-submit:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: 0 2px 10px rgba(186,117,23,0.22);
}
.zav-btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Footer / access note */
.zav-access-note { margin-top: 1.25rem; }
.zav-access-divider {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 0.75rem;
}
.zav-access-line {
  flex: 1;
  height: 1px;
  background: #1B3558;
}
.zav-access-text {
  font-size: 0.62rem;
  color: #1B3558;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  white-space: nowrap;
}
.zav-access-copy {
  text-align: center;
  font-size: 0.7rem;
  color: #1B3558;
  line-height: 1.7;
}
</style>