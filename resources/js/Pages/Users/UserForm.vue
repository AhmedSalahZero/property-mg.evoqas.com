<template>
  <div class="space-y-6">

    <!-- ══ SECTION 1: Basic Info ══════════════════════════════════════════════ -->
    <div class="fv-section">
      <h2 class="fv-section-title">
        <span class="fv-section-icon">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
        </span>
        Basic Information
      </h2>

      <div class="fv-grid-2">
        <!-- Full Name -->
        <div class="fv-field">
          <label class="fv-label">Full Name <span class="text-red-400">*</span></label>
          <input v-model="form.name" type="text" class="fv-input" :class="{ 'fv-input-error': errors.name }" placeholder="e.g. Ahmed Hassan" />
          <p v-if="errors.name" class="fv-error">{{ errors.name }}</p>
        </div>

        <!-- Email -->
        <div class="fv-field">
          <label class="fv-label">Email Address <span class="text-red-400">*</span></label>
          <input v-model="form.email" type="email" class="fv-input" :class="{ 'fv-input-error': errors.email }" placeholder="ahmed@company.com" />
          <p v-if="errors.email" class="fv-error">{{ errors.email }}</p>
        </div>

        <!-- Job Title -->
        <div class="fv-field">
          <label class="fv-label">Job Title</label>
          <input v-model="form.job_title" type="text" class="fv-input" placeholder="e.g. Financial Analyst" />
        </div>

        <!-- Phone -->
        <div class="fv-field">
          <label class="fv-label">Phone</label>
          <input v-model="form.phone" type="text" class="fv-input" placeholder="+20 10 xxxx xxxx" />
        </div>
      </div>
    </div>

    <!-- ══ SECTION 2: Company & Role ══════════════════════════════════════════ -->
    <div class="fv-section">
      <h2 class="fv-section-title">
        <span class="fv-section-icon">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
        </span>
        Company &amp; Role
      </h2>

      <div class="fv-grid-2">
        <!-- Company -->
        <div class="fv-field">
          <label class="fv-label">Company <span class="text-red-400">*</span></label>
          <select
            v-model="form.company_id"
            class="fv-input"
            :class="{ 'fv-input-error': errors.company_id }"
            :disabled="authRole !== 'super_admin'"
          >
            <option :value="null">— Select company —</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <p v-if="errors.company_id" class="fv-error">{{ errors.company_id }}</p>
        </div>

        <!-- Role -->
        <div class="fv-field">
          <label class="fv-label">Role <span class="text-red-400">*</span></label>
          <select v-model="form.role" class="fv-input" :class="{ 'fv-input-error': errors.role }">
            <option :value="null">— Select role —</option>
            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
          <p v-if="errors.role" class="fv-error">{{ errors.role }}</p>
        </div>
      </div>

      <!-- Role descriptions -->
      <div v-if="form.role" class="mt-3 p-3 rounded-lg bg-blue-500/5 border border-blue-500/15">
        <p class="text-xs text-blue-300">
          <strong>{{ selectedRole?.label }}:</strong> {{ selectedRole?.description }}
        </p>
      </div>

      <!-- Admin limit warning -->
      <div v-if="form.role === 'company_admin' && adminCount >= 2" class="mt-3 p-3 rounded-lg bg-amber-500/5 border border-amber-500/20">
        <p class="text-xs text-amber-300 flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          This company has {{ adminCount }} of 3 allowed Company Admins.
        </p>
      </div>

      <div
        v-if="authRole === 'super_admin' && form.role === 'company_admin'"
        class="mt-4 p-4 rounded-lg border border-indigo-500/20 bg-indigo-500/5"
      >
        <div class="flex items-start gap-3">
          <div class="w-7 h-7 rounded-md bg-indigo-500/15 border border-indigo-500/25 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-indigo-300 uppercase tracking-wide mb-1">User Creation Limit</p>
            <p class="text-xs text-slate-400 mb-3">
              Set how many users this Company Admin is allowed to create. Leave blank for no limit.
            </p>
            <div class="flex items-center gap-3">
              <div class="fv-field w-36">
                <input
                  v-model.number="form.max_users"
                  type="number"
                  min="1"
                  max="9999"
                  class="fv-input text-center"
                  :class="{ 'fv-input-error': errors.max_users }"
                  placeholder="No limit"
                />
                <p v-if="errors.max_users" class="fv-error">{{ errors.max_users }}</p>
              </div>
              <span class="text-xs text-slate-500">users maximum</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="authRole === 'company_admin' && userLimit !== null && userLimit !== undefined"
      class="fv-section"
    >
      <h2 class="fv-section-title">
        <span class="fv-section-icon">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </span>
        User Quota
      </h2>

      <div class="space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-400">Users in your company</span>
          <span :class="isAtLimit ? 'text-red-400 font-semibold' : 'text-slate-300'">
            {{ userCount }} / {{ userLimit }}
          </span>
        </div>
        <div class="h-2 rounded-full bg-slate-800 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="barColor"
            :style="{ width: barWidth }"
          />
        </div>
        <p class="text-xs" :class="isAtLimit ? 'text-red-400' : 'text-slate-500'">
          <template v-if="isAtLimit">
            You have reached your limit of {{ userLimit }} users. Contact your Super Admin to increase it.
          </template>
          <template v-else>
            {{ userLimit - userCount }} user slot{{ (userLimit - userCount) !== 1 ? 's' : '' }} remaining.
          </template>
        </p>
      </div>
    </div>

    <!-- ══ SECTION 3: Password ════════════════════════════════════════════════ -->
    <div class="fv-section">
      <h2 class="fv-section-title">
        <span class="fv-section-icon">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
        </span>
        {{ isEdit ? 'Reset Password' : 'Password' }}
      </h2>

      <p v-if="isEdit" class="text-xs text-slate-500 mb-4">
        Leave blank to keep the current password.
      </p>

      <div class="fv-grid-2">
        <div class="fv-field">
          <label class="fv-label">{{ isEdit ? 'New Password' : 'Password' }} {{ !isEdit ? '*' : '' }}</label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPw ? 'text' : 'password'"
              class="fv-input pr-10 w-full"
              :class="{ 'fv-input-error': errors.password }"
              placeholder="Min 8 characters"
            />
            <button type="button" @click="showPw = !showPw" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="!showPw" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-3-9C6.477 3 2 12 2 12s4.477 9 10 9 10-9 10-9S17.523 3 12 3z"/>
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-5.523 0-10-9-10-9a18.66 18.66 0 015.23-6.268M9.879 9.879A3 3 0 0114.12 14.12M3 3l18 18"/>
              </svg>
            </button>
          </div>
          <p v-if="errors.password" class="fv-error">{{ errors.password }}</p>
        </div>

        <div class="fv-field">
          <label class="fv-label">Confirm Password {{ !isEdit ? '*' : '' }}</label>
          <input
            v-model="form.password_confirmation"
            :type="showPw ? 'text' : 'password'"
            class="fv-input w-full"
            placeholder="Repeat password"
          />
        </div>
      </div>
    </div>

    <!-- ══ SECTION 4: Status ═════════════════════════════════════════════════ -->
    <div class="fv-section">
      <h2 class="fv-section-title">
        <span class="fv-section-icon">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </span>
        Status
      </h2>

      <label class="flex items-center gap-3 cursor-pointer w-fit">
        <div class="relative">
          <input type="checkbox" v-model="form.is_active" class="sr-only" />
          <div :class="['w-10 h-5 rounded-full transition-colors', form.is_active ? 'bg-blue-600' : 'bg-slate-700']">
            <div :class="['absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform', form.is_active ? 'translate-x-5' : 'translate-x-0']"/>
          </div>
        </div>
        <span class="text-sm fv-text-primary">
          {{ form.is_active ? 'Active — user can log in' : 'Inactive — login blocked' }}
        </span>
      </label>
    </div>

    <!-- ══ SUBMIT ═════════════════════════════════════════════════════════════ -->
    <div class="flex justify-end gap-3 pt-2">
      <Link :href="route('users.index')" class="fv-btn-ghost">Cancel</Link>
      <button
        type="button"
        @click="$emit('submit')"
        :disabled="processing || isAtLimit"
        class="fv-btn-primary"
        :title="isAtLimit ? 'User limit reached — contact your Super Admin' : ''"
      >
        <svg v-if="processing" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        {{ submitLabel }}
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  form:       Object,
  errors:     Object,
  companies:  Array,
  roles:      Array,
  authRole:   String,
  isEdit:     { type: Boolean, default: false },
  submitLabel:{ type: String, default: 'Save' },
  processing: Boolean,
  adminCount: { type: Number, default: 0 },
  userLimit:  { type: Number, default: null },
  userCount:  { type: Number, default: 0 },
})

defineEmits(['submit'])

const showPw = ref(false)

const selectedRole = computed(() =>
  props.roles?.find(r => r.value === props.form.role)
)

const isAtLimit = computed(() =>
  props.authRole === 'company_admin' &&
  props.userLimit !== null &&
  props.userLimit !== undefined &&
  props.userCount >= props.userLimit
)

const barPercent = computed(() => {
  if (!props.userLimit) return 0
  return Math.min(100, Math.round((props.userCount / props.userLimit) * 100))
})

const barWidth = computed(() => barPercent.value + '%')

const barColor = computed(() => {
  if (barPercent.value >= 100) return 'bg-red-500'
  if (barPercent.value >= 80) return 'bg-amber-500'
  return 'bg-blue-500'
})
</script>

<style scoped>
.fv-section {
  background-color: #112240;
  border: 1px solid #1e2d45;
  border-radius: 0.75rem;
  padding: 1.5rem;
}

.fv-section-title {
  display: flex; align-items: center; gap: 0.625rem;
  font-size: 0.8125rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: #ffffff; margin-bottom: 1.25rem;
}

.fv-section-icon {
  width: 1.75rem; height: 1.75rem;
  background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2);
  border-radius: 0.375rem; display: flex; align-items: center; justify-content: center;
  color: #60a5fa; flex-shrink: 0;
}

.fv-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
.fv-field  { display: flex; flex-direction: column; gap: 0.375rem; }

.fv-label {
  font-size: 0.8125rem; font-weight: 500; color: #FFBF00;
}

.fv-input {
  background-color: #101827; border: 1px solid #1e2d45;
  border-radius: 0.5rem; padding: 0.5rem 0.75rem;
  font-size: 0.875rem; color: #f1f5f9;
  transition: border-color 0.15s; outline: none; width: 100%;
}
.fv-input:focus       { border-color: #3B82F6; }
.fv-input-error       { border-color: #ef4444 !important; }
.fv-input:disabled    { opacity: 0.5; cursor: not-allowed; }
.fv-input option      { background-color: #101827; }

.fv-error { font-size: 0.75rem; color: #f87171; margin-top: 0.125rem; }

.fv-btn-primary {
  display: inline-flex; align-items: center; gap: 0.5rem;
  padding: 0.5rem 1.25rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;
  background-color: #2563eb; color: white; border: none; cursor: pointer;
  transition: background-color 0.15s;
}
.fv-btn-primary:hover    { background-color: #1d4ed8; }
.fv-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.fv-btn-ghost {
  display: inline-flex; align-items: center;
  padding: 0.5rem 1.25rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500;
  background-color: transparent; border: 1px solid #1e2d45; color: #94a3b8;
  text-decoration: none; cursor: pointer; transition: all 0.15s;
}
.fv-btn-ghost:hover { border-color: #334155; color: #f1f5f9; }

.fv-text-primary { color: #f1f5f9; }
</style>