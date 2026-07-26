<template>
  <div class="managed-combo-root" style="position:relative;">
    <label v-if="label" class="fv-label">
      {{ label }}
      <span v-if="required" class="text-red-400">*</span>
    </label>

    <button
      type="button"
      ref="triggerRef"
      @click="toggleOpen"
      class="fv-input w-full rounded-lg px-3 py-2 text-sm flex items-center justify-between gap-2 text-left"
    >
      <span :class="modelValue ? 'fv-text-primary' : 'fv-text-muted'" class="truncate">
        {{ modelValue || placeholder }}
      </span>
      <svg class="w-4 h-4 fv-text-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <p v-if="error" class="err-msg">{{ error }}</p>

    <Teleport to="body">
      <div v-if="open"
        class="fixed inset-0 z-40"
        @click="closePanel"
        @keydown.esc="closePanel">
      </div>

      <div v-if="open"
        ref="panelRef"
        class="fixed z-50 rounded-lg shadow-xl overflow-hidden"
        :style="{ top: pos.top + 'px', left: pos.left + 'px', width: pos.width + 'px', visibility: ready ? 'visible' : 'hidden' }"
      >
        <div style="background:var(--fv-bg-modal,#0E1E34); border:1px solid var(--fv-border,#21518B);" class="rounded-lg">

          <!-- Search -->
          <div class="p-2" style="border-bottom:1px solid var(--fv-border,#1B3558);">
            <input
              ref="searchRef"
              v-model="search"
              type="text"
              class="fv-input w-full rounded-md px-2 py-1.5 text-sm"
              placeholder="Search…"
              @keydown.esc="closePanel"
            />
          </div>

          <!-- Current value not yet in the list -->
          <div v-if="modelValue && !optionExists(modelValue)"
            class="px-3 py-2 text-xs flex items-center justify-between gap-2"
            style="border-bottom:1px solid var(--fv-border,#1B3558); color:var(--fv-text-muted,#6B96B8);">
            <span class="truncate">Current: “{{ modelValue }}” (not in list)</span>
            <button type="button" class="add-to-list-btn flex-shrink-0" @click="addToListFromCurrent">
              + Add to list
            </button>
          </div>

          <!-- Options list -->
          <div class="max-h-56 overflow-y-auto">
            <div v-if="!filteredOptions.length && !search.trim()" class="px-3 py-3 text-xs fv-text-muted">
              No entries yet — add the first one below.
            </div>
            <div v-if="!filteredOptions.length && search.trim()" class="px-3 py-3 text-xs fv-text-muted">
              No matches for “{{ search.trim() }}”.
            </div>

            <div v-for="opt in filteredOptions" :key="opt.id"
              class="group flex items-center gap-1 px-2 py-1.5 hover:bg-[rgba(20,144,168,0.10)]"
            >
              <template v-if="editingId === opt.id">
                <input
                  v-model="editingName"
                  type="text"
                  class="fv-input flex-1 rounded-md px-2 py-1 text-sm"
                  @keydown.enter.prevent="saveEdit(opt)"
                  @keydown.esc.prevent="cancelEdit"
                />
                <button type="button" class="icon-btn" title="Save" @click="saveEdit(opt)">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </button>
                <button type="button" class="icon-btn" title="Cancel" @click="cancelEdit">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </template>
              <template v-else>
                <button type="button" class="flex-1 text-left text-sm fv-text-primary px-1 py-0.5 truncate"
                  @click="select(opt)">
                  {{ opt.name }}
                </button>
                <button type="button" class="icon-btn opacity-0 group-hover:opacity-100" title="Rename"
                  @click="startEdit(opt)">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button type="button" class="icon-btn icon-btn-danger opacity-0 group-hover:opacity-100" title="Delete"
                  @click="requestDelete(opt)">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </template>
            </div>
          </div>

          <!-- Add new -->
          <div style="border-top:1px solid var(--fv-border,#1B3558);">
            <template v-if="adding">
              <div class="flex items-center gap-1 p-2">
                <input
                  ref="addRef"
                  v-model="newName"
                  type="text"
                  class="fv-input flex-1 rounded-md px-2 py-1 text-sm"
                  placeholder="New entry name…"
                  @keydown.enter.prevent="saveAdd"
                  @keydown.esc.prevent="cancelAdd"
                />
                <button type="button" class="icon-btn" title="Save" @click="saveAdd">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </button>
                <button type="button" class="icon-btn" title="Cancel" @click="cancelAdd">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </template>
            <button v-else type="button"
              class="w-full text-left px-3 py-2 text-xs font-medium hover:bg-[rgba(20,144,168,0.08)]"
              style="color:#48C4D8;"
              @click="startAdd">
              + Add New
            </button>
          </div>

        </div>
      </div>

      <!-- ── DELETE CONFIRM MODAL — matches the app's Delete-Property style ── -->
      <div v-if="deleteTarget"
        class="fixed inset-0 flex items-center justify-center z-[60] px-4"
        style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);"
        @click.self="cancelDelete">
        <div class="rounded-2xl p-6 w-full max-w-sm shadow-2xl"
          style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">
          <div class="w-10 h-10 rounded-full flex items-center justify-center mb-4"
            style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25);">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#f87171;">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <h3 class="fv-text-primary font-bold text-base mb-1">Delete “{{ deleteTarget.name }}”?</h3>
          <p class="fv-text-muted text-sm mb-5">
            Properties already using this value keep it — it just won't be suggested to anyone else anymore.
          </p>
          <p v-if="deleteError" class="text-xs mb-3" style="color:#f87171;">{{ deleteError }}</p>
          <div class="flex gap-3">
            <button type="button" @click="cancelDelete" class="flex-1 btn-sm modal-btn-ghost">Cancel</button>
            <button type="button" @click="confirmDelete" :disabled="deleting" class="flex-1 btn-sm modal-btn-danger">
              {{ deleting ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  companyId:       { type: [Number, String], required: true },
  modelValue:      { type: String, default: '' },
  options:         { type: Array, default: () => [] }, // [{id,name}]
  label:           { type: String, default: '' },
  placeholder:     { type: String, default: '— Select —' },
  required:        { type: Boolean, default: false },
  storeRouteName:  { type: String, required: true },
  updateRouteName: { type: String, required: true },
  destroyRouteName:{ type: String, required: true },
  error:           { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'update:options'])

const open = ref(false)
const ready = ref(false)
const search = ref('')
const triggerRef = ref(null)
const panelRef = ref(null)
const searchRef = ref(null)
const addRef = ref(null)
const pos = ref({ top: 0, left: 0, width: 240 })

const editingId = ref(null)
const editingName = ref('')
const adding = ref(false)
const newName = ref('')

function csrfHeaders() {
  const headers = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (meta) { headers['X-CSRF-TOKEN'] = meta; return headers }
  const row = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))
  if (row) headers['X-XSRF-TOKEN'] = decodeURIComponent(row.split('=').slice(1).join('='))
  return headers
}

const filteredOptions = computed(() => {
  const q = search.value.trim().toLowerCase()
  const list = props.options || []
  if (!q) return list
  return list.filter(o => o.name.toLowerCase().includes(q))
})

function optionExists(name) {
  const n = (name || '').trim().toLowerCase()
  return (props.options || []).some(o => o.name.trim().toLowerCase() === n)
}

function positionPanel() {
  const btn = triggerRef.value
  if (!btn) return
  const rect = btn.getBoundingClientRect()
  const PANEL_H = panelRef.value?.offsetHeight || 260

  let top = rect.bottom + 4
  if (window.innerHeight - rect.bottom < PANEL_H + 8) {
    top = Math.max(8, rect.top - PANEL_H - 4)
  }
  pos.value = { top, left: rect.left, width: rect.width }
  ready.value = true
}

function toggleOpen() {
  if (open.value) { closePanel(); return }
  open.value = true
  ready.value = false
  search.value = ''
  cancelEdit()
  cancelAdd()
  nextTick(() => {
    nextTick(() => {
      positionPanel()
      searchRef.value?.focus()
    })
  })
}

function closePanel() {
  open.value = false
  ready.value = false
  cancelEdit()
  cancelAdd()
}

function select(opt) {
  emit('update:modelValue', opt.name)
  closePanel()
}

function addToListFromCurrent() {
  newName.value = props.modelValue
  saveAdd()
}

// ── Add new ──────────────────────────────────────────────────────────
function startAdd() {
  adding.value = true
  newName.value = ''
  nextTick(() => addRef.value?.focus())
}
function cancelAdd() {
  adding.value = false
  newName.value = ''
}
async function saveAdd() {
  const name = newName.value.trim()
  if (!name) return
  try {
    const res = await fetch(route(props.storeRouteName, props.companyId), {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfHeaders(),
      body: JSON.stringify({ name }),
    })
    const body = await res.json().catch(() => ({}))
    if (!res.ok) return
    const created = body.data
    if (created) {
      const exists = (props.options || []).some(o => o.id === created.id)
      const updated = exists ? props.options : [...props.options, created].sort((a, b) => a.name.localeCompare(b.name))
      emit('update:options', updated)
      emit('update:modelValue', created.name)
    }
    cancelAdd()
    closePanel()
  } catch {
    // silent — the field still holds the typed text either way
  }
}

// ── Rename ───────────────────────────────────────────────────────────
function startEdit(opt) {
  editingId.value = opt.id
  editingName.value = opt.name
  nextTick(() => {
    const el = panelRef.value?.querySelector('input[type="text"]')
    el?.focus()
  })
}
function cancelEdit() {
  editingId.value = null
  editingName.value = ''
}
async function saveEdit(opt) {
  const name = editingName.value.trim()
  if (!name) return
  try {
    const res = await fetch(route(props.updateRouteName, [props.companyId, opt.id]), {
      method: 'PUT',
      credentials: 'same-origin',
      headers: csrfHeaders(),
      body: JSON.stringify({ name }),
    })
    const body = await res.json().catch(() => ({}))
    if (!res.ok) return
    const updatedRow = body.data
    const wasSelected = props.modelValue === opt.name
    const updatedList = (props.options || [])
      .map(o => (o.id === opt.id ? updatedRow : o))
      .sort((a, b) => a.name.localeCompare(b.name))
    emit('update:options', updatedList)
    if (wasSelected) emit('update:modelValue', updatedRow.name)
    cancelEdit()
  } catch {
    // silent
  }
}

// ── Delete ───────────────────────────────────────────────────────────
// Uses an in-app modal (not window.confirm) to match the app's look —
// a native dialog also risks the same focus/blur race that used to hide
// the Teleported dropdown panel underneath it.
const deleteTarget = ref(null)   // the option pending deletion, or null
const deleting     = ref(false)
const deleteError  = ref('')

function requestDelete(opt) {
  deleteError.value = ''
  deleteTarget.value = opt
}

function cancelDelete() {
  deleteTarget.value = null
  deleting.value = false
  deleteError.value = ''
}

async function confirmDelete() {
  const opt = deleteTarget.value
  if (!opt || deleting.value) return
  deleteError.value = ''
  deleting.value = true
  try {
    const res = await fetch(route(props.destroyRouteName, [props.companyId, opt.id]), {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: csrfHeaders(),
    })
    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      deleteError.value = body.message || 'Could not delete.'
      return
    }
    emit('update:options', (props.options || []).filter(o => o.id !== opt.id))
    cancelDelete()
  } catch {
    deleteError.value = 'Network error.'
  } finally {
    deleting.value = false
  }
}

const onScroll = () => { if (open.value) closePanel() }
const onResize = () => { if (open.value) closePanel() }
window.addEventListener('scroll', onScroll, { passive: true, capture: true })
window.addEventListener('resize', onResize)

function onGlobalEscape(e) {
  if (e.key === 'Escape' && deleteTarget.value) cancelDelete()
}
onMounted(() => {
  document.addEventListener('keydown', onGlobalEscape)
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll, { capture: true })
  window.removeEventListener('resize', onResize)
  document.removeEventListener('keydown', onGlobalEscape)
})
</script>

<style scoped>
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.375rem;
  color: var(--fv-text-muted, #6B96B8);
  flex-shrink: 0;
}
.icon-btn:hover { background: rgba(20,144,168,0.15); color: #48C4D8; }
.icon-btn-danger:hover { background: rgba(239,68,68,0.15); color: #f87171; }

/* Local copies — the host page's scoped .err-msg/.btn-xs/.btn-outline
   rules don't reach inside this child component's own template, so these
   are defined here to match the app's look regardless of which page
   this component is used on. */
.err-msg {
  font-size: 0.75rem;
  color: #f87171;
  margin-top: 0.25rem;
}
.add-to-list-btn {
  font-size: 0.75rem;
  padding: 0.25rem 0.625rem;
  border-radius: 0.375rem;
  background: transparent;
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-primary, #E2E8F0);
  cursor: pointer;
  font-weight: 500;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.add-to-list-btn:hover { border-color: #1490A8; color: #48C4D8; }

.btn-sm { font-size: 0.875rem; padding: 0.375rem 0.875rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; }
.btn-sm:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-btn-ghost {
  background: transparent;
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-muted, #6B96B8);
}
.modal-btn-ghost:hover { border-color: #1490A8; color: #48C4D8; }
.modal-btn-danger {
  background: #dc2626;
  color: #fff;
  border: none;
}
.modal-btn-danger:hover:not(:disabled) { background: #b91c1c; }
</style>
