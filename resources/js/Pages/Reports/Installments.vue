<template>
  <AuthenticatedLayout title="Installments Detail">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Installments Detail</h1>
          <p class="text-xs fv-text-muted mt-1">All installment due rows for a selected period</p>
        </div>
        <Link :href="route('company.reports.index', company.id)" class="fv-btn-secondary text-xs px-3 py-1.5 rounded-lg">
          ← Back to Reports
        </Link>
      </div>

      <!-- Controls -->
      <div class="fv-card p-4 flex flex-wrap gap-4 items-end">
        <div class="flex flex-col gap-1">
          <label class="fv-text-label">Start Date</label>
          <input type="date" v-model="startDate" class="fv-input text-sm rounded-lg px-3 py-2" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="fv-text-label">End Date</label>
          <input type="date" v-model="endDate" class="fv-input text-sm rounded-lg px-3 py-2" />
        </div>
        <button @click="runReport" :disabled="loading"
          class="fv-btn-gold text-sm px-5 py-2 rounded-lg disabled:opacity-40">
          {{ loading ? 'Loading…' : 'Run Report' }}
        </button>
      </div>

      <!-- Error -->
      <div v-if="error" class="fv-card p-4 text-sm" style="color:#f87171; border-color:rgba(248,113,113,0.3);">
        {{ error }}
      </div>

      <!-- Unconverted-rate warning -->
      <div v-if="result && result.unconverted_count" class="fv-card p-3 text-xs flex items-center gap-2"
        style="color:#FAC775; border-color:rgba(186,117,23,0.3); background:rgba(186,117,23,0.06);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        {{ result.unconverted_count }} row{{ result.unconverted_count === 1 ? '' : 's' }} excluded from totals below — no FX rate on file for that currency yet.
      </div>

      <!-- Summary strip -->
      <div v-if="result">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs fv-text-muted">Totals shown in base currency</span>
          <span class="fv-tag-gold text-xs">{{ result.base_currency }}</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-blue);">
            <p class="text-xs fv-text-muted">Total Schedule</p>
            <p class="text-lg font-bold fv-text-primary mt-1">{{ fmt(result.total_due) }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid #34d399;">
            <p class="text-xs fv-text-muted">Paid</p>
            <p class="text-lg font-bold mt-1" style="color:#6ee7b7;">{{ fmt(result.total_paid) }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-gold);">
            <p class="text-xs fv-text-muted">Pending</p>
            <p class="text-lg font-bold mt-1" style="color:#FAC775;">{{ fmt(result.total_pending) }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid #f87171;">
            <p class="text-xs fv-text-muted">Overdue</p>
            <p class="text-lg font-bold mt-1" style="color:#f87171;">{{ fmt(result.total_overdue) }}</p>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div v-if="result" class="fv-card !p-0 overflow-hidden">
        <div class="overflow-auto" style="max-height:200vh;">
          <table class="w-full text-sm">
            <thead>
              <tr>
                <th class="fv-th text-left">Due Date</th>
                <th class="fv-th text-left">Property</th>
                <th class="fv-th text-left">Type</th>
                <th class="fv-th text-left">Nature</th>
                <th class="fv-th text-right">Amount</th>
                <th class="fv-th text-left">Status</th>
                <th class="fv-th text-left">Paid Date</th>
                <th class="fv-th text-left">Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in result.rows" :key="row.id" class="fv-tr">
                <td class="fv-td whitespace-nowrap">{{ row.due_date }}</td>
                <td class="fv-td font-medium fv-text-primary">{{ row.property_name }}</td>
                <td class="fv-td fv-text-muted capitalize">{{ row.due_type }}</td>
                <td class="fv-td fv-text-muted capitalize">{{ row.property_nature }}</td>
                <td class="fv-td text-right font-semibold fv-text-primary whitespace-nowrap">
                  {{ fmt(row.amount) }} {{ row.currency }}
                </td>
                <td class="fv-td">
                  <span class="fv-badge" :style="statusStyle(row.status)">{{ row.status }}</span>
                </td>
                <td class="fv-td fv-text-muted">{{ row.paid_date ?? '—' }}</td>
                <td class="fv-td fv-text-muted">{{ row.notes ?? '' }}</td>
              </tr>
              <tr v-if="!result.rows.length">
                <td colspan="8" class="fv-td text-center fv-text-muted py-10">
                  No installment dues found for the selected period.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.fv-th {
  padding: 0.75rem 1rem;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--fv-text-label);
  background: var(--fv-bg-header);
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 1;
  border-bottom: 1px solid var(--fv-border);
}
.fv-td {
  padding: 0.65rem 1rem;
  font-size: 0.8rem;
  color: var(--fv-text-primary);
}
.fv-tr { border-bottom: 1px solid var(--fv-border); transition: background-color 0.12s ease; }
.fv-tr:nth-child(even) { background: var(--fv-bg-card); }
.fv-tr:nth-child(odd)  { background: var(--fv-bg); }
.fv-tr:hover { background: var(--fv-bg-hover) !important; }
</style>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:          { type: Object, required: true },
  defaultStartDate: { type: String, required: true },
  defaultEndDate:   { type: String, required: true },
})

const startDate = ref(props.defaultStartDate)
const endDate   = ref(props.defaultEndDate)
const loading   = ref(false)
const error     = ref(null)
const result    = ref(null)

async function runReport() {
  loading.value = true
  error.value   = null
  result.value  = null

  try {
    const url = route('company.reports.installments.data', { company: props.company.id })
      + `?start_date=${startDate.value}&end_date=${endDate.value}`

    const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!res.ok) { error.value = `Error ${res.status}`; return }
    result.value = await res.json()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function fmt(val) {
  if (val == null) return '—'
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusStyle(s) {
  if (s === 'paid')    return 'background:rgba(16,185,129,0.15);color:#6ee7b7;'
  if (s === 'overdue') return 'background:rgba(248,113,113,0.15);color:#f87171;'
  return 'background:rgba(186,117,23,0.15);color:#FAC775;'
}
</script>
