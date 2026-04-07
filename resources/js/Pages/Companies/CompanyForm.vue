<template>
  <form @submit.prevent="$emit('submit')" class="space-y-6">

    <!-- ── SECTION 1: Basic Information ──────────────────────────────────────── -->
    <div class="fv-card">
      <div class="fv-section-header">
        <div class="fv-section-icon fv-section-icon-teal">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9"/>
          </svg>
        </div>
        <div>
          <h2 class="fv-section-title">Basic Information</h2>
          <p class="fv-section-sub">Legal name, trade name, and structure</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">

        <div class="fv-field sm:col-span-2">
          <label class="fv-label">Legal Name <span class="text-red-400">*</span></label>
          <input v-model="form.name" type="text" class="fv-input fv-input-styled"
            placeholder="e.g. Cairo Consulting Group S.A.E." />
          <p v-if="errors.name" class="fv-error">{{ errors.name }}</p>
        </div>

        <div class="fv-field">
          <label class="fv-label">Trade / Brand Name</label>
          <input v-model="form.trade_name" type="text" class="fv-input fv-input-styled" placeholder="e.g. CCG" />
        </div>

        <div class="fv-field">
          <label class="fv-label">Legal Structure</label>
          <select v-model="form.legal_structure" class="fv-input fv-input-styled">
            <option value="">— Select —</option>
            <option v-for="ls in legalStructures" :key="ls" :value="ls">{{ ls }}</option>
          </select>
        </div>

        <div class="fv-field">
          <label class="fv-label">Established Year</label>
          <input v-model.number="form.established_year" type="number" class="fv-input fv-input-styled"
            placeholder="e.g. 2010" min="1900" max="2100" />
        </div>

        <div class="fv-field">
          <label class="fv-label">Parent Company</label>
          <select v-model="form.parent_id" class="fv-input fv-input-styled">
            <option :value="null">— None (top-level) —</option>
            <option v-if="parents.length === 0" disabled value="">No other companies exist yet</option>
            <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

      </div>
    </div>

    <!-- ── SECTION 2: Tax Configuration ──────────────────────────────────────── -->
    <div class="fv-card">
      <div class="fv-section-header">
        <div class="fv-section-icon fv-section-icon-gold">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
          </svg>
        </div>
        <div>
          <h2 class="fv-section-title">Tax Configuration</h2>
          <p class="fv-section-sub">Select the applicable tax regime for this company</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">

        <label class="fv-radio-card" :class="form.tax_type === 'corporate_income_tax' ? 'fv-radio-active-teal' : ''">
          <input type="radio" value="corporate_income_tax" v-model="form.tax_type" class="sr-only" />
          <div class="flex items-start gap-3">
            <div class="fv-radio-dot mt-0.5" :class="form.tax_type === 'corporate_income_tax' ? 'fv-radio-dot-teal' : ''"></div>
            <div>
              <p class="text-sm font-semibold" :class="form.tax_type === 'corporate_income_tax' ? 'fv-accent-teal' : 'fv-text-primary'">
                Corporate Income Tax
              </p>
              <p class="text-xs fv-text-muted mt-1">Standard Egyptian corporate tax — applies to most companies</p>
            </div>
          </div>
        </label>

        <label class="fv-radio-card" :class="form.tax_type === 'zakat' ? 'fv-radio-active-gold' : ''">
          <input type="radio" value="zakat" v-model="form.tax_type" class="sr-only" />
          <div class="flex items-start gap-3">
            <div class="fv-radio-dot mt-0.5" :class="form.tax_type === 'zakat' ? 'fv-radio-dot-gold' : ''"></div>
            <div>
              <p class="text-sm font-semibold" :class="form.tax_type === 'zakat' ? 'fv-accent-gold' : 'fv-text-primary'">
                Zakat
              </p>
              <p class="text-xs fv-text-muted mt-1">Islamic tax regime — applies to qualifying entities</p>
            </div>
          </div>
        </label>

      </div>

      <p v-if="!form.tax_type" class="fv-hint-gold mt-3">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        No tax type selected — affects tax calculation modules
      </p>
    </div>

    <!-- ── SECTION 3: Financial Settings ─────────────────────────────────────── -->
    <div class="fv-card">
      <div class="fv-section-header">
        <div class="fv-section-icon fv-section-icon-teal">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
          </svg>
        </div>
        <div>
          <h2 class="fv-section-title">Financial Settings</h2>
          <p class="fv-section-sub">Currency, fiscal year, and registration details</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">

        <div class="fv-field">
          <label class="fv-label">Currency</label>
          <select v-model="form.currency" class="fv-input fv-input-styled">
            <option value="">— Select —</option>
            <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>

        <div class="fv-field">
          <label class="fv-label">Fiscal Year Start</label>
          <select v-model.number="form.fiscal_year_start" class="fv-input fv-input-styled">
            <option :value="null">— Select Month —</option>
            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>

        <div class="fv-field">
          <label class="fv-label">Registration Number</label>
          <input v-model="form.registration_number" type="text" class="fv-input fv-input-styled"
            placeholder="e.g. 12345678" />
        </div>

        <div class="fv-field">
          <label class="fv-label">Tax ID</label>
          <input v-model="form.tax_id" type="text" class="fv-input fv-input-styled"
            placeholder="e.g. 200-123-456" />
        </div>

      </div>
    </div>

    <!-- ── SECTION 4: Module Subscriptions ───────────────────────────────────── -->
    <div class="fv-card">
      <div class="fv-section-header">
        <div class="fv-section-icon fv-section-icon-navy">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
          </svg>
        </div>
        <div class="flex-1">
          <h2 class="fv-section-title">Module Subscriptions</h2>
          <p class="fv-section-sub">Select which modules this company has access to</p>
        </div>
        <button type="button" @click="toggleAllModules" class="fv-link-btn ml-auto flex-shrink-0">
          {{ allModulesSelected ? 'Deselect All' : 'Select All' }}
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-5">
        <label
          v-for="(label, key) in modules"
          :key="key"
          class="fv-module-card"
          :class="form.enabled_modules.includes(key) ? 'fv-module-card-active' : ''"
        >
          <input type="checkbox" :value="key" v-model="form.enabled_modules" class="sr-only" />
          <div class="flex items-center gap-2.5 w-full">
            <div class="fv-module-dot" :class="form.enabled_modules.includes(key) ? 'fv-module-dot-active' : ''"></div>
            <span class="text-xs font-semibold flex-1">{{ label }}</span>
            <svg v-if="form.enabled_modules.includes(key)"
              class="w-3.5 h-3.5 flex-shrink-0 fv-accent-teal"
              fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
        </label>
      </div>

      <!-- Progress bar -->
      <div class="fv-module-footer mt-4">
        <div class="fv-progress-track">
          <div class="fv-progress-fill" :style="{ width: modulePercent + '%' }"></div>
        </div>
        <p class="text-xs fv-text-muted mt-2">
          <span class="fv-accent-teal font-bold">{{ form.enabled_modules.length }}</span>
          of {{ Object.keys(modules).length }} modules enabled
        </p>
      </div>
    </div>

    <!-- ── SECTION 5: Contact & Location ─────────────────────────────────────── -->
    <div class="fv-card">
      <div class="fv-section-header">
        <div class="fv-section-icon fv-section-icon-teal">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>
        <div>
          <h2 class="fv-section-title">Contact & Location</h2>
          <p class="fv-section-sub">Address, contact details and online presence</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">

        <div class="fv-field">
          <label class="fv-label">Country</label>
          <input v-model="form.country" type="text" class="fv-input fv-input-styled" placeholder="e.g. Egypt" />
        </div>

        <div class="fv-field">
          <label class="fv-label">City</label>
          <input v-model="form.city" type="text" class="fv-input fv-input-styled" placeholder="e.g. Cairo" />
        </div>

        <div class="fv-field sm:col-span-2">
          <label class="fv-label">Address</label>
          <input v-model="form.address" type="text" class="fv-input fv-input-styled" placeholder="Street address" />
        </div>

        <div class="fv-field">
          <label class="fv-label">Phone</label>
          <input v-model="form.phone" type="text" class="fv-input fv-input-styled" placeholder="+20 2 1234 5678" />
        </div>

        <div class="fv-field">
          <label class="fv-label">Email</label>
          <input v-model="form.email" type="email" class="fv-input fv-input-styled" placeholder="info@company.com" />
          <p v-if="errors.email" class="fv-error">{{ errors.email }}</p>
        </div>

        <div class="fv-field sm:col-span-2">
          <label class="fv-label">Website</label>
          <input v-model="form.website" type="url" class="fv-input fv-input-styled" placeholder="https://www.company.com" />
          <p v-if="errors.website" class="fv-error">{{ errors.website }}</p>
        </div>

      </div>
    </div>

    <!-- ── SECTION 6: Notes & Status ─────────────────────────────────────────── -->
    <div class="fv-card">
      <div class="fv-section-header">
        <div class="fv-section-icon fv-section-icon-gold">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </div>
        <div>
          <h2 class="fv-section-title">Notes & Status</h2>
          <p class="fv-section-sub">Internal notes and activation state</p>
        </div>
      </div>

      <div class="space-y-4 mt-5">

        <div class="fv-field">
          <label class="fv-label">Internal Notes</label>
          <textarea v-model="form.notes" rows="3" class="fv-input fv-input-styled resize-none"
            placeholder="Any internal notes about this company..."></textarea>
        </div>

        <div class="fv-toggle-row" @click="form.is_active = !form.is_active">
          <div class="fv-toggle" :class="form.is_active ? 'fv-toggle-on' : 'fv-toggle-off'">
            <div class="fv-toggle-thumb" :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"></div>
          </div>
          <div>
            <span class="text-sm font-semibold fv-text-primary">Company is Active</span>
            <span class="text-xs fv-text-muted ml-2">(inactive companies are hidden from regular users)</span>
          </div>
        </div>

      </div>
    </div>

    <!-- ── FORM ACTIONS ───────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-end gap-3 pt-2 pb-6">
      <Link :href="route('companies.index')" class="fv-btn-secondary text-sm px-5 py-2.5 rounded-lg">
        Cancel
      </Link>
      <button
        type="submit"
        :disabled="processing"
        class="fv-btn-primary text-sm px-6 py-2.5 rounded-lg flex items-center gap-2"
      >
        <svg v-if="processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        {{ processing ? 'Saving...' : submitLabel }}
      </button>
    </div>

  </form>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  form:        Object,
  errors:      { type: Object,  default: () => ({}) },
  parents:     { type: Array,   default: () => [] },
  modules:     { type: Object,  default: () => ({}) },
  processing:  { type: Boolean, default: false },
  submitLabel: { type: String,  default: 'Save Company' },
})

defineEmits(['submit'])

const allModulesSelected = computed(() =>
  Object.keys(props.modules).length > 0 &&
  Object.keys(props.modules).every(k => props.form.enabled_modules.includes(k))
)

const modulePercent = computed(() => {
  const total = Object.keys(props.modules).length
  return total > 0 ? Math.round((props.form.enabled_modules.length / total) * 100) : 0
})

function toggleAllModules() {
  props.form.enabled_modules = allModulesSelected.value ? [] : Object.keys(props.modules)
}

const legalStructures = [
  'S.A.E. (Joint Stock)', 'S.A.R.L. (LLC)', 'Branch', 'Holding Company',
  'Sole Proprietorship', 'Partnership', 'Cooperative', 'Other',
]

const currencies = [
  { value: 'EGP', label: 'EGP — Egyptian Pound' },
  { value: 'USD', label: 'USD — US Dollar'       },
  { value: 'EUR', label: 'EUR — Euro'             },
  { value: 'GBP', label: 'GBP — British Pound'   },
  { value: 'SAR', label: 'SAR — Saudi Riyal'      },
  { value: 'AED', label: 'AED — UAE Dirham'       },
]

const months = [
  { value: 1,  label: 'January'   }, { value: 2,  label: 'February'  },
  { value: 3,  label: 'March'     }, { value: 4,  label: 'April'     },
  { value: 5,  label: 'May'       }, { value: 6,  label: 'June'      },
  { value: 7,  label: 'July'      }, { value: 8,  label: 'August'    },
  { value: 9,  label: 'September' }, { value: 10, label: 'October'   },
  { value: 11, label: 'November'  }, { value: 12, label: 'December'  },
]
</script>

<style scoped>
/* ─────────────────────────────────────────────────────────────────────
   ALL colors reference --fv- variables from app.css
   Raw hex only used where a variable doesn't exist yet
───────────────────────────────────────────────────────────────────── */

/* Accent helpers */
.fv-accent-teal { color: #48C4D8; }
.fv-accent-gold { color: #FAC775; }

/* Section header */
.fv-section-header { display: flex; align-items: center; gap: 0.875rem; }
.fv-section-title  { font-size: 0.875rem; font-weight: 700; color: var(--fv-text-primary, #F1F5F9); }
.fv-section-sub    { font-size: 0.75rem; color: var(--fv-text-muted, #6B96B8); margin-top: 0.1rem; }

/* Section icons */
.fv-section-icon {
  width: 2.25rem; height: 2.25rem; border-radius: 0.5rem;
  border: 1px solid; display: flex; align-items: center;
  justify-content: center; flex-shrink: 0;
}
.fv-section-icon-teal {
  background-color: var(--fv-blue-dim);
  border-color: var(--fv-blue-border);
  color: #48C4D8;
}
.fv-section-icon-gold {
  background-color: var(--fv-gold-dim);
  border-color: var(--fv-gold-border);
  color: #FAC775;
}
.fv-section-icon-navy {
  background: linear-gradient(135deg, rgba(12,68,124,0.5), rgba(20,144,168,0.15));
  border-color: var(--fv-blue-border);
  color: #48C4D8;
}

/* Field */
.fv-field   { display: flex; flex-direction: column; gap: 0.375rem; }
.fv-label   { font-size: 0.75rem; font-weight: 600; color: var(--fv-text-label, #94B8D0); }
.fv-error   { font-size: 0.7rem; color: #f87171; margin-top: 0.2rem; }

/* Input extras on top of global .fv-input */
.fv-input-styled {
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  width: 100%;
}
.fv-input-styled option { background-color: var(--fv-bg-card, #112240); }

/* ── Radio Cards ────────────────────────────────────────────────────── */
.fv-radio-card {
  display: block;
  background-color: var(--fv-bg-input, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  border-radius: 0.625rem;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.fv-radio-card:hover {
  border-color: var(--fv-blue-border);
  background-color: var(--fv-bg-hover, #163050);
}
.fv-radio-active-teal {
  background-color: var(--fv-blue-dim);
  border-color: var(--fv-border-focus, #1490A8) !important;
}
.fv-radio-active-gold {
  background-color: var(--fv-gold-dim);
  border-color: var(--fv-gold, #BA7517) !important;
}

.fv-radio-dot {
  width: 1rem; height: 1rem; border-radius: 9999px;
  border: 2px solid var(--fv-border, #1B3558);
  flex-shrink: 0; transition: all 0.15s ease;
  background: transparent; position: relative;
}
.fv-radio-dot::after {
  content: ''; position: absolute; inset: 0; margin: auto;
  width: 0.4rem; height: 0.4rem; border-radius: 9999px;
  transform: scale(0); transition: transform 0.15s ease;
}
.fv-radio-dot-teal { border-color: var(--fv-border-focus, #1490A8); }
.fv-radio-dot-teal::after { background-color: #48C4D8; transform: scale(1); }
.fv-radio-dot-gold { border-color: var(--fv-gold, #BA7517); }
.fv-radio-dot-gold::after { background-color: #FAC775; transform: scale(1); }

/* Gold hint line */
.fv-hint-gold {
  display: flex; align-items: center; gap: 0.375rem;
  font-size: 0.75rem; color: #FAC775;
}

/* ── Module Checkbox Cards ──────────────────────────────────────────── */
.fv-module-card {
  display: flex; align-items: center;
  background-color: var(--fv-bg-input, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  cursor: pointer;
  transition: all 0.15s ease;
  color: var(--fv-text-muted, #6B96B8);
  user-select: none; min-height: 2.5rem;
}
.fv-module-card:hover {
  border-color: var(--fv-blue-border);
  color: var(--fv-text-primary, #F1F5F9);
  background-color: var(--fv-bg-hover, #163050);
}
.fv-module-card-active {
  background-color: var(--fv-blue-dim);
  border-color: var(--fv-border-focus, #1490A8);
  color: #48C4D8;
}
.fv-module-dot {
  width: 0.5rem; height: 0.5rem; border-radius: 9999px;
  background-color: var(--fv-border, #1B3558);
  flex-shrink: 0; transition: background-color 0.15s ease;
}
.fv-module-dot-active { background-color: #48C4D8; }

/* Progress bar */
.fv-module-footer { border-top: 1px solid var(--fv-border, #1B3558); padding-top: 0.875rem; }
.fv-progress-track {
  width: 100%; height: 3px;
  background-color: var(--fv-border, #1B3558);
  border-radius: 9999px; overflow: hidden;
}
.fv-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--fv-blue, #1490A8), #48C4D8);
  border-radius: 9999px; transition: width 0.3s ease;
}

/* Link button */
.fv-link-btn {
  font-size: 0.75rem; font-weight: 600;
  color: var(--fv-blue, #1490A8);
  background: none; border: none;
  cursor: pointer; padding: 0;
  transition: color 0.15s ease;
}
.fv-link-btn:hover { color: #48C4D8; }

/* Toggle */
.fv-toggle-row { display: flex; align-items: center; gap: 0.75rem; cursor: pointer; width: fit-content; }
.fv-toggle {
  width: 2.5rem; height: 1.375rem; border-radius: 9999px;
  transition: background-color 0.2s ease; position: relative; flex-shrink: 0;
}
.fv-toggle-on  { background-color: var(--fv-blue, #1490A8); }
.fv-toggle-off { background-color: var(--fv-border, #1B3558); }
.fv-toggle-thumb {
  position: absolute; top: 0.1875rem;
  width: 1rem; height: 1rem;
  background: white; border-radius: 9999px;
  transition: transform 0.2s ease;
}

/* Primary button */
.fv-btn-primary {
  background-color: var(--fv-blue, #1490A8);
  color: white; font-weight: 600;
  border: none; cursor: pointer;
  transition: background-color 0.15s ease, box-shadow 0.15s ease;
}
.fv-btn-primary:hover:not(:disabled) {
  background-color: var(--fv-blue-hover, #0F6E7E);
  box-shadow: 0 0 0 3px var(--fv-blue-dim);
}
.fv-btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
</style>