<template>
  <AuthenticatedLayout title="Annual Summary">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Annual Summary</h1>
          <p class="text-xs fv-text-muted mt-1">Full-year performance review — investor ready</p>
        </div>
        <Link :href="route('company.reports.index', company.id)" class="fv-btn-secondary text-xs px-3 py-1.5 rounded-lg">
          ← Back to Reports
        </Link>
      </div>

      <!-- Controls -->
      <div class="fv-card p-4 flex flex-wrap gap-4 items-end">
        <div class="flex flex-col gap-1">
          <label class="fv-text-label">Year</label>
          <input type="number" v-model.number="year" min="2000" max="2100"
            class="fv-input text-sm rounded-lg px-3 py-2 w-28" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="fv-text-label">Scope</label>
          <select v-model="scope" class="fv-select text-sm rounded-lg px-3 py-2">
            <option value="unit">Per Unit / Property</option>
            <option value="company">Whole Company (Single Row)</option>
          </select>
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

      <!-- Results -->
      <template v-if="result">

        <!-- Year badge -->
        <div class="flex items-center justify-between flex-wrap gap-2">
          <span class="text-xs font-bold px-3 py-1 rounded-full"
            style="background:rgba(16,185,129,0.15); color:#6ee7b7;">
            {{ result.year }} · {{ result.scope === 'unit' ? 'Per Unit' : 'Whole Company' }}
          </span>
          <span class="fv-tag-gold text-xs">Base: {{ result.base_currency }}</span>
        </div>

        <!-- Grand totals strip -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div class="fv-card p-4 text-center" style="border-left:3px solid #34d399;">
            <p class="text-xs fv-text-muted">Rent Collected</p>
            <p class="text-lg font-bold mt-1" style="color:#6ee7b7;">{{ fmt(result.grand_collected) }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid #f87171;">
            <p class="text-xs fv-text-muted">Expenses Paid</p>
            <p class="text-lg font-bold mt-1" style="color:#f87171;">{{ fmt(result.grand_expenses) }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-blue);">
            <p class="text-xs fv-text-muted">NOI</p>
            <p class="text-lg font-bold mt-1" :style="result.grand_noi >= 0 ? 'color:#6ee7b7' : 'color:#f87171'">
              {{ fmt(result.grand_noi) }}
            </p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-gold);">
            <p class="text-xs fv-text-muted">Market Value</p>
            <p class="text-lg font-bold fv-text-primary mt-1">
              {{ result.grand_mv > 0 ? fmt(result.grand_mv) : '—' }}
            </p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-gold);">
            <p class="text-xs fv-text-muted">Unrealized Gain</p>
            <p class="text-lg font-bold mt-1"
              :style="result.grand_unrealized > 0 ? 'color:#6ee7b7' : (result.grand_unrealized < 0 ? 'color:#f87171' : 'color:#6B96B8')">
              {{ result.grand_mv > 0 ? fmt(result.grand_unrealized) : '—' }}
            </p>
          </div>
        </div>

        <!-- Detail table -->
        <div class="fv-card !p-0 overflow-hidden">
          <div class="overflow-auto" style="max-height:65vh;">
            <table class="w-full text-sm">
              <thead>
                <tr>
                  <th class="fv-th text-left">Unit / Property</th>
                  <th class="fv-th text-left">Nature</th>
                  <th class="fv-th text-right">Acquisition Cost</th>
                  <th class="fv-th text-right">Rent Collected</th>
                  <th class="fv-th text-right">Expenses Paid</th>
                  <th class="fv-th text-right">NOI</th>
                  <th class="fv-th text-right">NOI Margin</th>
                  <th class="fv-th text-right">Market Value</th>
                  <th class="fv-th text-right">Unrealized Gain</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i" class="fv-tr">
                  <td class="fv-td font-medium fv-text-primary">{{ row.label }}</td>
                  <td class="fv-td fv-text-muted capitalize">{{ row.nature }}</td>
                  <td class="fv-td text-right fv-text-muted">
                    {{ row.acquisition_cost > 0 ? fmt(row.acquisition_cost) : '—' }}
                  </td>
                  <td class="fv-td text-right" style="color:#6ee7b7; font-weight:600;">
                    {{ fmt(row.total_collected) }}
                  </td>
                  <td class="fv-td text-right" style="color:#f87171; font-weight:600;">
                    {{ fmt(row.total_expenses) }}
                  </td>
                  <td class="fv-td text-right font-bold"
                    :style="row.noi >= 0 ? 'color:#6ee7b7' : 'color:#f87171'">
                    {{ fmt(row.noi) }}
                  </td>
                  <td class="fv-td text-right fv-text-muted">
                    {{ noiMargin(row) }}
                  </td>
                  <td class="fv-td text-right fv-text-primary">
                    {{ row.market_value != null ? fmt(row.market_value) : '—' }}
                  </td>
                  <td class="fv-td text-right font-semibold"
                    :style="row.unrealized_gain > 0 ? 'color:#6ee7b7' : (row.unrealized_gain < 0 ? 'color:#f87171' : 'color:#6B96B8')">
                    {{ row.unrealized_gain != null ? fmt(row.unrealized_gain) : '—' }}
                  </td>
                </tr>

                <tr v-if="!result.rows.length">
                  <td colspan="9" class="fv-td text-center fv-text-muted py-10">
                    No data found for {{ result.year }}.
                  </td>
                </tr>
              </tbody>

              <!-- Grand total footer row -->
              <tfoot v-if="result.rows.length">
                <tr style="border-top:2px solid var(--fv-gold); background:var(--fv-bg-header);">
                  <td class="fv-td font-bold fv-text-primary" colspan="3">TOTAL</td>
                  <td class="fv-td text-right font-bold" style="color:#6ee7b7;">{{ fmt(result.grand_collected) }}</td>
                  <td class="fv-td text-right font-bold" style="color:#f87171;">{{ fmt(result.grand_expenses) }}</td>
                  <td class="fv-td text-right font-bold"
                    :style="result.grand_noi >= 0 ? 'color:#6ee7b7' : 'color:#f87171'">
                    {{ fmt(result.grand_noi) }}
                  </td>
                  <td class="fv-td text-right fv-text-muted">
                    {{ grandMargin() }}
                  </td>
                  <td class="fv-td text-right font-bold fv-text-primary">
                    {{ result.grand_mv > 0 ? fmt(result.grand_mv) : '—' }}
                  </td>
                  <td class="fv-td text-right font-bold"
                    :style="result.grand_unrealized > 0 ? 'color:#6ee7b7' : (result.grand_unrealized < 0 ? 'color:#f87171' : 'color:#6B96B8')">
                    {{ result.grand_mv > 0 ? fmt(result.grand_unrealized) : '—' }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Footer note -->
        <p class="text-xs fv-text-muted">
          * Revenue = rent collections with status "collected" during {{ result.year }}.
          Expenses = expense payments made during {{ result.year }}.
          Market value = latest entry on record on or before December {{ result.year }}.
          Unrealized gain = market value minus acquisition cost.
          All money figures are shown in the company's base currency ({{ result.base_currency }}).
        </p>

      </template>

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
  white-space: nowrap;
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
  company:     { type: Object,  required: true },
  defaultYear: { type: Number,  required: true },
})

const year    = ref(props.defaultYear)
const scope   = ref('unit')
const loading = ref(false)
const error   = ref(null)
const result  = ref(null)

async function runReport() {
  loading.value = true
  error.value   = null
  result.value  = null

  try {
    const url = route('company.reports.annual-summary.data', { company: props.company.id })
      + `?year=${year.value}&scope=${scope.value}`

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

function noiMargin(row) {
  if (!row.total_collected || row.total_collected === 0) return '—'
  const m = (row.noi / row.total_collected) * 100
  return m.toFixed(1) + '%'
}

function grandMargin() {
  if (!result.value || !result.value.grand_collected || result.value.grand_collected === 0) return '—'
  const m = (result.value.grand_noi / result.value.grand_collected) * 100
  return m.toFixed(1) + '%'
}
</script>
