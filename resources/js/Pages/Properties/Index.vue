<template>
  <AuthenticatedLayout>
    <div class="p-6" style="background:var(--fv-bg); min-height:100vh;">

      <!-- ── PAGE HEADER ──────────────────────────────────────────────── -->
      <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
            style="background:linear-gradient(135deg,#1490A8,#0C447C);">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
          </div>
          <div>
            <h1 class="text-xl font-bold fv-text-primary tracking-tight">Properties</h1>
            <p class="text-xs fv-text-muted mt-0.5">{{ company.name }}</p>
          </div>
        </div>
        <Link :href="route('company.properties.create', company.id)"
          class="btn-sm btn-teal flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Add Property
        </Link>
      </div>

      <!-- ── KPI STRIP ─────────────────────────────────────────────────── -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        <div v-for="kpi in kpiStrip" :key="kpi.label"
          class="rounded-xl px-4 py-3 flex items-center gap-3"
          style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
            :style="`background:${kpi.bg};`">
            <span class="text-base">{{ kpi.icon }}</span>
          </div>
          <div>
            <p class="text-xs fv-text-muted">{{ kpi.label }}</p>
            <p class="text-lg font-bold fv-text-primary leading-tight">{{ kpi.value }}</p>
          </div>
        </div>
      </div>

      <!-- ── FLASH MESSAGE ─────────────────────────────────────────────── -->
      <div v-if="$page.props.flash?.success"
        class="mb-4 px-4 py-3 rounded-lg text-sm font-medium flex items-center gap-2"
        style="background:rgba(20,184,166,0.12); border:1px solid rgba(20,184,166,0.3); color:#2dd4bf;">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        {{ $page.props.flash.success }}
      </div>

      <!-- ── FILTER BAR ────────────────────────────────────────────────── -->
      <div class="flex flex-wrap items-center gap-3 mb-4">

        <div class="flex gap-1 p-1 rounded-xl w-fit"
          style="background:rgba(11,26,48,0.7); border:1px solid var(--fv-border);">
          <button v-for="tab in tabs" :key="tab.key"
            @click="activeTab = tab.key"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-150"
            :class="activeTab === tab.key ? 'tab-active' : 'tab-inactive'">
            {{ tab.label }}
            <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full"
              :style="activeTab === tab.key
                ? 'background:rgba(255,255,255,0.15); color:#fff;'
                : 'background:rgba(27,53,88,0.6); color:var(--fv-text-muted,#6B96B8);'">
              {{ tab.count }}
            </span>
          </button>
        </div>

        <select v-model="filterOwnership"
          class="fv-select rounded-lg px-3 py-1.5 text-sm" style="min-width:10rem;">
          <option value="">All Ownership</option>
          <option value="fully_owned">Fully Owned</option>
          <option value="installments">With Installments</option>
          <option value="usufruct">Usufruct</option>
          <option value="managed">Managed</option>
        </select>

        <div class="relative flex-1" style="min-width:12rem; max-width:22rem;">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 fv-text-muted pointer-events-none"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input v-model="searchQuery" type="text" placeholder="Search name, code, location…"
            class="fv-input w-full rounded-lg pl-9 pr-3 py-1.5 text-sm" />
        </div>

        <span class="text-xs fv-text-muted ml-auto">
          {{ filteredProperties.length }} propert{{ filteredProperties.length !== 1 ? 'ies' : 'y' }}
        </span>
      </div>

      <!-- ── EMPTY STATE ───────────────────────────────────────────────── -->
      <div v-if="filteredProperties.length === 0"
        class="rounded-2xl py-16 flex flex-col items-center gap-4 text-center"
        style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center"
          style="background:rgba(20,144,168,0.1); border:1px solid rgba(20,144,168,0.2);">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#1490A8;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
        </div>
        <div>
          <p class="fv-text-primary font-semibold">No properties found</p>
          <p class="fv-text-muted text-sm mt-1">
            {{ searchQuery || filterOwnership ? 'Try adjusting your filters.' : 'Add your first property to get started.' }}
          </p>
        </div>
        <Link v-if="!searchQuery && !filterOwnership"
          :href="route('company.properties.create', company.id)"
          class="btn-sm btn-teal mt-1">+ Add Property</Link>
      </div>

      <!-- ── PROPERTIES TABLE ──────────────────────────────────────────── -->
      <div v-else class="rounded-xl overflow-hidden"
        style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border); background:rgba(11,26,48,0.6);">
                <th class="text-left px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider" style="min-width:14rem;">Property</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider">Nature</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider">Ownership</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider hidden md:table-cell">Location</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider hidden lg:table-cell">Category / Type</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider hidden lg:table-cell">Area</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider hidden xl:table-cell">Acq. Cost</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider">Market Value</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-amber-400 uppercase tracking-wider">Units</th>
                <th class="px-4 py-3" style="width:3.5rem;"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="prop in filteredProperties" :key="prop.id"
                class="table-row-hover transition-colors duration-100"
                style="border-bottom:1px solid rgba(27,53,88,0.4);">

                <!-- Property name + code -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 text-sm"
                      :style="`background:${natureColor(prop.nature)}18; border:1px solid ${natureColor(prop.nature)}35;`">
                      {{ natureIcon(prop.nature) }}
                    </div>
                    <div class="min-w-0">
                      <p class="fv-text-primary text-sm truncate" style="max-width:12rem;">
                        {{ prop.property_name }}
                      </p>
                      <p v-if="prop.property_code" class="text-xs fv-text-muted font-mono">
                        {{ prop.property_code }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Nature badge -->
                <td class="px-4 py-3">
                  <span class="text-xs px-2 py-1 rounded-full"
                    :style="`background:${natureColor(prop.nature)}18; color:${natureColor(prop.nature)};`">
                    {{ natureLabel(prop.nature) }}
                  </span>
                </td>

                <!-- Ownership badge -->
                <td class="px-4 py-3">
                  <span class="text-xs px-2 py-1 rounded-full"
                    style="background:rgba(186,117,23,0.12); color:#FAC775; border:1px solid rgba(186,117,23,0.25);">
                    {{ ownershipLabel(prop.ownership) }}
                  </span>
                </td>

                <!-- Status badge -->
                <td class="px-4 py-3">
                  <!-- Standalone unit -->
                  <template v-if="prop.nature === 'unit'">
                    <span class="text-xs px-2 py-1 rounded-full"
                      :style="unitStatusStyle(prop)">
                      {{ unitStatusLabel(prop) }}
                    </span>
                  </template>
                  <!-- Parent property: X of Y occupied -->
                  <template v-else>
                    <span v-if="prop.units && prop.units.length > 0" class="text-xs fv-text-primary">
                      <span :style="occupiedCount(prop) > 0 ? 'color:#4ade80;' : 'color:var(--fv-text-muted)'">
                        {{ occupiedCount(prop) }}
                      </span>
                      <span class="fv-text-muted"> / {{ prop.units.length }} occupied</span>
                    </span>
                    <span v-else class="text-xs fv-text-muted">—</span>
                  </template>
                </td>

                <!-- Location -->
                <td class="px-4 py-3 hidden md:table-cell">
                  <span v-if="prop.governorate" class="text-xs fv-text-primary">
                    {{ prop.governorate }}{{ prop.province ? ', ' + prop.province : '' }}
                  </span>
                  <span v-else class="text-xs fv-text-muted">—</span>
                </td>

                <!-- Category / Type -->
                <td class="px-4 py-3 hidden lg:table-cell">
                  <span v-if="prop.nature === 'unit'" class="text-xs text-cyan-400">
                    {{ prop.property_category?.category_name || '—' }}
                    <span v-if="prop.property_type"> / {{ prop.property_type.type_name }}</span>
                  </span>
                  <span v-else class="text-xs fv-text-muted">—</span>
                </td>

                <!-- Area — 2 decimal places max -->
                <td class="px-4 py-3 text-right hidden lg:table-cell">
                  <span v-if="prop.nature === 'unit' && prop.area" class="text-xs fv-text-primary">
                    {{ formatArea(prop.area) }}
                    <span class="text-cyan-400">{{ prop.unit_of_measurement || '' }}</span>
                  </span>
                  <span v-else class="text-xs fv-text-muted">—</span>
                </td>

                <!-- Acquisition Cost -->
                <td class="px-4 py-3 text-right hidden xl:table-cell">
                  <span v-if="prop.nature === 'unit' && prop.acquisition_cost" class="text-xs fv-text-primary">
                    {{ formatCurrency(prop.acquisition_cost, prop.currency) }}
                  </span>
                  <span v-else class="text-xs fv-text-muted">—</span>
                </td>

                <!-- Market Value -->
                <td class="px-4 py-3 text-right">
                  <div v-if="latestMV(prop)">
                    <p class="text-sm" style="color:#FAC775;">
                      {{ formatCurrency(latestMV(prop).market_value, prop.currency) }}
                    </p>
                    <p class="text-xs text-cyan-400">{{ latestMV(prop).value_date }}</p>
                  </div>
                  <span v-else class="text-xs fv-text-muted">—</span>
                </td>

                <!-- Units count -->
                <td class="px-4 py-3 text-center">
                  <span v-if="prop.nature !== 'unit'"
                    class="text-xs px-2 py-1 rounded-full fv-text-primary"
                    style="background:rgba(20,144,168,0.12); border:1px solid rgba(20,144,168,0.2);">
                    {{ prop.units?.length || 0 }}
                  </span>
                  <span v-else class="text-xs fv-text-muted">—</span>
                </td>

                <!-- ⋮ button — dropdown rendered via Teleport, never clipped -->
                <td class="px-4 py-3 text-center">
                  <button
                    :ref="el => { if (el) btnRefs[prop.id] = el }"
                    @click.stop="toggleDropdown(prop.id)"
                    class="fv-action-btn mx-auto"
                    title="Actions">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                      <circle cx="12" cy="5"  r="1.5"/>
                      <circle cx="12" cy="12" r="1.5"/>
                      <circle cx="12" cy="19" r="1.5"/>
                    </svg>
                  </button>
                </td>

              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── DELETE CONFIRM MODAL ──────────────────────────────────────── -->
      <div v-if="deleteTarget"
        class="fixed inset-0 flex items-center justify-center z-50 px-4"
        style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);">
        <div class="rounded-2xl p-6 w-full max-w-sm shadow-2xl"
          style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">
          <div class="w-10 h-10 rounded-full flex items-center justify-center mb-4"
            style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25);">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#f87171;">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <h3 class="fv-text-primary font-bold text-base mb-1">Delete Property?</h3>
          <p class="fv-text-muted text-sm mb-5">
            <strong class="fv-text-primary">{{ deleteTarget.property_name }}</strong> and all its units and market value records will be permanently removed.
          </p>
          <div class="flex gap-3">
            <button @click="deleteTarget = null" class="flex-1 btn-sm btn-ghost">Cancel</button>
            <button @click="doDelete" class="flex-1 btn-sm"
              style="background:#dc2626; color:#fff; border-radius:0.5rem; font-weight:600; font-size:0.875rem; padding:0.375rem 0.75rem; border:none; cursor:pointer;">
              Delete
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         TELEPORT — dropdown rendered at <body>, never clipped by overflow
    ══════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">

      <!-- Full-screen invisible backdrop closes on outside click -->
      <div v-if="openDropdown !== null"
        class="fixed inset-0"
        style="z-index:998;"
        @click="closeDropdown">
      </div>

      <!-- Dropdown panel -->
      <transition :name="openUpward ? 'drop-up' : 'drop-down'">
        <div v-if="openDropdown !== null && dropdownProp"
          ref="dropdownPanel"
          class="fixed rounded-xl py-1"
          :style="{
            top:        dropdownPos.top  + 'px',
            left:       dropdownPos.left + 'px',
            zIndex:     999,
            minWidth:   '14rem',
            background: 'var(--fv-bg-modal,#0E1E34)',
            border:     '1px solid var(--fv-border,#21518B)',
            boxShadow:  '0 8px 40px rgba(0,0,0,0.6)',
            visibility: dropdownReady ? 'visible' : 'hidden',
          }">

          <!-- Edit Property -->
          <Link
            :href="route('company.properties.edit', [company.id, dropdownProp.id])"
            class="dd-item px-4 py-2.5 text-sm"
            style="color:var(--fv-text-primary,#F1F5F9);">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#1490A8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Property
          </Link>

          <div style="border-top:1px solid var(--fv-border,#21518B); margin:0.25rem 0;"></div>

          <!-- Contracts -->
          <button @click="goTo('contracts')"
            class="dd-item px-4 py-2.5 text-sm"
            style="color:var(--fv-text-primary,#F1F5F9);">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#14b8a6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Contracts
          </button>

          <!-- Due Installments — installment ownership only -->
          <button v-if="dropdownProp.ownership === 'installments'"
            @click="goTo('installments')"
            class="dd-item px-4 py-2.5 text-sm"
            style="color:var(--fv-text-primary,#F1F5F9);">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#FFC82D;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Due Installments
          </button>

          <!-- Expense Cards -->
          <button @click="goTo('expenses')"
            class="dd-item px-4 py-2.5 text-sm"
            style="color:var(--fv-text-primary,#F1F5F9);">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#BA7517;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Expense Cards
          </button>

          <!-- Reports -->
          <button @click="goTo('reports')"
            class="dd-item px-4 py-2.5 text-sm"
            style="color:var(--fv-text-primary,#F1F5F9);">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Reports
          </button>

          <div style="border-top:1px solid var(--fv-border,#21518B); margin:0.25rem 0;"></div>

          <!-- Delete -->
          <button @click="confirmDelete(dropdownProp)"
            class="dd-item px-4 py-2.5 text-sm"
            style="color:#f87171;">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Delete Property
          </button>

        </div>
      </transition>

    </Teleport>

    <!-- ── INSTALLMENT MODAL ───────────────────────────────────────────── -->
    <InstallmentModal
      v-if="installmentProperty"
      :show="showInstallmentModal"
      :company="company"
      :property="installmentProperty"
      @close="showInstallmentModal = false"
    />

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import InstallmentModal from '@/Pages/Properties/InstallmentModal.vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:    Object,
  properties: { type: Array, default: () => [] },
})

// ── State ──────────────────────────────────────────────────────────────
const activeTab       = ref('all')
const filterOwnership = ref('')
const searchQuery     = ref('')
const deleteTarget    = ref(null)
const openDropdown    = ref(null)          // prop.id of open row, or null

// ── Installment Modal ──────────────────────────────────────────────────
const showInstallmentModal = ref(false)
const installmentProperty  = ref(null)
const dropdownPos     = ref({ top: 0, left: 0 })
const openUpward      = ref(false)         // true when dropdown flips above the button
const btnRefs         = ref({})            // prop.id → <button> element

// ── The property whose dropdown is currently open ──────────────────────
const dropdownProp = computed(() =>
  openDropdown.value !== null
    ? (props.properties.find(p => p.id === openDropdown.value) ?? null)
    : null
)

// ── Tabs ───────────────────────────────────────────────────────────────
const tabs = computed(() => [
  { key: 'all',      label: 'All',       count: props.properties.length },
  { key: 'unit',     label: 'Units',     count: props.properties.filter(p => p.nature === 'unit').length },
  { key: 'building', label: 'Buildings', count: props.properties.filter(p => p.nature === 'building').length },
  { key: 'land',     label: 'Land',      count: props.properties.filter(p => p.nature === 'land').length },
  { key: 'complex',  label: 'Complexes', count: props.properties.filter(p => p.nature === 'complex').length },
])

const filteredProperties = computed(() => {
  let list = props.properties
  if (activeTab.value !== 'all')  list = list.filter(p => p.nature === activeTab.value)
  if (filterOwnership.value)      list = list.filter(p => p.ownership === filterOwnership.value)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(p =>
      p.property_name?.toLowerCase().includes(q) ||
      p.property_code?.toLowerCase().includes(q) ||
      p.governorate?.toLowerCase().includes(q)   ||
      p.province?.toLowerCase().includes(q)      ||
      p.location?.toLowerCase().includes(q)
    )
  }
  return list
})

// ── KPI Strip ──────────────────────────────────────────────────────────
const kpiStrip = computed(() => {
  const all     = props.properties
  const units   = all.filter(p => p.nature === 'unit').length
  const bldgs   = all.filter(p => p.nature === 'building').length
  const land    = all.filter(p => p.nature === 'land').length
  const complex = all.filter(p => p.nature === 'complex').length
  return [
    { label: 'Total Properties', value: all.length, icon: '🏢', bg: 'rgba(20,144,168,0.15)' },
    { label: 'Standalone Units', value: units,       icon: '🏠', bg: 'rgba(20,144,168,0.12)' },
    { label: 'Buildings',        value: bldgs,       icon: '🏗️', bg: 'rgba(186,117,23,0.15)' },
    { label: 'Land',             value: land,        icon: '🌿', bg: 'rgba(20,184,166,0.12)' },
    { label: 'Complexes',        value: complex,     icon: '🏪', bg: 'rgba(139,92,246,0.12)' },
  ]
})

// ── Helpers ────────────────────────────────────────────────────────────
const natureLabel = (n) => ({ unit:'Unit', building:'Building', land:'Land', complex:'Complex' }[n] || n)
const natureIcon  = (n) => ({ unit:'🏠', building:'🏗️', land:'🌿', complex:'🏪' }[n] || '🏢')
const natureColor = (n) => ({ unit:'#1490A8', building:'#BA7517', land:'#14b8a6', complex:'#8b5cf6' }[n] || '#1490A8')

const ownershipLabel = (o) => ({
  fully_owned:'Fully Owned', installments:'With Installments',
  usufruct:'Usufruct', managed:'Managed',
}[o] || o)

// ── Status helpers ─────────────────────────────────────────────────────

// Convert MM/YYYY varchar to a comparable date (first day of that month)
const parseMmYyyy = (str) => {
  if (!str) return null
  const parts = str.split('/')
  if (parts.length !== 2) return null
  return new Date(parseInt(parts[1]), parseInt(parts[0]) - 1, 1)
}

// Determine status for a standalone unit (nature === 'unit')
const unitStatus = (prop) => {
  // 1. Has a running contract → Occupied
  if (prop.rent_contracts && prop.rent_contracts.length > 0) return 'occupied'

  // 2. Ownership = installments and delivery_date is in the future → Not Delivered
  if (prop.ownership === 'installments' && prop.installment_plan) {
    const delivery = parseMmYyyy(prop.installment_plan.delivery_date)
    if (delivery && delivery > new Date()) return 'not_delivered'
  }

  // 3. Otherwise → Vacant
  return 'vacant'
}

const unitStatusLabel = (prop) => ({
  occupied:      'Occupied',
  not_delivered: 'Not Delivered',
  vacant:        'Vacant',
}[unitStatus(prop)])

const unitStatusStyle = (prop) => {
  const s = unitStatus(prop)
  if (s === 'occupied')      return 'background:rgba(74,222,128,0.12); color:#4ade80; border:1px solid rgba(74,222,128,0.25);'
  if (s === 'not_delivered') return 'background:rgba(251,191,36,0.12); color:#fbbf24; border:1px solid rgba(251,191,36,0.25);'
  return 'background:rgba(107,150,184,0.1); color:#6B96B8; border:1px solid rgba(107,150,184,0.2);'
}

// Count occupied child units for a parent property
const occupiedCount = (prop) => {
  if (!prop.units) return 0
  return prop.units.filter(u => u.rent_contracts && u.rent_contracts.length > 0).length
}

const latestMV = (prop) => {
  if (prop.nature === 'unit') return prop.market_values?.[0] ?? null
  const allMVs = (prop.units || []).flatMap(u => u.market_values || [])
  if (!allMVs.length) return null
  return allMVs.sort((a, b) => b.value_date.localeCompare(a.value_date))[0]
}

const formatCurrency = (val, currency = 'EGP') => {
  if (!val) return '—'
  return new Intl.NumberFormat('en-EG', {
    style: 'currency', currency: currency || 'EGP',
    minimumFractionDigits: 0, maximumFractionDigits: 0,
  }).format(val)
}

// ── Area: max 2 decimal places, strip trailing zeros ──────────────────
const formatArea = (val) => {
  if (!val && val !== 0) return '—'
  const n = parseFloat(val)
  if (isNaN(n)) return String(val)
  if (n % 1 === 0) return n.toLocaleString()
  return parseFloat(n.toFixed(2)).toLocaleString()
}

// ── Dropdown positioning (Teleport to body) ────────────────────────────
const dropdownPanel   = ref(null)
const dropdownReady   = ref(false)   // false = invisible while measuring

const positionDropdown = (propId) => {
  const btn = btnRefs.value[propId]
  if (!btn) return
  const rect = btn.getBoundingClientRect()

  // position:fixed is viewport-relative — never add scrollY
  const PANEL_W   = 224
  const PANEL_H   = dropdownPanel.value?.offsetHeight || 200

  let left = rect.right - PANEL_W
  if (left < 8) left = 8
  if (left + PANEL_W > window.innerWidth - 8) left = window.innerWidth - PANEL_W - 8

  const spaceBelow = window.innerHeight - rect.bottom
  const shouldFlip = spaceBelow < PANEL_H + 8

  openUpward.value = shouldFlip
  const top = shouldFlip
    ? rect.top  - PANEL_H - 4
    : rect.bottom + 4

  dropdownPos.value = { top, left }
  dropdownReady.value = true
}

const toggleDropdown = (propId) => {
  if (openDropdown.value === propId) {
    closeDropdown()
    return
  }
  // Show panel hidden first so we can measure it, then reposition
  openDropdown.value  = propId
  dropdownReady.value = false
  nextTick(() => {
    nextTick(() => positionDropdown(propId))
  })
}

const closeDropdown = () => { openDropdown.value = null; dropdownReady.value = false }

// ── Navigate from dropdown ─────────────────────────────────────────────
const goTo = (section) => {
  const prop = dropdownProp.value;
  closeDropdown();
  if (!prop) return;

  if (section === 'contracts') {
    window.location.href = route('company.properties.contracts.index', { company: prop.company_id, property: prop.id });
  } else if (section === 'installments') {
    installmentProperty.value = prop
    showInstallmentModal.value = true
  } else if (section === 'expenses') {
    window.location.href = route('company.properties.expenses.index', { company: prop.company_id, property: prop.id });
  } else {
    alert(`"${section}" for "${prop.property_name}" — coming soon.`);
  }
}

// ── Delete ─────────────────────────────────────────────────────────────
const confirmDelete = (prop) => {
  closeDropdown()
  deleteTarget.value = prop
}
const doDelete = () => {
  if (!deleteTarget.value) return
  router.delete(
    route('company.properties.destroy', [props.company.id, deleteTarget.value.id]),
    { preserveScroll: true, onSuccess: () => { deleteTarget.value = null } }
  )
}

// ── Lifecycle ──────────────────────────────────────────────────────────
const onKeydown = (e) => { if (e.key === 'Escape') closeDropdown() }
const onScroll  = () => { if (openDropdown.value !== null) closeDropdown() }

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', closeDropdown)
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('resize', closeDropdown)
})
</script>

<style scoped>
.tab-active {
  background: var(--fv-blue, #1490A8);
  color: #fff;
  box-shadow: 0 2px 8px rgba(20,144,168,0.3);
}
.tab-inactive { color: var(--fv-text-muted, #6B96B8); }
.tab-inactive:hover {
  color: var(--fv-text-primary, #e2eef0);
  background: rgba(20,144,168,0.07);
}

.table-row-hover:hover { background: rgba(20,144,168,0.04); }

/* ── Dropdown items — NOT scoped so they work inside Teleport ── */
.dd-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  width: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  transition: background 0.1s ease;
}
.dd-item:hover { background: rgba(20,144,168,0.09); }

/* ── Dropdown fade+slide transition ── */
.drop-down-enter-active,
.drop-up-enter-active   { transition: opacity 0.12s ease, transform 0.12s ease; }
.drop-down-leave-active,
.drop-up-leave-active   { transition: opacity 0.08s ease; }
.drop-down-enter-from   { opacity: 0; transform: translateY(-4px); }
.drop-up-enter-from     { opacity: 0; transform: translateY(4px); }
.drop-down-leave-to,
.drop-up-leave-to       { opacity: 0; }

.btn-teal {
  background: var(--fv-blue, #1490A8);
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}
.btn-teal:hover { background: #117a90; }

.btn-sm  { font-size: 0.875rem; padding: 0.375rem 0.875rem; }

.btn-ghost {
  background: transparent;
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-muted, #6B96B8);
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.15s ease;
  font-weight: 500;
}
.btn-ghost:hover { border-color: #1490A8; color: #48C4D8; }

.fv-action-btn {
  width: 1.875rem;
  height: 1.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.45rem;
  color: var(--fv-text-muted, #6B96B8);
  background: transparent;
  border: 1px solid transparent;
  transition: all 0.15s ease;
  cursor: pointer;
}
.fv-action-btn:hover {
  color: #48C4D8;
  background: rgba(20,144,168,0.08);
  border-color: rgba(20,144,168,0.2);
}
</style>