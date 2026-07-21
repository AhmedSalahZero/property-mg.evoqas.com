<template>
  <AuthenticatedLayout title="Tenant Ledger">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Tenant Ledger</h1>
          <p class="text-xs fv-text-muted mt-1">Full financial history per tenant</p>
        </div>
        <Link :href="route('company.reports.index', company.id)" class="fv-btn-secondary text-xs px-3 py-1.5 rounded-lg">
          ← Back to Reports
        </Link>
      </div>

      <!-- Controls -->
      <div class="fv-card p-4 flex flex-wrap gap-4 items-end">
        <!-- Tenant selector -->
        <div class="flex flex-col gap-1 min-w-[220px]">
          <label class="fv-text-label">Tenant</label>
          <select v-model="selectedTenantId" class="fv-select text-sm rounded-lg px-3 py-2">
            <option value="">— Select a tenant —</option>
            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.customer_name }}</option>
          </select>
        </div>

        <!-- Status filter -->
        <div class="flex flex-col gap-1">
          <label class="fv-text-label">Contract Status</label>
          <select v-model="statusFilter" class="fv-select text-sm rounded-lg px-3 py-2">
            <option value="all">All Statuses</option>
            <option value="running">Running</option>
            <option value="expired">Expired</option>
            <option value="terminated">Terminated</option>
          </select>
        </div>

        <!-- Run button -->
        <button @click="runReport"
          :disabled="!selectedTenantId || loading"
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
        {{ result.unconverted_count }} collection row{{ result.unconverted_count === 1 ? '' : 's' }} excluded from totals below — no FX rate on file for that currency yet.
      </div>

      <!-- Results -->
      <template v-if="result">

        <!-- Tenant name + Grand totals strip -->
        <div class="fv-card p-4" style="border-left:3px solid var(--fv-gold);">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
              <span class="text-xs fv-text-muted uppercase tracking-widest">Tenant</span>
              <p class="fv-text-primary font-bold text-base mt-0.5">{{ result.tenant.name }}</p>
            </div>
            <div class="flex items-center gap-4">
              <span class="fv-tag-gold text-xs">Base: {{ result.base_currency }}</span>
              <div class="flex flex-wrap gap-3">
                <div class="text-center">
                  <p class="text-xs fv-text-muted">Total Due</p>
                  <p class="text-sm font-bold fv-text-primary">{{ fmt(result.grand_total_due) }}</p>
                </div>
                <div class="text-center">
                  <p class="text-xs fv-text-muted">Total Collected</p>
                  <p class="text-sm font-bold" style="color:#6ee7b7;">{{ fmt(result.grand_total_collected) }}</p>
                </div>
                <div class="text-center">
                  <p class="text-xs fv-text-muted">Outstanding</p>
                  <p class="text-sm font-bold" :style="result.grand_outstanding > 0 ? 'color:#f87171' : 'color:#6ee7b7'">
                    {{ fmt(result.grand_outstanding) }}
                  </p>
                </div>
                <div class="text-center">
                  <p class="text-xs fv-text-muted">Insurance Held</p>
                  <p class="text-sm font-bold" style="color:#FAC775;">{{ fmt(result.grand_insurance) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- One section per contract -->
        <div v-for="contract in result.contracts" :key="contract.id" class="fv-card !p-0 overflow-hidden">

          <!-- Contract header -->
          <div class="p-4 border-b flex flex-wrap items-start justify-between gap-3" style="border-color:var(--fv-border);">
            <div>
              <p class="fv-text-primary font-semibold text-sm">{{ contract.unit_label }}</p>
              <p class="text-xs fv-text-muted mt-0.5">
                {{ contract.start_date }} → {{ contract.end_date }}
                &nbsp;·&nbsp;
                {{ contract.collection_interval_months }}m collections
                &nbsp;·&nbsp;
                {{ contract.contract_currency }}
              </p>
              <p class="text-xs mt-1.5">
                <span class="fv-badge" :style="statusStyle(contract.status)">{{ contract.status }}</span>
                <span class="ml-2 fv-tag">{{ contract.revenue_type === 'direct_rent' ? 'Direct Rent' : 'Mgmt Fee' }}</span>
              </p>
            </div>
            <div class="flex flex-wrap gap-4 text-right">
              <div>
                <p class="text-xs fv-text-muted">Rent Basis</p>
                <p class="text-sm font-semibold fv-text-primary">
                  {{ fmt(contract.min_monthly_rent ?? contract.monthly_rent_amount) }}/mo
                </p>
              </div>
              <div v-if="contract.insurance_amount > 0">
                <p class="text-xs fv-text-muted">Insurance</p>
                <p class="text-sm font-semibold" style="color:#FAC775;">{{ fmt(contract.insurance_amount) }}</p>
              </div>
              <div>
                <p class="text-xs fv-text-muted">Due</p>
                <p class="text-sm font-semibold fv-text-primary">{{ fmt(contract.total_due) }}</p>
              </div>
              <div>
                <p class="text-xs fv-text-muted">Collected</p>
                <p class="text-sm font-semibold" style="color:#6ee7b7;">{{ fmt(contract.total_collected) }}</p>
              </div>
              <div>
                <p class="text-xs fv-text-muted">Outstanding</p>
                <p class="text-sm font-semibold" :style="contract.outstanding > 0 ? 'color:#f87171' : 'color:#6ee7b7'">
                  {{ fmt(contract.outstanding) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Collections table -->
          <div class="overflow-auto" style="max-height:200vh;">
            <table class="w-full text-sm">
              <thead>
                <tr>
                  <th class="fv-th text-left">Due Date</th>
                  <th class="fv-th text-left">Period</th>
                  <th class="fv-th text-right">Amount Due</th>
                  <th class="fv-th text-left">Status</th>
                  <th class="fv-th text-left">Collected Date</th>
                  <th class="fv-th text-left">Notes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="col in contract.collections" :key="col.id" class="fv-tr">
                  <td class="fv-td whitespace-nowrap">{{ col.collection_date }}</td>
                  <td class="fv-td fv-text-muted whitespace-nowrap">{{ col.period_from }} → {{ col.period_to }}</td>
                  <td class="fv-td text-right font-medium fv-text-primary whitespace-nowrap">
                    {{ fmt(col.collection_amount) }} {{ col.currency }}
                  </td>
                  <td class="fv-td">
                    <span class="fv-badge" :style="collectionStatusStyle(col.status)">{{ col.status }}</span>
                  </td>
                  <td class="fv-td fv-text-muted">{{ col.collected_date ?? '—' }}</td>
                  <td class="fv-td fv-text-muted">{{ col.notes ?? '' }}</td>
                </tr>
                <tr v-if="!contract.collections.length">
                  <td colspan="6" class="fv-td text-center fv-text-muted py-6">No collections</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

        <!-- No contracts message -->
        <div v-if="!result.contracts.length" class="fv-card p-8 text-center fv-text-muted text-sm">
          No contracts found for this tenant with the selected filter.
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
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: { type: Object, required: true },
  tenants: { type: Array, required: true },
})

const selectedTenantId = ref('')
const statusFilter     = ref('all')
const loading          = ref(false)
const error            = ref(null)
const result           = ref(null)

async function runReport() {
  if (!selectedTenantId.value) return
  loading.value = true
  error.value   = null
  result.value  = null

  try {
    const url = route('company.reports.tenant-ledger.data', { company: props.company.id })
      + `?tenant_id=${selectedTenantId.value}&status=${statusFilter.value}`

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
  if (s === 'running')    return 'background:rgba(16,185,129,0.15);color:#6ee7b7;'
  if (s === 'expired')    return 'background:rgba(107,150,184,0.15);color:#6B96B8;'
  if (s === 'terminated') return 'background:rgba(248,113,113,0.15);color:#f87171;'
  return ''
}

function collectionStatusStyle(s) {
  if (s === 'collected') return 'background:rgba(16,185,129,0.15);color:#6ee7b7;'
  if (s === 'overdue')   return 'background:rgba(248,113,113,0.15);color:#f87171;'
  return 'background:rgba(186,117,23,0.15);color:#FAC775;'
}
</script>
