<script setup>
import { computed, reactive, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: { type: Object, required: true },
  property: { type: Object, required: true },
  defaultStartDate: { type: String, required: true },
  defaultEndDate: { type: String, required: true },
  baseCurrency: { type: String, default: 'EGP' },
})

const startDate = ref(props.defaultStartDate)
const endDate = ref(props.defaultEndDate)
const loading = ref(false)
const loaded = ref(false)
const activeTab = ref('rent-expenses')
const months = ref([])

// Accrual basis — "Rent / Expenses Report" tab (confirmed July 2026 session:
// shows what's COMMITTED, paid or not — a fresh unpaid expense shows here
// immediately).
const rentAccrualByMonth = ref({})
const directAccrualByMonth = ref({})
const corporateAccrualByMonth = ref({})
const totalExpensesAccrualByMonth = ref({})

// Cash basis — "Cashflow Report" tab (fix for audit H3, unchanged): only
// money that has actually moved.
const rentCashByMonth = ref({})
const directCashByMonth = ref({})
const corporateCashByMonth = ref({})
const totalExpensesCashByMonth = ref({})
const cashflowByMonth = ref({})
const accumulatedByMonth = ref({})

const errorMessage = ref('')

// ── Currency view ──────────────────────────────────────────────────────
const viewCurrency = ref('')
const availableCurrencies = ref([])
const isFunctionalView = ref(true)
const unconvertedCurrencies = ref([])

const monthName = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const fmtMonth = (ym) => {
  if (!ym) return ''
  const [y, m] = ym.split('-')
  return `${monthName[Number(m) - 1]} ${y}`
}
const fmtDate = (d) => {
  if (!d) return '—'
  const dt = new Date(String(d).slice(0, 10) + 'T00:00:00')
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
const n = (v) => Number(v || 0)
const fmt = (v) => {
  const val = Number(v || 0)
  if (!val) return '—'
  return val.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
const fmt2 = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const sumMonths = (obj) => months.value.reduce((sum, m) => sum + n(obj[m]), 0)

const totalRentAccrual      = computed(() => sumMonths(rentAccrualByMonth.value))
const totalDirectAccrual    = computed(() => sumMonths(directAccrualByMonth.value))
const totalCorporateAccrual = computed(() => sumMonths(corporateAccrualByMonth.value))
const totalExpensesAccrual  = computed(() => sumMonths(totalExpensesAccrualByMonth.value))

const totalRentCash      = computed(() => sumMonths(rentCashByMonth.value))
const totalDirectCash    = computed(() => sumMonths(directCashByMonth.value))
const totalCorporateCash = computed(() => sumMonths(corporateCashByMonth.value))
const totalExpensesCash  = computed(() => sumMonths(totalExpensesCashByMonth.value))

async function submitReport() {
  if (!startDate.value || !endDate.value) return
  if (startDate.value > endDate.value) {
    errorMessage.value = 'Start date must be before or equal to end date.'
    return
  }

  loading.value = true
  errorMessage.value = ''
  // A new date range or currency invalidates any cached expand detail.
  Object.keys(expandedRows).forEach(k => delete expandedRows[k])
  Object.keys(detailCache).forEach(k => delete detailCache[k])

  try {
    const currencyParam = viewCurrency.value ? `&currency=${viewCurrency.value}` : ''
    const url = route('company.properties.reports.rent-expenses.data', [props.company.id, props.property.id])
      + `?start_date=${startDate.value}&end_date=${endDate.value}${currencyParam}`
    const res = await fetch(url, {
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    if (!res.ok) {
      const text = await res.text()
      throw new Error(text || `HTTP ${res.status}`)
    }
    const data = await res.json()
    months.value = data.months || []

    rentAccrualByMonth.value = data.rentAccrualByMonth || {}
    directAccrualByMonth.value = data.directExpensesAccrualByMonth || {}
    corporateAccrualByMonth.value = data.corporateExpensesAccrualByMonth || {}
    totalExpensesAccrualByMonth.value = data.totalExpensesAccrualByMonth || {}

    rentCashByMonth.value = data.rentCashByMonth || {}
    directCashByMonth.value = data.directExpensesCashByMonth || {}
    corporateCashByMonth.value = data.corporateExpensesCashByMonth || {}
    totalExpensesCashByMonth.value = data.totalExpensesCashByMonth || {}
    cashflowByMonth.value = data.cashflowByMonth || {}
    accumulatedByMonth.value = data.accumulatedByMonth || {}

    availableCurrencies.value = data.availableCurrencies || []
    isFunctionalView.value = data.isFunctionalView ?? true
    unconvertedCurrencies.value = data.unconvertedCurrencies || []
    loaded.value = true
  } catch (err) {
    errorMessage.value = 'Failed to load report data.'
    console.error(err)
  } finally {
    loading.value = false
  }
}

// ── Father/Son expand — line-item detail fetched on demand ─────────────
// Never eager-loaded with the summary above; same lazy pattern used by
// Corporate Expenses' own allocation breakdown. Key = `${basis}-${source}`.
const expandedRows  = reactive({})
const detailCache   = reactive({})
const detailLoading = reactive({})

async function toggleDetail(basis, source) {
  const key = `${basis}-${source}`
  expandedRows[key] = !expandedRows[key]
  if (!expandedRows[key] || detailCache[key]) return

  detailLoading[key] = true
  try {
    const params = new URLSearchParams({ source, basis, start_date: startDate.value, end_date: endDate.value })
    if (viewCurrency.value) params.set('currency', viewCurrency.value)
    const url = route('company.properties.reports.rent-expenses.detail', [props.company.id, props.property.id]) + '?' + params.toString()
    const res = await fetch(url, { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    const data = await res.json()
    detailCache[key] = data.rows || []
  } catch (err) {
    detailCache[key] = []
  } finally {
    detailLoading[key] = false
  }
}
</script>

<template>
  <AuthenticatedLayout :title="`Rent / Expenses Report - ${property.property_name}`">
    <div class="p-6 space-y-5">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Rent / Expenses Report</h1>
          <p class="text-xs fv-text-muted mt-1">{{ property.property_name }}</p>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="route('company.properties.reports.index', [company.id, property.id])" class="btn-sm btn-ghost">
            Back To Reports
          </Link>
        </div>
      </div>

      <div class="fv-card p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div>
            <label class="text-xs fv-text-muted block mb-1">Currency</label>
            <select v-model="viewCurrency" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
              <option value="">{{ baseCurrency }} (Functional)</option>
              <option v-for="c in availableCurrencies.filter(c => c !== baseCurrency)" :key="c" :value="c">
                {{ c }} only
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs fv-text-muted block mb-1">Start Date</label>
            <input v-model="startDate" type="date" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs fv-text-muted block mb-1">End Date</label>
            <input v-model="endDate" type="date" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="md:col-span-2">
            <button
              :disabled="loading"
              @click="submitReport"
              class="btn-sm btn-teal"
            >
              {{ loading ? 'Loading...' : 'Submit' }}
            </button>
          </div>
        </div>
        <p v-if="errorMessage" class="text-xs mt-2" style="color:#f87171;">{{ errorMessage }}</p>
      </div>

      <div v-if="loaded && !isFunctionalView" class="px-4 py-2.5 rounded-lg text-xs"
        style="background:rgba(186,117,23,0.1); border:1px solid rgba(186,117,23,0.3); color:#BA7517;">
        Showing <strong>{{ viewCurrency }} only</strong> — raw, unconverted figures in this currency alone.
      </div>
      <div v-if="loaded && isFunctionalView && unconvertedCurrencies.length" class="px-4 py-2.5 rounded-lg text-xs"
        style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#f87171;">
        💱 {{ unconvertedCurrencies.join(', ') }} {{ unconvertedCurrencies.length > 1 ? 'have' : 'has' }} no
        exchange rate on file — those amounts are excluded from the totals below.
      </div>

      <div v-if="loaded" class="space-y-4">
        <div class="flex items-center gap-2">
          <button
            class="px-3 py-1.5 rounded-lg text-sm"
            :class="activeTab === 'rent-expenses' ? 'tab-active' : 'tab-inactive'"
            @click="activeTab = 'rent-expenses'"
          >
            Rent / Expenses Report
            <span class="text-[10px] block opacity-75">Accrual — committed, paid or not</span>
          </button>
          <button
            class="px-3 py-1.5 rounded-lg text-sm"
            :class="activeTab === 'cashflow' ? 'tab-active' : 'tab-inactive'"
            @click="activeTab = 'cashflow'"
          >
            Cashflow Report
            <span class="text-[10px] block opacity-75">Cash — actually paid only</span>
          </button>
        </div>

        <!-- ══════════════════════════════════════════════════════════
             ACCRUAL TAB — Rent / Expenses Report
        ══════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'rent-expenses'" class="fv-card overflow-x-auto">
          <table class="w-full min-w-max">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border); background:rgba(11,26,48,0.6);">
                <th class="text-left px-4 py-3 text-xs text-amber-400 uppercase"></th>
                <th class="text-left px-4 py-3 text-xs text-amber-400 uppercase">Item</th>
                <th v-for="m in months" :key="m" class="text-center px-4 py-3 text-xs text-amber-400 uppercase">
                  {{ fmtMonth(m) }}
                </th>
                <th class="text-right px-4 py-3 text-xs text-amber-400 uppercase">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid var(--fv-border);">
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-sm fv-text-primary">Rent Accrued</td>
                <td v-for="m in months" :key="`r-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(rentAccrualByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#4ade80;">
                  {{ fmt(totalRentAccrual) }}
                </td>
              </tr>

              <!-- Direct Expenses — expandable -->
              <tr style="border-bottom:1px solid var(--fv-border); cursor:pointer;" @click="toggleDetail('accrual', 'direct')">
                <td class="px-4 py-3">
                  <svg class="w-3.5 h-3.5 transition-transform fv-text-muted" :style="{ transform: expandedRows['accrual-direct'] ? 'rotate(90deg)' : 'none' }"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </td>
                <td class="px-4 py-3 text-sm fv-text-primary">Direct Expenses</td>
                <td v-for="m in months" :key="`de-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(directAccrualByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#f87171;">
                  {{ fmt(totalDirectAccrual) }}
                </td>
              </tr>
              <tr v-if="expandedRows['accrual-direct']" style="border-bottom:1px solid var(--fv-border);">
                <td :colspan="months.length + 3" class="px-4 py-3" style="background:var(--fv-bg-input)">
                  <p v-if="detailLoading['accrual-direct']" class="text-xs fv-text-muted">Loading…</p>
                  <p v-else-if="!detailCache['accrual-direct']?.length" class="text-xs fv-text-muted">No direct expenses in this period.</p>
                  <table v-else class="w-full text-xs">
                    <thead>
                      <tr class="fv-text-muted">
                        <th class="text-left py-1 font-medium">Category / Item</th>
                        <th class="text-left py-1 font-medium">Date</th>
                        <th class="text-right py-1 font-medium">Amount</th>
                        <th class="text-right py-1 font-medium">Paid</th>
                        <th class="text-right py-1 font-medium">Balance</th>
                        <th class="text-center py-1 font-medium">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(r, i) in detailCache['accrual-direct']" :key="i">
                        <td class="py-1 fv-text-primary">{{ r.category }} <span class="fv-text-muted">— {{ r.item }}</span></td>
                        <td class="py-1 fv-text-muted">{{ fmtDate(r.date) }}</td>
                        <td class="py-1 text-right fv-text-primary">{{ r.currency }} {{ fmt2(r.amount) }}</td>
                        <td class="py-1 text-right" style="color:#34d399">{{ fmt2(r.paid) }}</td>
                        <td class="py-1 text-right" style="color:#f87171">{{ fmt2(r.balance) }}</td>
                        <td class="py-1 text-center fv-text-muted capitalize">{{ (r.status || '').replace('_',' ') }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>

              <!-- Corporate Expenses — expandable -->
              <tr style="border-bottom:1px solid var(--fv-border); cursor:pointer;" @click="toggleDetail('accrual', 'corporate')">
                <td class="px-4 py-3">
                  <svg class="w-3.5 h-3.5 transition-transform fv-text-muted" :style="{ transform: expandedRows['accrual-corporate'] ? 'rotate(90deg)' : 'none' }"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </td>
                <td class="px-4 py-3 text-sm fv-text-primary">
                  Corporate Expenses
                  <span class="text-[10px] fv-text-muted block">(allocated share)</span>
                </td>
                <td v-for="m in months" :key="`ce-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(corporateAccrualByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#f87171;">
                  {{ fmt(totalCorporateAccrual) }}
                </td>
              </tr>
              <tr v-if="expandedRows['accrual-corporate']" style="border-bottom:1px solid var(--fv-border);">
                <td :colspan="months.length + 3" class="px-4 py-3" style="background:var(--fv-bg-input)">
                  <p v-if="detailLoading['accrual-corporate']" class="text-xs fv-text-muted">Loading…</p>
                  <p v-else-if="!detailCache['accrual-corporate']?.length" class="text-xs fv-text-muted">No corporate expenses allocated to this unit in this period.</p>
                  <table v-else class="w-full text-xs">
                    <thead>
                      <tr class="fv-text-muted">
                        <th class="text-left py-1 font-medium">Category / Item</th>
                        <th class="text-left py-1 font-medium">Date</th>
                        <th class="text-right py-1 font-medium">Unit %</th>
                        <th class="text-right py-1 font-medium">Allocated Amount</th>
                        <th class="text-center py-1 font-medium">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(r, i) in detailCache['accrual-corporate']" :key="i">
                        <td class="py-1 fv-text-primary">{{ r.category }} <span class="fv-text-muted">— {{ r.item }}</span></td>
                        <td class="py-1 fv-text-muted">{{ fmtDate(r.date) }}</td>
                        <td class="py-1 text-right fv-text-muted">{{ r.allocation_pct.toFixed(2) }}%</td>
                        <td class="py-1 text-right fv-text-primary">{{ r.currency }} {{ fmt2(r.amount) }}</td>
                        <td class="py-1 text-center fv-text-muted capitalize">{{ (r.status || '').replace('_',' ') }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>

              <tr>
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-sm font-semibold fv-text-primary">Total Expenses</td>
                <td v-for="m in months" :key="`e-${m}`" class="px-4 py-3 text-center text-sm font-semibold fv-text-primary">
                  {{ fmt(totalExpensesAccrualByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#f87171;">
                  {{ fmt(totalExpensesAccrual) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ══════════════════════════════════════════════════════════
             CASH TAB — Cashflow Report
        ══════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'cashflow'" class="fv-card overflow-x-auto">
          <table class="w-full min-w-max">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border); background:rgba(11,26,48,0.6);">
                <th class="text-left px-4 py-3 text-xs text-amber-400 uppercase"></th>
                <th class="text-left px-4 py-3 text-xs text-amber-400 uppercase">Item</th>
                <th v-for="m in months" :key="`c-${m}`" class="text-center px-4 py-3 text-xs text-amber-400 uppercase">
                  {{ fmtMonth(m) }}
                </th>
                <th class="text-right px-4 py-3 text-xs text-amber-400 uppercase">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid var(--fv-border);">
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-sm fv-text-primary">Rent Collected</td>
                <td v-for="m in months" :key="`rc-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(rentCashByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#4ade80;">{{ fmt(totalRentCash) }}</td>
              </tr>

              <tr style="border-bottom:1px solid var(--fv-border); cursor:pointer;" @click="toggleDetail('cash', 'direct')">
                <td class="px-4 py-3">
                  <svg class="w-3.5 h-3.5 transition-transform fv-text-muted" :style="{ transform: expandedRows['cash-direct'] ? 'rotate(90deg)' : 'none' }"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </td>
                <td class="px-4 py-3 text-sm fv-text-primary">Direct Expenses Paid</td>
                <td v-for="m in months" :key="`dc-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(directCashByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#f87171;">{{ fmt(totalDirectCash) }}</td>
              </tr>
              <tr v-if="expandedRows['cash-direct']" style="border-bottom:1px solid var(--fv-border);">
                <td :colspan="months.length + 3" class="px-4 py-3" style="background:var(--fv-bg-input)">
                  <p v-if="detailLoading['cash-direct']" class="text-xs fv-text-muted">Loading…</p>
                  <p v-else-if="!detailCache['cash-direct']?.length" class="text-xs fv-text-muted">No direct expense payments in this period.</p>
                  <table v-else class="w-full text-xs">
                    <thead>
                      <tr class="fv-text-muted">
                        <th class="text-left py-1 font-medium">Category / Item</th>
                        <th class="text-left py-1 font-medium">Payment Date</th>
                        <th class="text-right py-1 font-medium">Amount Paid</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(r, i) in detailCache['cash-direct']" :key="i">
                        <td class="py-1 fv-text-primary">{{ r.category }} <span class="fv-text-muted">— {{ r.item }}</span></td>
                        <td class="py-1 fv-text-muted">{{ fmtDate(r.date) }}</td>
                        <td class="py-1 text-right" style="color:#34d399">{{ r.currency }} {{ fmt2(r.amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>

              <tr style="border-bottom:1px solid var(--fv-border); cursor:pointer;" @click="toggleDetail('cash', 'corporate')">
                <td class="px-4 py-3">
                  <svg class="w-3.5 h-3.5 transition-transform fv-text-muted" :style="{ transform: expandedRows['cash-corporate'] ? 'rotate(90deg)' : 'none' }"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </td>
                <td class="px-4 py-3 text-sm fv-text-primary">
                  Corporate Expenses Paid
                  <span class="text-[10px] fv-text-muted block">(allocated share)</span>
                </td>
                <td v-for="m in months" :key="`cc-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(corporateCashByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#f87171;">{{ fmt(totalCorporateCash) }}</td>
              </tr>
              <tr v-if="expandedRows['cash-corporate']" style="border-bottom:1px solid var(--fv-border);">
                <td :colspan="months.length + 3" class="px-4 py-3" style="background:var(--fv-bg-input)">
                  <p v-if="detailLoading['cash-corporate']" class="text-xs fv-text-muted">Loading…</p>
                  <p v-else-if="!detailCache['cash-corporate']?.length" class="text-xs fv-text-muted">No corporate expense payments allocated to this unit in this period.</p>
                  <table v-else class="w-full text-xs">
                    <thead>
                      <tr class="fv-text-muted">
                        <th class="text-left py-1 font-medium">Category / Item</th>
                        <th class="text-left py-1 font-medium">Payment Date</th>
                        <th class="text-right py-1 font-medium">Unit %</th>
                        <th class="text-right py-1 font-medium">Amount Paid (Share)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(r, i) in detailCache['cash-corporate']" :key="i">
                        <td class="py-1 fv-text-primary">{{ r.category }} <span class="fv-text-muted">— {{ r.item }}</span></td>
                        <td class="py-1 fv-text-muted">{{ fmtDate(r.date) }}</td>
                        <td class="py-1 text-right fv-text-muted">{{ r.allocation_pct.toFixed(2) }}%</td>
                        <td class="py-1 text-right" style="color:#34d399">{{ r.currency }} {{ fmt2(r.amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>

              <tr style="border-bottom:1px solid var(--fv-border);">
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-sm font-semibold fv-text-primary">Total Expenses Paid</td>
                <td v-for="m in months" :key="`tec-${m}`" class="px-4 py-3 text-center text-sm font-semibold fv-text-primary">
                  {{ fmt(totalExpensesCashByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#f87171;">{{ fmt(totalExpensesCash) }}</td>
              </tr>

              <tr style="border-bottom:1px solid var(--fv-border);">
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-sm fv-text-primary">Net Cashflow</td>
                <td
                  v-for="m in months"
                  :key="`n-${m}`"
                  class="px-4 py-3 text-center text-sm"
                  :style="{ color: n(cashflowByMonth[m]) >= 0 ? '#4ade80' : '#f87171' }"
                >
                  {{ fmt(cashflowByMonth[m]) }}
                </td>
                <td class="px-4 py-3"></td>
              </tr>
              <tr>
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-sm fv-text-primary">Accumulated Cashflow</td>
                <td
                  v-for="m in months"
                  :key="`a-${m}`"
                  class="px-4 py-3 text-center text-sm"
                  :style="{ color: n(accumulatedByMonth[m]) >= 0 ? '#4ade80' : '#f87171' }"
                >
                  {{ fmt(accumulatedByMonth[m]) }}
                </td>
                <td class="px-4 py-3"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.tab-active {
  background: var(--fv-blue, #1490A8);
  color: #fff;
}
.tab-inactive {
  color: var(--fv-text-muted, #6B96B8);
  background: rgba(11,26,48,0.6);
}
</style>
