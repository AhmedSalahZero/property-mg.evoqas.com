<template>
  <Head :title="`${company.name} — Vero Property Management`" />
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg">

      <!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
      <div class="fv-header-bg border-b fv-border">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">

            <!-- Left: back + company identity -->
            <div class="flex items-center gap-4">
              <Link :href="route('companies.index')" class="fv-back-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
              </Link>
              <div class="flex items-center gap-3">
                <div class="fv-avatar-lg">{{ initials(company.name) }}</div>
                <div>
                  <p class="text-xs font-bold uppercase tracking-widest fv-breadcrumb mb-0.5">
                    Companies · Profile
                  </p>
                  <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-bold fv-text-primary">{{ company.name }}</h1>
                    <span :class="company.is_active ? 'fv-badge-active' : 'fv-badge-inactive'" class="fv-badge">
                      {{ company.is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span v-if="company.tax_type" class="fv-tag-gold">
                      {{ company.tax_type === 'zakat' ? 'Zakat' : 'Corp. Tax' }}
                    </span>
                  </div>
                  <p v-if="company.trade_name" class="fv-text-muted text-sm mt-0.5">{{ company.trade_name }}</p>
                </div>
              </div>
            </div>

            <!-- Right: actions -->
            <div class="flex items-center gap-2 flex-shrink-0">
              <Link :href="route('companies.edit', company.id)"
                class="fv-btn-secondary-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
              </Link>
              <button @click="confirmDelete = true" class="fv-btn-danger-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ OPEN COMPANY CTA BANNER ═══════════════════════════════════════════ -->
      <div class="fv-open-banner">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="fv-open-banner-icon">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
              <div>
                <p class="text-sm font-semibold fv-text-primary">Enter Company Application</p>
                <p class="text-xs fv-text-muted">Access all modules, KPIs, and analysis for {{ company.name }}</p>
              </div>
            </div>
            <Link :href="route('company.properties.dashboard', company.id)" class="fv-btn-open-company">
              Open Company
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
              </svg>
            </Link>
          </div>
        </div>
      </div>

      <!-- ══ MAIN CONTENT ══════════════════════════════════════════════════════ -->
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- ── Row 1: Details + Stats ─────────────────────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- Company Details -->
          <div class="lg:col-span-2 fv-card">
            <h2 class="fv-card-title mb-4">Company Details</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div v-for="field in detailFields" :key="field.label" class="fv-detail-row">
                <span class="fv-detail-label">{{ field.label }}</span>
                <span class="fv-detail-value">{{ field.value || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Quick Stats -->
          <div class="flex flex-col gap-4">
            <!-- Users stat -->
            <div class="fv-stat-card">
              <div class="fv-stat-icon fv-stat-icon-teal">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-2xl font-bold fv-text-primary">{{ userCount }}</p>
                <p class="text-xs fv-text-muted">Total Users</p>
              </div>
            </div>
            <!-- Admins stat -->
            <div class="fv-stat-card">
              <div class="fv-stat-icon fv-stat-icon-gold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
              </div>
              <div>
                <p class="text-2xl font-bold fv-text-primary">{{ adminCount }}</p>
                <p class="text-xs fv-text-muted">Company Admins</p>
              </div>
            </div>
            <!-- Modules stat -->
            <div class="fv-stat-card">
              <div class="fv-stat-icon fv-stat-icon-navy">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
              </div>
              <div>
                <p class="text-2xl font-bold fv-text-primary">{{ company.enabled_modules?.length ?? 0 }}</p>
                <p class="text-xs fv-text-muted">Active Modules</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Enabled Modules ────────────────────────────────────────────── -->
        <div v-if="company.enabled_modules?.length" class="fv-card">
          <h2 class="fv-card-title mb-4">Enabled Modules</h2>
          <div class="flex flex-wrap gap-2">
            <span v-for="(label, key) in modules" :key="key">
              <span v-if="company.enabled_modules.includes(key)" class="fv-tag">
                {{ label }}
              </span>
            </span>
          </div>
        </div>

        <!-- ── Company Structure ───────────────────────────────────────────── -->
        <div v-if="company.parent || company.subsidiaries?.length" class="fv-card">
          <h2 class="fv-card-title mb-4">Company Structure</h2>
          <div class="space-y-3">

            <div v-if="company.parent" class="fv-inner-card flex items-center gap-3 p-3 rounded-lg">
              <div class="fv-structure-icon">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
              </div>
              <div>
                <p class="text-xs fv-text-muted">Parent Company</p>
                <p class="text-sm font-semibold fv-text-primary">{{ company.parent.name }}</p>
              </div>
              <Link :href="route('companies.show', company.parent.id)" class="ml-auto fv-link text-xs">View →</Link>
            </div>

            <div v-if="company.subsidiaries?.length">
              <p class="text-xs fv-text-muted uppercase tracking-wider font-semibold mb-2">
                Subsidiaries ({{ company.subsidiaries.length }})
              </p>
              <div class="space-y-2">
                <div v-for="sub in company.subsidiaries" :key="sub.id"
                  class="fv-inner-card flex items-center gap-3 p-3 rounded-lg">
                  <div class="fv-avatar-sm">{{ initials(sub.name) }}</div>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold fv-text-primary truncate">{{ sub.name }}</p>
                    <p v-if="sub.legal_structure" class="text-xs fv-text-muted">{{ sub.legal_structure }}</p>
                  </div>
                  <span :class="sub.is_active ? 'fv-badge-active' : 'fv-badge-inactive'" class="fv-badge flex-shrink-0">
                    {{ sub.is_active ? 'Active' : 'Inactive' }}
                  </span>
                  <Link :href="route('companies.show', sub.id)" class="fv-link text-xs">View →</Link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Users ──────────────────────────────────────────────────────── -->
        <div class="fv-card">
          <div class="flex items-center justify-between mb-4">
            <h2 class="fv-card-title">
              Users
              <span class="fv-text-muted font-normal text-xs ml-1">({{ company.users?.length ?? 0 }})</span>
            </h2>
            <Link :href="route('users.create')" class="fv-link-btn text-xs">+ Add User</Link>
          </div>

          <div v-if="company.users?.length" class="space-y-2">
            <div v-for="user in company.users" :key="user.id"
              class="fv-inner-card flex items-center gap-3 p-3 rounded-lg">
              <div class="fv-user-avatar flex-shrink-0">{{ initials(user.name) }}</div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold fv-text-primary truncate">{{ user.name }}</p>
                <p class="text-xs fv-text-muted truncate">
                  {{ user.email }}{{ user.job_title ? ' · ' + user.job_title : '' }}
                </p>
              </div>
              <span class="fv-role-tag">{{ formatRole(user.role) }}</span>
              <span :class="user.is_active ? 'fv-badge-active' : 'fv-badge-inactive'" class="fv-badge flex-shrink-0">
                {{ user.is_active ? 'Active' : 'Inactive' }}
              </span>
              <Link :href="route('users.edit', user.id)" class="fv-link text-xs">Edit →</Link>
            </div>
          </div>
          <p v-else class="fv-text-muted text-sm">No users assigned to this company yet.</p>
        </div>

        <!-- ── Notes ──────────────────────────────────────────────────────── -->
        <div v-if="company.notes" class="fv-card">
          <h2 class="fv-card-title mb-3">Internal Notes</h2>
          <p class="text-sm fv-text-muted whitespace-pre-line">{{ company.notes }}</p>
        </div>

      </div>
    </div>

    <!-- ══ DELETE MODAL ══════════════════════════════════════════════════════ -->
    <Transition name="modal">
      <div v-if="confirmDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="confirmDelete = false"></div>
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
            Are you sure you want to permanently delete
            <span class="fv-text-primary font-semibold">{{ company.name }}</span>?
          </p>
          <div class="flex justify-end gap-3">
            <button @click="confirmDelete = false" class="fv-btn-secondary text-sm px-4 py-2 rounded-lg cursor-pointer">Cancel</button>
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
  company:    Object,
  userCount:  Number,
  adminCount: Number,
  modules:    Object,
})

const confirmDelete = ref(false)
function doDelete() { router.delete(route('companies.destroy', props.company.id)) }

function initials(name) {
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

function formatRole(role) {
  const map = {
    company_admin: 'Admin', manager: 'Manager',
    sales_manager: 'Sales Mgr', analyst: 'Analyst', viewer: 'Viewer',
  }
  return map[role] ?? role
}

const detailFields = computed(() => [
  { label: 'Legal Structure',   value: props.company.legal_structure },
  { label: 'Established Year',  value: props.company.established_year },
  { label: 'Tax Type',          value: props.company.tax_type === 'zakat' ? 'Zakat' : props.company.tax_type === 'corporate_income_tax' ? 'Corporate Income Tax' : null },
  { label: 'Currency',          value: props.company.currency },
  { label: 'Fiscal Year Start', value: props.company.fiscal_year_start ? monthName(props.company.fiscal_year_start) : null },
  { label: 'Registration No.',  value: props.company.registration_number },
  { label: 'Tax ID',            value: props.company.tax_id },
  { label: 'Country',           value: props.company.country },
  { label: 'City',              value: props.company.city },
  { label: 'Phone',             value: props.company.phone },
  { label: 'Email',             value: props.company.email },
  { label: 'Website',           value: props.company.website },
])

function monthName(n) {
  return ['','January','February','March','April','May','June','July',
    'August','September','October','November','December'][n] ?? n
}
</script>

<style scoped>
/* Gold breadcrumb */
.fv-breadcrumb { color: var(--fv-gold, #BA7517); }

/* Back button */
.fv-back-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  background-color: var(--fv-bg-card, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  border-radius: 0.5rem; color: var(--fv-text-muted, #6B96B8);
  transition: all 0.15s ease; flex-shrink: 0; text-decoration: none;
}
.fv-back-btn:hover { border-color: var(--fv-border-focus, #1490A8); color: #48C4D8; }

/* Large avatar */
.fv-avatar-lg {
  width: 3rem; height: 3rem;
  background: linear-gradient(135deg, var(--fv-blue, #1490A8), #0C447C);
  border-radius: 0.75rem;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 700; color: white;
  letter-spacing: 0.05em; flex-shrink: 0;
}
.fv-avatar-sm {
  width: 2rem; height: 2rem;
  background: linear-gradient(135deg, var(--fv-blue, #1490A8), #0C447C);
  border-radius: 0.375rem;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.6rem; font-weight: 700; color: white; flex-shrink: 0;
}
.fv-user-avatar {
  width: 2rem; height: 2rem;
  background: linear-gradient(135deg, #0C447C, var(--fv-blue, #1490A8));
  border-radius: 9999px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.6rem; font-weight: 700; color: white;
}

/* Header action buttons */
.fv-btn-secondary-sm {
  display: inline-flex; align-items: center; gap: 0.375rem;
  background-color: var(--fv-bg-card, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-label, #94B8D0);
  font-size: 0.8125rem; font-weight: 500;
  padding: 0.5rem 0.875rem; border-radius: 0.5rem;
  text-decoration: none; cursor: pointer;
  transition: all 0.15s ease;
}
.fv-btn-secondary-sm:hover { border-color: var(--fv-border-focus, #1490A8); color: #48C4D8; }

.fv-btn-danger-sm {
  display: inline-flex; align-items: center; gap: 0.375rem;
  background-color: rgba(239,68,68,0.08);
  border: 1px solid rgba(239,68,68,0.2);
  color: #f87171; font-size: 0.8125rem; font-weight: 500;
  padding: 0.5rem 0.875rem; border-radius: 0.5rem; cursor: pointer;
  transition: all 0.15s ease;
}
.fv-btn-danger-sm:hover { background-color: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.35); }

/* ── OPEN COMPANY BANNER — the gateway CTA ─────────────────────────── */
.fv-open-banner {
  background: linear-gradient(90deg, rgba(12,68,124,0.4), rgba(20,144,168,0.15));
  border-bottom: 1px solid var(--fv-blue-border, rgba(20,144,168,0.20));
}
.fv-open-banner-icon {
  width: 2rem; height: 2rem;
  background-color: var(--fv-blue-dim);
  border: 1px solid var(--fv-blue-border);
  border-radius: 0.5rem;
  display: flex; align-items: center; justify-content: center;
  color: #48C4D8; flex-shrink: 0;
}
.fv-btn-open-company {
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: linear-gradient(135deg, var(--fv-blue, #1490A8), #0C447C);
  color: white; font-size: 0.875rem; font-weight: 700;
  padding: 0.625rem 1.5rem; border-radius: 0.5rem;
  text-decoration: none; border: none; cursor: pointer;
  transition: opacity 0.15s ease, box-shadow 0.15s ease;
  white-space: nowrap;
  box-shadow: 0 0 20px rgba(20,144,168,0.25);
}
.fv-btn-open-company:hover {
  opacity: 0.9;
  box-shadow: 0 0 28px rgba(20,144,168,0.4);
}

/* Card title */
.fv-card-title {
  font-size: 0.75rem; font-weight: 700;
  color: var(--fv-text-muted, #6B96B8);
  text-transform: uppercase; letter-spacing: 0.08em;
}

/* Inner card */
.fv-inner-card {
  background-color: var(--fv-bg, #0C1829);
  border: 1px solid var(--fv-border, #1B3558);
}

/* Detail rows */
.fv-detail-row    { display: flex; flex-direction: column; gap: 0.2rem; }
.fv-detail-label  { font-size: 0.7rem; font-weight: 600; color: var(--fv-text-muted, #6B96B8); text-transform: uppercase; letter-spacing: 0.07em; }
.fv-detail-value  { font-size: 0.875rem; color: var(--fv-text-primary, #F1F5F9); }

/* Stat cards */
.fv-stat-card {
  background-color: var(--fv-bg-card, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  border-radius: 0.75rem; padding: 1rem;
  display: flex; align-items: center; gap: 0.875rem;
  flex: 1;
}
.fv-stat-icon {
  width: 2.25rem; height: 2.25rem; border-radius: 0.5rem;
  border: 1px solid; display: flex; align-items: center;
  justify-content: center; flex-shrink: 0;
}
.fv-stat-icon-teal { background-color: var(--fv-blue-dim); border-color: var(--fv-blue-border); color: #48C4D8; }
.fv-stat-icon-gold { background-color: var(--fv-gold-dim); border-color: var(--fv-gold-border); color: #FAC775; }
.fv-stat-icon-navy {
  background: linear-gradient(135deg, rgba(12,68,124,0.5), rgba(20,144,168,0.15));
  border-color: var(--fv-blue-border); color: #48C4D8;
}

/* Structure icon */
.fv-structure-icon {
  width: 2rem; height: 2rem; border-radius: 0.5rem;
  background-color: var(--fv-blue-dim);
  border: 1px solid var(--fv-blue-border);
  display: flex; align-items: center; justify-content: center;
  color: #48C4D8; flex-shrink: 0;
}

/* Role tag */
.fv-role-tag {
  font-size: 0.65rem; font-weight: 600;
  background-color: var(--fv-blue-dim);
  color: #48C4D8;
  border: 1px solid var(--fv-blue-border);
  padding: 0.15rem 0.5rem; border-radius: 0.35rem;
  white-space: nowrap;
}

/* Links */
.fv-link { color: var(--fv-blue, #1490A8); text-decoration: none; transition: color 0.15s; }
.fv-link:hover { color: #48C4D8; }
.fv-link-btn {
  font-size: 0.75rem; font-weight: 600;
  color: var(--fv-blue, #1490A8);
  background: none; border: none; cursor: pointer;
  padding: 0; transition: color 0.15s;
  text-decoration: none;
}
.fv-link-btn:hover { color: #48C4D8; }

/* Danger button */
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