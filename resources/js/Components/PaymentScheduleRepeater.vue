<!--
  Payment Schedule repeater — shared by Properties/Expenses/Index.vue and
  Properties/CorporateExpenses/Index.vue (both expense types use this
  identically, confirmed). Each row: % / auto-calculated Amount / Forecasted
  Date / a built-in Payment Term dropdown as a per-row shortcut.

  Picking a term for a row just fills in THAT row's date
  (expense_date + N days) — it does not touch any other row or regenerate
  the whole schedule. This is deliberate: the point of a repeater is
  supporting a genuine split (e.g. 40% deposit now, 60% balance Net 60), and
  a whole-schedule generator would make that harder, not easier, since a
  split payment often mixes different terms across its own rows.

  The actual 100%-total validation happens server-side (authoritative), but
  this component shows a live running total so the user sees the problem
  before they even try to save.
-->
<template>
  <div>
    <div class="flex items-center justify-between mb-2">
      <label class="text-xs fv-text-label font-semibold">
        Payment Schedule <span class="text-red-400">*</span>
      </label>
      <span class="text-xs font-semibold" :style="totalStyle">
        Total: {{ totalPercentage.toFixed(2) }}%
      </span>
    </div>

    <div v-if="modelValue.length === 0" class="text-xs fv-text-muted py-2">
      No schedule rows yet — add at least one.
    </div>

    <div v-for="(row, i) in modelValue" :key="i"
      class="grid gap-2 mb-2 items-end"
      style="grid-template-columns: 1fr 1fr 1fr 1fr auto">
      <div>
        <label v-if="i === 0" class="block text-xs fv-text-label mb-1">%</label>
        <input type="number" v-model="row.percentage" min="0.01" max="100" step="0.01"
          class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00"
          @input="onPercentageInput(row)" />
      </div>
      <div>
        <label v-if="i === 0" class="block text-xs fv-text-label mb-1">Amount</label>
        <input type="text" :value="fmtAmount(rowAmount(row))" disabled
          class="fv-input w-full rounded-lg px-3 py-2 text-sm opacity-70" />
      </div>
      <div>
        <label v-if="i === 0" class="block text-xs fv-text-label mb-1">Term</label>
        <select v-model="row.payment_term" class="fv-select w-full rounded-lg px-3 py-2 text-sm"
          @change="onTermSelected(row)">
          <option value="">— Pick a date instead —</option>
          <option v-for="t in terms" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
      </div>
      <div>
        <label v-if="i === 0" class="block text-xs fv-text-label mb-1">Forecasted Date</label>
        <input type="date" v-model="row.forecasted_date"
          class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
      </div>
      <div class="flex items-end pb-0.5">
        <button type="button" @click="removeRow(i)" class="fv-action-btn fv-action-btn-danger">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <button type="button" @click="addRow" class="fv-btn-secondary text-xs px-3 py-1 rounded-lg flex items-center gap-1 mt-1">
      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Add Row
    </button>

    <p v-if="error" class="text-xs text-red-400 mt-2">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue:    { type: Array, required: true },   // [{ percentage, forecasted_date, payment_term }]
  expenseAmount: { type: [Number, String], default: 0 },
  expenseDate:   { type: String, default: '' },      // anchor a term counts forward from
  error:         { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

// Same built-in terms as ExpensePaymentScheduleService — kept in sync
// manually since the backend is the authoritative source (this is just the
// dropdown label list, the actual date math happens server-side too,
// mirrored here only so the date fills in immediately without a round trip).
//
// Fix 1 (Net 30 ≠ 30 days): standard business usage — Net 30/60/90/120/
// 150/180 mean N calendar months later (Jan 31 + Net 30 = Feb 28, not
// Mar 2), not literally N*30 days. Net 45 and Net 75 have no clean
// calendar-month equivalent ("a month and a half" isn't a real unit), so
// those two stay day-based — exactly matching dateForTerm() in
// ExpensePaymentScheduleService.php.
const TERM_MONTHS = { net_30: 1, net_60: 2, net_90: 3, net_120: 4, net_150: 5, net_180: 6 }
const TERM_DAYS   = { cash: 0, net_45: 45, net_75: 75 }
const terms = [
  { value: 'cash',    label: 'Cash (due immediately)' },
  { value: 'net_30',  label: 'Net 30' },
  { value: 'net_45',  label: 'Net 45' },
  { value: 'net_60',  label: 'Net 60' },
  { value: 'net_75',  label: 'Net 75' },
  { value: 'net_90',  label: 'Net 90' },
  { value: 'net_120', label: 'Net 120' },
  { value: 'net_150', label: 'Net 150' },
  { value: 'net_180', label: 'Net 180' },
]

function addRow() {
  emit('update:modelValue', [...props.modelValue, { percentage: '', forecasted_date: '', payment_term: '' }])
}
function removeRow(i) {
  const rows = [...props.modelValue]
  rows.splice(i, 1)
  emit('update:modelValue', rows)
}
function onPercentageInput(row) {
  // Just triggers reactivity for the total/amount displays — row is already
  // the live object inside modelValue (v-model on the array's own items),
  // so no explicit emit is needed here.
}

// Fix 2 (timezone off-by-one): the old code did
// `new Date(expenseDate + 'T00:00:00').toISOString()` — that constructs a
// LOCAL midnight Date, then formats it in UTC. In any timezone ahead of
// UTC (e.g. Egypt, UTC+2/+3), local midnight is still the previous day in
// UTC, so the formatted date silently lost a day — a Cash (0-day) term on
// 07/01 came out as 06/30. Fixed by staying entirely in UTC-constructed
// dates from end to end (Date.UTC + getUTC* accessors) — never mixing a
// locally-parsed Date with a UTC-formatted output.
function addMonthsNoOverflow(y, m, d, months) {
  const targetIndex = (m - 1) + months
  const targetYear  = y + Math.floor(targetIndex / 12)
  const targetMonth = ((targetIndex % 12) + 12) % 12 // 0-indexed
  const lastDayOfTargetMonth = new Date(Date.UTC(targetYear, targetMonth + 1, 0)).getUTCDate()
  const clampedDay = Math.min(d, lastDayOfTargetMonth)
  return `${targetYear}-${String(targetMonth + 1).padStart(2, '0')}-${String(clampedDay).padStart(2, '0')}`
}
function addDaysUTC(y, m, d, days) {
  const dt = new Date(Date.UTC(y, m - 1, d))
  dt.setUTCDate(dt.getUTCDate() + days)
  return `${dt.getUTCFullYear()}-${String(dt.getUTCMonth() + 1).padStart(2, '0')}-${String(dt.getUTCDate()).padStart(2, '0')}`
}
function onTermSelected(row) {
  if (!row.payment_term || !props.expenseDate) return
  const [y, m, d] = props.expenseDate.split('-').map(Number)
  const term = row.payment_term

  row.forecasted_date = (term in TERM_MONTHS)
    ? addMonthsNoOverflow(y, m, d, TERM_MONTHS[term])
    : addDaysUTC(y, m, d, TERM_DAYS[term] ?? 0)
}

function rowAmount(row) {
  const pct = parseFloat(row.percentage) || 0
  const total = parseFloat(props.expenseAmount) || 0
  return Math.round(total * pct) / 100
}
function fmtAmount(v) {
  return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const totalPercentage = computed(() =>
  props.modelValue.reduce((s, r) => s + (parseFloat(r.percentage) || 0), 0)
)
const totalStyle = computed(() => ({
  color: Math.abs(totalPercentage.value - 100) < 0.01 ? '#34d399' : '#f87171',
}))
</script>
