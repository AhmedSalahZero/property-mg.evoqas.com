<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    company:     Object,
    fromDefault: String,
    toDefault:   String,
    baseCurrency: { type: String, default: 'EGP' },
})

const companyId = computed(() => props.company?.id)

// ── Period ────────────────────────────────────────────────────────────────
const fromPicker = ref(props.fromDefault)
const toPicker   = ref(props.toDefault)

// ── Currency view ─────────────────────────────────────────────────────────
// '' (empty) = main functional currency (every currency converted at the
// latest rate on file and summed together). Any other value = show ONLY
// that currency's raw, unconverted cash in/out.
const viewCurrency        = ref('')
const availableCurrencies = ref([])
const isFunctionalView    = ref(true)
const unconvertedCurrencies = ref([])

// ── Server data ───────────────────────────────────────────────────────────
const months            = ref([])
const rentByTypeUnit    = ref({})
const saleReceivablesByTypeUnit = ref({})
const installByTypeUnit = ref({})
const expenseByItem     = ref({})
const corporateExpenseByItem = ref({})
const managementFeesByMonth = ref({})
const loading           = ref(false)

// ── Section collapse — separate refs so Vue tracks them reactively ─────────
const cashInOpen      = ref(true)
const cashOutOpen     = ref(true)
const rentTypeOpen    = ref({})
const saleTypeOpen    = ref({})
const installTypeOpen = ref({})

// ── User-entered data ─────────────────────────────────────────────────────
const otherCollections = ref([])
const salaries         = ref({})
const newHirings       = ref({})
const otherPayments    = ref([])

// ── Fix for audit finding H-4 — persistence state for the four manual
// sections above. They previously lived only in this in-memory state with
// no save endpoint anywhere, so every entry was lost on refresh. ─────────
const manualRowsSaving  = ref(false)
const manualRowsDirty   = ref(false)
const manualRowsSavedAt = ref(null)
// True while THIS code (not the user) is writing to salaries/newHirings/
// otherCollections/otherPayments — during the initial load, and during
// fetchData()'s own "fill in any month not already present" step — so the
// dirty-tracking watch below only fires on genuine user edits.
const suppressManualDirty = ref(false)

// ── Hiring modal ──────────────────────────────────────────────────────────
const hiringModalOpen  = ref(false)
const hiringModalMonth = ref(null)

// ── Charts ────────────────────────────────────────────────────────────────
const chartCashRef  = ref(null)
const chartAccumRef = ref(null)
let chartCash  = null
let chartAccum = null

// ─────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────
const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

function fmtLabel(ym) {
    if (!ym) return ''
    const [y, m] = ym.split('-')
    return MONTH_NAMES[parseInt(m) - 1] + ' ' + y
}

function n(v) { return parseFloat(v) || 0 }

function fmt(v) {
    const num = parseFloat(v) || 0
    if (num === 0) return '—'
    return num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function fmtSigned(v) {
    return (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// ─────────────────────────────────────────────────────────────────────────
// COMPUTED TOTALS
// ─────────────────────────────────────────────────────────────────────────
const rentTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        let s = 0
        Object.values(rentByTypeUnit.value).forEach(units =>
            Object.values(units).forEach(mMap => { s += n(mMap[m]) })
        )
        out[m] = s
    })
    return out
})

const saleReceivablesTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        let s = 0
        Object.values(saleReceivablesByTypeUnit.value).forEach(units =>
            Object.values(units).forEach(mMap => { s += n(mMap[m]) })
        )
        out[m] = s
    })
    return out
})

const otherCollTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = otherCollections.value.reduce((s, r) => s + n(r.amounts[m]), 0)
    })
    return out
})

const totalCashIn = computed(() => {
    const out = {}
    months.value.forEach(m => { out[m] = rentTotals.value[m] + saleReceivablesTotals.value[m] + otherCollTotals.value[m] })
    return out
})

const installTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        let s = 0
        Object.values(installByTypeUnit.value).forEach(units =>
            Object.values(units).forEach(mMap => { s += n(mMap[m]) })
        )
        out[m] = s
    })
    return out
})

const expenseTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = Object.values(expenseByItem.value).reduce((s, mMap) => s + n(mMap[m]), 0)
    })
    return out
})

const corporateExpenseTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = Object.values(corporateExpenseByItem.value).reduce((s, mMap) => s + n(mMap[m]), 0)
    })
    return out
})

const salaryTotals = computed(() => {
    const out = {}
    months.value.forEach(m => { out[m] = n(salaries.value[m]) })
    return out
})

const hiringTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = (newHirings.value[m] || []).reduce((s, r) => s + n(r.amount), 0)
    })
    return out
})

const otherPayTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = otherPayments.value.reduce((s, r) => s + n(r.amounts[m]), 0)
    })
    return out
})

const managementFeeTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = n(managementFeesByMonth.value[m])
    })
    return out
})

const totalCashOut = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = installTotals.value[m] + expenseTotals.value[m] + corporateExpenseTotals.value[m] +
                 managementFeeTotals.value[m] + salaryTotals.value[m]  + hiringTotals.value[m]  +
                 otherPayTotals.value[m]
    })
    return out
})

const netFlow = computed(() => {
    const out = {}
    months.value.forEach(m => { out[m] = totalCashIn.value[m] - totalCashOut.value[m] })
    return out
})

const accumulated = computed(() => {
    const out = {}
    let running = 0
    months.value.forEach(m => { running += netFlow.value[m]; out[m] = running })
    return out
})

const maxGapMonth = computed(() => {
    let worst = null, worstVal = 0
    months.value.forEach(m => {
        if (accumulated.value[m] < worstVal) { worstVal = accumulated.value[m]; worst = m }
    })
    return worst
})

// ─────────────────────────────────────────────────────────────────────────
// FETCH
// ─────────────────────────────────────────────────────────────────────────
async function fetchData() {
    if (!companyId.value) return
    if (!fromPicker.value || !toPicker.value) return
    if (fromPicker.value > toPicker.value) return
    loading.value = true
    try {
        const currencyParam = viewCurrency.value ? `&currency=${viewCurrency.value}` : ''
        const url = route('company.properties.cash-forecast.data', { company: companyId.value })
            + `?from=${fromPicker.value}&to=${toPicker.value}${currencyParam}`
        const res  = await fetch(url, { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        if (!res.ok) {
            console.error('CashForecast HTTP error', res.status, await res.text())
            return
        }
        const data = await res.json()

        months.value            = data.months            || []
        rentByTypeUnit.value    = data.rentByTypeUnit    || {}
        saleReceivablesByTypeUnit.value = data.saleReceivablesByTypeUnit || {}
        installByTypeUnit.value = data.installByTypeUnit || {}
        expenseByItem.value     = data.expenseByItem     || {}
        corporateExpenseByItem.value = data.corporateExpenseByItem || {}
        managementFeesByMonth.value = data.managementFeesByMonth || {}
        availableCurrencies.value   = data.availableCurrencies   || []
        isFunctionalView.value      = data.isFunctionalView ?? true
        unconvertedCurrencies.value = data.unconvertedCurrencies || []

        months.value.forEach(m => {
            if (!(m in salaries.value))   salaries.value[m]   = ''
            if (!(m in newHirings.value)) newHirings.value[m] = []
        })
        otherCollections.value.forEach(r => {
            months.value.forEach(m => { if (!(m in r.amounts)) r.amounts[m] = '' })
        })
        otherPayments.value.forEach(r => {
            months.value.forEach(m => { if (!(m in r.amounts)) r.amounts[m] = '' })
        })
        // Fix for audit finding H-4 — the three blocks just above only ever
        // WRITE a value for a month that's genuinely missing (e.g. the
        // period picker was widened) — real user-entered amounts are never
        // touched. Still, mutating the refs at all would otherwise trip the
        // dirty-tracking watch below and prompt a save/leave-warning for
        // data the user never actually edited. suppressManualDirty (already
        // true from the loadManualRows() call that always precedes the
        // first fetchData() — see onMounted below) covers the initial load;
        // this repeats the same guard for every subsequent fetchData() call
        // triggered by changing the period/currency pickers.
        suppressManualDirty.value = true
        await nextTick()
        suppressManualDirty.value = false

        const rt = {}
        Object.keys(rentByTypeUnit.value).forEach(t => { rt[t] = true })
        rentTypeOpen.value = rt

        const st = {}
        Object.keys(saleReceivablesByTypeUnit.value).forEach(t => { st[t] = true })
        saleTypeOpen.value = st

        const it = {}
        Object.keys(installByTypeUnit.value).forEach(t => { it[t] = true })
        installTypeOpen.value = it

        await nextTick()
        renderCharts()
    } catch(e) {
        console.error('CashForecast fetch error', e)
    } finally {
        loading.value = false
    }
}

watch([fromPicker, toPicker, viewCurrency], fetchData)
onMounted(async () => {
    // Fix for audit finding H-4 — load saved manual rows BEFORE the first
    // fetchData() call. fetchData()'s own month-filling logic only fills in
    // months that AREN'T already present on salaries/newHirings (and
    // otherCollections/otherPayments' own amounts objects), so loading the
    // saved data first means real values are preserved and only genuinely
    // missing months get blank defaults, in either order of months.
    await loadManualRows()
    await fetchData()
})

// ─────────────────────────────────────────────────────────────────────────
// MANUAL ROWS PERSISTENCE — fix for audit finding H-4
// ─────────────────────────────────────────────────────────────────────────
async function loadManualRows() {
    if (!companyId.value) return
    suppressManualDirty.value = true
    try {
        const url = route('company.properties.cash-forecast.manual-rows', { company: companyId.value })
        const res = await fetch(url, { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        if (!res.ok) {
            console.error('CashForecast manual-rows load error', res.status, await res.text())
            return
        }
        const data = await res.json()
        salaries.value         = data.salaries          || {}
        newHirings.value       = data.new_hirings        || {}
        otherCollections.value = data.other_collections  || []
        otherPayments.value    = data.other_payments     || []
        manualRowsSavedAt.value = data.updated_at || null
    } catch (e) {
        console.error('CashForecast manual-rows load error', e)
    } finally {
        await nextTick()
        suppressManualDirty.value = false
        manualRowsDirty.value = false
    }
}

async function saveManualRows() {
    if (!companyId.value) return
    manualRowsSaving.value = true
    try {
        const url = route('company.properties.cash-forecast.manual-rows.save', { company: companyId.value })
        const res = await axios.post(url, {
            salaries:          salaries.value,
            new_hirings:       newHirings.value,
            other_collections: otherCollections.value,
            other_payments:    otherPayments.value,
        })
        manualRowsSavedAt.value = res.data.saved_at
        manualRowsDirty.value   = false
    } catch (e) {
        console.error('CashForecast manual-rows save error', e?.response?.data || e)
        alert('Could not save your Salaries/New Hirings/Other Collections/Other Payments entries — please try again.')
    } finally {
        manualRowsSaving.value = false
    }
}

// Mark the four manual sections dirty the moment the user changes anything
// in them, so the "unsaved changes" indicator (and the browser's own
// leave-page warning below) only fires on real edits, not on writes made by
// loadManualRows() or fetchData()'s own month-filling step above (guarded
// by suppressManualDirty).
watch([salaries, newHirings, otherCollections, otherPayments], () => {
    if (!suppressManualDirty.value) manualRowsDirty.value = true
}, { deep: true })

// Warn before leaving the page (refresh, close tab, navigate away) with
// unsaved manual-row edits — the exact scenario audit finding H-4 flagged.
window.addEventListener('beforeunload', (e) => {
    if (manualRowsDirty.value) {
        e.preventDefault()
        e.returnValue = ''
    }
})

// ─────────────────────────────────────────────────────────────────────────
// REPEATERS
// ─────────────────────────────────────────────────────────────────────────
function addOtherCollection() {
    const amounts = {}
    months.value.forEach(m => { amounts[m] = '' })
    otherCollections.value.push({ name: '', amounts })
}
function removeOtherCollection(i) { otherCollections.value.splice(i, 1) }

function addOtherPayment() {
    const amounts = {}
    months.value.forEach(m => { amounts[m] = '' })
    otherPayments.value.push({ name: '', amounts })
}
function removeOtherPayment(i) { otherPayments.value.splice(i, 1) }

function copyRight(amounts, monthIndex) {
    const val = amounts[months.value[monthIndex]]
    for (let i = monthIndex + 1; i < months.value.length; i++) {
        amounts[months.value[i]] = val
    }
}
function copySalaryRight(monthIndex) {
    const val = salaries.value[months.value[monthIndex]]
    for (let i = monthIndex + 1; i < months.value.length; i++) {
        salaries.value[months.value[i]] = val
    }
}

// ─────────────────────────────────────────────────────────────────────────
// HIRING MODAL
// ─────────────────────────────────────────────────────────────────────────
function openHiring(month) {
    hiringModalMonth.value = month
    if (!newHirings.value[month]) newHirings.value[month] = []
    hiringModalOpen.value = true
}
function addHiringRow() {
    newHirings.value[hiringModalMonth.value].push({ title: '', amount: '' })
}
function removeHiringRow(i) {
    newHirings.value[hiringModalMonth.value].splice(i, 1)
}
function copyHiringRight(rowIndex) {
    const month = hiringModalMonth.value
    const row   = newHirings.value[month][rowIndex]
    const mi    = months.value.indexOf(month)
    for (let i = mi + 1; i < months.value.length; i++) {
        const m = months.value[i]
        if (!newHirings.value[m]) newHirings.value[m] = []
        newHirings.value[m].push({ title: row.title, amount: row.amount })
    }
}

// ─────────────────────────────────────────────────────────────────────────
// CHARTS
// ─────────────────────────────────────────────────────────────────────────
async function renderCharts() {
    if (!window.Chart) {
        await new Promise(resolve => {
            const s = document.createElement('script')
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
            s.onload = resolve
            document.head.appendChild(s)
        })
    }
    const labels      = months.value.map(fmtLabel)
    const cashInData  = months.value.map(m => totalCashIn.value[m]  || 0)
    const cashOutData = months.value.map(m => totalCashOut.value[m] || 0)
    const accumData   = months.value.map(m => accumulated.value[m]  || 0)

    if (chartCashRef.value) {
        if (chartCash) chartCash.destroy()
        chartCash = new window.Chart(chartCashRef.value, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Cash In',  data: cashInData,  borderColor: '#1490A8', backgroundColor: 'rgba(20,144,168,0.12)', tension: 0.3, fill: true, pointRadius: 4 },
                    { label: 'Cash Out', data: cashOutData, borderColor: '#BA7517', backgroundColor: 'rgba(186,117,23,0.10)', tension: 0.3, fill: true, pointRadius: 4 },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#E2E8F0' } } },
                scales: {
                    x: { ticks: { color: '#6B96B8' }, grid: { color: '#1B3558' } },
                    y: { ticks: { color: '#6B96B8' }, grid: { color: '#1B3558' } },
                }
            }
        })
    }

    if (chartAccumRef.value) {
        if (chartAccum) chartAccum.destroy()
        chartAccum = new window.Chart(chartAccumRef.value, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Accumulated Cash Flow',
                    data: accumData,
                    borderColor: '#48C4D8',
                    backgroundColor: 'rgba(72,196,216,0.10)',
                    tension: 0.3, fill: true,
                    pointRadius: accumData.map((_, i) => months.value[i] === maxGapMonth.value ? 8 : 4),
                    pointBackgroundColor: accumData.map((_, i) => months.value[i] === maxGapMonth.value ? '#ef4444' : '#48C4D8'),
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#E2E8F0' } } },
                scales: {
                    x: { ticks: { color: '#6B96B8' }, grid: { color: '#1B3558' } },
                    y: { ticks: { color: '#6B96B8' }, grid: { color: '#1B3558' } },
                }
            }
        })
    }
}

watch([totalCashIn, totalCashOut, accumulated], () => nextTick(renderCharts), { deep: true })
</script>

<template>
    <AuthenticatedLayout>
        <div class="p-6 space-y-5" style="color:var(--fv-text,#E2E8F0);">

            <!-- ── PAGE HEADER ──────────────────────────────────────── -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold" style="color:#48C4D8;">Cash Forecast</h1>
                    <p class="text-xs mt-0.5" style="color:var(--fv-muted);">12-Month Rolling Cash Flow Projection</p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase" style="color:var(--fv-muted);">Currency</span>
                        <select v-model="viewCurrency" class="fv-select rounded-lg px-3 py-1.5 text-sm">
                            <option value="">{{ baseCurrency }} (Functional — all currencies converted)</option>
                            <option v-for="c in availableCurrencies.filter(c => c !== baseCurrency)" :key="c" :value="c">
                                {{ c }} only (raw, unconverted)
                            </option>
                        </select>
                        <!-- Fix for audit finding M-4 — see the matching note on the
                             main Dashboard's currency picker: the "Functional" total
                             here always uses today's latest exchange rate, while each
                             underlying transaction keeps the rate that applied on its
                             own date. Both are correct, just answering a different
                             question — this makes that visible in the UI itself. -->
                        <span class="text-xs cursor-help" style="color:var(--fv-muted);"
                            title="Functional totals always use the latest exchange rate on file (today's value). Individual transactions elsewhere in the app keep the rate that applied on their own date, so the same amount can show slightly differently in the two places — both are correct, just answering a different question.">ⓘ</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase" style="color:var(--fv-muted);">From</span>
                        <input type="month" v-model="fromPicker" class="fv-input rounded-lg px-3 py-1.5 text-sm" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase" style="color:var(--fv-muted);">To</span>
                        <input type="month" v-model="toPicker" class="fv-input rounded-lg px-3 py-1.5 text-sm" />
                    </div>
                    <!-- Fix for audit finding H-4 — Save button + status for the
                         Salaries/New Hirings/Other Collections/Other Payments
                         sections, which previously had no way to persist at all. -->
                    <button type="button" @click="saveManualRows" :disabled="manualRowsSaving || !manualRowsDirty"
                        class="fv-btn-gold rounded-lg px-4 py-1.5 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ manualRowsSaving ? 'Saving…' : 'Save Forecast Inputs' }}
                    </button>
                    <span v-if="manualRowsDirty && !manualRowsSaving" class="text-xs" style="color:#BA7517;">● Unsaved changes</span>
                    <span v-else-if="manualRowsSavedAt && !manualRowsSaving" class="text-xs" style="color:var(--fv-muted);">
                        Saved {{ new Date(manualRowsSavedAt).toLocaleString() }}
                    </span>
                    <span v-if="loading" class="text-xs animate-pulse" style="color:#1490A8;">Loading…</span>
                </div>
            </div>

            <div v-if="!isFunctionalView" class="px-4 py-2.5 rounded-lg text-xs"
                style="background:rgba(186,117,23,0.1); border:1px solid rgba(186,117,23,0.3); color:#BA7517;">
                Showing <strong>{{ viewCurrency }} only</strong> — raw, unconverted cash flows in this
                currency alone. A contract billed in {{ viewCurrency }} but actually collected in a
                different currency correctly shows nothing here, since collection currency (what's
                really received) is what counts, not the rent's billing currency.
            </div>
            <div v-if="isFunctionalView && unconvertedCurrencies.length" class="px-4 py-2.5 rounded-lg text-xs"
                style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#f87171;">
                💱 {{ unconvertedCurrencies.join(', ') }} {{ unconvertedCurrencies.length > 1 ? 'have' : 'has' }}
                no exchange rate on file — those amounts are excluded from the totals below until a rate
                is added under Company Settings → Exchange Rates.
            </div>

            <!-- ── CHARTS ───────────────────────────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-xl p-4" style="background:var(--fv-card);border:1px solid var(--fv-border);">
                    <p class="text-xs font-bold mb-3" style="color:#1490A8;">Cash In vs Cash Out</p>
                    <div style="height:220px;"><canvas ref="chartCashRef"></canvas></div>
                </div>
                <div class="rounded-xl p-4" style="background:var(--fv-card);border:1px solid var(--fv-border);">
                    <p class="text-xs font-bold mb-3" style="color:#1490A8;">
                        Accumulated Cash Flow
                        <span v-if="maxGapMonth" class="ml-2" style="color:#ef4444;">
                            — Max Gap: {{ fmtLabel(maxGapMonth) }}
                        </span>
                    </p>
                    <div style="height:220px;"><canvas ref="chartAccumRef"></canvas></div>
                </div>
            </div>

            <!-- ── MAIN TABLE ────────────────────────────────────────── -->
            <div class="rounded-xl overflow-hidden" style="border:1px solid var(--fv-border);">
                <div class="overflow-x-auto">
                <table style="border-collapse:collapse;min-width:max-content;width:100%;">

                    <!-- COLUMN HEADERS -->
                    <thead>
                        <tr style="background:#0B1A30;">
                            <th style="position:sticky;left:0;z-index:20;background:#0B1A30;
                                       min-width:220px;text-align:left;padding:10px 16px;
                                       font-size:11px;font-weight:700;text-transform:uppercase;
                                       letter-spacing:.05em;color:#26C6DA;
                                       border-bottom:2px solid var(--fv-border,#1B3558);">
                                Item
                            </th>
                            <th v-for="m in months" :key="m"
                                style="min-width:120px;padding:10px 12px;font-size:11px;font-weight:700;
                                       text-align:center;white-space:nowrap;color:#26C6DA;
                                       border-bottom:2px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                {{ fmtLabel(m) }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                    <!-- ═══════════════════════════ CASH IN ═══════════════════════════ -->

                    <!-- Section header row -->
                    <tr style="background:rgba(20,144,168,0.15);cursor:pointer;user-select:none;"
                        @click="cashInOpen = !cashInOpen">
                        <td style="position:sticky;left:0;z-index:10;background:rgba(20,144,168,0.15);
                                   padding:10px 16px;font-size:11px;font-weight:800;
                                   text-transform:uppercase;letter-spacing:.05em;color:#48C4D8;
                                   border-bottom:1px solid var(--fv-border,#1B3558);">
                            {{ cashInOpen ? '▼' : '▶' }}&nbsp;&nbsp;CASH IN
                        </td>
                        <td v-for="m in months" :key="m"
                            style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;
                                   color:#48C4D8;border-bottom:1px solid var(--fv-border,#1B3558);
                                   border-left:1px solid var(--fv-border,#1B3558);">
                            {{ fmt(totalCashIn[m]) }}
                        </td>
                    </tr>

                    <template v-if="cashInOpen">

                        <!-- Rent Collection — type parent rows -->
                        <template v-for="(units, typeName) in rentByTypeUnit" :key="'rt-'+typeName">
                            <tr style="background:rgba(11,26,48,0.85);cursor:pointer;user-select:none;"
                                @click="rentTypeOpen[typeName] = !rentTypeOpen[typeName]">
                                <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.85);
                                           padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                           color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                    {{ rentTypeOpen[typeName] ? '▼' : '▶' }}&nbsp;&nbsp;{{ typeName }}
                                </td>
                                <td v-for="m in months" :key="m"
                                    style="padding:8px 12px;text-align:center;font-size:11px;font-weight:600;
                                           color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);
                                           border-left:1px solid var(--fv-border,#1B3558);">
                                    {{ fmt(Object.values(units).reduce((s,u)=>s+n(u[m]),0)) }}
                                </td>
                            </tr>
                            <!-- Unit child rows -->
                            <template v-if="rentTypeOpen[typeName]">
                                <tr v-for="(mMap, unitName) in units" :key="'ru-'+unitName"
                                    style="background:rgba(17,34,64,0.7);">
                                    <td style="position:sticky;left:0;z-index:10;background:rgba(17,34,64,0.7);
                                               padding:6px 16px 6px 44px;font-size:11px;
                                               color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                        {{ unitName }}
                                    </td>
                                    <td v-for="m in months" :key="m"
                                        style="padding:6px 12px;text-align:center;font-size:11px;
                                               color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                               border-left:1px solid var(--fv-border,#1B3558);">
                                        {{ fmt(mMap[m]) }}
                                    </td>
                                </tr>
                            </template>
                        </template>

                        <!-- No rent data -->
                        <tr v-if="Object.keys(rentByTypeUnit).length === 0" style="background:rgba(11,26,48,0.5);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.5);
                                       padding:8px 16px 8px 28px;font-size:11px;font-style:italic;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                Rent Collection — no scheduled collections in period
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:8px 12px;text-align:center;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">—</td>
                        </tr>

                        <!-- Sale Receivables — type parent rows (Phase 2, confirmed
                             July 2026: installment receivables from a unit/property
                             sale flow into Cash Forecast the same way rent does) -->
                        <template v-for="(units, typeName) in saleReceivablesByTypeUnit" :key="'st-'+typeName">
                            <tr style="background:rgba(11,26,48,0.85);cursor:pointer;user-select:none;"
                                @click="saleTypeOpen[typeName] = !saleTypeOpen[typeName]">
                                <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.85);
                                           padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                           color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                    {{ saleTypeOpen[typeName] ? '▼' : '▶' }}&nbsp;&nbsp;{{ typeName }} (Sale Receivables)
                                </td>
                                <td v-for="m in months" :key="m"
                                    style="padding:8px 12px;text-align:center;font-size:11px;font-weight:600;
                                           color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);
                                           border-left:1px solid var(--fv-border,#1B3558);">
                                    {{ fmt(Object.values(units).reduce((s,u)=>s+n(u[m]),0)) }}
                                </td>
                            </tr>
                            <!-- Unit child rows -->
                            <template v-if="saleTypeOpen[typeName]">
                                <tr v-for="(mMap, unitName) in units" :key="'su-'+unitName"
                                    style="background:rgba(17,34,64,0.7);">
                                    <td style="position:sticky;left:0;z-index:10;background:rgba(17,34,64,0.7);
                                               padding:6px 16px 6px 44px;font-size:11px;
                                               color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                        {{ unitName }}
                                    </td>
                                    <td v-for="m in months" :key="m"
                                        style="padding:6px 12px;text-align:center;font-size:11px;
                                               color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                               border-left:1px solid var(--fv-border,#1B3558);">
                                        {{ fmt(mMap[m]) }}
                                    </td>
                                </tr>
                            </template>
                        </template>

                        <!-- Other Collections — header -->
                        <tr style="background:rgba(11,26,48,0.7);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.7);
                                       padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                Other Collections
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:8px 12px;text-align:center;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                {{ fmt(otherCollTotals[m]) }}
                            </td>
                        </tr>

                        <!-- Other Collections rows -->
                        <tr v-for="(row, ri) in otherCollections" :key="'oc-'+ri"
                            style="background:rgba(17,34,64,0.7);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(17,34,64,0.7);
                                       padding:4px 8px 4px 40px;border-bottom:1px solid var(--fv-border,#1B3558);">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <button @click="removeOtherCollection(ri)"
                                        style="color:#ef4444;font-size:11px;cursor:pointer;background:none;border:none;padding:0;">✕</button>
                                    <input v-model="row.name" placeholder="Item name" class="fv-input"
                                        style="border-radius:4px;padding:3px 8px;font-size:11px;width:140px;" />
                                </div>
                            </td>
                            <td v-for="(m, mi) in months" :key="m"
                                style="padding:4px 6px;border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                    <input v-model="row.amounts[m]" type="number" min="0" class="fv-input"
                                        style="border-radius:4px;padding:3px 6px;font-size:11px;text-align:center;width:100px;" />
                                    <button @click="copyRight(row.amounts, mi)"
                                        title="Copy to all months on the right"
                                        style="font-size:10px;color:#BA7517;cursor:pointer;background:none;border:none;line-height:1;">•••</button>
                                </div>
                            </td>
                        </tr>

                        <!-- Add Other Collection button -->
                        <tr style="background:rgba(11,26,48,0.4);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.4);
                                       padding:6px 16px 6px 40px;border-bottom:1px solid var(--fv-border,#1B3558);">
                                <button @click="addOtherCollection"
                                    style="font-size:11px;padding:3px 12px;border-radius:4px;cursor:pointer;
                                           color:#1490A8;border:1px solid #1490A8;background:transparent;">
                                    + Add Row
                                </button>
                            </td>
                            <td v-for="m in months" :key="m"
                                style="border-bottom:1px solid var(--fv-border,#1B3558);border-left:1px solid var(--fv-border,#1B3558);"></td>
                        </tr>

                    </template>

                    <!-- TOTAL CASH IN -->
                    <tr style="background:rgba(20,144,168,0.22);">
                        <td style="position:sticky;left:0;z-index:10;background:rgba(20,144,168,0.22);
                                   padding:12px 16px;font-size:12px;font-weight:800;
                                   text-transform:uppercase;color:#48C4D8;
                                   border-top:2px solid #1490A8;border-bottom:2px solid #1490A8;">
                            Total Cash In
                        </td>
                        <td v-for="m in months" :key="m"
                            style="padding:12px 12px;text-align:center;font-size:12px;font-weight:800;
                                   color:#48C4D8;border-top:2px solid #1490A8;border-bottom:2px solid #1490A8;
                                   border-left:1px solid var(--fv-border,#1B3558);">
                            {{ fmtSigned(totalCashIn[m]) }}
                        </td>
                    </tr>

                    <!-- Spacer -->
                    <tr><td :colspan="months.length + 1" style="height:6px;background:var(--fv-bg,#0C1829);"></td></tr>

                    <!-- ═══════════════════════════ CASH OUT ═══════════════════════════ -->

                    <tr style="background:rgba(186,117,23,0.15);cursor:pointer;user-select:none;"
                        @click="cashOutOpen = !cashOutOpen">
                        <td style="position:sticky;left:0;z-index:10;background:rgba(186,117,23,0.15);
                                   padding:10px 16px;font-size:11px;font-weight:800;
                                   text-transform:uppercase;letter-spacing:.05em;color:#FAC775;
                                   border-bottom:1px solid var(--fv-border,#1B3558);">
                            {{ cashOutOpen ? '▼' : '▶' }}&nbsp;&nbsp;CASH OUT
                        </td>
                        <td v-for="m in months" :key="m"
                            style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;
                                   color:#FAC775;border-bottom:1px solid var(--fv-border,#1B3558);
                                   border-left:1px solid var(--fv-border,#1B3558);">
                            {{ fmt(totalCashOut[m]) }}
                        </td>
                    </tr>

                    <template v-if="cashOutOpen">

                        <!-- Installment Payments — type parent rows -->
                        <template v-for="(units, typeName) in installByTypeUnit" :key="'it-'+typeName">
                            <tr style="background:rgba(11,26,48,0.85);cursor:pointer;user-select:none;"
                                @click="installTypeOpen[typeName] = !installTypeOpen[typeName]">
                                <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.85);
                                           padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                           color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                    {{ installTypeOpen[typeName] ? '▼' : '▶' }}&nbsp;&nbsp;{{ typeName }}
                                </td>
                                <td v-for="m in months" :key="m"
                                    style="padding:8px 12px;text-align:center;font-size:11px;font-weight:600;
                                           color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);
                                           border-left:1px solid var(--fv-border,#1B3558);">
                                    {{ fmt(Object.values(units).reduce((s,u)=>s+n(u[m]),0)) }}
                                </td>
                            </tr>
                            <template v-if="installTypeOpen[typeName]">
                                <tr v-for="(mMap, unitName) in units" :key="'iu-'+unitName"
                                    style="background:rgba(17,34,64,0.7);">
                                    <td style="position:sticky;left:0;z-index:10;background:rgba(17,34,64,0.7);
                                               padding:6px 16px 6px 44px;font-size:11px;
                                               color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                        {{ unitName }}
                                    </td>
                                    <td v-for="m in months" :key="m"
                                        style="padding:6px 12px;text-align:center;font-size:11px;
                                               color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                               border-left:1px solid var(--fv-border,#1B3558);">
                                        {{ fmt(mMap[m]) }}
                                    </td>
                                </tr>
                            </template>
                        </template>

                        <!-- No installment data -->
                        <tr v-if="Object.keys(installByTypeUnit).length === 0" style="background:rgba(11,26,48,0.5);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.5);
                                       padding:8px 16px 8px 28px;font-size:11px;font-style:italic;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                Installment Payments — no pending dues in period
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:8px 12px;text-align:center;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">—</td>
                        </tr>

                        <!-- Expense Payments — one row per expense item -->
                        <tr v-for="(mMap, itemName) in expenseByItem" :key="'ep-'+itemName"
                            style="background:rgba(11,26,48,0.6);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.6);
                                       padding:7px 16px 7px 28px;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                {{ itemName }}
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:7px 12px;text-align:center;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                {{ fmt(mMap[m]) }}
                            </td>
                        </tr>

                        <!-- No expense data -->
                        <tr v-if="Object.keys(expenseByItem).length === 0" style="background:rgba(11,26,48,0.5);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.5);
                                       padding:8px 16px 8px 28px;font-size:11px;font-style:italic;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                Expense Payments — no scheduled payments in period
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:8px 12px;text-align:center;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">—</td>
                        </tr>

                        <!-- Corporate Expenses — one row per expense item, same paid+forecast blend as Expense Payments above -->
                        <tr v-for="(mMap, itemName) in corporateExpenseByItem" :key="'cep-'+itemName"
                            style="background:rgba(11,26,48,0.6);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.6);
                                       padding:7px 16px 7px 28px;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                {{ itemName }} <span style="opacity:0.6;">(Corporate)</span>
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:7px 12px;text-align:center;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                {{ fmt(mMap[m]) }}
                            </td>
                        </tr>

                        <!-- No corporate expense data -->
                        <tr v-if="Object.keys(corporateExpenseByItem).length === 0" style="background:rgba(11,26,48,0.5);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.5);
                                       padding:8px 16px 8px 28px;font-size:11px;font-style:italic;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);">
                                Corporate Expenses — no scheduled payments in period
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:8px 12px;text-align:center;font-size:11px;
                                       color:var(--fv-muted,#6B96B8);border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">—</td>
                        </tr>

                        <!-- Salaries Payment — fixed editable row -->
                        <tr style="background:rgba(11,26,48,0.8);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.8);
                                       padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                Management Fees
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:8px 12px;text-align:center;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                {{ fmt(managementFeeTotals[m]) }}
                            </td>
                        </tr>

                        <!-- Salaries Payment — fixed editable row -->
                        <tr style="background:rgba(11,26,48,0.8);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.8);
                                       padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                Salaries Payment
                            </td>
                            <td v-for="(m, mi) in months" :key="m"
                                style="padding:4px 6px;border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                    <input v-model="salaries[m]" type="number" min="0" class="fv-input"
                                        style="border-radius:4px;padding:3px 6px;font-size:11px;text-align:center;width:100px;" />
                                    <button @click="copySalaryRight(mi)"
                                        title="Copy to all months on the right"
                                        style="font-size:10px;color:#BA7517;cursor:pointer;background:none;border:none;line-height:1;">•••</button>
                                </div>
                            </td>
                        </tr>

                        <!-- New Hirings — click each cell to open popup -->
                        <tr style="background:rgba(11,26,48,0.8);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.8);
                                       padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                New Hirings
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:5px 6px;text-align:center;border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                <button @click.stop="openHiring(m)"
                                    style="width:100px;padding:5px 6px;border-radius:4px;font-size:11px;cursor:pointer;"
                                    :style="{
                                        background: hiringTotals[m] > 0 ? 'rgba(186,117,23,0.25)' : 'rgba(27,53,88,0.7)',
                                        color:      hiringTotals[m] > 0 ? '#FAC775' : '#6B96B8',
                                        border:     hiringTotals[m] > 0 ? '1px solid #BA7517' : '1px solid #1B3558',
                                    }">
                                    {{ hiringTotals[m] > 0 ? fmtSigned(hiringTotals[m]) : '+ Add' }}
                                </button>
                            </td>
                        </tr>

                        <!-- Other Payments — header -->
                        <tr style="background:rgba(11,26,48,0.7);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.7);
                                       padding:8px 16px 8px 28px;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);">
                                Other Payments
                            </td>
                            <td v-for="m in months" :key="m"
                                style="padding:8px 12px;text-align:center;font-size:11px;font-weight:600;
                                       color:#E2E8F0;border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                {{ fmt(otherPayTotals[m]) }}
                            </td>
                        </tr>

                        <!-- Other Payments rows -->
                        <tr v-for="(row, ri) in otherPayments" :key="'op-'+ri"
                            style="background:rgba(17,34,64,0.7);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(17,34,64,0.7);
                                       padding:4px 8px 4px 40px;border-bottom:1px solid var(--fv-border,#1B3558);">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <button @click="removeOtherPayment(ri)"
                                        style="color:#ef4444;font-size:11px;cursor:pointer;background:none;border:none;padding:0;">✕</button>
                                    <input v-model="row.name" placeholder="Item name" class="fv-input"
                                        style="border-radius:4px;padding:3px 8px;font-size:11px;width:140px;" />
                                </div>
                            </td>
                            <td v-for="(m, mi) in months" :key="m"
                                style="padding:4px 6px;border-bottom:1px solid var(--fv-border,#1B3558);
                                       border-left:1px solid var(--fv-border,#1B3558);">
                                <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                    <input v-model="row.amounts[m]" type="number" min="0" class="fv-input"
                                        style="border-radius:4px;padding:3px 6px;font-size:11px;text-align:center;width:100px;" />
                                    <button @click="copyRight(row.amounts, mi)"
                                        title="Copy to all months on the right"
                                        style="font-size:10px;color:#BA7517;cursor:pointer;background:none;border:none;line-height:1;">•••</button>
                                </div>
                            </td>
                        </tr>

                        <!-- Add Other Payment button -->
                        <tr style="background:rgba(11,26,48,0.4);">
                            <td style="position:sticky;left:0;z-index:10;background:rgba(11,26,48,0.4);
                                       padding:6px 16px 6px 40px;border-bottom:1px solid var(--fv-border,#1B3558);">
                                <button @click="addOtherPayment"
                                    style="font-size:11px;padding:3px 12px;border-radius:4px;cursor:pointer;
                                           color:#1490A8;border:1px solid #1490A8;background:transparent;">
                                    + Add Row
                                </button>
                            </td>
                            <td v-for="m in months" :key="m"
                                style="border-bottom:1px solid var(--fv-border,#1B3558);border-left:1px solid var(--fv-border,#1B3558);"></td>
                        </tr>

                    </template>

                    <!-- TOTAL CASH OUT -->
                    <tr style="background:rgba(186,117,23,0.22);">
                        <td style="position:sticky;left:0;z-index:10;background:rgba(186,117,23,0.22);
                                   padding:12px 16px;font-size:12px;font-weight:800;
                                   text-transform:uppercase;color:#FAC775;
                                   border-top:2px solid #BA7517;border-bottom:2px solid #BA7517;">
                            Total Cash Out
                        </td>
                        <td v-for="m in months" :key="m"
                            style="padding:12px 12px;text-align:center;font-size:12px;font-weight:800;
                                   color:#FAC775;border-top:2px solid #BA7517;border-bottom:2px solid #BA7517;
                                   border-left:1px solid var(--fv-border,#1B3558);">
                            {{ fmtSigned(totalCashOut[m]) }}
                        </td>
                    </tr>

                    <!-- Spacer -->
                    <tr><td :colspan="months.length + 1" style="height:6px;background:var(--fv-bg,#0C1829);"></td></tr>

                    <!-- ═══════════ NET MONTHLY CASH FLOW ═══════════ -->
                    <tr style="background:rgba(72,196,216,0.10);">
                        <td style="position:sticky;left:0;z-index:10;background:rgba(72,196,216,0.10);
                                   padding:12px 16px;font-size:12px;font-weight:800;
                                   text-transform:uppercase;color:#E2E8F0;
                                   border-bottom:1px solid var(--fv-border,#1B3558);">
                            Net Monthly Cash Flow
                        </td>
                        <td v-for="m in months" :key="m"
                            style="padding:12px 12px;text-align:center;font-size:12px;font-weight:800;
                                   border-bottom:1px solid var(--fv-border,#1B3558);
                                   border-left:1px solid var(--fv-border,#1B3558);"
                            :style="{ color: netFlow[m] >= 0 ? '#4ade80' : '#f87171' }">
                            {{ fmtSigned(netFlow[m]) }}
                        </td>
                    </tr>

                    <!-- ═══════════ ACCUMULATED CASH FLOW ═══════════ -->
                    <tr style="background:rgba(72,196,216,0.05);">
                        <td style="position:sticky;left:0;z-index:10;background:rgba(72,196,216,0.05);
                                   padding:12px 16px;font-size:12px;font-weight:800;
                                   text-transform:uppercase;color:#E2E8F0;
                                   border-bottom:2px solid var(--fv-border,#1B3558);">
                            Accumulated Cash Flow
                        </td>
                        <td v-for="m in months" :key="m"
                            style="padding:12px 12px;text-align:center;font-size:12px;font-weight:800;
                                   border-bottom:2px solid var(--fv-border,#1B3558);
                                   border-left:1px solid var(--fv-border,#1B3558);"
                            :style="{
                                color:      accumulated[m] >= 0 ? '#4ade80' : '#f87171',
                                background: m === maxGapMonth ? 'rgba(239,68,68,0.20)' : '',
                            }">
                            {{ fmtSigned(accumulated[m]) }}
                            <div v-if="m === maxGapMonth"
                                style="font-size:9px;font-weight:700;color:#ef4444;margin-top:2px;">
                                ▲ MAX GAP
                            </div>
                        </td>
                    </tr>

                    </tbody>
                </table>
                </div>
            </div>

        </div>

        <!-- ══════════════════════ NEW HIRINGS MODAL ══════════════════════ -->
        <Teleport to="body">
            <div v-if="hiringModalOpen"
                style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.75);
                       display:flex;align-items:center;justify-content:center;padding:16px;">
                <div style="background:var(--fv-card,#112240);border:1px solid var(--fv-border,#1B3558);
                             border-radius:12px;width:100%;max-width:480px;
                             box-shadow:0 25px 60px rgba(0,0,0,0.5);">

                    <!-- Modal header -->
                    <div style="display:flex;align-items:center;justify-content:space-between;
                                padding:16px 20px;border-bottom:1px solid var(--fv-border,#1B3558);">
                        <div>
                            <p style="font-size:13px;font-weight:700;color:#48C4D8;margin:0;">New Hirings</p>
                            <p style="font-size:11px;color:var(--fv-muted,#6B96B8);margin:4px 0 0;">
                                {{ fmtLabel(hiringModalMonth) }}
                            </p>
                        </div>
                        <button @click="hiringModalOpen = false"
                            style="font-size:13px;padding:4px 10px;border-radius:6px;cursor:pointer;
                                   color:var(--fv-muted,#6B96B8);border:1px solid var(--fv-border,#1B3558);
                                   background:transparent;">✕</button>
                    </div>

                    <!-- Modal rows -->
                    <div style="padding:16px 20px;max-height:340px;overflow-y:auto;
                                display:flex;flex-direction:column;gap:14px;">
                        <p v-if="(newHirings[hiringModalMonth] || []).length === 0"
                            style="font-size:11px;text-align:center;color:var(--fv-muted,#6B96B8);
                                   padding:20px 0;margin:0;font-style:italic;">
                            No hirings added yet. Click "+ Add Hiring" below.
                        </p>
                        <div v-for="(row, ri) in (newHirings[hiringModalMonth] || [])" :key="ri"
                            style="display:flex;gap:8px;align-items:flex-start;">
                            <button @click="removeHiringRow(ri)"
                                style="color:#ef4444;font-size:11px;margin-top:7px;flex-shrink:0;
                                       cursor:pointer;background:none;border:none;padding:0;">✕</button>
                            <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
                                <input v-model="row.title" placeholder="Title / Position" class="fv-input"
                                    style="border-radius:6px;padding:7px 12px;font-size:12px;width:100%;" />
                                <input v-model="row.amount" type="number" min="0" placeholder="Amount" class="fv-input"
                                    style="border-radius:6px;padding:7px 12px;font-size:12px;width:100%;" />
                                <button @click="copyHiringRight(ri)"
                                    style="font-size:10px;color:#BA7517;cursor:pointer;
                                           background:none;border:none;text-align:left;padding:0;">
                                    ••• Copy this hiring to all future months
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div style="display:flex;align-items:center;justify-content:space-between;
                                padding:12px 20px;border-top:1px solid var(--fv-border,#1B3558);">
                        <button @click="addHiringRow"
                            style="font-size:11px;padding:7px 16px;border-radius:6px;cursor:pointer;
                                   color:#1490A8;border:1px solid #1490A8;background:transparent;font-weight:600;">
                            + Add Hiring
                        </button>
                        <span style="font-size:12px;font-weight:700;color:#FAC775;">
                            Total: {{ fmtSigned(hiringTotals[hiringModalMonth] || 0) }}
                        </span>
                        <button @click="hiringModalOpen = false"
                            style="font-size:11px;padding:7px 20px;border-radius:6px;cursor:pointer;
                                   background:#BA7517;color:white;border:none;font-weight:700;">
                            Done
                        </button>
                    </div>

                </div>
            </div>
        </Teleport>

    </AuthenticatedLayout>
</template>