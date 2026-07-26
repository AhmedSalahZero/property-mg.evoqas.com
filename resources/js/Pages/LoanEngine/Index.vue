<template>
  <Head title="Loan Calculator" />
  <AuthenticatedLayout>
    <div class="min-h-screen loan-page" style="background:var(--loan-bg); color:var(--loan-text);">
      <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-7">

        <!-- ── PAGE HEADER ─────────────────────────────────────────────── -->
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
              🧮 Loan Calculator
            </h1>
            <p class="text-sm mt-1" style="color:var(--loan-muted);">
              Day-count: Actual days / 360 &nbsp;·&nbsp;
              All schedule types &nbsp;·&nbsp;
              CBE corridor changes &nbsp;·&nbsp;
              Excel export
            </p>
          </div>

          <!-- Mode switcher tabs -->
          <div class="flex gap-1 rounded-xl p-1" style="background:var(--loan-card); border:1px solid var(--loan-border);">
            <button
              v-for="mode in modes" :key="mode.value"
              @click="form.schedule_type = mode.value; result = null"
              class="px-4 py-2 rounded-lg text-sm font-semibold transition-all"
              :style="form.schedule_type === mode.value
                ? 'background:' + mode.color + '; color:#fff;'
                : 'color:var(--loan-muted);'">
              {{ mode.label }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">

          <!-- ══════════════════════════════════════════════════════════
               LEFT — Input Panel
          ══════════════════════════════════════════════════════════ -->
          <div class="xl:col-span-1 space-y-4">

            <!-- ── Basic Parameters ─────────────────────────────────── -->
            <div class="rounded-xl p-5" style="background:var(--loan-card); border:1px solid var(--loan-border);">
              <h2 class="section-title mb-4">Loan Parameters</h2>
              <div class="space-y-3">

                <div>
                  <label class="field-label">Principal (EGP)</label>
                  <input v-model.number="form.principal" type="number"
                    class="calc-input w-full" placeholder="1,000,000" />
                </div>

                <div>
                  <label class="field-label">Annual Rate (%)</label>
                  <div class="relative">
                    <input v-model.number="form.annual_rate" type="number" step="0.01"
                      class="calc-input w-full" placeholder="20" style="padding-right:2rem;" />
                    <span class="input-suffix-right">%</span>
                  </div>
                </div>

                <div>
                  <label class="field-label">Term (total months)</label>
                  <input v-model.number="form.term_months" type="number"
                    class="calc-input w-full" placeholder="36" />
                </div>

                <div>
                  <label class="field-label">Disbursement Date</label>
                  <input v-model="form.disbursement_date" type="date"
                    class="calc-input w-full" />
                </div>

                <!-- Installment Interval -->
                <div>
                  <label class="field-label">Installment Interval</label>
                  <select v-model="form.installment_interval" class="calc-input w-full">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly (every 3 months)</option>
                    <option value="semi_annual">Semi-Annual (every 6 months)</option>
                  </select>
                  <p class="text-xs mt-1" style="color:var(--loan-accent);">
                    {{ derivedPeriods }} period{{ derivedPeriods !== 1 ? 's' : '' }}
                    · {{ form.term_months }} months ÷ {{ intervalMonths }} mo/period
                  </p>
                </div>

                <!-- Payment Timing — not applicable for variable -->
                <div v-if="form.schedule_type !== 'variable'">
                  <label class="field-label">Payment Timing</label>
                  <select v-model="form.payment_timing" class="calc-input w-full">
                    <option value="end">End of Period (Arrears)</option>
                    <option value="begin">Beginning of Period (Advance)</option>
                  </select>
                </div>

                <!-- Schedule Type (hidden — controlled by tabs) -->
                <div v-if="!modeValues.includes(form.schedule_type)">
                  <label class="field-label">Schedule Type</label>
                  <select v-model="form.schedule_type" class="calc-input w-full">
                    <option value="normal">Normal (Annuity / Flat Rate)</option>
                    <option value="variable">Variable (Fixed Principal + Actual Interest)</option>
                    <option value="step_up">Step-Up</option>
                    <option value="step_down">Step-Down</option>
                    <option value="grace_no_cap">Grace — Interest Paid (No Cap)</option>
                    <option value="grace_cap">Grace — Interest Capitalised</option>
                    <option value="step_up_grace">Step-Up + Grace (Capitalised)</option>
                    <option value="step_down_grace">Step-Down + Grace (Capitalised)</option>
                  </select>
                </div>

                <!-- Grace Period -->
                <div v-if="hasGrace">
                  <label class="field-label">
                    Grace Period (months)
                    <span style="color:var(--loan-muted-2); font-weight:400;">— multiple of {{ intervalMonths }}</span>
                  </label>
                  <input v-model.number="form.grace_months" type="number" min="0"
                    :step="intervalMonths" class="calc-input w-full" placeholder="6" />
                </div>
              </div>
            </div>

            <!-- ── Variable Schedule Info ──────────────────────────── -->
            <div v-if="form.schedule_type === 'variable'"
              class="rounded-xl p-4"
              style="background:var(--loan-card); border:1px solid rgba(34,211,238,0.25);">
              <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:var(--loan-accent);">
                Variable Schedule Logic
              </p>
              <div class="space-y-1.5 text-xs" style="color:var(--loan-muted);">
                <p>
                  <span style="color:var(--loan-text); font-weight:600;">Principal per period</span>
                  = Total Principal ÷ {{ derivedPeriods - gracePeriods }} amort. periods
                </p>
                <p>
                  <span style="color:var(--loan-text); font-weight:600;">Interest per period</span>
                  = Opening Balance × (Rate × Actual Days / 360)
                </p>
                <p>
                  <span style="color:var(--loan-text); font-weight:600;">Installment</span>
                  = Fixed Principal + Actual Interest
                </p>
                <p class="pt-1" style="color:var(--loan-amber);">
                  ⚡ CBE corridor changes affect only the interest — principal per period stays fixed.
                </p>
                <div v-if="form.principal > 0 && derivedPeriods > gracePeriods"
                  class="mt-2 p-2 rounded-lg" style="background:rgba(34,211,238,0.1);">
                  <p style="color:var(--loan-accent); font-weight:700;">
                    Fixed principal / period:
                    {{ fmtNum(form.principal / (derivedPeriods - gracePeriods)) }}
                  </p>
                </div>
              </div>

              <!-- Grace for variable -->
              <div class="mt-3">
                <label class="field-label">Grace Period (months)</label>
                <input v-model.number="form.grace_months" type="number" min="0"
                  :step="intervalMonths" class="calc-input w-full" placeholder="0" />
                <p class="text-xs mt-1" style="color:var(--loan-muted);">
                  During grace: interest only paid, principal deferred.
                </p>
              </div>
            </div>

            <!-- ── Step Parameters ──────────────────────────────────── -->
            <div v-if="hasSteps" class="rounded-xl p-5"
              style="background:var(--loan-card-alt); border:1px solid var(--loan-border);">
              <h2 class="section-title mb-2">Step Parameters</h2>
              <p class="text-xs mb-3" style="color:var(--loan-muted-2); line-height:1.5;">
                The <strong style="color:var(--loan-text-strong);">installment amount</strong> steps up or down
                by a fixed % at each interval. Interest rate stays constant.
              </p>
              <div class="space-y-3">
                <div>
                  <label class="field-label">Step % <span style="color:var(--loan-muted-2); font-weight:400;">(per step)</span></label>
                  <div class="relative">
                    <input v-model.number="form.step_pct" type="number"
                      step="0.5" min="0" max="50"
                      class="calc-input w-full" placeholder="5" style="padding-right:2rem;"/>
                    <span class="input-suffix-right">%</span>
                  </div>
                </div>
                <div>
                  <label class="field-label">Step Interval</label>
                  <select v-model="form.step_interval" class="calc-input w-full">
                    <option value="semi_annual">Every 6 months (Semi-Annual)</option>
                    <option value="annual">Every 12 months (Annual)</option>
                  </select>
                </div>
                <!-- Step group preview -->
                <div v-if="form.step_pct > 0 && form.term_months > 0">
                  <p class="text-xs mb-1" style="color:var(--loan-muted-3);">Installment groups:</p>
                  <div class="space-y-1 max-h-36 overflow-y-auto">
                    <div v-for="(g, i) in stepGroupPreview" :key="i"
                      class="flex justify-between text-xs px-2 py-1 rounded"
                      :style="i === 0
                        ? 'background:rgba(59,130,246,0.15); color:var(--loan-blue-light);'
                        : 'background:rgba(255,255,255,0.04); color:var(--loan-muted);'">
                      <span>Group {{ i + 1 }} (p. {{ g.from }}–{{ g.to }})</span>
                      <span class="font-semibold">× {{ g.multiplier.toFixed(4) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── CBE Corridor Changes ─────────────────────────────── -->
            <div class="rounded-xl p-5" style="background:var(--loan-card); border:1px solid var(--loan-border);">
              <div class="flex items-center justify-between mb-3">
                <h2 class="section-title">CBE Corridor Changes</h2>
                <button @click="addCorridorChange"
                  class="text-xs px-2.5 py-1 rounded-lg font-semibold"
                  style="background:var(--loan-blue-strong); color:#fff;">+ Add</button>
              </div>
              <p v-if="form.corridor_changes.length === 0" class="text-xs" style="color:var(--loan-muted-3);">
                No changes — base rate applies throughout.
              </p>
              <div v-for="(change, i) in form.corridor_changes" :key="i"
                class="rounded-lg p-3 mb-2" style="background:var(--loan-input); border:1px solid var(--loan-border);">
                <div class="flex justify-between mb-2">
                  <span class="text-xs" style="color:var(--loan-muted-2);">Change {{ i + 1 }}</span>
                  <button @click="removeCorridorChange(i)" class="text-xs" style="color:var(--loan-red);">✕</button>
                </div>
                <div class="grid grid-cols-3 gap-2">
                  <div class="col-span-3">
                    <label class="field-label-xs">Effective Date</label>
                    <input v-model="change.date" type="date" class="calc-input-sm w-full" />
                  </div>
                  <div>
                    <label class="field-label-xs">Corridor (%)</label>
                    <input v-model.number="change.corridor_rate" type="number" step="0.25" class="calc-input-sm w-full" />
                  </div>
                  <div>
                    <label class="field-label-xs">Margin (%)</label>
                    <input v-model.number="change.margin" type="number" step="0.01" class="calc-input-sm w-full" />
                  </div>
                  <div>
                    <label class="field-label-xs">Net Rate</label>
                    <div class="calc-input-sm w-full font-bold" style="color:var(--loan-accent); cursor:default;">
                      {{ ((change.corridor_rate || 0) + (change.margin || 0)).toFixed(2) }}%
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Actions ──────────────────────────────────────────── -->
            <button @click="calculate" :disabled="loading"
              class="w-full py-3 rounded-xl font-bold text-white transition disabled:opacity-50"
              style="background:var(--loan-blue-strong);">
              {{ loading ? 'Calculating…' : 'Calculate Schedule' }}
            </button>

            <button v-if="result" @click="exportExcel" :disabled="exporting"
              class="w-full py-3 rounded-xl font-semibold text-white transition disabled:opacity-50 flex items-center justify-center gap-2"
              style="background:var(--loan-green-strong);">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              {{ exporting ? 'Preparing…' : '📥 Export to Excel' }}
            </button>

            <div v-if="error" class="rounded-xl px-4 py-3 text-sm"
              style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:var(--loan-red);">
              {{ error }}
            </div>

          </div>

          <!-- ══════════════════════════════════════════════════════════
               RIGHT — Results
          ══════════════════════════════════════════════════════════ -->
          <div class="xl:col-span-3 space-y-4">

            <!-- Summary cards -->
            <div v-if="result" class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div class="summary-card">
                <div class="summary-label">Principal</div>
                <div class="summary-value" style="color:var(--loan-blue);">{{ fmt(result.summary.original_principal) }}</div>
              </div>
              <div class="summary-card">
                <div class="summary-label">
                  {{ result.params.is_variable ? 'Principal / Period' : (result.params.is_stepped ? 'Base PMT (Grp 1)' : 'PMT') }}
                  <span class="text-xs font-normal" style="color:var(--loan-blue);">({{ result.params.installment_interval }})</span>
                </div>
                <div class="summary-value" style="color:var(--loan-green);">{{ fmt(result.params.pmt_base) }}</div>
              </div>
              <div class="summary-card">
                <div class="summary-label">Total Interest</div>
                <div class="summary-value" style="color:var(--loan-amber);">{{ fmt(result.summary.total_interest) }}</div>
              </div>
              <div class="summary-card">
                <div class="summary-label">Total Paid</div>
                <div class="summary-value" style="color:var(--loan-purple);">{{ fmt(result.summary.total_installments) }}</div>
              </div>
            </div>

            <!-- Meta strip -->
            <div v-if="result" class="rounded-xl px-4 py-3 text-xs flex flex-wrap gap-x-4 gap-y-1"
              style="background:var(--loan-bg); border:1px solid var(--loan-border); color:var(--loan-muted-2);">
              <span><span style="color:var(--loan-accent);">Basis:</span> {{ result.params.day_count_basis }}</span>
              <span><span style="color:var(--loan-accent);">Interval:</span> {{ result.params.installment_interval }} ({{ result.params.months_per_period }} mo/period)</span>
              <span><span style="color:var(--loan-accent);">Periods:</span> {{ result.params.total_periods }}</span>
              <span><span style="color:var(--loan-accent);">Grace:</span> {{ result.params.grace_periods }} periods</span>
              <span v-if="!result.params.is_variable"><span style="color:var(--loan-accent);">Timing:</span> {{ result.params.payment_timing }}</span>
              <span><span style="color:var(--loan-accent);">Type:</span> {{ result.params.schedule_type }}</span>
              <span v-if="result.params.is_variable" style="color:var(--loan-accent); font-weight:700;">
                ↳ Variable: fixed principal {{ fmt(result.params.principal_per_period) }} / period · interest varies by actual days & rate
              </span>
              <span v-if="result.params.is_stepped" style="color:var(--loan-green); font-weight:700;">
                ↳ Step: {{ (result.params.step_pct * 100).toFixed(1) }}%
                every {{ result.params.step_interval_periods }} period(s)
                · Base PMT = {{ fmt(result.params.pmt_base) }}
              </span>
              <span v-if="isExpanded" style="color:var(--loan-orange); font-weight:700;">
                ↳ Expanded: accrual rows shown between payment rows
              </span>
            </div>

            <!-- ── SCHEDULE TABLE ─────────────────────────────────────── -->
            <div v-if="result" class="rounded-xl overflow-hidden" style="background:var(--loan-bg); border:1px solid var(--loan-border);">

              <!-- Table header bar with export button -->
              <div class="flex items-center justify-between px-4 py-2.5" style="background:var(--loan-table-bar); border-bottom:1px solid var(--loan-border);">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold uppercase tracking-widest" style="color:var(--loan-table-bar-text);">Amortisation Schedule</span>
                  <span class="text-xs px-2 py-0.5 rounded-full font-semibold" style="background:rgba(34,211,238,0.1); color:var(--loan-table-bar-text); border:1px solid rgba(34,211,238,0.2);">
                    {{ result.schedule.length }} rows
                  </span>
                </div>
                <button @click="exportExcel" :disabled="exporting"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all disabled:opacity-50"
                  style="background:var(--loan-green-strong); border:1px solid rgba(16,185,129,0.35); color:#6ee7b7;">
                  <svg v-if="!exporting" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  {{ exporting ? 'Preparing…' : 'Export to Excel' }}
                </button>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-xs" style="min-width:max-content;">
                  <thead>
                    <tr style="background:var(--loan-table-bar); border-bottom:1px solid var(--loan-border);">
                      <th class="th">#</th>
                      <th class="th">Date</th>
                      <th class="th text-right">Days</th>
                      <th class="th text-right">Annual Rate</th>
                      <th class="th text-right">Opening Balance</th>

                      <!-- Monthly non-expanded: single Interest column -->
                      <template v-if="!isExpanded">
                        <th class="th text-right">Interest</th>
                      </template>
                      <!-- Expanded quarterly/semi-annual: two columns -->
                      <template v-else>
                        <th class="th text-right">Monthly Interest</th>
                        <th class="th text-right">Interest Payment</th>
                      </template>

                      <th class="th text-right">Principal</th>
                      <th class="th text-right">Installment</th>
                      <th class="th text-right">Closing Balance</th>
                      <th class="th">Note</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, idx) in result.schedule" :key="idx"
                      :class="rowClass(row)"
                      style="border-bottom:1px solid var(--loan-border-input);">

                      <td class="td" style="color:var(--loan-muted-3);">
                        <span v-if="row.row_type === 'disbursement'" style="color:var(--loan-blue); font-weight:700;">D</span>
                        <span v-else>{{ row.month_num }}</span>
                      </td>
                      <td class="td font-medium">{{ row.period_label }}</td>
                      <td class="td text-right" style="color:var(--loan-muted-3);">{{ row.days_in_period || '—' }}</td>
                      <td class="td text-right" style="color:var(--loan-blue-light);">
                        {{ row.row_type === 'disbursement' ? '—' : row.annual_rate }}
                      </td>
                      <td class="td text-right">{{ fmt(row.opening_balance) }}</td>

                      <!-- Monthly interest -->
                      <template v-if="!isExpanded">
                        <td class="td text-right" style="color:var(--loan-amber-light);">
                          {{ row.row_type === 'disbursement' ? '—' : fmt(row.interest) }}
                        </td>
                      </template>

                      <!-- Expanded: accrued + payment -->
                      <template v-else>
                        <td class="td text-right"
                          :style="row.row_type === 'accrual' ? 'color:var(--loan-orange);' : 'color:var(--loan-muted-3);'">
                          {{ row.row_type === 'disbursement' ? '—' : fmt(row.monthly_interest) }}
                        </td>
                        <td class="td text-right" style="color:var(--loan-amber-light);">
                          {{ (row.row_type === 'payment' && !row.is_grace) ? fmt(row.interest_payment) : (row.row_type === 'disbursement' ? '—' : '0.00') }}
                        </td>
                      </template>

                      <td class="td text-right" style="color:var(--loan-green-light);">
                        {{ row.row_type === 'disbursement' ? '—' : fmt(row.principal) }}
                      </td>
                      <td class="td text-right font-semibold"
                        :style="row.row_type === 'disbursement' ? 'color:var(--loan-blue-light);' : (row.is_adjusted ? 'color:var(--loan-amber);' : '')">
                        {{ row.row_type === 'disbursement' ? fmt(row.disbursement) : fmt(row.installment) }}
                      </td>
                      <td class="td text-right"
                        :style="row.closing_balance < 1 && row.closing_balance >= 0 ? 'color:var(--loan-green); font-weight:700;' : ''">
                        {{ fmt(row.closing_balance) }}
                      </td>
                      <td class="td" :style="noteStyle(row)">{{ row.note }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr style="background:var(--loan-table-bar); border-top:2px solid var(--loan-border); font-weight:700; font-size:0.75rem;">
                      <td class="td" colspan="4" style="color:#fff;">TOTALS</td>
                      <td class="td"></td>
                      <template v-if="!isExpanded">
                        <td class="td text-right" style="color:var(--loan-amber-light);">{{ fmt(result.summary.total_interest) }}</td>
                      </template>
                      <template v-else>
                        <td class="td"></td>
                        <td class="td text-right" style="color:var(--loan-amber-light);">{{ fmt(result.summary.total_interest) }}</td>
                      </template>
                      <td class="td text-right" style="color:var(--loan-green-light);">{{ fmt(result.summary.total_principal_paid) }}</td>
                      <td class="td text-right" style="color:#fff;">{{ fmt(result.summary.total_installments) }}</td>
                      <td class="td"></td>
                      <td class="td"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="!result && !loading"
              class="rounded-xl p-16 text-center"
              style="background:var(--loan-card); border:1px solid var(--loan-border);">
              <div class="text-5xl mb-4">📊</div>
              <p style="color:var(--loan-muted);">Select a schedule type and click <strong style="color:var(--loan-text-strong);">Calculate Schedule</strong></p>
              <p class="text-sm mt-2" style="color:var(--loan-muted-3);">The full amortisation table will appear here</p>

              <!-- Quick type reference -->
              <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-3 text-left max-w-2xl mx-auto">
                <div v-for="t in typeReference" :key="t.value"
                  class="rounded-lg p-3 cursor-pointer transition-all"
                  style="background:var(--loan-input); border:1px solid var(--loan-border-input);"
                  @click="form.schedule_type = t.value; result = null">
                  <p class="text-xs font-bold mb-1" :style="`color:${t.color};`">{{ t.label }}</p>
                  <p class="text-xs" style="color:var(--loan-muted-3);">{{ t.desc }}</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

// ── Quick mode tabs ──────────────────────────────────────────────────
const modes = [
  { value: 'normal',   label: 'Annuity',   color: '#1d4ed8' },
  { value: 'variable', label: 'Variable',  color: '#0e7490' },
  { value: 'step_up',  label: 'Step-Up',   color: '#065f46' },
  { value: 'step_down',label: 'Step-Down', color: '#7c3aed' },
  { value: 'grace_cap',label: 'Grace',     color: '#92400e' },
]
const modeValues = modes.map(m => m.value)

// ── Type reference cards (empty state) ──────────────────────────────
const typeReference = [
  { value: 'normal',       label: 'Annuity',             color: '#60a5fa', desc: 'Fixed installment, PMT solved' },
  { value: 'variable',     label: 'Variable',            color: '#22d3ee', desc: 'Fixed principal + actual interest' },
  { value: 'step_up',      label: 'Step-Up',             color: '#34d399', desc: 'Installment grows each interval' },
  { value: 'step_down',    label: 'Step-Down',           color: '#c084fc', desc: 'Installment shrinks each interval' },
  { value: 'grace_no_cap', label: 'Grace (No Cap)',      color: '#fbbf24', desc: 'Interest paid, principal deferred' },
  { value: 'grace_cap',    label: 'Grace (Capitalised)', color: '#fb923c', desc: 'Interest added to balance' },
  { value: 'step_up_grace',   label: 'Step-Up + Grace', color: '#86efac', desc: 'Grace then stepped-up PMT' },
  { value: 'step_down_grace', label: 'Step-Down + Grace',color: '#f9a8d4', desc: 'Grace then stepped-down PMT' },
]

// ── Form State ───────────────────────────────────────────────────────
const form = ref({
  principal:            1000000,
  annual_rate:          20,
  term_months:          36,
  disbursement_date:    '2026-01-01',
  payment_timing:       'end',
  installment_interval: 'monthly',
  schedule_type:        'normal',
  grace_months:         0,
  step_pct:             5,
  step_interval:        'annual',
  corridor_changes:     [],
})

const result    = ref(null)
const loading   = ref(false)
const exporting = ref(false)
const error     = ref(null)

// ── Derived ──────────────────────────────────────────────────────────
const INTERVAL_MONTHS = { monthly: 1, quarterly: 3, semi_annual: 6 }

const intervalMonths = computed(() => INTERVAL_MONTHS[form.value.installment_interval] || 1)
const derivedPeriods = computed(() => Math.ceil(form.value.term_months / intervalMonths.value))
const isExpanded     = computed(() => result.value?.params?.is_expanded === true)

const gracePeriods   = computed(() =>
  Math.floor((form.value.grace_months || 0) / intervalMonths.value)
)

const hasGrace = computed(() =>
  ['grace_no_cap','grace_cap','step_up_grace','step_down_grace'].includes(form.value.schedule_type)
)
const hasSteps = computed(() =>
  ['step_up','step_down','step_up_grace','step_down_grace'].includes(form.value.schedule_type)
)

// Step group preview
const stepGroupPreview = computed(() => {
  if (!hasSteps.value || !form.value.step_pct || !form.value.term_months) return []
  const STEP_INTERVAL_MONTHS = { semi_annual: 6, annual: 12 }
  const stepIntervalMonths   = STEP_INTERVAL_MONTHS[form.value.step_interval] || 12
  const mpp                  = intervalMonths.value
  const stepIntervalPeriods  = Math.max(1, Math.round(stepIntervalMonths / mpp))
  const grace                = gracePeriods.value
  const amort                = derivedPeriods.value - grace
  const isUp                 = ['step_up','step_up_grace'].includes(form.value.schedule_type)
  const pct                  = form.value.step_pct / 100

  const groups = []
  let i = 0
  while (i < amort) {
    const groupIdx   = Math.floor(i / stepIntervalPeriods)
    const multiplier = Math.pow(1 + (isUp ? 1 : -1) * pct, groupIdx)
    const from       = grace + i + 1
    const to         = Math.min(grace + i + stepIntervalPeriods, derivedPeriods.value)
    groups.push({ from, to, multiplier })
    i += stepIntervalPeriods
  }
  return groups
})

// ── Corridor Changes ─────────────────────────────────────────────────
function addCorridorChange()     { form.value.corridor_changes.push({ date: '', corridor_rate: 0, margin: 0 }) }
function removeCorridorChange(i) { form.value.corridor_changes.splice(i, 1) }

// ── Payload ──────────────────────────────────────────────────────────
function buildPayload() {
  const p = { ...form.value }
  if (!hasGrace.value && form.value.schedule_type !== 'variable') p.grace_months = 0
  if (!hasSteps.value) { p.step_pct = 0; p.step_interval = 'annual' }
  if (form.value.schedule_type === 'variable') p.payment_timing = 'end' // variable always end
  return p
}

// ── Calculate ────────────────────────────────────────────────────────
async function calculate() {
  loading.value = true
  error.value   = null
  result.value  = null

  try {
    const res = await axios.post('/loan-engine/calculate', buildPayload())
    if (res.data.success) {
      result.value = res.data.data
    } else {
      error.value = res.data.error || 'Calculation failed'
    }
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e.response?.data?.error || e.message || 'Server error')
  } finally {
    loading.value = false
  }
}

// ── Export ───────────────────────────────────────────────────────────
async function exportExcel() {
  exporting.value = true
  error.value     = null
  try {
    const res = await axios.post('/loan-engine/export', buildPayload(), { responseType: 'blob' })
    const blob = new Blob([res.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    const url  = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href  = url
    const cd   = res.headers['content-disposition'] || ''
    const m    = cd.match(/filename="?([^"]+)"?/)
    link.download = m ? m[1] : 'LoanSchedule.xlsx'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = 'Export failed: ' + (e.message || 'Unknown error')
  } finally {
    exporting.value = false
  }
}

// ── Row Styling ───────────────────────────────────────────────────────
function rowClass(row) {
  if (row.row_type === 'disbursement') return 'disbursement-row'
  if (row.row_type === 'accrual')      return 'accrual-row'
  if (row.is_grace)                    return 'grace-row'
  if (row.is_last)                     return 'last-row'
  return 'normal-row'
}

function noteStyle(row) {
  if (row.row_type === 'disbursement') return 'color:var(--loan-blue); font-weight:600;'
  if (row.row_type === 'accrual')      return 'color:var(--loan-orange); font-style:italic;'
  if (row.is_grace)                    return 'color:var(--loan-amber);'
  if (row.is_adjusted)                 return 'color:var(--loan-orange);'
  return 'color:var(--loan-muted-3);'
}

// ── Formatter ─────────────────────────────────────────────────────────
function fmt(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function fmtNum(val) {
  return Number(val).toLocaleString('en-EG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════════
   Theme variables (confirmed session) — this page previously hardcoded
   every color directly as literal hex values in style="..." attributes,
   so it never responded to light/dark mode at all — see the Loan
   Calculator light-mode conversion session for the full diagnosis.
   Dark defaults below are copied EXACTLY from the previous hardcoded
   values, so dark mode looks 100% identical to before. Light overrides
   are new. modes[] / typeReference[] in <script> (the tab colors and
   the empty-state legend colors) are deliberately left as plain literal
   hex — a small, self-contained categorical color-coding scheme that's
   meant to stay the same across both themes, like a chart legend.
══════════════════════════════════════════════════════════════════ */
.loan-page {
  --loan-bg:            #0C1829;
  --loan-card:          #112240;
  --loan-card-alt:      #1e293b;
  --loan-border:        #334155;
  --loan-input:         #0f172a;
  --loan-input-deep:    #0a1020;
  --loan-table-bar:     #0a1020;
  --loan-border-input:  #1e3a5a;

  --loan-text:          #e2e8f0;
  --loan-text-soft:     #cbd5e1;
  --loan-text-strong:   #ffffff;
  --loan-muted:         #94a3b8;
  --loan-muted-2:       #64748b;
  --loan-muted-3:       #475569;

  --loan-accent:        #22d3ee;
  --loan-table-bar-text: #22d3ee;
  --loan-blue:          #60a5fa;
  --loan-blue-light:    #93c5fd;
  --loan-blue-strong:   #1d4ed8;
  --loan-green:         #34d399;
  --loan-green-light:   #86efac;
  --loan-green-strong:  #065f46;
  --loan-amber:         #facc15;
  --loan-amber-light:   #fde68a;
  --loan-orange:        #fb923c;
  --loan-red:           #f87171;
  --loan-purple:        #c084fc;
}

[data-theme="light"] .loan-page {
  --loan-bg:            #F0F8FD;
  --loan-card:          #FFFFFF;
  --loan-card-alt:      #F1F6FB;
  --loan-border:        #D7E3EC;
  --loan-input:         #F4F9FC;
  --loan-input-deep:    #E8F1F8;
  /* Table header/footer bars keep a solid dark-Navy fill (not a pale
     tint) even in light mode, same reasoning as the app-wide colored
     table headers — the white/cyan text sitting on these bars needs a
     dark backdrop to stay legible regardless of theme. */
  --loan-table-bar:     #0C447C;
  --loan-border-input:  #C7DAEA;

  /* Primary text becomes Navy in light mode, matching VERO's app-wide
     convention (--fv-text-primary). */
  --loan-text:          #0C447C;
  --loan-text-soft:     #2F4F6B;
  /* "Strong/white" emphasis text needs to flip to a DARK tone in light
     mode — it's white-on-dark-card in dark mode, so on a white card it
     has to become dark to stay legible at all. */
  --loan-text-strong:   #0C2C4C;
  --loan-muted:         #5A7E9A;
  --loan-muted-2:       #6B8CA6;
  --loan-muted-3:       #7B98AF;

  --loan-accent:        #0C7A94;
  --loan-blue:          #1D5FC2;
  --loan-blue-light:    #2563AF;
  --loan-blue-strong:   #1447B8;
  --loan-green:         #0C7A4C;
  --loan-green-light:   #15803D;
  --loan-green-strong:  #065f46;
  --loan-amber:         #A8720B;
  --loan-amber-light:   #92650A;
  --loan-orange:        #C2540E;
  --loan-red:           #DC2626;
  --loan-purple:        #7C3AED;
}

/* ── Labels ─────────────────────────────────────────────────────────── */
.section-title {
  font-size: 0.65rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: var(--loan-accent);
}
.field-label {
  display: block; font-size: 0.68rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.07em;
  color: var(--loan-muted); margin-bottom: 0.3rem;
}
.field-label-xs {
  display: block; font-size: 0.62rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--loan-muted-2); margin-bottom: 0.25rem;
}

/* ── Inputs ─────────────────────────────────────────────────────────── */
.calc-input {
  background: var(--loan-input);
  border: 1px solid var(--loan-border-input);
  color: var(--loan-text);
  border-radius: 0.45rem;
  padding: 0.45rem 0.7rem;
  font-size: 0.85rem;
  transition: border-color 0.15s;
}
.calc-input:focus {
  outline: none;
  border-color: var(--loan-accent);
  box-shadow: 0 0 0 2px rgba(34,211,238,0.12);
}
.calc-input::placeholder { color: var(--loan-border-input); }
select.calc-input option { background: var(--loan-input); color: var(--loan-text); }

.calc-input-sm {
  background: var(--loan-input-deep);
  border: 1px solid var(--loan-border-input);
  color: var(--loan-text);
  border-radius: 0.35rem;
  padding: 0.32rem 0.55rem;
  font-size: 0.78rem;
  transition: border-color 0.15s;
}
.calc-input-sm:focus {
  outline: none;
  border-color: var(--loan-accent);
}
.calc-input-sm option { background: var(--loan-input-deep); color: var(--loan-text); }

.input-suffix-right {
  position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%);
  font-size: 0.75rem; color: var(--loan-accent); font-weight: 700; pointer-events: none;
}

/* ── Summary cards ───────────────────────────────────────────────────── */
.summary-card {
  background: var(--loan-card);
  border: 1px solid var(--loan-border);
  border-radius: 0.75rem;
  padding: 1rem;
}
.summary-label { font-size: 0.68rem; color: var(--loan-text-strong); margin-bottom: 0.3rem; }
.summary-value { font-size: 1.1rem; font-weight: 700; }

/* ── Table ───────────────────────────────────────────────────────────── */
.th {
  padding: 0.6rem 0.75rem;
  font-size: 0.65rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.07em;
  color: var(--loan-table-bar-text); white-space: nowrap;
  text-align: left;
}
.td {
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  white-space: nowrap;
  color: var(--loan-text-soft);
}

/* ── Row types ────────────────────────────────────────────────────────── */
.disbursement-row { background: rgba(59,130,246,0.07) !important; }
.accrual-row      { background: rgba(251,146,60,0.05) !important; }
.grace-row        { background: rgba(234,179,8,0.07)  !important; }
.last-row         { background: rgba(16,185,129,0.06) !important; }
.normal-row:hover { background: rgba(255,255,255,0.02); }

/* ── Remove spinners ─────────────────────────────────────────────────── */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
input[type="number"] { -moz-appearance: textfield; }

/* ── Date picker dark theme ──────────────────────────────────────────── */
input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.5); }
</style>