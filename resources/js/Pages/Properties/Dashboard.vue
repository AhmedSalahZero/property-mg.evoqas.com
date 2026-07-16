<template>
  <Head :title="`Property Dashboard — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen" style="background-color: var(--fv-bg); color: var(--fv-text-primary);">

      <!-- ── PAGE HEADER ──────────────────────────────────────────────── -->
      <div style="background-color: var(--fv-bg-header); border-bottom: 1px solid var(--fv-border);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest mb-0.5" style="color: var(--fv-gold);">
                {{ company.name }} · Property Management
              </p>
              <h1 class="text-xl font-bold" style="color: var(--fv-text-primary);">Property Dashboard</h1>
              <p class="text-xs mt-0.5" style="color: var(--fv-text-muted);">Portfolio analytics & financial performance</p>
            </div>
            <!-- Date range -->
            <div class="flex items-center gap-3 flex-wrap">
              <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg"
                style="background: var(--fv-bg-input); border: 1px solid var(--fv-border);">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--fv-blue);">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <input v-model="dateFrom" type="date" @change="loadData" class="bg-transparent text-sm focus:outline-none w-32" style="color: var(--fv-text-primary);" />
                <span class="text-xs" style="color: var(--fv-text-muted);">→</span>
                <input v-model="dateTo" type="date" @change="loadData" class="bg-transparent text-sm focus:outline-none w-32" style="color: var(--fv-text-primary);" />
              </div>
              <Link :href="route('company.properties.index', company.id)"
                class="flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-all fv-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Properties
              </Link>
            </div>
          </div>

          <!-- ── TAB NAV ────────────────────────────────────────────── -->
          <div class="flex items-center gap-1 mt-4 overflow-x-auto pb-1">
            <button v-for="tab in tabs" :key="tab.key"
              @click="activeTab = tab.key"
              class="flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg whitespace-nowrap transition-all"
              :style="activeTab === tab.key
                ? 'background: var(--fv-blue); color: #fff;'
                : 'background: transparent; color: var(--fv-text-muted); border: 1px solid transparent;'"
              @mouseover="e => { if(activeTab !== tab.key) e.currentTarget.style.color='var(--fv-text-primary)' }"
              @mouseout="e => { if(activeTab !== tab.key) e.currentTarget.style.color='var(--fv-text-muted)' }">
              <span>{{ tab.icon }}</span>
              {{ tab.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- ── LOADING ──────────────────────────────────────────────────── -->
      <div v-if="loading" class="max-w-7xl mx-auto px-4 py-16 text-center">
        <svg class="animate-spin w-10 h-10 mx-auto mb-4" fill="none" viewBox="0 0 24 24" style="color: var(--fv-blue);">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        <p class="text-sm" style="color: var(--fv-text-muted);">Loading dashboard data...</p>
      </div>

      <!-- ── ERROR ───────────────────────────────────────────────────────── -->
      <div v-else-if="loadError" class="max-w-7xl mx-auto px-4 py-16 text-center">
        <p class="text-3xl mb-3">⚠️</p>
        <p class="text-sm font-semibold mb-1" style="color: #f87171;">Dashboard failed to load</p>
        <p class="text-xs mb-4" style="color: var(--fv-text-muted);">{{ loadError }}</p>
        <button @click="loadData" class="text-xs font-semibold px-4 py-2 rounded-lg fv-btn-secondary">Retry</button>
      </div>

      <!-- ── CONTENT ──────────────────────────────────────────────────── -->
      <div v-else-if="!loadError" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- AUTO INSIGHTS (always shown) -->
        <div v-if="insights.length > 0">
          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Auto Insights & Alerts</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="(ins, i) in insights" :key="i"
              class="rounded-xl border p-4 flex gap-3"
              :style="ins.type === 'positive'
                ? 'background: rgba(16,185,129,0.06); border-color: rgba(16,185,129,0.20);'
                : ins.type === 'warning'
                ? 'background: var(--fv-gold-dim); border-color: var(--fv-gold-border);'
                : 'background: rgba(239,68,68,0.06); border-color: rgba(239,68,68,0.20);'">
              <span class="text-2xl flex-shrink-0 mt-0.5">{{ ins.icon }}</span>
              <div>
                <p class="text-sm font-semibold" style="color: var(--fv-text-primary);">{{ ins.title }}</p>
                <p class="text-xs mt-1 leading-relaxed" style="color: var(--fv-text-label);">{{ ins.body }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 1 — PORTFOLIO OVERVIEW
        ═════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'portfolio' && portfolio">

          <!-- KPI Strip -->
          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Portfolio Summary</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <div v-for="kpi in portfolioKpis" :key="kpi.label" class="rounded-xl p-4"
              style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" :style="`color: ${kpi.color};`">{{ kpi.label }}</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ kpi.value }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">{{ kpi.sub }}</p>
            </div>
          </div>

          <!-- Financial cards -->
          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Financial Overview</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #48C4D8;">Acquisition Cost</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(portfolio.total_acquisition_cost) }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Total portfolio cost</p>
            </div>
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #FAC775;">Book Value</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(portfolio.total_book_value) }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Net after depreciation</p>
            </div>
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #34d399;">Market Value</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(portfolio.total_market_value) }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Latest valuations</p>
            </div>
            <div class="rounded-xl p-5" :style="portfolio.unrealized_gain >= 0
              ? 'background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.25);'
              : 'background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25);'">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" :style="portfolio.unrealized_gain >= 0 ? 'color: #34d399;' : 'color: #f87171;'">Unrealized Gain</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(portfolio.unrealized_gain) }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Market vs Book value</p>
            </div>
            <div class="rounded-xl p-5" :style="portfolio.roi_if_sold != null && portfolio.roi_if_sold >= 0
              ? 'background: rgba(186,117,23,0.08); border: 1px solid rgba(186,117,23,0.30);'
              : 'background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25);'">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: var(--fv-gold);">ROI if Sold</p>
              <p class="text-2xl font-bold" :style="portfolio.roi_if_sold != null && portfolio.roi_if_sold >= 0 ? 'color: #FAC775;' : 'color: #f87171;'">
                {{ portfolio.roi_if_sold != null ? portfolio.roi_if_sold + '%' : '—' }}
              </p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Unrealized gain ÷ cost</p>
            </div>
          </div>

          <!-- Occupancy table -->
          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Unit Status</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div v-for="s in ['occupied','vacant','not_delivered']" :key="s"
              class="rounded-xl p-5 cursor-pointer transition-all"
              :style="occupancyFilter === s
                ? statusStyle(s, true)
                : 'background: var(--fv-bg-card); border: 1px solid var(--fv-border);'"
              @click="occupancyFilter = occupancyFilter === s ? null : s">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" :style="`color: ${statusColor(s)};`">{{ statusLabel(s) }}</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ portfolio.status_counts?.[s] ?? 0 }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Click to filter</p>
            </div>
          </div>

          <!-- Slot detail table -->
          <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">
                {{ occupancyFilter ? statusLabel(occupancyFilter) + ' Units' : 'All Units' }}
                <span class="ml-2 font-normal normal-case">{{ filteredSlots.length }} units</span>
              </p>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr style="border-bottom: 1px solid var(--fv-border);">
                    <th class="text-left text-xs font-semibold uppercase px-5 py-3" style="color: var(--fv-text-muted);">Unit / Property</th>
                    <th class="text-left text-xs font-semibold uppercase px-5 py-3" style="color: var(--fv-text-muted);">Governorate</th>
                    <th class="text-right text-xs font-semibold uppercase px-5 py-3" style="color: var(--fv-text-muted);">Area</th>
                    <th class="text-left text-xs font-semibold uppercase px-5 py-3" style="color: var(--fv-text-muted);">Status</th>
                    <th class="text-left text-xs font-semibold uppercase px-5 py-3" style="color: var(--fv-text-muted);">Tenant</th>
                    <th class="text-left text-xs font-semibold uppercase px-5 py-3" style="color: var(--fv-text-muted);">Contract End</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(slot, i) in filteredSlots" :key="i"
                    style="border-bottom: 1px solid var(--fv-border);"
                    onmouseover="this.style.background='var(--fv-bg-hover)'"
                    onmouseout="this.style.background='transparent'">
                    <td class="px-5 py-2.5">
                      <p class="font-medium text-sm" style="color: var(--fv-text-primary);">{{ slot.name }}</p>
                      <p class="text-xs" style="color: var(--fv-text-muted);">{{ slot.code }}</p>
                    </td>
                    <td class="px-5 py-2.5 text-sm" style="color: var(--fv-text-muted);">{{ slot.governorate ?? '—' }}</td>
                    <td class="px-5 py-2.5 text-right text-sm" style="color: var(--fv-text-muted);">{{ slot.area ? Math.round(parseFloat(slot.area)).toLocaleString() + ' m²' : '—' }}</td>
                    <td class="px-5 py-2.5">
                      <span class="fv-badge text-xs px-2 py-0.5 rounded-full" :style="statusBadge(slot.status)">{{ statusLabel(slot.status) }}</span>
                    </td>
                    <td class="px-5 py-2.5 text-sm" style="color: var(--fv-text-primary);">{{ slot.tenant ?? '—' }}</td>
                    <td class="px-5 py-2.5 text-sm" style="color: var(--fv-text-muted);">{{ fmtDate(slot.contract_end) }}</td>
                  </tr>
                  <tr v-if="filteredSlots.length === 0">
                    <td colspan="6" class="px-5 py-8 text-center text-sm" style="color: var(--fv-text-muted);">No units found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 2 — CONTRACT ANALYSIS
        ═════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'contracts' && contracts">

          <!-- KPI strip -->
          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Contract Overview</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #34d399;">Running</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ contracts.running_count }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Active contracts</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #FAC775;">Expired</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ contracts.expired_count }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #f87171;">Terminated</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ contracts.terminated_count }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #48C4D8;">Monthly Rent</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(contracts.total_monthly_rent) }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Total contracted</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #FAC775;">With Increase</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ contracts.with_increase }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Avg {{ contracts.avg_increase_rate }}% p.a.</p>
            </div>
          </div>

          <!-- Expiry radar -->
          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Renewal Radar</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div v-for="radar in expiryRadar" :key="radar.label"
              class="rounded-xl p-4 text-center"
              :style="radar.count > 0
                ? 'background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.20);'
                : 'background: var(--fv-bg-card); border: 1px solid var(--fv-border);'">
              <p class="text-xs font-semibold uppercase mb-1" :style="radar.count > 0 ? 'color: #f87171;' : 'color: var(--fv-text-muted);'">{{ radar.label }}</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ radar.count }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">contracts</p>
            </div>
          </div>

          <!-- Expiring list + Top tenants side by side -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Expiring contracts -->
            <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">Expiring in 180 Days</p>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr style="border-bottom: 1px solid var(--fv-border);">
                      <th class="text-left text-xs px-4 py-2.5 font-semibold uppercase" style="color: var(--fv-text-muted);">Tenant</th>
                      <th class="text-left text-xs px-4 py-2.5 font-semibold uppercase" style="color: var(--fv-text-muted);">Property</th>
                      <th class="text-right text-xs px-4 py-2.5 font-semibold uppercase" style="color: var(--fv-text-muted);">Days Left</th>
                      <th class="text-right text-xs px-4 py-2.5 font-semibold uppercase" style="color: var(--fv-text-muted);">Monthly Rent</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="c in contracts.expiring_list" :key="c.id"
                      style="border-bottom: 1px solid var(--fv-border);"
                      onmouseover="this.style.background='var(--fv-bg-hover)'"
                      onmouseout="this.style.background='transparent'">
                      <td class="px-4 py-2.5 font-medium" style="color: var(--fv-text-primary);">{{ c.tenant }}</td>
                      <td class="px-4 py-2.5 text-xs" style="color: var(--fv-text-muted);">{{ c.property }}{{ c.unit ? ' / ' + c.unit : '' }}</td>
                      <td class="px-4 py-2.5 text-right">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          :style="c.days_left <= 30 ? 'color:#f87171;background:rgba(239,68,68,0.10);' : c.days_left <= 90 ? 'color:#FAC775;background:var(--fv-gold-dim);' : 'color:#48C4D8;background:var(--fv-blue-dim);'">
                          {{ c.days_left }}d
                        </span>
                      </td>
                      <td class="px-4 py-2.5 text-right font-semibold" style="color: #48C4D8;">{{ fmt(c.monthly_rent) }}</td>
                    </tr>
                    <tr v-if="!contracts.expiring_list?.length">
                      <td colspan="4" class="px-4 py-6 text-center text-xs" style="color: var(--fv-text-muted);">No contracts expiring in 180 days.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Top tenants -->
            <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">Top Tenants by Contracted Rent</p>
              </div>
              <div class="p-4 space-y-3">
                <div v-for="(t, i) in contracts.top_tenants" :key="i">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium" style="color: var(--fv-text-primary);">{{ t.name }}</span>
                    <span class="text-sm font-semibold" style="color: #48C4D8;">{{ fmt(t.monthly_rent) }}/mo</span>
                  </div>
                  <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--fv-border);">
                    <div class="h-full rounded-full" style="background: linear-gradient(90deg, var(--fv-blue), var(--fv-gold));"
                      :style="`width: ${contracts.top_tenants[0]?.monthly_rent > 0 ? (t.monthly_rent / contracts.top_tenants[0].monthly_rent * 100) : 0}%`"></div>
                  </div>
                  <p class="text-xs mt-0.5" style="color: var(--fv-text-muted);">{{ t.contracts }} contract(s)</p>
                </div>
                <p v-if="!contracts.top_tenants?.length" class="text-xs text-center py-4" style="color: var(--fv-text-muted);">No running contracts.</p>
              </div>
            </div>
          </div>

          <!-- Revenue trend chart -->
          <div class="rounded-xl p-6" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Monthly Revenue Schedule</p>
            <div style="height: 260px;"><canvas ref="contractChartCanvas"></canvas></div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 3 — REVENUE ANALYSIS
        ═════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'revenues' && revenues">

          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Revenue Summary</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #48C4D8;">Total Revenue (Period)</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(revenues.total_revenue) }}</p>
            </div>
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #34d399;">Forward 12 Months</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(revenues.forward_12_months?.reduce((s, r) => s + r.value, 0) ?? 0) }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Scheduled from today</p>
            </div>
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #FAC775;">Avg Monthly</p>
              <p class="text-3xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(revenues.monthly_trend?.length ? revenues.total_revenue / revenues.monthly_trend.length : 0) }}</p>
            </div>
          </div>

          <!-- Forward 12 months chart -->
          <div class="rounded-xl p-6 mb-6" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Revenue — Next 12 Months</p>
            <div style="height: 260px;"><canvas ref="revenueForwardCanvas"></canvas></div>
          </div>

          <!-- Breakdowns row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">By Revenue Type</p>
              </div>
              <div class="p-4 space-y-2">
                <div v-for="row in revenues.by_revenue_type" :key="row.label" class="flex justify-between items-center">
                  <span class="text-sm capitalize" style="color: var(--fv-text-primary);">{{ row.label?.replace('_', ' ') }}</span>
                  <span class="text-sm font-semibold" style="color: #48C4D8;">{{ fmt(row.value) }}</span>
                </div>
              </div>
            </div>
            <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">By Tenant Nature</p>
              </div>
              <div class="p-4 space-y-2">
                <div v-for="row in revenues.by_tenant_nature" :key="row.label" class="flex justify-between items-center">
                  <span class="text-sm capitalize" style="color: var(--fv-text-primary);">{{ row.label }}</span>
                  <span class="text-sm font-semibold" style="color: #48C4D8;">{{ fmt(row.value) }}</span>
                </div>
              </div>
            </div>
            <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">By Property Nature</p>
              </div>
              <div class="p-4 space-y-2">
                <div v-for="row in revenues.by_property_nature" :key="row.label" class="flex justify-between items-center">
                  <span class="text-sm capitalize" style="color: var(--fv-text-primary);">{{ row.label }}</span>
                  <span class="text-sm font-semibold" style="color: #48C4D8;">{{ fmt(row.value) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Top properties -->
          <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">Top Properties by Revenue</p>
            </div>
            <div class="p-4 space-y-3">
              <div v-for="(p, i) in revenues.top_properties" :key="i">
                <div class="flex justify-between items-center mb-1">
                  <span class="text-sm font-medium" style="color: var(--fv-text-primary);">{{ p.label }}</span>
                  <span class="text-sm font-semibold" style="color: #48C4D8;">{{ fmt(p.value) }}</span>
                </div>
                <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--fv-border);">
                  <div class="h-full rounded-full" style="background: var(--fv-blue);"
                    :style="`width: ${revenues.top_properties[0]?.value > 0 ? (p.value / revenues.top_properties[0].value * 100) : 0}%`"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 4 — COLLECTION ANALYSIS
        ═════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'collections' && collections">

          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Collection Summary</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3 mb-6">
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: var(--fv-text-muted);">Total Due</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(collections.total_due) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #34d399;">Collected</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(collections.total_collected) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-gold-dim); border: 1px solid var(--fv-gold-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #FAC775;">Pending</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(collections.total_pending) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #f87171;">Overdue</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(collections.total_overdue) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #48C4D8;">Collection Rate</p>
              <p class="text-2xl font-bold" :style="collections.collection_rate >= 90 ? 'color: #34d399;' : collections.collection_rate >= 70 ? 'color: #FAC775;' : 'color: #f87171;'">{{ collections.collection_rate }}%</p>
            </div>
          </div>

          <!-- Forward 6 months + Aging side by side -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Forward chart -->
            <div class="rounded-xl p-6" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Collections — Next 6 Months</p>
              <div style="height: 220px;"><canvas ref="collectionForwardCanvas"></canvas></div>
            </div>

            <!-- Aging buckets -->
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Overdue Aging</p>
              <div class="space-y-3">
                <div v-for="bucket in agingBuckets" :key="bucket.label">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-semibold" :style="`color: ${bucket.color};`">{{ bucket.label }}</span>
                    <span class="text-sm font-semibold" style="color: var(--fv-text-primary);">{{ fmt(collections.aging?.[bucket.key] ?? 0) }}</span>
                  </div>
                  <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--fv-border);">
                    <div class="h-full rounded-full" :style="`background: ${bucket.color}; width: ${agingBarWidth(collections.aging?.[bucket.key] ?? 0)}%`"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Outstanding by tenant -->
          <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">Outstanding by Tenant</p>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr style="border-bottom: 1px solid var(--fv-border);">
                    <th class="text-left text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Tenant</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Outstanding</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="t in collections.outstanding_by_tenant" :key="t.tenant"
                    style="border-bottom: 1px solid var(--fv-border);"
                    onmouseover="this.style.background='var(--fv-bg-hover)'"
                    onmouseout="this.style.background='transparent'">
                    <td class="px-5 py-2.5 font-medium" style="color: var(--fv-text-primary);">{{ t.tenant }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold" style="color: #f87171;">{{ fmt(t.outstanding) }}</td>
                  </tr>
                  <tr v-if="!collections.outstanding_by_tenant?.length">
                    <td colspan="2" class="px-5 py-6 text-center text-xs" style="color: var(--fv-text-muted);">No outstanding collections.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 5 — INSTALLMENTS
        ═════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'installments' && installments">

          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Installment Summary</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3 mb-6">
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: var(--fv-text-muted);">Total Plan</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(installments.total_amount) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #34d399;">Paid</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(installments.total_paid) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-gold-dim); border: 1px solid var(--fv-gold-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #FAC775;">Pending</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(installments.total_pending) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #f87171;">Overdue</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(installments.total_overdue) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-blue-dim); border: 1px solid var(--fv-blue-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #48C4D8;">Paid %</p>
              <p class="text-2xl font-bold" style="color: #48C4D8;">{{ installments.paid_pct }}%</p>
            </div>
          </div>

          <!-- Forward 6 months + aging -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="rounded-xl p-6" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Upcoming Dues — Next 6 Months</p>
              <div style="height: 220px;"><canvas ref="installmentForwardCanvas"></canvas></div>
            </div>
            <div class="rounded-xl p-5" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Overdue Aging</p>
              <div class="space-y-3">
                <div v-for="bucket in agingBuckets" :key="bucket.label">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-semibold" :style="`color: ${bucket.color};`">{{ bucket.label }}</span>
                    <span class="text-sm font-semibold" style="color: var(--fv-text-primary);">{{ fmt(installments.aging?.[bucket.key] ?? 0) }}</span>
                  </div>
                  <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--fv-border);">
                    <div class="h-full rounded-full" :style="`background: ${bucket.color}; width: ${instAgingBarWidth(installments.aging?.[bucket.key] ?? 0)}%`"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Per property table -->
          <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">Per Property</p>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr style="border-bottom: 1px solid var(--fv-border);">
                    <th class="text-left text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Property</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Total Plan</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Paid</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Outstanding</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Overdue</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Complete</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in installments.by_property" :key="p.property"
                    style="border-bottom: 1px solid var(--fv-border);"
                    onmouseover="this.style.background='var(--fv-bg-hover)'"
                    onmouseout="this.style.background='transparent'">
                    <td class="px-5 py-2.5 font-medium" style="color: var(--fv-text-primary);">{{ p.property }}</td>
                    <td class="px-5 py-2.5 text-right" style="color: var(--fv-text-muted);">{{ fmt(p.total) }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold" style="color: #34d399;">{{ fmt(p.paid) }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold" style="color: #FAC775;">{{ fmt(p.outstanding) }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold" style="color: #f87171;">{{ fmt(p.overdue) }}</td>
                    <td class="px-5 py-2.5 text-right">
                      <span class="text-xs font-semibold" style="color: #48C4D8;">{{ p.completion_pct }}%</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 6 — EXPENSE ANALYSIS
        ═════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'expenses' && expenses">

          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Expense Summary</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: var(--fv-text-muted);">Total Committed</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(expenses.total_committed) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #34d399;">Total Paid</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(expenses.total_paid) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #f87171;">Outstanding</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(expenses.total_outstanding) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #48C4D8;">Payment Rate</p>
              <p class="text-2xl font-bold" style="color: #48C4D8;">{{ expenses.payment_rate }}%</p>
            </div>
          </div>

          <!-- Monthly trend chart -->
          <div class="rounded-xl p-6 mb-6" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Monthly Expense Trend</p>
            <div style="height: 240px;"><canvas ref="expenseChartCanvas"></canvas></div>
          </div>

          <!-- Category + Property breakdowns -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">By Category</p>
              </div>
              <div class="p-4 space-y-3">
                <div v-for="row in expenses.by_category" :key="row.label">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium" style="color: var(--fv-text-primary);">{{ row.label }}</span>
                    <span class="text-sm font-semibold" style="color: #FAC775;">{{ fmt(row.value) }}</span>
                  </div>
                  <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--fv-border);">
                    <div class="h-full rounded-full" style="background: var(--fv-gold);"
                      :style="`width: ${expenses.by_category[0]?.value > 0 ? (row.value / expenses.by_category[0].value * 100) : 0}%`"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
                <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">By Property</p>
              </div>
              <div class="p-4 space-y-3">
                <div v-for="row in expenses.by_property" :key="row.label">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium" style="color: var(--fv-text-primary);">{{ row.label }}</span>
                    <span class="text-sm font-semibold" style="color: #FAC775;">{{ fmt(row.value) }}</span>
                  </div>
                  <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--fv-border);">
                    <div class="h-full rounded-full" style="background: var(--fv-gold);"
                      :style="`width: ${expenses.by_property[0]?.value > 0 ? (row.value / expenses.by_property[0].value * 100) : 0}%`"></div>
                  </div>
                </div>
                <p v-if="!expenses.by_property?.length" class="text-xs text-center py-4" style="color: var(--fv-text-muted);">No expense data.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 7 — PROFITABILITY
        ═════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'profitability' && profitability">

          <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--fv-gold);">Portfolio P&L</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            <div class="rounded-xl p-4" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #34d399;">Revenue</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(profitability.total_revenue) }}</p>
            </div>
            <div class="rounded-xl p-4" style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #f87171;">Expenses</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(profitability.total_expenses) }}</p>
            </div>
            <div class="rounded-xl p-4"
              :style="profitability.noi >= 0 ? 'background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.30);' : 'background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.30);'">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" :style="profitability.noi >= 0 ? 'color: #34d399;' : 'color: #f87171;'">NOI</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(profitability.noi) }}</p>
              <p class="text-xs mt-1" style="color: var(--fv-text-muted);">Margin: {{ profitability.noi_margin }}%</p>
            </div>
            <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: #48C4D8;">Market Value</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(profitability.total_market_value) }}</p>
            </div>
            <div class="rounded-xl p-4"
              :style="profitability.unrealized_gain >= 0 ? 'background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.20);' : 'background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.20);'">
              <p class="text-xs font-semibold uppercase tracking-widest mb-1" :style="profitability.unrealized_gain >= 0 ? 'color: #34d399;' : 'color: #f87171;'">Unrealized Gain</p>
              <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ fmt(profitability.unrealized_gain) }}</p>
            </div>
          </div>

          <!-- NOI trend chart -->
          <div class="rounded-xl p-6 mb-6" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--fv-text-muted);">Monthly NOI — Revenue vs Expenses</p>
            <div style="height: 260px;"><canvas ref="noiChartCanvas"></canvas></div>
          </div>

          <!-- Per property P&L table -->
          <div class="rounded-xl overflow-hidden" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
            <div class="px-5 py-3" style="border-bottom: 1px solid var(--fv-border);">
              <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--fv-text-muted);">Per Property P&L</p>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr style="border-bottom: 1px solid var(--fv-border);">
                    <th class="text-left text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Property</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Revenue</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Expenses</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">NOI</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">Margin</th>
                    <th class="text-right text-xs px-5 py-3 font-semibold uppercase" style="color: var(--fv-text-muted);">ROI</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in profitability.per_property" :key="p.property"
                    style="border-bottom: 1px solid var(--fv-border);"
                    onmouseover="this.style.background='var(--fv-bg-hover)'"
                    onmouseout="this.style.background='transparent'">
                    <td class="px-5 py-2.5 font-medium" style="color: var(--fv-text-primary);">{{ p.property }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold" style="color: #34d399;">{{ fmt(p.revenue) }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold" style="color: #f87171;">{{ fmt(p.expenses) }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold" :style="p.noi >= 0 ? 'color: #34d399;' : 'color: #f87171;'">{{ fmt(p.noi) }}</td>
                    <td class="px-5 py-2.5 text-right text-xs" style="color: var(--fv-text-muted);">{{ p.noi_margin }}%</td>
                    <td class="px-5 py-2.5 text-right">
                      <span v-if="p.roi_pct !== null" class="text-xs font-semibold" :style="p.roi_pct >= 0 ? 'color: #34d399;' : 'color: #f87171;'">{{ p.roi_pct }}%</span>
                      <span v-else class="text-xs" style="color: var(--fv-text-muted);">—</span>
                    </td>
                  </tr>
                  <tr v-if="!profitability.per_property?.length">
                    <td colspan="6" class="px-5 py-8 text-center text-xs" style="color: var(--fv-text-muted);">No data for this period.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
  company: Object,
})

// ── State ──────────────────────────────────────────────────────────────
const loading   = ref(true)
const loadError = ref(null)
const dateFrom  = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const dateTo    = ref(new Date().toISOString().slice(0, 10))
const activeTab = ref('portfolio')

const portfolio    = ref(null)
const contracts    = ref(null)
const revenues     = ref(null)
const collections  = ref(null)
const installments = ref(null)
const expenses     = ref(null)
const profitability= ref(null)
const insights     = ref([])

const occupancyFilter = ref(null)

// ── Canvas refs ────────────────────────────────────────────────────────
const contractChartCanvas     = ref(null)
const revenueForwardCanvas    = ref(null)
const collectionForwardCanvas = ref(null)
const installmentForwardCanvas= ref(null)
const expenseChartCanvas      = ref(null)
const noiChartCanvas          = ref(null)

let ChartLib = null
const chartInstances = {}

// ── Tabs ───────────────────────────────────────────────────────────────
const tabs = [
  { key: 'portfolio',    icon: '🏢', label: 'Portfolio' },
  { key: 'contracts',    icon: '📋', label: 'Contracts' },
  { key: 'revenues',     icon: '💰', label: 'Revenues' },
  { key: 'collections',  icon: '🏦', label: 'Collections' },
  { key: 'installments', icon: '📅', label: 'Installments' },
  { key: 'expenses',     icon: '💸', label: 'Expenses' },
  { key: 'profitability',icon: '📊', label: 'Profitability' },
]

// ── Computed ───────────────────────────────────────────────────────────
const portfolioKpis = computed(() => {
  if (!portfolio.value) return []
  const p = portfolio.value
  return [
    { label: 'Total Properties', value: p.total_properties, sub: 'in portfolio', color: '#48C4D8' },
    { label: 'Leasable Units', value: p.total_leasable, sub: 'slots tracked', color: '#FAC775' },
    { label: 'Occupied', value: p.status_counts?.occupied ?? 0, sub: `${p.occupancy_rate}% rate`, color: '#34d399' },
    { label: 'Vacant', value: p.status_counts?.vacant ?? 0, sub: 'available now', color: '#f87171' },
    { label: 'Not Delivered', value: p.status_counts?.not_delivered ?? 0, sub: 'installment', color: '#c084fc' },
    { label: 'Occupied Area', value: Math.round(p.occupied_area ?? 0).toLocaleString() + ' m²', sub: `of ${Math.round(p.total_area ?? 0).toLocaleString()} m²`, color: '#48C4D8' },
  ]
})

const filteredSlots = computed(() => {
  if (!portfolio.value?.slots) return []
  if (!occupancyFilter.value) return portfolio.value.slots
  return portfolio.value.slots.filter(s => s.status === occupancyFilter.value)
})

const expiryRadar = computed(() => {
  if (!contracts.value) return []
  return [
    { label: 'Within 30 Days', count: contracts.value.expiring_30 },
    { label: 'Within 60 Days', count: contracts.value.expiring_60 },
    { label: 'Within 90 Days', count: contracts.value.expiring_90 },
    { label: 'Within 180 Days', count: contracts.value.expiring_180 },
  ]
})

const agingBuckets = [
  { key: '0_30',    label: '0–30 Days',  color: '#FAC775' },
  { key: '31_60',   label: '31–60 Days', color: '#fb923c' },
  { key: '61_90',   label: '61–90 Days', color: '#ef4444' },
  { key: '90_plus', label: '90+ Days',   color: '#b91c1c' },
]

function agingBarWidth(val) {
  const max = Math.max(...agingBuckets.map(b => collections.value?.aging?.[b.key] ?? 0))
  return max > 0 ? Math.round(val / max * 100) : 0
}
function instAgingBarWidth(val) {
  const max = Math.max(...agingBuckets.map(b => installments.value?.aging?.[b.key] ?? 0))
  return max > 0 ? Math.round(val / max * 100) : 0
}

// ── Helpers ────────────────────────────────────────────────────────────
function fmt(val) {
  const n = parseFloat(val) || 0
  return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function statusLabel(s) {
  return { occupied: 'Occupied', vacant: 'Vacant', not_delivered: 'Not Delivered' }[s] ?? s
}
function statusColor(s) {
  return { occupied: '#34d399', vacant: '#f87171', not_delivered: '#c084fc' }[s] ?? '#48C4D8'
}
function statusStyle(s, active) {
  const colors = {
    occupied:      'background: rgba(16,185,129,0.08); border: 2px solid rgba(16,185,129,0.40);',
    vacant:        'background: rgba(239,68,68,0.08); border: 2px solid rgba(239,68,68,0.40);',
    not_delivered: 'background: rgba(192,132,252,0.08); border: 2px solid rgba(192,132,252,0.40);',
  }
  return colors[s] ?? 'background: var(--fv-bg-card); border: 1px solid var(--fv-border);'
}
function statusBadge(s) {
  return {
    occupied:      'background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.25);',
    vacant:        'background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.25);',
    not_delivered: 'background: rgba(192,132,252,0.12); color: #c084fc; border: 1px solid rgba(192,132,252,0.25);',
  }[s] ?? ''
}

function fmtDate(val) {
  if (!val) return '—'
  const d = new Date(val)
  if (isNaN(d)) return val
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}


// ── Chart.js loader ────────────────────────────────────────────────────
async function loadChartJs() {
  if (ChartLib) return
  await new Promise((resolve, reject) => {
    if (window.Chart) { ChartLib = window.Chart; resolve(); return }
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { ChartLib = window.Chart; resolve() }
    s.onerror = reject
    document.head.appendChild(s)
  })
}

function destroyChart(key) {
  if (chartInstances[key]) { chartInstances[key].destroy(); delete chartInstances[key] }
}

function barChart(canvasRef, labels, datasets, key) {
  destroyChart(key)
  if (!canvasRef.value) return
  chartInstances[key] = new ChartLib(canvasRef.value.getContext('2d'), {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: '#6B96B8', font: { size: 11 } } } },
      scales: {
        x: { ticks: { color: '#6B96B8', font: { size: 10 } }, grid: { color: '#1B3558' } },
        y: { ticks: { color: '#6B96B8', font: { size: 10 }, callback: v => Number(v).toLocaleString('en-US', { notation: 'compact' }) }, grid: { color: '#1B3558' } },
      }
    }
  })
}

function lineChart(canvasRef, labels, datasets, key) {
  destroyChart(key)
  if (!canvasRef.value) return
  chartInstances[key] = new ChartLib(canvasRef.value.getContext('2d'), {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: '#6B96B8', font: { size: 11 } } } },
      scales: {
        x: { ticks: { color: '#6B96B8', font: { size: 10 } }, grid: { color: '#1B3558' } },
        y: { ticks: { color: '#6B96B8', font: { size: 10 }, callback: v => Number(v).toLocaleString('en-US', { notation: 'compact' }) }, grid: { color: '#1B3558' } },
      }
    }
  })
}

// ── Render charts per tab ──────────────────────────────────────────────
async function renderCharts() {
  await loadChartJs()
  await nextTick()
  setTimeout(() => {
    if (activeTab.value === 'contracts' && contracts.value?.monthly_trend?.length) {
      lineChart(contractChartCanvas, contracts.value.monthly_trend.map(r => r.period), [{
        label: 'Revenue', data: contracts.value.monthly_trend.map(r => r.value),
        borderColor: '#1490A8', backgroundColor: 'rgba(20,144,168,0.08)', fill: true, tension: 0.4,
      }], 'contracts')
    }
    if (activeTab.value === 'revenues' && revenues.value?.forward_12_months?.length) {
      barChart(revenueForwardCanvas, revenues.value.forward_12_months.map(r => r.period), [{
        label: 'Scheduled Revenue', data: revenues.value.forward_12_months.map(r => r.value),
        backgroundColor: 'rgba(20,144,168,0.70)', borderColor: '#1490A8', borderWidth: 1, borderRadius: 4,
      }], 'revenues_forward')
    }
    if (activeTab.value === 'collections' && collections.value?.forward_6_months?.length) {
      const fwd = collections.value.forward_6_months
      barChart(collectionForwardCanvas, fwd.map(r => r.period), [
        { label: 'Collected', data: fwd.map(r => r.collected), backgroundColor: 'rgba(52,211,153,0.70)', borderRadius: 4 },
        { label: 'Pending', data: fwd.map(r => r.pending), backgroundColor: 'rgba(250,199,117,0.70)', borderRadius: 4 },
      ], 'collections_forward')
    }
    if (activeTab.value === 'installments' && installments.value?.forward_6_months?.length) {
      barChart(installmentForwardCanvas, installments.value.forward_6_months.map(r => r.period), [{
        label: 'Due Amount', data: installments.value.forward_6_months.map(r => r.amount),
        backgroundColor: 'rgba(192,132,252,0.70)', borderColor: '#c084fc', borderWidth: 1, borderRadius: 4,
      }], 'inst_forward')
    }
    if (activeTab.value === 'expenses' && expenses.value?.monthly_trend?.length) {
      lineChart(expenseChartCanvas, expenses.value.monthly_trend.map(r => r.period), [{
        label: 'Expenses', data: expenses.value.monthly_trend.map(r => r.value),
        borderColor: '#f87171', backgroundColor: 'rgba(239,68,68,0.08)', fill: true, tension: 0.4,
      }], 'expenses_trend')
    }
    if (activeTab.value === 'profitability' && profitability.value?.monthly_noi?.length) {
      const mn = profitability.value.monthly_noi
      barChart(noiChartCanvas, mn.map(r => r.period), [
        { label: 'Revenue', data: mn.map(r => r.revenue), backgroundColor: 'rgba(52,211,153,0.70)', borderRadius: 4 },
        { label: 'Expenses', data: mn.map(r => r.expenses), backgroundColor: 'rgba(239,68,68,0.60)', borderRadius: 4 },
        { label: 'NOI', data: mn.map(r => r.noi), type: 'line', borderColor: '#FAC775', backgroundColor: 'transparent', pointBackgroundColor: '#FAC775', tension: 0.4 },
      ], 'noi_trend')
    }
  }, 100)
}

// ── Watch tab changes to render charts ────────────────────────────────
watch(activeTab, () => {
  if (!loading.value) renderCharts()
})

// ── Data load ──────────────────────────────────────────────────────────
async function loadData() {
  loading.value = true
  loadError.value = null
  Object.keys(chartInstances).forEach(k => { chartInstances[k]?.destroy(); delete chartInstances[k] })
  try {
    const { data } = await axios.get(route('company.properties.dashboard.data', props.company.id), {
      params: { date_from: dateFrom.value, date_to: dateTo.value }
    })
    portfolio.value     = data.portfolio
    contracts.value     = data.contracts
    revenues.value      = data.revenues
    collections.value   = data.collections
    installments.value  = data.installments
    expenses.value      = data.expenses
    profitability.value = data.profitability
    insights.value      = data.insights || []
  } catch(e) {
    console.error(e)
    loadError.value = e?.response?.data?.message || e?.message || 'Failed to load dashboard data.'
  } finally {
    loading.value = false
    await nextTick()
    renderCharts()
  }
}

onMounted(() => loadData())
</script>