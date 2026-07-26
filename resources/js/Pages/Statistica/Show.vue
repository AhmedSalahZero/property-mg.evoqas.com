<template>
  <Head :title="`${series.name} — Statistica`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-[#0C1829] text-white">

      <!-- PAGE HEADER -->
      <div class="bg-[#0C1829] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
              <Link :href="`/companies/${props.company.id}/statistica`" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">
                ← Statistica
              </Link>
              <div class="flex items-center gap-3 mt-1">
                <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ background: series.color }"></div>
                <h1 class="text-2xl font-bold text-white truncate">{{ series.name }}</h1>
                <span class="text-xs font-semibold uppercase tracking-widest px-2 py-0.5 rounded-full flex-shrink-0"
                  :style="{ background: series.color + '22', color: series.color }">
                  {{ series.category.replace('_', ' ') }}
                </span>
              </div>
              <p class="text-gray-400 text-sm mt-0.5 flex items-center gap-3">
                <span>{{ series.frequency }}</span>
                <span v-if="series.unit">· Unit: {{ series.unit }}</span>
                <span v-if="series.source">· Source: {{ series.source }}</span>
              </p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <template v-if="canEdit">
                <button @click="importModal.show = true"
                  class="flex items-center gap-1.5 bg-[#112240] hover:bg-blue-700 border border-gray-700 text-gray-300 text-sm px-3 py-2 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                  </svg>
                  Import CSV / Excel
                </button>
                <a :href="`/companies/${props.company.id}/statistica/template`"
                  class="flex items-center gap-1.5 bg-[#112240] hover:bg-blue-700 border border-gray-700 text-gray-300 text-sm px-3 py-2 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  Template
                </a>
                <button @click="openEntryModal(null)"
                  class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Add Entry
                </button>
              </template>
              <!-- Read-only badge -->
              <span v-if="!canEdit" class="flex items-center gap-1.5 bg-[#112240] border border-gray-700 text-gray-400 text-xs font-medium px-3 py-2 rounded-lg">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View Only
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="mb-6 bg-green-900/50 border border-green-700 text-green-300 px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- KPI CARDS ROW -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-[#112240] border border-[#21518B] rounded-xl p-4">
            <p class="text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1">Latest Value</p>
            <p class="text-2xl font-bold text-white tabular-nums">
              {{ latestEntry ? formatValue(latestEntry.value) : '—' }}
              <span class="text-sm font-normal text-gray-400 ml-1">{{ series.unit }}</span>
            </p>
            <p class="text-gray-500 text-xs mt-0.5">{{ latestEntry ? formatDate(latestEntry.entry_date) : 'No data' }}</p>
          </div>
          <div class="bg-[#112240] border border-[#21518B] rounded-xl p-4">
            <p class="text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1">Period Change</p>
            <p class="text-2xl font-bold tabular-nums" :class="popChange >= 0 ? 'text-green-400' : 'text-red-400'">
              {{ popChange !== null ? (popChange >= 0 ? '+' : '') + popChange.toFixed(4) : '—' }}
            </p>
            <p class="text-gray-500 text-xs mt-0.5">{{ popChangePct !== null ? (popChangePct >= 0 ? '+' : '') + popChangePct.toFixed(2) + '%' : '' }}</p>
          </div>
          <div class="bg-[#112240] border border-[#21518B] rounded-xl p-4">
            <p class="text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1">Data Points</p>
            <p class="text-2xl font-bold text-white">{{ entries.length }}</p>
            <p class="text-gray-500 text-xs mt-0.5">
              {{ entries.length > 0 ? formatDate(entries[0].entry_date) + ' → ' + formatDate(entries[entries.length-1].entry_date) : 'No range' }}
            </p>
          </div>
          <div class="bg-[#112240] border border-[#21518B] rounded-xl p-4">
            <p class="text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1">YoY Change</p>
            <p v-if="lastYoy" class="text-2xl font-bold tabular-nums" :class="lastYoy >= 0 ? 'text-green-400' : 'text-red-400'">
              {{ (lastYoy >= 0 ? '+' : '') + lastYoy.toFixed(1) }}%
            </p>
            <p v-else class="text-2xl font-bold text-gray-600">—</p>
            <p class="text-gray-500 text-xs mt-0.5">vs same period last year</p>
          </div>
        </div>

        <!-- CHART + FORECAST PANEL -->
        <div class="bg-[#112240] border border-[#21518B] rounded-xl p-5 mb-6">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-white font-semibold">Historical Chart</h2>
            <div class="flex flex-wrap items-center gap-3">

              <!-- Quick-range presets -->
              <div class="flex gap-1">
                <button v-for="r in quickRanges" :key="r.key"
                  @click="applyQuickRange(r.key)"
                  :class="[
                    'px-2.5 py-1 text-xs font-medium rounded-lg transition-colors',
                    activeQuickRange === r.key ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white hover:bg-gray-700'
                  ]">
                  {{ r.label }}
                </button>
              </div>

              <!-- Custom date range -->
              <div class="flex items-center gap-1.5">
                <input v-model="customStart" type="date"
                  class="bg-[#112240] border border-[#21518B] rounded-lg px-2 py-1 text-gray-200 text-xs focus:outline-none focus:border-blue-500" />
                <span class="text-gray-500 text-xs">→</span>
                <input v-model="customEnd" type="date"
                  class="bg-[#112240] border border-[#21518B] rounded-lg px-2 py-1 text-gray-200 text-xs focus:outline-none focus:border-blue-500" />
                <button @click="applyCustomRange"
                  class="px-2.5 py-1 text-xs font-medium rounded-lg bg-indigo-700 hover:bg-indigo-600 text-white transition-colors">
                  Apply
                </button>
              </div>

              <!-- Forecast toggle -->
              <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                <div @click="showForecast = !showForecast"
                  :class="['w-9 h-5 rounded-full transition-colors', showForecast ? 'bg-blue-600' : 'bg-gray-700']">
                  <div :class="['w-4 h-4 bg-white rounded-full m-0.5 transition-transform', showForecast ? 'translate-x-4' : '']"></div>
                </div>
                Show Forecast
              </label>
            </div>
          </div>

          <!-- SVG Chart -->
          <div class="relative" style="height: 280px;">
            <svg v-if="chartData.points.length > 0" ref="chartSvgRef" width="100%" height="100%"
              :viewBox="`0 0 ${chartW} ${chartH}`" preserveAspectRatio="none"
              class="overflow-visible"
              @mousemove="onChartMouseMove"
              @mouseleave="onChartMouseLeave">
              <defs>
                <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" :stop-color="series.color" stop-opacity="0.25"/>
                  <stop offset="100%" :stop-color="series.color" stop-opacity="0"/>
                </linearGradient>
                <linearGradient id="forecastGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#6366f1" stop-opacity="0.15"/>
                  <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                </linearGradient>
              </defs>

              <!-- Y gridlines -->
              <template v-for="(tick, i) in chartData.yTicks" :key="i">
                <line :x1="chartPad.l" :x2="chartW - chartPad.r" :y1="tick.y" :y2="tick.y"
                  stroke="#374151" stroke-width="0.5" stroke-dasharray="4,4"/>
                <text :x="chartPad.l - 6" :y="tick.y + 4" fill="#6b7280" font-size="9"
                  text-anchor="end" font-family="monospace">{{ formatValue(tick.value) }}</text>
              </template>

              <!-- Forecast confidence band -->
              <path v-if="showForecast && forecastBandPath" :d="forecastBandPath" fill="url(#forecastGrad)"/>

              <!-- Historical area fill -->
              <path :d="chartData.areaPath" fill="url(#chartGrad)"/>

              <!-- Historical line -->
              <path :d="chartData.linePath" :stroke="series.color" stroke-width="1.5"
                fill="none" stroke-linecap="round" stroke-linejoin="round"/>

              <!-- Forecast line -->
              <path v-if="showForecast && forecastLinePath" :d="forecastLinePath" stroke="#818cf8"
                stroke-width="1.5" fill="none" stroke-dasharray="5,4" stroke-linecap="round"/>

              <!-- Forecast upper/lower bands -->
              <path v-if="showForecast && forecastUpperPath" :d="forecastUpperPath" stroke="#818cf8"
                stroke-width="0.7" fill="none" stroke-dasharray="2,3" opacity="0.5"/>
              <path v-if="showForecast && forecastLowerPath" :d="forecastLowerPath" stroke="#818cf8"
                stroke-width="0.7" fill="none" stroke-dasharray="2,3" opacity="0.5"/>

              <!-- X axis labels -->
              <template v-for="(tick, i) in chartData.xTicks" :key="i">
                <text :x="tick.x" :y="chartH - 4" fill="#6b7280" font-size="9"
                  text-anchor="middle" font-family="sans-serif">{{ tick.label }}</text>
              </template>

              <!-- ── TOOLTIP CROSSHAIR ── -->
              <template v-if="tooltip.visible">
                <!-- Vertical line -->
                <line
                  :x1="tooltip.x" :x2="tooltip.x"
                  :y1="chartPad.t" :y2="chartH - chartPad.b"
                  stroke="#ffffff" stroke-width="0.5" stroke-dasharray="3,3" opacity="0.4"/>
                <!-- Dot on the line -->
                <circle
                  :cx="tooltip.x" :cy="tooltip.y" r="4"
                  :fill="tooltip.isForecast ? '#818cf8' : series.color"
                  stroke="#1f2937" stroke-width="1.5"/>
                <circle
                  :cx="tooltip.x" :cy="tooltip.y" r="7"
                  :fill="tooltip.isForecast ? '#818cf8' : series.color"
                  opacity="0.2"/>
              </template>

              <!-- Transparent overlay to capture mouse events uniformly -->
              <rect
                :x="chartPad.l" :y="chartPad.t"
                :width="chartW - chartPad.l - chartPad.r"
                :height="chartH - chartPad.t - chartPad.b"
                fill="transparent" style="cursor: crosshair;"/>
            </svg>

            <!-- ── FLOATING TOOLTIP BOX ── -->
            <Teleport to="body">
              <div v-if="tooltip.visible && chartSvgRef"
                class="fixed z-50 pointer-events-none"
                :style="tooltipStyle">
                <div class="bg-[#112240] border border-[#21518B] rounded-xl shadow-2xl px-3.5 py-2.5 min-w-[140px]">
                  <p class="text-gray-400 text-xs mb-1 font-mono">{{ formatDate(tooltip.date) }}</p>
                  <div class="flex items-baseline gap-1.5">
                    <span class="text-white font-bold text-base tabular-nums">{{ formatValue(tooltip.value) }}</span>
                    <span class="text-gray-200 text-xs">{{ series.unit }}</span>
                  </div>
                  <p v-if="tooltip.isForecast" class="text-indigo-400 text-xs mt-1 font-medium">✨ Forecast</p>
                  <div v-if="tooltip.isForecast && tooltip.upper !== null" class="text-gray-500 text-xs mt-0.5">
                    Range: {{ formatValue(tooltip.lower) }} – {{ formatValue(tooltip.upper) }}
                  </div>
                  <!-- small color indicator -->
                  <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-1.5 h-6 rounded-full"
                    :style="{ background: tooltip.isForecast ? '#818cf8' : series.color }"></div>
                </div>
              </div>
            </Teleport>

            <div v-if="chartData.points.length === 0" class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">
              Not enough data to display chart. Add at least 2 entries.
            </div>
          </div>

          <!-- Legend -->
          <div v-if="showForecast && forecast.length > 0" class="flex items-center gap-6 mt-3 pt-3 border-t border-gray-800">
            <div class="flex items-center gap-2">
              <div class="w-6 h-0.5 rounded" :style="{ background: series.color }"></div>
              <span class="text-xs text-gray-400">Historical</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-6 h-0.5 rounded border-t-2 border-dashed border-indigo-400"></div>
              <span class="text-xs text-gray-400">Forecast (Holt-Winters)</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-6 h-3 rounded opacity-30 bg-indigo-400"></div>
              <span class="text-xs text-gray-400">95% Confidence Band</span>
            </div>
            <span class="text-xs text-gray-600 ml-auto">
              Projecting {{ forecast.length }} {{ series.frequency === 'daily' ? 'days' : series.frequency === 'weekly' ? 'weeks' : series.frequency === 'monthly' ? 'months' : 'quarters' }} ahead
            </span>
          </div>
        </div>

        <!-- GROWTH RATES PANEL -->
        <div v-if="growth.pop && growth.pop.length > 1" class="bg-gray-900 border border-gray-800 rounded-xl p-5 mb-6">
          <h2 class="text-white font-semibold mb-4">Period-on-Period Growth Rate</h2>
          <div style="height: 120px;" class="relative">
            <svg width="100%" height="100%" :viewBox="`0 0 ${chartW} 120`" preserveAspectRatio="none">
              <!-- Zero line -->
              <line :x1="chartPad.l" :x2="chartW - chartPad.r" :y1="60" :y2="60"
                stroke="#374151" stroke-width="1"/>

              <!-- Growth bars -->
              <template v-for="(p, i) in growthBars" :key="i">
                <rect :x="p.x - p.w/2" :y="p.y" :width="p.w" :height="p.h"
                  :fill="p.positive ? '#10b981' : '#ef4444'" opacity="0.7" rx="1"/>
              </template>

              <!-- X labels -->
              <template v-for="(tick, i) in growthXTicks" :key="i">
                <text :x="tick.x" y="118" fill="#6b7280" font-size="8" text-anchor="middle">{{ tick.label }}</text>
              </template>
            </svg>
          </div>
        </div>

        <!-- DATA TABLE + FORECAST TABLE GRID -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <!-- ENTRIES TABLE -->
          <div class="xl:col-span-2 bg-[#112240] border border-[#21518B] rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between gap-3">
              <h2 class="text-white font-semibold">Historical Data</h2>
              <div class="flex items-center gap-3">
                <!-- Bulk delete toolbar — visible when rows are selected -->
                <transition enter-active-class="transition-all duration-150" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                  leave-active-class="transition-all duration-100" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                  <div v-if="selectedIds.size > 0 && canEdit" class="flex items-center gap-2">
                    <span class="text-xs text-blue-400 font-medium">{{ selectedIds.size }} selected</span>
                    <button @click="confirmBulkDelete"
                      class="flex items-center gap-1.5 bg-red-900/60 hover:bg-red-800 border border-red-700 text-red-300 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                      Delete Selected
                    </button>
                    <button @click="selectedIds.clear()"
                      class="text-xs text-gray-500 hover:text-gray-300 transition-colors px-1">
                      Clear
                    </button>
                  </div>
                </transition>
                <span class="text-xs text-gray-500">{{ entries.length }} entries · newest first</span>
              </div>
            </div>
            <div class="overflow-y-auto" style="max-height: 520px;">
              <table class="w-full text-sm">
                <thead class="sticky top-0 bg-[#0C1829]">
                  <tr class="border-b border-[#1490A8]">
                    <th v-if="canEdit" class="px-4 py-3 w-8">
                      <input type="checkbox" :checked="allSelected" :indeterminate="someSelected"
                        @change="toggleSelectAll"
                        class="w-3.5 h-3.5 rounded border-gray-600 bg-gray-800 accent-blue-500 cursor-pointer" />
                    </th>
                    <th class="text-left text-xs font-semibold text-blue-400 uppercase tracking-wider px-5 py-3">Date</th>
                    <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-5 py-3">Value</th>
                    <th class="text-right text-xs font-semibold text-blue-400 uppercase tracking-wider px-5 py-3">Change</th>
                    <th class="text-left text-xs font-semibold text-blue-400 uppercase tracking-wider px-5 py-3">Notes</th>
                    <th class="text-center text-xs font-semibold text-blue-400 uppercase tracking-wider px-5 py-3">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-if="entries.length === 0">
                    <td :colspan="canEdit ? 6 : 5" class="px-5 py-10 text-center text-gray-500 text-sm">
                      No entries yet. Click "Add Entry" to start recording data.
                    </td>
                  </tr>
                  <template v-for="(entry, i) in reversedEntries" :key="entry.id">
                    <tr class="hover:bg-gray-800/50 transition-colors" :class="{ 'bg-blue-950/30': selectedIds.has(entry.id) }">
                      <td v-if="canEdit" class="px-4 py-3">
                        <input type="checkbox" :checked="selectedIds.has(entry.id)"
                          @change="toggleSelect(entry.id)"
                          class="w-3.5 h-3.5 rounded border-gray-600 bg-gray-800 accent-blue-500 cursor-pointer" />
                      </td>
                      <td class="px-5 py-3 text-gray-300 font-mono text-xs">{{ formatDate(entry.entry_date) }}</td>
                      <td class="px-5 py-3 text-right font-semibold text-white tabular-nums">
                        {{ formatValue(entry.value) }}
                        <span class="text-gray-500 text-xs ml-0.5">{{ series.unit }}</span>
                      </td>
                      <td class="px-5 py-3 text-right tabular-nums">
                        <span v-if="entryChange(i)" :class="entryChange(i) > 0 ? 'text-green-400' : 'text-red-400'" class="text-xs font-medium">
                          {{ entryChange(i) > 0 ? '+' : '' }}{{ entryChange(i).toFixed(4) }}
                        </span>
                        <span v-else class="text-gray-600 text-xs">—</span>
                      </td>
                      <td class="px-5 py-3 text-gray-400 text-xs max-w-[160px] truncate">{{ entry.notes || '' }}</td>
                      <td class="px-5 py-3">
                        <div v-if="canEdit" class="flex items-center justify-center gap-1">
                          <button @click="openEntryModal(entry)"
                            class="w-6 h-6 flex items-center justify-center rounded bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                          </button>
                          <button @click="confirmDeleteEntry(entry)"
                            class="w-6 h-6 flex items-center justify-center rounded bg-gray-800 hover:bg-red-900 text-gray-400 hover:text-red-400 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <!-- FORECAST TABLE -->
          <div class="bg-[#112240] border border-[#21518B] rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-800">
              <h2 class="text-white font-semibold flex items-center gap-2">
                <span class="text-indigo-400">✨</span>
                AI Forecast
              </h2>
              <p class="text-gray-500 text-xs mt-0.5">Holt-Winters exponential smoothing</p>
            </div>
            <div v-if="forecast.length === 0" class="px-5 py-8 text-center text-gray-500 text-sm">
              Add at least 4 data points to generate a forecast.
            </div>
            <div v-else class="overflow-y-auto" style="max-height: 520px;">
              <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-900">
                  <tr class="border-b border-gray-800">
                    <th class="text-left text-xs font-semibold text-indigo-400 uppercase tracking-wider px-4 py-3">Date</th>
                    <th class="text-right text-xs font-semibold text-indigo-400 uppercase tracking-wider px-4 py-3">Forecast</th>
                    <th class="text-right text-xs font-semibold text-indigo-400 uppercase tracking-wider px-4 py-3">Range</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(f, i) in forecast" :key="i" class="hover:bg-gray-800/30 transition-colors">
                    <td class="px-4 py-2.5 text-gray-400 font-mono text-xs">{{ formatDate(f.date) }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-indigo-300 tabular-nums">
                      {{ formatValue(f.value) }}
                    </td>
                    <td class="px-4 py-2.5 text-right text-xs text-gray-500 tabular-nums">
                      {{ formatValue(f.lower) }}–{{ formatValue(f.upper) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ── ADD/EDIT ENTRY MODAL ── -->
    <Teleport to="body">
      <div v-if="entryModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-md shadow-2xl">
          <div class="px-6 py-5 border-b border-gray-800 flex items-center justify-between">
            <h2 class="text-white font-bold text-lg">{{ entryModal.editing ? 'Edit Entry' : 'Add Entry' }}</h2>
            <button @click="entryModal.show = false" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Date *</label>
              <input v-model="entryForm.entry_date" type="date"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">
                Value <span class="text-gray-500 normal-case">({{ series.unit || 'number' }})</span>
              </label>
              <input v-model="entryForm.value" type="number" step="any" placeholder="e.g. 30.55"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Notes (optional)</label>
              <input v-model="entryForm.notes" type="text" placeholder="Optional note..."
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500" />
            </div>
          </div>
          <div class="px-6 pb-6 flex gap-3 justify-end">
            <button @click="entryModal.show = false"
              class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-lg transition-colors">Cancel</button>
            <button @click="saveEntry"
              :disabled="!entryForm.entry_date || entryForm.value === ''"
              class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
              {{ entryModal.editing ? 'Save Changes' : 'Add Entry' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── DELETE ENTRY CONFIRM ── -->
    <Teleport to="body">
      <div v-if="deleteEntryModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-gray-900 border border-red-800/60 rounded-2xl w-full max-w-sm shadow-2xl p-6">
          <h2 class="text-white font-bold text-lg mb-2">Delete Entry</h2>
          <p class="text-gray-400 text-sm mb-5">Remove the entry for <span class="text-white font-medium">{{ deleteEntryModal.entry ? formatDate(deleteEntryModal.entry.entry_date) : '' }}</span>?</p>
          <div class="flex gap-3 justify-end">
            <button @click="deleteEntryModal.show = false"
              class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-lg transition-colors">Cancel</button>
            <button @click="executeDeleteEntry"
              class="px-5 py-2 bg-red-700 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── IMPORT MODAL ── -->
    <Teleport to="body">
      <div v-if="importModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-md shadow-2xl">
          <div class="px-6 py-5 border-b border-gray-800 flex items-center justify-between">
            <h2 class="text-white font-bold text-lg">Import CSV / Excel</h2>
            <button @click="importModal.show = false; importError = ''" class="text-gray-400 hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div class="bg-blue-950/40 border border-blue-800/50 rounded-lg p-4 mb-4 text-sm text-blue-300">
              <p class="font-semibold mb-1">File format (CSV or Excel):</p>
              <p>Column A: Date — format <strong>DD/MM/YYYY</strong> (e.g. 15/01/2026)</p>
              <p>Column B: Value (numeric)</p>
              <p>Column C: Notes (optional)</p>
              <p class="mt-2">First row is treated as a header and skipped. Duplicate dates will be updated.</p>
            </div>

            <!-- Error message -->
            <div v-if="importError" class="mb-4 bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded-lg text-sm">
              {{ importError }}
            </div>

            <div class="mb-4">
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-wider mb-2">Select File</label>
              <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls"
                @change="importForm.file = $event.target.files[0]; importError = ''"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer" />
            </div>
            <div class="flex gap-3 justify-end">
              <a :href="`/companies/${props.company.id}/statistica/template`"
                class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-lg transition-colors">
                📥 Download Template
              </a>
              <button @click="submitImport"
                :disabled="!importForm.file || importLoading"
                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
                {{ importLoading ? 'Importing…' : 'Import' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── BULK DELETE CONFIRM ── -->
    <Teleport to="body">
      <div v-if="bulkDeleteModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-gray-900 border border-red-800/60 rounded-2xl w-full max-w-sm shadow-2xl p-6">
          <h2 class="text-white font-bold text-lg mb-2">Delete {{ selectedIds.size }} {{ selectedIds.size === 1 ? 'Entry' : 'Entries' }}</h2>
          <p class="text-gray-400 text-sm mb-5">This will permanently delete <span class="text-white font-medium">{{ selectedIds.size }} {{ selectedIds.size === 1 ? 'entry' : 'entries' }}</span>. This cannot be undone.</p>
          <div class="flex gap-3 justify-end">
            <button @click="bulkDeleteModal.show = false"
              class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-lg transition-colors">Cancel</button>
            <button @click="executeBulkDelete"
              class="px-5 py-2 bg-red-700 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition-colors">
              Delete {{ selectedIds.size }} {{ selectedIds.size === 1 ? 'Entry' : 'Entries' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:  Object,
  series:   Object,
  entries:  Array,
  forecast: Array,
  growth:   Object,
  filters:  Object,
})

// Only admin and super-admin can add / edit / delete entries and import
const page    = usePage()
const canEdit = computed(() => {
  const user = page.props.auth?.user
  return user?.is_super_admin || ['company_admin', 'manager'].includes(user?.role)
})

// ── Chart config ─────────────────────────────────────────────────────────────
const chartW   = 800
const chartH   = 200
const chartPad = { l: 60, r: 20, t: 10, b: 20 }

// ── Tooltip state ─────────────────────────────────────────────────────────────
const tooltip     = reactive({ visible: false, x: 0, y: 0, date: '', value: null, isForecast: false, upper: null, lower: null })
const chartSvgRef = ref(null)

const onChartMouseMove = (e) => {
  const pts = filteredEntries.value
  if (!pts.length || !chartSvgRef.value) return

  // Convert mouse position to SVG coordinate space
  const svg    = chartSvgRef.value
  const rect   = svg.getBoundingClientRect()
  const mouseX = ((e.clientX - rect.left) / rect.width) * chartW

  // Determine if forecast is included in the coordinate space
  const includeForecast = showForecast.value && props.forecast.length > 0
  const allPts  = includeForecast ? [...pts, ...props.forecast] : pts
  const n       = allPts.length

  // Find nearest point index
  const toX = (i) => chartPad.l + (i / (n - 1)) * (chartW - chartPad.l - chartPad.r)
  let   nearest = 0
  let   minDist = Infinity
  for (let i = 0; i < n; i++) {
    const d = Math.abs(toX(i) - mouseX)
    if (d < minDist) { minDist = d; nearest = i }
  }

  // Get value range for Y position
  const allVals = includeForecast
    ? [...pts.map(p => p.value), ...props.forecast.map(f => f.value), ...props.forecast.map(f => f.upper), ...props.forecast.map(f => f.lower)]
    : pts.map(p => p.value)
  const minVal = Math.min(...allVals)
  const maxVal = Math.max(...allVals)
  const range  = maxVal - minVal || 1
  const toY    = (v) => chartPad.t + ((maxVal - v) / range) * (chartH - chartPad.t - chartPad.b)

  const pt         = allPts[nearest]
  const isForecast = nearest >= pts.length
  const svgX       = toX(nearest)
  const svgY       = toY(pt.value)

  tooltip.visible    = true
  tooltip.x          = svgX
  tooltip.y          = svgY
  tooltip.date       = pt.entry_date ?? pt.date
  tooltip.value      = pt.value
  tooltip.isForecast = isForecast
  tooltip.upper      = isForecast ? pt.upper : null
  tooltip.lower      = isForecast ? pt.lower : null
}

const onChartMouseLeave = () => { tooltip.visible = false }

const quickRanges = [
  { key: '1m',   label: '1M'  },
  { key: '3m',   label: '3M'  },
  { key: '6m',   label: '6M'  },
  { key: '1y',   label: '1Y'  },
  { key: 'all',  label: 'All' },
]
const showForecast = ref(true)

// Initialise from server-side filters prop
const customStart = ref(props.filters?.start_date ?? '')
const customEnd   = ref(props.filters?.end_date   ?? '')

// Detect which quick-range button to highlight
const activeQuickRange = computed(() => {
  if (!props.filters || props.filters.mode === 'all') return 'all'
  const start = props.filters.start_date
  const end   = props.filters.end_date
  if (!start || !end) return null
  const diffDays = Math.round((new Date(end) - new Date(start)) / 86400000)
  if (diffDays <= 31)  return '1m'
  if (diffDays <= 92)  return '3m'
  if (diffDays <= 184) return '6m'
  if (diffDays <= 366) return '1y'
  return null
})

const navigateWithDates = (start, end) => {
  router.get(
    `/companies/${props.company.id}/statistica/${props.series.id}`,
    { start_date: start, end_date: end },
    { preserveScroll: false, preserveState: false }
  )
}

const applyQuickRange = (key) => {
  if (key === 'all') {
    navigateWithDates('', '')
    return
  }
  const days = { '1m': 30, '3m': 90, '6m': 180, '1y': 365 }[key]
  const end   = new Date()
  const start = new Date(end.getTime() - days * 86400000)
  const fmt   = (d) => d.toISOString().split('T')[0]
  customStart.value = fmt(start)
  customEnd.value   = fmt(end)
  navigateWithDates(fmt(start), fmt(end))
}

const applyCustomRange = () => {
  if (!customStart.value || !customEnd.value) return
  navigateWithDates(customStart.value, customEnd.value)
}

// filteredEntries is now just the server-returned entries (already filtered)
const filteredEntries = computed(() => props.entries)

const chartData = computed(() => {
  const pts = filteredEntries.value
  if (pts.length < 2) return { points: [], linePath: '', areaPath: '', yTicks: [], xTicks: [] }

  const vals   = pts.map(p => p.value)
  const minVal = Math.min(...vals)
  const maxVal = Math.max(...vals)
  const range  = maxVal - minVal || 1
  const pad    = chartPad

  const toX = (i) => pad.l + (i / (pts.length - 1)) * (chartW - pad.l - pad.r)
  const toY = (v) => pad.t + ((maxVal - v) / range) * (chartH - pad.t - pad.b)

  const points = pts.map((p, i) => ({ x: toX(i), y: toY(p.value), value: p.value, date: p.entry_date }))
  const linePath = 'M' + points.map(p => `${p.x},${p.y}`).join(' L')
  const areaPath = linePath + ` L${points[points.length-1].x},${chartH - pad.b} L${pad.l},${chartH - pad.b} Z`

  // Y ticks (5 lines)
  const yTicks = Array.from({ length: 5 }, (_, i) => {
    const v = minVal + (i / 4) * range
    return { value: v, y: toY(v) }
  })

  // X ticks (up to 6)
  const step = Math.max(1, Math.floor(pts.length / 6))
  const xTicks = pts
    .filter((_, i) => i % step === 0 || i === pts.length - 1)
    .map((p, _, arr) => {
      const idx = pts.indexOf(p)
      return { x: toX(idx), label: formatDateShort(p.entry_date) }
    })

  return { points, linePath, areaPath, yTicks, xTicks }
})

// Forecast chart paths (extend the same coordinate space)
const forecastLinePath = computed(() => {
  if (!showForecast.value || !props.forecast.length || !filteredEntries.value.length) return ''
  const hist = filteredEntries.value
  const all  = [...hist, ...props.forecast]
  const vals   = all.map(p => p.value)
  const minVal = Math.min(...vals)
  const maxVal = Math.max(...vals)
  const range  = maxVal - minVal || 1
  const pad    = chartPad
  const n      = all.length

  const toX = (i) => pad.l + (i / (n - 1)) * (chartW - pad.l - pad.r)
  const toY = (v) => pad.t + ((maxVal - v) / range) * (chartH - pad.t - pad.b)

  // Forecast starts at hist.length - 1
  const fPts = props.forecast.map((f, i) => `${toX(hist.length - 1 + i + 1)},${toY(f.value)}`)
  const startPt = `${toX(hist.length - 1)},${toY(hist[hist.length-1].value)}`
  return `M${startPt} L` + fPts.join(' L')
})

const forecastBandPath = computed(() => {
  if (!showForecast.value || !props.forecast.length || !filteredEntries.value.length) return ''
  const hist = filteredEntries.value
  const all  = [...hist, ...props.forecast]
  const allVals  = [...all.map(p => p.value), ...props.forecast.map(f => f.upper), ...props.forecast.map(f => f.lower)]
  const minVal = Math.min(...allVals)
  const maxVal = Math.max(...allVals)
  const range  = maxVal - minVal || 1
  const pad    = chartPad
  const n      = all.length

  const toX = (i) => pad.l + (i / (n - 1)) * (chartW - pad.l - pad.r)
  const toY = (v) => pad.t + ((maxVal - v) / range) * (chartH - pad.t - pad.b)

  const upper = props.forecast.map((f, i) => `${toX(hist.length + i)},${toY(f.upper)}`)
  const lower = props.forecast.map((f, i) => `${toX(hist.length + i)},${toY(f.lower)}`).reverse()
  return `M${upper.join(' L')} L${lower.join(' L')} Z`
})

const forecastUpperPath = computed(() => {
  if (!showForecast.value || !props.forecast.length || !filteredEntries.value.length) return ''
  const hist = filteredEntries.value
  const all  = [...hist, ...props.forecast]
  const allVals = [...all.map(p => p.value), ...props.forecast.map(f => f.upper)]
  const minVal = Math.min(...allVals)
  const maxVal = Math.max(...allVals)
  const range  = maxVal - minVal || 1
  const pad    = chartPad
  const n      = all.length
  const toX = (i) => pad.l + (i / (n - 1)) * (chartW - pad.l - pad.r)
  const toY = (v) => pad.t + ((maxVal - v) / range) * (chartH - pad.t - pad.b)
  return 'M' + props.forecast.map((f, i) => `${toX(hist.length + i)},${toY(f.upper)}`).join(' L')
})

const forecastLowerPath = computed(() => {
  if (!showForecast.value || !props.forecast.length || !filteredEntries.value.length) return ''
  const hist = filteredEntries.value
  const all  = [...hist, ...props.forecast]
  const allVals = [...all.map(p => p.value), ...props.forecast.map(f => f.lower)]
  const minVal = Math.min(...allVals)
  const maxVal = Math.max(...allVals)
  const range  = maxVal - minVal || 1
  const pad    = chartPad
  const n      = all.length
  const toX = (i) => pad.l + (i / (n - 1)) * (chartW - pad.l - pad.r)
  const toY = (v) => pad.t + ((maxVal - v) / range) * (chartH - pad.t - pad.b)
  return 'M' + props.forecast.map((f, i) => `${toX(hist.length + i)},${toY(f.lower)}`).join(' L')
})

// ── Tooltip position (screen coordinates) ────────────────────────────────────
const tooltipStyle = computed(() => {
  if (!tooltip.visible || !chartSvgRef.value) return {}
  const svg    = chartSvgRef.value
  const rect   = svg.getBoundingClientRect()
  // Convert SVG x back to screen x
  const scaleX = rect.width / chartW
  const scaleY = rect.height / chartH
  const screenX = rect.left + tooltip.x * scaleX
  const screenY = rect.top  + tooltip.y * scaleY
  // Keep tooltip from going off right edge
  const offsetX = screenX > window.innerWidth - 180 ? -160 : 16
  const offsetY = -40
  return {
    left: `${screenX + offsetX}px`,
    top:  `${screenY + offsetY}px`,
  }
})
const growthBars = computed(() => {
  const pop = props.growth?.pop ?? []
  if (!pop.length) return []
  const maxAbs = Math.max(...pop.map(p => Math.abs(p.pct)), 0.01)
  const w  = (chartW - chartPad.l - chartPad.r) / pop.length
  const midY = 60
  return pop.map((p, i) => {
    const barH   = Math.abs(p.pct / maxAbs) * 50
    const positive = p.pct >= 0
    return {
      x: chartPad.l + i * w + w / 2,
      w: Math.max(w - 2, 1),
      y: positive ? midY - barH : midY,
      h: barH,
      positive,
    }
  })
})

const growthXTicks = computed(() => {
  const pop = props.growth?.pop ?? []
  if (!pop.length) return []
  const step = Math.max(1, Math.floor(pop.length / 6))
  const w    = (chartW - chartPad.l - chartPad.r) / pop.length
  return pop
    .filter((_, i) => i % step === 0)
    .map((p, _, arr) => {
      const i = pop.indexOf(p)
      return { x: chartPad.l + i * w + w / 2, label: formatDateShort(p.date) }
    })
})

// ── Stats ─────────────────────────────────────────────────────────────────────
const reversedEntries = computed(() => [...props.entries].reverse())
const latestEntry     = computed(() => props.entries.length ? props.entries[props.entries.length - 1] : null)

const popChange = computed(() => {
  const n = props.entries.length
  if (n < 2) return null
  return round4(props.entries[n-1].value - props.entries[n-2].value)
})
const popChangePct = computed(() => {
  const n = props.entries.length
  if (n < 2 || props.entries[n-2].value === 0) return null
  return round2((props.entries[n-1].value - props.entries[n-2].value) / Math.abs(props.entries[n-2].value) * 100)
})

const lastYoy = computed(() => {
  const yoy = props.growth?.yoy ?? []
  return yoy.length ? yoy[yoy.length - 1]?.pct ?? null : null
})

const entryChange = (i) => {
  const reversed = reversedEntries.value
  if (i >= reversed.length - 1) return null
  return round4(reversed[i].value - reversed[i+1].value)
}

// ── Entry modal ───────────────────────────────────────────────────────────────
const entryModal = reactive({ show: false, editing: null })
const entryForm  = reactive({ entry_date: '', value: '', notes: '' })

const openEntryModal = (entry) => {
  if (entry) {
    Object.assign(entryForm, { entry_date: entry.entry_date, value: entry.value, notes: entry.notes ?? '' })
    entryModal.editing = entry.id
  } else {
    const today = new Date().toISOString().split('T')[0]
    Object.assign(entryForm, { entry_date: today, value: '', notes: '' })
    entryModal.editing = null
  }
  entryModal.show = true
}

const saveEntry = () => {
  if (entryModal.editing) {
    router.put(`/companies/${props.company.id}/statistica/${props.series.id}/entries/${entryModal.editing}`, { ...entryForm }, {
      onSuccess: () => { entryModal.show = false }
    })
  } else {
    router.post(`/companies/${props.company.id}/statistica/${props.series.id}/entries`, { ...entryForm }, {
      onSuccess: () => { entryModal.show = false }
    })
  }
}

const deleteEntryModal = reactive({ show: false, entry: null })
const confirmDeleteEntry = (entry) => {
  deleteEntryModal.entry = entry
  deleteEntryModal.show  = true
}
const executeDeleteEntry = () => {
  router.delete(`/companies/${props.company.id}/statistica/${props.series.id}/entries/${deleteEntryModal.entry.id}`, {
    onSuccess: () => { deleteEntryModal.show = false }
  })
}

// ── Checkbox selection ────────────────────────────────────────────────────────
const selectedIds = reactive(new Set())

const allSelected = computed(() =>
  reversedEntries.value.length > 0 && reversedEntries.value.every(e => selectedIds.has(e.id))
)
const someSelected = computed(() =>
  reversedEntries.value.some(e => selectedIds.has(e.id)) && !allSelected.value
)

const toggleSelect = (id) => {
  if (selectedIds.has(id)) selectedIds.delete(id)
  else selectedIds.add(id)
}
const toggleSelectAll = () => {
  if (allSelected.value) {
    reversedEntries.value.forEach(e => selectedIds.delete(e.id))
  } else {
    reversedEntries.value.forEach(e => selectedIds.add(e.id))
  }
}

// ── Bulk delete ───────────────────────────────────────────────────────────────
const bulkDeleteModal = reactive({ show: false })

const confirmBulkDelete = () => {
  if (selectedIds.size === 0) return
  bulkDeleteModal.show = true
}
const executeBulkDelete = () => {
  router.post(
    `/companies/${props.company.id}/statistica/${props.series.id}/bulk-delete`,
    { ids: [...selectedIds] },
    {
      preserveScroll: false,
      preserveState: false,
      onSuccess: () => {
        bulkDeleteModal.show = false
        selectedIds.clear()
      },
    }
  )
}

// ── Import modal ──────────────────────────────────────────────────────────────
const importModal  = reactive({ show: false })
const importForm   = reactive({ file: null })
const importError  = ref('')
const importLoading = ref(false)
const fileInput    = ref(null)

const submitImport = () => {
  if (!importForm.file) return
  importError.value   = ''
  importLoading.value = true

  const data = new FormData()
  data.append('file', importForm.file)

  router.post(
    `/companies/${props.company.id}/statistica/${props.series.id}/import`,
    data,
    {
      forceFormData: true,
      preserveScroll: false,
      preserveState: false,
      onSuccess: () => {
        importModal.show    = false
        importForm.file     = null
        importLoading.value = false
        if (fileInput.value) fileInput.value.value = ''
      },
      onError: (errors) => {
        importError.value   = errors.file ?? 'Import failed. Please check your file and try again.'
        importLoading.value = false
      },
    }
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatValue = (v) => {
  if (v === null || v === undefined) return '—'
  if (Math.abs(v) >= 1000) return Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 })
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}
const formatDate = (d) => {
  if (!d) return ''
  return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
const formatDateShort = (d) => {
  if (!d) return ''
  return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}
const round2 = (v) => Math.round(v * 100) / 100
const round4 = (v) => Math.round(v * 10000) / 10000
</script>