<template>
  <AuthenticatedLayout>
    <div style="padding:24px; min-height:100vh; background:var(--fv-bg);">

      <!-- ── PAGE HEADER ─────────────────────────────────────────────── -->
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
        <div>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
            <div style="width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#112240,#1490A8); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
              <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#FFC82D" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
              </svg>
            </div>
            <h1 style="font-size:20px; font-weight:700; color:var(--fv-text-primary); margin:0; letter-spacing:-0.01em;">Keep or Sell Analysis</h1>
          </div>
          <p style="color:var(--fv-text-muted); font-size:12px; margin:0 0 0 46px;">DCF-based investment decision tool &nbsp;&middot;&nbsp; NPV &nbsp;&middot;&nbsp; IRR &nbsp;&middot;&nbsp; Terminal Value</p>
        </div>
        <button
          @click="openNewAnalysis"
          style="display:flex; align-items:center; gap:8px; padding:9px 18px; border-radius:8px; background:var(--fv-gold); border:none; color:#0C1829; font-size:13px; font-weight:700; cursor:pointer; transition:background 0.15s; letter-spacing:0.01em;"
          onmouseover="this.style.background='#e6b428'" onmouseout="this.style.background='var(--fv-gold)'">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          New Analysis
        </button>
      </div>

      <!-- ── SNAPSHOTS TABLE ──────────────────────────────────────────── -->
      <div class="fv-card" style="padding:0; overflow:hidden;">
        <div style="padding:14px 18px; border-bottom:1px solid var(--fv-border); display:flex; align-items:center; gap:4px;">
          <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--fv-blue);">Saved Snapshots</span>
          <span style="font-size:11px; font-weight:600; background:var(--fv-blue-dim); color:var(--fv-blue); border:1px solid var(--fv-blue-border); padding:1px 8px; border-radius:999px;">{{ analyses.length }}</span>
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
              <tr style="background:var(--fv-bg-header);">
                <th class="th">Label</th>
                <th class="th">Property / Unit</th>
                <th class="th">Recommendation</th>
                <th class="th" style="text-align:right;">NPV of Hold</th>
                <th class="th" style="text-align:right;">Net Sale Proceeds</th>
                <th class="th" style="text-align:right;">IRR</th>
                <th class="th">Saved By</th>
                <th class="th">Date</th>
                <th class="th" style="text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="analyses.length === 0">
                <td colspan="9" style="text-align:center; padding:52px 20px;">
                  <div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                    <div style="width:44px; height:44px; border-radius:10px; background:var(--fv-bg-card); border:1px solid var(--fv-border); display:flex; align-items:center; justify-content:center;">
                      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="var(--fv-blue)" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                    </div>
                    <p style="color:var(--fv-text-muted); margin:0; font-size:13px;">No analyses saved yet. Create your first one.</p>
                  </div>
                </td>
              </tr>
              <tr v-for="a in analyses" :key="a.id"
                style="border-top:1px solid var(--fv-border); transition:background 0.12s;"
                onmouseover="this.style.background='var(--fv-bg-hover)'"
                onmouseout="this.style.background=''">
                <td class="td" style="font-weight:600;">{{ a.snapshot_label || '—' }}</td>
                <td class="td">
                  <span style="color:var(--fv-text-primary);">{{ a.property_name }}</span>
                  <span v-if="a.unit_name" style="color:var(--fv-text-muted);"> / {{ a.unit_name }}</span>
                </td>
                <td class="td">
                  <span class="fv-badge" :style="badgeStyle(a.auto_recommendation)">
                    {{ recLabel(a.auto_recommendation) }}
                  </span>
                </td>
                <td class="td" style="text-align:right; color:var(--fv-text-primary); font-weight:600;">{{ fmt(a.npv_hold) }}</td>
                <td class="td" style="text-align:right; color:var(--fv-text-muted);">{{ fmt(a.net_sale_proceeds) }}</td>
                <td class="td" style="text-align:right;">
                  <span v-if="a.irr_hold !== null" style="color:var(--fv-gold); font-weight:600;">{{ Number(a.irr_hold).toFixed(1) }}%</span>
                  <span v-else style="color:var(--fv-text-muted);">—</span>
                </td>
                <td class="td" style="color:var(--fv-text-muted);">{{ a.created_by_name }}</td>
                <td class="td" style="color:var(--fv-text-muted);">{{ a.created_at }}</td>
                <td class="td" style="text-align:center;">
                  <div style="display:flex; gap:6px; justify-content:center;">
                    <button class="fv-action-btn" title="View / Edit" @click="loadSnapshot(a.id)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </button>
                    <button class="fv-action-btn" title="Share Link" @click="generateShare(a.id)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                      </svg>
                    </button>
                    <button class="fv-action-btn fv-action-btn-danger" title="Delete" @click="deleteAnalysis(a.id)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── SHARE MODAL ──────────────────────────────────────────────── -->
      <Teleport to="body">
        <div v-if="shareModal.open" style="position:fixed;inset:0;z-index:300;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.65);">
          <div class="fv-card" style="width:520px; padding:28px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
              <div style="width:32px; height:32px; border-radius:7px; background:var(--fv-blue-dim); border:1px solid var(--fv-blue-border); display:flex; align-items:center; justify-content:center;">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="var(--fv-blue)" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
              </div>
              <h3 style="color:var(--fv-text-primary); margin:0; font-size:15px; font-weight:700;">Share Analysis</h3>
            </div>
            <p style="color:var(--fv-text-muted); font-size:13px; margin:0 0 14px;">This link is publicly accessible — no login required. Safe to email to board members.</p>
            <div style="display:flex; gap:8px;">
              <input class="fv-input" style="flex:1; font-size:12px; padding:8px 10px; border-radius:7px;" readonly :value="shareModal.url" />
              <button @click="copyShare"
                style="padding:0 18px; border-radius:7px; border:none; background:var(--fv-gold); color:#0C1829; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background 0.15s;"
                onmouseover="this.style.background='#e6b428'" onmouseout="this.style.background='var(--fv-gold)'">
                {{ shareModal.copied ? 'Copied!' : 'Copy' }}
              </button>
            </div>
            <div style="margin-top:16px; text-align:right;">
              <button class="fv-btn-secondary" style="padding:7px 16px; border-radius:7px; font-size:13px; cursor:pointer;" @click="shareModal.open=false">Close</button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ── ANALYSIS PANEL ───────────────────────────────────────────── -->
      <Teleport to="body">
        <div v-if="panel.open" style="position:fixed;inset:0;z-index:250;display:flex;">
          <div style="flex:1; background:rgba(0,0,0,0.55);" @click="panel.open=false"></div>

          <div style="width:85%; max-width:100vw; background:var(--fv-bg); overflow-y:auto; display:flex; flex-direction:column; border-left:1px solid var(--fv-border);">

            <!-- Panel Header -->
            <div style="position:sticky; top:0; z-index:10; display:flex; justify-content:space-between; align-items:center; padding:16px 24px; background:var(--fv-bg-header); border-bottom:1px solid var(--fv-border);">
              <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:8px; height:8px; border-radius:50%; background:var(--fv-gold);"></div>
                <span style="color:var(--fv-text-primary); font-size:15px; font-weight:700;">
                  {{ panel.snapshotId ? 'View Saved Analysis' : 'New Analysis' }}
                </span>
              </div>
              <button @click="panel.open=false"
                style="display:flex; align-items:center; gap:6px; padding:6px 14px; border-radius:7px; background:transparent; border:1px solid var(--fv-border); color:var(--fv-text-muted); font-size:12px; cursor:pointer; transition:all 0.15s;"
                onmouseover="this.style.borderColor='var(--fv-blue)'; this.style.color='var(--fv-blue)'"
                onmouseout="this.style.borderColor='var(--fv-border)'; this.style.color='var(--fv-text-muted)'">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Close
              </button>
            </div>

            <div style="padding:24px; display:flex; flex-direction:column; gap:18px;">

              <!-- STEP 1: Property & Unit -->
              <div class="fv-card" style="padding:20px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                  <span style="width:20px; height:20px; border-radius:5px; background:var(--fv-blue); display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:#fff; flex-shrink:0;">1</span>
                  <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--fv-blue);">Select Property &amp; Unit</span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                  <div>
                    <label class="inp-label">Property</label>
                    <select class="fv-select inp" v-model="form.property_id" @change="onPropertyChange" :disabled="!!panel.snapshotId">
                      <option value="">— Select property —</option>
                      <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.property_name }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="inp-label">Unit <span style="color:var(--fv-text-muted); text-transform:none; font-weight:400; font-size:11px;">(if parent property)</span></label>
                    <select class="fv-select inp" v-model="form.property_unit_id" @change="loadUnitData"
                      :disabled="!!panel.snapshotId || !selectedProperty || selectedProperty.nature === 'unit'">
                      <option value="">— Standalone / All units —</option>
                      <option v-for="u in selectedUnits" :key="u.id" :value="u.id">{{ u.unit_name }}</option>
                    </select>
                  </div>
                </div>

                <div v-if="unitLoading" style="margin-top:12px; display:flex; align-items:center; gap:8px; color:var(--fv-text-muted); font-size:12px;">
                  <svg class="spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40" stroke-dashoffset="10"/>
                  </svg>
                  Loading unit data…
                </div>

                <div v-if="unitInfo.property_name" style="margin-top:14px; display:grid; grid-template-columns:repeat(4,1fr); gap:10px;">
                  <div style="padding:12px 14px; border-radius:8px; background:var(--fv-bg); border:1px solid var(--fv-border);">
                    <p class="info-label">Market Value (Latest)</p>
                    <p style="color:var(--fv-gold); font-size:16px; font-weight:800; margin:0;">
                      {{ unitInfo.market_value != null ? fmt(unitInfo.market_value) : '—' }}
                    </p>
                    <p v-if="unitInfo.valuation_currency && unitInfo.valuation_currency !== unitInfo.base_currency && unitInfo.market_value_original != null"
                       style="color:var(--fv-text-muted); font-size:11px; margin:4px 0 0;">
                      Converted from {{ fmt(unitInfo.market_value_original) }} {{ unitInfo.valuation_currency }}
                    </p>
                  </div>
                  <div style="padding:12px 14px; border-radius:8px; background:var(--fv-bg); border:1px solid var(--fv-border);">
                    <p class="info-label">Acquisition Cost</p>
                    <p style="color:var(--fv-text-primary); font-size:16px; font-weight:800; margin:0;">
                      {{ unitInfo.acquisition_cost != null ? fmt(unitInfo.acquisition_cost) : '—' }}
                    </p>
                    <p v-if="unitInfo.valuation_currency && unitInfo.valuation_currency !== unitInfo.base_currency && unitInfo.acquisition_cost_original != null"
                       style="color:var(--fv-text-muted); font-size:11px; margin:4px 0 0;">
                      Converted from {{ fmt(unitInfo.acquisition_cost_original) }} {{ unitInfo.valuation_currency }}
                    </p>
                  </div>
                  <div style="padding:12px 14px; border-radius:8px; background:var(--fv-bg); border:1px solid var(--fv-border);">
                    <p class="info-label">Currency</p>
                    <p style="color:var(--fv-text-primary); font-size:16px; font-weight:800; margin:0;">{{ unitInfo.base_currency }}</p>
                    <p v-if="unitInfo.valuation_currency && unitInfo.valuation_currency !== unitInfo.base_currency"
                       style="color:var(--fv-text-muted); font-size:11px; margin:4px 0 0;">
                      Property priced in {{ unitInfo.valuation_currency }} — all figures shown in {{ unitInfo.base_currency }}
                    </p>
                  </div>
                  <!-- Installment badge — only shown when ownership = installments -->
                  <div v-if="unitInfo.ownership === 'installments'" style="padding:12px 14px; border-radius:8px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2);">
                    <p class="info-label" style="color:#fca5a5;">Remaining Installments</p>
                    <p style="color:#ef4444; font-size:16px; font-weight:800; margin:0;">{{ fmt(unitInfo.total_remaining_installments) }}</p>
                  </div>
                </div>

                <div v-if="unitInfo.valuation_fx_missing" style="margin-top:10px; padding:10px 14px; border-radius:8px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#ef4444; font-size:12px;">
                  ⚠ This property is priced in {{ unitInfo.valuation_currency }} and no exchange rate to {{ unitInfo.base_currency }} is on file in Statistica.
                  Market Value / Acquisition Cost could not be converted automatically — enter Current Market Value below manually, in {{ unitInfo.base_currency }},
                  or add a {{ unitInfo.valuation_currency }} rate first for an accurate result.
                </div>
              </div>

              <!-- STEP 2: Assumptions -->
              <div class="fv-card" style="padding:20px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                  <span style="width:20px; height:20px; border-radius:5px; background:var(--fv-blue); display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:#fff; flex-shrink:0;">2</span>
                  <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--fv-blue);">Assumptions</span>
                </div>

                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px;">
                  <div>
                    <label class="inp-label">Snapshot Label</label>
                    <input class="fv-input inp" v-model="form.snapshot_label" placeholder="e.g. Q1 2026 Review" />
                  </div>
                  <div>
                    <label class="inp-label">Current Market Value</label>
                    <input class="fv-input inp" type="number" v-model="form.market_value" placeholder="Auto-filled from DB" />
                  </div>
                  <div>
                    <label class="inp-label">Selling Costs %</label>
                    <input class="fv-input inp" type="number" v-model="form.selling_costs_pct" placeholder="e.g. 3" min="0" max="100" />
                  </div>
                  <div>
                    <label class="inp-label">Holding Period (years)</label>
                    <input class="fv-input inp" type="number" v-model="form.holding_years" min="1" max="30" />
                  </div>
                  <div>
                    <label class="inp-label">Rent Growth Rate % (post-contract)</label>
                    <input class="fv-input inp" type="number" v-model="form.rent_growth_rate_pct" placeholder="e.g. 5" min="0" max="100" />
                  </div>
                  <div>
                    <label class="inp-label">Other OpEx % of Revenue</label>
                    <input class="fv-input inp" type="number" v-model="form.other_opex_pct" placeholder="e.g. 10" min="0" max="100" />
                  </div>
                  <div>
                    <label class="inp-label">Corporate Tax Rate %</label>
                    <input class="fv-input inp" type="number" v-model="form.corporate_tax_rate_pct" placeholder="e.g. 22.5" min="0" max="100" />
                  </div>
                  <div>
                    <label class="inp-label">Discount Rate % (WACC)</label>
                    <input class="fv-input inp" type="number" v-model="form.discount_rate_pct" placeholder="e.g. 12" min="0.01" max="100" />
                  </div>
                </div>

                <!-- ── EXIT VALUE METHOD ─────────────────────────────────────── -->
                <div style="margin-top:20px; padding:16px; border-radius:10px; background:var(--fv-bg); border:1px solid var(--fv-border);">
                  <p style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--fv-blue); margin:0 0 12px;">Exit Value Method (Future Property Value)</p>
                  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
                    <label v-for="opt in exitMethodOptions" :key="opt.value"
                      :style="form.exit_value_method === opt.value
                        ? 'display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:8px; background:var(--fv-blue-dim); border:1px solid var(--fv-blue); cursor:pointer; flex:1; min-width:200px;'
                        : 'display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:8px; background:var(--fv-bg-card); border:1px solid var(--fv-border); cursor:pointer; flex:1; min-width:200px;'">
                      <input type="radio" v-model="form.exit_value_method" :value="opt.value" style="margin-top:2px; accent-color:var(--fv-blue);" />
                      <div>
                        <div style="font-size:13px; font-weight:700; color:var(--fv-text-primary); margin-bottom:2px;">{{ opt.label }}</div>
                        <div style="font-size:11px; color:var(--fv-text-muted); line-height:1.5;">{{ opt.description }}</div>
                      </div>
                    </label>
                  </div>

                  <!-- Conditional fields based on method -->
                  <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div v-if="form.exit_value_method === 'appreciation' || form.exit_value_method === 'higher_of'">
                      <label class="inp-label">Annual Market Appreciation %</label>
                      <input class="fv-input inp" type="number" v-model="form.appreciation_rate_pct" placeholder="e.g. 8" min="0" max="100" />
                      <p style="font-size:11px; color:var(--fv-text-muted); margin:4px 0 0;">Exit Value = Current Market Value × (1 + rate)^years</p>
                    </div>
                    <div v-if="form.exit_value_method === 'cap_rate' || form.exit_value_method === 'higher_of'">
                      <label class="inp-label">Exit Cap Rate %</label>
                      <input class="fv-input inp" type="number" v-model="form.exit_cap_rate_pct" placeholder="e.g. 7" min="0.01" max="100" />
                      <p style="font-size:11px; color:var(--fv-text-muted); margin:4px 0 0;">Exit Value = Year N NOI ÷ Cap Rate</p>
                    </div>
                  </div>
                </div>

                <div style="margin-top:18px; display:flex; justify-content:flex-end;">
                  <button @click="calculate" :disabled="computing"
                    style="display:flex; align-items:center; gap:8px; padding:9px 22px; border-radius:8px; border:none; background:var(--fv-blue); color:#fff; font-size:13px; font-weight:700; cursor:pointer; transition:background 0.15s; letter-spacing:0.01em;"
                    onmouseover="this.style.background='var(--fv-blue-hover)'" onmouseout="this.style.background='var(--fv-blue)'">
                    <svg v-if="!computing" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                    </svg>
                    <svg v-else class="spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40" stroke-dashoffset="10"/>
                    </svg>
                    {{ computing ? 'Calculating…' : 'Calculate' }}
                  </button>
                </div>
              </div>

              <!-- RESULTS -->
              <div v-if="result" style="display:flex; flex-direction:column; gap:16px;">

                <!-- KPI Strip -->
                <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px;">
                  <div style="padding:16px 18px; border-radius:10px; background:var(--fv-bg-card); border:1px solid var(--fv-border);">
                    <p class="info-label">NPV of Hold</p>
                    <p style="font-size:20px; font-weight:800; margin:0;" :style="{color: result.npv_hold > result.net_sale_proceeds ? '#22c55e' : '#ef4444'}">
                      {{ fmt(result.npv_hold) }}
                    </p>
                  </div>
                  <div style="padding:16px 18px; border-radius:10px; background:var(--fv-bg-card); border:1px solid var(--fv-border);">
                    <p class="info-label">Net Sale Proceeds</p>
                    <p style="font-size:20px; font-weight:800; color:var(--fv-text-primary); margin:0;">{{ fmt(result.net_sale_proceeds) }}</p>
                  </div>
                  <div style="padding:16px 18px; border-radius:10px; background:var(--fv-bg-card); border:1px solid var(--fv-border);">
                    <p class="info-label">IRR of Hold</p>
                    <p style="font-size:20px; font-weight:800; color:var(--fv-gold); margin:0;">
                      {{ result.irr_hold !== null ? Number(result.irr_hold).toFixed(1) + '%' : '—' }}
                    </p>
                  </div>
                  <div style="padding:16px 18px; border-radius:10px; background:var(--fv-bg-card); border:1px solid var(--fv-border);">
                    <p class="info-label">
                      Exit Value
                      <span style="font-size:10px; font-weight:600; padding:1px 6px; border-radius:4px; margin-left:4px;"
                        :style="result.terminal_value_note === 'market_appreciation'
                          ? 'background:rgba(20,144,168,0.15); color:var(--fv-blue);'
                          : 'background:rgba(186,117,23,0.15); color:var(--fv-gold);'">
                        {{ result.terminal_value_note === 'market_appreciation' ? 'Appreciation' : 'Cap Rate' }}
                      </span>
                    </p>
                    <p style="font-size:20px; font-weight:800; color:var(--fv-text-primary); margin:0;">{{ fmt(result.terminal_value) }}</p>
                    <!-- Show both when higher_of method was used -->
                    <div v-if="form.exit_value_method === 'higher_of'" style="margin-top:6px; display:flex; flex-direction:column; gap:2px;">
                      <span style="font-size:10px; color:var(--fv-text-muted);">Appreciation: {{ fmt(result.tv_appreciation) }}</span>
                      <span style="font-size:10px; color:var(--fv-text-muted);">Cap Rate: {{ fmt(result.tv_cap_rate) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Installment summary strip — only when there are installments -->
                <div v-if="result.total_installments > 0"
                  style="padding:14px 18px; border-radius:10px; background:rgba(239,68,68,0.07); border:1px solid rgba(239,68,68,0.2); display:flex; align-items:center; gap:14px;">
                  <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="2" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                  </svg>
                  <div>
                    <span style="font-size:12px; font-weight:700; color:#ef4444;">Installment Payments Deducted from DCF: </span>
                    <span style="font-size:12px; font-weight:800; color:#ef4444;">{{ fmt(result.total_installments) }}</span>
                    <span style="font-size:11px; color:var(--fv-text-muted); margin-left:8px;">Total remaining installment dues within the holding period — shown per year in the table below.</span>
                  </div>
                </div>

                <!-- Recommendation Banner -->
                <div class="fv-card" style="padding:20px;" :style="{borderLeft: '4px solid ' + recColor(result.auto_recommendation)}">
                  <div style="display:flex; align-items:center; gap:16px; margin-bottom:14px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:12px; flex:1;">
                      <div :style="{width:'40px', height:'40px', borderRadius:'10px', background: recBgColor(result.auto_recommendation), display:'flex', alignItems:'center', justifyContent:'center', flexShrink:'0'}">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" :stroke="recColor(result.auto_recommendation)" stroke-width="2">
                          <path v-if="result.auto_recommendation === 'keep'" stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                          <path v-else-if="result.auto_recommendation === 'sell'" stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          <path v-else stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                      </div>
                      <div>
                        <p class="info-label">Auto Recommendation</p>
                        <p style="font-size:17px; font-weight:800; margin:0;" :style="{color: recColor(result.auto_recommendation)}">
                          {{ recLabel(result.auto_recommendation) }}
                        </p>
                      </div>
                    </div>
                    <div style="text-align:right;">
                      <p class="info-label">NPV Gap vs Sale</p>
                      <p style="font-size:16px; font-weight:800; margin:0;" :style="{color: result.npv_gap >= 0 ? '#22c55e' : '#ef4444'}">
                        {{ result.npv_gap >= 0 ? '+' : '' }}{{ fmt(result.npv_gap) }}
                      </p>
                    </div>
                  </div>
                  <div v-if="result.auto_flags && result.auto_flags.length" style="display:flex; flex-direction:column; gap:6px;">
                    <div v-for="(flag, i) in result.auto_flags" :key="i"
                      style="display:flex; gap:10px; align-items:flex-start; background:var(--fv-gold-dim); border:1px solid var(--fv-gold-border); padding:9px 12px; border-radius:7px;">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="var(--fv-gold)" stroke-width="2" style="flex-shrink:0; margin-top:1px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                      </svg>
                      <span style="color:var(--fv-text-muted); font-size:12px; line-height:1.5;">{{ flag }}</span>
                    </div>
                  </div>
                </div>

                <!-- Cash Flow Table -->
                <div class="fv-card" style="padding:0; overflow:hidden;">
                  <div style="padding:14px 18px; border-bottom:1px solid var(--fv-border);">
                    <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--fv-blue);">Year-by-Year Cash Flow Projection</span>
                  </div>
                  <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:12px;">
                      <thead>
                        <tr style="background:var(--fv-bg-header);">
                          <th class="th">Year</th>
                          <th class="th">Cal. Year</th>
                          <th class="th">Source</th>
                          <th class="th" style="text-align:right;">Gross Revenue</th>
                          <th class="th" style="text-align:right;">Direct Expenses</th>
                          <th class="th" style="text-align:right;">Other OpEx</th>
                          <th class="th" style="text-align:right;">Corp. Tax</th>
                          <th class="th" style="text-align:right; color:#ef4444;">Installments</th>
                          <th class="th" style="text-align:right;">Net CF</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="row in result.annual_cashflows" :key="row.year"
                          style="border-top:1px solid var(--fv-border); transition:background 0.1s;"
                          onmouseover="this.style.background='var(--fv-bg-hover)'"
                          onmouseout="this.style.background=''">
                          <td class="td" style="font-weight:700; color:var(--fv-text-primary);">Y{{ row.year }}</td>
                          <td class="td" style="color:var(--fv-text-muted);">{{ row.cal_year }}</td>
                          <td class="td">
                            <span style="font-size:11px; font-weight:600; padding:2px 7px; border-radius:5px;"
                              :style="row.is_contracted
                                ? 'background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.2);'
                                : 'background:var(--fv-gold-dim); color:var(--fv-gold); border:1px solid var(--fv-gold-border);'">
                              {{ row.is_contracted ? 'Contracted' : 'Projected' }}
                            </span>
                          </td>
                          <td class="td" style="text-align:right; color:var(--fv-text-primary);">{{ fmt(row.gross_revenue) }}</td>
                          <td class="td" style="text-align:right; color:#ef4444;">{{ fmt(row.direct_expenses) }}</td>
                          <td class="td" style="text-align:right; color:#f97316;">{{ fmt(row.other_opex) }}</td>
                          <td class="td" style="text-align:right; color:#ef4444;">{{ fmt(row.corporate_tax) }}</td>
                          <td class="td" style="text-align:right;">
                            <span v-if="row.installment_payment > 0" style="color:#ef4444; font-weight:700;">({{ fmt(row.installment_payment) }})</span>
                            <span v-else style="color:var(--fv-text-muted);">—</span>
                          </td>
                          <td class="td" style="text-align:right; font-weight:700;" :style="{color: row.net_cf >= 0 ? '#22c55e' : '#ef4444'}">{{ fmt(row.net_cf) }}</td>
                        </tr>
                        <!-- Terminal Value row -->
                        <tr style="border-top:2px solid var(--fv-border); background:var(--fv-blue-dim);">
                          <td class="td" colspan="3" style="font-weight:700; color:var(--fv-blue);">
                            Exit Value
                            <span style="font-size:10px; font-weight:600; padding:1px 6px; border-radius:4px; margin-left:6px;"
                              :style="result.terminal_value_note === 'market_appreciation'
                                ? 'background:rgba(20,144,168,0.2); color:var(--fv-blue);'
                                : 'background:rgba(186,117,23,0.2); color:var(--fv-gold);'">
                              {{ result.terminal_value_note === 'market_appreciation' ? 'Market Appreciation' : 'Income Cap Rate' }}
                            </span>
                          </td>
                          <td class="td" colspan="5"></td>
                          <td class="td" style="text-align:right; font-weight:800; color:var(--fv-gold);">{{ fmt(result.terminal_value) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Chart -->
                <div class="fv-card" style="padding:20px;">
                  <p style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--fv-blue); margin:0 0 14px;">Net Cash Flow vs Accumulated (Discounted)</p>
                  <div style="position:relative; width:100%; height:240px;">
                    <canvas ref="chartCanvas"></canvas>
                  </div>
                </div>

                <!-- Analyst Recommendation -->
                <div class="fv-card" style="padding:20px;">
                  <p style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--fv-blue); margin:0 0 4px;">Analyst Recommendation</p>
                  <p style="font-size:12px; color:var(--fv-text-muted); margin:0 0 10px;">Saved with the snapshot and visible in the shared report.</p>
                  <textarea class="fv-input" v-model="form.analyst_recommendation" rows="3"
                    style="width:100%; resize:vertical; font-size:13px; line-height:1.6; box-sizing:border-box;"
                    placeholder="Add your professional assessment here…"></textarea>
                </div>

                <!-- Save Actions -->
                <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; padding-bottom:8px;">
                  <button v-if="panel.snapshotId" @click="generateShare(panel.snapshotId)"
                    style="display:flex; align-items:center; gap:7px; padding:9px 18px; border-radius:8px; border:1px solid var(--fv-border); background:transparent; color:var(--fv-text-muted); font-size:13px; font-weight:600; cursor:pointer; transition:all 0.15s;"
                    onmouseover="this.style.borderColor='var(--fv-blue)'; this.style.color='var(--fv-blue)'"
                    onmouseout="this.style.borderColor='var(--fv-border)'; this.style.color='var(--fv-text-muted)'">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                    </svg>
                    Generate Share Link
                  </button>

                  <button v-if="panel.snapshotId" @click="updateRecommendation" :disabled="saving"
                    style="display:flex; align-items:center; gap:7px; padding:9px 20px; border-radius:8px; border:none; background:var(--fv-blue); color:#fff; font-size:13px; font-weight:700; cursor:pointer; transition:background 0.15s;"
                    onmouseover="this.style.background='var(--fv-blue-hover)'" onmouseout="this.style.background='var(--fv-blue)'">
                    <svg v-if="!saving" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    <svg v-else class="spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40" stroke-dashoffset="10"/>
                    </svg>
                    {{ saving ? 'Saving…' : 'Update Recommendation' }}
                  </button>

                  <button v-else @click="saveSnapshot" :disabled="saving"
                    style="display:flex; align-items:center; gap:7px; padding:9px 22px; border-radius:8px; border:none; background:var(--fv-gold); color:#0C1829; font-size:13px; font-weight:700; cursor:pointer; transition:background 0.15s;"
                    onmouseover="this.style.background='#e6b428'" onmouseout="this.style.background='var(--fv-gold)'">
                    <svg v-if="!saving" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    <svg v-else class="spin" width="14" height="14" fill="none" viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40" stroke-dashoffset="10"/>
                    </svg>
                    {{ saving ? 'Saving…' : 'Save Snapshot' }}
                  </button>
                </div>

              </div><!-- /results -->
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  companyId:  { type: Number, required: true },
  analyses:   { type: Array,  default: () => [] },
  properties: { type: Array,  default: () => [] },
})

// ── Helpers ──────────────────────────────────────────────
const fmt = (v) => {
  if (v === null || v === undefined) return '—'
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

const recLabel   = (r) => ({ keep: 'Keep', sell: 'Sell', neutral: 'Neutral' }[r] ?? '—')
const recColor   = (r) => ({ keep: '#22c55e', sell: '#ef4444', neutral: '#eab308' }[r] ?? 'var(--fv-text-muted)')
const recBgColor = (r) => ({ keep: 'rgba(34,197,94,0.1)', sell: 'rgba(239,68,68,0.1)', neutral: 'rgba(234,179,8,0.1)' }[r] ?? 'var(--fv-bg-card)')

const badgeStyle = (r) => {
  const s = {
    keep:    'background:rgba(34,197,94,0.12); color:#86efac; border:1px solid rgba(34,197,94,0.2);',
    sell:    'background:rgba(239,68,68,0.12); color:#fca5a5; border:1px solid rgba(239,68,68,0.2);',
    neutral: 'background:rgba(234,179,8,0.12); color:#fde68a; border:1px solid rgba(234,179,8,0.2);',
  }
  return s[r] ?? ''
}

// ── Exit method options ──────────────────────────────────
const exitMethodOptions = [
  {
    value: 'appreciation',
    label: 'Market Appreciation',
    description: 'Future value = Current market value growing at % per year. Best for assets with strong capital growth regardless of rent level.',
  },
  {
    value: 'cap_rate',
    label: 'Income Cap Rate',
    description: 'Future value = Year N NOI ÷ Cap Rate. Valid only when rent is at or near market rate.',
  },
  {
    value: 'higher_of',
    label: 'Higher of Both',
    description: 'System calculates both methods and picks the greater value. Conservative protection against undervaluation.',
  },
]

// ── State ────────────────────────────────────────────────
const panel = ref({ open: false, snapshotId: null })

const defaultForm = () => ({
  property_id:            '',
  property_unit_id:       '',
  snapshot_label:         '',
  market_value:           '',
  selling_costs_pct:      3,
  holding_years:          5,
  rent_growth_rate_pct:   5,
  other_opex_pct:         10,
  corporate_tax_rate_pct: 22.5,
  discount_rate_pct:      12,
  exit_value_method:      'appreciation',   // default to the correct method
  appreciation_rate_pct:  8,
  exit_cap_rate_pct:      7,
  analyst_recommendation: '',
})

const form        = ref(defaultForm())
const unitInfo    = ref({})
const unitLoading = ref(false)
const computing   = ref(false)
const saving      = ref(false)
const result      = ref(null)
const chartCanvas = ref(null)
let   chartInstance = null

const shareModal = ref({ open: false, url: '', copied: false })

// ── Property / Unit selectors ────────────────────────────
const selectedProperty = computed(() =>
  props.properties.find(p => p.id == form.value.property_id) ?? null
)

const selectedUnits = computed(() =>
  selectedProperty.value?.units ?? []
)

function onPropertyChange() {
  form.value.property_unit_id = ''
  unitInfo.value = {}
  result.value   = null
  if (form.value.property_id) loadUnitData()
}

async function loadUnitData() {
  if (!form.value.property_id) return
  unitLoading.value = true
  try {
    const { data } = await axios.get(route('company.properties.keep-or-sell.unit-data', { company: props.companyId }), {
      params: {
        property_id: form.value.property_id,
        unit_id:     form.value.property_unit_id || undefined,
      }
    })
    unitInfo.value = data
    if (data.market_value) form.value.market_value = data.market_value
  } catch (e) { console.error(e) }
  unitLoading.value = false
}

// ── Calculate ─────────────────────────────────────────────
async function calculate() {
  if (!form.value.property_id) { alert('Please select a property first.'); return }
  computing.value = true
  result.value    = null

  const contractedRevenuesObj = {}
  const contractedExpensesObj = {}
  const installmentByYearObj  = {}
  let lastContractedRent      = 0

  if (unitInfo.value.revenue_by_year) {
    unitInfo.value.revenue_by_year.forEach(r => { contractedRevenuesObj[r.yr] = r.total_revenue })
  }
  if (unitInfo.value.expense_by_year) {
    unitInfo.value.expense_by_year.forEach(r => { contractedExpensesObj[r.yr] = r.total_expense })
  }
  if (unitInfo.value.installment_by_year) {
    unitInfo.value.installment_by_year.forEach(r => { installmentByYearObj[r.yr] = r.total_due })
  }
  if (unitInfo.value.contracts && unitInfo.value.contracts.length) {
    const c = unitInfo.value.contracts[0]
    lastContractedRent = parseFloat(c.min_monthly_rent || c.monthly_rent_amount || 0)
  }

  const contractedRevenues = Object.keys(contractedRevenuesObj).length ? contractedRevenuesObj : []
  const contractedExpenses = Object.keys(contractedExpensesObj).length ? contractedExpensesObj : []
  const installmentByYear  = Object.keys(installmentByYearObj).length  ? installmentByYearObj  : []

  try {
    const { data } = await axios.post(route('company.properties.keep-or-sell.compute', { company: props.companyId }), {
      property_id:            form.value.property_id,
      property_unit_id:       form.value.property_unit_id || null,
      market_value:           parseFloat(form.value.market_value),
      selling_costs_pct:      parseFloat(form.value.selling_costs_pct),
      holding_years:          parseInt(form.value.holding_years),
      rent_growth_rate_pct:   parseFloat(form.value.rent_growth_rate_pct),
      other_opex_pct:         parseFloat(form.value.other_opex_pct),
      corporate_tax_rate_pct: parseFloat(form.value.corporate_tax_rate_pct),
      discount_rate_pct:      parseFloat(form.value.discount_rate_pct),
      exit_value_method:      form.value.exit_value_method,
      appreciation_rate_pct:  parseFloat(form.value.appreciation_rate_pct || 0),
      exit_cap_rate_pct:      parseFloat(form.value.exit_cap_rate_pct || 0),
      contracted_revenues:    contractedRevenues,
      contracted_expenses:    contractedExpenses,
      installment_by_year:    installmentByYear,
      last_contracted_rent:   lastContractedRent,
    })
    result.value = data
    await nextTick()
    renderChart(data)
  } catch (e) {
    const msg = e.response?.data?.message
      ?? (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null)
      ?? e.message
    console.error(e)
    alert('Calculation failed: ' + msg)
  }

  computing.value = false
}

// ── Chart ─────────────────────────────────────────────────
async function renderChart(data) {
  if (!chartCanvas.value) return
  if (chartInstance) { chartInstance.destroy(); chartInstance = null }

  const Chart = (await import('chart.js/auto')).default
  const labels = data.annual_cashflows.map(r => 'Y' + r.year)
  const netCFs = data.annual_cashflows.map(r => r.net_cf)
  const cumCFs = netCFs.reduce((acc, v, i) => { acc.push((acc[i - 1] ?? 0) + v); return acc }, [])

  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { type: 'bar',  label: 'Net CF',         data: netCFs, backgroundColor: 'rgba(20,144,168,0.55)', borderRadius: 4, borderSkipped: false },
        { type: 'line', label: 'Accumulated CF',  data: cumCFs, borderColor: '#FFC82D', borderWidth: 2, pointRadius: 3, tension: 0.3, fill: false },
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false, resizeDelay: 0,
      plugins: { legend: { labels: { color: '#94a3b8', font: { size: 11 } } } },
      scales: {
        x: { ticks: { color: '#64748b', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
        y: { ticks: { color: '#64748b', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
      }
    }
  })
}

// ── Save Snapshot ─────────────────────────────────────────
async function saveSnapshot() {
  if (!result.value) { alert('Please calculate first.'); return }
  saving.value = true

  const contractedRevenuesObj = {}
  const contractedExpensesObj = {}
  const installmentByYearObj  = {}
  let lastContractedRent      = 0

  if (unitInfo.value.revenue_by_year)    unitInfo.value.revenue_by_year.forEach(r => { contractedRevenuesObj[r.yr] = r.total_revenue })
  if (unitInfo.value.expense_by_year)    unitInfo.value.expense_by_year.forEach(r => { contractedExpensesObj[r.yr] = r.total_expense })
  if (unitInfo.value.installment_by_year) unitInfo.value.installment_by_year.forEach(r => { installmentByYearObj[r.yr] = r.total_due })
  if (unitInfo.value.contracts?.length) {
    const c = unitInfo.value.contracts[0]
    lastContractedRent = parseFloat(c.min_monthly_rent || c.monthly_rent_amount || 0)
  }

  const contractedRevenues = Object.keys(contractedRevenuesObj).length ? contractedRevenuesObj : []
  const contractedExpenses = Object.keys(contractedExpensesObj).length ? contractedExpensesObj : []
  const installmentByYear  = Object.keys(installmentByYearObj).length  ? installmentByYearObj  : []

  try {
    const { data } = await axios.post(route('company.properties.keep-or-sell.store', { company: props.companyId }), {
      ...form.value,
      property_id:            form.value.property_id,
      property_unit_id:       form.value.property_unit_id || null,
      market_value:           parseFloat(form.value.market_value),
      selling_costs_pct:      parseFloat(form.value.selling_costs_pct),
      holding_years:          parseInt(form.value.holding_years),
      rent_growth_rate_pct:   parseFloat(form.value.rent_growth_rate_pct),
      other_opex_pct:         parseFloat(form.value.other_opex_pct),
      corporate_tax_rate_pct: parseFloat(form.value.corporate_tax_rate_pct),
      discount_rate_pct:      parseFloat(form.value.discount_rate_pct),
      exit_value_method:      form.value.exit_value_method,
      appreciation_rate_pct:  parseFloat(form.value.appreciation_rate_pct || 0),
      exit_cap_rate_pct:      parseFloat(form.value.exit_cap_rate_pct || 0),
      contracted_revenues:    contractedRevenues,
      contracted_expenses:    contractedExpenses,
      installment_by_year:    installmentByYear,
      last_contracted_rent:   lastContractedRent,
    })
    if (data.saved) {
      panel.value.open = false
      router.reload({ only: ['analyses'] })
    }
  } catch (e) {
    const msg = e.response?.data?.message
      ?? (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null)
      ?? e.message
    console.error(e)
    alert('Save failed: ' + msg)
  }
  saving.value = false
}

// ── Update Recommendation ─────────────────────────────────
async function updateRecommendation() {
  if (!panel.value.snapshotId) return
  saving.value = true
  try {
    await axios.patch(route('company.properties.keep-or-sell.update-recommendation', { company: props.companyId, analysis: panel.value.snapshotId }), {
      analyst_recommendation: form.value.analyst_recommendation,
    })
    router.reload({ only: ['analyses'] })
  } catch (e) { console.error(e) }
  saving.value = false
}

// ── Load Snapshot ─────────────────────────────────────────
async function loadSnapshot(id) {
  try {
    const { data } = await axios.get(route('company.properties.keep-or-sell.show', { company: props.companyId, analysis: id }))

    form.value = {
      property_id:            data.property_id,
      property_unit_id:       data.property_unit_id ?? '',
      snapshot_label:         data.snapshot_label ?? '',
      market_value:           data.market_value,
      selling_costs_pct:      data.selling_costs_pct,
      holding_years:          data.holding_years,
      rent_growth_rate_pct:   data.rent_growth_rate_pct,
      other_opex_pct:         data.other_opex_pct,
      corporate_tax_rate_pct: data.corporate_tax_rate_pct,
      discount_rate_pct:      data.discount_rate_pct,
      // Backward compat: old snapshots have no exit_value_method stored
      exit_value_method:      data.exit_value_method ?? 'appreciation',
      appreciation_rate_pct:  data.appreciation_rate_pct ?? 8,
      exit_cap_rate_pct:      data.exit_cap_rate_pct ?? 7,
      analyst_recommendation: data.analyst_recommendation ?? '',
    }

    result.value = {
      net_sale_proceeds:   data.net_sale_proceeds,
      terminal_value:      data.terminal_value,
      terminal_value_note: data.terminal_value_note ?? 'market_appreciation',
      tv_appreciation:     data.tv_appreciation ?? null,
      tv_cap_rate:         data.tv_cap_rate ?? null,
      npv_hold:            data.npv_hold,
      irr_hold:            data.irr_hold,
      auto_recommendation: data.auto_recommendation,
      auto_flags:          data.auto_flags ?? [],
      annual_cashflows:    data.annual_cashflows ?? [],
      npv_gap:             (data.npv_hold ?? 0) - (data.net_sale_proceeds ?? 0),
      total_installments:  data.total_installments ?? 0,
    }

    panel.value = { open: true, snapshotId: id }
    await nextTick()
    renderChart(result.value)
  } catch (e) { console.error(e) }
}

// ── New Analysis ──────────────────────────────────────────
function openNewAnalysis() {
  form.value     = defaultForm()
  unitInfo.value = {}
  result.value   = null
  panel.value    = { open: true, snapshotId: null }
}

// ── Delete ────────────────────────────────────────────────
async function deleteAnalysis(id) {
  if (!confirm('Delete this analysis snapshot?')) return
  await axios.delete(route('company.properties.keep-or-sell.destroy', { company: props.companyId, analysis: id }))
  router.reload({ only: ['analyses'] })
}

// ── Share ─────────────────────────────────────────────────
async function generateShare(id) {
  try {
    const { data } = await axios.post(route('company.properties.keep-or-sell.generate-token', { company: props.companyId, analysis: id }))
    shareModal.value = { open: true, url: window.location.origin + '/keep-or-sell/share/' + data.token, copied: false }
  } catch (e) { console.error(e) }
}

function copyShare() {
  navigator.clipboard.writeText(shareModal.value.url)
  shareModal.value.copied = true
  setTimeout(() => { shareModal.value.copied = false }, 2000)
}
</script>

<style scoped>
.th {
  padding: 10px 14px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  color: var(--fv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  white-space: nowrap;
}
.td {
  padding: 11px 14px;
  color: var(--fv-text-primary);
  font-size: 13px;
}
.inp-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--fv-text-primary);
  margin-bottom: 5px;
}
.info-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--fv-text-muted);
  margin: 0 0 4px;
}
.inp {
  width: 100%;
  padding: 8px 10px;
  border-radius: 7px;
  font-size: 13px;
  box-sizing: border-box;
}
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 0.8s linear infinite; }
</style>