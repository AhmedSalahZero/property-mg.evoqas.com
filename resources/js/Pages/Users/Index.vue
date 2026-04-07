<template>
  <Head title="User Management — FinVero NBFS" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-[#0C1829] text-white">

      <!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
      <div class="bg-[#0E1E34]  border-b fv-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
              <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-0.5">
                Administration
              </p>
              <h1 class="text-2xl font-bold fv-text-primary">User Management</h1>
            </div>
            <Link
              v-if="canManageUsers"
              :href="route('users.create')"
              class="fv-btn-primary"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add User
            </Link>
          </div>
        </div>
      </div>

      <!-- ══ FILTERS ══════════════════════════════════════════════════════════ -->
      <div class="max-w-7xl mx-auto px-4 bg-[#0C1829] sm:px-6 lg:px-8 py-4">
        <div class="flex flex-wrap gap-3 items-center">

          <!-- Search -->
          <div class="relative flex-1 min-w-[220px] max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z"/>
            </svg>
            <input
              v-model="search"
              type="text"
              placeholder="Search users..."
              class="fv-input pl-9 w-full"
            />
          </div>

          <!-- Company filter (Super Admin only) -->
          <select v-if="authRole === 'super_admin'" v-model="filterCompany" class="fv-input">
            <option value="">All Companies</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>

          <!-- Role filter -->
          <select v-model="filterRole" class="fv-input">
            <option value="">All Roles</option>
            <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>

          <!-- Status filter -->
          <select v-model="filterStatus" class="fv-input">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>

          <!-- Count -->
          <span class="text-sm text-slate-500 ml-auto">
            {{ filteredUsers.length }} user{{ filteredUsers.length !== 1 ? 's' : '' }}
          </span>
        </div>
      </div>

      <!-- ══ FLASH MESSAGE ════════════════════════════════════════════════════ -->
      <div v-if="$page.props.flash?.success" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-2">
        <div class="fv-alert-success flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          {{ $page.props.flash.success }}
        </div>
      </div>

      <!-- ══ TABLE ════════════════════════════════════════════════════════════ -->
      <div class="max-w-7xl mx-auto px-4 bg-[#0C1829] sm:px-6 lg:px-8 pb-12">
        <div class="fv-card overflow-hidden">

          <!-- Empty state -->
          <div v-if="filteredUsers.length === 0" class="py-16 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-5.916-3.5M9 20H4v-2a4 4 0 015.916-3.5M15 7a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0zm-18 0a3 3 0 116 0 3 3 0 01-6 0z"/>
            </svg>
            <p class="text-slate-400 text-sm">No users found</p>
          </div>

          <!-- User rows -->
          <div v-else class="divide-y fv-divide">
            <div
              v-for="user in filteredUsers"
              :key="user.id"
              class="flex items-center gap-4 px-6 py-4 bg-[#112240] hover:bg-white/[0.02] transition-colors"
            >
              <!-- Avatar -->
              <div class="fv-avatar flex-shrink-0">
                {{ initials(user.name) }}
              </div>

              <!-- Name + Email + Company -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold fv-text-primary text-sm">{{ user.name }}</span>
                  <!-- Inactive badge -->
                  <span v-if="!user.is_active" class="fv-badge-inactive">Inactive</span>
                </div>
                <div class="text-xs text-orange-200 mt-0.5 truncate">{{ user.email }}</div>
                <div v-if="user.job_title" class="text-xs text-slate-600 mt-0.5">{{ user.job_title }}</div>
              </div>

              <!-- Company (Super Admin view) -->
              <div v-if="authRole === 'super_admin'" class="hidden md:block w-40 flex-shrink-0">
                <span class="text-xs text-blue-400">{{ user.company?.name ?? '—' }}</span>
              </div>

              <!-- Role badge -->
              <div class="flex-shrink-0">
                <span :class="roleBadgeClass(user.role)">
                  {{ roleLabel(user.role) }}
                </span>
              </div>

              <!-- Actions -->
              <div v-if="canManageUsers" class="flex items-center gap-2 flex-shrink-0">
                <!-- Toggle active -->
                <button
                  @click="toggleActive(user)"
                  :title="user.is_active ? 'Deactivate' : 'Activate'"
                  class="fv-icon-btn"
                  :class="user.is_active ? 'text-amber-400 hover:text-red-400' : 'text-slate-500 hover:text-emerald-400'"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="user.is_active" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </button>

                <!-- Edit -->
                <Link :href="route('users.edit', user.id)" class="fv-icon-btn text-slate-400 hover:text-blue-400">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </Link>

                <!-- Delete -->
                <button
                  @click="confirmDelete(user)"
                  class="fv-icon-btn text-slate-400 hover:text-red-400"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- ══ DELETE MODAL ═════════════════════════════════════════════════════ -->
      <Transition name="modal">
        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="deleteTarget = null"/>
          <div class="relative fv-modal w-full max-w-sm">
            <div class="p-6">
              <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center mb-4 mx-auto">
                <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
              </div>
              <h3 class="text-lg font-bold fv-text-primary text-center mb-1">Delete User</h3>
              <p class="text-sm text-slate-400 text-center mb-6">
                Are you sure you want to delete <strong class="text-white">{{ deleteTarget.name }}</strong>?
                This action cannot be undone.
              </p>
              <div class="flex gap-3">
                <button @click="deleteTarget = null" class="fv-btn-ghost flex-1">Cancel</button>
                <button @click="doDelete" class="fv-btn-danger flex-1">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </Transition>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  users:       Array,
  companies:   Array,
  authRole:    String,
  myCompanyId: Number,
})

// ── State ─────────────────────────────────────────────────────────────────────
const search        = ref('')
const filterCompany = ref('')
const filterRole    = ref('')
const filterStatus  = ref('')
const deleteTarget  = ref(null)

// ── Permissions ───────────────────────────────────────────────────────────────
const canManageUsers = computed(() =>
  props.authRole === 'super_admin' || props.authRole === 'company_admin'
)

// ── Filter ────────────────────────────────────────────────────────────────────
const filteredUsers = computed(() => {
  return props.users.filter(u => {
    const q = search.value.toLowerCase()
    if (q && !u.name.toLowerCase().includes(q) && !u.email.toLowerCase().includes(q)) return false
    if (filterCompany.value && u.company_id !== filterCompany.value) return false
    if (filterRole.value    && u.role !== filterRole.value) return false
    if (filterStatus.value === 'active'   && !u.is_active) return false
    if (filterStatus.value === 'inactive' &&  u.is_active) return false
    return true
  })
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const roleOptions = [
  { value: 'company_admin', label: 'Company Admin' },
  { value: 'manager',       label: 'Manager' },
  { value: 'analyst',       label: 'Analyst' },
  { value: 'viewer',        label: 'Viewer' },
]

function roleLabel(role) {
  return roleOptions.find(r => r.value === role)?.label ?? role
}

function roleBadgeClass(role) {
  const base = 'text-xs font-semibold px-2.5 py-0.5 rounded-full border '
  const map = {
    company_admin: 'bg-blue-500/10 text-blue-300 border-blue-500/30',
    manager:       'bg-violet-500/10 text-violet-300 border-violet-500/30',
    analyst:       'bg-amber-500/10 text-amber-300 border-amber-500/30',
    viewer:        'bg-slate-500/10 text-slate-400 border-slate-500/30',
  }
  return base + (map[role] ?? map.viewer)
}

function initials(name) {
  return name.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}

// ── Actions ───────────────────────────────────────────────────────────────────
function toggleActive(user) {
  router.patch(route('users.toggle-active', user.id), {}, { preserveScroll: true })
}

function confirmDelete(user) {
  deleteTarget.value = user
}

function doDelete() {
  router.delete(route('users.destroy', deleteTarget.value.id), {
    onFinish: () => { deleteTarget.value = null },
  })
}
</script>

<style scoped>
.fv-bg          { background-color: #0B1120; }
.fv-header-bg   { background-color: #0d1426; }
.fv-border      { border-color: #1e2d45; }
.fv-divide > * + * { border-color: #ffffff; }
.fv-text-primary { color: #f1f5f9; }

.fv-card {
  background-color: #0C1829;
  border: 1px solid #1e2d45;
  border-radius: 0.75rem;
}

.fv-modal {
  background-color: #0d1426;
  border: 1px solid #1e2d45;
  border-radius: 0.75rem;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6);
}

.fv-input {
  background-color: #0C1829;
  border: 1px solid #1e2d45;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: #f1f5f9;
  transition: border-color 0.15s;
  outline: none;
}
.fv-input:focus { border-color: #3B82F6; }
.fv-input option { background-color: #101827; }

.fv-avatar {
  width: 2.25rem;
  height: 2.25rem;
  background: linear-gradient(135deg, #1d4ed8, #3b82f6);
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  color: #e0eaff;
  letter-spacing: 0.02em;
}

.fv-icon-btn {
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.375rem;
  transition: all 0.15s;
  background: transparent;
  border: none;
  cursor: pointer;
}
.fv-icon-btn:hover { background-color: rgba(255,255,255,0.05); }

.fv-btn-primary {
  display: inline-flex; align-items: center; gap: 0.5rem;
  padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;
  background-color: #2563eb; color: white; text-decoration: none;
  transition: background-color 0.15s;
}
.fv-btn-primary:hover { background-color: #1d4ed8; }

.fv-btn-ghost {
  padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500;
  background-color: transparent; border: 1px solid #1e2d45; color: #94a3b8;
  cursor: pointer; transition: all 0.15s;
}
.fv-btn-ghost:hover { border-color: #334155; color: #f1f5f9; }

.fv-btn-danger {
  padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;
  background-color: #dc2626; color: white; border: none; cursor: pointer;
  transition: background-color 0.15s;
}
.fv-btn-danger:hover { background-color: #b91c1c; }

.fv-badge-inactive {
  font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
  padding: 0.125rem 0.5rem; border-radius: 9999px;
  background-color: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.25);
}

.fv-alert-success {
  background-color: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25);
  color: #6ee7b7; border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500;
}

/* Modal transition */
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>