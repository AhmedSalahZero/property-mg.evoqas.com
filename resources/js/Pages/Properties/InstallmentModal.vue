<template>
    <!-- ── Backdrop ─────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-16 pb-4"
                style="background: rgba(0,0,0,0.6); overflow-y: auto;"
                @mousedown.self="$emit('close')"
            >
                <!-- ── Modal Shell ───────────────────────────────────────── -->
                <div
                    class="fv-modal rounded-xl w-full flex flex-col"
                    style="max-width:780px; max-height:calc(100vh - 96px); margin-bottom:1rem;"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b fv-divider flex-shrink-0">
                        <div>
                            <h2 class="font-semibold text-base fv-text-primary">Due Installments</h2>
                            <p class="text-xs fv-text-muted mt-0.5">{{ property.property_name }}</p>
                        </div>
                        <button @click="$emit('close')" class="fv-action-btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Loading state -->
                    <div v-if="loading" class="flex items-center justify-center py-16">
                        <div class="w-6 h-6 border-2 border-t-transparent rounded-full animate-spin" style="border-color: var(--fv-blue)"></div>
                    </div>

                    <template v-else>
                        <!-- ── Tab Bar (Plan / Schedule) ────────────────── -->
                        <div class="flex border-b fv-divider flex-shrink-0 px-6">
                            <button
                                v-for="tab in tabs" :key="tab.key"
                                @click="activeTab = tab.key"
                                class="py-3 px-1 mr-6 text-sm font-medium border-b-2 transition-colors"
                                :style="activeTab === tab.key
                                    ? 'border-color: var(--fv-blue); color: var(--fv-blue)'
                                    : 'border-color: transparent; color: var(--fv-text-muted)'"
                            >{{ tab.label }}</button>
                        </div>

                        <!-- Scrollable body -->
                        <div class="overflow-y-auto flex-1 px-6 py-5">

                            <!-- ════════════════════════════════════════════
                                 TAB 1 — PLAN (form)
                            ═════════════════════════════════════════════ -->
                            <div v-if="activeTab === 'plan'">

                                <!-- Type + Currency row -->
                                <div class="flex items-center gap-6 mb-5">
                                    <!-- Installment Type -->
                                    <div class="flex items-center gap-4">
                                        <label
                                            v-for="opt in typeOptions" :key="opt.value"
                                            class="flex items-center gap-2 cursor-pointer"
                                        >
                                            <input
                                                type="radio"
                                                :value="opt.value"
                                                v-model="form.installment_type"
                                                class="accent-teal-400"
                                            />
                                            <span class="text-sm fv-text-primary">{{ opt.label }}</span>
                                        </label>
                                    </div>
                                    <!-- Currency -->
                                    <div class="ml-auto flex items-center gap-2">
                                        <label class="text-xs fv-text-label">Currency</label>
                                        <select v-model="form.currency" class="fv-select rounded-lg px-3 py-1.5 text-sm" style="min-width:90px">
                                            <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Delivery + Ready to Use -->
                                <div class="grid grid-cols-2 gap-4 mb-5">
                                    <div>
                                        <label class="block text-xs fv-text-label mb-1">Delivery Date</label>
                                        <MonthYearPicker v-model="form.delivery_date" />
                                    </div>
                                    <div>
                                        <label class="block text-xs fv-text-label mb-1">Ready to Use Date</label>
                                        <MonthYearPicker v-model="form.ready_to_use_date" />
                                    </div>
                                </div>

                                <!-- ── REGULAR ─────────────────────────── -->
                                <template v-if="form.installment_type === 'regular'">

                                    <!-- Signing + Reservation -->
                                    <div class="grid grid-cols-2 gap-4 mb-5">
                                        <div>
                                            <label class="block text-xs fv-text-label mb-1">Contract Signing Payment</label>
                                            <input v-model="form.signing_amount" type="number" min="0" placeholder="0" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs fv-text-label mb-1">Date</label>
                                            <MonthYearPicker v-model="form.signing_date" />
                                        </div>
                                        <div>
                                            <label class="block text-xs fv-text-label mb-1">Reservation Payment</label>
                                            <input v-model="form.reservation_amount" type="number" min="0" placeholder="0" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs fv-text-label mb-1">Date</label>
                                            <MonthYearPicker v-model="form.reservation_date" />
                                        </div>
                                    </div>

                                    <!-- Installment Rows Repeater -->
                                    <div class="mb-4">
                                        <div
                                            v-for="(row, idx) in form.installment_rows"
                                            :key="idx"
                                            class="grid gap-3 mb-3 items-end"
                                            style="grid-template-columns: 2fr 1fr 2fr 2fr auto"
                                        >
                                            <div>
                                                <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Installment Amount</label>
                                                <input v-model="row.amount" type="number" min="0" placeholder="0" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Count</label>
                                                <input v-model="row.count" type="number" min="1" placeholder="1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Start Date</label>
                                                <MonthYearPicker v-model="row.start_date" />
                                            </div>
                                            <div>
                                                <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Payment Interval</label>
                                                <select v-model="row.interval" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                                                    <option value="monthly">Monthly</option>
                                                    <option value="quarterly">Quarterly</option>
                                                    <option value="semi_annually">Semi-Annually</option>
                                                </select>
                                            </div>
                                            <div class="flex items-end pb-0.5">
                                                <button
                                                    v-if="idx > 0"
                                                    @click="removeRow(idx)"
                                                    class="fv-action-btn fv-action-btn-danger"
                                                    title="Remove row"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                                <div v-else class="w-8"></div>
                                            </div>
                                        </div>
                                        <button @click="addRow" class="fv-btn-gold text-xs font-semibold px-4 py-1.5 rounded-lg mt-1">
                                            + Add Row
                                        </button>
                                    </div>

                                    <!-- Optional Sections -->
                                    <!-- Annual -->
                                    <div class="border fv-border rounded-lg p-4 mb-3">
                                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                                            <input type="checkbox" v-model="form.has_annual" class="accent-teal-400 w-4 h-4" />
                                            <span class="text-sm font-medium fv-text-primary">Add Annual Installments</span>
                                        </label>
                                        <div v-if="form.has_annual" class="grid grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Start Date</label>
                                                <MonthYearPicker v-model="form.annual_start_date" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Annual Amount</label>
                                                <input v-model="form.annual_amount" type="number" min="0" placeholder="0" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Annual Count</label>
                                                <input v-model="form.annual_count" type="number" min="1" placeholder="1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delivery -->
                                    <div class="border fv-border rounded-lg p-4 mb-3">
                                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                                            <input type="checkbox" v-model="form.has_delivery" class="accent-teal-400 w-4 h-4" />
                                            <span class="text-sm font-medium fv-text-primary">Add Delivery Payments</span>
                                        </label>
                                        <div v-if="form.has_delivery" class="grid grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Start Date</label>
                                                <MonthYearPicker v-model="form.delivery_start_date" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Amount</label>
                                                <input v-model="form.delivery_amount" type="number" min="0" placeholder="0" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Count</label>
                                                <input v-model="form.delivery_count" type="number" min="1" placeholder="1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Payment Interval</label>
                                                <select v-model="form.delivery_interval" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                                                    <option value="monthly">Monthly</option>
                                                    <option value="quarterly">Quarterly</option>
                                                    <option value="semi_annually">Semi-Annually</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Maintenance -->
                                    <div class="border fv-border rounded-lg p-4 mb-3">
                                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                                            <input type="checkbox" v-model="form.has_maintenance" class="accent-teal-400 w-4 h-4" />
                                            <span class="text-sm font-medium fv-text-primary">Add Maintenance Payments</span>
                                        </label>
                                        <div v-if="form.has_maintenance" class="grid grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Start Date</label>
                                                <MonthYearPicker v-model="form.maintenance_start_date" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Amount</label>
                                                <input v-model="form.maintenance_amount" type="number" min="0" placeholder="0" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Count</label>
                                                <input v-model="form.maintenance_count" type="number" min="1" placeholder="1" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-xs fv-text-label mb-1">Payment Interval</label>
                                                <select v-model="form.maintenance_interval" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                                                    <option value="monthly">Monthly</option>
                                                    <option value="quarterly">Quarterly</option>
                                                    <option value="semi_annually">Semi-Annually</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                </template>

                                <!-- ── VARIABLE ────────────────────────── -->
                                <template v-else>

                                    <!-- Excel import button -->
                                    <div class="flex items-center justify-between mb-4">
                                        <p class="text-xs fv-text-muted">Enter each payment manually or import from Excel.</p>
                                        <label class="fv-btn-secondary text-xs font-semibold px-4 py-1.5 rounded-lg cursor-pointer flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            Import Excel
                                            <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="importExcel" />
                                        </label>
                                    </div>

                                    <!-- Variable rows -->
                                    <div
                                        v-for="(row, idx) in form.variable_dues"
                                        :key="idx"
                                        class="grid gap-3 mb-3 items-end"
                                        style="grid-template-columns: 2fr 2fr 3fr auto"
                                    >
                                        <div>
                                            <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Date</label>
                                            <input v-model="row.date" type="date" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                        </div>
                                        <div>
                                            <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Amount</label>
                                            <input v-model="row.amount" type="number" min="0" placeholder="0" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                        </div>
                                        <div>
                                            <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Note</label>
                                            <input v-model="row.notes" type="text" placeholder="Optional note" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                                        </div>
                                        <div class="flex items-end pb-0.5">
                                            <button @click="removeVariableRow(idx)" class="fv-action-btn fv-action-btn-danger">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <button @click="addVariableRow" class="fv-btn-gold text-xs font-semibold px-4 py-1.5 rounded-lg">
                                        + Add Row
                                    </button>
                                </template>

                            </div><!-- end plan tab -->

                            <!-- ════════════════════════════════════════════
                                 TAB 2 — SCHEDULE (dues table)
                            ═════════════════════════════════════════════ -->
                            <div v-if="activeTab === 'schedule'">
                                <div v-if="!dues.length" class="text-center py-12 fv-text-muted text-sm">
                                    No schedule yet. Save the plan first.
                                </div>

                                <template v-else>
                                    <!-- Summary strip -->
                                    <div class="grid grid-cols-3 gap-3 mb-5">
                                        <div class="fv-card text-center py-3">
                                            <p class="text-xs fv-text-muted mb-1">Total Due</p>
                                            <p class="text-sm font-bold fv-text-primary">{{ formatMoney(totalDue) }}</p>
                                        </div>
                                        <div class="fv-card text-center py-3">
                                            <p class="text-xs fv-text-muted mb-1">Paid</p>
                                            <p class="text-sm font-bold" style="color:#34d399">{{ formatMoney(totalPaid) }}</p>
                                        </div>
                                        <div class="fv-card text-center py-3">
                                            <p class="text-xs fv-text-muted mb-1">Remaining</p>
                                            <p class="text-sm font-bold" style="color:var(--fv-gold)">{{ formatMoney(totalDue - totalPaid) }}</p>
                                        </div>
                                    </div>

                                    <!-- Dues table -->
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="border-b fv-divider">
                                                    <th class="text-left py-2 pr-4 text-xs fv-text-label font-semibold">Due Date</th>
                                                    <th class="text-left py-2 pr-4 text-xs fv-text-label font-semibold">Type</th>
                                                    <th class="text-right py-2 pr-4 text-xs fv-text-label font-semibold">Amount</th>
                                                    <th class="text-center py-2 pr-4 text-xs fv-text-label font-semibold">Status</th>
                                                    <th class="text-left py-2 text-xs fv-text-label font-semibold">Paid Date</th>
                                                    <th class="py-2"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="due in dues"
                                                    :key="due.id"
                                                    class="border-b fv-divider hover:bg-opacity-50"
                                                    style="transition: background 0.1s"
                                                >
                                                    <td class="py-2 pr-4 fv-text-primary">{{ formatDate(due.due_date) }}</td>
                                                    <td class="py-2 pr-4 fv-text-muted capitalize">{{ typeLabel(due.due_type) }}</td>
                                                    <td class="py-2 pr-4 text-right font-medium fv-text-primary">
                                                        {{ formatMoney(due.amount) }} <span class="text-xs fv-text-muted">{{ due.currency }}</span>
                                                    </td>
                                                    <td class="py-2 pr-4 text-center">
                                                        <span class="fv-badge" :class="statusClass(due.status)">{{ due.status }}</span>
                                                    </td>
                                                    <td class="py-2 fv-text-muted text-xs">{{ due.paid_date ? formatDate(due.paid_date) : '—' }}</td>
                                                    <td class="py-2">
                                                        <button
                                                            v-if="due.status !== 'paid'"
                                                            @click="openMarkPaid(due)"
                                                            class="fv-action-btn"
                                                            title="Mark as Paid"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                            </div><!-- end schedule tab -->

                        </div><!-- end scrollable body -->

                        <!-- ── Footer ────────────────────────────────── -->
                        <div class="flex items-center justify-between px-6 py-4 border-t fv-divider flex-shrink-0">
                            <button @click="$emit('close')" class="fv-btn-secondary text-sm font-medium px-5 py-2 rounded-lg">
                                Close
                            </button>
                            <button
                                v-if="activeTab === 'plan'"
                                @click="savePlan"
                                :disabled="saving"
                                class="fv-btn-gold text-sm font-semibold px-6 py-2 rounded-lg flex items-center gap-2"
                            >
                                <span v-if="saving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span>
                                {{ saving ? 'Saving...' : 'Save & Generate Schedule' }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>

        <!-- ── Mark Paid Mini-Modal ──────────────────────────────────────── -->
        <Transition name="modal">
            <div
                v-if="markPaidModal.show"
                class="fixed inset-0 z-60 flex items-center justify-center"
                style="background: rgba(0,0,0,0.5)"
                @mousedown.self="markPaidModal.show = false"
            >
                <div class="fv-modal rounded-xl p-6 w-full" style="max-width:360px">
                    <h3 class="text-sm font-semibold fv-text-primary mb-4">Mark as Paid</h3>
                    <div class="mb-3">
                        <label class="block text-xs fv-text-label mb-1">Paid Date</label>
                        <input v-model="markPaidModal.paid_date" type="date" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div class="mb-5">
                        <label class="block text-xs fv-text-label mb-1">Notes (optional)</label>
                        <input v-model="markPaidModal.notes" type="text" placeholder="e.g. Bank transfer ref" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button @click="markPaidModal.show = false" class="fv-btn-secondary text-sm px-4 py-2 rounded-lg">Cancel</button>
                        <button @click="submitMarkPaid" :disabled="markPaidModal.saving" class="fv-btn-gold text-sm font-semibold px-5 py-2 rounded-lg">
                            {{ markPaidModal.saving ? 'Saving...' : 'Confirm Paid' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

    </Teleport>
</template>

<script setup>
import { ref, computed, watch, h, Teleport } from 'vue'
import axios from 'axios'

// ── MonthYearPicker — same pattern as Create.vue / Edit.vue ──────────────────
// defineComponent + setup() with refs, no template string, works with Vite.
const MonthYearPicker = {
    props: { modelValue: { type: String, default: '' } },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
        const open     = ref(false)
        const viewYear = ref(new Date().getFullYear())
        const popTop   = ref(0)
        const popLeft  = ref(0)

        const display = computed(() => {
            if (!props.modelValue) return ''
            const [m, y] = props.modelValue.split('/')
            if (!m || !y) return props.modelValue
            return `${MONTHS[parseInt(m) - 1]} ${y}`
        })

        function toggle(e) {
            if (open.value) { open.value = false; return }
            const rect = e.currentTarget.getBoundingClientRect()
            const popH = 192
            popTop.value  = rect.bottom + popH > window.innerHeight ? rect.top - popH - 4 : rect.bottom + 4
            popLeft.value = rect.left
            viewYear.value = props.modelValue
                ? parseInt(props.modelValue.split('/')[1]) || new Date().getFullYear()
                : new Date().getFullYear()
            open.value = true
            setTimeout(() => {
                const handler = () => { open.value = false; document.removeEventListener('click', handler) }
                document.addEventListener('click', handler)
            }, 0)
        }
        function pick(idx) {
            emit('update:modelValue', `${String(idx + 1).padStart(2, '0')}/${viewYear.value}`)
            open.value = false
        }
        function clear() { emit('update:modelValue', ''); open.value = false }
        function isActive(idx) {
            if (!props.modelValue) return false
            const [m, y] = props.modelValue.split('/')
            return parseInt(m) - 1 === idx && parseInt(y) === viewYear.value
        }

        return { open, viewYear, popTop, popLeft, display, toggle, pick, clear, isActive, MONTHS }
    },
    render() {
        const { open, viewYear, popTop, popLeft, display, toggle, pick, clear, isActive, MONTHS } = this

        const trigger = h('div', {
            class: 'fv-input flex items-center justify-between rounded-lg px-3 py-2 cursor-pointer text-sm',
            onClick: toggle,
        }, [
            h('span', {
                style: display ? 'color:var(--fv-text-primary)' : 'color:var(--fv-text-muted)',
            }, display || 'MM/YYYY'),
            h('svg', {
                class: 'w-3.5 h-3.5 ml-2 flex-shrink-0',
                style: 'color:var(--fv-text-muted)',
                fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24',
            }, [
                h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2',
                    d: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' })
            ]),
        ])

        const popup = open ? h(Teleport, { to: 'body' }, [
            h('div', {
                onClick: (e) => e.stopPropagation(),
                style: `position:fixed;z-index:9999;width:224px;top:${popTop}px;left:${popLeft}px;` +
                    'background:var(--fv-bg-modal,#0E1E34);border:1px solid var(--fv-border,#21518B);' +
                    'border-radius:0.5rem;padding:0.75rem;box-shadow:0 8px 40px rgba(0,0,0,0.7);',
            }, [
                // Year navigation
                h('div', { style: 'display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;' }, [
                    h('button', {
                        onClick: (e) => { e.stopPropagation(); this.viewYear-- },
                        style: 'width:1.875rem;height:1.875rem;display:flex;align-items:center;justify-content:center;border-radius:0.45rem;color:var(--fv-text-muted);background:transparent;border:1px solid transparent;cursor:pointer;font-size:1rem;',
                    }, '‹'),
                    h('span', { style: 'font-size:0.875rem;font-weight:600;color:var(--fv-text-primary);' }, viewYear),
                    h('button', {
                        onClick: (e) => { e.stopPropagation(); this.viewYear++ },
                        style: 'width:1.875rem;height:1.875rem;display:flex;align-items:center;justify-content:center;border-radius:0.45rem;color:var(--fv-text-muted);background:transparent;border:1px solid transparent;cursor:pointer;font-size:1rem;',
                    }, '›'),
                ]),
                // Month grid
                h('div', { style: 'display:grid;grid-template-columns:repeat(3,1fr);gap:0.25rem;' },
                    MONTHS.map((m, i) => h('button', {
                        key: i,
                        onClick: (e) => { e.stopPropagation(); pick(i) },
                        style: isActive(i)
                            ? 'padding:0.25rem;border-radius:0.35rem;font-size:0.75rem;font-weight:500;border:none;cursor:pointer;background:var(--fv-blue);color:#fff;'
                            : 'padding:0.25rem;border-radius:0.35rem;font-size:0.75rem;font-weight:500;border:none;cursor:pointer;background:transparent;color:var(--fv-text-primary);',
                    }, m))
                ),
                // Clear button
                h('button', {
                    onClick: (e) => { e.stopPropagation(); clear() },
                    style: 'margin-top:0.5rem;width:100%;font-size:0.75rem;color:var(--fv-text-muted);background:transparent;border:none;cursor:pointer;text-align:center;',
                }, 'Clear'),
            ])
        ]) : null

        return h('div', {}, [trigger, popup])
    },
}

// ── Props & Emits ─────────────────────────────────────────────────────────────
const props = defineProps({
    show:     { type: Boolean, default: false },
    company:  { type: Object, required: true },
    property: { type: Object, required: true },
})
const emit = defineEmits(['close'])

// ── State ─────────────────────────────────────────────────────────────────────
const loading   = ref(false)
const saving    = ref(false)
const activeTab = ref('plan')
const dues      = ref([])

const tabs = [
    { key: 'plan',     label: 'Installment Plan' },
    { key: 'schedule', label: 'Payment Schedule' },
]

const typeOptions = [
    { value: 'regular',  label: 'Regular Installment' },
    { value: 'variable', label: 'Variable Installment' },
]

const currencies = ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED']

const defaultForm = () => ({
    installment_type:    'regular',
    currency:            props.property?.currency ?? 'EGP',
    delivery_date:       '',
    ready_to_use_date:   '',
    signing_amount:      0,
    signing_date:        '',
    reservation_amount:  0,
    reservation_date:    '',
    installment_rows:    [{ amount: 0, count: 1, start_date: '', interval: 'monthly' }],
    has_annual:          false,
    annual_start_date:   '',
    annual_amount:       0,
    annual_count:        1,
    has_delivery:        false,
    delivery_start_date: '',
    delivery_amount:     0,
    delivery_count:      1,
    delivery_interval:   'monthly',
    has_maintenance:     false,
    maintenance_start_date: '',
    maintenance_amount:  0,
    maintenance_count:   1,
    maintenance_interval: 'monthly',
    variable_dues:       [{ date: '', amount: 0, notes: '' }],
})

const form = ref(defaultForm())

// ── Mark Paid Modal ───────────────────────────────────────────────────────────
const markPaidModal = ref({ show: false, due: null, paid_date: '', notes: '', saving: false })

// ── Load data when modal opens ────────────────────────────────────────────────
watch(() => props.show, async (val) => {
    if (!val) return
    activeTab.value = 'plan'
    await loadPlan()
})

async function loadPlan() {
    loading.value = true
    try {
        const { data } = await axios.get(
            route('company.properties.installments.load', [props.company.id, props.property.id])
        )
        dues.value = data.dues ?? []

        if (data.plan) {
            const p = data.plan
            form.value = {
                installment_type:    p.installment_type ?? 'regular',
                currency:            p.currency ?? props.property?.currency ?? 'EGP',
                delivery_date:       p.delivery_date ?? '',
                ready_to_use_date:   p.ready_to_use_date ?? '',
                signing_amount:      p.signing_amount ?? 0,
                signing_date:        p.signing_date ?? '',
                reservation_amount:  p.reservation_amount ?? 0,
                reservation_date:    p.reservation_date ?? '',
                installment_rows:    p.installment_rows?.length
                    ? p.installment_rows.map(r => ({ ...r, amount: r.amount ?? 0, count: r.count ?? 1 }))
                    : [{ amount: 0, count: 1, start_date: '', interval: 'monthly' }],
                has_annual:          !!p.has_annual,
                annual_start_date:   p.annual_start_date ?? '',
                annual_amount:       p.annual_amount ?? 0,
                annual_count:        p.annual_count ?? 1,
                has_delivery:        !!p.has_delivery,
                delivery_start_date: p.delivery_start_date ?? '',
                delivery_amount:     p.delivery_amount ?? 0,
                delivery_count:      p.delivery_count ?? 1,
                delivery_interval:   p.delivery_interval ?? 'monthly',
                has_maintenance:     !!p.has_maintenance,
                maintenance_start_date: p.maintenance_start_date ?? '',
                maintenance_amount:  p.maintenance_amount ?? 0,
                maintenance_count:   p.maintenance_count ?? 1,
                maintenance_interval: p.maintenance_interval ?? 'monthly',
                variable_dues:       dues.value.length && p.installment_type === 'variable'
                    ? dues.value.map(d => ({ date: d.due_date, amount: d.amount, notes: d.notes ?? '' }))
                    : [{ date: '', amount: '', notes: '' }],
            }
        } else {
            form.value = defaultForm()
            form.value.currency = data.currency ?? 'EGP'
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

// ── Validation ────────────────────────────────────────────────────────────────
function validate() {
    const f = form.value
    const errors = []

    // Always required
    if (!f.delivery_date)     errors.push('Delivery Date is required.')
    if (!f.ready_to_use_date) errors.push('Ready to Use Date is required.')

    if (f.installment_type === 'regular') {
        // Signing — date required only if amount > 0
        if (parseFloat(f.signing_amount) > 0 && !f.signing_date)
            errors.push('Contract Signing Date is required when amount is greater than zero.')

        // Reservation — date required only if amount > 0
        if (parseFloat(f.reservation_amount) > 0 && !f.reservation_date)
            errors.push('Reservation Date is required when amount is greater than zero.')

        // Installment rows — date + count required only if amount > 0
        f.installment_rows.forEach((row, idx) => {
            const amt = parseFloat(row.amount)
            if (amt > 0) {
                if (!row.start_date)
                    errors.push(`Installment row ${idx + 1}: Start Date is required when amount is greater than zero.`)
                if (!row.count || parseInt(row.count) < 1)
                    errors.push(`Installment row ${idx + 1}: Count must be at least 1.`)
            }
        })

        // Annual section
        if (f.has_annual && parseFloat(f.annual_amount) > 0 && !f.annual_start_date)
            errors.push('Annual Installments: Start Date is required when amount is greater than zero.')

        // Delivery section
        if (f.has_delivery && parseFloat(f.delivery_amount) > 0 && !f.delivery_start_date)
            errors.push('Delivery Payments: Start Date is required when amount is greater than zero.')

        // Maintenance section
        if (f.has_maintenance && parseFloat(f.maintenance_amount) > 0 && !f.maintenance_start_date)
            errors.push('Maintenance Payments: Start Date is required when amount is greater than zero.')

    } else {
        // Variable mode — date required only if amount > 0
        f.variable_dues.forEach((row, idx) => {
            if (parseFloat(row.amount) > 0 && !row.date)
                errors.push(`Variable row ${idx + 1}: Date is required when amount is greater than zero.`)
        })
    }

    return errors
}

// ── Save Plan ─────────────────────────────────────────────────────────────────
async function savePlan() {
    const errors = validate()
    if (errors.length) {
        alert(errors.join('\n'))
        return
    }
    saving.value = true
    try {
        const payload = { ...form.value }
        const { data } = await axios.post(
            route('company.properties.installments.save', [props.company.id, props.property.id]),
            payload
        )
        dues.value = data.dues ?? []
        activeTab.value = 'schedule'
    } catch (e) {
        alert(e?.response?.data?.message ?? 'Save failed. Please check the form.')
    } finally {
        saving.value = false
    }
}

// ── Repeater helpers ──────────────────────────────────────────────────────────
function addRow() {
    form.value.installment_rows.push({ amount: 0, count: 1, start_date: '', interval: 'monthly' })
}
function removeRow(idx) {
    form.value.installment_rows.splice(idx, 1)
}
function addVariableRow() {
    form.value.variable_dues.push({ date: '', amount: 0, notes: '' })
}
function removeVariableRow(idx) {
    form.value.variable_dues.splice(idx, 1)
}

// ── Excel Import (variable) ───────────────────────────────────────────────────
async function importExcel(e) {
    const file = e.target.files[0]
    if (!file) return
    const fd = new FormData()
    fd.append('file', file)
    try {
        const { data } = await axios.post(
            route('company.properties.installments.import', [props.company.id, props.property.id]),
            fd
        )
        if (data.rows?.length) {
            form.value.variable_dues = data.rows.map(r => ({
                date:   r.date,
                amount: r.amount,
                notes:  r.notes ?? '',
            }))
        }
    } catch (err) {
        alert('Import failed. Check the file format.')
    }
    e.target.value = ''
}

// ── Mark Paid ─────────────────────────────────────────────────────────────────
function openMarkPaid(due) {
    markPaidModal.value = {
        show:      true,
        due,
        paid_date: new Date().toISOString().slice(0, 10),
        notes:     '',
        saving:    false,
    }
}

async function submitMarkPaid() {
    markPaidModal.value.saving = true
    try {
        const { data } = await axios.patch(
            route('company.properties.installments.mark-paid', [
                props.company.id,
                props.property.id,
                markPaidModal.value.due.id,
            ]),
            {
                paid_date: markPaidModal.value.paid_date,
                notes:     markPaidModal.value.notes,
            }
        )
        // Update due in local list
        const idx = dues.value.findIndex(d => d.id === markPaidModal.value.due.id)
        if (idx !== -1) dues.value[idx] = data.due
        markPaidModal.value.show = false
    } catch (e) {
        alert('Failed to mark as paid.')
    } finally {
        markPaidModal.value.saving = false
    }
}

// ── Computed ──────────────────────────────────────────────────────────────────
const totalDue  = computed(() => dues.value.reduce((s, d) => s + parseFloat(d.amount ?? 0), 0))
const totalPaid = computed(() => dues.value.filter(d => d.status === 'paid').reduce((s, d) => s + parseFloat(d.amount ?? 0), 0))

// ── Format helpers ────────────────────────────────────────────────────────────
function formatMoney(val) {
    return Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
function formatDate(val) {
    if (!val) return '—'
    return new Date(val).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function typeLabel(t) {
    const map = {
        signing: 'Contract Signing', reservation: 'Reservation',
        installment: 'Installment', annual: 'Annual',
        delivery: 'Delivery', maintenance: 'Maintenance', variable: 'Payment',
    }
    return map[t] ?? t
}
function statusClass(s) {
    if (s === 'paid')    return 'fv-badge-active'
    if (s === 'overdue') return 'fv-badge-inactive'
    return 'fv-badge-inactive'
}
</script>

<style scoped>
.z-60 { z-index: 60; }
</style>