<template>
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg px-6 py-8">

      <!-- ── Header ─────────────────────────────────────────────── -->
      <div class="mb-6">
        <div class="flex items-center gap-2 mb-1">
          <a :href="route('company.properties.index', company.id)" class="fv-text-muted text-sm hover:underline">Properties</a>
          <span class="fv-text-muted text-sm">/</span>
          <a :href="route('company.properties.contracts.index', [company.id, property.id])" class="fv-text-muted text-sm hover:underline">{{ property.property_name }}</a>
          <span class="fv-text-muted text-sm">/</span>
          <span class="fv-text-primary text-sm font-semibold">
            {{ isEdit ? 'Edit Contract' : renewedFrom ? 'Renew Contract' : 'New Contract' }}
          </span>
        </div>
        <h1 class="text-2xl font-bold fv-text-primary">
          {{ isEdit ? 'Edit Rent Contract' : renewedFrom ? 'Renew Contract' : 'New Rent Contract' }}
        </h1>
        <p v-if="renewedFrom" class="fv-text-muted text-sm mt-0.5">
          Renewing from contract with <strong class="fv-text-primary">{{ renewedFrom.customer?.customer_name }}</strong>
          ({{ formatDate(renewedFrom.start_date) }} → {{ formatDate(renewedFrom.end_date) }})
        </p>
      </div>

      <!-- Locked banner — contract has collected rent payments on record -->
      <div v-if="isLocked" class="fv-card mb-6 flex items-start gap-3" style="border-color:#f87171; background:rgba(239,68,68,0.06);">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color:#f87171" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
        <p class="text-sm" style="color:#f87171">
          This contract has collected rent payments on record and cannot be edited.
          Delete the collected rows first (Rent Collections tab) if you need to edit this contract.
        </p>
      </div>

      <!-- Same message, shown if the server rejects the save for this reason
           even when the banner above wasn't showing (e.g. collected rows
           were added in another tab after this page loaded). -->
      <div v-if="errors.contract" class="fv-card mb-6" style="border-color:#f87171; background:rgba(239,68,68,0.06);">
        <p class="text-sm" style="color:#f87171">{{ errors.contract }}</p>
      </div>

      <form @submit.prevent="submit">
        <fieldset :disabled="isLocked" class="max-w-3xl space-y-6" style="border:none; padding:0; margin:0; min-width:0;">

          <!-- Revenue Type -->
          <div class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Revenue Type</h2>
            <div class="grid grid-cols-2 gap-3">
              <button type="button"
                v-for="rt in revenueTypes" :key="rt.value"
                @click="form.revenue_type = rt.value"
                class="p-4 rounded-xl border-2 text-left transition-all"
                :style="form.revenue_type === rt.value
                  ? 'border-color:var(--fv-blue);background:var(--fv-blue-dim)'
                  : 'border-color:var(--fv-border);background:var(--fv-bg-card)'">
                <div class="font-semibold text-sm fv-text-primary">{{ rt.label }}</div>
                <div class="fv-text-muted text-xs mt-0.5">{{ rt.desc }}</div>
              </button>
            </div>

            <!-- Management Fee Revenue Rate -->
            <div v-if="form.revenue_type === 'management_fee'" class="mt-4">
              <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">
                Management Fee Revenue Rate (%) *
              </label>
              <input type="number" v-model="form.management_fee_rate"
                min="0" max="100" step="0.01"
                class="fv-input rounded-lg px-3 py-2 text-sm w-48"
                placeholder="e.g. 10"/>
              <p class="fv-text-muted text-xs mt-1">
                This unit is owned by someone else — you earn Rent × this rate as your only revenue and collection.
                The full rent is settled directly between tenant and owner, so a Management Fees Expense does not apply here.
              </p>
            </div>
          </div>

          <!-- Linked Unit -->
          <div v-if="property.units && property.units.length > 0" class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Linked Unit</h2>
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Unit *</label>
            <select v-model="form.property_unit_id" class="fv-select rounded-lg px-3 py-2 text-sm w-full">
              <option value="">— Select Unit —</option>
              <option v-for="u in property.units" :key="u.id" :value="u.id">
                {{ u.unit_name }}<span v-if="u.unit_code"> ({{ u.unit_code }})</span>
              </option>
            </select>
            <p v-if="errors.property_unit_id" class="text-red-400 text-xs mt-1">{{ errors.property_unit_id }}</p>
          </div>

          <!-- Tenant -->
          <div class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Tenant</h2>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Tenant Nature *</label>
                <select v-model="form.tenant_nature" @change="form.customer_id = ''"
                  class="fv-select rounded-lg px-3 py-2 text-sm w-full">
                  <option value="">— Select —</option>
                  <option value="individual">Individual</option>
                  <option value="corporate">Corporate</option>
                </select>
                <p v-if="errors.tenant_nature" class="text-red-400 text-xs mt-1">{{ errors.tenant_nature }}</p>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Tenant Name *</label>
                <select v-model="form.customer_id" class="fv-select rounded-lg px-3 py-2 text-sm w-full"
                  :disabled="!form.tenant_nature">
                  <option value="">— Select Tenant —</option>
                  <option v-for="t in filteredTenants" :key="t.id" :value="t.id">
                    {{ t.customer_name }}
                  </option>
                </select>
                <p v-if="errors.customer_id" class="text-red-400 text-xs mt-1">{{ errors.customer_id }}</p>
              </div>
            </div>
          </div>

          <!-- Contract Dates -->
          <div class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Contract Period</h2>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Start Date *</label>
                <input type="date" v-model="form.start_date" class="fv-input rounded-lg px-3 py-2 text-sm w-full"/>
                <p v-if="errors.start_date" class="text-red-400 text-xs mt-1">{{ errors.start_date }}</p>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">End Date *</label>
                <input type="date" v-model="form.end_date" class="fv-input rounded-lg px-3 py-2 text-sm w-full"/>
                <p v-if="errors.end_date" class="text-red-400 text-xs mt-1">{{ errors.end_date }}</p>
              </div>
            </div>
            <div v-if="contractDuration" class="mt-3 px-3 py-2 rounded-lg text-sm fv-text-muted"
              style="background:var(--fv-blue-dim);border:1px solid var(--fv-blue-border)">
              Duration: <strong class="fv-text-primary">{{ contractDuration }}</strong>
            </div>
          </div>

          <!-- Financial Terms -->
          <div class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Financial Terms</h2>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Contract Currency *</label>
                <select v-model="form.contract_currency" class="fv-select rounded-lg px-3 py-2 text-sm w-full">
                  <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Monthly Rent Amount *</label>
                <input type="number" v-model="form.monthly_rent_amount"
                  min="0" step="0.01"
                  class="fv-input rounded-lg px-3 py-2 text-sm w-full"
                  placeholder="0.00"/>
                <p v-if="errors.monthly_rent_amount" class="text-red-400 text-xs mt-1">{{ errors.monthly_rent_amount }}</p>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">
                  Variable from Tenant Revenues %
                  <span class="fv-tag ml-1">Info only</span>
                </label>
                <input type="number" v-model="form.variable_revenue_pct"
                  min="0" max="100" step="0.01"
                  class="fv-input rounded-lg px-3 py-2 text-sm w-full"
                  placeholder="0"/>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">
                  Min Monthly Rent Amount
                </label>
                <input type="number" v-model="form.min_monthly_rent"
                  min="0" step="0.01"
                  class="fv-input rounded-lg px-3 py-2 text-sm w-full"
                  placeholder="0.00"/>
                <p class="fv-text-muted text-xs mt-1">If set, overrides Monthly Rent as the basis for all calculations.</p>
              </div>
              <div v-if="form.contract_currency !== baseCurrency">
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">
                  FX Rate at Signing (1 {{ form.contract_currency }} = ? {{ baseCurrency }})
                </label>
                <input type="number" v-model="form.fx_rate"
                  min="0.000001" step="0.000001"
                  class="fv-input rounded-lg px-3 py-2 text-sm w-full"
                  :placeholder="`e.g. 48.50`"/>
                <p class="fv-text-muted text-xs mt-1">
                  Saves this as today's exchange rate for {{ form.contract_currency }} in your Exchange
                  Rates table — the same one the Dashboard and Cash Forecast read from. Updating the
                  rate later (Company Settings → Exchange Rates) is what moves future reporting; this
                  field just saves you re-typing it now.
                </p>
              </div>
            </div>
          </div>

          <!-- Collection -->
          <div class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Collection</h2>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Collection Currency *</label>
                <select v-model="form.collection_currency" class="fv-select rounded-lg px-3 py-2 text-sm w-full">
                  <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                </select>
                <p v-if="form.collection_currency !== form.contract_currency" class="fv-text-muted text-xs mt-1"
                  style="color:var(--fv-gold)">
                  ⚠ Different from contract currency — collections (and the insurance deposit below)
                  will be converted to {{ form.collection_currency }} using the latest rate in your
                  Exchange Rates table.
                </p>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Collection Interval *</label>
                <select v-model="form.collection_interval_months" class="fv-select rounded-lg px-3 py-2 text-sm w-full">
                  <option v-for="opt in intervalOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Insurance Months</label>
                <input type="number" v-model="form.insurance_months"
                  min="0" max="24" step="1"
                  class="fv-input rounded-lg px-3 py-2 text-sm w-full"
                  placeholder="0"/>
              </div>
              <div>
                <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">
                  Insurance Amount
                  <span class="fv-tag ml-1">Auto</span>
                </label>
                <div class="fv-input rounded-lg px-3 py-2 text-sm w-full fv-text-muted"
                  style="background:var(--fv-bg-input);border:1px solid var(--fv-border)">
                  {{ formatMoney(insuranceAmountCalc) }} {{ form.collection_currency }}
                </div>
                <p class="fv-text-muted text-xs mt-1">
                  Rent Basis × Insurance Months, in the <strong>collection</strong> currency (the deposit
                  is real cash collected alongside the rent). Preview here is unconverted if collection
                  currency differs from contract currency — the exact converted amount is calculated
                  when you save, using the latest exchange rate on file.
                </p>
              </div>
            </div>
          </div>

          <!-- Annual Increase Schedule -->
          <div class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Increase Rate Per Year</h2>
            <p class="fv-text-muted text-xs mb-3">
              Starts from contract year 2. Increase is applied from the day after each anniversary.
            </p>

            <div v-if="annualIncreaseYears.length === 0"
              class="rounded-lg px-3 py-2 text-sm fv-text-muted"
              style="background:var(--fv-bg-input);border:1px solid var(--fv-border)">
              No increase years yet. Set contract start and end dates first.
            </div>

            <div v-else class="space-y-2">
              <div class="grid grid-cols-2 gap-3 px-1">
                <div class="fv-text-label text-xs font-semibold uppercase tracking-wider">Year</div>
                <div class="fv-text-label text-xs font-semibold uppercase tracking-wider">Increase Rate (%)</div>
              </div>

              <div v-for="row in form.annual_increase_schedule" :key="row.year" class="grid grid-cols-2 gap-3 items-center">
                <div class="fv-input rounded-lg px-3 py-2 text-sm fv-text-muted"
                  style="background:var(--fv-bg-input);border:1px solid var(--fv-border)">
                  {{ row.year }}
                </div>
                <input
                  type="number"
                  v-model.number="row.rate"
                  min="0"
                  max="100"
                  step="0.01"
                  class="fv-input rounded-lg px-3 py-2 text-sm w-full"
                  placeholder="0"
                />
              </div>
            </div>
          </div>

          <!-- Management Fees Expense — only applies when the company owns the unit itself (Direct Rent).
               Not applicable when revenue_type = management_fee, since in that case the company
               doesn't own the rent stream at all — it only earns the Management Fee Revenue above. -->
          <div v-if="form.revenue_type !== 'management_fee'" class="fv-card">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">Management Fees Expense</h2>
            <p class="fv-text-muted text-xs mb-3">
              Use this only if you own this unit and pay an outside party to manage it for you.
            </p>

            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.has_management_fees"
                class="rounded border"
                style="accent-color:var(--fv-blue)"
              />
              <span class="fv-text-primary text-sm font-semibold">Has Management Fees</span>
            </label>

            <div v-if="form.has_management_fees" class="mt-4">
              <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">
                Percentage from Collection (%)
              </label>
              <input
                type="number"
                v-model.number="form.management_fee_expense_rate"
                min="0"
                max="100"
                step="0.01"
                class="fv-input rounded-lg px-3 py-2 text-sm w-full"
                placeholder="0"
              />
              <p class="fv-text-muted text-xs mt-1">
                This will be recorded as cash out on each collection due date (same interval terms).
              </p>
            </div>
          </div>

          <!-- Schedule Preview -->
          <div v-if="canPreview" class="fv-card"
            style="border-color:var(--fv-blue-border);background:var(--fv-blue-dim)">
            <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-3">Schedule Preview</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
              <div>
                <div class="text-xl font-bold fv-text-primary">{{ previewMonths }}</div>
                <div class="fv-text-muted text-xs">Total Months</div>
              </div>
              <div>
                <div class="text-xl font-bold fv-text-primary">{{ previewCollections }}</div>
                <div class="fv-text-muted text-xs">Collections</div>
              </div>
              <div>
                <div class="text-xl font-bold" style="color:var(--fv-gold)">
                  {{ formatMoney(rentBasis) }}
                </div>
                <div class="fv-text-muted text-xs">Monthly Rent Basis ({{ form.contract_currency }})</div>
              </div>
              <div>
                <div class="text-xl font-bold" style="color:var(--fv-gold)">
                  {{ formatMoney(firstCollectionAmount) }}
                </div>
                <div class="fv-text-muted text-xs">First Collection ({{ form.contract_currency }})</div>
              </div>
            </div>
          </div>

          <!-- Hidden renewal link -->
          <input v-if="renewedFrom" type="hidden" v-model="form.renewed_from_contract_id"/>

          <!-- Actions -->
          <div class="flex items-center gap-3 pb-8">
            <a :href="route('company.properties.contracts.index', [company.id, property.id])"
               class="fv-btn-secondary px-6 py-2.5 rounded-lg text-sm font-semibold">
              Cancel
            </a>
            <button type="submit" :disabled="form.processing"
              class="btn-teal px-6 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2">
              <svg v-if="form.processing" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4"/>
              </svg>
              {{ form.processing ? 'Saving…' : isEdit ? 'Update Contract' : 'Save & Generate Schedule' }}
            </button>
          </div>

        </fieldset>
      </form>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:         Object,
  property:        Object,
  tenants:         Array,
  currencyOptions: Array,
  intervalOptions: Array,
  renewedFrom:     Object,     // for renewal
  contract:        Object,     // ← NEW: for edit mode (null when creating)
  baseCurrency:    { type: String, default: 'EGP' },
  hasCollectedHistory: { type: Boolean, default: false },
})

const isEdit = computed(() => !!props.contract)

// Confirmed product decision — a contract with any 'collected' row is
// locked for editing entirely, every field, since any save regenerates the
// whole revenue/collection schedule. The user has to delete the collected
// rows first (Rent Collections tab on the Show page) before this form will
// accept changes. This is enforced again server-side in
// RentContractController::update() regardless of what this flag does here.
const isLocked = computed(() => isEdit.value && props.hasCollectedHistory)

const revenueTypes = [
  { value: 'direct_rent',    label: 'Direct Rent',       desc: 'You own the unit and collect rent directly' },
  { value: 'management_fee', label: 'Management Fee',     desc: 'You manage on behalf of owner, keep a % fee' },
]

// Initialize form with existing contract data when editing
const initialIncreaseSchedule =
  props.contract?.annual_increase_schedule ??
  props.renewedFrom?.annual_increase_schedule ??
  []
const legacyIncreaseRate = Number(props.contract?.annual_increase_rate ?? props.renewedFrom?.annual_increase_rate ?? 0)

const form = useForm({
  property_unit_id:           props.contract?.property_unit_id ?? props.renewedFrom?.property_unit_id ?? '',
  revenue_type:               props.contract?.revenue_type ?? props.renewedFrom?.revenue_type ?? 'direct_rent',
  management_fee_rate:        props.contract?.management_fee_rate ?? props.renewedFrom?.management_fee_rate ?? '',
  has_management_fees:        !!(props.contract?.has_management_fees ?? props.renewedFrom?.has_management_fees ?? false),
  management_fee_expense_rate: props.contract?.management_fee_expense_rate ?? props.renewedFrom?.management_fee_expense_rate ?? '',
  tenant_nature:              props.contract?.tenant_nature ?? props.renewedFrom?.tenant_nature ?? '',
  customer_id:                props.contract?.customer_id ?? props.renewedFrom?.customer_id ?? '',
  start_date:                 props.contract?.start_date ? props.contract.start_date.slice(0, 10) : '',
  end_date:                   props.contract?.end_date   ? props.contract.end_date.slice(0, 10)   : '',
  contract_currency:          props.contract?.contract_currency ?? props.renewedFrom?.contract_currency ?? 'EGP',
  monthly_rent_amount:        props.contract?.monthly_rent_amount ?? '',
  variable_revenue_pct:       props.contract?.variable_revenue_pct ?? props.renewedFrom?.variable_revenue_pct ?? '',
  min_monthly_rent:           props.contract?.min_monthly_rent ?? '',
  fx_rate:                    '',
  collection_currency:        props.contract?.collection_currency ?? props.renewedFrom?.collection_currency ?? 'EGP',
  collection_interval_months: props.contract?.collection_interval_months ?? props.renewedFrom?.collection_interval_months ?? 1,
  insurance_months:           props.contract?.insurance_months ?? props.renewedFrom?.insurance_months ?? 0,
  annual_increase_schedule:   Array.isArray(initialIncreaseSchedule) ? initialIncreaseSchedule : [],
  renewed_from_contract_id:   props.renewedFrom?.id ?? null,
})

const errors = computed(() => form.errors)

const filteredTenants = computed(() => {
  if (!form.tenant_nature) return []
  return props.tenants.filter(t => t.tenant_nature === form.tenant_nature)
})

// All computed properties remain exactly the same
const rentBasis = computed(() => {
  const min = parseFloat(form.min_monthly_rent) || 0
  const monthly = parseFloat(form.monthly_rent_amount) || 0
  return min > 0 ? min : monthly
})

const insuranceAmountCalc = computed(() => rentBasis.value * (parseInt(form.insurance_months) || 0))

const contractDuration = computed(() => {
  if (!form.start_date || !form.end_date) return null
  const s = new Date(form.start_date)
  const e = new Date(form.end_date)
  if (e <= s) return null
  let months = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth())
  // If end day >= start day, the end month is fully included — count it
  if (e.getDate() >= s.getDate()) months += 1
  const years = Math.floor(months / 12)
  const rem = months % 12
  let out = ''
  if (years > 0) out += `${years} year${years > 1 ? 's' : ''}`
  if (rem > 0) out += `${out ? ' ' : ''}${rem} month${rem > 1 ? 's' : ''}`
  return out || null
})

const previewMonths = computed(() => {
  if (!form.start_date || !form.end_date) return 0
  const s = new Date(form.start_date)
  const e = new Date(form.end_date)
  let months = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth())
  if (e.getDate() >= s.getDate()) months += 1
  return Math.max(0, months)
})

const previewCollections = computed(() => {
  if (!previewMonths.value || !form.collection_interval_months) return 0
  return Math.ceil(previewMonths.value / form.collection_interval_months)
})

const firstCollectionAmount = computed(() => rentBasis.value * form.collection_interval_months)

const canPreview = computed(() => form.start_date && form.end_date && rentBasis.value > 0)

const annualIncreaseYears = computed(() => {
  if (!form.start_date || !form.end_date) return []

  const start = new Date(form.start_date)
  const end = new Date(form.end_date)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) return []

  const years = []
  const increaseDate = new Date(start)
  increaseDate.setFullYear(increaseDate.getFullYear() + 1)
  increaseDate.setDate(increaseDate.getDate() + 1)

  while (increaseDate <= end) {
    years.push(increaseDate.getFullYear())
    increaseDate.setFullYear(increaseDate.getFullYear() + 1)
  }

  return years
})

watch(
  annualIncreaseYears,
  (years) => {
    const existing = new Map(
      (Array.isArray(form.annual_increase_schedule) ? form.annual_increase_schedule : []).map((row) => [
        Number(row.year),
        Number(row.rate) || 0,
      ])
    )

    form.annual_increase_schedule = years.map((year) => ({
      year,
      rate: existing.has(year) ? existing.get(year) : legacyIncreaseRate,
    }))
  },
  { immediate: true }
)

// Management Fee Revenue (owned by others, we manage it) and Management Fees
// Expense (we own it, someone else manages it) are mutually exclusive.
// Clear the expense side the moment the contract is switched to management_fee
// revenue, so stale values from a previous selection can't linger and get submitted.
watch(
  () => form.revenue_type,
  (type) => {
    if (type === 'management_fee') {
      form.has_management_fees = false
      form.management_fee_expense_rate = ''
    }
  }
)

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatMoney(v) {
  if (!v) return '0'
  return parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function submit() {
  if (isLocked.value) return // form is disabled via fieldset; this just guards against any programmatic bypass

  // Management Fee Revenue (external owner) and Management Fees Expense
  // (we own it, external manager) are mutually exclusive — never submit both.
  if (form.revenue_type === 'management_fee' || !form.has_management_fees) {
    form.has_management_fees = false
    form.management_fee_expense_rate = ''
  }

  form.annual_increase_schedule = (form.annual_increase_schedule || []).map((row) => ({
    year: Number(row.year),
    rate: Number(row.rate) || 0,
  }))

  if (isEdit.value) {
    form.put(route('company.properties.contracts.update', [
      props.company.id,
      props.property.id,
      props.contract.id
    ]))
  } else {
    form.post(route('company.properties.contracts.store', [props.company.id, props.property.id]))
  }
}
</script>