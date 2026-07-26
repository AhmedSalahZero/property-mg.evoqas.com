<template>
  <AuthenticatedLayout title="Exchange Rates">
    <div class="p-6 space-y-6">

      <!-- ── Page Header ─────────────────────────────────────────── -->
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
          <Link
            :href="route('company.reports.index', company.id)"
            class="fv-action-btn"
            title="Back to Reports"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
          </Link>
          <div>
            <h1 class="text-lg font-bold fv-text-primary">Exchange Rates</h1>
            <p class="text-xs fv-text-muted mt-0.5">
              Base currency: <strong class="fv-text-primary">{{ baseCurrency }}</strong> —
              every rent revenue, collection, expense, and installment due is converted to this
              currency for all dashboard totals and reports.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <a
            :href="route('company.reports.currency-rates.template', company.id)"
            class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m0 0l-5-5m5 5l5-5M19 19H5"/>
            </svg>
            Download Template
          </a>

          <a
            v-if="totalAcrossTabs"
            :href="route('company.reports.currency-rates.export', company.id)"
            class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V3"/>
            </svg>
            Export Current Rates
          </a>

          <input
            ref="excelFileInput"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="hidden"
            @change="onExcelFileSelected"
          />
          <button
            @click="triggerExcelUpload"
            :disabled="importingExcel"
            class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v8m0 0l4-4m-4 4l-4-4"/>
            </svg>
            {{ importingExcel ? 'Uploading…' : 'Upload Excel' }}
          </button>

          <button @click="showPullForm = true" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H4a2 2 0 00-2 2v6a2 2 0 002 2h14a2 2 0 002-2v-4M9 19h10a2 2 0 002-2v-1M13 5v6m0 0l3-3m-3 3L10 8"/>
            </svg>
            Pull from Statistica
          </button>

          <button @click="openAdd" class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Rate
          </button>
        </div>
      </div>

      <!-- ── Flash ─────────────────────────────────────────────────── -->
      <div v-if="$page.props.flash?.success"
        class="px-4 py-3 rounded-lg text-sm"
        style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.25); color:#34d399;">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys(errors).length"
        class="px-4 py-3 rounded-lg text-sm"
        style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#f87171;">
        <div v-for="(msg, key) in errors" :key="key">{{ msg }}</div>
      </div>

      <!-- ── Pull from Statistica (inline card) ───────────────────── -->
      <div v-if="showPullForm" class="fv-card">
        <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-1">Pull Rates from Statistica</h2>
        <p class="fv-text-muted text-xs mb-4">
          Already tracking this currency's trend in Statistica? Pull those entries in as exchange
          rates instead of re-typing them. This copies the data in once — it isn't a live link, so
          re-run it any time you've added new entries to the Statistica series.
        </p>

        <div v-if="!statisticaSeries.length" class="fv-text-muted text-sm">
          No Statistica series exist for this company yet. Create one under the Statistica tab first
          (Category: FX Rates works well), then come back here to pull it in.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Currency *</label>
            <select v-model="pullForm.currency" class="fv-select rounded-lg px-3 py-2 text-sm w-full">
              <option value="" disabled>— Select —</option>
              <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Statistica Series *</label>
            <select v-model="pullForm.series_id" class="fv-select rounded-lg px-3 py-2 text-sm w-full">
              <option value="" disabled>— Select —</option>
              <option v-for="s in statisticaSeries" :key="s.id" :value="s.id" :disabled="!s.entry_count">
                {{ s.name }} ({{ s.category }}{{ s.unit ? ' — ' + s.unit : '' }}) — {{ s.entry_count }} entries
              </option>
            </select>
          </div>
          <div>
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">From (optional)</label>
            <input type="date" v-model="pullForm.date_from" class="fv-input rounded-lg px-3 py-2 text-sm w-full"/>
          </div>
          <div>
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">To (optional)</label>
            <input type="date" v-model="pullForm.date_to" class="fv-input rounded-lg px-3 py-2 text-sm w-full"/>
          </div>
        </div>

        <div class="flex items-center gap-2 mt-4">
          <button @click="submitPull" :disabled="pulling || !pullForm.currency || !pullForm.series_id"
            class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold">
            {{ pulling ? 'Pulling…' : 'Pull Rates' }}
          </button>
          <button @click="showPullForm = false" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold">
            Cancel
          </button>
        </div>
        <p class="fv-text-muted text-xs mt-3">
          You're confirming which series is which currency — leave the date range blank to pull every
          entry in that series.
        </p>
      </div>

      <!-- ── Add Rate Form (inline card) ──────────────────────────── -->
      <div v-if="showAddForm" class="fv-card">
        <h2 class="text-sm font-bold fv-text-label uppercase tracking-wider mb-4">New Exchange Rate</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Currency *</label>
            <select v-model="form.currency" class="fv-select rounded-lg px-3 py-2 text-sm w-full">
              <option value="" disabled>— Select —</option>
              <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div>
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Date *</label>
            <input type="date" v-model="form.rate_date" class="fv-input rounded-lg px-3 py-2 text-sm w-full"/>
          </div>
          <div>
            <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">
              Rate * <span class="normal-case font-normal">(1 {{ form.currency || 'FX' }} = ? {{ baseCurrency }})</span>
            </label>
            <input type="number" step="0.000001" min="0.000001" v-model="form.rate"
              class="fv-input rounded-lg px-3 py-2 text-sm w-full" :placeholder="`e.g. 48.50`"/>
          </div>
          <div class="flex items-end gap-2">
            <button @click="submitRate" :disabled="submitting" class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold">
              {{ submitting ? 'Saving…' : 'Save' }}
            </button>
            <button @click="showAddForm = false" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold">
              Cancel
            </button>
          </div>
        </div>
        <p class="fv-text-muted text-xs mt-3">
          Only one rate per currency per day is kept — saving again for the same currency and date
          overwrites the existing rate for that day.
        </p>
      </div>

      <!-- ── Currency Tabs ─────────────────────────────────────────── -->
      <div class="flex items-center gap-2 flex-wrap border-b pb-px" style="border-color: var(--fv-border);">
        <button
          @click="goToTab('ALL')"
          class="px-4 py-2 text-sm font-semibold rounded-t-lg transition-colors"
          :style="tabStyle('ALL')"
        >
          All <span class="fv-text-muted font-normal">({{ totalAcrossTabs }})</span>
        </button>
        <button
          v-for="c in tabs" :key="c"
          @click="goToTab(c)"
          class="px-4 py-2 text-sm font-semibold rounded-t-lg transition-colors"
          :style="tabStyle(c)"
        >
          {{ c }} <span class="fv-text-muted font-normal">({{ countsByCurrency[c] || 0 }})</span>
        </button>
      </div>

      <!-- ── Rates Table ───────────────────────────────────────────── -->
      <div class="fv-card overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b" style="border-color: var(--fv-border);">
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs fv-text-muted">Currency</th>
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs fv-text-muted">Date</th>
              <th class="text-right px-4 py-3 font-semibold uppercase text-xs fv-text-muted">Rate (per 1 unit → {{ baseCurrency }})</th>
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs fv-text-muted">Source</th>
              <th class="text-right px-4 py-3 font-semibold uppercase text-xs fv-text-muted">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rates.length">
              <td colspan="5" class="px-4 py-8 text-center fv-text-muted text-sm">
                {{ activeCurrency === 'ALL'
                  ? 'No exchange rates on file yet. Add one above, or upload an Excel file with your rate history.'
                  : `No exchange rates on file yet for ${activeCurrency}.` }}
              </td>
            </tr>
            <tr v-for="r in rates" :key="r.id" class="border-b" style="border-color: var(--fv-border);">
              <td class="px-4 py-2.5 fv-text-primary font-semibold">{{ r.currency }}</td>
              <td class="px-4 py-2.5 fv-text-primary">{{ formatDate(r.rate_date) }}</td>
              <td class="px-4 py-2.5 text-right fv-text-primary">{{ formatRate(r.rate) }}</td>
              <td class="px-4 py-2.5">
                <span class="fv-tag" :class="r.source === 'manual' ? 'fv-tag-gold' : ''">
                  {{ sourceLabel(r.source) }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-right">
                <button @click="removeRate(r)" class="fv-action-btn-danger" title="Delete">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- ── Pagination ──────────────────────────────────────────── -->
        <div v-if="pagination.total > 0" class="flex items-center justify-between px-4 py-3 border-t text-sm" style="border-color: var(--fv-border);">
          <span class="fv-text-muted">
            Showing page {{ pagination.current_page }} of {{ pagination.last_page }}
            ({{ pagination.total }} rate{{ pagination.total === 1 ? '' : 's' }}{{ activeCurrency !== 'ALL' ? ` — ${activeCurrency}` : '' }})
          </span>
          <div class="flex items-center gap-2">
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="fv-btn-secondary px-3 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-40"
            >
              ← Previous
            </button>
            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="fv-btn-secondary px-3 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-40"
            >
              Next →
            </button>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  company: Object,
  baseCurrency: String,
  rates: Array,
  currencyOptions: Array,
  statisticaSeries: Array,
  tabs: { type: Array, default: () => [] },
  activeCurrency: { type: String, default: 'ALL' },
  countsByCurrency: { type: Object, default: () => ({}) },
  pagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }),
  },
})

const page = usePage()
const errors = computed(() => page.props.errors || {})

// ── Add rate form ───────────────────────────────────────────────────
const showAddForm = ref(false)
const submitting  = ref(false)
const form = ref({
  currency: props.currencyOptions[0] || '',
  rate_date: new Date().toISOString().slice(0, 10),
  rate: '',
})

function openAdd() {
  showAddForm.value = true
}

function submitRate() {
  if (!form.value.currency || !form.value.rate_date || !form.value.rate) return
  submitting.value = true
  router.post(
    route('company.reports.currency-rates.store', props.company.id),
    { ...form.value },
    {
      preserveScroll: true,
      onSuccess: () => { showAddForm.value = false; form.value.rate = '' },
      onFinish:  () => { submitting.value = false },
    }
  )
}

function removeRate(r) {
  if (!confirm(`Delete the ${r.currency} rate for ${formatDate(r.rate_date)}?`)) return
  router.delete(
    route('company.reports.currency-rates.destroy', [props.company.id, r.id]),
    { preserveScroll: true }
  )
}

function formatRate(v) {
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 })
}

// Table displays dates DD/MM/YYYY (Egypt convention); the underlying
// value stored/sent to the backend stays ISO (YYYY-MM-DD) — only this
// display string changes.
function formatDate(isoDate) {
  if (!isoDate) return '—'
  const [y, m, d] = String(isoDate).split('-')
  if (!y || !m || !d) return isoDate
  return `${d}/${m}/${y}`
}

function sourceLabel(source) {
  return { manual: 'Manual', excel_import: 'Excel Import', statistica_import: 'From Statistica' }[source] || source
}

// ── Currency tabs + pagination ────────────────────────────────────────
// Total across every currency, for the "All" tab's count badge and the
// Export button's visibility — independent of whichever tab/page is
// currently showing.
const totalAcrossTabs = computed(() =>
  Object.values(props.countsByCurrency || {}).reduce((sum, n) => sum + Number(n), 0)
)

function tabStyle(tab) {
  const isActive = props.activeCurrency === tab
  return isActive
    ? 'color: var(--fv-gold); border-bottom: 2px solid var(--fv-gold); background: rgba(186,117,23,0.08);'
    : 'color: var(--fv-text-muted); border-bottom: 2px solid transparent;'
}

function goToTab(tab) {
  goTo(tab, 1)
}

function goToPage(page) {
  goTo(props.activeCurrency, page)
}

function goTo(currency, page) {
  router.get(
    route('company.reports.currency-rates.index', props.company.id),
    { currency: currency === 'ALL' ? undefined : currency, page },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

// ── Pull from Statistica ─────────────────────────────────────────────
const showPullForm = ref(false)
const pulling       = ref(false)
const pullForm = ref({
  currency: '',
  series_id: '',
  date_from: '',
  date_to: '',
})

function submitPull() {
  if (!pullForm.value.currency || !pullForm.value.series_id) return
  pulling.value = true
  router.post(
    route('company.reports.currency-rates.from-statistica', props.company.id),
    {
      ...pullForm.value,
      date_from: pullForm.value.date_from || null,
      date_to: pullForm.value.date_to || null,
    },
    {
      preserveScroll: true,
      onSuccess: () => { showPullForm.value = false },
      onFinish:  () => { pulling.value = false },
    }
  )
}

// ── Excel import ─────────────────────────────────────────────────────
const excelFileInput = ref(null)
const importingExcel = ref(false)

function triggerExcelUpload() {
  if (importingExcel.value) return
  excelFileInput.value?.click()
}

function onExcelFileSelected(event) {
  const file = event.target?.files?.[0]
  if (!file) return

  importingExcel.value = true
  router.post(
    route('company.reports.currency-rates.import', props.company.id),
    { file },
    {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        importingExcel.value = false
        if (excelFileInput.value) excelFileInput.value.value = ''
      },
    }
  )
}
</script>