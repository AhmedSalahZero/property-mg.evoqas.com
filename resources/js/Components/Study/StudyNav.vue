<template>
  <!--
    StudyNav.vue
    VERO Property Management — Study Navigation Bar
    Replaces the linear wizard with a horizontal tab bar + dropdowns.
    Shows status badges (✅ Saved | ⚠️ Stale | ○ Not Started) on every section.

    Props:
      companyId   — company ID for URL generation
      studyId     — study ID for URL generation
      currentPage — string key matching a navItem key e.g. 'leasing', 'manpower'
      streams     — array of active revenue stream keys e.g. ['leasing','direct_factoring']
      statuses    — object from StudySectionStatus::forStudy($studyId)
                    { section_key: { is_saved, is_calculated, needs_recalculation } }
  -->
  <div class="study-nav" style="background:#0E1E34; border-bottom:1px solid #1B3558; position:relative; z-index:9999; overflow:visible;">
    <div class="w-full px-4 sm:px-6 lg:px-8" style="overflow-x:auto; overflow-y:visible;">
      <div class="flex items-center gap-1" style="min-height:52px; overflow:visible;">

        <!-- ── 1. Study Information + 2. General Assumptions   :active="currentPage === tab.key" ── -->
        <NavTab
          v-for="tab in staticTabs"
          :key="tab.key"
          :label="tab.label"
          :href="tab.href"
          :active="currentPage === tab.key"
          :status="statuses[tab.key] ?? null"
        />

        

        <!-- ── Divider ─────────────────────────────────────── -->
        <span class="nav-divider" aria-hidden="true">|</span>

        <!-- ── 3. Sales Projections dropdown ──────────────── -->
        <NavDropdown
          label="Sales Projections"
          :active="salesStreamKeys.includes(currentPage)"
          :items="salesDropdownItems"
          :current-page="currentPage"
        />

        <!-- ── 4. Manpower ────────────────────────────────── -->
        <NavTab
          label="Manpower"
          :href="`/companies/${companyId}/financial-studies/${studyId}/manpower`"
          :active="currentPage === 'manpower'"
          :status="statuses['manpower'] ?? null"
        />

        <!-- ── 5. Expenses ────────────────────────────────── -->
        <NavTab
          label="Expenses"
          :href="`/companies/${companyId}/financial-studies/${studyId}/expenses`"
          :active="currentPage === 'expenses'"
          :status="statuses['expenses'] ?? null"
        />

        <!-- ── 6. Fixed Assets dropdown ───────────────────── -->
        <NavDropdown
          label="Fixed Assets"
          :active="['fixed_assets_general','fixed_assets_per_employee','fixed_assets_new_branches'].includes(currentPage)"
          :items="fixedAssetsItems"
          :current-page="currentPage"
        />

        <!-- ── Opening Balance — slim secondary link ─────────── -->
        <a
          :href="`/companies/${companyId}/financial-studies/${studyId}/opening-balance`"
          :class="[
            'nav-tab-secondary',
            currentPage === 'opening_balance' ? 'nav-tab-secondary--active' : ''
          ]"
          title="Opening Balance Sheet"
        >
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2"/>
            <path d="M8 21h8M12 17v4"/>
          </svg>
          <span class="nav-tab-secondary__label">Opening Balance</span>
          <span
            v-if="statuses['opening_balance']?.is_saved"
            class="status-dot status-dot--blue"
            style="margin-left:2px;"
          />
        </a>

        <!-- ── 7. Financial Results ───────────────────────── -->
        <NavTab
          label="Financial Results"
          :href="`/companies/${companyId}/financial-studies/${studyId}/results`"
          :active="currentPage === 'results'"
          :status="statuses['financial_results'] ?? null"
          accent
        />

      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import NavTab      from './NavTab.vue'
import NavDropdown from './NavDropdown.vue'

const props = defineProps({
  companyId:   { type: [Number, String], required: true },
  studyId:     { type: [Number, String], required: true },
  currentPage: { type: String, default: '' },
  streams:     { type: Array,  default: () => [] },
  statuses:    { type: Object, default: () => ({}) },
})

// ── Static tabs (always visible) ────────────────────────────────────────
const staticTabs = computed(() => [
  {
    key:   'setup',
    label: 'Study Information',
    href:  `/companies/${props.companyId}/financial-studies/${props.studyId}/edit`,
  },
  {
    key:   'general_assumptions',
    label: 'General Assumptions',
    href:  `/companies/${props.companyId}/financial-studies/${props.studyId}/general-assumptions`,
  },
])

// ── All possible sales stream options ───────────────────────────────────
const allStreams = [
  { key: 'leasing',           label: 'Leasing Projection',           route: 'leasing' },
  { key: 'direct_factoring',  label: 'Direct Factoring Projection',  route: 'direct-factoring' },
  { key: 'reverse_factoring', label: 'Reverse Factoring Projection', route: 'reverse-factoring' },
  { key: 'ijara_mortgage',    label: 'Ijara Mortgage Projection',    route: 'ijara-mortgage' },
  { key: 'portfolio_mortgage',label: 'Portfolio Mortgage Projection',route: 'portfolio-mortgage' },
  { key: 'consumer_finance',  label: 'Consumer Finance Projection',  route: 'consumer-finance' },
  { key: 'sukuk',             label: 'Sukuk Projection',             route: 'sukuk' },
  { key: 'micro_finance',     label: 'Microfinance Projection',      route: 'microfinance' },
  { key: 'securitization',    label: 'Securitization Projection',    route: 'securitization' },
]

// Only show streams the company has selected
const salesDropdownItems = computed(() =>
  allStreams
    .filter(s => props.streams.includes(s.key))
    .map(s => ({
      key:    s.key,
      label:  s.label,
      href:   `/companies/${props.companyId}/financial-studies/${props.studyId}/sales/${s.route}`,
      status: props.statuses[s.key] ?? null,
    }))
)

const salesStreamKeys = computed(() => salesDropdownItems.value.map(i => i.key))

// ── Fixed assets sub-items ───────────────────────────────────────────────
const fixedAssetsItems = computed(() => [
  {
    key:    'fixed_assets_general',
    label:  'General Fixed Assets',
    href:   `/companies/${props.companyId}/financial-studies/${props.studyId}/fixed-assets/general`,
    status: props.statuses['fixed_assets_general'] ?? null,
  },
  {
    key:    'fixed_assets_per_employee',
    label:  'Fixed Assets Per Employee',
    href:   `/companies/${props.companyId}/financial-studies/${props.studyId}/fixed-assets/per-employee`,
    status: props.statuses['fixed_assets_per_employee'] ?? null,
  },
  ...(props.streams.includes('micro_finance') ? [{
    key:    'fixed_assets_new_branches',
    label:  'New Branches Fixed Assets',
    href:   `/companies/${props.companyId}/financial-studies/${props.studyId}/fixed-assets/new-branches`,
    status: props.statuses['fixed_assets_new_branches'] ?? null,
  }] : []),
])




</script>

<style scoped>
/* Opening Balance raw <a> link */
.nav-tab-secondary {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #6B96B8;
  text-decoration: none;
  border: 1px solid transparent;
  transition: color 0.15s, border-color 0.15s, background 0.15s;
  white-space: nowrap;
  flex-shrink: 0;
}
.nav-tab-secondary:hover {
  color: #94B8D0;
  border-color: #1B3558;
  background: #0C1829;
}
.nav-tab-secondary--active {
  color: #48C4D8;
  border-color: #1490A8;
  background: #0C3D6B;
}
.nav-divider {
  color: #1B3558;
  font-size: 16px;
  line-height: 1;
  padding: 0 2px;
  flex-shrink: 0;
  user-select: none;
}
.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
  display: inline-block;
}
.status-dot--blue  { background: #48C4D8; }
.status-dot--green { background: #22c55e; }
.status-dot--amber { background: #f59e0b; }
</style>