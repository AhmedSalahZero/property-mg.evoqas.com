<template>
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg px-6 py-8">

      <!-- ── Header ─────────────────────────────────────────────── -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <a :href="route('company.properties.index', company.id)" class="fv-text-muted text-sm hover:underline">Properties</a>
            <span class="fv-text-muted text-sm">/</span>
            <a :href="route('company.properties.contracts.index', [company.id, property.id])" class="fv-text-muted text-sm hover:underline">{{ property.property_name }}</a>
            <!-- <span class="fv-text-muted text-sm">/</span>
            <span class="fv-text-primary text-sm font-semibold">Contract #{{ contract.id }}</span> -->
          </div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold fv-text-primary">{{ contract.customer?.customer_name }}</h1>
            <span :class="statusBadge(contract.status)">{{ contract.status }}</span>
            <span :class="contract.revenue_type === 'direct_rent' ? 'fv-tag' : 'fv-tag-gold'">
              {{ contract.revenue_type === 'direct_rent' ? 'Direct Rent' : 'Management Fee' }}
            </span>
          </div>
          <p class="fv-text-muted text-sm mt-0.5">
            {{ formatDate(contract.start_date) }} → {{ formatDate(contract.end_date) }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <a v-if="contract.status !== 'running'"
            :href="route('company.properties.contracts.renew', [company.id, property.id, contract.id])"
            class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Renew
          </a>
          <button v-if="contract.status === 'running'" @click="terminateModal = true"
            class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2"
            style="border-color:rgba(239,68,68,0.3);color:#f87171">
            Terminate
          </button>
        </div>
      </div>

      <!-- ── Summary Cards ───────────────────────────────────────── -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="fv-card">
          <div class="fv-text-muted text-xs uppercase tracking-wider mb-1">Unit</div>
          <div class="fv-text-primary font-semibold text-sm">
            {{ contract.property_unit ? contract.property_unit.unit_name : property.property_name }}
          </div>
        </div>
        <div class="fv-card">
          <div class="fv-text-muted text-xs uppercase tracking-wider mb-1">Monthly Rent Basis</div>
          <div class="font-bold" style="color:var(--fv-gold)">
            {{ formatMoney(contract.min_monthly_rent || contract.monthly_rent_amount) }}
            <span class="fv-text-muted text-xs">{{ contract.contract_currency }}</span>
          </div>
        </div>
        <div class="fv-card">
          <div class="fv-text-muted text-xs uppercase tracking-wider mb-1">Insurance</div>
          <div class="fv-text-primary font-semibold text-sm">
            {{ formatMoney(contract.insurance_amount) }} {{ contract.contract_currency }}
            <span class="fv-text-muted text-xs">({{ contract.insurance_months }} months)</span>
          </div>
        </div>
        <div class="fv-card">
          <div class="fv-text-muted text-xs uppercase tracking-wider mb-1">Increase Plan</div>
          <div class="fv-text-primary font-semibold text-sm">
            {{ increasePlanSummary }}
          </div>
        </div>
      </div>

      <!-- ── Cross-currency notice ──────────────────────────────── -->
      <div v-if="contract.collection_currency !== contract.contract_currency"
        class="mb-6 px-4 py-3 rounded-xl text-sm fv-text-primary flex items-start gap-2"
        style="background:var(--fv-gold-dim);border:1px solid var(--fv-gold-border)">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:var(--fv-gold)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span>
          Contract currency is <strong>{{ contract.contract_currency }}</strong> but collection currency is
          <strong>{{ contract.collection_currency }}</strong>.
          Collection amounts are stored in {{ contract.contract_currency }}.
          At reporting time, FX rates from Statistica (<strong>{{ contract.contract_currency }}/{{ contract.collection_currency }}</strong>) will be applied using the nearest available date.
        </span>
      </div>

      <!-- ── Tabs ────────────────────────────────────────────────── -->
      <div class="fv-card p-0 overflow-hidden">
        <div class="flex border-b" style="border-color:var(--fv-border)">
          <button v-for="tab in ['Rent Schedule', 'Collections', 'Details']" :key="tab"
            @click="activeTab = tab"
            class="px-5 py-3 text-sm font-semibold transition-colors"
            :class="activeTab === tab ? 'border-b-2 fv-text-primary' : 'fv-text-muted'"
            :style="activeTab === tab ? 'border-color:var(--fv-blue);color:var(--fv-blue)' : ''">
            {{ tab }}
          </button>
        </div>

        <!-- ── Rent Schedule Tab ─────────────────────────────────── -->
        <div v-if="activeTab === 'Rent Schedule'" class="overflow-x-auto">
          <!-- Fix for audit finding M-5 — makes the already-confirmed
               management-fee business rule visible here too, not just on
               the Create form. "Monthly Rent" below is the full rent basis;
               "Revenue Amount" is only this company's commission on it —
               the two intentionally differ for a management-fee contract. -->
          <div v-if="contract.revenue_type === 'management_fee'" class="mx-4 mt-4 mb-2 px-3 py-2 rounded-lg text-xs"
            style="background:var(--fv-blue-dim); border:1px solid var(--fv-border); color:var(--fv-text-muted);">
            ℹ️ Management Fee contract — <strong class="fv-text-primary">Monthly Rent</strong> is the full rent basis,
            but <strong class="fv-text-primary">Revenue Amount</strong> is only this company's {{ contract.management_fee_rate }}%
            commission on it. The full rent is settled directly between tenant and owner and never appears in this app's totals.
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border)">
                <th class="px-4 py-3 text-left fv-text-muted font-semibold text-xs uppercase">Period</th>
                <th class="px-4 py-3 text-right fv-text-muted font-semibold text-xs uppercase">Monthly Rent</th>
                <th class="px-4 py-3 text-right fv-text-muted font-semibold text-xs uppercase">Revenue Amount</th>
                <th class="px-4 py-3 text-center fv-text-muted font-semibold text-xs uppercase">Year</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in contract.revenues" :key="r.id"
                style="border-bottom:1px solid var(--fv-border)"
                class="transition-colors"
                @mouseenter="e => e.currentTarget.style.background='var(--fv-bg-hover)'"
                @mouseleave="e => e.currentTarget.style.background='transparent'">
                <td class="px-4 py-2.5 fv-text-primary">{{ r.period_label }}</td>
                <td class="px-4 py-2.5 text-right fv-text-muted tabular-nums">
                  {{ formatMoney(r.monthly_rent) }}
                </td>
                <td class="px-4 py-2.5 text-right fv-text-primary font-semibold tabular-nums">
                  {{ formatMoney(r.revenue_amount) }}
                  <span class="fv-text-muted text-xs ml-1">{{ r.currency }}</span>
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span class="fv-tag">Y{{ r.year_number }}</span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="border-top:2px solid var(--fv-border)">
                <td class="px-4 py-3 fv-text-label font-bold text-xs uppercase">Total</td>
                <td></td>
                <td class="px-4 py-3 text-right font-bold tabular-nums" style="color:var(--fv-gold)">
                  {{ formatMoney(totalRevenue) }}
                  <span class="fv-text-muted text-xs ml-1">{{ contract.contract_currency }}</span>
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- ── Collections Tab ──────────────────────────────────── -->
        <div v-if="activeTab === 'Collections'" class="overflow-x-auto">
          <div v-if="contract.revenue_type === 'management_fee'" class="mx-4 mt-4 mb-2 px-3 py-2 rounded-lg text-xs"
            style="background:var(--fv-blue-dim); border:1px solid var(--fv-border); color:var(--fv-text-muted);">
            ℹ️ Management Fee contract — amounts below are only this company's commission, collected directly.
            The tenant's full rent payment to the property owner is settled outside this app.
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border)">
                <th class="px-4 py-3 text-left fv-text-muted font-semibold text-xs uppercase">#</th>
                <th class="px-4 py-3 text-left fv-text-muted font-semibold text-xs uppercase">Due Date</th>
                <th class="px-4 py-3 text-left fv-text-muted font-semibold text-xs uppercase">Period Covered</th>
                <th class="px-4 py-3 text-right fv-text-muted font-semibold text-xs uppercase">Amount</th>
                <th class="px-4 py-3 text-center fv-text-muted font-semibold text-xs uppercase">Status</th>
                <th class="px-4 py-3 text-center fv-text-muted font-semibold text-xs uppercase">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(col, i) in contract.collections" :key="col.id"
                style="border-bottom:1px solid var(--fv-border)"
                class="transition-colors"
                @mouseenter="e => e.currentTarget.style.background='var(--fv-bg-hover)'"
                @mouseleave="e => e.currentTarget.style.background='transparent'">
                <td class="px-4 py-2.5 fv-text-muted text-xs">{{ i + 1 }}</td>
                <td class="px-4 py-2.5 fv-text-primary">{{ formatDate(col.collection_date) }}</td>
                <td class="px-4 py-2.5 fv-text-muted text-xs">
                  {{ formatDate(col.period_from) }} → {{ formatDate(col.period_to) }}
                </td>
                <td class="px-4 py-2.5 text-right fv-text-primary font-semibold tabular-nums">
                  {{ formatMoney(col.collection_amount) }}
                  <span class="fv-text-muted text-xs ml-1">{{ col.currency }}</span>
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span :class="collectionBadge(effectiveStatus(col))" style="text-transform:capitalize">{{ effectiveStatus(col) }}</span>
                </td>
                <td class="px-4 py-2.5 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <!-- COLLECTED: show date + edit pencil + Uncollect -->
                    <div v-if="col.status === 'collected'" class="flex items-center justify-center gap-2">
                      <span class="fv-text-muted text-xs">{{ formatDate(col.collected_date) }}</span>
                      <button @click="openMarkCollected(col, true)"
                        class="fv-text-muted hover:text-white transition-colors"
                        title="Edit collection">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H8v-2.414a2 2 0 01.586-1.414z"/>
                        </svg>
                      </button>
                      <!-- Uncollect — undo, required before this row could
                           ever be reconsidered. Keeps the row intact and
                           reverts status to pending/overdue, unlike the
                           old Delete button which removed it entirely. -->
                      <button @click="uncollect(col)"
                        class="fv-action-btn text-xs px-2 h-auto py-1 rounded"
                        style="font-size:0.7rem"
                        title="Undo — revert to pending/overdue, keep the row">
                        Uncollect
                      </button>
                    </div>
                    <!-- PENDING or OVERDUE: show Mark Collected button -->
                    <button v-else
                      @click="openMarkCollected(col, false)"
                      class="fv-action-btn fv-action-btn-settings text-xs px-2 h-auto py-1 rounded"
                      style="font-size:0.7rem">
                      Mark Collected
                    </button>
                    <!-- No Delete action here (confirmed product decision,
                         July 2026): a collection row is a scheduled slice
                         of the contract's own revenue, not something a
                         user creates by hand, so ad hoc deletion could
                         desync the schedule from the contract. The correct
                         fixes are Edit Contract (regenerates the whole
                         schedule) or Terminate (auto-truncates future
                         rows) — both already exist and keep everything
                         consistent. Uncollect above is the only "undo"
                         needed for a mistaken Mark Collected. -->
                  </div>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="border-top:2px solid var(--fv-border)">
                <td colspan="3" class="px-4 py-3 fv-text-label font-bold text-xs uppercase">Total Collections</td>
                <td class="px-4 py-3 text-right font-bold tabular-nums" style="color:var(--fv-gold)">
                  {{ formatMoney(totalCollections) }}
                  <span class="fv-text-muted text-xs ml-1">{{ contract.contract_currency }}</span>
                </td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- ── Details Tab ───────────────────────────────────────── -->
        <div v-if="activeTab === 'Details'" class="p-6">
          <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Tenant</dt>
              <dd class="fv-text-primary font-semibold">{{ contract.customer?.customer_name }}</dd></div>
            <div><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Tenant Nature</dt>
              <dd class="fv-text-primary">{{ contract.tenant_nature === 'corporate' ? 'Corporate' : 'Individual' }}</dd></div>
            <div><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Contract Currency</dt>
              <dd class="fv-text-primary">{{ contract.contract_currency }}</dd></div>
            <div><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Collection Currency</dt>
              <dd class="fv-text-primary">{{ contract.collection_currency }}</dd></div>
            <div><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Monthly Rent Amount</dt>
              <dd class="fv-text-primary">{{ formatMoney(contract.monthly_rent_amount) }} {{ contract.contract_currency }}</dd></div>
            <div v-if="contract.min_monthly_rent"><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Min Monthly Rent (Basis)</dt>
              <dd class="fv-text-primary font-bold" style="color:var(--fv-gold)">{{ formatMoney(contract.min_monthly_rent) }} {{ contract.contract_currency }}</dd></div>
            <div v-if="contract.variable_revenue_pct"><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Variable Revenue %</dt>
              <dd class="fv-text-primary">{{ contract.variable_revenue_pct }}% <span class="fv-tag ml-1">Info only</span></dd></div>
            <div v-if="contract.revenue_type === 'management_fee'"><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Management Fee Revenue Rate</dt>
              <dd class="fv-text-primary">{{ contract.management_fee_rate }}%</dd></div>
            <div><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Collection Interval</dt>
              <dd class="fv-text-primary">{{ intervalLabel(contract.collection_interval_months) }}</dd></div>
            <div><dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Increase Plan</dt>
              <dd class="fv-text-primary">{{ increasePlanSummary }}</dd></div>
            <div v-if="contract.terminated_date">
              <dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Terminated Date</dt>
              <dd class="text-red-400">{{ formatDate(contract.terminated_date) }}</dd>
            </div>
            <div v-if="contract.termination_notes" class="col-span-2">
              <dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Termination Notes</dt>
              <dd class="fv-text-primary">{{ contract.termination_notes }}</dd>
            </div>
            <div v-if="contract.renewed_from">
              <dt class="fv-text-muted text-xs uppercase tracking-wider mb-0.5">Renewed From</dt>
              <dd class="fv-text-primary">Contract #{{ contract.renewed_from.id }}
                ({{ formatDate(contract.renewed_from.start_date) }} → {{ formatDate(contract.renewed_from.end_date) }})
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- ── Terminate Modal ────────────────────────────────────── -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="terminateModal"
               class="fixed inset-0 z-50 flex items-center justify-center px-4"
               style="background:rgba(0,0,0,0.6)" @click.self="terminateModal = false">
            <div class="fv-modal rounded-xl p-6 w-full max-w-md">
              <h3 class="text-lg font-bold fv-text-primary mb-4">Terminate Contract</h3>
              <div class="space-y-4">
                <div>
                  <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Termination Date *</label>
                  <input type="date" v-model="terminateForm.terminated_date"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm"/>
                </div>
                <div>
                  <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Notes</label>
                  <textarea v-model="terminateForm.termination_notes" rows="3"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm resize-none"/>
                </div>
              </div>
              <div class="flex gap-3 mt-5">
                <button @click="terminateModal = false" class="fv-btn-secondary flex-1 py-2 rounded-lg text-sm">Cancel</button>
                <button @click="submitTerminate"
                  :disabled="!terminateForm.terminated_date"
                  class="flex-1 py-2 rounded-lg text-sm font-semibold text-white"
                  style="background:var(--fv-gold)">
                  Confirm
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- ── Mark Collected / Edit Collection Modal ─────────────── -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="collectedModal.open"
               class="fixed inset-0 z-50 flex items-center justify-center px-4"
               style="background:rgba(0,0,0,0.6)" @click.self="collectedModal.open = false">
            <div class="fv-modal rounded-xl p-6 w-full max-w-md">
              <h3 class="text-lg font-bold fv-text-primary mb-1">
                {{ collectedModal.isEdit ? 'Edit Collection' : 'Mark as Collected' }}
              </h3>
              <p class="fv-text-muted text-sm mb-4">
                Amount: <strong class="fv-text-primary">{{ formatMoney(collectedModal.collection?.collection_amount) }} {{ contract.contract_currency }}</strong>
              </p>
              <div class="space-y-4">
                <div>
                  <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Collection Date *</label>
                  <input type="date" v-model="collectedForm.collected_date"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm"/>
                </div>
                <div>
                  <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Notes</label>
                  <input type="text" v-model="collectedForm.notes"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                    placeholder="Optional notes"/>
                </div>
              </div>
              <div class="flex gap-3 mt-5">
                <button @click="collectedModal.open = false" class="fv-btn-secondary flex-1 py-2 rounded-lg text-sm">Cancel</button>
                <button @click="submitCollected"
                  :disabled="!collectedForm.collected_date"
                  class="btn-teal flex-1 py-2 rounded-lg text-sm font-semibold">
                  {{ collectedModal.isEdit ? 'Update' : 'Save' }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:  Object,
  property: Object,
  contract: Object,
})

const activeTab = ref('Rent Schedule')

const totalRevenue = computed(() =>
  (props.contract.revenues || []).reduce((s, r) => s + parseFloat(r.revenue_amount || 0), 0)
)
const totalCollections = computed(() =>
  (props.contract.collections || []).reduce((s, c) => s + parseFloat(c.collection_amount || 0), 0)
)
const increasePlanSummary = computed(() => {
  const rows = Array.isArray(props.contract.annual_increase_schedule)
    ? props.contract.annual_increase_schedule
    : []

  if (!rows.length) {
    return `${props.contract.annual_increase_rate || 0}% (legacy)`
  }

  return rows.map((r) => `${r.year}: ${r.rate}%`).join(' | ')
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function formatMoney(v) {
  if (!v) return '0'
  return parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
function intervalLabel(m) {
  const map = { 1: 'Monthly', 2: '2-Month', 3: '3-Month', 4: '4-Month', 6: '6-Month', 12: 'Annual' }
  return map[m] || `${m} months`
}
function statusBadge(s) {
  if (s === 'running')    return 'fv-badge fv-badge-active'
  if (s === 'expired')    return 'fv-badge bg-amber-500/10 text-amber-400 border border-amber-500/20'
  return 'fv-badge fv-badge-inactive'
}
function collectionBadge(s) {
  if (s === 'collected') return 'fv-badge bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
  if (s === 'overdue')   return 'fv-badge bg-red-500/10 text-red-400 border border-red-500/20'
  return 'fv-badge bg-cyan-500/10 text-cyan-400 border border-cyan-500/20'
}

// Defense-in-depth (confirmed July 2026 session): the stored `status`
// column only flips 'pending' → 'overdue' when the daily
// property:mark-overdue command runs, which depends on a system cron
// actually calling `php artisan schedule:run` — easy to go unconfigured
// on a dev machine (see routes/console.php) and silently leave every
// past-due row displaying as "Pending" indefinitely. This computes the
// correct badge live from the date instead of trusting that job to have
// run recently, so the UI is never wrong even if it hasn't.
function effectiveStatus(col) {
  if (col.status === 'pending' && new Date(col.collection_date) < new Date(new Date().toDateString())) {
    return 'overdue'
  }
  return col.status
}

// ── Terminate ──────────────────────────────────────────────────────────
const terminateModal = ref(false)
const terminateForm  = ref({ terminated_date: '', termination_notes: '' })

function submitTerminate() {
  router.post(
    route('company.properties.contracts.terminate', [props.company.id, props.property.id, props.contract.id]),
    terminateForm.value,
    { onSuccess: () => { terminateModal.value = false } }
  )
}

// ── Mark Collected / Edit Collection ───────────────────────────────────
const collectedModal = ref({ open: false, collection: null, isEdit: false })
const collectedForm  = ref({ collected_date: '', notes: '' })

function openMarkCollected(col, isEdit) {
  collectedModal.value = { open: true, collection: col, isEdit }
  collectedForm.value  = {
    collected_date: isEdit && col.collected_date ? col.collected_date.slice(0, 10) : '',
    notes:          isEdit ? (col.notes || '') : '',
  }
}

function submitCollected() {
  const col = collectedModal.value.collection
  router.patch(
    route('company.properties.contracts.collections.collected', [props.company.id, props.property.id, props.contract.id, col.id]),
    collectedForm.value,
    { onSuccess: () => { collectedModal.value.open = false } }
  )
}

// ── Uncollect ─────────────────────────────────────────────────────────────
// Undo a Mark Collected — keeps the row, reverts status to pending/overdue.
// Replaces the old Delete button (confirmed product decision, July 2026):
// a collection row is a scheduled slice of the contract's own revenue, so
// ad hoc deletion could desync the schedule — Edit Contract or Terminate
// are the correct ways to actually remove/change a collection. This is
// the mirror image of PropertyInstallmentDue's markUnpaid().
function uncollect(col) {
  if (!confirm('Revert this collection back to pending/overdue? It will still be due, ready to be collected again correctly.')) return

  router.patch(
    route('company.properties.contracts.collections.uncollect', [props.company.id, props.property.id, props.contract.id, col.id]),
    {},
    { preserveScroll: true }
  )
}
</script>