<template>
  <div class="tag-input-root">
    <label v-if="label" class="fv-label">{{ label }}</label>
    <p v-if="hint" class="text-xs fv-text-muted mb-2">{{ hint }}</p>

    <div
      class="rounded-lg px-2 py-2 min-h-[2.75rem] flex flex-wrap gap-2 items-center"
      style="background:var(--fv-bg-input,#0D1E38); border:1px solid var(--fv-border,#1B3558);"
      @click="focusInput"
    >
      <span
        v-for="tag in modelValue"
        :key="tag.id"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium"
        style="background:rgba(20,144,168,0.18); border:1px solid rgba(20,144,168,0.35); color:#48C4D8;"
      >
        {{ tag.name }}
        <button
          type="button"
          class="leading-none opacity-70 hover:opacity-100"
          style="color:inherit;"
          title="Rename tag (renames it everywhere it's used)"
          @click.stop="openRenameModal(tag)"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </button>
        <button
          type="button"
          class="leading-none opacity-70 hover:opacity-100"
          style="color:inherit;"
          @click.stop="removeTag(tag.id)"
        >
          ×
        </button>
      </span>

      <input
        ref="inputRef"
        v-model="query"
        type="text"
        class="flex-1 min-w-[8rem] bg-transparent outline-none text-sm fv-text-primary py-0.5"
        style="border:none;"
        :placeholder="placeholder"
        autocomplete="off"
        @keydown="onKeydown"
        @focus="onFocus"
        @blur="onBlur"
      />
    </div>

    <div
      v-if="showDropdown && (suggestions.length || (query.trim() && !exactMatch))"
      class="relative z-30 mt-1 rounded-lg overflow-hidden shadow-xl"
      style="background:var(--fv-bg-modal,#0E1E34); border:1px solid var(--fv-border,#21518B);"
    >
      <div
        v-for="s in suggestions"
        :key="s.id"
        class="group w-full flex items-center gap-1 px-1"
      >
        <button
          type="button"
          class="flex-1 text-left px-2 py-2 text-sm fv-text-primary hover:bg-[rgba(20,144,168,0.12)] truncate"
          @mousedown.prevent="selectSuggestion(s)"
        >
          {{ s.name }}
        </button>
        <button
          type="button"
          class="tag-manage-btn opacity-0 group-hover:opacity-100"
          title="Rename tag (renames it everywhere it's used)"
          @mousedown.prevent="openRenameModal(s)"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </button>
        <button
          type="button"
          class="tag-manage-btn tag-manage-btn-danger opacity-0 group-hover:opacity-100"
          title="Delete tag (removes it from every property)"
          @mousedown.prevent="openDeleteModal(s)"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </button>
      </div>
      <button
        v-if="query.trim() && !exactMatch && allowCreate"
        type="button"
        class="w-full text-left px-3 py-2 text-xs fv-text-muted hover:bg-[rgba(20,144,168,0.08)]"
        style="border-top:1px solid var(--fv-border,#1B3558);"
        @mousedown.prevent="createAndAdd"
      >
        Create “{{ query.trim() }}”
      </button>
      <p v-if="!suggestions.length && !query.trim()" class="px-3 py-2 text-xs fv-text-muted">Type to search tags…</p>
    </div>

    <p v-if="error" class="text-xs mt-1" style="color:#f87171;">{{ error }}</p>

    <!-- ── RENAME TAG MODAL ──────────────────────────────────────────── -->
    <div v-if="renameTarget"
      class="fixed inset-0 flex items-center justify-center z-50 px-4"
      style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);"
      @click.self="closeRenameModal"
      @keydown.esc="closeRenameModal">
      <div class="rounded-2xl p-6 w-full max-w-sm shadow-2xl"
        style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">
        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-4"
          style="background:rgba(20,144,168,0.12); border:1px solid rgba(20,144,168,0.3);">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#48C4D8;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </div>
        <h3 class="fv-text-primary font-bold text-base mb-1">Rename Tag</h3>
        <p class="fv-text-muted text-sm mb-4">
          This updates the tag everywhere it's used, on every property.
        </p>
        <input
          ref="renameInputRef"
          v-model="renameValue"
          type="text"
          class="fv-input w-full rounded-lg px-3 py-2 text-sm mb-2"
          maxlength="150"
          @keydown.enter.prevent="confirmRename"
          @keydown.esc.prevent="closeRenameModal"
        />
        <p v-if="modalError" class="text-xs mb-3" style="color:#f87171;">{{ modalError }}</p>
        <div class="flex gap-3" :class="modalError ? '' : 'mt-3'">
          <button type="button" @click="closeRenameModal" class="flex-1 btn-sm modal-btn-ghost">Cancel</button>
          <button type="button" @click="confirmRename" :disabled="renameSaving" class="flex-1 btn-sm modal-btn-confirm">
            {{ renameSaving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── DELETE TAG MODAL ──────────────────────────────────────────── -->
    <div v-if="deleteTarget"
      class="fixed inset-0 flex items-center justify-center z-50 px-4"
      style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);"
      @click.self="closeDeleteModal"
      @keydown.esc="closeDeleteModal">
      <div class="rounded-2xl p-6 w-full max-w-sm shadow-2xl"
        style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">
        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-4"
          style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25);">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#f87171;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <h3 class="fv-text-primary font-bold text-base mb-1">Delete Tag?</h3>
        <p class="fv-text-muted text-sm mb-5">
          <strong class="fv-text-primary">{{ deleteTarget.name }}</strong> will be removed from every property that has it.
        </p>
        <p v-if="modalError" class="text-xs mb-3" style="color:#f87171;">{{ modalError }}</p>
        <div class="flex gap-3">
          <button type="button" @click="closeDeleteModal" class="flex-1 btn-sm modal-btn-ghost">Cancel</button>
          <button type="button" @click="confirmDelete" :disabled="deleteSaving" class="flex-1 btn-sm modal-btn-danger">
            {{ deleteSaving ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  companyId: { type: [Number, String], required: true },
  label: { type: String, default: 'Description' },
  hint: { type: String, default: 'Add tags to describe this property. Search existing tags or create new ones.' },
  placeholder: { type: String, default: 'Search or type a new tag…' },
  modelValue: { type: Array, default: () => [] },
  debounceMs: { type: Number, default: 300 },
  allowCreate: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])

const query = ref('')
const suggestions = ref([])
const showDropdown = ref(false)
const loading = ref(false)
const error = ref('')
const inputRef = ref(null)

let debounceTimer = null

const selectedIds = computed(() => new Set(props.modelValue.map((t) => t.id)))

const exactMatch = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return false
  return props.modelValue.some((t) => t.name.toLowerCase() === q)
    || suggestions.value.some((s) => s.name.toLowerCase() === q)
})

function csrfHeaders() {
  const headers = {}
  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (meta) {
    headers['X-CSRF-TOKEN'] = meta
    return headers
  }
  const row = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))
  if (row) {
    const raw = row.split('=').slice(1).join('=')
    headers['X-XSRF-TOKEN'] = decodeURIComponent(raw)
  }
  return headers
}

function focusInput() {
  inputRef.value?.focus()
}

function removeTag(id) {
  emit(
    'update:modelValue',
    props.modelValue.filter((t) => t.id !== id)
  )
}

// ── Rename modal ─────────────────────────────────────────────────────
// Renames a tag globally (PUT /tags/{tag}) — since tags are attached by
// ID, this instantly reflects on every property using it, not just this
// one. Uses an in-app modal (not window.prompt) both to match the app's
// look and because a native dialog was blurring the search input and
// racing the dropdown's auto-close timer, which suppressed the chip's
// re-render until the next full page load.
const renameTarget  = ref(null)   // the tag object being renamed, or null
const renameValue   = ref('')
const renameSaving  = ref(false)
const renameInputRef = ref(null)
const modalError    = ref('')

function openRenameModal(tag) {
  modalError.value = ''
  renameTarget.value = tag
  renameValue.value = tag.name
  nextTick(() => renameInputRef.value?.focus())
}

function closeRenameModal() {
  renameTarget.value = null
  renameValue.value = ''
  renameSaving.value = false
  modalError.value = ''
}

async function confirmRename() {
  const tag = renameTarget.value
  if (!tag || renameSaving.value) return
  const trimmed = renameValue.value.trim()
  if (!trimmed) { modalError.value = 'Name cannot be empty.'; return }
  if (trimmed === tag.name) { closeRenameModal(); return }

  modalError.value = ''
  renameSaving.value = true
  try {
    const res = await fetch(route('company.tags.update', [props.companyId, tag.id]), {
      method: 'PUT',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...csrfHeaders() },
      body: JSON.stringify({ name: trimmed }),
    })
    const body = await res.json().catch(() => ({}))
    if (!res.ok) {
      modalError.value = body.message || 'Could not rename tag.'
      return
    }
    const updated = body.tag
    if (props.modelValue.some((t) => t.id === tag.id)) {
      emit('update:modelValue', props.modelValue.map((t) => (t.id === tag.id ? updated : t)))
    }
    suggestions.value = suggestions.value.map((s) => (s.id === tag.id ? updated : s))
    closeRenameModal()
  } catch {
    modalError.value = 'Network error.'
  } finally {
    renameSaving.value = false
  }
}

// ── Delete modal ─────────────────────────────────────────────────────
// Deletes a tag globally (DELETE /tags/{tag}) — removes it from every
// property it was attached to (pivot rows cascade-delete server-side).
const deleteTarget = ref(null)   // the tag object pending deletion, or null
const deleteSaving = ref(false)

function openDeleteModal(tag) {
  modalError.value = ''
  deleteTarget.value = tag
}

function closeDeleteModal() {
  deleteTarget.value = null
  deleteSaving.value = false
  modalError.value = ''
}

async function confirmDelete() {
  const tag = deleteTarget.value
  if (!tag || deleteSaving.value) return

  modalError.value = ''
  deleteSaving.value = true
  try {
    const res = await fetch(route('company.tags.destroy', [props.companyId, tag.id]), {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...csrfHeaders() },
    })
    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      modalError.value = body.message || 'Could not delete tag.'
      return
    }
    emit('update:modelValue', props.modelValue.filter((t) => t.id !== tag.id))
    suggestions.value = suggestions.value.filter((s) => s.id !== tag.id)
    closeDeleteModal()
  } catch {
    modalError.value = 'Network error.'
  } finally {
    deleteSaving.value = false
  }
}

function selectSuggestion(tag) {
  if (selectedIds.value.has(tag.id)) {
    query.value = ''
    suggestions.value = []
    return
  }
  emit('update:modelValue', [...props.modelValue, { id: tag.id, name: tag.name }])
  query.value = ''
  suggestions.value = []
  showDropdown.value = true
}

async function createAndAdd() {
  const name = query.value.trim()
  if (!name || exactMatch.value) return
  error.value = ''
  loading.value = true
  try {
    const res = await fetch(route('company.tags.store', props.companyId), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...csrfHeaders(),
      },
      body: JSON.stringify({ name }),
    })
    const body = await res.json().catch(() => ({}))
    if (!res.ok) {
      error.value = body.message || 'Could not create tag.'
      return
    }
    const tag = body.tag
    if (tag && !selectedIds.value.has(tag.id)) {
      emit('update:modelValue', [...props.modelValue, { id: tag.id, name: tag.name }])
    }
    query.value = ''
    suggestions.value = []
  } catch {
    error.value = 'Network error.'
  } finally {
    loading.value = false
  }
}

async function runSearch() {
  const q = query.value.trim()
  error.value = ''
  loading.value = true
  try {
    const url = route('company.tags.search', props.companyId) + (q ? `?q=${encodeURIComponent(q)}` : '')
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
    if (!res.ok) {
      suggestions.value = []
      return
    }
    const body = await res.json()
    const rows = body.data || []
    suggestions.value = rows.filter((s) => !selectedIds.value.has(s.id))
  } catch {
    suggestions.value = []
  } finally {
    loading.value = false
  }
}

function scheduleSearch() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debounceTimer = null
    runSearch()
  }, props.debounceMs)
}

watch(query, () => {
  scheduleSearch()
})

function onKeydown(e) {
  if (e.key === 'Enter') {
    e.preventDefault()
    if (suggestions.value.length === 1) {
      selectSuggestion(suggestions.value[0])
    } else if (props.allowCreate && query.value.trim() && !exactMatch.value) {
      createAndAdd()
    }
  } else if (e.key === 'Escape') {
    showDropdown.value = false
  } else if (e.key === 'Backspace' && !query.value && props.modelValue.length) {
    const copy = [...props.modelValue]
    copy.pop()
    emit('update:modelValue', copy)
  }
}

function onFocus() {
  showDropdown.value = true
  runSearch()
}

function onBlur() {
  setTimeout(() => {
    showDropdown.value = false
  }, 150)
}

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  document.removeEventListener('keydown', onGlobalEscape)
})

function onGlobalEscape(e) {
  if (e.key !== 'Escape') return
  if (renameTarget.value) closeRenameModal()
  else if (deleteTarget.value) closeDeleteModal()
}

onMounted(() => {
  document.addEventListener('keydown', onGlobalEscape)
})
</script>

<style scoped>
.tag-input-root .fv-label {
  display: block;
  font-size: 0.75rem;
  color: var(--fv-text-muted, #6B96B8);
  margin-bottom: 0.375rem;
  font-weight: 500;
}
.tag-manage-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.375rem;
  color: var(--fv-text-muted, #6B96B8);
  flex-shrink: 0;
}
.tag-manage-btn:hover { background: rgba(20,144,168,0.15); color: #48C4D8; }
.tag-manage-btn-danger:hover { background: rgba(239,68,68,0.15); color: #f87171; }

/* Local copies — the host page's scoped .btn-sm/.btn-teal/.btn-ghost
   rules don't reach inside this child component's own template, so these
   are defined here to match the app's Delete-Property-modal look
   regardless of which page this component is used on. */
.btn-sm { font-size: 0.875rem; padding: 0.375rem 0.875rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; }
.btn-sm:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-btn-ghost {
  background: transparent;
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-muted, #6B96B8);
}
.modal-btn-ghost:hover { border-color: #1490A8; color: #48C4D8; }
.modal-btn-confirm {
  background: var(--fv-blue, #1490A8);
  color: #fff;
  border: none;
}
.modal-btn-confirm:hover:not(:disabled) { background: #117a90; }
.modal-btn-danger {
  background: #dc2626;
  color: #fff;
  border: none;
}
.modal-btn-danger:hover:not(:disabled) { background: #b91c1c; }
</style>
