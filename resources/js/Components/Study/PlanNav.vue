<template>
  <!--
    PlanNav.vue
    VERO Property Management — Financial Annual Plan Navigation Bar
    Location: resources/js/Components/Study/PlanNav.vue

    Props:
      companyId   — company ID for URL generation
      planId      — plan ID for URL generation
      planBasis   — 'by_customer' | 'by_service'
      currentPage — string key:
                      'setup'
                      'sales'                       ← status page (customer or service)
                      'sales_customer_{id}'         ← individual customer projection
                      'sales_slot_{n}'              ← new customer slot projection
                      'sales_service_{id}'          ← service item projection
                      'other_revenues'
                      'manpower'
                      'expenses'
                      'fixed_assets_general'
                      'fixed_assets_per_employee'
                      'opening_balance'
                      'results'
      statuses    — object { section_key: { is_saved: bool, is_calculated?: bool } }

    Changes vs previous version:
      1. Sales Projections  → plain tab (no dropdown). Always lands on the
                              Status page for the plan's basis.
      2. Fixed Assets       → dropdown using position:fixed panel + click-toggle
                              + outside-click-close. Escapes any overflow:hidden
                              on the nav bar wrapper — this is why the dropdown
                              was invisible before.
  -->
  <div
    ref="navBar"
    style="background:var(--fv-bg-header); border-bottom:1px solid var(--fv-border); position:relative; z-index:300;"
  >
    <div class="w-full px-4 sm:px-6 lg:px-8" style="overflow-x:auto;">
      <div class="pn-row">

        <!-- ① Study Info ─────────────────────────────────────────── -->
        <a
          :href="`/companies/${companyId}/plans/${planId}/edit`"
          :class="['pn-tab', currentPage === 'setup' ? 'pn-tab--active' : '']"
        >
          Study Info
          <span
            :class="statuses['setup']?.is_saved ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'"
            style="margin-left:5px;"
          />
        </a>

        <span class="pn-divider" aria-hidden="true">|</span>

        <!-- ② Sales Projections — plain tab → Status page ──────── -->
        <!--
          No dropdown here. The status page IS the entry point for sales.
          From there the user clicks into individual customer / service projections.
          planBasis drives which status URL to use.
        -->
        <a
          :href="salesStatusHref"
          :class="['pn-tab', currentPage.startsWith('sales') ? 'pn-tab--active' : '']"
        >
          Sales Projections
          <span
            :class="salesSaved ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'"
            style="margin-left:5px;"
          />
        </a>

        <span class="pn-divider" aria-hidden="true">|</span>

        <!-- ③ Other Revenues ────────────────────────────────────── -->
        <a
          :href="`/companies/${companyId}/plans/${planId}/other-revenues`"
          :class="['pn-tab', currentPage === 'other_revenues' ? 'pn-tab--active' : '']"
        >
          Other Revenues
          <span
            :class="statuses['other_revenues']?.is_saved ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'"
            style="margin-left:5px;"
          />
        </a>

        <!-- ④ Manpower ───────────────────────────────────────────── -->
        <a
          :href="`/companies/${companyId}/plans/${planId}/manpower`"
          :class="['pn-tab', currentPage === 'manpower' ? 'pn-tab--active' : '']"
        >
          Manpower
          <span
            :class="statuses['manpower']?.is_saved ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'"
            style="margin-left:5px;"
          />
        </a>

        <!-- ⑤ Expenses ───────────────────────────────────────────── -->
        <a
          :href="`/companies/${companyId}/plans/${planId}/expenses`"
          :class="['pn-tab', currentPage === 'expenses' ? 'pn-tab--active' : '']"
        >
          Expenses
          <span
            :class="statuses['expenses']?.is_saved ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'"
            style="margin-left:5px;"
          />
        </a>

        <!-- ⑥ Fixed Assets — dropdown (position:fixed panel) ─────── -->
        <!--
          THE FIX: The old version used position:absolute on the panel and
          relied on hover. Because the nav bar has overflow:auto on its
          scroll wrapper, the absolutely-positioned panel was clipped and
          never visible.

          Solution (same as Finvero NavDropdown.vue):
            • position:fixed on the panel
            • JS calculates panel top/left from the trigger button's
              getBoundingClientRect() at click time
            • Click-to-toggle (not hover) — reliable on all devices
            • Document click listener closes it when user clicks elsewhere
        -->
        <div ref="fixedWrap" class="pn-drop-wrap">
          <button
            type="button"
            :class="[
              'pn-tab pn-drop-trigger',
              fixedAssetIsActive ? 'pn-tab--active' : ''
            ]"
            @click="toggleFixed"
          >
            <!-- Aggregate dot: teal if both sub-pages saved, slate otherwise -->
            Fixed Assets
            <span
              :class="fixedAssetsDotClass"
              style="margin-left:5px;"
            />
            <svg
              width="11" height="11"
              viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
              :style="`margin-left:3px; transition:transform 0.15s;
                       transform:${openFixed ? 'rotate(180deg)' : 'rotate(0deg)'}`"
            >
              <path d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <!-- Panel rendered in a Teleport so it is never clipped by a parent -->
          <Teleport to="body">
            <Transition name="pn-fade">
              <div
                v-if="openFixed"
                class="pn-fixed-panel"
                :style="{ top: fixedPanelTop + 'px', left: fixedPanelLeft + 'px' }"
              >
                <!-- General Fixed Assets -->
                <a
                  :href="`/companies/${companyId}/plans/${planId}/fixed-assets/general`"
                  :class="['pn-drop-item', currentPage === 'fixed_assets_general' ? 'pn-drop-item--active' : '']"
                  @click="openFixed = false"
                >
                  <span
                    :class="statuses['fixed_assets_general']?.is_saved
                      ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'"
                    style="flex-shrink:0;"
                  />
                  <span style="flex:1;">General Fixed Assets</span>
                  <svg v-if="currentPage === 'fixed_assets_general'"
                    width="11" height="11" viewBox="0 0 24 24"
                    fill="none" stroke="#48C4D8" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                </a>

                <!-- Per-Employee Fixed Assets -->
                <a
                  :href="`/companies/${companyId}/plans/${planId}/fixed-assets/per-employee`"
                  :class="['pn-drop-item', currentPage === 'fixed_assets_per_employee' ? 'pn-drop-item--active' : '']"
                  @click="openFixed = false"
                >
                  <span
                    :class="statuses['fixed_assets_per_employee']?.is_saved
                      ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'"
                    style="flex-shrink:0;"
                  />
                  <span style="flex:1;">Per-Employee Assets</span>
                  <svg v-if="currentPage === 'fixed_assets_per_employee'"
                    width="11" height="11" viewBox="0 0 24 24"
                    fill="none" stroke="#48C4D8" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                </a>
              </div>
            </Transition>
          </Teleport>
        </div>

        <!-- ⑦ Opening Balances — slim secondary style ────────────── -->
        <a
          :href="`/companies/${companyId}/plans/${planId}/opening-balance`"
          :class="['pn-secondary', currentPage === 'opening_balance' ? 'pn-secondary--active' : '']"
          title="Opening Balance Sheet"
        >
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2"/>
            <path d="M8 21h8M12 17v4"/>
          </svg>
          <span>Opening Balances</span>
          <span
            v-if="statuses['opening_balance']?.is_saved"
            class="pn-dot pn-dot--teal"
            style="margin-left:2px;"
          />
        </a>

        <!-- ⑧ Financial Results — gold accent ───────────────────── -->
        <a
          :href="`/companies/${companyId}/plans/${planId}/results`"
          :class="['pn-tab pn-tab--accent', currentPage === 'results' ? 'pn-tab--accent-active' : '']"
        >
          Financial Results
        </a>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
  companyId:   { type: [Number, String], required: true },
  planId:      { type: [Number, String], required: true },
  planBasis:   { type: String, default: 'by_customer' },
  currentPage: { type: String, default: '' },
  // salesItems is kept in the prop signature for backward compatibility
  // but is no longer used — Sales tab goes straight to the status page
  salesItems:  { type: Array,  default: () => [] },
  statuses:    { type: Object, default: () => ({}) },
})

// ── Sales tab — plain link to the correct status page ─────────────────────
const salesStatusHref = computed(() => {
  if (props.planBasis === 'by_service') {
    return `/companies/${props.companyId}/plans/${props.planId}/service-items/status`
  }
  return `/companies/${props.companyId}/plans/${props.planId}/customers/status`
})

// Show teal dot on Sales tab if any sales projection has been saved.
// statuses may contain keys like 'sales_customer_18', 'sales_slot_2', 'sales_service_5'
// We check for any key starting with 'sales_' that has is_saved = true,
// OR we check the dedicated 'sales' key if the controller sets one.
const salesSaved = computed(() => {
  if (props.statuses['sales']?.is_saved) return true
  return Object.entries(props.statuses).some(
    ([key, val]) => key.startsWith('sales_') && val?.is_saved
  )
})

// ── Fixed Assets dropdown — position:fixed panel ──────────────────────────
const fixedWrap      = ref(null)
const openFixed      = ref(false)
const fixedPanelTop  = ref(0)
const fixedPanelLeft = ref(0)

const fixedAssetIsActive = computed(() =>
  ['fixed_assets_general', 'fixed_assets_per_employee'].includes(props.currentPage)
)

// Aggregate dot: teal if at least one saved, slate if nothing saved yet
const fixedAssetsDotClass = computed(() => {
  const gen = props.statuses['fixed_assets_general']?.is_saved
  const emp = props.statuses['fixed_assets_per_employee']?.is_saved
  return (gen || emp) ? 'pn-dot pn-dot--teal' : 'pn-dot pn-dot--slate'
})

function toggleFixed(event) {
  if (!openFixed.value) {
    // Calculate position from the button bounding rect
    const rect = event.currentTarget.getBoundingClientRect()
    fixedPanelTop.value  = rect.bottom + 4   // 4px gap below the button
    fixedPanelLeft.value = rect.left          // align with left edge of button
  }
  openFixed.value = !openFixed.value
}

// Close when clicking anywhere outside the trigger + panel
function handleDocClick(e) {
  if (!openFixed.value) return
  const wrap = fixedWrap.value
  if (wrap && wrap.contains(e.target)) return
  // Also ignore clicks inside the teleported panel (it is outside the DOM tree of wrap)
  const panel = document.querySelector('.pn-fixed-panel')
  if (panel && panel.contains(e.target)) return
  openFixed.value = false
}

// Close on Escape key
function handleKeydown(e) {
  if (e.key === 'Escape') openFixed.value = false
}

onMounted(() => {
  document.addEventListener('click', handleDocClick)
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleDocClick)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
/* ── Nav row ───────────────────────────────────────────────────────────── */
.pn-row {
  display:     flex;
  align-items: center;
  gap:         0.125rem;   /* gap-0.5 */
  min-height:  52px;
}

/* ── Base tab ──────────────────────────────────────────────────────────── */
.pn-tab {
  display:         inline-flex;
  align-items:     center;
  padding:         0 14px;
  height:          52px;
  font-size:       13.5px;
  font-weight:     500;
  color:           #ffffff;
  text-decoration: none;
  border-bottom:   2px solid transparent;
  border-top:      none;
  border-left:     none;
  border-right:    none;
  white-space:     nowrap;
  flex-shrink:     0;
  transition:      color 0.15s, border-color 0.15s;
  cursor:          pointer;
  background:      transparent;
}
.pn-tab:hover           { color: #94C4D8; }
.pn-tab--active         { color: #48C4D8; border-bottom: 2px solid #1490A8; font-weight: 600; }
.pn-tab--accent         { color: #FAC775; }
.pn-tab--accent:hover   { color: #FFC82D; }
.pn-tab--accent-active  { color: #FFC82D; border-bottom: 2px solid #BA7517; font-weight: 600; }

/* ── Dropdown wrapper ──────────────────────────────────────────────────── */
.pn-drop-wrap    { position: relative; flex-shrink: 0; }
.pn-drop-trigger { /* inherits .pn-tab */ }

/* ── Fixed-position dropdown panel (rendered via Teleport to body) ─────── */
/* NOTE: This is NOT scoped-CSS-limited because it lives in <body> via Teleport.
   The class name pn-fixed-panel is unique enough to avoid collisions. */
</style>

<!-- Global styles for the teleported panel — must NOT be scoped -->
<style>
.pn-fixed-panel {
  position:      fixed;          /* escapes every overflow:hidden parent */
  min-width:     230px;
  max-height:    320px;
  overflow-y:    auto;
  background:    var(--fv-bg-card, #0f2235);
  border:        1px solid var(--fv-border, #1a3a55);
  border-radius: 10px;
  box-shadow:    0 10px 36px rgba(0, 0, 0, 0.55);
  z-index:       99999;          /* beats sticky headers, modals, everything */
  padding:       6px;
}

.pn-fixed-panel .pn-drop-item {
  display:         flex;
  align-items:     center;
  gap:             9px;
  padding:         9px 11px;
  border-radius:   7px;
  font-size:       13px;
  font-weight:     500;
  color:           #94B8D0;
  text-decoration: none;
  transition:      background 0.12s, color 0.12s;
  white-space:     nowrap;
  border:          1px solid transparent;
}
.pn-fixed-panel .pn-drop-item:hover {
  background: rgba(20, 144, 168, 0.10);
  color:      #48C4D8;
}
.pn-fixed-panel .pn-drop-item--active {
  background:   rgba(20, 144, 168, 0.15);
  border-color: rgba(20, 144, 168, 0.35);
  color:        #48C4D8;
  font-weight:  600;
}

/* Fade transition */
.pn-fade-enter-active,
.pn-fade-leave-active  { transition: opacity 0.14s, transform 0.14s; }
.pn-fade-enter-from,
.pn-fade-leave-to      { opacity: 0; transform: translateY(-5px); }
</style>

<!-- Scoped secondary tab + divider + dot styles ── -->
<style scoped>
.pn-secondary {
  display:         inline-flex;
  align-items:     center;
  gap:             5px;
  padding:         5px 10px;
  border-radius:   6px;
  font-size:       13px;
  font-weight:     500;
  color:           #6B96B8;
  text-decoration: none;
  border:          1px solid transparent;
  transition:      color 0.15s, border-color 0.15s, background 0.15s;
  white-space:     nowrap;
  flex-shrink:     0;
}
.pn-secondary:hover    { color: #94B8D0; border-color: var(--fv-border); background: rgba(20,144,168,0.04); }
.pn-secondary--active  { color: #48C4D8; border-color: #1490A8; background: rgba(20,144,168,0.10); }

.pn-divider {
  color:       var(--fv-border, #1a3a55);
  font-size:   16px;
  line-height: 1;
  padding:     0 3px;
  flex-shrink: 0;
  user-select: none;
}

.pn-dot         { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; display: inline-block; }
.pn-dot--teal   { background: #48C4D8; }
.pn-dot--slate  { background: #334E68; }
</style>