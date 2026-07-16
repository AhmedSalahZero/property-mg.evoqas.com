<template>
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg px-6 py-8">

      <!-- ── Page Header ─────────────────────────────────────────── -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <a :href="route('company.properties.index', company.id)"
               class="fv-text-muted text-sm hover:underline">Properties</a>
            <span class="fv-text-muted text-sm">/</span>
            <span class="fv-text-muted text-sm">{{ property.property_name }}</span>
            <span class="fv-text-muted text-sm">/</span>
            <span class="fv-text-primary text-sm font-semibold">Contracts</span>
          </div>
          <h1 class="text-2xl font-bold fv-text-primary">Rent Contracts</h1>
          <p class="fv-text-muted text-sm mt-0.5">
            {{ property.property_name }}
            <span v-if="property.property_code" class="fv-tag ml-2">{{ property.property_code }}</span>
          </p>
        </div>
        <a :href="route('company.properties.contracts.create', [company.id, property.id])"
           class="flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-all fv-btn-secondary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          New Contract
        </a>
      </div>

      <!-- ── KPI Strip ───────────────────────────────────────────── -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
        <div class="fv-card text-center">
          <div class="text-2xl font-bold" style="color:var(--fv-blue)">{{ running.length }}</div>
          <div class="fv-text-muted text-xs mt-1">Running</div>
        </div>
        <div class="fv-card text-center">
          <div class="text-2xl font-bold text-amber-400">{{ expired.length }}</div>
          <div class="fv-text-muted text-xs mt-1">Expired</div>
        </div>
        <div class="fv-card text-center">
          <div class="text-2xl font-bold text-red-400">{{ terminated.length }}</div>
          <div class="fv-text-muted text-xs mt-1">Terminated</div>
        </div>
        <div class="fv-card text-center">
          <div class="text-2xl font-bold" style="color:var(--fv-gold)">
            {{ totalRunningRevenue }}
          </div>
          <div class="fv-text-muted text-xs mt-1">Monthly Rent (Running)</div>
        </div>
      </div>

      <!-- ── Tab Bar ─────────────────────────────────────────────── -->
      <div class="fv-card p-0 overflow-hidden">
        <div class="flex border-b" style="border-color:var(--fv-border)">
          <button v-for="tab in tabs" :key="tab.key"
            @click="activeTab = tab.key"
            class="px-5 py-3 text-sm font-semibold transition-colors"
            :class="activeTab === tab.key
              ? 'border-b-2 fv-text-primary'
              : 'fv-text-muted hover:fv-text-primary'"
            :style="activeTab === tab.key ? 'border-color:' : ''">
            {{ tab.label }}
            <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full"
              :class="activeTab === tab.key ? 'bg-blue-500/20' : 'bg-white/5'">
              {{ tabCount(tab.key) }}
            </span>
          </button>
        </div>

        <!-- ── Table ─────────────────────────────────────────────── -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border)">
                <th class="px-4 py-3 text-left text-amber-400 font-semibold text-xs uppercase tracking-wider">Unit</th>
                <th class="px-4 py-3 text-left text-amber-400 font-semibold text-xs uppercase tracking-wider">Tenant</th>
                <th class="px-4 py-3 text-left text-amber-400 font-semibold text-xs uppercase tracking-wider">Type</th>
                <th class="px-4 py-3 text-left text-amber-400 font-semibold text-xs uppercase tracking-wider">Period</th>
                <th class="px-4 py-3 text-right text-amber-400 font-semibold text-xs uppercase tracking-wider">Monthly Rent</th>
                <th class="px-4 py-3 text-right text-amber-400 font-semibold text-xs uppercase tracking-wider">Collection</th>
                <th class="px-4 py-3 text-center text-amber-400 font-semibold text-xs uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-center text-amber-400 font-semibold text-xs uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="activeRows.length === 0">
                <td colspan="8" class="px-4 py-12 text-center fv-text-muted text-sm">
                  No {{ activeTab }} contracts.
                </td>
              </tr>
              <tr v-for="c in activeRows" :key="c.id"
                  class="transition-colors"
                  style="border-bottom:1px solid var(--fv-border)"
                  :style="{background:'transparent'}"
                  @mouseenter="e => e.currentTarget.style.background='var(--fv-bg-hover)'"
                  @mouseleave="e => e.currentTarget.style.background='transparent'">
                <!-- Unit -->
                <td class="px-4 py-3">
                  <span class="fv-text-primary font-medium">
                    {{ c.property_unit ? c.property_unit.unit_name : property.property_name }}
                  </span>
                  <span v-if="c.property_unit?.unit_code" class="fv-tag ml-1">{{ c.property_unit.unit_code }}</span>
                </td>
                <!-- Tenant -->
                <td class="px-4 py-3">
                  <div class="fv-text-primary">{{ c.customer?.customer_name }}</div>
                  <div class="fv-text-muted text-xs">{{ c.tenant_nature === 'corporate' ? 'Corporate' : 'Individual' }}</div>
                </td>
                <!-- Revenue type -->
                <td class="px-4 py-3">
                  <span :class="c.revenue_type === 'direct_rent' ? 'fv-tag' : 'fv-tag-gold'">
                    {{ c.revenue_type === 'direct_rent' ? 'Direct Rent' : 'Mgmt Fee' }}
                  </span>
                </td>
                <!-- Period -->
                <td class="px-4 py-3 fv-text-muted text-xs">
                  {{ formatDate(c.start_date) }} → {{ formatDate(c.end_date) }}
                </td>
                <!-- Monthly rent -->
                <td class="px-4 py-3 text-right fv-text-primary font-semibold tabular-nums">
                  {{ formatMoney(c.min_monthly_rent || c.monthly_rent_amount) }}
                  <span class="fv-text-muted text-xs ml-1">{{ c.contract_currency }}</span>
                </td>
                <!-- Collection interval -->
                <td class="px-4 py-3 text-right fv-text-muted text-xs">
                  {{ intervalLabel(c.collection_interval_months) }}
                </td>
                <!-- Status -->
                <td class="px-4 py-3 text-center">
                  <span :class="statusBadge(c.status)">{{ c.status }}</span>
                </td>
                <!-- Actions -->
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Edit -->
                    <a :href="route('company.properties.contracts.edit', [company.id, property.id, c.id])"
                       class="fv-action-btn" 
                       title="Edit Contract">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2v-5m10-10l-5 5m0 0l-5-5m5 5v11" />
                      </svg>
                    </a>  
                 <!-- View -->
                    <a :href="route('company.properties.contracts.show', [company.id, property.id, c.id])"
                       class="fv-action-btn" title="View Details">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </a>
                    <!-- Renew -->
                    <a v-if="c.status !== 'running'"
                       :href="route('company.properties.contracts.renew', [company.id, property.id, c.id])"
                       class="fv-action-btn fv-action-btn-settings" title="Renew">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                      </svg>
                    </a>
                    <!-- Terminate -->
                    <button v-if="c.status === 'running'"
                      @click="openTerminate(c)"
                      class="fv-action-btn fv-action-btn-danger" title="Terminate">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                    <!-- Delete -->
                    <button @click="openDelete(c)"
                      class="fv-action-btn fv-action-btn-danger" title="Delete">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Terminate Modal ────────────────────────────────────── -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="terminateModal.open"
               class="fixed inset-0 z-50 flex items-center justify-center px-4"
               style="background:rgba(0,0,0,0.6)" @click.self="terminateModal.open = false">
            <div class="fv-modal rounded-xl p-6 w-full max-w-md">
              <h3 class="text-lg font-bold fv-text-primary mb-1">Terminate Contract</h3>
              <p class="fv-text-muted text-sm mb-4">
                Tenant: <strong class="fv-text-primary">{{ terminateModal.contract?.customer?.customer_name }}</strong>
              </p>
              <div class="space-y-4">
                <div>
                  <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Termination Date *</label>
                  <input type="date" v-model="terminateForm.terminated_date"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm"/>
                </div>
                <div>
                  <label class="fv-text-label text-xs font-semibold uppercase tracking-wider block mb-1">Notes</label>
                  <textarea v-model="terminateForm.termination_notes" rows="3"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm resize-none"
                    placeholder="Reason for termination..."/>
                </div>
              </div>
              <div class="flex gap-3 mt-5">
                <button @click="terminateModal.open = false" class="fv-btn-secondary flex-1 py-2 rounded-lg text-sm">Cancel</button>
                <button @click="submitTerminate"
                  :disabled="!terminateForm.terminated_date"
                  class="flex-1 py-2 rounded-lg text-sm font-semibold text-white"
                  style="background:var(--fv-gold);opacity: terminateForm.terminated_date ? 1 : 0.5">
                  Confirm Termination
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- ── Delete Modal ───────────────────────────────────────── -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="deleteModal.open"
               class="fixed inset-0 z-50 flex items-center justify-center px-4"
               style="background:rgba(0,0,0,0.6)" @click.self="deleteModal.open = false">
            <div class="fv-modal rounded-xl p-6 w-full max-w-sm">
              <h3 class="text-lg font-bold fv-text-primary mb-2">Delete Contract?</h3>
              <p class="fv-text-muted text-sm mb-4">
                This will permanently delete the contract and all its rent schedules. This cannot be undone.
              </p>
              <div class="flex gap-3">
                <button @click="deleteModal.open = false" class="fv-btn-secondary flex-1 py-2 rounded-lg text-sm">Cancel</button>
                <button @click="submitDelete"
                  class="flex-1 py-2 rounded-lg text-sm font-semibold text-white bg-red-600 hover:bg-red-700">
                  Delete
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
import { useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:    Object,
  property:   Object,
  running:    Array,
  expired:    Array,
  terminated: Array,
})

const activeTab = ref('running')

const tabs = [
  { key: 'running',    label: 'Running' },
  { key: 'expired',    label: 'Expired' },
  { key: 'terminated', label: 'Terminated' },
]

const activeRows = computed(() => props[activeTab.value] || [])
const tabCount   = (key) => props[key]?.length ?? 0

const totalRunningRevenue = computed(() => {
  const total = props.running.reduce((sum, c) => {
    const amt = parseFloat(c.min_monthly_rent) || parseFloat(c.monthly_rent_amount) || 0
    return sum + amt
  }, 0)
  return total.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
})

function formatDate(d) {
  if (!d) return '—'
  const dt = new Date(d)
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatMoney(v) {
  if (!v) return '0'
  return parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function intervalLabel(months) {
  const map = { 1: 'Monthly', 2: '2-Month', 3: '3-Month', 4: '4-Month', 6: '6-Month', 12: 'Annual' }
  return map[months] || `${months}M`
}

function statusBadge(status) {
  if (status === 'running')    return 'fv-badge fv-badge-active'
  if (status === 'expired')    return 'fv-badge bg-amber-500/10 text-amber-400 border border-amber-500/20'
  if (status === 'terminated') return 'fv-badge fv-badge-inactive'
  return 'fv-badge'
}

// ── Terminate ──────────────────────────────────────────────────────────
const terminateModal = ref({ open: false, contract: null })
const terminateForm  = ref({ terminated_date: '', termination_notes: '' })

function openTerminate(c) {
  terminateModal.value = { open: true, contract: c }
  terminateForm.value  = { terminated_date: '', termination_notes: '' }
}

function submitTerminate() {
  const c = terminateModal.value.contract
  router.post(
    route('company.properties.contracts.terminate', [props.company.id, props.property.id, c.id]),
    terminateForm.value,
    { onSuccess: () => { terminateModal.value.open = false } }
  )
}

// ── Delete ─────────────────────────────────────────────────────────────
const deleteModal = ref({ open: false, contract: null })

function openDelete(c) { deleteModal.value = { open: true, contract: c } }

function submitDelete() {
  const c = deleteModal.value.contract
  router.delete(
    route('company.properties.contracts.destroy', [props.company.id, props.property.id, c.id]),
    { onSuccess: () => { deleteModal.value.open = false } }
  )
}
</script>