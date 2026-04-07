<template>
  <Head :title="`Projects — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-[#0C1829] text-white">

      <!-- ══ HEADER ══ -->
      <div class="bg-[#0C1829] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <!-- <Link :href="`/companies/${company.id}`"
                  class="text-xs text-gray-500 hover:text-gray-300 transition-colors">
                  {{ company.name }}
                </Link> -->
                <span class="text-gray-700"></span>
                <span class="text-xs text-[#5fd38f] font-semibold uppercase tracking-widest">Projects</span>
              </div>
              <h1 class="text-2xl font-bold text-white">Project & Task Management</h1>
              <p class="text-gray-400 text-sm mt-1">{{ projects.length }} project{{ projects.length !== 1 ? 's' : '' }}</p>
            </div>
            <div class="flex items-center gap-3">
              <button @click="openCostRatesModal"
                class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                💰 Cost Rates
              </button>
              <button @click="openCreateModal"
                class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Project
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ CONTENT ══ -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="flash" class="mb-6 bg-green-900 border border-green-700 text-green-300 px-4 py-3 rounded-lg text-sm">{{ flash }}</div>

        <!-- Empty State -->
        <div v-if="projects.length === 0"
          class="bg-[#112240] border border-dashed border-gray-700 rounded-xl p-16 text-center">
          <div class="w-14 h-14 bg-blue-950/50 rounded-xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
          </div>
          <p class="text-white font-semibold mb-1">No projects yet</p>
          <p class="text-gray-500 text-sm mb-5">Create your first project to start tracking tasks and costs</p>
          <button @click="openCreateModal"
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            + Create Project
          </button>
        </div>

        <!-- Project Cards Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          <template v-for="proj in projects" :key="proj.id">
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden hover:border-blue-800/50 transition-all group">

              <!-- Top color bar by status -->
              <div :class="statusBarClass(proj.status)" class="h-1 w-full"></div>

              <div class="p-5">
                <!-- Header row -->
                <div class="flex items-start justify-between mb-3">
                  <div class="flex-1 min-w-0">
                    <div v-if="proj.phase" class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">{{ proj.phase }}</div>
                    <h3 class="font-bold text-white text-base leading-tight truncate">{{ proj.name }}</h3>
                  </div>
                  <div class="flex items-center gap-1.5 ml-3 flex-shrink-0">
                    <span :class="statusBadgeClass(proj.status)"
                      class="text-xs font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide">
                      {{ statusLabel(proj.status) }}
                    </span>
                  </div>
                </div>

                <p v-if="proj.description" class="text-gray-400 text-sm line-clamp-2 mb-4">{{ proj.description }}</p>

                <!-- Task Progress -->
                <div class="mb-4">
                  <div class="flex items-center justify-between text-xs mb-1.5">
                    <span class="text-gray-500">Tasks</span>
                    <span class="text-gray-300 font-medium">{{ proj.task_done }} / {{ proj.task_total }}</span>
                  </div>
                  <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 rounded-full transition-all"
                      :style="`width:${proj.task_total > 0 ? Math.round(proj.task_done / proj.task_total * 100) : 0}%`"></div>
                  </div>
                </div>

                <!-- Stats row -->
                <div class="flex items-center gap-4 text-xs text-gray-500 mb-4">
                  <span v-if="proj.start_date">📅 {{ fmtDate(proj.start_date) }}</span>
                  <span v-if="proj.end_date">→ {{ fmtDate(proj.end_date) }}</span>
                </div>

                <!-- Cost card -->
                <div v-if="proj.total_cost > 0"
                  class="bg-gray-800/60 rounded-lg px-3 py-2 mb-4 flex items-center justify-between">
                  <span class="text-xs text-gray-400">External Cost</span>
                  <span class="text-sm font-bold text-amber-400">{{ fmtMoney(proj.total_cost, proj.currency) }}</span>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 pt-3 border-t border-gray-800">
                  <Link :href="`/companies/${company.id}/projects/${proj.id}`"
                    class="flex-1 text-center text-sm font-semibold bg-blue-600/20 hover:bg-blue-600/40 text-blue-400 py-1.5 rounded-lg transition-colors">
                    Open Project →
                  </Link>
                  <button @click="openEditModal(proj)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <button @click="confirmDelete(proj)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 hover:bg-red-600 text-gray-400 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           CREATE / EDIT PROJECT MODAL
      ═══════════════════════════════════════════ -->
      <div v-if="showProjectModal"
        class="fixed inset-0 z-[200] flex items-start justify-center pt-20 px-4 pb-4"
        @click.self="showProjectModal = false">
        <div class="bg-[#112240] border border-gray-700 rounded-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
            <h3 class="text-base font-bold text-white">{{ editingProject ? 'Edit Project' : 'New Project' }}</h3>
            <button @click="showProjectModal = false" class="text-gray-500 hover:text-white">✕</button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-[#5fd38f] uppercase tracking-widest mb-1.5">Project Name *</label>
              <input v-model="form.name" type="text" placeholder="e.g. Acquisition Due Diligence"
                class="w-full bg-[#112240] border border-cyan-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-[#5fd38f] uppercase tracking-widest mb-1.5">Phase / Category</label>
              <input v-model="form.phase" type="text" placeholder="e.g. Phase 1: Due Diligence"
                class="w-full bg-[#112240] border border-cyan-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-[#5fd38f] uppercase tracking-widest mb-1.5">Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Brief project summary..."
                class="w-full bg-[#112240] border border-cyan-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 resize-none"/>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-[#5fd38f] uppercase tracking-widest mb-1.5">Status</label>
                <select v-model="form.status"
                  class="w-full bg-[#112240] border border-cyan-600 text-gray-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                  <option value="not_started">Not Started</option>
                  <option value="in_progress">In Progress</option>
                  <option value="on_hold">On Hold</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#5fd38f] uppercase tracking-widest mb-1.5">Currency</label>
                <input v-model="form.currency" type="text" placeholder="EGP"
                  class="w-full bg-[#112240] border border-cyan-600 text-gray-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"/>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-[#5fd38f] uppercase tracking-widest mb-1.5">Start Date</label>
                <input v-model="form.start_date" type="date"
                  class="w-full bg-[#112240] border border-cyan-600 text-gray-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#5fd38f] uppercase tracking-widest mb-1.5">End Date</label>
                <input v-model="form.end_date" type="date"
                  class="w-full bg-[#112240] border border-cyan-600 text-gray-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"/>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-gray-800 flex justify-end gap-3">
            <button @click="showProjectModal = false"
              class="px-4 py-2 text-sm text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">
              Cancel
            </button>
            <button @click="saveProject" :disabled="saving"
              class="px-5 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-50">
              {{ saving ? 'Saving...' : (editingProject ? 'Update Project' : 'Create Project') }}
            </button>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           COST RATES MODAL
      ═══════════════════════════════════════════ -->
      <div v-if="showCostRatesModal"
        class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="showCostRatesModal = false">
        <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-2xl shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
            <div>
              <h3 class="text-base font-bold text-white">Team Cost Rates</h3>
              <p class="text-xs text-gray-500 mt-0.5">Set hourly or daily rates per user to calculate project labor costs</p>
            </div>
            <button @click="showCostRatesModal = false" class="text-gray-500 hover:text-white">✕</button>
          </div>
          <div class="px-6 py-5">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-800">
                  <th class="text-left text-xs font-semibold text-blue-400 uppercase tracking-widest pb-2">Team Member</th>
                  <th class="text-center text-xs font-semibold text-blue-400 uppercase tracking-widest pb-2">Hourly Rate</th>
                  <th class="text-center text-xs font-semibold text-blue-400 uppercase tracking-widest pb-2">Daily Rate</th>
                  <th class="text-center text-xs font-semibold text-blue-400 uppercase tracking-widest pb-2">Currency</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <template v-for="user in companyUsers" :key="user.id">
                  <tr>
                    <td class="py-3 text-white font-medium">{{ user.name }}</td>
                    <td class="py-3 px-2">
                      <input v-model="rateForm[user.id].hourly_rate" type="number" min="0" step="0.01" placeholder="0.00"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:border-blue-500"/>
                    </td>
                    <td class="py-3 px-2">
                      <input v-model="rateForm[user.id].daily_rate" type="number" min="0" step="0.01" placeholder="0.00"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:border-blue-500"/>
                    </td>
                    <td class="py-3 px-2">
                      <input v-model="rateForm[user.id].currency" type="text" placeholder="USD"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:border-blue-500"/>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div class="px-6 py-4 border-t border-gray-800 flex justify-end gap-3">
            <button @click="showCostRatesModal = false"
              class="px-4 py-2 text-sm text-gray-400 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">Cancel</button>
            <button @click="saveCostRates" :disabled="saving"
              class="px-5 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save Rates' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ══ DELETE CONFIRM ══ -->
      <div v-if="deleteTarget"
        class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="deleteTarget = null">
        <div class="bg-gray-900 border border-red-800 rounded-2xl w-full max-w-sm shadow-2xl p-6 text-center">
          <div class="w-12 h-12 bg-red-900/40 rounded-xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h3 class="text-base font-bold text-white mb-1">Delete Project?</h3>
          <p class="text-gray-400 text-sm mb-5">This will permanently delete <strong class="text-white">{{ deleteTarget.name }}</strong> and all its tasks, logs, and expenses.</p>
          <div class="flex gap-3 justify-center">
            <button @click="deleteTarget = null" class="px-4 py-2 text-sm bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition-colors">Cancel</button>
            <button @click="deleteProject" class="px-5 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">Delete</button>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:      { type: Object, required: true },
  projects:     { type: Array,  default: () => [] },
  companyUsers: { type: Array,  default: () => [] },
  costRates:    { type: Object, default: () => ({}) },
})

const flash = ref(null)
const saving = ref(false)
const showProjectModal  = ref(false)
const showCostRatesModal = ref(false)
const editingProject = ref(null)
const deleteTarget = ref(null)

// ── Project Form ──
const emptyForm = () => ({
  name: '', description: '', phase: '', status: 'not_started',
  start_date: '', end_date: '', currency: props.company.base_currency || 'USD'
})
const form = reactive(emptyForm())

function openCreateModal() {
  editingProject.value = null
  Object.assign(form, emptyForm())
  showProjectModal.value = true
}
function openEditModal(proj) {
  editingProject.value = proj
  Object.assign(form, {
    name: proj.name, description: proj.description || '',
    phase: proj.phase || '', status: proj.status,
    start_date: proj.start_date || '', end_date: proj.end_date || '',
    currency: proj.currency || 'USD',
  })
  showProjectModal.value = true
}

async function saveProject() {
  if (!form.name.trim()) return
  saving.value = true
  const url = editingProject.value
    ? `/companies/${props.company.id}/projects/${editingProject.value.id}`
    : `/companies/${props.company.id}/projects`
  const method = editingProject.value ? 'PUT' : 'POST'
  await apiFetch(url, { method, body: JSON.stringify(form) })
  saving.value = false
  showProjectModal.value = false
  window.location.reload()
}

function confirmDelete(proj) { deleteTarget.value = proj }

async function deleteProject() {
  await apiFetch(`/companies/${props.company.id}/projects/${deleteTarget.value.id}`, { method: 'DELETE' })
  deleteTarget.value = null
  window.location.reload()
}

// ── Cost Rates Form ──
const rateForm = reactive({})
function openCostRatesModal() {
  props.companyUsers.forEach(u => {
    const existing = props.costRates[u.id]
    rateForm[u.id] = {
      hourly_rate: existing?.hourly_rate ?? '',
      daily_rate:  existing?.daily_rate ?? '',
      currency:    existing?.currency ?? 'USD',
    }
  })
  showCostRatesModal.value = true
}
async function saveCostRates() {
  saving.value = true
  const rates = props.companyUsers.map(u => ({
    user_id:     u.id,
    hourly_rate: rateForm[u.id].hourly_rate || null,
    daily_rate:  rateForm[u.id].daily_rate || null,
    currency:    rateForm[u.id].currency || 'USD',
  }))
  await apiFetch(`/companies/${props.company.id}/projects/cost-rates`, {
    method: 'POST', body: JSON.stringify({ rates })
  })
  saving.value = false
  showCostRatesModal.value = false
  flash.value = 'Cost rates saved.'
  setTimeout(() => flash.value = null, 3000)
}

// ── Helpers ──
function apiFetch(url, opts = {}) {
  const token = document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='))
  const xsrf  = token ? decodeURIComponent(token.trim().split('=')[1]) : ''
  return fetch(url, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf },
    ...opts,
  })
}

function fmtDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function fmtMoney(v, cur) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: cur || 'USD', maximumFractionDigits: 0 }).format(v)
}
function statusLabel(s) {
  return { not_started: 'Not Started', in_progress: 'In Progress', on_hold: 'On Hold', completed: 'Completed', cancelled: 'Cancelled' }[s] || s
}
function statusBarClass(s) {
  return { not_started: 'bg-gray-600', in_progress: 'bg-blue-500', on_hold: 'bg-yellow-500', completed: 'bg-green-500', cancelled: 'bg-red-500' }[s] || 'bg-gray-600'
}
function statusBadgeClass(s) {
  return { not_started: 'bg-gray-800 text-gray-400', in_progress: 'bg-blue-900/50 text-blue-300 border border-blue-700/40', on_hold: 'bg-yellow-900/50 text-yellow-300', completed: 'bg-green-900/50 text-green-300', cancelled: 'bg-red-900/50 text-red-300' }[s] || 'bg-gray-800 text-gray-400'
}
</script>