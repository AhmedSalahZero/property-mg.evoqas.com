<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    company:     Object,
    fromDefault: String,
    toDefault:   String,
})

const companyId = computed(() => props.company?.id)

// ── Period ────────────────────────────────────────────────────────────────
const fromPicker = ref(props.fromDefault)
const toPicker   = ref(props.toDefault)

// ── Server data ───────────────────────────────────────────────────────────
const months            = ref([])
const rentByTypeUnit    = ref({})
const installByTypeUnit = ref({})
const expenseByItem     = ref({})
const loading           = ref(false)

// ── Section collapse — separate refs so Vue tracks them reactively ─────────
const cashInOpen      = ref(true)
const cashOutOpen     = ref(true)
const rentTypeOpen    = ref({})
const installTypeOpen = ref({})

// ── User-entered data ─────────────────────────────────────────────────────
const otherCollections = ref([])
const salaries         = ref({})
const newHirings       = ref({})
const otherPayments    = ref([])

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

const otherCollTotals = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = otherCollections.value.reduce((s, r) => s + n(r.amounts[m]), 0)
    })
    return out
})

const totalCashIn = computed(() => {
    const out = {}
    months.value.forEach(m => { out[m] = rentTotals.value[m] + otherCollTotals.value[m] })
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

const totalCashOut = computed(() => {
    const out = {}
    months.value.forEach(m => {
        out[m] = installTotals.value[m] + expenseTotals.value[m] +
                 salaryTotals.value[m]  + hiringTotals.value[m]  +
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
        const url = route('company.properties.cash-forecast.data', { company: companyId.value })
            + `?from=${fromPicker.value}&to=${toPicker.value}`
        const res  = await fetch(url, { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        if (!res.ok) {
            console.error('CashForecast HTTP error', res.status, await res.text())
            return
        }
        const data = await res.json()

        months.value            = data.months            || []
        rentByTypeUnit.value    = data.rentByTypeUnit    || {}
        installByTypeUnit.value = data.installByTypeUnit || {}
        expenseByItem.value     = data.expenseByItem     || {}

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

        const rt = {}
        Object.keys(rentByTypeUnit.value).forEach(t => { rt[t] = true })
        rentTypeOpen.value = rt

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

watch([fromPicker, toPicker], fetchData)
onMounted(fetchData)

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
                        <span class="text-xs font-semibold uppercase" style="color:var(--fv-muted);">From</span>
                        <input type="month" v-model="fromPicker" class="fv-input rounded-lg px-3 py-1.5 text-sm" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase" style="color:var(--fv-muted);">To</span>
                        <input type="month" v-model="toPicker" class="fv-input rounded-lg px-3 py-1.5 text-sm" />
                    </div>
                    <span v-if="loading" class="text-xs animate-pulse" style="color:#1490A8;">Loading…</span>
                </div>
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