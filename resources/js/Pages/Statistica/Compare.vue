<template>
  <Head title="Compare Series — Statistica" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-gray-950 text-white">

      <!-- HEADER -->
      <div class="bg-gray-900 border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link :href="`/companies/${props.company.id}/statistica`" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">
                ← Statistica
              </Link>
              <h1 class="text-2xl font-bold text-white mt-1">📊 Compare Series</h1>
              <p class="text-gray-400 text-sm mt-0.5">Overlay multiple data series on the same chart</p>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- SERIES SELECTOR -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5 mb-6">
          <h2 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-blue-400">Select Series to Compare</h2>
          <div class="flex flex-wrap gap-2">
            <button v-for="s in allSeries" :key="s.id"
              @click="toggleSeries(s.id)"
              :class="[
                'flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-all',
                isSelected(s.id)
                  ? 'border-opacity-100 text-white scale-105'
                  : 'bg-gray-800 border-gray-700 text-gray-400 hover:text-white hover:bg-gray-700'
              ]"
              :style="isSelected(s.id) ? { borderColor: s.color, background: s.color + '22', color: s.color } : {}">
              <div v-if="isSelected(s.id)" class="w-2.5 h-2.5 rounded-full" :style="{ background: s.color }"></div>
              {{ s.name }}
              <span class="text-xs opacity-60">{{ s.unit }}</span>
            </button>
          </div>
          <p v-if="selectedSeriesIds.length === 0" class="text-gray-500 text-sm mt-3 italic">Select 2 or more series to compare them on the chart.</p>
          <p v-if="selectedSeriesIds.length >= 2 && hasDualAxis" class="text-amber-400 text-xs mt-3">
            ⚠️ Series have different units — dual Y-axis is shown. The left axis corresponds to the first selected series.
          </p>
        </div>

        <!-- CHART -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5 mb-6" v-if="selectedSeriesIds.length >= 1">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-white font-semibold">Overlay Chart</h2>
            <div class="flex gap-1">
              <button v-for="r in ranges" :key="r.key"
                @click="selectedRange = r.key"
                :class="['px-2.5 py-1 text-xs font-medium rounded-lg transition-colors',
                  selectedRange === r.key ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white hover:bg-gray-700']">
                {{ r.label }}
              </button>
            </div>
          </div>

          <div style="height: 320px;" class="relative">
            <svg ref="compareSvgRef" width="100%" height="100%"
              :viewBox="`0 0 ${chartW} ${chartH}`" preserveAspectRatio="none"
              class="overflow-visible"
              @mousemove="onCompareMouseMove"
              @mouseleave="onCompareMouseLeave">

              <!-- Y gridlines -->
              <template v-for="(tick, i) in primaryYTicks" :key="i">
                <line :x1="chartPad.l" :x2="chartW - chartPad.r" :y1="tick.y" :y2="tick.y"
                  stroke="#374151" stroke-width="0.5" stroke-dasharray="4,4"/>
                <text :x="chartPad.l - 6" :y="tick.y + 4" fill="#6b7280" font-size="9"
                  text-anchor="end" font-family="monospace">{{ formatValue(tick.value) }}</text>
              </template>

              <!-- Each series line -->
              <template v-for="sd in chartSeriesData" :key="sd.id">
                <defs>
                  <linearGradient :id="`cg-${sd.id}`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="sd.color" stop-opacity="0.15"/>
                    <stop offset="100%" :stop-color="sd.color" stop-opacity="0"/>
                  </linearGradient>
                </defs>
                <path :d="sd.areaPath" :fill="`url(#cg-${sd.id})`"/>
                <path :d="sd.linePath" :stroke="sd.color" stroke-width="1.5"
                  fill="none" stroke-linecap="round" stroke-linejoin="round"/>
              </template>

              <!-- X labels -->
              <template v-for="(tick, i) in xTicks" :key="i">
                <text :x="tick.x" :y="chartH - 4" fill="#6b7280" font-size="9"
                  text-anchor="middle">{{ tick.label }}</text>
              </template>

              <!-- ── TOOLTIP CROSSHAIR ── -->
              <template v-if="compareTooltip.visible">
                <!-- Vertical line -->
                <line
                  :x1="compareTooltip.x" :x2="compareTooltip.x"
                  :y1="chartPad.t" :y2="chartH - chartPad.b"
                  stroke="#ffffff" stroke-width="0.5" stroke-dasharray="3,3" opacity="0.4"/>
                <!-- One dot per series at this date -->
                <template v-for="pt in compareTooltip.points" :key="pt.id">
                  <circle v-if="pt.y !== null"
                    :cx="compareTooltip.x" :cy="pt.y" r="4"
                    :fill="pt.color" stroke="#1f2937" stroke-width="1.5"/>
                  <circle v-if="pt.y !== null"
                    :cx="compareTooltip.x" :cy="pt.y" r="7"
                    :fill="pt.color" opacity="0.15"/>
                </template>
              </template>

              <!-- Transparent overlay -->
              <rect
                :x="chartPad.l" :y="chartPad.t"
                :width="chartW - chartPad.l - chartPad.r"
                :height="chartH - chartPad.t - chartPad.b"
                fill="transparent" style="cursor: crosshair;"/>
            </svg>

            <!-- ── FLOATING TOOLTIP ── -->
            <Teleport to="body">
              <div v-if="compareTooltip.visible && compareSvgRef"
                class="fixed z-50 pointer-events-none"
                :style="compareTooltipStyle">
                <div class="bg-gray-900 border border-gray-600 rounded-xl shadow-2xl px-3.5 py-3 min-w-[160px]">
                  <p class="text-gray-400 text-xs font-mono mb-2 pb-2 border-b border-gray-800">
                    {{ new Date(compareTooltip.date + 'T00:00:00').toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) }}
                  </p>
                  <div v-for="pt in compareTooltip.points" :key="pt.id" class="flex items-center justify-between gap-4 mb-1 last:mb-0">
                    <div class="flex items-center gap-1.5">
                      <div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ background: pt.color }"></div>
                      <span class="text-gray-400 text-xs truncate max-w-[90px]">{{ pt.name }}</span>
                    </div>
                    <span class="text-white font-semibold text-xs tabular-nums">
                      {{ formatValue(pt.value) }}
                      <span class="text-gray-500 font-normal">{{ pt.unit }}</span>
                    </span>
                  </div>
                </div>
              </div>
            </Teleport>
          </div>

          <!-- Legend -->
          <div class="flex flex-wrap gap-x-6 gap-y-2 mt-4 pt-4 border-t border-gray-800">
            <div v-for="sd in chartSeriesData" :key="sd.id" class="flex items-center gap-2">
              <div class="w-6 h-0.5 rounded" :style="{ background: sd.color }"></div>
              <span class="text-xs text-gray-400">{{ sd.name }}</span>
              <span class="text-xs text-gray-600">({{ sd.unit }})</span>
            </div>
          </div>
        </div>

        <!-- STATS TABLE -->
        <div v-if="selectedSeriesIds.length >= 2" class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-800">
            <h2 class="text-white font-semibold">Summary Statistics</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-800">
                  <th class="text-left text-xs font-semibold text-blue-400 uppercase tracking-wider px-5 py-3">Series</th>
                  <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-4 py-3">Latest</th>
                  <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-4 py-3">Min</th>
                  <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-4 py-3">Max</th>
                  <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-4 py-3">Avg</th>
                  <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-4 py-3">Total Change</th>
                  <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-4 py-3">Points</th>
                  <th class="text-center text-xs font-semibold text-blue-400 uppercase tracking-wider px-4 py-3">Detail</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="stat in seriesStats" :key="stat.id" class="hover:bg-gray-800/30 transition-colors">
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: stat.color }"></div>
                      <span class="text-white font-medium text-sm">{{ stat.name }}</span>
                      <span class="text-gray-500 text-xs">{{ stat.unit }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-right font-semibold text-white tabular-nums">{{ formatValue(stat.latest) }}</td>
                  <td class="px-4 py-3 text-right text-gray-400 tabular-nums text-xs">{{ formatValue(stat.min) }}</td>
                  <td class="px-4 py-3 text-right text-gray-400 tabular-nums text-xs">{{ formatValue(stat.max) }}</td>
                  <td class="px-4 py-3 text-right text-gray-400 tabular-nums text-xs">{{ formatValue(stat.avg) }}</td>
                  <td class="px-4 py-3 text-right tabular-nums text-xs"
                    :class="stat.totalChangePct >= 0 ? 'text-green-400' : 'text-red-400'">
                    {{ stat.totalChangePct !== null ? (stat.totalChangePct >= 0 ? '+' : '') + stat.totalChangePct.toFixed(2) + '%' : '—' }}
                  </td>
                  <td class="px-4 py-3 text-right text-gray-400 text-xs">{{ stat.count }}</td>
                  <td class="px-4 py-3 text-center">
                    <Link :href="`/companies/${props.company.id}/statistica/${stat.id}`"
                      class="text-xs text-blue-400 hover:text-blue-300 transition-colors">
                      View →
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="allSeries.length === 0" class="bg-gray-900 rounded-xl border border-dashed border-gray-700 p-16 text-center">
          <p class="text-gray-500 text-sm">No series available. <Link :href="`/companies/${props.company.id}/statistica`" class="text-blue-400 hover:text-blue-300">Create some series first.</Link></p>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:    Object,
  allSeries:  Array,
  seriesData: Array,
  selected:   Array,
})

const selectedSeriesIds = ref([...(props.selected ?? [])])

const chartW   = 800
const chartH   = 240
const chartPad = { l: 65, r: 20, t: 10, b: 20 }

// ── Tooltip state ─────────────────────────────────────────────────────────────
const compareTooltip  = reactive({ visible: false, x: 0, y: 0, date: '', points: [] })
const compareSvgRef   = ref(null)

const onCompareMouseMove = (e) => {
  if (!compareSvgRef.value || !activeSeriesData.value.length) return

  const svg    = compareSvgRef.value
  const rect   = svg.getBoundingClientRect()
  const mouseX = ((e.clientX - rect.left) / rect.width) * chartW

  // Collect all sorted dates
  const allDates = [...new Set(activeSeriesData.value.flatMap(sd =>
    Object.keys(filterByRange(sd.entries))
  ))].sort()
  if (allDates.length < 2) return

  const toX = (i) => chartPad.l + (i / (allDates.length - 1)) * (chartW - chartPad.l - chartPad.r)

  // Find nearest date index
  let nearest = 0
  let minDist = Infinity
  for (let i = 0; i < allDates.length; i++) {
    const d = Math.abs(toX(i) - mouseX)
    if (d < minDist) { minDist = d; nearest = i }
  }

  const hovDate = allDates[nearest]

  // Get Y scale
  const allVals = activeSeriesData.value.flatMap(sd =>
    Object.values(filterByRange(sd.entries)).map(Number)
  )
  const minVal = Math.min(...allVals)
  const maxVal = Math.max(...allVals)
  const range  = maxVal - minVal || 1
  const toY    = (v) => chartPad.t + ((maxVal - v) / range) * (chartH - chartPad.t - chartPad.b)

  // Collect each series' value at this date
  const points = activeSeriesData.value.map(sd => {
    const meta  = props.allSeries.find(a => a.id === sd.id)
    const filt  = filterByRange(sd.entries)
    const val   = filt[hovDate] !== undefined ? Number(filt[hovDate]) : null
    return {
      id:    sd.id,
      name:  meta?.name ?? 'Series',
      unit:  meta?.unit ?? '',
      color: meta?.color ?? '#3b82f6',
      value: val,
      y:     val !== null ? toY(val) : null,
    }
  }).filter(p => p.value !== null)

  // Use first available point's Y for crosshair dot
  const firstPt = points[0]

  compareTooltip.visible = true
  compareTooltip.x       = toX(nearest)
  compareTooltip.y       = firstPt?.y ?? chartH / 2
  compareTooltip.date    = hovDate
  compareTooltip.points  = points
}

const onCompareMouseLeave = () => { compareTooltip.visible = false }

const compareTooltipStyle = computed(() => {
  if (!compareTooltip.visible || !compareSvgRef.value) return {}
  const svg    = compareSvgRef.value
  const rect   = svg.getBoundingClientRect()
  const scaleX = rect.width / chartW
  const screenX = rect.left + compareTooltip.x * scaleX
  const screenY = rect.top  + compareTooltip.y * (rect.height / chartH)
  const offsetX = screenX > window.innerWidth - 220 ? -210 : 16
  return {
    left: `${screenX + offsetX}px`,
    top:  `${Math.max(screenY - 40, rect.top + 8)}px`,
  }
})

const ranges = [
  { key: '1m', label: '1M' }, { key: '3m', label: '3M' },
  { key: '6m', label: '6M' }, { key: '1y', label: '1Y' }, { key: 'all', label: 'All' },
]
const selectedRange = ref('all')

const isSelected = (id) => selectedSeriesIds.value.includes(id)

const toggleSeries = (id) => {
  if (isSelected(id)) {
    selectedSeriesIds.value = selectedSeriesIds.value.filter(i => i !== id)
  } else {
    selectedSeriesIds.value = [...selectedSeriesIds.value, id]
  }
  // Reload page with new selection
  router.get(`/companies/${props.company.id}/statistica/compare`, {
    series: selectedSeriesIds.value.join(',')
  }, { preserveState: true, replace: true })
}

const activeSeriesData = computed(() =>
  props.seriesData.filter(sd => selectedSeriesIds.value.includes(sd.id))
)

const hasDualAxis = computed(() => {
  const units = new Set(activeSeriesData.value.map(sd => {
    const s = props.allSeries.find(a => a.id === sd.id)
    return s?.unit ?? ''
  }))
  return units.size > 1
})

// Filter entries by range
const filterByRange = (entries) => {
  if (selectedRange.value === 'all') return entries
  const days = { '1m': 30, '3m': 90, '6m': 180, '1y': 365 }[selectedRange.value] || 9999
  const cutoff = new Date(Date.now() - days * 86400000)
  const result = {}
  for (const [date, val] of Object.entries(entries)) {
    if (new Date(date) >= cutoff) result[date] = val
  }
  return result
}

const chartSeriesData = computed(() => {
  const allEntries = activeSeriesData.value.map(sd => {
    const filtered = filterByRange(sd.entries)
    return { ...sd, filteredEntries: filtered }
  })

  if (!allEntries.length) return []

  // Collect all dates across all series
  const allDates = [...new Set(allEntries.flatMap(sd => Object.keys(sd.filteredEntries)))].sort()
  if (allDates.length < 2) return []

  // Use first series for Y scale (primary axis)
  const primaryVals = allEntries.flatMap(sd => Object.values(sd.filteredEntries).map(Number))
  const minVal = Math.min(...primaryVals)
  const maxVal = Math.max(...primaryVals)
  const range  = maxVal - minVal || 1

  const toX = (i) => chartPad.l + (i / (allDates.length - 1)) * (chartW - chartPad.l - chartPad.r)
  const toY = (v) => chartPad.t + ((maxVal - v) / range) * (chartH - chartPad.t - chartPad.b)

  return allEntries.map(sd => {
    const meta = props.allSeries.find(a => a.id === sd.id)
    const pts  = allDates
      .filter(d => sd.filteredEntries[d] !== undefined)
      .map(d => ({ x: toX(allDates.indexOf(d)), y: toY(Number(sd.filteredEntries[d])) }))

    const linePath = pts.length >= 2 ? 'M' + pts.map(p => `${p.x},${p.y}`).join(' L') : ''
    const areaPath = pts.length >= 2
      ? linePath + ` L${pts[pts.length-1].x},${chartH - chartPad.b} L${pts[0].x},${chartH - chartPad.b} Z`
      : ''

    return {
      id: sd.id,
      name: meta?.name ?? 'Series',
      unit: meta?.unit ?? '',
      color: meta?.color ?? '#3b82f6',
      linePath,
      areaPath,
    }
  })
})

const primaryYTicks = computed(() => {
  const allVals = activeSeriesData.value.flatMap(sd =>
    Object.values(filterByRange(sd.entries)).map(Number)
  )
  if (!allVals.length) return []
  const minVal = Math.min(...allVals)
  const maxVal = Math.max(...allVals)
  const range  = maxVal - minVal || 1
  const toY = (v) => chartPad.t + ((maxVal - v) / range) * (chartH - chartPad.t - chartPad.b)
  return Array.from({ length: 5 }, (_, i) => {
    const v = minVal + (i / 4) * range
    return { value: v, y: toY(v) }
  })
})

const xTicks = computed(() => {
  const allDates = [...new Set(activeSeriesData.value.flatMap(sd =>
    Object.keys(filterByRange(sd.entries))
  ))].sort()
  if (!allDates.length) return []
  const step = Math.max(1, Math.floor(allDates.length / 6))
  const toX = (i) => chartPad.l + (i / (allDates.length - 1)) * (chartW - chartPad.l - chartPad.r)
  return allDates.filter((_, i) => i % step === 0).map(d => ({
    x: toX(allDates.indexOf(d)),
    label: new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
  }))
})

const seriesStats = computed(() =>
  activeSeriesData.value.map(sd => {
    const meta = props.allSeries.find(a => a.id === sd.id)
    const vals = Object.values(sd.entries).map(Number)
    if (!vals.length) return { id: sd.id, name: meta?.name, unit: meta?.unit, color: meta?.color, latest: null, min: null, max: null, avg: null, totalChangePct: null, count: 0 }
    const latest = vals[vals.length - 1]
    const first  = vals[0]
    const totalChangePct = first !== 0 ? Math.round((latest - first) / Math.abs(first) * 10000) / 100 : null
    return {
      id: sd.id,
      name: meta?.name ?? 'Series',
      unit: meta?.unit ?? '',
      color: meta?.color ?? '#3b82f6',
      latest,
      min: Math.min(...vals),
      max: Math.max(...vals),
      avg: vals.reduce((a, b) => a + b, 0) / vals.length,
      totalChangePct,
      count: vals.length,
    }
  })
)

const formatValue = (v) => {
  if (v === null || v === undefined) return '—'
  if (Math.abs(v) >= 1000) return Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 })
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}
</script>