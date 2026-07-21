<template>
    <AuthenticatedLayout>
        <div class="p-4 md:p-6 space-y-6">

            <!-- ── Header ──────────────────────────────────────────────────── -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <a :href="route('company.reports.index', company.id)"
                        class="fv-action-btn" title="Back to Reports">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold" style="color:var(--fv-text-primary)">
                            {{ editingReport ? editingReport.name : 'Custom Report Builder' }}
                        </h1>
                        <p class="text-sm mt-0.5" style="color:var(--fv-text-muted)">
                            Build an analytical view from your property data
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showSaveModal = true" :disabled="!canRun"
                        class="fv-btn-secondary text-sm px-4 py-2 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4 mr-1.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        {{ editingReport ? 'Update Report' : 'Save Report' }}
                    </button>
                    <button @click="exportReport" :disabled="results.rows.length === 0 || exporting"
                        class="fv-btn-gold text-sm px-4 py-2 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4 mr-1.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        {{ exporting ? 'Exporting…' : 'Export Excel' }}
                    </button>
                </div>
            </div>

            <!-- ── Builder Panel ───────────────────────────────────────────── -->
            <div class="fv-card" style="padding: 0; overflow: hidden;">

                <!-- Step 1 — Data Source -->
                <div class="builder-section">
                    <div class="builder-step-label">
                        <span class="step-number">1</span>
                        Data Source
                    </div>
                    <div class="builder-step-body">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <button v-for="src in dataSources" :key="src.key"
                                @click="selectSource(src.key)"
                                :class="['source-card', config.data_source === src.key ? 'source-card-active' : '']">
                                <div class="source-icon" v-html="src.icon"></div>
                                <div class="text-sm font-semibold mt-2" style="color:var(--fv-text-primary)">{{ src.label }}</div>
                                <div class="text-xs mt-0.5" style="color:var(--fv-text-muted)">{{ src.desc }}</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2 — Dimensions -->
                <div class="builder-section" :class="!config.data_source ? 'opacity-40 pointer-events-none' : ''">
                    <div class="builder-step-label">
                        <span class="step-number">2</span>
                        Dimensions
                        <span class="step-hint">How to group / slice the data (up to 3 levels)</span>
                    </div>
                    <div class="builder-step-body">
                        <div class="flex flex-wrap gap-3">
                            <div v-for="(dim, idx) in [0,1,2]" :key="idx" class="flex items-center gap-2">
                                <span class="text-xs font-bold" style="color:var(--fv-text-muted)">
                                    Group {{ idx + 1 }}{{ idx === 0 ? ' *' : '' }}
                                </span>
                                <select v-model="config.dimensions[idx]" class="fv-select text-sm" style="min-width:180px;"
                                    @change="onDimChange">
                                    <option value="">— None —</option>
                                    <option v-for="d in availableDimensions" :key="d.key"
                                        :value="d.key"
                                        :disabled="isDimUsed(d.key, idx)">
                                        {{ d.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3 — Measures -->
                <div class="builder-section" :class="!config.data_source ? 'opacity-40 pointer-events-none' : ''">
                    <div class="builder-step-label">
                        <span class="step-number">3</span>
                        Measures
                        <span class="step-hint">Which numbers to calculate</span>
                    </div>
                    <div class="builder-step-body">
                        <div class="flex flex-wrap gap-3">
                            <label v-for="m in availableMeasures" :key="m.key"
                                class="measure-checkbox">
                                <input type="checkbox" :value="m.key" v-model="config.measures"
                                    class="w-4 h-4 rounded" style="accent-color:var(--fv-gold)">
                                <span class="text-sm" style="color:var(--fv-text-primary)">{{ m.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 4 — Filters -->
                <div class="builder-section" :class="!config.data_source ? 'opacity-40 pointer-events-none' : ''">
                    <div class="builder-step-label">
                        <span class="step-number">4</span>
                        Filters
                    </div>
                    <div class="builder-step-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                            <!-- Date Range - always shown -->
                            <div>
                                <label class="fv-text-label block mb-1">From Date *</label>
                                <input type="date" v-model="config.filters.start_date" class="fv-input text-sm w-full">
                            </div>
                            <div>
                                <label class="fv-text-label block mb-1">To Date *</label>
                                <input type="date" v-model="config.filters.end_date" class="fv-input text-sm w-full">
                            </div>

                            <!-- Expense Date Mode — only for property_expenses, fills the 3rd column slot -->
                            <div v-if="config.data_source === 'property_expenses'">
                                <label class="fv-text-label block mb-2">Date Filter Applies To</label>
                                <div class="flex flex-col gap-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio"
                                            v-model="config.filters.expense_date_mode"
                                            value="expense_date"
                                            style="accent-color:var(--fv-gold); width:16px; height:16px;">
                                        <span class="text-sm" style="color:var(--fv-text-primary)">Expense Date</span>
                                        <span class="text-xs" style="color:var(--fv-text-muted)">(when committed)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio"
                                            v-model="config.filters.expense_date_mode"
                                            value="payment_date"
                                            style="accent-color:var(--fv-gold); width:16px; height:16px;">
                                        <span class="text-sm" style="color:var(--fv-text-primary)">Payment Date</span>
                                        <span class="text-xs" style="color:var(--fv-text-muted)">(when paid)</span>
                                    </label>
                                </div>
                            </div>
                            <!-- Empty spacer for non-expense sources to keep grid aligned -->
                            <div v-else></div>

                            <!-- Governorate -->
                            <div>
                                <label class="fv-text-label block mb-1">Governorate</label>
                                <select v-model="config.filters.governorate" multiple class="fv-select text-sm w-full" style="height:80px;">
                                    <option v-for="g in filterOptions.governorates" :key="g" :value="g">{{ g }}</option>
                                </select>
                                <p class="text-xs mt-1" style="color:var(--fv-text-muted)">Hold Ctrl to select multiple</p>
                            </div>

                            <!-- Property -->
                            <div>
                                <label class="fv-text-label block mb-1">
                                    Property
                                    <span v-if="propertyTypeSelected" class="ml-1 text-xs font-normal" style="color:var(--fv-gold)">(disabled — Property Type is active)</span>
                                </label>
                                <select
                                    v-model="config.filters.property_id"
                                    multiple
                                    :disabled="propertyTypeSelected"
                                    @change="onPropertySelected"
                                    class="fv-select text-sm w-full"
                                    :style="propertyTypeSelected ? 'height:80px; opacity:0.35; cursor:not-allowed;' : 'height:80px;'">
                                    <option v-for="p in filterOptions.properties" :key="p.id" :value="p.id">{{ p.property_name }}</option>
                                </select>
                                <p class="text-xs mt-1" style="color:var(--fv-text-muted)">Hold Ctrl to select multiple</p>
                            </div>

                            <!-- Property Type -->
                            <div>
                                <label class="fv-text-label block mb-1">
                                    Property Type
                                    <span v-if="propertySelected" class="ml-1 text-xs font-normal" style="color:var(--fv-gold)">(disabled — Property is active)</span>
                                </label>
                                <select
                                    v-model="config.filters.property_type_id"
                                    multiple
                                    :disabled="propertySelected"
                                    @change="onPropertyTypeSelected"
                                    class="fv-select text-sm w-full"
                                    :style="propertySelected ? 'height:80px; opacity:0.35; cursor:not-allowed;' : 'height:80px;'">
                                    <option v-for="pt in filterOptions.property_types" :key="pt.id" :value="pt.id">{{ pt.type_name }}</option>
                                </select>
                                <p class="text-xs mt-1" style="color:var(--fv-text-muted)">Hold Ctrl to select multiple</p>
                            </div>

                            <!-- Expense Category — only for property_expenses -->
                            <div v-if="config.data_source === 'property_expenses'">
                                <label class="fv-text-label block mb-1">Expense Category</label>
                                <select v-model="config.filters.expense_category_id" multiple class="fv-select text-sm w-full" style="height:80px;">
                                    <option v-for="ec in filterOptions.expense_categories" :key="ec.id" :value="ec.id">{{ ec.category_name }}</option>
                                </select>
                                <p class="text-xs mt-1" style="color:var(--fv-text-muted)">Hold Ctrl to select multiple</p>
                            </div>

                            <!-- Revenue Type — rent sources -->
                            <div v-if="config.data_source === 'rent_collections' || config.data_source === 'rent_revenues'">
                                <label class="fv-text-label block mb-1">Revenue Type</label>
                                <select v-model="config.filters.revenue_type" multiple class="fv-select text-sm w-full" style="height:80px;">
                                    <option value="direct_rent">Direct Rent</option>
                                    <option value="management_fee">Management Fee</option>
                                </select>
                            </div>

                            <!-- Collection Status — rent_collections -->
                            <div v-if="config.data_source === 'rent_collections'">
                                <label class="fv-text-label block mb-1">Collection Status</label>
                                <select v-model="config.filters.status" multiple class="fv-select text-sm w-full" style="height:80px;">
                                    <option value="pending">Pending</option>
                                    <option value="collected">Collected</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>

                            <!-- Installment Status — installment_dues -->
                            <div v-if="config.data_source === 'installment_dues'">
                                <label class="fv-text-label block mb-1">Installment Status</label>
                                <select v-model="config.filters.status" multiple class="fv-select text-sm w-full" style="height:80px;">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>

                            <!-- Due Type — installment_dues -->
                            <div v-if="config.data_source === 'installment_dues'">
                                <label class="fv-text-label block mb-1">Due Type</label>
                                <select v-model="config.filters.due_type" multiple class="fv-select text-sm w-full" style="height:80px;">
                                    <option value="signing">Signing</option>
                                    <option value="reservation">Reservation</option>
                                    <option value="installment">Installment</option>
                                    <option value="annual">Annual</option>
                                    <option value="delivery">Delivery</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="variable">Variable</option>
                                </select>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Run Button -->
                <div class="px-6 py-4" style="border-top:1px solid var(--fv-border);">
                    <button @click="runReport" :disabled="!canRun || loading"
                        class="fv-btn-gold px-8 py-2.5 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg v-if="loading" class="w-4 h-4 mr-2 inline animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        <svg v-else class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ loading ? 'Running…' : 'Run Report' }}
                    </button>
                    <span v-if="errorMsg" class="ml-4 text-sm" style="color:#F87171;">{{ errorMsg }}</span>
                </div>
            </div>

            <!-- ── Results Table ────────────────────────────────────────────── -->
            <div v-if="results.columns.length > 0" class="fv-card" style="padding:0; overflow:hidden;">

                <!-- Results header -->
                <div class="flex items-center justify-between px-5 py-3" style="border-bottom:1px solid var(--fv-border);">
                    <div>
                        <span class="text-sm font-semibold" style="color:var(--fv-text-primary)">Results</span>
                        <span class="ml-2 text-sm" style="color:var(--fv-text-muted)">{{ results.rows.length }} rows</span>
                    </div>
                    <div class="text-xs" style="color:var(--fv-text-muted)">
                        {{ config.filters.start_date }} → {{ config.filters.end_date }}
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background:var(--fv-bg-header)">
                                <th v-for="col in results.columns" :key="col.key"
                                    class="px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap"
                                    :style="col.type === 'measure' ? 'text-align:right; color:var(--fv-gold)' : 'text-align:left; color:var(--fv-text-label)'">
                                    {{ col.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, ri) in results.rows" :key="ri"
                                style="border-bottom:1px solid var(--fv-border)"
                                :style="ri % 2 === 0 ? 'background:var(--fv-bg-card)' : 'background:var(--fv-bg)'">
                                <td v-for="col in results.columns" :key="col.key"
                                    class="px-4 py-2.5 whitespace-nowrap"
                                    :style="col.type === 'measure' ? 'text-align:right; color:var(--fv-text-primary); font-variant-numeric:tabular-nums' : 'color:var(--fv-text-primary)'">
                                    <template v-if="col.type === 'measure'">
                                        {{ formatNum(row[col.key]) }}
                                    </template>
                                    <template v-else>
                                        {{ row[col.key] ?? '—' }}
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                        <!-- Totals row -->
                        <tfoot>
                            <tr style="border-top:2px solid var(--fv-gold); background:var(--fv-bg-header)">
                                <td v-for="(col, ci) in results.columns" :key="col.key"
                                    class="px-4 py-2.5 whitespace-nowrap font-bold"
                                    :style="col.type === 'measure' ? 'text-align:right; color:var(--fv-gold)' : 'color:var(--fv-text-muted)'">
                                    <template v-if="ci === 0 && col.type === 'dimension'">TOTAL</template>
                                    <template v-else-if="col.type === 'measure'">{{ formatNum(results.totals[col.key]) }}</template>
                                    <template v-else>—</template>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Empty state after run with no data -->
            <div v-else-if="ranOnce && !loading" class="fv-card text-center py-12">
                <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--fv-text-muted)">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <p style="color:var(--fv-text-muted)">No data found for the selected filters.</p>
            </div>

        </div>

        <!-- ── Save Modal ──────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showSaveModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4"
                style="background:rgba(0,0,0,0.6)">
                <div class="fv-card w-full max-w-md" style="padding:24px">
                    <h3 class="text-lg font-bold mb-4" style="color:var(--fv-text-primary)">
                        {{ editingReport ? 'Update Report' : 'Save Report' }}
                    </h3>
                    <label class="fv-text-label block mb-1">Report Name *</label>
                    <input v-model="saveName" type="text" class="fv-input w-full mb-4"
                        placeholder="e.g. Q1 Collections by Governorate"
                        @keyup.enter="confirmSave">
                    <div class="flex gap-3 justify-end">
                        <button @click="showSaveModal = false" class="fv-btn-secondary px-4 py-2 text-sm">Cancel</button>
                        <button @click="confirmSave" :disabled="!saveName.trim() || saving"
                            class="fv-btn-gold px-5 py-2 text-sm disabled:opacity-40">
                            {{ saving ? 'Saving…' : (editingReport ? 'Update' : 'Save') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AuthenticatedLayout>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

export default defineComponent({
    components: { AuthenticatedLayout },

    props: {
        company:       { type: Object, required: true },
        report:        { type: Object, default: null },
        filterOptions: { type: Object, required: true },
    },

    setup(props) {

        // ── Data Source definitions ─────────────────────────────────────────
        const dataSources = [
            {
                key: 'rent_collections',
                label: 'Rent Collections',
                desc: 'Actual payments collected',
                icon: '<svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#1490A8"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>'
            },
            {
                key: 'rent_revenues',
                label: 'Rent Revenues',
                desc: 'Scheduled revenue schedule',
                icon: '<svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#BA7517"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>'
            },
            {
                key: 'property_expenses',
                label: 'Property Expenses',
                desc: 'Committed & paid expenses',
                icon: '<svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#F87171"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>'
            },
            {
                key: 'installment_dues',
                label: 'Installment Dues',
                desc: 'Property purchase installments',
                icon: '<svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#A78BFA"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
            },
        ]

        // ── Dimension definitions per source ───────────────────────────────
        const allDimensions = {
            rent_collections: [
                { key: 'governorate',   label: 'Governorate' },
                { key: 'province',      label: 'Province' },
                { key: 'property_name', label: 'Property Name' },
                { key: 'property_type', label: 'Property Type' },
                { key: 'unit_name',     label: 'Unit Name' },
                { key: 'tenant_name',   label: 'Tenant Name' },
                { key: 'tenant_nature', label: 'Tenant Nature' },
                { key: 'revenue_type',  label: 'Revenue Type' },
                { key: 'month',         label: 'Month' },
                { key: 'quarter',       label: 'Quarter' },
                { key: 'year',          label: 'Year' },
            ],
            rent_revenues: [
                { key: 'governorate',   label: 'Governorate' },
                { key: 'province',      label: 'Province' },
                { key: 'property_name', label: 'Property Name' },
                { key: 'property_type', label: 'Property Type' },
                { key: 'unit_name',     label: 'Unit Name' },
                { key: 'tenant_name',   label: 'Tenant Name' },
                { key: 'tenant_nature', label: 'Tenant Nature' },
                { key: 'revenue_type',  label: 'Revenue Type' },
                { key: 'month',         label: 'Month' },
                { key: 'quarter',       label: 'Quarter' },
                { key: 'year',          label: 'Year' },
            ],
            property_expenses: [
                { key: 'governorate',      label: 'Governorate' },
                { key: 'province',         label: 'Province' },
                { key: 'property_name',    label: 'Property Name' },
                { key: 'property_type',    label: 'Property Type' },
                { key: 'expense_category', label: 'Expense Category' },
                { key: 'expense_item',     label: 'Expense Item' },
                { key: 'currency',         label: 'Currency' },
                { key: 'month',            label: 'Month' },
                { key: 'quarter',          label: 'Quarter' },
                { key: 'year',             label: 'Year' },
            ],
            installment_dues: [
                { key: 'governorate',   label: 'Governorate' },
                { key: 'province',      label: 'Province' },
                { key: 'property_name', label: 'Property Name' },
                { key: 'property_type', label: 'Property Type' },
                { key: 'due_type',      label: 'Due Type' },
                { key: 'currency',      label: 'Currency' },
                { key: 'month',         label: 'Month' },
                { key: 'quarter',       label: 'Quarter' },
                { key: 'year',          label: 'Year' },
            ],
        }

        // ── Measure definitions per source ─────────────────────────────────
        const allMeasures = {
            rent_collections: [
                { key: 'amount_collected', label: 'Amount Collected' },
                { key: 'collection_count', label: 'Collection Count' },
            ],
            rent_revenues: [
                { key: 'revenue_amount', label: 'Revenue Amount' },
                { key: 'revenue_count',  label: 'Revenue Count' },
            ],
            property_expenses: [
                { key: 'committed_amount',   label: 'Committed Amount' },
                { key: 'paid_amount',        label: 'Paid Amount' },
                { key: 'outstanding_amount', label: 'Outstanding' },
                { key: 'payment_count',      label: 'Expense Count' },
            ],
            installment_dues: [
                { key: 'due_amount',         label: 'Due Amount' },
                { key: 'paid_amount',        label: 'Paid Amount' },
                { key: 'outstanding_amount', label: 'Outstanding' },
                { key: 'due_count',          label: 'Due Count' },
            ],
        }

        // ── State ───────────────────────────────────────────────────────────
        const config = ref({
            data_source: '',
            dimensions:  ['', '', ''],
            measures:    [],
            filters: {
                start_date:          new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
                end_date:            new Date().toISOString().slice(0, 10),
                expense_date_mode:   'expense_date',
                governorate:         [],
                property_id:         [],
                property_type_id:    [],
                expense_category_id: [],
                revenue_type:        [],
                status:              [],
                due_type:            [],
            },
        })

        const results    = ref({ columns: [], rows: [], totals: {} })
        const loading    = ref(false)
        const exporting  = ref(false)
        const saving     = ref(false)
        const errorMsg   = ref('')
        const ranOnce    = ref(false)
        const showSaveModal = ref(false)
        const saveName   = ref('')

        // ── Load saved report if editing ────────────────────────────────────
        const editingReport = ref(props.report || null)

        onMounted(() => {
            if (props.report) {
                config.value.data_source = props.report.data_source
                const dims = props.report.dimensions || []
                config.value.dimensions = [dims[0] || '', dims[1] || '', dims[2] || '']
                config.value.measures   = props.report.measures || []
                // Merge saved filters over defaults (preserves expense_date_mode if saved)
                config.value.filters = { ...config.value.filters, ...props.report.filters }
                saveName.value = props.report.name
            }
        })

        // ── Computed ────────────────────────────────────────────────────────
        const availableDimensions = computed(() =>
            config.value.data_source ? (allDimensions[config.value.data_source] || []) : []
        )

        const availableMeasures = computed(() =>
            config.value.data_source ? (allMeasures[config.value.data_source] || []) : []
        )

        const canRun = computed(() => {
            const dims = config.value.dimensions.filter(Boolean)
            return config.value.data_source &&
                dims.length >= 1 &&
                config.value.measures.length >= 1 &&
                config.value.filters.start_date &&
                config.value.filters.end_date
        })

        // Property and Property Type are mutually exclusive filters.
        // If one has selections, the other is disabled and its value is cleared.
        const propertySelected     = computed(() => config.value.filters.property_id.length > 0)
        const propertyTypeSelected = computed(() => config.value.filters.property_type_id.length > 0)

        // ── Methods ─────────────────────────────────────────────────────────
        function selectSource(key) {
            if (config.value.data_source === key) return
            config.value.data_source = key
            config.value.dimensions  = ['', '', '']
            config.value.measures    = []
            results.value            = { columns: [], rows: [], totals: {} }
            ranOnce.value            = false
        }

        function onDimChange() {
            // nothing extra needed — v-model handles it
        }

        // When user picks a Property, clear and disable Property Type
        function onPropertySelected() {
            if (config.value.filters.property_id.length > 0) {
                config.value.filters.property_type_id = []
            }
        }

        // When user picks a Property Type, clear and disable Property
        function onPropertyTypeSelected() {
            if (config.value.filters.property_type_id.length > 0) {
                config.value.filters.property_id = []
            }
        }

        function isDimUsed(key, currentIdx) {
            return config.value.dimensions.some((d, i) => d === key && i !== currentIdx)
        }

        function formatNum(val) {
            if (val === null || val === undefined || val === '') return '—'
            const n = parseFloat(val)
            if (isNaN(n)) return val
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        }

        function buildPayload() {
            return {
                data_source: config.value.data_source,
                dimensions:  config.value.dimensions.filter(Boolean),
                measures:    config.value.measures,
                filters:     config.value.filters,
                report_id:   editingReport.value?.id ?? null,
            }
        }

        async function runReport() {
            if (!canRun.value) return
            loading.value  = true
            errorMsg.value = ''
            ranOnce.value  = true

            try {
                const res = await axios.post(
                    route('company.reports.custom.run', { company: props.company.id }),
                    buildPayload()
                )
                results.value = res.data
            } catch (e) {
                errorMsg.value = e.response?.data?.message || 'Request failed: ' + e.message
            } finally {
                loading.value = false
            }
        }

        async function exportReport() {
            if (results.value.rows.length === 0) return
            exporting.value = true

            try {
                const payload = {
                    ...buildPayload(),
                    report_name: editingReport.value?.name || saveName.value || 'Custom Report',
                }
                const res = await axios.post(
                    route('company.reports.custom.export', { company: props.company.id }),
                    payload,
                    { responseType: 'blob' }
                )
                const url   = URL.createObjectURL(new Blob([res.data]))
                const cd    = res.headers['content-disposition'] || ''
                const match = cd.match(/filename="?([^"]+)"?/)
                const a     = document.createElement('a')
                a.href      = url
                a.download  = match ? match[1] : 'custom_report.xlsx'
                a.click()
                URL.revokeObjectURL(url)
            } catch (e) {
                console.error('Export failed', e)
            } finally {
                exporting.value = false
            }
        }

        function confirmSave() {
            if (!saveName.value.trim() || saving.value) return
            saving.value = true

            const payload = {
                name:        saveName.value.trim(),
                data_source: config.value.data_source,
                dimensions:  config.value.dimensions.filter(Boolean),
                measures:    config.value.measures,
                filters:     config.value.filters,
            }

            if (editingReport.value) {
                router.put(
                    route('company.reports.custom.update', { company: props.company.id, report: editingReport.value.id }),
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => { showSaveModal.value = false; saving.value = false },
                        onError:   () => { saving.value = false },
                    }
                )
            } else {
                router.post(
                    route('company.reports.custom.store', { company: props.company.id }),
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            showSaveModal.value = false
                            saving.value = false
                            router.visit(route('company.reports.index', props.company.id))
                        },
                        onError: () => { saving.value = false },
                    }
                )
            }
        }

        return {
            dataSources, config, results, loading, exporting, saving,
            errorMsg, ranOnce, showSaveModal, saveName, editingReport,
            availableDimensions, availableMeasures, canRun,
            propertySelected, propertyTypeSelected,
            selectSource, onDimChange, isDimUsed, formatNum,
            onPropertySelected, onPropertyTypeSelected,
            runReport, exportReport, confirmSave,
        }
    }
})
</script>

<style scoped>
.builder-section {
    border-bottom: 1px solid var(--fv-border);
    display: grid;
    grid-template-columns: 200px 1fr;
    transition: opacity 0.2s;
}

@media (max-width: 768px) {
    .builder-section { grid-template-columns: 1fr; }
    .builder-step-label { border-right: none; border-bottom: 1px solid var(--fv-border); }
}

.builder-step-label {
    padding: 20px 20px;
    font-size: 13px;
    font-weight: 700;
    color: var(--fv-text-primary);
    border-right: 1px solid var(--fv-border);
    background: var(--fv-bg-header);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--fv-blue);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
}

.step-hint {
    font-size: 11px;
    font-weight: 400;
    color: var(--fv-text-muted);
}

.builder-step-body {
    padding: 20px 24px;
}

.source-card {
    border: 1px solid var(--fv-border);
    border-radius: 10px;
    padding: 16px 12px;
    text-align: center;
    background: var(--fv-bg-card);
    cursor: pointer;
    transition: all 0.15s;
}

.source-card:hover {
    border-color: var(--fv-blue);
    background: var(--fv-bg-input);
}

.source-card-active {
    border-color: var(--fv-gold) !important;
    background: rgba(186, 117, 23, 0.08) !important;
    box-shadow: 0 0 0 2px rgba(186, 117, 23, 0.2);
}

.measure-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border: 1px solid var(--fv-border);
    border-radius: 8px;
    background: var(--fv-bg-card);
    cursor: pointer;
    transition: border-color 0.15s;
}

.measure-checkbox:hover {
    border-color: var(--fv-gold);
}
</style>
