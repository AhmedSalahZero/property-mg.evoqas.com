<template>
  <!--
    NavDropdown.vue — Dropdown tab for grouped sub-pages
    Shows a chevron arrow. Clicking opens the dropdown list.
    Each item shows a status dot just like NavTab.

    Dot colours:
      🟢 green  — calculated & up to date
      🟡 amber  — stale: was calculated but GA changed (recalculate to refresh)
      🔵 blue   — saved, not yet calculated
      ⚫ empty  — not started
  -->
  <div class="nav-dropdown" ref="container">
    <!-- Trigger button -->
    <button
      type="button"
      class="nav-tab"
      :class="{ 'nav-tab--active': active }"
      @click="toggleOpen"
    >
      <!-- Amber dot if ANY sub-item is stale (was calculated, GA changed) -->
      <span
        v-if="anyStale"
        class="status-dot status-dot--amber"
        title="One or more projections need recalculation after GA change"
      />
      <!-- Green dot if ALL sub-items calculated and up to date -->
      <span
        v-else-if="allDone"
        class="status-dot status-dot--green"
        title="All projections calculated & up to date"
      />
      <!-- Blue dot if all saved but none calculated -->
      <span
        v-else-if="anySaved"
        class="status-dot status-dot--blue"
        title="Saved — calculate each projection to see results"
      />
      {{ label }}
      <!-- Chevron -->
      <svg
        class="chevron"
        :class="{ 'chevron--open': open }"
        width="12" height="12"
        viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
      >
        <path d="M6 9l6 6 6-6"/>
      </svg>
    </button>

    <!-- Dropdown panel — uses position:fixed to escape all parent overflow containers -->
    <Transition name="dropdown">
      <div
        v-if="open"
        class="dropdown-panel"
        :style="{ top: panelTop + 'px', left: panelLeft + 'px' }"
      >
        <a
          v-for="item in items"
          :key="item.key"
          :href="item.href"
          class="dropdown-item"
          :class="{ 'dropdown-item--active': currentPage === item.key }"
          @click="open = false"
        >
          <!-- Status dot -->
          <span
            v-if="itemDot(item.status)"
            class="status-dot"
            :class="[`status-dot--${itemDot(item.status)}`, itemDot(item.status) === 'amber' ? 'status-dot--pulse' : '']"
            :title="itemTitle(item.status)"
          />
          <span v-else class="status-dot status-dot--empty" />
          <span class="item-label">{{ item.label }}</span>
          <!-- Stale badge — only when was-calculated-but-now-stale -->
          <span
            v-if="item.status?.is_calculated && item.status?.needs_recalculation"
            class="stale-badge"
            title="Recalculate — General Assumptions changed"
          >recalc</span>
        </a>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  label:       { type: String,  required: true },
  active:      { type: Boolean, default: false },
  items:       { type: Array,   default: () => [] },
  currentPage: { type: String,  default: '' },
})

const open      = ref(false)
const container = ref(null)
const panelTop  = ref(0)
const panelLeft = ref(0)

// Calculate where to place the fixed panel based on trigger button position
function updatePanelPosition() {
  if (!container.value) return
  const rect = container.value.getBoundingClientRect()
  panelTop.value  = rect.bottom + 6          // 6px gap below the button
  panelLeft.value = rect.left                 // align with left edge of button
}

function toggleOpen() {
  if (!open.value) updatePanelPosition()
  open.value = !open.value
}

// Close when clicking outside
function handleOutsideClick(e) {
  if (container.value && !container.value.contains(e.target)) {
    open.value = false
  }
}
onMounted(()  => document.addEventListener('click', handleOutsideClick))
onUnmounted(() => document.removeEventListener('click', handleOutsideClick))

// ── Aggregate status for the trigger button ──────────────────────────────

// Stale = was previously calculated, but GA changed → amber (not a failure, just needs refresh)
const anyStale = computed(() =>
  props.items.some(i => i.status?.is_calculated && i.status?.needs_recalculation)
)

const allDone = computed(() =>
  props.items.length > 0 &&
  props.items.every(i => i.status?.is_calculated && !i.status?.needs_recalculation)
)

const anySaved = computed(() =>
  props.items.some(i => i.status?.is_saved)
)

// ── Per-item dot colour ───────────────────────────────────────────────────
function itemDot(status) {
  if (!status) return null
  if (status.is_calculated && !status.needs_recalculation) return 'green'
  if (status.is_calculated && status.needs_recalculation)  return 'amber'   // was done, GA changed
  if (status.needs_recalculation && !status.is_calculated) return 'amber'   // saved, GA changed before first calc
  if (status.is_saved) return 'blue'
  return null
}

function itemTitle(status) {
  if (!status) return ''
  if (status.is_calculated && !status.needs_recalculation) return '✅ Calculated & up to date'
  if (status.is_calculated && status.needs_recalculation)  return '🔄 Previously calculated — recalculate after GA change'
  if (status.needs_recalculation)                          return '⚠️ Needs recalculation'
  if (status.is_saved)                                     return '💾 Saved — not yet calculated'
  return 'Not started'
}
</script>

<style scoped>
.nav-dropdown {
  position: relative;
  flex-shrink: 0;
}

/* Reuse same pill style as NavTab */
.nav-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  color: #94a3b8;
  cursor: pointer;
  white-space: nowrap;
  background: transparent;
  border: 1px solid transparent;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.nav-tab:hover   { background: #0f172a; color: #e2e8f0; }
.nav-tab--active { background: #1e3a5f; border-color: #1d4ed8; color: #60a5fa; }

.chevron {
  transition: transform 0.2s;
}
.chevron--open { transform: rotate(180deg); }

/* Dropdown panel — fixed so it escapes ALL overflow:hidden parents */
.dropdown-panel {
  position: fixed;   /* ← key change: escapes every parent container */
  min-width: 260px;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 10px;
  padding: 6px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
  z-index: 99999;   /* ← raised from 9999 to beat any sticky/fixed page elements */
}
.dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  color: #94a3b8;
  text-decoration: none;
  transition: background 0.12s, color 0.12s, border-color 0.12s;
  border: 1px solid transparent;
  cursor: pointer;
}
.dropdown-item:hover         { background: #0f172a; color: #e2e8f0; }
.dropdown-item--active       { background: #1e3a5f; border: 1px solid #1d4ed8; color: #60a5fa; }

.item-label {
  flex: 1;
}

/* "recalc" badge shown next to stale-but-previously-calculated items */
.stale-badge {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: #f59e0b;
  background: rgba(245,158,11,0.12);
  border: 1px solid rgba(245,158,11,0.3);
  border-radius: 4px;
  padding: 1px 5px;
  white-space: nowrap;
  flex-shrink: 0;
}

/* Status dots */
.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-dot--empty { background: #334155; }
.status-dot--green { background: #22c55e; }
.status-dot--amber { background: #f59e0b; }
.status-dot--blue  { background: #60a5fa; }

/* Subtle pulse on stale amber dots — draws attention without being alarming */
@keyframes stale-pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.5; }
}
.status-dot--pulse {
  animation: stale-pulse 2s ease-in-out infinite;
}

/* Dropdown animation */
.dropdown-enter-active, .dropdown-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.dropdown-enter-from, .dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ── Light mode overrides ─────────────────────────────────────────────── */
:global([data-theme="light"]) .nav-tab {
  color: #1e3a5f;
}
:global([data-theme="light"]) .nav-tab:hover {
  background: rgba(59,130,246,0.08);
  color: #1d4ed8;
}
:global([data-theme="light"]) .nav-tab--active {
  background: rgba(59,130,246,0.12);
  border-color: #3B82F6;
  color: #1d4ed8;
}
:global([data-theme="light"]) .dropdown-panel {
  background: #ffffff;
  border-color: #e2e8f0;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}
:global([data-theme="light"]) .dropdown-item {
  color: #1e3a5f;
}
:global([data-theme="light"]) .dropdown-item:hover {
  background: rgba(59,130,246,0.08);
  color: #1d4ed8;
}
:global([data-theme="light"]) .dropdown-item--active {
  background: rgba(59,130,246,0.12);
  border-color: #3B82F6;
  color: #1d4ed8;
}
:global([data-theme="light"]) .status-dot--empty { background: #cbd5e1; }
:global([data-theme="light"]) .stale-badge {
  color: #b45309;
  background: rgba(245,158,11,0.08);
  border-color: rgba(180,83,9,0.3);
}
</style>