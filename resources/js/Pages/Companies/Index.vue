<template>
  <Head title="Companies — VERO Property Management" />
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg">

      <!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
      <div class="fv-header-bg border-b fv-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-widest mb-1 fv-breadcrumb">
                Super Admin · Company Management
              </p>
              <h1 class="text-2xl font-bold fv-text-primary">Companies</h1>
              <p class="fv-text-muted text-sm mt-1">
                {{ companies.length }} {{ companies.length === 1 ? 'company' : 'companies' }} registered
              </p>
            </div>
            <Link :href="route('companies.create')" class="fv-btn-primary-link">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              New Company
            </Link>
          </div>
        </div>
      </div>

      <!-- ══ FLASH ════════════════════════════════════════════════════════════ -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div v-if="$page.props.flash?.success"
          class="flex items-center gap-3 text-sm rounded-lg px-4 py-3 mb-4 fv-flash-success">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error"
          class="flex items-center gap-3 text-sm rounded-lg px-4 py-3 mb-4 fv-flash-error">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
          {{ $page.props.flash.error }}
        </div>
      </div>

      <!-- ══ MAIN CONTENT ══════════════════════════════════════════════════════ -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">

        <!-- Search & Filter Bar -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
          <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 fv-text-muted pointer-events-none"
              fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input v-model="search" type="text" placeholder="Search companies..."
              class="fv-input fv-input-field w-full pl-10 pr-4" />
          </div>
          <select v-model="filterStatus" class="fv-input fv-input-field w-full sm:w-44">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <select v-model="filterSubscription" class="fv-input fv-input-field w-full sm:w-56">
            <option value="">All Subscriptions</option>
            <option value="expired">Expired</option>
            <option value="expiring">Expiring Soon</option>
            <option value="active">Active</option>
          </select>
        </div>

        <!-- Empty State -->
        <div v-if="filtered.length === 0"
          class="fv-card flex flex-col items-center justify-center py-24 text-center">
          <div class="fv-empty-icon mb-4">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9"/>
            </svg>
          </div>
          <h3 class="text-lg font-semibold fv-text-primary mb-1">No Companies Found</h3>
          <p class="fv-text-muted text-sm mb-6">
            {{ companies.length === 0 ? 'Get started by creating your first company.' : 'Try adjusting your search or filters.' }}
          </p>
          <Link v-if="companies.length === 0" :href="route('companies.create')" class="fv-btn-primary-link">
            Create First Company
          </Link>
        </div>

        <!-- Companies Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <div v-for="company in filtered" :key="company.id" class="fv-company-card group">

            <!-- Card Header -->
            <div class="flex items-start justify-between mb-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="fv-avatar flex-shrink-0">{{ initials(company.name) }}</div>
                <div class="min-w-0">
                  <h3 class="fv-text-primary font-semibold text-sm truncate">{{ company.name }}</h3>
                  <p v-if="company.trade_name" class="fv-text-muted text-xs truncate">{{ company.trade_name }}</p>
                </div>
              </div>
              <span :class="company.is_active ? 'fv-badge-active' : 'fv-badge-inactive'" class="fv-badge flex-shrink-0 ml-2">
                {{ company.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>

            <!-- Meta Row -->
            <div class="flex flex-wrap gap-x-4 gap-y-1 mb-3">
              <span v-if="company.legal_structure" class="fv-meta-item">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
                {{ company.legal_structure }}
              </span>
              <span v-if="company.country" class="fv-meta-item">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ company.city ? company.city + ', ' : '' }}{{ company.country }}
              </span>
              <span v-if="company.currency" class="fv-meta-item">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                </svg>
                {{ company.currency }}
              </span>
              <!-- Tax type tag -->
              <span v-if="company.tax_type" class="fv-tag-gold">
                {{ company.tax_type === 'zakat' ? 'Zakat' : 'Corp. Tax' }}
              </span>
            </div>

            <div class="grid grid-cols-1 gap-1.5 mb-3 text-xs">
              <div class="flex items-center justify-between">
                <span class="fv-text-muted">Subscription End Date</span>
                <span class="fv-text-primary font-semibold">
                  {{ company.subscription_end_date ?? 'Not set' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="fv-text-muted">Remaining Time</span>
                <span :class="remainingTimeClass(company)">
                  {{ remainingTimeLabel(company) }}
                </span>
              </div>
            </div>

            <!-- Enabled modules count -->
            <div v-if="company.enabled_modules?.length" class="mb-3">
              <div class="flex items-center gap-2">
                <div class="fv-module-mini-bar flex-1">
                  <div class="fv-module-mini-fill"
                    :style="{ width: Math.round((company.enabled_modules.length / 12) * 100) + '%' }">
                  </div>
                </div>
                <span class="text-xs fv-text-muted">
                  <span class="fv-accent-teal font-semibold">{{ company.enabled_modules.length }}</span>/12 modules
                </span>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-3 border-t fv-border">
              <div class="flex items-center gap-3 fv-text-muted text-xs">
                <span class="flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                  </svg>
                  {{ company.users_count ?? 0 }} users
                </span>
                <span v-if="company.parent" class="flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                  </svg>
                  Sub of {{ company.parent.name }}
                </span>
              </div>
              <!-- Actions -->
              <div class="flex items-center gap-1">
                <Link :href="route('companies.show', company.id)" class="fv-action-btn" title="View Profile">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </Link>
                <Link :href="route('companies.edit', company.id)" class="fv-action-btn" title="Edit">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </Link>
                <button @click="confirmDelete(company)" class="fv-action-btn fv-action-btn-danger" title="Delete">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ══ DELETE MODAL ══════════════════════════════════════════════════════ -->
    <Transition name="modal">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="deleteTarget = null"></div>
        <div class="relative fv-modal z-10 w-full max-w-md rounded-xl p-6">
          <div class="flex items-center gap-4 mb-4">
            <div class="fv-modal-icon-danger">
              <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold fv-text-primary">Delete Company</h3>
              <p class="fv-text-muted text-sm">This action cannot be undone.</p>
            </div>
          </div>
          <p class="fv-text-muted text-sm mb-6">
            Are you sure you want to delete
            <span class="fv-text-primary font-semibold">{{ deleteTarget?.name }}</span>?
            All associated data will be permanently removed.
          </p>
          <div class="flex justify-end gap-3">
            <button @click="deleteTarget = null" class="fv-btn-secondary text-sm px-4 py-2 rounded-lg">Cancel</button>
            <button @click="doDelete" class="fv-btn-danger text-sm px-4 py-2 rounded-lg">Delete Company</button>
          </div>
        </div>
      </div>
    </Transition>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  companies: Array,
  warningDays: { type: Number, default: 30 },
  displayDaysPerMonth: { type: Number, default: 30 },
})

const search       = ref('')
const filterStatus = ref('')
const filterSubscription = ref('')

const filtered = computed(() =>
  props.companies.filter(c => {
    const q = search.value.toLowerCase()
    if (q && !c.name.toLowerCase().includes(q) && !c.trade_name?.toLowerCase().includes(q)) return false
    if (filterStatus.value === 'active'   && !c.is_active) return false
    if (filterStatus.value === 'inactive' &&  c.is_active) return false
    if (filterSubscription.value === 'expired' && !c.is_expired) return false
    if (filterSubscription.value === 'expiring' && !c.is_expiring_soon) return false
    if (filterSubscription.value === 'active' && (c.is_expired || c.is_expiring_soon)) return false
    return true
  })
)

function initials(name) {
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

function remainingTimeLabel(company) {
  const remaining = company.remaining_days

  if (remaining === null || remaining === undefined) return 'Not set'
  if (remaining < 0) return 'Expired'
  if (remaining <= props.warningDays) return `${remaining} day${remaining === 1 ? '' : 's'}`

  const months = Math.ceil(remaining / props.displayDaysPerMonth)
  return `${months} month${months === 1 ? '' : 's'}`
}

function remainingTimeClass(company) {
  const remaining = company.remaining_days

  if (remaining === null || remaining === undefined) return 'fv-text-muted'
  if (remaining <= props.warningDays) return 'text-red-400 font-semibold'

  return 'text-emerald-400 font-semibold'
}

const deleteTarget = ref(null)
function confirmDelete(company) { deleteTarget.value = company }
function doDelete() {
  if (!deleteTarget.value) return
  router.delete(route('companies.destroy', deleteTarget.value.id), {
    onFinish: () => { deleteTarget.value = null },
  })
}
</script>

<style scoped>
/* Breadcrumb gold */
.fv-breadcrumb { color: var(--fv-gold, #BA7517); }

/* Accent */
.fv-accent-teal { color: #48C4D8; }

/* New Company button */
.fv-btn-primary-link {
  display: inline-flex; align-items: center; gap: 0.5rem;
  background-color: var(--fv-blue, #1490A8);
  color: white; font-size: 0.875rem; font-weight: 600;
  padding: 0.625rem 1.25rem; border-radius: 0.5rem;
  text-decoration: none; border: none; cursor: pointer;
  transition: background-color 0.15s ease, box-shadow 0.15s ease;
  white-space: nowrap;
}
.fv-btn-primary-link:hover {
  background-color: var(--fv-blue-hover, #0F6E7E);
  box-shadow: 0 0 0 3px var(--fv-blue-dim);
}

/* Flash messages */
.fv-flash-success {
  background: rgba(16,185,129,0.08);
  border: 1px solid rgba(16,185,129,0.25);
  color: #34d399;
}
.fv-flash-error {
  background: rgba(239,68,68,0.08);
  border: 1px solid rgba(239,68,68,0.25);
  color: #f87171;
}

/* Input extras */
.fv-input-field {
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
}
.fv-input-field option { background-color: var(--fv-bg-card, #112240); }

/* Empty state icon */
.fv-empty-icon {
  width: 3.5rem; height: 3.5rem;
  background-color: var(--fv-blue-dim);
  border: 1px solid var(--fv-blue-border);
  border-radius: 1rem;
  display: flex; align-items: center; justify-content: center;
  color: #48C4D8;
}

/* Module mini progress bar */
.fv-module-mini-bar {
  height: 3px; background-color: var(--fv-border, #1B3558);
  border-radius: 9999px; overflow: hidden;
}
.fv-module-mini-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--fv-blue, #1490A8), #48C4D8);
  border-radius: 9999px; transition: width 0.3s ease;
}

/* Delete button */
.fv-btn-danger {
  background-color: rgba(239,68,68,0.12);
  border: 1px solid rgba(239,68,68,0.3);
  color: #f87171; font-weight: 600; cursor: pointer;
  transition: all 0.15s ease;
}
.fv-btn-danger:hover { background-color: rgba(239,68,68,0.2); }

/* Modal */
.fv-modal {
  background-color: var(--fv-bg-modal, #0E1E34);
  border: 1px solid var(--fv-border, #1B3558);
  box-shadow: var(--fv-shadow);
}
.fv-modal-icon-danger {
  width: 2.5rem; height: 2.5rem; border-radius: 9999px;
  background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>