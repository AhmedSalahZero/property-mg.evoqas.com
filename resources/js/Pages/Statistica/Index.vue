<template>
  <Head title="Statistica" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-[#0C1829] text-white">

      <!-- PAGE HEADER -->
      <div class="bg-[#0C1829] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3">
                
              </div>
              <h1 class="text-2xl font-bold text-white mt-1 flex items-center gap-2">
                <span class="text-2xl">📊</span> Statistica
              </h1>
              <p class="text-[#5fd38f] text-sm mt-0.5">{{ company.name }} · Macro market data tracker</p>
            </div>
            <div class="flex items-center gap-2">
              <Link :href="`/companies/${props.company.id}/statistica/compare`"
                class="flex items-center gap-2 bg-[#112240] hover:bg-gray-700 border border-gray-700 text-gray-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m6-6v6m0 0V3m0 14h2a2 2 0 002-2v-4a2 2 0 00-2-2h-2" />
                </svg>
                Compare Series
              </Link>
              <button v-if="canEdit" @click="openCreate"
                class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Series
              </button>
              <!-- Read-only badge for non-admins -->
              <span v-if="!canEdit" class="flex items-center gap-1.5 bg-[#112240] border border-gray-700 text-gray-400 text-xs font-medium px-3 py-2 rounded-lg">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View Only
              </span>
            </div>
          </div>

          <!-- CATEGORY FILTER TABS -->
          <div class="flex gap-1 mt-6 flex-wrap">
            <button v-for="cat in categories" :key="cat.key"
              @click="activeCategory = cat.key"
              :class="[
                'px-3 py-1.5 text-xs font-medium rounded-lg transition-colors border',
                activeCategory === cat.key
                  ? 'bg-blue-600 border-blue-500 text-white'
                  : 'bg-[#112240] border-gray-700 text-white hover:text-white hover:bg-gray-700'
              ]">
              {{ cat.icon }} {{ cat.label }}
              <span class="ml-1 opacity-70">({{ countByCategory(cat.key) }})</span>
            </button>
          </div>
        </div>
      </div>

      <!-- MAIN CONTENT -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="mb-6 bg-green-900/50 border border-green-700 text-green-300 px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- EMPTY STATE -->
        <div v-if="filteredSeries.length === 0" class="bg-[#112240] rounded-xl border border-dashed border-gray-700 p-16 text-center">
          <div class="text-5xl mb-4">📈</div>
          <p class="text-gray-300 font-semibold text-lg mb-2">No data series yet</p>
          <p class="text-gray-500 text-sm mb-6">Create your first series to start tracking FX rates, commodity prices, or any market metric.</p>
          <button v-if="canEdit" @click="openCreate" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            + Create First Series
          </button>
        </div>

        <!-- SERIES GRID -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          <template v-for="s in filteredSeries" :key="s.id">
            <div class="bg-[#112240] rounded-xl border border-[#112240] overflow-hidden hover:border-gray-700 transition-colors group">

              <!-- Card top accent -->
              <div class="h-1 w-full" :style="{ background: s.color }"></div>

              <div class="p-5">
                <!-- Header row -->
                <div class="flex items-start justify-between gap-3 mb-3">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-xs font-semibold uppercase tracking-widest px-2 py-0.5 rounded-full"
                        :style="{ background: s.color + '22', color: s.color }">
                        {{ categoryLabel(s.category) }}
                      </span>
                      <span class="text-xs text-gray-500 bg-gray-800 px-2 py-0.5 rounded-full">{{ s.frequency }}</span>
                    </div>
                    <h3 class="text-white font-semibold mt-1.5 text-base leading-tight">{{ s.name }}</h3>
                    <p v-if="s.source" class="text-gray-500 text-xs mt-0.5">Source: {{ s.source }}</p>
                  </div>
                  <div v-if="canEdit" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button @click="openEdit(s)"
                      class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                      </svg>
                    </button>
                    <button @click="confirmDelete(s)"
                      class="w-7 h-7 flex items-center justify-center rounded-lg bg-[#112240] hover:bg-red-900 text-gray-400 hover:text-red-400 transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Value display -->
                <div class="flex items-end justify-between mb-4">
                  <div>
                    <div v-if="s.latest_value !== null" class="flex items-baseline gap-1.5">
                      <span class="text-3xl font-bold text-white tabular-nums">
                        {{ formatValue(s.latest_value) }}
                      </span>
                      <span class="text-sm text-gray-400">{{ s.unit }}</span>
                    </div>
                    <p v-else class="text-gray-500 text-sm italic">No data yet</p>
                    <p v-if="s.latest_date" class="text-gray-500 text-xs mt-0.5">
                      {{ formatDate(s.latest_date) }} ·
                      <span class="text-gray-600">{{ s.entry_count }} entries</span>
                    </p>
                  </div>
                  <!-- Change badge -->
                  <div v-if="s.change_pct !== null"
                    :class="[
                      'flex items-center gap-1 text-sm font-semibold px-2 py-1 rounded-lg',
                      s.change_pct > 0 ? 'bg-green-900/40 text-green-400' : s.change_pct < 0 ? 'bg-red-900/40 text-red-400' : 'bg-gray-800 text-gray-400'
                    ]">
                    <span>{{ s.change_pct > 0 ? '▲' : s.change_pct < 0 ? '▼' : '–' }}</span>
                    <span>{{ Math.abs(s.change_pct) }}%</span>
                  </div>
                </div>

                <!-- Mini sparkline SVG -->
                <div v-if="s.sparkline && s.sparkline.length > 1" class="h-12 mb-4">
                  <svg width="100%" height="100%" viewBox="0 0 200 48" preserveAspectRatio="none">
                    <defs>
                      <linearGradient :id="`grad-${s.id}`" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" :stop-color="s.color" stop-opacity="0.3"/>
                        <stop offset="100%" :stop-color="s.color" stop-opacity="0"/>
                      </linearGradient>
                    </defs>
                    <path :d="buildSparklineFill(s.sparkline, 200, 48)" :fill="`url(#grad-${s.id})`"/>
                    <path :d="buildSparkline(s.sparkline, 200, 48)" :stroke="s.color"
                      stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
                <div v-else class="h-12 mb-4 flex items-center">
                  <div class="w-full h-px bg-gray-800"></div>
                </div>

                <!-- Action button -->
                <Link :href="`/companies/${props.company.id}/statistica/${s.id}`"
                  class="w-full flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white text-sm font-medium py-2 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                  View Detail & Forecast
                </Link>
              </div>
            </div>
          </template>
        </div>

      </div>
    </div>

    <!-- ── CREATE / EDIT SERIES MODAL ── -->
    <Teleport to="body">
      <div v-if="modal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-[#0C1829] border border-gray-700 rounded-2xl w-full max-w-xl shadow-2xl flex flex-col max-h-[80vh]">
          <div class="px-6 py-5 border-b border-gray-800 flex items-center justify-between flex-shrink-0">
            <h2 class="text-white font-bold text-lg">{{ modal.editing ? 'Edit Series' : 'New Data Series' }}</h2>
            <button @click="modal.show = false" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-6 space-y-4 overflow-y-auto flex-1">
            <!-- Category quick-pick -->
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-2">Category</label>
              <div class="grid grid-cols-2 gap-2">
                <button v-for="cat in categories.filter(c => c.key !== 'all')" :key="cat.key"
                  type="button"
                  @click="form.category = cat.key"
                  :class="[
                    'flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition-colors text-left',
                    form.category === cat.key
                      ? 'bg-blue-600/20 border-blue-500 text-blue-300'
                      : 'bg-gray-800 border-gray-700 text-gray-400 hover:text-white hover:bg-gray-700'
                  ]">
                  <span>{{ cat.icon }}</span>
                  <span>{{ cat.label }}</span>
                </button>
              </div>
            </div>

            <!-- Quick presets (based on category) -->
            <div v-if="presets[form.category] && presets[form.category].length > 0">
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-2">Quick Presets</label>
              <div class="flex flex-wrap gap-1.5">
                <button v-for="p in presets[form.category]" :key="p.name"
                  type="button"
                  @click="applyPreset(p)"
                  class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 hover:text-white text-xs rounded-lg transition-colors">
                  {{ p.name }}
                </button>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Series Name *</label>
                <input v-model="form.name" type="text" placeholder="e.g. USD / EGP Daily Rate"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:border-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Unit</label>
                <input v-model="form.unit" type="text" placeholder="e.g. EGP, USD/bbl, %"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:border-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Frequency *</label>
                <select v-model="form.frequency"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500">
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Source</label>
                <input v-model="form.source" type="text" placeholder="e.g. CBE, Bloomberg"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:border-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Chart Color</label>
                <div class="flex items-center gap-2">
                  <input v-model="form.color" type="color"
                    class="w-10 h-10 bg-gray-800 border border-gray-700 rounded-lg cursor-pointer p-1" />
                  <div class="flex gap-1.5 flex-wrap">
                    <button v-for="c in colorPalette" :key="c" type="button"
                      @click="form.color = c"
                      :style="{ background: c }"
                      class="w-6 h-6 rounded-full border-2 transition-all"
                      :class="form.color === c ? 'border-white scale-110' : 'border-transparent'">
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Optional notes about this series..."
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:border-blue-500 resize-none"></textarea>
            </div>
          </div>

          <div class="px-6 pb-6 pt-4 border-t border-gray-800 flex gap-3 justify-end flex-shrink-0">
            <button @click="modal.show = false"
              class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-lg transition-colors">
              Cancel
            </button>
            <button @click="saveForm"
              :disabled="!form.name || !form.category"
              class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors">
              {{ modal.editing ? 'Save Changes' : 'Create Series' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── DELETE CONFIRM MODAL ── -->
    <Teleport to="body">
      <div v-if="deleteModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-[#112240] border border-red-800/60 rounded-2xl w-full max-w-md shadow-2xl p-6">
          <h2 class="text-white font-bold text-lg mb-2">Delete Series</h2>
          <p class="text-gray-400 text-sm mb-1">This will permanently delete <span class="text-white font-medium">{{ deleteModal.series?.name }}</span> and all its historical entries.</p>
          <p class="text-red-400 text-sm font-medium mb-5">This action cannot be undone.</p>
          <div class="flex gap-3 justify-end">
            <button @click="deleteModal.show = false"
              class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-lg transition-colors">
              Cancel
            </button>
            <button @click="executeDelete"
              class="px-5 py-2 bg-red-700 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition-colors">
              Delete Series
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: Object,
  series:  Array,
})

// Only admin and super-admin can create / edit / delete series and entries
const page    = usePage()
const canEdit = computed(() => {
  const user = page.props.auth?.user
  return user?.is_super_admin || ['company_admin', 'manager'].includes(user?.role)
})

const activeCategory = ref('all')

const categories = [
  { key: 'all',           label: 'All',             icon: '📊' },
  { key: 'fx_rates',      label: 'FX Rates',        icon: '💱' },
  { key: 'oil_energy',    label: 'Oil & Energy',     icon: '🛢️' },
  { key: 'commodities',   label: 'Commodities',      icon: '🌾' },
  { key: 'interest_rates',label: 'Interest Rates',   icon: '🏦' },
  { key: 'custom',        label: 'Custom',           icon: '⚙️' },
]

const presets = {
  fx_rates: [
    { name: 'USD / EGP', unit: 'EGP', frequency: 'daily', source: 'CBE' },
    { name: 'EUR / EGP', unit: 'EGP', frequency: 'daily', source: 'CBE' },
    { name: 'GBP / EGP', unit: 'EGP', frequency: 'daily', source: 'CBE' },
    { name: 'USD / EUR', unit: 'EUR', frequency: 'daily', source: 'ECB' },
    { name: 'SAR / EGP', unit: 'EGP', frequency: 'daily', source: 'CBE' },
  ],
  oil_energy: [
    { name: 'Brent Crude Oil', unit: 'USD/bbl', frequency: 'daily', source: 'ICE' },
    { name: 'WTI Crude Oil',   unit: 'USD/bbl', frequency: 'daily', source: 'NYMEX' },
    { name: 'Natural Gas',     unit: 'USD/MMBtu', frequency: 'daily', source: 'NYMEX' },
    { name: 'Gasoline Egypt',  unit: 'EGP/L',   frequency: 'monthly', source: 'EGPC' },
  ],
  commodities: [
    { name: 'Steel (HRC)',   unit: 'USD/ton', frequency: 'weekly', source: 'LME' },
    { name: 'Wheat',         unit: 'USD/bu',  frequency: 'weekly', source: 'CBOT' },
    { name: 'Cotton',        unit: 'USD/lb',  frequency: 'weekly', source: 'ICE' },
    { name: 'Copper',        unit: 'USD/ton', frequency: 'daily',  source: 'LME' },
    { name: 'Aluminum',      unit: 'USD/ton', frequency: 'daily',  source: 'LME' },
    { name: 'Gold',          unit: 'USD/oz',  frequency: 'daily',  source: 'COMEX' },
  ],
  interest_rates: [
    { name: 'CBE Overnight Deposit',  unit: '%', frequency: 'monthly', source: 'CBE' },
    { name: 'CBE Overnight Lending',  unit: '%', frequency: 'monthly', source: 'CBE' },
    { name: 'Egypt T-Bill 91D',       unit: '%', frequency: 'weekly',  source: 'CBE' },
    { name: 'SOFR',                   unit: '%', frequency: 'daily',   source: 'Fed' },
    { name: 'EURIBOR 3M',             unit: '%', frequency: 'daily',   source: 'ECB' },
  ],
  custom: [],
}

const colorPalette = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#ec4899','#84cc16','#14b8a6']

const filteredSeries = computed(() =>
  activeCategory.value === 'all'
    ? props.series
    : props.series.filter(s => s.category === activeCategory.value)
)

const countByCategory = (key) =>
  key === 'all' ? props.series.length : props.series.filter(s => s.category === key).length

const categoryLabel = (key) => categories.find(c => c.key === key)?.label ?? key

// ── Modal state ──────────────────────────────────────────────────────────────
const modal = reactive({ show: false, editing: null })
const form  = reactive({
  name: '', category: 'fx_rates', unit: '', frequency: 'daily',
  color: '#3b82f6', description: '', source: '',
})

const openCreate = () => {
  Object.assign(form, { name: '', category: 'fx_rates', unit: '', frequency: 'daily', color: '#3b82f6', description: '', source: '' })
  modal.editing = null
  modal.show    = true
}

const openEdit = (s) => {
  Object.assign(form, {
    name: s.name, category: s.category, unit: s.unit,
    frequency: s.frequency, color: s.color,
    description: s.description ?? '', source: s.source ?? '',
  })
  modal.editing = s.id
  modal.show    = true
}

const applyPreset = (p) => {
  form.name      = p.name
  form.unit      = p.unit
  form.frequency = p.frequency
  form.source    = p.source
}

const saveForm = () => {
  if (modal.editing) {
    router.put(`/companies/${props.company.id}/statistica/${modal.editing}`, { ...form }, {
      onSuccess: () => { modal.show = false }
    })
  } else {
    router.post(`/companies/${props.company.id}/statistica`, { ...form }, {
      onSuccess: () => { modal.show = false }
    })
  }
}

// ── Delete ───────────────────────────────────────────────────────────────────
const deleteModal = reactive({ show: false, series: null })

const confirmDelete = (s) => {
  deleteModal.series = s
  deleteModal.show   = true
}

const executeDelete = () => {
  router.delete(`/companies/${props.company.id}/statistica/${deleteModal.series.id}`, {
    onSuccess: () => { deleteModal.show = false }
  })
}

// ── Sparkline builder ────────────────────────────────────────────────────────
const buildSparkline = (points, w, h) => {
  if (!points || points.length < 2) return ''
  const vals   = points.map(p => p.value)
  const minVal = Math.min(...vals)
  const maxVal = Math.max(...vals)
  const range  = maxVal - minVal || 1
  const pad    = 4
  const pts    = points.map((p, i) => {
    const x = (i / (points.length - 1)) * (w - pad * 2) + pad
    const y = h - pad - ((p.value - minVal) / range) * (h - pad * 2)
    return `${x},${y}`
  })
  return 'M' + pts.join(' L')
}

const buildSparklineFill = (points, w, h) => {
  const line = buildSparkline(points, w, h)
  if (!line) return ''
  const pad = 4
  return line + ` L${w - pad},${h - pad} L${pad},${h - pad} Z`
}

const formatValue = (v) => {
  if (v === null || v === undefined) return '—'
  if (Math.abs(v) >= 1000) return v.toLocaleString('en-US', { maximumFractionDigits: 2 })
  return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}

const formatDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>