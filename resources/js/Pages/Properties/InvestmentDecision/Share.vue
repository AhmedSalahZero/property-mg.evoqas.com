<template>
  <div style="min-height:100vh;background:#0C1829;color:#e2e8f0;font-family:'Inter',sans-serif;padding:32px 16px;">

    <!-- Header -->
    <div style="max-width:1100px;margin:0 auto 32px;">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
          <p style="color:#1490A8;font-size:12px;font-weight:600;letter-spacing:1px;margin:0 0 4px;">VERO PROPERTY MANAGEMENT</p>
          <h1 style="font-size:24px;font-weight:800;margin:0;color:#f8fafc;">Investment Decision — Feasibility Study</h1>
          <p style="color:#64748b;font-size:13px;margin:4px 0 0;">{{ analysis.company_name }} · {{ analysis.created_at }}</p>
        </div>
        <div style="text-align:right;">
          <p style="color:#64748b;font-size:12px;margin:0;">{{ analysis.snapshot_label || 'Feasibility Snapshot' }}</p>
          <p style="color:#94a3b8;font-size:12px;margin:4px 0 0;">{{ analysis.prospect_name }}</p>
        </div>
      </div>
    </div>

    <div style="max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:24px;">

      <!-- Funding Path banner -->
      <div :style="cardStyle" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
          <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 4px;">Funding Path</p>
          <p style="font-size:18px;font-weight:800;margin:0;color:#f8fafc;">{{ fundingPathLabel }}</p>
        </div>
        <div style="text-align:right;">
          <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 4px;">Exit Year</p>
          <p style="font-size:18px;font-weight:800;margin:0;color:#f8fafc;">Year {{ analysis.exit_year }}</p>
        </div>
        <div style="text-align:right;">
          <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 4px;">Discount Rate</p>
          <p style="font-size:18px;font-weight:800;margin:0;color:#f8fafc;">{{ analysis.discount_rate_pct }}%</p>
        </div>
        <div style="text-align:right;">
          <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 4px;">Rent Collection</p>
          <p style="font-size:18px;font-weight:800;margin:0;color:#f8fafc;">{{ collectionLabel }}</p>
        </div>
      </div>

      <!-- Three Scenarios, side by side -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
        <div v-for="key in ['conservative','base','optimistic']" :key="key"
             :style="key === bestNpvScenario ? cardStyle + 'border-color:#BA7517;border-width:2px;' : cardStyle">
          <p style="font-size:13px;font-weight:700;margin:0 0 12px;" :style="{color: scenarioColors[key]}">
            {{ scenarioLabels[key] }}
            <span v-if="key === bestNpvScenario" style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px;background:rgba(186,117,23,0.25);color:#BA7517;margin-left:6px;">BEST NPV</span>
          </p>
          <div v-if="scenarioOf(key).computation_warning" style="padding:10px;border-radius:6px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#f87171;font-size:12px;">
            ⚠ {{ scenarioOf(key).computation_warning }}
          </div>
          <template v-else>
            <p style="font-size:11px;color:#64748b;margin:0;">NPV</p>
            <p style="font-size:22px;font-weight:800;margin:2px 0 10px;" :style="{color: scenarioOf(key).npv >= 0 ? '#4ade80' : '#f87171'}">
              {{ fmt(scenarioOf(key).npv) }} {{ analysis.currency }}
            </p>
            <p style="font-size:11px;color:#64748b;margin:0;">IRR</p>
            <p style="font-size:16px;font-weight:700;margin:2px 0 12px;color:#f8fafc;">{{ scenarioOf(key).irr != null ? Number(scenarioOf(key).irr).toFixed(1) + '%' : 'N/A' }}</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px;">
              <div><span style="color:#64748b;">Year-0 Outflow</span><br><strong>{{ fmt(scenarioOf(key).year0_equity_outflow) }}</strong></div>
              <div><span style="color:#64748b;">Exit Value</span><br><strong>{{ fmt(scenarioOf(key).terminal_value) }}</strong></div>
              <div><span style="color:#64748b;">Net Sale Proceeds</span><br><strong>{{ fmt(scenarioOf(key).net_sale_proceeds) }}</strong></div>
              <div v-if="scenarioOf(key).contractor_fee_at_exit > 0"><span style="color:#64748b;">Contractor Fee</span><br><strong style="color:#f87171;">{{ fmt(scenarioOf(key).contractor_fee_at_exit) }}</strong></div>
            </div>
          </template>
        </div>
      </div>

      <!-- Portfolio Impact -->
      <div v-if="portfolioImpact" :style="cardStyle" style="overflow-x:auto;">
        <p style="font-size:12px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin:0 0 14px;">Portfolio Impact (Base Case)</p>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="background:rgba(255,255,255,0.04);">
              <th style="padding:8px 10px;text-align:left;color:#64748b;font-size:11px;">Metric</th>
              <th style="padding:8px 10px;text-align:right;color:#64748b;font-size:11px;">Today</th>
              <th style="padding:8px 10px;text-align:right;color:#64748b;font-size:11px;">+ This Deal</th>
            </tr>
          </thead>
          <tbody>
            <tr style="border-top:1px solid rgba(255,255,255,0.06);">
              <td style="padding:8px 10px;">Total Units</td>
              <td style="padding:8px 10px;text-align:right;">{{ portfolioImpact.total_units_before }}</td>
              <td style="padding:8px 10px;text-align:right;font-weight:700;">{{ portfolioImpact.total_units_after }}</td>
            </tr>
            <tr style="border-top:1px solid rgba(255,255,255,0.06);">
              <td style="padding:8px 10px;">Occupancy Rate</td>
              <td style="padding:8px 10px;text-align:right;">{{ portfolioImpact.occupancy_rate_before }}%</td>
              <td style="padding:8px 10px;text-align:right;font-weight:700;">{{ portfolioImpact.occupancy_rate_after }}%</td>
            </tr>
            <tr style="border-top:1px solid rgba(255,255,255,0.06);">
              <td style="padding:8px 10px;">Portfolio NOI (trailing 12mo)</td>
              <td style="padding:8px 10px;text-align:right;">{{ fmt(portfolioImpact.portfolio_noi_before) }}</td>
              <td style="padding:8px 10px;text-align:right;font-weight:700;">{{ fmt(portfolioImpact.portfolio_noi_after) }}</td>
            </tr>
            <tr style="border-top:1px solid rgba(255,255,255,0.06);">
              <td style="padding:8px 10px;">Blended ROI</td>
              <td style="padding:8px 10px;text-align:right;">{{ portfolioImpact.blended_roi_before != null ? portfolioImpact.blended_roi_before + '%' : 'N/A' }}</td>
              <td style="padding:8px 10px;text-align:right;font-weight:700;">{{ portfolioImpact.blended_roi_after != null ? portfolioImpact.blended_roi_after + '%' : 'N/A' }}</td>
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
        Generated by VERO Property Management · SQUAD Financial & Business Consulting · Confidential<br>
        This is a candidate acquisition not yet part of the portfolio — nothing here reflects an owned property.
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

const cardStyle = `background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:20px;`

const scenarioLabels = { conservative: 'Conservative', base: 'Base Case', optimistic: 'Optimistic' }
const scenarioColors = { conservative: '#fbbf24', base: '#BA7517', optimistic: '#4ade80' }

const scenarios = computed(() => props.analysis.computed_result?.result?.scenarios ?? {})
const scenarioOf = (key) => scenarios.value[key] ?? {}

const bestNpvScenario = computed(() => {
  const entries = Object.entries(scenarios.value)
  return entries.reduce((best, [key, s]) => (!best || (s.npv ?? -Infinity) > (best[1].npv ?? -Infinity) ? [key, s] : best), null)?.[0]
})

const portfolioImpact = computed(() => props.analysis.computed_result?.portfolio_impact ?? null)

const FUNDING_PATH_LABELS = {
  cash_purchase: 'Cash Purchase',
  bank_loan: 'Bank Loan',
  seller_installments: 'Seller / Developer Installments',
  custom_schedule: 'Custom Payment Schedule',
  contractor_deal: 'Contractor Development Deal',
}
const fundingPathLabel = computed(() => FUNDING_PATH_LABELS[props.analysis.funding_path] ?? props.analysis.funding_path)

const COLLECTION_INTERVAL_LABELS = {
  monthly: 'Monthly', quarterly: 'Quarterly', semi_annually: 'Semi-Annually', annually: 'Annually',
}
const collectionLabel = computed(() => COLLECTION_INTERVAL_LABELS[props.analysis.rent_collection_interval] ?? props.analysis.rent_collection_interval)
</script>
