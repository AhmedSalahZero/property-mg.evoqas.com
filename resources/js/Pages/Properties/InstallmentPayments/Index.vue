<template>
  <AuthenticatedLayout title="Properties Installment Payment">
    <div class="p-6 space-y-6">

      <!-- ── Page Header ─────────────────────────────────────────── -->
      <div>
        <h1 class="text-lg font-bold fv-text-primary">Properties Installment Payment</h1>
        <p class="text-xs fv-text-muted mt-0.5">Outstanding developer installment dues across every property — pending &amp; overdue only.</p>
      </div>

      <!-- ── Bucket Summary ──────────────────────────────────────── -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="fv-card !p-4 space-y-1" style="border:1px solid rgba(239,68,68,0.3);">
          <p class="text-xs fv-text-muted">Overdue</p>
          <p class="text-base font-bold" style="color:#f87171">{{ fmt(buckets.overdue) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">{{ bucketLabels.this_month || 'This Month' }}</p>
          <p class="text-base font-bold fv-text-primary">{{ fmt(buckets.this_month) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">{{ bucketLabels.next_month || 'Next Month' }}</p>
          <p class="text-base font-bold fv-text-primary">{{ fmt(buckets.next_month) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">{{ bucketLabels.plus_2_months || '+2 Months' }}</p>
          <p class="text-base font-bold fv-text-primary">{{ fmt(buckets.plus_2_months) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">{{ bucketLabels.plus_3_months || '+3 Months' }}</p>
          <p class="text-base font-bold fv-text-primary">{{ fmt(buckets.plus_3_months) }}</p>
        </div>
      </div>
      <p class="text-xs fv-text-muted -mt-4">Amounts in {{ baseCurrency }} (base currency).
        <span v-if="unconvertedCount > 0">{{ unconvertedCount }} row(s) awaiting an FX rate are excluded from these totals.</span>
      </p>

      <!-- ── Filters ─────────────────────────────────────────────── -->
      <div class="fv-card !p-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-xs fv-text-label mb-1">Property</label>
          <select v-model="filters.property_id" @change="fetchData(1)" class="fv-select rounded-lg px-3 py-2 text-sm" style="min-width:220px">
            <option value="">All Properties</option>
            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.property_name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs fv-text-label mb-1">Type</label>
          <select v-model="filters.due_type" @change="fetchData(1)" class="fv-select rounded-lg px-3 py-2 text-sm" style="min-width:180px">
            <option value="">All Types</option>
            <option v-for="t in dueTypes" :key="t" :value="t">{{ typeLabel(t) }}</option>
          </select>
        </div>
        <button v-if="filters.property_id || filters.due_type" @click="clearFilters" class="fv-btn-secondary text-sm px-4 py-2 rounded-lg">
          Clear
        </button>
        <a :href="exportUrl" class="fv-btn-gold text-sm font-semibold px-4 py-2 rounded-lg ml-auto flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m0 0l-5-5m5 5l5-5M19 19H5"/>
          </svg>
          Export to Excel
        </a>
      </div>

      <!-- ── Table ───────────────────────────────────────────────── -->
      <div class="fv-card !p-0 overflow-hidden">
        <div v-if="loading" class="flex items-center justify-center py-16">
          <div class="w-6 h-6 border-2 border-t-transparent rounded-full animate-spin" style="border-color: var(--fv-blue)"></div>
        </div>
        <div v-else-if="!rows.length" class="text-center py-12 fv-text-muted text-sm">
          No outstanding installment dues found.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b fv-divider">
                <th class="text-left py-2.5 px-4 text-xs fv-text-label font-semibold">Property</th>
                <th class="text-left py-2.5 px-4 text-xs fv-text-label font-semibold">Type</th>
                <th class="text-left py-2.5 px-4 text-xs fv-text-label font-semibold">Due Date</th>
                <th class="text-right py-2.5 px-4 text-xs fv-text-label font-semibold">Amount</th>
                <th class="text-center py-2.5 px-4 text-xs fv-text-label font-semibold">Status</th>
                <th class="text-left py-2.5 px-4 text-xs fv-text-label font-semibold">Apply Payment</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" class="border-b fv-divider">
                <td class="py-2.5 px-4 fv-text-primary">{{ row.property_name }}</td>
                <td class="py-2.5 px-4 fv-text-muted capitalize">{{ typeLabel(row.due_type) }}</td>
                <td class="py-2.5 px-4 fv-text-primary">{{ formatDate(row.due_date) }}</td>
                <td class="py-2.5 px-4 text-right font-medium fv-text-primary">
                  {{ fmt(row.amount) }} <span class="text-xs fv-text-muted">{{ row.currency }}</span>
                </td>
                <td class="py-2.5 px-4 text-center">
                  <span class="fv-badge" :style="row.status === 'overdue' ? 'color:#ffffff' : 'color:var(--fv-gold)'">{{ row.status }}</span>
                </td>
                <td class="py-2.5 px-4">
                  <div class="flex items-center gap-2">
                    <input type="date" v-model="paidDates[row.id]" class="fv-input rounded-lg px-2 py-1.5 text-xs" style="width:9.5rem;" />
                    <button @click="markPaid(row)" :disabled="payingId === row.id"
                      class="fv-btn-gold text-xs font-semibold px-3 py-1.5 rounded-lg">
                      {{ payingId === row.id ? '…' : 'Mark Paid' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- ── Pagination ────────────────────────────────────────── -->
        <div v-if="!loading && rows.length" class="flex items-center justify-between px-4 py-3 border-t fv-divider">
          <p class="text-xs fv-text-muted">
            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
            of {{ pagination.total }}
          </p>
          <div class="flex items-center gap-2">
            <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
              class="fv-btn-secondary text-xs px-3 py-1.5 rounded-lg disabled:opacity-40">Prev</button>
            <span class="text-xs fv-text-muted">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
              class="fv-btn-secondary text-xs px-3 py-1.5 rounded-lg disabled:opacity-40">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
  company: { type: Object, required: true },
})

const loading   = ref(true)
const rows      = ref([])
const buckets   = ref({ overdue: 0, this_month: 0, next_month: 0, plus_2_months: 0, plus_3_months: 0 })
const bucketLabels = ref({})
const properties = ref([])
const dueTypes    = ref([])
const baseCurrency = ref('EGP')
const unconvertedCount = ref(0)
const paidDates  = ref({})   // row.id → date string, defaults to today
const payingId   = ref(null)
const pagination = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 })

const filters = ref({ property_id: '', due_type: '' })

const today = new Date().toISOString().slice(0, 10)

// Export always reflects the current filters but ignores pagination — the
// backend pulls the full filtered outstanding list for the spreadsheet.
const exportUrl = computed(() => {
  const params = new URLSearchParams()
  if (filters.value.property_id) params.set('property_id', filters.value.property_id)
  if (filters.value.due_type) params.set('due_type', filters.value.due_type)
  const qs = params.toString()
  return route('company.properties.installment-payments.export', props.company.id) + (qs ? `?${qs}` : '')
})

onMounted(fetchData)

async function fetchData(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get(
      route('company.properties.installment-payments.data', props.company.id),
      { params: {
        property_id: filters.value.property_id || undefined,
        due_type: filters.value.due_type || undefined,
        page,
      } }
    )
    rows.value = data.rows ?? []
    buckets.value = data.buckets ?? buckets.value
    bucketLabels.value = data.bucket_labels ?? {}
    properties.value = data.properties ?? []
    dueTypes.value = data.due_types ?? []
    baseCurrency.value = data.base_currency ?? 'EGP'
    unconvertedCount.value = data.unconverted_count ?? 0
    pagination.value = data.pagination ?? pagination.value

    for (const row of rows.value) {
      if (!(row.id in paidDates.value)) paidDates.value[row.id] = today
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > pagination.value.last_page) return
  fetchData(page)
}

function clearFilters() {
  filters.value = { property_id: '', due_type: '' }
  fetchData(1)
}

// PropertyInstallmentController::markPaid returns JSON (unlike the rent
// collection equivalent), so this can go straight through axios — then
// refetch the same page so the row drops off the list and bucket totals
// stay correct without jumping the user back to page 1.
async function markPaid(row) {
  payingId.value = row.id
  try {
    await axios.patch(
      route('company.properties.installments.mark-paid', [props.company.id, row.property_id, row.id]),
      { paid_date: paidDates.value[row.id] || today }
    )
    await fetchData(pagination.value.current_page)
  } catch (e) {
    alert('Could not mark this installment as paid.')
  } finally {
    payingId.value = null
  }
}

function fmt(val) {
  return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function typeLabel(t) {
  const map = {
    signing: 'Contract Signing', reservation: 'Reservation',
    installment: 'Installment', annual: 'Annual',
    delivery: 'Delivery', maintenance: 'Maintenance', variable: 'Payment',
  }
  return map[t] ?? t
}
</script>
