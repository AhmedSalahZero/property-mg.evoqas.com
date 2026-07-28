<template>
  <div style="min-height:100vh;background:#0C1829;color:#e2e8f0;font-family:'Inter',sans-serif;padding:32px 16px;">

    <!-- Header -->
    <div style="max-width:960px;margin:0 auto 32px;">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
          <p style="color:#1490A8;font-size:12px;font-weight:600;letter-spacing:1px;margin:0 0 4px;">VERO PROPERTY MANAGEMENT</p>
          <h1 style="font-size:24px;font-weight:800;margin:0;color:#f8fafc;">Keep or Sell Analysis</h1>
          <p style="color:#64748b;font-size:13px;margin:4px 0 0;">{{ analysis.company_name }} · {{ analysis.created_at }}</p>
        </div>
        <div style="text-align:right;">
          <p style="color:#64748b;font-size:12px;margin:0;">{{ analysis.snapshot_label || 'Investment Decision Report' }}</p>
          <p style="color:#94a3b8;font-size:12px;margin:4px 0 0;">
            {{ analysis.property_name }}<span v-if="analysis.unit_name"> / {{ analysis.unit_name }}</span>
          </p>
        </div>
      </div>
    </div>

    <div style="max-width:960px;margin:0 auto;display:flex;flex-direction:column;gap:24px;">

      <!-- Recommendation Banner -->
      <div :style="bannerStyle">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
          <span style="font-size:36px;">{{ recIcon }}</span>
          <div>
            <p style="font-size:12px;font-weight:600;letter-spacing:.5px;margin:0;opacity:.8;">RECOMMENDATION</p>
            <p style="font-size:24px;font-weight:800;margin:4px 0 0;" :style="{color: recColor}">{{ recLabel }}</p>
          </div>
          <div style="margin-left:auto;text-align:right;">
            <p style="font-size:12px;opacity:.8;margin:0;">NPV Gap vs Sale Proceeds</p>
            <p style="font-size:20px;font-weight:700;margin:4px 0 0;" :style="{color: npvGap >= 0 ? '#4ade80' : '#f87171'}">
              {{ npvGap >= 0 ? '+' : '' }}{{ fmt(npvGap) }} {{ analysis.currency }}
            </p>
          </div>
        </div>
        <!-- Flags -->
        <div v-if="analysis.auto_flags?.length" style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
          <div v-for="(flag, i) in analysis.auto_flags" :key="i"
            style="display:flex;gap:8px;background:rgba(0,0,0,0.2);padding:10px 14px;border-radius:6px;">
            <span style="color:#fbbf24;">⚠</span>
            <span style="font-size:13px;opacity:.9;">{{ flag }}</span>
          </div>
        </div>
      </div>

      <!-- KPI Row -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
        <div v-for="kpi in kpis" :key="kpi.label" :style="cardStyle">
          <p style="font-size:11px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 6px;">{{ kpi.label }}</p>
          <p style="font-size:20px;font-weight:800;margin:0;" :style="{color: kpi.color ?? '#f8fafc'}">{{ kpi.value }}</p>
          <p v-if="kpi.sub" style="font-size:11px;color:#64748b;margin:4px 0 0;">{{ kpi.sub }}</p>
        </div>
      </div>

      <!-- Installment warning — only shown when installments were deducted -->
      <div v-if="totalInstallments > 0"
        style="display:flex;align-items:center;gap:12px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:14px 18px;">
        <span style="font-size:18px;flex-shrink:0;">⚠️</span>
        <div>
          <span style="font-size:13px;font-weight:700;color:#f87171;">Installment Payments Deducted from DCF: {{ fmt(totalInstallments) }} {{ analysis.currency }}</span>
          <span style="font-size:12px;color:#94a3b8;margin-left:8px;">Remaining purchase installments due within the holding period are included as cash outflows per year.</span>
        </div>
      </div>

      <!-- Assumptions -->
      <div :style="cardStyle">
        <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 14px;">Analysis Assumptions</p>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;font-size:13px;">
          <div><span style="color:#64748b;">Holding Period</span><br><strong>{{ analysis.holding_years }} years</strong></div>
          <div><span style="color:#64748b;">Evaluation Date</span><br><strong>{{ analysis.evaluation_month || '—' }}</strong></div>
          <div><span style="color:#64748b;">Discount Rate (WACC)</span><br><strong>{{ analysis.discount_rate_pct }}%</strong></div>
          <div><span style="color:#64748b;">Rent Growth (post-contract)</span><br><strong>{{ analysis.rent_growth_rate_pct }}%</strong></div>
          <div><span style="color:#64748b;">Other OpEx</span><br><strong>{{ analysis.other_opex_pct }}% of revenue</strong></div>
          <div><span style="color:#64748b;">Corporate Tax</span><br><strong>{{ analysis.corporate_tax_rate_pct }}%</strong></div>
          <div><span style="color:#64748b;">Selling Costs</span><br><strong>{{ analysis.selling_costs_pct }}% of market value</strong></div>
          <div><span style="color:#64748b;">Current Market Value</span><br><strong>{{ fmt(analysis.market_value) }} {{ analysis.currency }}</strong></div>
          <div><span style="color:#64748b;">Net Sale Proceeds</span><br><strong>{{ fmt(analysis.net_sale_proceeds) }} {{ analysis.currency }}</strong></div>
          <!-- Exit method — shown dynamically based on what was actually used -->
          <div>
            <span style="color:#64748b;">Exit Value Method</span><br>
            <strong>{{ exitMethodLabel }}</strong>
          </div>
          <div v-if="analysis.appreciation_rate_pct != null && analysis.appreciation_rate_pct > 0">
            <span style="color:#64748b;">Market Appreciation Rate</span><br>
            <strong>{{ analysis.appreciation_rate_pct }}% per year</strong>
          </div>
          <div v-if="analysis.exit_cap_rate_pct != null && analysis.exit_cap_rate_pct > 0">
            <span style="color:#64748b;">Exit Cap Rate</span><br>
            <strong>{{ analysis.exit_cap_rate_pct }}%</strong>
          </div>
        </div>
      </div>

      <!-- Cash Flow Table -->
      <div :style="cardStyle" style="overflow-x:auto;">
        <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 14px;">Year-by-Year Cash Flow</p>
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
          <thead>
            <tr style="background:rgba(255,255,255,0.04);">
              <th style="padding:8px 10px;text-align:left;color:#64748b;font-size:11px;">Year</th>
              <th style="padding:8px 10px;text-align:left;color:#64748b;font-size:11px;">Period</th>
              <th style="padding:8px 10px;text-align:left;color:#64748b;font-size:11px;">Source</th>
              <th style="padding:8px 10px;text-align:right;color:#64748b;font-size:11px;">Gross Revenue</th>
              <th style="padding:8px 10px;text-align:right;color:#64748b;font-size:11px;">Expenses</th>
              <th style="padding:8px 10px;text-align:right;color:#64748b;font-size:11px;">Other OpEx</th>
              <th style="padding:8px 10px;text-align:right;color:#64748b;font-size:11px;">Tax</th>
              <th style="padding:8px 10px;text-align:right;color:#f87171;font-size:11px;">Installments</th>
              <th style="padding:8px 10px;text-align:right;color:#64748b;font-size:11px;">Net CF</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in analysis.annual_cashflows" :key="row.year" style="border-top:1px solid rgba(255,255,255,0.06);">
              <td style="padding:8px 10px;font-weight:700;">Y{{ row.year }}</td>
              <td style="padding:8px 10px;color:#64748b;white-space:nowrap;">{{ row.period_label }}</td>
              <td style="padding:8px 10px;">
                <span :style="{color: row.source === 'contracted' ? '#4ade80' : (row.source === 'partial' ? '#38bdf8' : '#fbbf24'), fontSize:'11px'}">
                  {{ row.source === 'contracted' ? '✓ Contracted' : (row.source === 'partial' ? `~ Partial (${row.contracted_months}/12)` : '~ Projected') }}
                </span>
              </td>
              <td style="padding:8px 10px;text-align:right;">{{ fmt(row.gross_revenue) }}</td>
              <td style="padding:8px 10px;text-align:right;color:#f87171;">{{ fmt(row.direct_expenses) }}</td>
              <td style="padding:8px 10px;text-align:right;color:#fb923c;">{{ fmt(row.other_opex) }}</td>
              <td style="padding:8px 10px;text-align:right;color:#f87171;">{{ fmt(row.corporate_tax) }}</td>
              <td style="padding:8px 10px;text-align:right;">
                <span v-if="row.installment_payment > 0" style="color:#f87171;font-weight:700;">({{ fmt(row.installment_payment) }})</span>
                <span v-else style="color:#334155;">—</span>
              </td>
              <td style="padding:8px 10px;text-align:right;font-weight:700;" :style="{color: row.net_cf >= 0 ? '#4ade80' : '#f87171'}">{{ fmt(row.net_cf) }}</td>
            </tr>
            <!-- Exit Value row — label reflects actual method used -->
            <tr style="border-top:2px solid rgba(255,255,255,0.1);background:rgba(20,144,168,0.1);">
              <td colspan="3" style="padding:8px 10px;font-weight:700;color:#1490A8;">
                Exit Value
                <span style="font-size:10px;font-weight:600;padding:1px 7px;border-radius:4px;margin-left:6px;"
                  :style="exitMethodNote === 'market_appreciation'
                    ? 'background:rgba(20,144,168,0.25);color:#1490A8;'
                    : 'background:rgba(186,117,23,0.25);color:#BA7517;'">
                  {{ exitMethodNote === 'market_appreciation' ? 'Market Appreciation' : 'Income Cap Rate' }}
                </span>
              </td>
              <td colspan="5"></td>
              <td style="padding:8px 10px;text-align:right;font-weight:800;color:#BA7517;">{{ fmt(analysis.terminal_value) }} {{ analysis.currency }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Analyst Recommendation -->
      <div v-if="analysis.analyst_recommendation" :style="cardStyle">
        <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 10px;">Analyst Recommendation</p>
        <p style="font-size:14px;line-height:1.7;color:#e2e8f0;margin:0;white-space:pre-wrap;">{{ analysis.analyst_recommendation }}</p>
      </div>

      <!-- Footer -->
      <div style="text-align:center;padding:20px 0;color:#334155;font-size:12px;">
        Generated by VERO Property Management · SQUAD Financial & Business Consulting · Confidential
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  analysis: { type: Object, required: true }
})

const fmt = (v) => {
  if (v === null || v === undefined) return '—'
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

const npvGap = computed(() => (props.analysis.npv_hold ?? 0) - (props.analysis.net_sale_proceeds ?? 0))

// Total installments deducted — sum from annual_cashflows
const totalInstallments = computed(() =>
  (props.analysis.annual_cashflows ?? []).reduce((sum, r) => sum + (r.installment_payment ?? 0), 0)
)

// Exit method note — what actually drove the terminal value
const exitMethodNote = computed(() => props.analysis.terminal_value_note ?? 'market_appreciation')

// Human-readable label for the assumptions section
const exitMethodLabel = computed(() => {
  const m = props.analysis.exit_value_method
  if (m === 'cap_rate')    return 'Income Cap Rate'
  if (m === 'higher_of')   return 'Higher of Appreciation & Cap Rate'
  return 'Market Appreciation'   // default for old snapshots
})

const recColor = computed(() => ({ keep: '#4ade80', sell: '#f87171', neutral: '#fbbf24' }[props.analysis.auto_recommendation] ?? '#94a3b8'))
const recLabel = computed(() => ({ keep: 'KEEP — Hold the Asset', sell: 'SELL — Dispose the Asset', neutral: 'NEUTRAL — Decision at Discretion' }[props.analysis.auto_recommendation] ?? '—'))
const recIcon  = computed(() => ({ keep: '🏠', sell: '💰', neutral: '⚖️' }[props.analysis.auto_recommendation] ?? '—'))

const bannerStyle = computed(() => {
  const c = recColor.value
  return `background: rgba(0,0,0,0.3); border:1px solid ${c}44; border-left:4px solid ${c}; border-radius:8px; padding:20px;`
})

const cardStyle = `background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:20px;`

const kpis = computed(() => [
  {
    label: 'NPV of Hold',
    value: fmt(props.analysis.npv_hold) + ' ' + props.analysis.currency,
    color: (props.analysis.npv_hold ?? 0) > (props.analysis.net_sale_proceeds ?? 0) ? '#4ade80' : '#f87171',
  },
  {
    label: 'Net Sale Proceeds',
    value: fmt(props.analysis.net_sale_proceeds) + ' ' + props.analysis.currency,
    color: '#f8fafc',
  },
  {
    label: 'IRR of Hold',
    value: props.analysis.irr_hold !== null ? Number(props.analysis.irr_hold).toFixed(1) + '%' : '—',
    color: '#BA7517',
  },
  {
    label: 'Exit Value',
    value: fmt(props.analysis.terminal_value) + ' ' + props.analysis.currency,
    color: '#f8fafc',
    sub: exitMethodNote.value === 'market_appreciation' ? 'via Market Appreciation' : 'via Income Cap Rate',
  },
])
</script>