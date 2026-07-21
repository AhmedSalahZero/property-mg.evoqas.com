<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, reactive, computed, h, Teleport, onMounted } from 'vue'
import axios from 'axios'

// ── MonthYearPicker — copied verbatim from InstallmentModal.vue (same
// component used by the real installment plan editor). Per this app's own
// established rule: always copy the exact block, never rewrite from
// scratch — this component has specific quirks (setup()+render()+h(), no
// template string, <Teleport to="body">) that keep it working correctly
// with Vite and never getting clipped by overflow containers. ─────────────
const MonthYearPicker = {
    props: { modelValue: { type: String, default: '' } },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
        const open     = ref(false)
        const viewYear = ref(new Date().getFullYear())
        const popTop   = ref(0)
        const popLeft  = ref(0)

        const display = computed(() => {
            if (!props.modelValue) return ''
            const [m, y] = props.modelValue.split('/')
            if (!m || !y) return props.modelValue
            return `${MONTHS[parseInt(m) - 1]} ${y}`
        })

        function toggle(e) {
            if (open.value) { open.value = false; return }
            const rect = e.currentTarget.getBoundingClientRect()
            const popH = 192
            popTop.value  = rect.bottom + popH > window.innerHeight ? rect.top - popH - 4 : rect.bottom + 4
            popLeft.value = rect.left
            viewYear.value = props.modelValue
                ? parseInt(props.modelValue.split('/')[1]) || new Date().getFullYear()
                : new Date().getFullYear()
            open.value = true
            setTimeout(() => {
                const handler = () => { open.value = false; document.removeEventListener('click', handler) }
                document.addEventListener('click', handler)
            }, 0)
        }
        function pick(idx) {
            emit('update:modelValue', `${String(idx + 1).padStart(2, '0')}/${viewYear.value}`)
            open.value = false
        }
        function clear() { emit('update:modelValue', ''); open.value = false }
        function isActive(idx) {
            if (!props.modelValue) return false
            const [m, y] = props.modelValue.split('/')
            return parseInt(m) - 1 === idx && parseInt(y) === viewYear.value
        }

        return { open, viewYear, popTop, popLeft, display, toggle, pick, clear, isActive, MONTHS }
    },
    render() {
        const { open, viewYear, popTop, popLeft, display, toggle, pick, clear, isActive, MONTHS } = this

        const trigger = h('div', {
            class: 'fv-input flex items-center justify-between rounded-lg px-3 py-2 cursor-pointer text-sm',
            onClick: toggle,
        }, [
            h('span', {
                style: display ? 'color:var(--fv-text-primary)' : 'color:var(--fv-text-muted)',
            }, display || 'MM/YYYY'),
            h('svg', {
                class: 'w-3.5 h-3.5 ml-2 flex-shrink-0',
                style: 'color:var(--fv-text-muted)',
                fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24',
            }, [
                h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2',
                    d: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' })
            ]),
        ])

        const popup = open ? h(Teleport, { to: 'body' }, [
            h('div', {
                onClick: (e) => e.stopPropagation(),
                style: `position:fixed;z-index:9999;width:224px;top:${popTop}px;left:${popLeft}px;` +
                    'background:var(--fv-bg-modal,#0E1E34);border:1px solid var(--fv-border,#21518B);' +
                    'border-radius:0.5rem;padding:0.75rem;box-shadow:0 8px 40px rgba(0,0,0,0.7);',
            }, [
                // Year navigation
                h('div', { style: 'display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;' }, [
                    h('button', {
                        onClick: (e) => { e.stopPropagation(); this.viewYear-- },
                        style: 'width:1.875rem;height:1.875rem;display:flex;align-items:center;justify-content:center;border-radius:0.45rem;color:var(--fv-text-muted);background:transparent;border:1px solid transparent;cursor:pointer;font-size:1rem;',
                    }, '‹'),
                    h('span', { style: 'font-size:0.875rem;font-weight:600;color:var(--fv-text-primary);' }, viewYear),
                    h('button', {
                        onClick: (e) => { e.stopPropagation(); this.viewYear++ },
                        style: 'width:1.875rem;height:1.875rem;display:flex;align-items:center;justify-content:center;border-radius:0.45rem;color:var(--fv-text-muted);background:transparent;border:1px solid transparent;cursor:pointer;font-size:1rem;',
                    }, '›'),
                ]),
                // Month grid
                h('div', { style: 'display:grid;grid-template-columns:repeat(3,1fr);gap:0.25rem;' },
                    MONTHS.map((m, i) => h('button', {
                        key: i,
                        onClick: (e) => { e.stopPropagation(); pick(i) },
                        style: isActive(i)
                            ? 'padding:0.25rem;border-radius:0.35rem;font-size:0.75rem;font-weight:500;border:none;cursor:pointer;background:var(--fv-blue);color:#fff;'
                            : 'padding:0.25rem;border-radius:0.35rem;font-size:0.75rem;font-weight:500;border:none;cursor:pointer;background:transparent;color:var(--fv-text-primary);',
                    }, m))
                ),
                // Clear button
                h('button', {
                    onClick: (e) => { e.stopPropagation(); clear() },
                    style: 'margin-top:0.5rem;width:100%;font-size:0.75rem;color:var(--fv-text-muted);background:transparent;border:none;cursor:pointer;text-align:center;',
                }, 'Clear'),
            ])
        ]) : null

        return h('div', {}, [trigger, popup])
    },
}

const props = defineProps({
  company:           Object,
  prospect:          Object,
  baseCurrency:      String,
  statusLabels:      Object,
  scenarioDefaults:  Object, // { conservative: {...}, base: {...}, optimistic: {...} }
  sharedDefaults:    Object,
  bankLoanDefaults:  Object,
})

const isMultiUnit = props.prospect.is_multi_unit

// ── Decision status — set here, on the workspace, AFTER seeing the
// numbers below, never guessed at on the entry form (confirmed design
// decision, July 2026). Purely a label for the user's own tracking. ────
const currentStatus = ref(props.prospect.status)
const statusColor = (status) => ({
  evaluating: '#eab308',
  pursuing:   '#3b82f6',
  passed:     'var(--fv-text-muted)',
  acquired:   '#22c55e',
}[status] ?? 'var(--fv-text-muted)')
const settingStatus = ref(false)

async function setStatus(newStatus) {
  settingStatus.value = true
  try {
    const { data } = await axios.patch(
      route('company.properties.investment-decision.update-status', [props.company.id, props.prospect.id]),
      { status: newStatus }
    )
    currentStatus.value = data.prospect.status
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to update status.')
  } finally {
    settingStatus.value = false
  }
}

// ── Shared assumptions ──────────────────────────────────────────────────
const shared = reactive({
  exit_year:                props.sharedDefaults.exit_year,
  discount_rate_pct:        props.sharedDefaults.discount_rate_pct,
  corporate_tax_rate_pct:   props.sharedDefaults.corporate_tax_rate_pct,
  selling_costs_pct:        props.sharedDefaults.selling_costs_pct,
  exit_value_method:        props.sharedDefaults.exit_value_method,
  rent_collection_interval: props.sharedDefaults.rent_collection_interval,
  inflation_rate_pct:       props.sharedDefaults.inflation_rate_pct,
})

const COLLECTION_INTERVAL_LABELS = {
  monthly: 'Monthly', quarterly: 'Quarterly', semi_annually: 'Semi-Annually', annually: 'Annually',
}

// ── Per-scenario assumptions (deep copy of defaults so edits don't mutate props) ──
const scenarios = reactive(JSON.parse(JSON.stringify(props.scenarioDefaults)))
const scenarioLabels = { conservative: 'Conservative', base: 'Base Case', optimistic: 'Optimistic' }
const scenarioColors = { conservative: '#eab308', base: 'var(--fv-gold)', optimistic: '#22c55e' }

// ── Funding path ─────────────────────────────────────────────────────────
const FUNDING_PATHS = [
  { key: 'cash_purchase', label: 'Cash Purchase', available: true, blurb: 'Full price paid at Year 0. No financing.' },
  { key: 'bank_loan',     label: 'Bank Loan',      available: true, blurb: 'Down payment + monthly principal & interest.' },
  { key: 'seller_installments', label: 'Seller / Developer Installments', available: true, blurb: 'Signing, reservation, installment rows, annual/delivery/maintenance.' },
  { key: 'custom_schedule',     label: 'Custom Payment Schedule',        available: true, blurb: 'Irregular, user-defined dates and amounts.' },
  { key: 'contractor_deal',     label: 'Contractor Development Deal',    available: true, blurb: 'Contractor takes a % of rent and/or a % of sale price if sold.' },
]
const fundingPath = ref('cash_purchase')

const bankLoan = reactive({
  down_payment_pct:     props.bankLoanDefaults.down_payment_pct,
  annual_rate:           props.bankLoanDefaults.annual_rate,
  term_months:           props.bankLoanDefaults.term_months,
  grace_months:          props.bankLoanDefaults.grace_months,
})

// ── Seller / Developer Installments — same shape as a real Regular-Mode
// installment plan. Dates here are "MM/YYYY" text, same convention as the
// rest of this app's installment/rent-contract date fields. ─────────────
const regularPlan = reactive({
  signing_amount: '',
  signing_date: '',
  reservation_amount: '',
  reservation_date: '',
  installment_rows: [],
  has_annual: false,
  annual_start_date: '',
  annual_amount: '',
  annual_count: '',
  has_delivery: false,
  delivery_start_date: '',
  delivery_amount: '',
  delivery_count: '',
  delivery_interval: 'monthly',
  has_maintenance: false,
  maintenance_start_date: '',
  maintenance_amount: '',
  maintenance_count: '',
  maintenance_interval: 'monthly',
})

function addInstallmentRow() {
  regularPlan.installment_rows.push({ amount: '', count: '', start_date: '', interval: 'monthly' })
}
function removeInstallmentRow(idx) {
  regularPlan.installment_rows.splice(idx, 1)
}

// ── Custom Payment Schedule, and Contractor Deal's own construction draws
// — same free-form {date, amount, notes} rows either way (confirmed reuse,
// July 2026 planning session). ───────────────────────────────────────────
const customRows = reactive([])
function addCustomRow() {
  customRows.push({ date: '', amount: '', notes: '' })
}
function removeCustomRow(idx) {
  customRows.splice(idx, 1)
}
const customRowsTotal = () => customRows.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0)

// Contractor Development Deal only.
// Contractor Development Deal only — either, both, or neither of these
// can be filled in (confirmed July 2026): a % of annual rent for a set
// number of years starting from Year 1, and/or a % of the full sale price
// (owed only if sold).
const contractorFeePct = ref(0)
const contractorRentSharePct = ref(0)
const contractorRentShareYears = ref(0)

// ── Compute ──────────────────────────────────────────────────────────────
const computing = ref(false)
const computeError = ref('')
const result = ref(null)       // raw API response
const lastComputedFundingParams = ref({}) // remembers what was actually sent, so Save Snapshot reuses it exactly
const expandedScenario = ref(null) // which scenario's year-by-year table is open

const fmt = (v) => v == null ? '—' : Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 })
const fmtPct = (v) => v == null ? '—' : Number(v).toFixed(1) + '%'

async function compute() {
  computeError.value = ''

  // Fix for a real bug (July 2026): a cleared Loan Term field used to
  // silently reach the server as an empty value and produce a nonsense
  // result. Caught here now, before the request is even sent — the
  // engine also has its own independent safety net, but stopping it here
  // means the person sees the problem immediately instead of a confusing
  // result a few seconds later.
  if (fundingPath.value === 'bank_loan') {
    if (!bankLoan.term_months || bankLoan.term_months < 1) {
      computeError.value = 'Loan Term (months) is required for a Bank Loan and must be at least 1 — please fill it in.'
      return
    }
    if (bankLoan.down_payment_pct === '' || bankLoan.down_payment_pct == null) {
      computeError.value = 'Down Payment % is required for a Bank Loan — please fill it in.'
      return
    }
    if (bankLoan.annual_rate === '' || bankLoan.annual_rate == null) {
      computeError.value = 'Annual Interest Rate % is required for a Bank Loan — please fill it in.'
      return
    }
  }

  if (fundingPath.value === 'seller_installments') {
    const hasSomething = regularPlan.signing_amount || regularPlan.reservation_amount || regularPlan.installment_rows.length > 0
    if (!hasSomething) {
      computeError.value = 'Add at least a signing amount, reservation amount, or one installment row for Seller/Developer Installments.'
      return
    }
  }

  if (fundingPath.value === 'custom_schedule' || fundingPath.value === 'contractor_deal') {
    const validRows = customRows.filter(r => r.date && parseFloat(r.amount) > 0)
    if (validRows.length === 0) {
      computeError.value = fundingPath.value === 'contractor_deal'
        ? 'Add at least one construction draw (date + amount) for RAM\'s funding of the build.'
        : 'Add at least one payment (date + amount) to the Custom Payment Schedule.'
      return
    }
  }

  computing.value = true
  result.value = null
  expandedScenario.value = null

  let fundingParams = {}
  if (fundingPath.value === 'bank_loan') {
    fundingParams = { ...bankLoan }
  } else if (fundingPath.value === 'seller_installments') {
    fundingParams = { regular_plan: { ...regularPlan } }
  } else if (fundingPath.value === 'custom_schedule') {
    fundingParams = { custom_rows: customRows.filter(r => r.date && parseFloat(r.amount) > 0) }
  } else if (fundingPath.value === 'contractor_deal') {
    fundingParams = {
      custom_rows: customRows.filter(r => r.date && parseFloat(r.amount) > 0),
      contractor_fee_pct: contractorFeePct.value,
      contractor_rent_share_pct: contractorRentSharePct.value,
      contractor_rent_share_years: contractorRentShareYears.value,
    }
  }

  const payload = {
    exit_year:                shared.exit_year,
    discount_rate_pct:        shared.discount_rate_pct,
    corporate_tax_rate_pct:   shared.corporate_tax_rate_pct,
    selling_costs_pct:        shared.selling_costs_pct,
    exit_value_method:        shared.exit_value_method,
    rent_collection_interval: shared.rent_collection_interval,
    inflation_rate_pct:       shared.inflation_rate_pct,
    scenarios:                scenarios,
    funding_path:              fundingPath.value,
    funding_params:            fundingParams,
  }

  try {
    const { data } = await axios.post(
      route('company.properties.investment-decision.compute', [props.company.id, props.prospect.id]),
      payload
    )
    result.value = data
    lastComputedFundingParams.value = fundingParams
  } catch (e) {
    computeError.value = e.response?.data?.message || 'Something went wrong computing this analysis.'
  } finally {
    computing.value = false
  }
}

// ── Phase 4 — Save / Share Snapshots ────────────────────────────────────
const savedAnalyses = ref([])
const loadingAnalyses = ref(false)
const snapshotLabel = ref('')
const analystRecommendation = ref('')
const saving = ref(false)
const saveError = ref('')
const saveSuccess = ref('')
const shareLinks = reactive({}) // { [analysisId]: fullUrl }
const generatingToken = ref(null)

async function loadAnalyses() {
  loadingAnalyses.value = true
  try {
    const { data } = await axios.get(route('company.properties.investment-decision.analyses.index', [props.company.id, props.prospect.id]))
    savedAnalyses.value = data.analyses
  } catch (e) {
    // Non-critical — the workspace still works fine without the saved list.
  } finally {
    loadingAnalyses.value = false
  }
}
onMounted(loadAnalyses)

async function saveSnapshot() {
  if (!result.value) {
    saveError.value = 'Compute the three scenarios first, then save.'
    return
  }
  saving.value = true
  saveError.value = ''
  saveSuccess.value = ''

  // Same payload compute() already sent — the server re-runs the exact
  // same computation and saves the verified result, it never trusts a
  // number from this screen directly.
  const payload = {
    exit_year:                shared.exit_year,
    discount_rate_pct:        shared.discount_rate_pct,
    corporate_tax_rate_pct:   shared.corporate_tax_rate_pct,
    selling_costs_pct:        shared.selling_costs_pct,
    exit_value_method:        shared.exit_value_method,
    rent_collection_interval: shared.rent_collection_interval,
    inflation_rate_pct:       shared.inflation_rate_pct,
    scenarios:                scenarios,
    funding_path:             fundingPath.value,
    funding_params:           lastComputedFundingParams.value,
    snapshot_label:           snapshotLabel.value || null,
    analyst_recommendation:   analystRecommendation.value || null,
  }

  try {
    await axios.post(route('company.properties.investment-decision.analyses.store', [props.company.id, props.prospect.id]), payload)
    saveSuccess.value = 'Snapshot saved.'
    snapshotLabel.value = ''
    await loadAnalyses()
  } catch (e) {
    saveError.value = e.response?.data?.message || 'Failed to save this snapshot.'
  } finally {
    saving.value = false
  }
}

async function deleteSnapshot(analysis) {
  if (!confirm(`Delete the snapshot "${analysis.snapshot_label || 'Untitled'}"? This cannot be undone.`)) return
  try {
    await axios.delete(route('company.properties.investment-decision.analyses.destroy', [props.company.id, props.prospect.id, analysis.id]))
    savedAnalyses.value = savedAnalyses.value.filter(a => a.id !== analysis.id)
  } catch (e) {
    alert('Failed to delete this snapshot.')
  }
}

async function shareSnapshot(analysis) {
  generatingToken.value = analysis.id
  try {
    const { data } = await axios.post(route('company.properties.investment-decision.analyses.generate-token', [props.company.id, props.prospect.id, analysis.id]))
    const url = `${window.location.origin}/investment-decision/share/${data.token}`
    shareLinks[analysis.id] = url
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).catch(() => {})
    }
  } catch (e) {
    alert('Failed to generate a share link.')
  } finally {
    generatingToken.value = null
  }
}

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) : ''

const npvVerdict = (npv) => {
  if (npv == null) return { label: '—', color: 'var(--fv-text-muted)' }
  return npv >= 0
    ? { label: 'NPV Positive', color: '#22c55e' }
    : { label: 'NPV Negative', color: '#ef4444' }
}

const bestNpvScenario = computed(() => {
  if (!result.value) return null
  const entries = Object.entries(result.value.result.scenarios)
  return entries.reduce((best, [key, s]) => (!best || s.npv > best[1].npv ? [key, s] : best), null)?.[0]
})
</script>

<template>
  <Head :title="`Feasibility Study — ${prospect.prospect_name}`" />
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold fv-text-primary">{{ prospect.prospect_name }}</h1>
            <span class="fv-badge text-xs" :style="{ color: statusColor(currentStatus) }">{{ statusLabels[currentStatus] }}</span>
          </div>
          <p class="fv-text-muted text-sm mt-1">
            {{ fmt(prospect.total_purchase_price) }} {{ prospect.currency }} purchase price ·
            {{ fmt(prospect.total_expected_monthly_rent) }} {{ prospect.currency }} / month expected rent (once stabilized)
            <span v-if="isMultiUnit"> · {{ prospect.unit_count }} unit{{ prospect.unit_count === 1 ? '' : 's' }}</span>
            <span v-if="prospect.currency !== baseCurrency"> — converted to {{ baseCurrency }} when computed</span>
          </p>
        </div>
        <Link :href="route('company.properties.investment-decision.index', company.id)" class="fv-btn-secondary rounded-lg px-4 py-2 text-sm font-semibold">
          Back to Prospects
        </Link>
      </div>

      <!-- ── Saved Snapshots — always visible, not tied to today's compute ── -->
      <div v-if="savedAnalyses.length > 0" class="fv-card rounded-xl p-5 mb-5 overflow-x-auto">
        <h2 class="fv-text-primary font-semibold text-sm mb-3">Saved Snapshots</h2>
        <table class="w-full text-xs">
          <thead>
            <tr class="fv-border" style="border-bottom-width:1px;">
              <th class="text-left py-2 px-2 fv-text-label">Label</th>
              <th class="text-left py-2 px-2 fv-text-label">Funding Path</th>
              <th class="text-right py-2 px-2 fv-text-label">NPV (Base Case)</th>
              <th class="text-right py-2 px-2 fv-text-label">IRR (Base Case)</th>
              <th class="text-left py-2 px-2 fv-text-label">Saved</th>
              <th class="text-right py-2 px-2 fv-text-label">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in savedAnalyses" :key="a.id" class="fv-border" style="border-bottom-width:1px;">
              <td class="py-2 px-2 fv-text-primary">{{ a.snapshot_label || 'Untitled' }}</td>
              <td class="py-2 px-2 fv-text-muted">{{ FUNDING_PATHS.find(fp => fp.key === a.funding_path)?.label || a.funding_path }}</td>
              <td class="py-2 px-2 text-right" :style="{ color: (a.npv_base_case ?? 0) >= 0 ? '#22c55e' : '#ef4444' }">{{ fmt(a.npv_base_case) }}</td>
              <td class="py-2 px-2 text-right fv-text-primary">{{ a.irr_base_case != null ? fmtPct(a.irr_base_case) : 'N/A' }}</td>
              <td class="py-2 px-2 fv-text-muted">{{ fmtDate(a.created_at) }}</td>
              <td class="py-2 px-2 text-right">
                <div class="flex justify-end items-center gap-2">
                  <button @click="shareSnapshot(a)" class="fv-btn-secondary rounded-lg px-2.5 py-1 text-xs font-semibold" :disabled="generatingToken === a.id">
                    {{ generatingToken === a.id ? '…' : (shareLinks[a.id] ? 'Copied!' : 'Share') }}
                  </button>
                  <button @click="deleteSnapshot(a)" class="fv-action-btn fv-action-btn-danger" title="Delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
                <div v-if="shareLinks[a.id]" class="fv-text-muted text-xs mt-1 text-right" style="word-break:break-all;">{{ shareLinks[a.id] }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Unit breakdown, multi-unit only -->
      <div v-if="isMultiUnit" class="fv-card rounded-xl p-5 mb-5">
        <h2 class="fv-text-primary font-semibold text-sm mb-3">Units in This Deal</h2>
        <table class="w-full text-xs">
          <thead>
            <tr class="fv-border" style="border-bottom-width:1px;">
              <th class="text-left py-2 px-2 fv-text-label">Unit</th>
              <th class="text-left py-2 px-2 fv-text-label">Category / Type</th>
              <th class="text-right py-2 px-2 fv-text-label">Area</th>
              <th class="text-right py-2 px-2 fv-text-label">Purchase Price</th>
              <th class="text-right py-2 px-2 fv-text-label">Expected Rent/mo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in prospect.units" :key="u.id" class="fv-border" style="border-bottom-width:1px;">
              <td class="py-2 px-2 fv-text-primary">{{ u.unit_name }}</td>
              <td class="py-2 px-2 fv-text-muted">{{ u.property_category?.category_name || '—' }}<span v-if="u.property_type"> · {{ u.property_type.type_name }}</span></td>
              <td class="py-2 px-2 text-right fv-text-muted">{{ u.area ?? '—' }}</td>
              <td class="py-2 px-2 text-right fv-text-primary">{{ fmt(u.purchase_price) }}</td>
              <td class="py-2 px-2 text-right fv-text-primary">{{ fmt(u.expected_monthly_rent) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Shared assumptions -->
      <div class="fv-card rounded-xl p-5 mb-5">
        <h2 class="fv-text-primary font-semibold text-sm mb-3">Shared Assumptions <span class="fv-text-muted font-normal">— same across all three scenarios, for a fair comparison</span></h2>
        <div class="grid grid-cols-3 gap-4 mb-4">
          <div>
            <label class="fv-label">Exit Year</label>
            <select v-model.number="shared.exit_year" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
              <option v-for="y in [3,4,5,6,7,8,9,10]" :key="y" :value="y">Year {{ y }}</option>
            </select>
          </div>
          <div>
            <label class="fv-label">Discount Rate %</label>
            <input v-model.number="shared.discount_rate_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Exit Value Method</label>
            <select v-model="shared.exit_value_method" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
              <option value="appreciation">Market Appreciation</option>
              <option value="cap_rate">Income Cap Rate</option>
              <option value="higher_of">Higher Of</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-4 gap-4">
          <div>
            <label class="fv-label">Corporate Tax %</label>
            <input v-model.number="shared.corporate_tax_rate_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Selling Costs %</label>
            <input v-model.number="shared.selling_costs_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Rent Collection Interval</label>
            <select v-model="shared.rent_collection_interval" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
              <option v-for="(label, key) in COLLECTION_INTERVAL_LABELS" :key="key" :value="key">{{ label }}</option>
            </select>
            <p class="fv-text-muted text-xs mt-1">Collected in advance. Longer intervals discount slightly better — bigger lump sums land earlier.</p>
          </div>
          <div>
            <label class="fv-label">Inflation Rate % (beyond scheduled data)</label>
            <input v-model.number="shared.inflation_rate_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
            <p class="fv-text-muted text-xs mt-1">Used only for Company Cash Flow Impact, once your existing scheduled data runs out.</p>
          </div>
        </div>
      </div>

      <!-- Funding path -->
      <div class="fv-card rounded-xl p-5 mb-5">
        <h2 class="fv-text-primary font-semibold text-sm mb-3">Funding Path</h2>
        <div class="grid grid-cols-5 gap-3 mb-4">
          <button
            v-for="fp in FUNDING_PATHS" :key="fp.key"
            @click="fp.available && (fundingPath = fp.key)"
            :disabled="!fp.available"
            class="text-left p-3 rounded-lg border text-xs transition"
            :style="{
              borderColor: fundingPath === fp.key ? 'var(--fv-gold)' : 'var(--fv-border)',
              background: fundingPath === fp.key ? 'rgba(186,117,23,0.08)' : 'var(--fv-bg)',
              opacity: fp.available ? 1 : 0.5,
              cursor: fp.available ? 'pointer' : 'not-allowed',
            }"
          >
            <div class="font-semibold fv-text-primary">{{ fp.label }}</div>
            <div class="fv-text-muted mt-1">{{ fp.available ? fp.blurb : 'Coming in Phase 2' }}</div>
          </button>
        </div>

        <div v-if="fundingPath === 'bank_loan'" class="grid grid-cols-4 gap-4 pt-2" style="border-top:1px solid var(--fv-border);">
          <div>
            <label class="fv-label">Down Payment %</label>
            <input v-model.number="bankLoan.down_payment_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Annual Interest Rate %</label>
            <input v-model.number="bankLoan.annual_rate" type="number" step="0.1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Loan Term (months)</label>
            <input v-model.number="bankLoan.term_months" type="number" step="1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Grace Period (months)</label>
            <input v-model.number="bankLoan.grace_months" type="number" step="1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>

        <!-- ── Seller / Developer Installments — Regular Mode ──────────── -->
        <div v-if="fundingPath === 'seller_installments'" class="pt-3 space-y-4" style="border-top:1px solid var(--fv-border);">
          <p class="fv-text-muted text-xs">Click a date field to pick month and year — same calendar picker used in your real Installment Plans.</p>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="fv-label text-xs">Signing Amount</label>
              <input v-model.number="regularPlan.signing_amount" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="fv-label text-xs">Signing Date</label>
              <MonthYearPicker v-model="regularPlan.signing_date" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="fv-label text-xs">Reservation Amount</label>
              <input v-model.number="regularPlan.reservation_amount" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="fv-label text-xs">Reservation Date</label>
              <MonthYearPicker v-model="regularPlan.reservation_date" />
            </div>
          </div>

          <div class="flex items-center justify-between">
            <label class="fv-label text-xs">Installment Rows</label>
            <button type="button" @click="addInstallmentRow" class="fv-btn-secondary rounded-lg px-3 py-1 text-xs font-semibold">+ Add Row</button>
          </div>
          <div v-for="(row, idx) in regularPlan.installment_rows" :key="idx" class="grid gap-2 items-end" style="grid-template-columns: 1fr 1fr 1fr 1fr auto;">
            <div>
              <label class="fv-label text-xs">Amount</label>
              <input v-model.number="row.amount" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
            </div>
            <div>
              <label class="fv-label text-xs">Count</label>
              <input v-model.number="row.count" type="number" min="1" step="1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
            </div>
            <div>
              <label class="fv-label text-xs">Start Date</label>
              <MonthYearPicker v-model="row.start_date" />
            </div>
            <div>
              <label class="fv-label text-xs">Interval</label>
              <select v-model="row.interval" class="fv-select w-full rounded-lg px-2 py-1.5 text-xs">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="semi_annually">Semi-Annually</option>
              </select>
            </div>
            <button type="button" @click="removeInstallmentRow(idx)" class="fv-action-btn fv-action-btn-danger" title="Remove">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <label class="flex items-center gap-2 text-xs fv-text-label">
              <input v-model="regularPlan.has_annual" type="checkbox" /> Annual Payment
            </label>
            <label class="flex items-center gap-2 text-xs fv-text-label">
              <input v-model="regularPlan.has_delivery" type="checkbox" /> Delivery Payment
            </label>
            <label class="flex items-center gap-2 text-xs fv-text-label">
              <input v-model="regularPlan.has_maintenance" type="checkbox" /> Maintenance Payment
            </label>
          </div>

          <div v-if="regularPlan.has_annual" class="grid grid-cols-3 gap-3 p-3 rounded-lg" style="background:var(--fv-bg); border:1px solid var(--fv-border);">
            <div><label class="fv-label text-xs">Annual Amount</label><input v-model.number="regularPlan.annual_amount" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" /></div>
            <div><label class="fv-label text-xs">Start Date</label><MonthYearPicker v-model="regularPlan.annual_start_date" /></div>
            <div><label class="fv-label text-xs">Number of Years</label><input v-model.number="regularPlan.annual_count" type="number" min="1" step="1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" /></div>
          </div>

          <div v-if="regularPlan.has_delivery" class="grid grid-cols-4 gap-3 p-3 rounded-lg" style="background:var(--fv-bg); border:1px solid var(--fv-border);">
            <div><label class="fv-label text-xs">Delivery Amount</label><input v-model.number="regularPlan.delivery_amount" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" /></div>
            <div><label class="fv-label text-xs">Start Date</label><MonthYearPicker v-model="regularPlan.delivery_start_date" /></div>
            <div><label class="fv-label text-xs">Count</label><input v-model.number="regularPlan.delivery_count" type="number" min="1" step="1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" /></div>
            <div><label class="fv-label text-xs">Interval</label>
              <select v-model="regularPlan.delivery_interval" class="fv-select w-full rounded-lg px-2 py-1.5 text-xs">
                <option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="semi_annually">Semi-Annually</option>
              </select>
            </div>
          </div>

          <div v-if="regularPlan.has_maintenance" class="grid grid-cols-4 gap-3 p-3 rounded-lg" style="background:var(--fv-bg); border:1px solid var(--fv-border);">
            <div><label class="fv-label text-xs">Maintenance Amount</label><input v-model.number="regularPlan.maintenance_amount" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" /></div>
            <div><label class="fv-label text-xs">Start Date</label><MonthYearPicker v-model="regularPlan.maintenance_start_date" /></div>
            <div><label class="fv-label text-xs">Count</label><input v-model.number="regularPlan.maintenance_count" type="number" min="1" step="1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" /></div>
            <div><label class="fv-label text-xs">Interval</label>
              <select v-model="regularPlan.maintenance_interval" class="fv-select w-full rounded-lg px-2 py-1.5 text-xs">
                <option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="semi_annually">Semi-Annually</option>
              </select>
            </div>
          </div>

          <p class="fv-text-muted text-xs">If this schedule runs longer than your chosen Exit Year, whatever's still unpaid is deducted from sale proceeds at exit — same treatment as an outstanding bank loan balance.</p>
        </div>

        <!-- ── Custom Payment Schedule / Contractor Deal's construction draws ── -->
        <div v-if="fundingPath === 'custom_schedule' || fundingPath === 'contractor_deal'" class="pt-3 space-y-3" style="border-top:1px solid var(--fv-border);">
          <p class="fv-text-muted text-xs" v-if="fundingPath === 'contractor_deal'">RAM's own construction draw schedule — the amounts RAM pays out to fund the build.</p>

          <div class="flex items-center justify-between">
            <label class="fv-label text-xs">Payment Rows</label>
            <button type="button" @click="addCustomRow" class="fv-btn-secondary rounded-lg px-3 py-1 text-xs font-semibold">+ Add Row</button>
          </div>
          <div v-for="(row, idx) in customRows" :key="idx" class="grid gap-2 items-end" style="grid-template-columns: 1fr 1fr 1.4fr auto;">
            <div>
              <label class="fv-label text-xs">Date</label>
              <input v-model="row.date" type="date" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
            </div>
            <div>
              <label class="fv-label text-xs">Amount</label>
              <input v-model.number="row.amount" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
            </div>
            <div>
              <label class="fv-label text-xs">Notes</label>
              <input v-model="row.notes" type="text" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" placeholder="optional" />
            </div>
            <button type="button" @click="removeCustomRow(idx)" class="fv-action-btn fv-action-btn-danger" title="Remove">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
          <div v-if="customRows.length > 0" class="text-xs fv-text-muted text-right">Total: {{ fmt(customRowsTotal()) }}</div>

          <div v-if="fundingPath === 'contractor_deal'" class="pt-3 space-y-4" style="border-top:1px solid var(--fv-border);">
            <p class="fv-text-muted text-xs">The contractor can take either, both, or neither of these — leave a field at 0 to skip it.</p>

            <div>
              <label class="fv-label text-xs">Rent Share — % of Annual Rent</label>
              <div class="flex items-center gap-3" style="max-width:420px;">
                <input v-model.number="contractorRentSharePct" type="number" min="0" step="0.1" class="fv-input rounded-lg px-3 py-2 text-sm" style="width:140px;" />
                <span class="fv-text-muted text-xs">for</span>
                <input v-model.number="contractorRentShareYears" type="number" min="0" step="1" class="fv-input rounded-lg px-3 py-2 text-sm" style="width:100px;" />
                <span class="fv-text-muted text-xs">years, starting from Year 1 (rent start)</span>
              </div>
              <p class="fv-text-muted text-xs mt-1">Earned every one of those years regardless of whether RAM ever sells — a running cost on rental income, like a management fee.</p>
            </div>

            <div>
              <label class="fv-label text-xs">Sale Price Share — % of Full Sale Price</label>
              <input v-model.number="contractorFeePct" type="number" min="0" step="0.1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" style="max-width:220px;" />
              <p class="fv-text-muted text-xs mt-1">Paid only if/when RAM sells — calculated on the full sale price, not profit above cost. If RAM holds and leases instead, the contractor gets nothing from this share.</p>
            </div>
          </div>

          <p class="fv-text-muted text-xs" v-if="fundingPath === 'custom_schedule'">If this schedule runs longer than your chosen Exit Year, whatever's still unpaid is deducted from sale proceeds at exit — same treatment as an outstanding bank loan balance.</p>
        </div>
      </div>

      <!-- Scenario assumptions -->
      <div class="fv-card rounded-xl p-5 mb-5">
        <h2 class="fv-text-primary font-semibold text-sm mb-1">Scenario Assumptions <span class="fv-text-muted font-normal">— pre-filled, fully editable</span></h2>
        <p class="fv-text-muted text-xs mb-3">
          <template v-if="isMultiUnit">This deal has multiple units, so each scenario models a lease-up curve — how long the whole {{ prospect.nature }} takes to fill up.</template>
          <template v-else>This is a single unit, so each scenario models a simple vacancy period — how many months it sits empty before the first tenant.</template>
        </p>
        <div class="grid grid-cols-3 gap-4">
          <div v-for="key in ['conservative','base','optimistic']" :key="key" class="p-4 rounded-lg" style="background:var(--fv-bg); border:1px solid var(--fv-border);">
            <div class="font-semibold text-sm mb-3" :style="{ color: scenarioColors[key] }">{{ scenarioLabels[key] }}</div>
            <div class="space-y-2.5">
              <div>
                <label class="fv-label text-xs">Rent Growth %/yr</label>
                <input v-model.number="scenarios[key].rent_growth_rate_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
              </div>

              <template v-if="!isMultiUnit">
                <div>
                  <label class="fv-label text-xs">Months Vacant Before First Tenant</label>
                  <input v-model.number="scenarios[key].months_vacant" type="number" step="1" min="0" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
                </div>
              </template>
              <template v-else>
                <div>
                  <label class="fv-label text-xs">Lease-Up Ramp (months to fully fill)</label>
                  <input v-model.number="scenarios[key].occupancy_ramp_months" type="number" step="1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
                </div>
                <div>
                  <label class="fv-label text-xs">Starting Occupancy %</label>
                  <input v-model.number="scenarios[key].occupancy_start_pct" type="number" step="1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
                </div>
              </template>

              <div>
                <label class="fv-label text-xs">Appreciation %/yr</label>
                <input v-model.number="scenarios[key].appreciation_rate_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
              </div>
              <div>
                <label class="fv-label text-xs">Exit Cap Rate %</label>
                <input v-model.number="scenarios[key].exit_cap_rate_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
              </div>
              <div>
                <label class="fv-label text-xs">Other OpEx % of Revenue</label>
                <input v-model.number="scenarios[key].other_opex_pct" type="number" step="0.1" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end mb-5">
        <button @click="compute" class="fv-btn-gold rounded-lg text-sm font-semibold px-6 py-2.5" :disabled="computing">
          {{ computing ? 'Computing…' : 'Compute All Three Scenarios' }}
        </button>
      </div>

      <div v-if="computeError" class="fv-card rounded-xl p-4 mb-5" style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#ef4444;">
        {{ computeError }}
      </div>

      <!-- Results — side by side -->
      <div v-if="result" class="space-y-5">
        <div class="grid grid-cols-3 gap-4">
          <div
            v-for="key in ['conservative','base','optimistic']" :key="key"
            class="fv-card rounded-xl p-5"
            :style="key === bestNpvScenario ? { borderColor: 'var(--fv-gold)', borderWidth: '2px' } : {}"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="font-semibold text-sm" :style="{ color: scenarioColors[key] }">{{ scenarioLabels[key] }}</div>
              <span v-if="key === bestNpvScenario" class="fv-tag-gold text-xs">Best NPV</span>
            </div>

            <div class="space-y-3">
              <div v-if="result.result.scenarios[key].computation_warning" class="p-2.5 rounded-lg text-xs" style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#ef4444;">
                ⚠ {{ result.result.scenarios[key].computation_warning }}
              </div>
              <div v-else>
                <p class="fv-text-label text-xs">NPV</p>
                <p class="text-lg font-bold" :style="{ color: npvVerdict(result.result.scenarios[key].npv).color }">
                  {{ fmt(result.result.scenarios[key].npv) }} {{ baseCurrency }}
                </p>
                <p class="text-xs" :style="{ color: npvVerdict(result.result.scenarios[key].npv).color }">
                  {{ npvVerdict(result.result.scenarios[key].npv).label }}
                </p>
              </div>
              <div>
                <p class="fv-text-label text-xs">IRR</p>
                <p class="text-base font-semibold fv-text-primary">
                  {{ result.result.scenarios[key].irr != null ? fmtPct(result.result.scenarios[key].irr) : 'N/A' }}
                </p>
              </div>
              <div class="grid grid-cols-2 gap-2 pt-2" style="border-top:1px solid var(--fv-border);">
                <div>
                  <p class="fv-text-label text-xs">Year-0 Outflow</p>
                  <p class="text-sm fv-text-primary">{{ fmt(result.result.scenarios[key].year0_equity_outflow) }}</p>
                </div>
                <div>
                  <p class="fv-text-label text-xs">Exit Value</p>
                  <p class="text-sm fv-text-primary">{{ fmt(result.result.scenarios[key].terminal_value) }}</p>
                </div>
                <div>
                  <p class="fv-text-label text-xs">Net Sale Proceeds</p>
                  <p class="text-sm fv-text-primary">{{ fmt(result.result.scenarios[key].net_sale_proceeds) }}</p>
                </div>
                <div v-if="result.result.scenarios[key].outstanding_loan_at_exit > 0">
                  <p class="fv-text-label text-xs">{{ fundingPath === 'bank_loan' ? 'Loan Payoff at Exit' : 'Remaining Balance Settled at Exit' }}</p>
                  <p class="text-sm" style="color:#ef4444;">{{ fmt(result.result.scenarios[key].outstanding_loan_at_exit) }}</p>
                </div>
                <div v-if="result.result.scenarios[key].contractor_fee_at_exit > 0">
                  <p class="fv-text-label text-xs">Contractor Fee at Exit</p>
                  <p class="text-sm" style="color:#ef4444;">{{ fmt(result.result.scenarios[key].contractor_fee_at_exit) }}</p>
                </div>
              </div>

              <button
                v-if="!result.result.scenarios[key].computation_warning"
                @click="expandedScenario = expandedScenario === key ? null : key"
                class="fv-btn-secondary rounded-lg text-xs font-semibold w-full mt-2 py-1.5"
              >
                {{ expandedScenario === key ? 'Hide' : 'View' }} Year-by-Year Cash Flow
              </button>
            </div>
          </div>
        </div>

        <!-- Drill-down: year-by-year table for the expanded scenario -->
        <div v-if="expandedScenario && !result.result.scenarios[expandedScenario].computation_warning" class="fv-card rounded-xl p-5 overflow-x-auto">
          <h3 class="fv-text-primary font-semibold text-sm mb-3">
            {{ scenarioLabels[expandedScenario] }} — Year-by-Year Cash Flow ({{ baseCurrency }})
          </h3>
          <table class="w-full text-xs">
            <thead>
              <tr class="fv-border" style="border-bottom-width:1px;">
                <th class="text-left py-2 px-2 fv-text-label">Year</th>
                <th class="text-right py-2 px-2 fv-text-label">Avg Occupancy</th>
                <th class="text-right py-2 px-2 fv-text-label">Gross Revenue</th>
                <th class="text-right py-2 px-2 fv-text-label">Other OpEx</th>
                <th v-if="fundingPath === 'contractor_deal'" class="text-right py-2 px-2 fv-text-label">Contractor Rent Share</th>
                <th class="text-right py-2 px-2 fv-text-label">Net Before Tax</th>
                <th class="text-right py-2 px-2 fv-text-label">Corporate Tax</th>
                <th class="text-right py-2 px-2 fv-text-label">Financing Outflow</th>
                <th class="text-right py-2 px-2 fv-text-label">Net Cash Flow</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in result.result.scenarios[expandedScenario].annual_cashflows" :key="row.year" class="fv-border" style="border-bottom-width:1px;">
                <td class="py-2 px-2 fv-text-primary">Year {{ row.year }}</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ fmtPct(row.avg_occupancy_pct) }}</td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ fmt(row.gross_revenue) }}</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ fmt(row.other_opex) }}</td>
                <td v-if="fundingPath === 'contractor_deal'" class="py-2 px-2 text-right" style="color:#ef4444;">{{ row.contractor_rent_share > 0 ? '-' + fmt(row.contractor_rent_share) : '—' }}</td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ fmt(row.net_before_tax) }}</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ fmt(row.corporate_tax) }}</td>
                <td class="py-2 px-2 text-right" style="color:#ef4444;">{{ row.financing_outflow > 0 ? '-' + fmt(row.financing_outflow) : '—' }}</td>
                <td class="py-2 px-2 text-right font-semibold" :style="{ color: row.net_cf >= 0 ? '#22c55e' : '#ef4444' }">{{ fmt(row.net_cf) }}</td>
              </tr>
            </tbody>
          </table>
          <p class="fv-text-muted text-xs mt-3">
            Rent modeled as collected {{ COLLECTION_INTERVAL_LABELS[result.result.scenarios[expandedScenario].rent_collection_interval].toLowerCase() }}, in advance.
            At exit (Year {{ shared.exit_year }}): sells for {{ fmt(result.result.scenarios[expandedScenario].terminal_value) }} {{ baseCurrency }}
            ({{ result.result.scenarios[expandedScenario].terminal_value_note === 'cap_rate' ? 'income cap rate method' : 'market appreciation method' }}),
            <span v-if="result.result.scenarios[expandedScenario].outstanding_loan_at_exit > 0">{{ fundingPath === 'bank_loan' ? 'pays off' : 'settles' }} {{ fmt(result.result.scenarios[expandedScenario].outstanding_loan_at_exit) }} {{ fundingPath === 'bank_loan' ? 'remaining loan balance' : 'still owed on the payment schedule' }}, </span>
            <span v-if="result.result.scenarios[expandedScenario].contractor_fee_at_exit > 0">pays the contractor {{ fmt(result.result.scenarios[expandedScenario].contractor_fee_at_exit) }} ({{ contractorFeePct }}% of sale price), </span>
            netting {{ fmt(result.result.scenarios[expandedScenario].net_sale_proceeds) }} {{ baseCurrency }} in sale proceeds.
          </p>
        </div>

        <!-- ── Portfolio Impact — compared against Base Case ───────────── -->
        <div v-if="result.portfolio_impact" class="fv-card rounded-xl p-5">
          <h2 class="fv-text-primary font-semibold text-sm mb-1">Portfolio Impact</h2>
          <p class="fv-text-muted text-xs mb-4">How this deal (Base Case) would change your existing portfolio, if added today.</p>
          <table class="w-full text-sm">
            <thead>
              <tr class="fv-border" style="border-bottom-width:1px;">
                <th class="text-left py-2 px-2 fv-text-label">Metric</th>
                <th class="text-right py-2 px-2 fv-text-label">Today</th>
                <th class="text-right py-2 px-2 fv-text-label">+ This Deal</th>
                <th class="text-right py-2 px-2 fv-text-label">Change</th>
              </tr>
            </thead>
            <tbody>
              <tr class="fv-border" style="border-bottom-width:1px;">
                <td class="py-2 px-2 fv-text-primary">Total Units</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ result.portfolio_impact.total_units_before }}</td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ result.portfolio_impact.total_units_after }}</td>
                <td class="py-2 px-2 text-right" style="color:#22c55e;">+1</td>
              </tr>
              <tr class="fv-border" style="border-bottom-width:1px;">
                <td class="py-2 px-2 fv-text-primary">Occupancy Rate</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ fmtPct(result.portfolio_impact.occupancy_rate_before) }}</td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ fmtPct(result.portfolio_impact.occupancy_rate_after) }}</td>
                <td class="py-2 px-2 text-right" :style="{ color: result.portfolio_impact.occupancy_rate_after >= result.portfolio_impact.occupancy_rate_before ? '#22c55e' : '#ef4444' }">
                  {{ (result.portfolio_impact.occupancy_rate_after - result.portfolio_impact.occupancy_rate_before).toFixed(1) }}pp
                </td>
              </tr>
              <tr class="fv-border" style="border-bottom-width:1px;">
                <td class="py-2 px-2 fv-text-primary">Portfolio NOI (trailing 12mo)</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ fmt(result.portfolio_impact.portfolio_noi_before) }}</td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ fmt(result.portfolio_impact.portfolio_noi_after) }}</td>
                <td class="py-2 px-2 text-right" :style="{ color: result.portfolio_impact.portfolio_noi_after >= result.portfolio_impact.portfolio_noi_before ? '#22c55e' : '#ef4444' }">
                  {{ fmt(result.portfolio_impact.portfolio_noi_after - result.portfolio_impact.portfolio_noi_before) }}
                </td>
              </tr>
              <tr>
                <td class="py-2 px-2 fv-text-primary">Blended ROI</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ result.portfolio_impact.blended_roi_before != null ? fmtPct(result.portfolio_impact.blended_roi_before) : 'N/A' }}</td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ result.portfolio_impact.blended_roi_after != null ? fmtPct(result.portfolio_impact.blended_roi_after) : 'N/A' }}</td>
                <td class="py-2 px-2 text-right" v-if="result.portfolio_impact.blended_roi_before != null && result.portfolio_impact.blended_roi_after != null"
                    :style="{ color: result.portfolio_impact.blended_roi_after >= result.portfolio_impact.blended_roi_before ? '#22c55e' : '#ef4444' }">
                  {{ (result.portfolio_impact.blended_roi_after - result.portfolio_impact.blended_roi_before).toFixed(2) }}pp
                </td>
                <td v-else class="py-2 px-2 text-right fv-text-muted">—</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── Company Cash Flow Impact — compared against Base Case ───── -->
        <div v-if="result.cash_flow_impact" class="fv-card rounded-xl p-5 overflow-x-auto">
          <h2 class="fv-text-primary font-semibold text-sm mb-1">Company Cash Flow Impact</h2>
          <p class="fv-text-muted text-xs mb-4">
            Your portfolio's existing scheduled cash flow (rent, installments, expenses), plus this deal (Base Case), year by year.
            <span v-if="result.cash_flow_impact.last_scheduled_year > 0 && result.cash_flow_impact.last_scheduled_year < shared.exit_year">
              Years beyond {{ result.cash_flow_impact.last_scheduled_year }} are projected at {{ result.cash_flow_impact.inflation_rate_pct }}%/yr inflation, since no scheduled data exists that far out.
            </span>
          </p>
          <div v-if="result.cash_flow_impact.has_squeeze" class="p-2.5 rounded-lg text-xs mb-3" style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#ef4444;">
            ⚠ At least one year shows a negative combined cash flow — see highlighted row(s) below.
          </div>
          <table class="w-full text-xs">
            <thead>
              <tr class="fv-border" style="border-bottom-width:1px;">
                <th class="text-left py-2 px-2 fv-text-label">Year</th>
                <th class="text-right py-2 px-2 fv-text-label">Existing Cash In</th>
                <th class="text-right py-2 px-2 fv-text-label">Existing Cash Out</th>
                <th class="text-right py-2 px-2 fv-text-label">Existing Net</th>
                <th class="text-right py-2 px-2 fv-text-label">This Deal Net</th>
                <th class="text-right py-2 px-2 fv-text-label">Combined Net</th>
                <th class="text-right py-2 px-2 fv-text-label">Accumulated (Combined)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in result.cash_flow_impact.years" :key="row.year" class="fv-border"
                  :style="row.is_squeeze ? { background: 'rgba(239,68,68,0.08)', borderBottomWidth: '1px' } : { borderBottomWidth: '1px' }">
                <td class="py-2 px-2 fv-text-primary">
                  Year {{ row.year }}
                  <span v-if="row.is_projected" class="fv-text-muted" style="font-style:italic;"> (projected)</span>
                </td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ fmt(row.existing_cash_in) }}</td>
                <td class="py-2 px-2 text-right fv-text-muted">{{ fmt(row.existing_cash_out) }}</td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ fmt(row.existing_net) }}</td>
                <td class="py-2 px-2 text-right" :style="{ color: row.deal_net >= 0 ? '#22c55e' : '#ef4444' }">{{ fmt(row.deal_net) }}</td>
                <td class="py-2 px-2 text-right font-semibold" :style="{ color: row.combined_net >= 0 ? '#22c55e' : '#ef4444' }">
                  {{ fmt(row.combined_net) }} <span v-if="row.is_squeeze">⚠</span>
                </td>
                <td class="py-2 px-2 text-right fv-text-primary">{{ fmt(row.accumulated_combined) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Decision — made here, after seeing the numbers, not guessed at creation -->
        <div class="fv-card rounded-xl p-5">
          <h2 class="fv-text-primary font-semibold text-sm mb-1">Decision</h2>
          <p class="fv-text-muted text-xs mb-4">Now that you've seen how this looks under all three scenarios, record where this deal stands. This is just a label for your own tracking — nothing else in the app changes because of it.</p>
          <div class="flex flex-wrap gap-3">
            <button
              @click="setStatus('pursuing')" :disabled="settingStatus"
              class="rounded-lg px-4 py-2 text-sm font-semibold transition"
              :style="currentStatus === 'pursuing'
                ? { background: 'rgba(59,130,246,0.15)', color: '#3b82f6', border: '1px solid rgba(59,130,246,0.4)' }
                : { background: 'var(--fv-bg)', color: 'var(--fv-text-label)', border: '1px solid var(--fv-border)' }"
            >
              Pursue This Deal
            </button>
            <button
              @click="setStatus('passed')" :disabled="settingStatus"
              class="rounded-lg px-4 py-2 text-sm font-semibold transition"
              :style="currentStatus === 'passed'
                ? { background: 'rgba(100,116,139,0.15)', color: 'var(--fv-text-primary)', border: '1px solid var(--fv-border)' }
                : { background: 'var(--fv-bg)', color: 'var(--fv-text-label)', border: '1px solid var(--fv-border)' }"
            >
              Decline / Pass
            </button>
            <button
              @click="setStatus('acquired')" :disabled="settingStatus"
              class="rounded-lg px-4 py-2 text-sm font-semibold transition"
              :style="currentStatus === 'acquired'
                ? { background: 'rgba(34,197,94,0.15)', color: '#22c55e', border: '1px solid rgba(34,197,94,0.4)' }
                : { background: 'var(--fv-bg)', color: 'var(--fv-text-label)', border: '1px solid var(--fv-border)' }"
            >
              Mark as Acquired
            </button>
            <button
              v-if="currentStatus !== 'evaluating'"
              @click="setStatus('evaluating')" :disabled="settingStatus"
              class="fv-btn-secondary rounded-lg px-4 py-2 text-sm font-semibold"
            >
              Reopen as Still Evaluating
            </button>
          </div>
        </div>

        <!-- ── Save Snapshot ────────────────────────────────────────── -->
        <div class="fv-card rounded-xl p-5">
          <h2 class="fv-text-primary font-semibold text-sm mb-1">Save This Analysis</h2>
          <p class="fv-text-muted text-xs mb-4">Saves everything shown above — all three scenarios, the chosen funding path, and both impact views — as a snapshot you (or anyone with a share link) can look back on later.</p>

          <div class="grid grid-cols-2 gap-4 mb-3">
            <div>
              <label class="fv-label text-xs">Snapshot Label (optional)</label>
              <input v-model="snapshotLabel" type="text" placeholder="e.g. Base case with 20% down" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="mb-3">
            <label class="fv-label text-xs">Analyst Recommendation (optional)</label>
            <textarea v-model="analystRecommendation" rows="2" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="Your own notes on this deal, shown alongside the numbers."></textarea>
          </div>

          <div v-if="saveError" class="p-2.5 rounded-lg text-xs mb-3" style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#ef4444;">{{ saveError }}</div>
          <div v-if="saveSuccess" class="p-2.5 rounded-lg text-xs mb-3" style="background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); color:#22c55e;">{{ saveSuccess }}</div>

          <button @click="saveSnapshot" class="fv-btn-gold rounded-lg px-4 py-2 text-sm font-semibold" :disabled="saving">
            {{ saving ? 'Saving…' : '+ Save Snapshot' }}
          </button>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
