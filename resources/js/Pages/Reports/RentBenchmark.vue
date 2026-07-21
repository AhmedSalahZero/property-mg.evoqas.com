<template>
  <AuthenticatedLayout title="Rent Benchmark">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Rent Benchmark</h1>
          <p class="text-xs fv-text-muted mt-1">Compare monthly rent across units of the same type and flag under-market contracts</p>
        </div>
        <Link :href="route('company.reports.index', company.id)" class="fv-btn-secondary text-xs px-3 py-1.5 rounded-lg">
          ← Back to Reports
        </Link>
      </div>

      <!-- Controls -->
      <div class="fv-card p-4 flex flex-wrap gap-4 items-end">
        <div class="flex flex-col gap-1">
          <label class="fv-text-label">Property Type</label>
          <select v-model="typeId" class="fv-select text-sm rounded-lg px-3 py-2 w-48">
            <option value="">— Select type —</option>
            <option v-for="t in types" :key="t.type_id" :value="t.type_id">{{ t.type_name }}</option>
          </select>
        </div>
        <div class="flex flex-col gap-1">
          <label class="fv-text-label">Flag Threshold (%)</label>
          <div class="flex items-center gap-2">
            <input type="number" v-model.number="threshold" min="1" max="100"
              class="fv-input text-sm rounded-lg px-3 py-2 w-24" />
            <span class="text-xs fv-text-muted">of province avg</span>
          </div>
        </div>
        <button @click="runReport" :disabled="loading || !typeId"
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
        {{ result.unconverted_count }} unit{{ result.unconverted_count === 1 ? '' : 's' }} have no FX rate on file and are excluded from the province comparison (marked "FX Rate Missing" below).
      </div>

      <template v-if="result">

        <!-- KPI strip -->
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs fv-text-muted">Comparisons run in base currency</span>
          <span class="fv-tag-gold text-xs">{{ result.base_currency }}</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-blue);">
            <p class="text-xs fv-text-muted">Type</p>
            <p class="text-sm font-bold fv-text-primary mt-1">{{ result.type_name }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-blue);">
            <p class="text-xs fv-text-muted">Running Contracts</p>
            <p class="text-lg font-bold fv-text-primary mt-1">{{ result.total }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid #f87171;">
            <p class="text-xs fv-text-muted">Flagged Units</p>
            <p class="text-lg font-bold mt-1" style="color:#f87171;">{{ result.flagged }}</p>
          </div>
          <div class="fv-card p-4 text-center" style="border-left:3px solid var(--fv-gold);">
            <p class="text-xs fv-text-muted">Threshold Applied</p>
            <p class="text-lg font-bold mt-1" style="color:#FAC775;">{{ result.threshold }}%</p>
          </div>
        </div>

        <!-- Summary by Governorate → Province -->
        <div v-if="result.summary.length" class="space-y-3">
          <h2 class="text-sm font-bold fv-text-primary">Summary by Location</h2>
          <div v-for="gov in result.summary" :key="gov.governorate" class="fv-card !p-0 overflow-hidden">
            <!-- Governorate header -->
            <div class="px-4 py-2.5 flex items-center gap-2"
              style="background:rgba(20,144,168,0.08); border-bottom:1px solid var(--fv-border);">
              <svg class="w-3.5 h-3.5" style="color:var(--fv-blue);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <span class="text-xs font-bold uppercase tracking-wide" style="color:var(--fv-blue);">{{ gov.governorate }}</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr>
                    <th class="fv-th text-left">Province</th>
                    <th class="fv-th text-right">Units</th>
                    <th class="fv-th text-right">Min Rent</th>
                    <th class="fv-th text-right">Max Rent</th>
                    <th class="fv-th text-right">Avg Rent</th>
                    <th class="fv-th text-right">Flagged</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="prov in gov.provinces" :key="prov.province" class="fv-tr">
                    <td class="fv-td font-medium fv-text-primary">{{ prov.province }}</td>
                    <td class="fv-td text-right fv-text-muted">{{ prov.count }}</td>
                    <td class="fv-td text-right" style="color:#6ee7b7; font-weight:600;">{{ fmt(prov.min_rent) }}</td>
                    <td class="fv-td text-right" style="color:var(--fv-gold); font-weight:600;">{{ fmt(prov.max_rent) }}</td>
                    <td class="fv-td text-right fv-text-primary font-semibold">{{ fmt(prov.avg_rent) }}</td>
                    <td class="fv-td text-right">
                      <span v-if="prov.flagged > 0" class="fv-badge"
                        style="background:rgba(248,113,113,0.15);color:#f87171;">
                        {{ prov.flagged }}
                      </span>
                      <span v-else class="fv-text-muted">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Detail table -->
        <div>
          <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
            <h2 class="text-sm font-bold fv-text-primary">Unit Detail</h2>
            <div class="flex items-center gap-2">
              <label class="text-xs fv-text-muted">Filter:</label>
              <select v-model="detailFilter" class="fv-select text-xs rounded-lg px-2 py-1">
                <option value="all">All Units</option>
                <option value="flagged">Flagged Only</option>
                <option value="ok">OK Only</option>
              </select>
            </div>
          </div>

          <div class="fv-card !p-0 overflow-hidden">
            <div class="overflow-auto" style="max-height:200vh;">
              <table class="w-full text-sm">
                <thead>
                  <tr>
                    <th class="fv-th text-left">Property / Unit</th>
                    <th class="fv-th text-left">Tenant</th>
                    <th class="fv-th text-left">Governorate</th>
                    <th class="fv-th text-left">Province</th>
                    <th class="fv-th text-right">Rent Basis</th>
                    <th class="fv-th text-right">Province Avg</th>
                    <th class="fv-th text-right">vs Avg</th>
                    <th class="fv-th text-left">Contract Period</th>
                    <th class="fv-th text-left">Recommendation</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in filteredRows" :key="row.contract_id" class="fv-tr">
                    <td class="fv-td font-medium fv-text-primary whitespace-nowrap">
                      {{ row.unit_name }}
                    </td>
                    <td class="fv-td fv-text-muted">{{ row.tenant_name }}</td>
                    <td class="fv-td fv-text-muted">{{ row.governorate }}</td>
                    <td class="fv-td fv-text-muted">{{ row.province }}</td>
                    <td class="fv-td text-right font-semibold fv-text-primary whitespace-nowrap">
                      {{ fmt(row.rent_basis) }} {{ row.currency }}
                    </td>
                    <td class="fv-td text-right fv-text-muted">
                      {{ row.rent_basis_base != null ? fmt(row.avg_province) : '—' }}
                    </td>
                    <td class="fv-td text-right font-semibold" :style="vsAvgStyle(row)">
                      {{ vsAvg(row) }}
                    </td>
                    <td class="fv-td fv-text-muted whitespace-nowrap">
                      {{ row.start_date }} → {{ row.end_date }}
                    </td>
                    <td class="fv-td">
                      <span class="fv-badge" :style="recStyle(row.recommendation)">
                        {{ row.recommendation }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!filteredRows.length">
                    <td colspan="9" class="fv-td text-center fv-text-muted py-10">
                      No units match the selected filter.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

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
}
.fv-tr { border-bottom: 1px solid var(--fv-border); transition: background-color 0.12s ease; }
.fv-tr:nth-child(even) { background: var(--fv-bg-card); }
.fv-tr:nth-child(odd)  { background: var(--fv-bg); }
.fv-tr:hover { background: var(--fv-bg-hover) !important; }
</style>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: { type: Object, required: true },
  types:   { type: Array,  required: true },
})

const typeId       = ref('')
const threshold    = ref(80)
const loading      = ref(false)
const error        = ref(null)
const result       = ref(null)
const detailFilter = ref('all')

async function runReport() {
  if (!typeId.value) return
  loading.value = true
  error.value   = null
  result.value  = null

  try {
    const url = route('company.reports.rent-benchmark.data', { company: props.company.id })
      + `?type_id=${typeId.value}&threshold=${threshold.value}`

    const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!res.ok) { error.value = `Error ${res.status}`; return }
    result.value = await res.json()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const filteredRows = computed(() => {
  if (!result.value) return []
  if (detailFilter.value === 'flagged') return result.value.rows.filter(r => r.recommendation === 'Correction Needed')
  if (detailFilter.value === 'ok')      return result.value.rows.filter(r => r.recommendation === 'OK')
  return result.value.rows
})

function fmt(val) {
  if (val == null) return '—'
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function vsAvg(row) {
  if (row.rent_basis_base == null) return 'N/A'
  if (!row.avg_province) return '—'
  const diff = ((row.rent_basis_base - row.avg_province) / row.avg_province) * 100
  return (diff >= 0 ? '+' : '') + diff.toFixed(1) + '%'
}

function vsAvgStyle(row) {
  if (row.rent_basis_base == null || !row.avg_province) return 'color:#6B96B8'
  const diff = row.rent_basis_base - row.avg_province
  if (diff >= 0) return 'color:#6ee7b7'
  return 'color:#f87171'
}

function recStyle(rec) {
  if (rec === 'Correction Needed') return 'background:rgba(248,113,113,0.15);color:#f87171;'
  if (rec === 'FX Rate Missing')   return 'background:rgba(100,116,139,0.15);color:#94A3B8;'
  return 'background:rgba(16,185,129,0.15);color:#6ee7b7;'
}
</script>
