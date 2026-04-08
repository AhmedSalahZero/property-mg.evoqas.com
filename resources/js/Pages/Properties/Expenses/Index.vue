<template>
  <AuthenticatedLayout :title="`Expense Card — ${property.property_name}`">
    <div class="p-6 space-y-6">

      <!-- ── Page Header ─────────────────────────────────────────── -->
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
          <!-- Back button -->
          <Link
            :href="route('company.properties.index', company.id)"
            class="fv-action-btn"
            title="Back to Properties"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
          </Link>
          <div>
            <h1 class="text-lg font-bold fv-text-primary">Expense Card</h1>
            <p class="text-xs fv-text-muted mt-0.5">
              {{ property.property_name }}
              <span v-if="property.property_category" class="ml-1">· {{ property.property_category.category_name }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <a
            :href="route('company.properties.expenses.template', [company.id, property.id])"
            class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m0 0l-5-5m5 5l5-5M19 19H5"/>
            </svg>
            Download Excel Template
          </a>

          <input
            ref="excelFileInput"
            type="file"
            accept=".xlsx,.xls"
            class="hidden"
            @change="onExcelFileSelected"
          />
          <button
            @click="triggerExcelUpload"
            :disabled="importingExcel"
            class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V3"/>
            </svg>
            {{ importingExcel ? 'Uploading…' : 'Upload Excel' }}
          </button>

          <button @click="openAdd" class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add New Expense
          </button>
        </div>
      </div>

      <!-- ── Summary Strip ───────────────────────────────────────── -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Total Committed</p>
          <p class="text-base font-bold fv-text-primary">{{ fmtAmount(totalCommitted) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Total Paid</p>
          <p class="text-base font-bold" style="color:#34d399">{{ fmtAmount(totalPaid) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Outstanding</p>
          <p class="text-base font-bold" style="color:#f87171">{{ fmtAmount(totalOutstanding) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Expenses Count</p>
          <p class="text-base font-bold fv-text-primary">{{ expenses.length }}</p>
        </div>
      </div>

      <!-- ── Table ───────────────────────────────────────────────── -->
      <div class="fv-card !p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border)">
                <th class="text-left px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Category</th>
                <th class="text-left px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Item</th>
                <th class="text-left px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Expense Date</th>
                <th class="text-right px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Amount</th>
                <th class="text-right px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Paid</th>
                <th class="text-right px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Balance</th>
                <th class="text-center px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Status</th>
                <th class="text-center px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="expenses.length === 0">
                <td colspan="8" class="text-center py-12 fv-text-muted text-sm">
                  No expenses recorded yet.
                </td>
              </tr>
              <tr
                v-for="exp in expenses"
                :key="exp.id"
                class="transition-colors"
                style="border-bottom:1px solid var(--fv-border)"
                :style="{ backgroundColor: 'transparent' }"
                @mouseenter="e => e.currentTarget.style.backgroundColor='var(--fv-bg-hover)'"
                @mouseleave="e => e.currentTarget.style.backgroundColor='transparent'"
              >
                <td class="px-4 py-3 fv-text-primary font-medium">{{ exp.expense_category }}</td>
                <td class="px-4 py-3 fv-text-muted">{{ exp.expense_item }}</td>
                <td class="px-4 py-3 fv-text-muted">{{ fmtDate(exp.expense_date) }}</td>
                <td class="px-4 py-3 text-right fv-text-primary font-semibold">
                  {{ fmtCurrency(exp.expense_amount, exp.currency) }}
                  <span v-if="exp.fx_rate" class="block text-xs fv-text-muted">FX: {{ exp.fx_rate }}</span>
                </td>
                <td class="px-4 py-3 text-right" style="color:#34d399">{{ fmtCurrency(exp.total_paid, exp.currency) }}</td>
                <td class="px-4 py-3 text-right" style="color:#f87171">{{ fmtCurrency(exp.balance, exp.currency) }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="fv-badge" :class="statusClass(exp.status)">{{ statusLabel(exp.status) }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Payments eye -->
                    <button
                      @click="openPayments(exp)"
                      class="fv-action-btn"
                      title="View / Add Payments"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </button>
                    <!-- Edit -->
                    <button @click="openEdit(exp)" class="fv-action-btn" title="Edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </button>
                    <!-- Delete -->
                    <button @click="confirmDelete(exp)" class="fv-action-btn fv-action-btn-danger" title="Delete">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         ADD / EDIT EXPENSE MODAL
    ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showExpenseModal" class="fixed inset-0 z-[200] flex items-start justify-center pt-20 px-4 pb-4" style="background:rgba(0,0,0,0.6)">
          <div class="fv-modal rounded-xl w-full max-w-xl max-h-[85vh] overflow-y-auto" @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4" style="border-bottom:1px solid var(--fv-border)">
              <h2 class="font-bold fv-text-primary">{{ editingExpense ? 'Edit Expense' : 'Add New Expense' }}</h2>
              <button @click="closeExpenseModal" class="fv-action-btn"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-4">

              <!-- Row 1: Category + Item -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs fv-text-label mb-1">Expense Category <span class="text-red-400">*</span></label>
                  <select v-model="form.expense_category_id" @change="form.expense_item_id = null" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select —</option>
                    <option v-for="cat in expenseCategories" :key="cat.id" :value="cat.id">{{ cat.category_name }}</option>
                  </select>
                  <p v-if="errors.expense_category_id" class="text-xs text-red-400 mt-1">{{ errors.expense_category_id }}</p>
                </div>
                <div>
                  <label class="block text-xs fv-text-label mb-1">Expense Item <span class="text-red-400">*</span></label>
                  <select v-model="form.expense_item_id" class="fv-select w-full rounded-lg px-3 py-2 text-sm" :disabled="!form.expense_category_id">
                    <option value="">— Select —</option>
                    <option v-for="item in filteredItems" :key="item.id" :value="item.id">{{ item.item_name }}</option>
                  </select>
                  <p v-if="errors.expense_item_id" class="text-xs text-red-400 mt-1">{{ errors.expense_item_id }}</p>
                </div>
              </div>

              <!-- Row 2: Expense Date + Amount -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs fv-text-label mb-1">Expense Date <span class="text-red-400">*</span></label>
                  <input type="date" v-model="form.expense_date" class="fv-input w-full rounded-lg px-3 py-2 text-sm"/>
                  <p v-if="errors.expense_date" class="text-xs text-red-400 mt-1">{{ errors.expense_date }}</p>
                </div>
                <div>
                  <label class="block text-xs fv-text-label mb-1">Expense Amount <span class="text-red-400">*</span></label>
                  <input type="number" v-model="form.expense_amount" min="0" step="0.01" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00"/>
                  <p v-if="errors.expense_amount" class="text-xs text-red-400 mt-1">{{ errors.expense_amount }}</p>
                </div>
              </div>

              <!-- Row 3: Currency + FX Rate -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs fv-text-label mb-1">Currency <span class="text-red-400">*</span></label>
                  <select v-model="form.currency" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                    <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                  </select>
                </div>
                <div v-if="showFxRate">
                  <label class="block text-xs fv-text-label mb-1">FX Rate (1 {{ form.currency }} = ? {{ companyCurrency }})</label>
                  <input type="number" v-model="form.fx_rate" min="0" step="0.000001" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.000000"/>
                  <p v-if="errors.fx_rate" class="text-xs text-red-400 mt-1">{{ errors.fx_rate }}</p>
                </div>
              </div>

              <!-- Notes -->
              <div>
                <label class="block text-xs fv-text-label mb-1">Notes</label>
                <textarea v-model="form.notes" rows="2" class="fv-input w-full rounded-lg px-3 py-2 text-sm resize-none" placeholder="Optional notes..."></textarea>
              </div>

              <!-- Payment Repeater (only on Add) -->
              <div v-if="!editingExpense">
                <div class="flex items-center justify-between mb-2">
                  <label class="text-xs fv-text-label font-semibold">Initial Payments <span class="fv-text-muted font-normal">(optional)</span></label>
                  <button type="button" @click="addPaymentRow" class="fv-btn-secondary text-xs px-3 py-1 rounded-lg flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Row
                  </button>
                </div>
                <div v-if="form.payments.length === 0" class="text-xs fv-text-muted py-2">No payments added. You can record them later.</div>
                <div v-for="(p, i) in form.payments" :key="i" class="grid grid-cols-[1fr_1fr_auto] gap-2 mb-2">
                  <input type="date" v-model="p.payment_date" class="fv-input rounded-lg px-3 py-2 text-sm"/>
                  <input type="number" v-model="p.amount" min="0" step="0.01" class="fv-input rounded-lg px-3 py-2 text-sm" placeholder="Amount"/>
                  <button @click="removePaymentRow(i)" class="fv-action-btn fv-action-btn-danger">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>

            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-5 py-4" style="border-top:1px solid var(--fv-border)">
              <button @click="closeExpenseModal" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm">Cancel</button>
              <button @click="submitExpense" :disabled="submitting" class="fv-btn-gold px-5 py-2 rounded-lg text-sm font-semibold">
                {{ submitting ? 'Saving…' : (editingExpense ? 'Update Expense' : 'Save Expense') }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ══════════════════════════════════════════════════════════════
         PAYMENT HISTORY MODAL (eye icon)
    ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
          <div class="fv-modal rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4" style="border-bottom:1px solid var(--fv-border)">
              <div>
                <h2 class="font-bold fv-text-primary">Payment History</h2>
                <p class="text-xs fv-text-muted mt-0.5" v-if="paymentTarget">
                  {{ paymentTarget.expense_category }} — {{ paymentTarget.expense_item }}
                  · Total: {{ fmtCurrency(paymentTarget.expense_amount, paymentTarget.currency) }}
                </p>
              </div>
              <button @click="closePaymentModal" class="fv-action-btn"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>

            <!-- Existing payments -->
            <div class="p-5 space-y-4">
              <div v-if="paymentTarget && paymentTarget.payments.length === 0" class="text-xs fv-text-muted">No payments recorded yet.</div>
              <table v-else class="w-full text-sm">
                <thead>
                  <tr style="border-bottom:1px solid var(--fv-border)">
                    <th class="text-left py-2 text-xs fv-text-muted">Date</th>
                    <th class="text-right py-2 text-xs fv-text-muted">Amount</th>
                    <th class="text-center py-2 text-xs fv-text-muted">Del</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in paymentTarget?.payments ?? []" :key="p.id" style="border-bottom:1px solid var(--fv-border)">
                    <td class="py-2 fv-text-primary">{{ fmtDate(p.payment_date) }}</td>
                    <td class="py-2 text-right" style="color:#34d399">{{ fmtCurrency(p.amount, paymentTarget.currency) }}</td>
                    <td class="py-2 text-center">
                      <button @click="deletePayment(p)" class="fv-action-btn fv-action-btn-danger mx-auto">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="paymentTarget && paymentTarget.payments.length > 0">
                  <tr>
                    <td class="pt-3 text-xs fv-text-muted font-semibold">Total Paid</td>
                    <td class="pt-3 text-right font-bold" style="color:#34d399">{{ fmtCurrency(paymentTarget.total_paid, paymentTarget.currency) }}</td>
                    <td></td>
                  </tr>
                  <tr>
                    <td class="text-xs fv-text-muted font-semibold">Balance</td>
                    <td class="text-right font-bold" style="color:#f87171">{{ fmtCurrency(paymentTarget.balance, paymentTarget.currency) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>

              <!-- Add Payment Section -->
              <div style="border-top:1px solid var(--fv-border)" class="pt-4">
                <div class="flex items-center justify-between mb-3">
                  <p class="text-xs font-semibold fv-text-label">Make Payment</p>
                  <button type="button" @click="addNewPaymentRow" class="fv-btn-secondary text-xs px-3 py-1 rounded-lg flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Row
                  </button>
                </div>
                <div v-if="newPayments.length === 0" class="text-xs fv-text-muted">Click "Add Row" to record a new payment.</div>
                <div v-for="(p, i) in newPayments" :key="i" class="grid grid-cols-[1fr_1fr_auto] gap-2 mb-2">
                  <input type="date" v-model="p.payment_date" class="fv-input rounded-lg px-3 py-2 text-sm"/>
                  <input type="number" v-model="p.amount" min="0" step="0.01" class="fv-input rounded-lg px-3 py-2 text-sm" placeholder="Amount"/>
                  <button @click="removeNewPaymentRow(i)" class="fv-action-btn fv-action-btn-danger">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-5 py-4" style="border-top:1px solid var(--fv-border)">
              <button @click="closePaymentModal" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm">Close</button>
              <button v-if="newPayments.length > 0" @click="submitPayments" :disabled="submitting" class="fv-btn-gold px-5 py-2 rounded-lg text-sm font-semibold">
                {{ submitting ? 'Saving…' : 'Save Payments' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ══════════════════════════════════════════════════════════════
         DELETE CONFIRM MODAL
    ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
          <div class="fv-modal rounded-xl w-full max-w-sm p-6 text-center space-y-4">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto" style="background:rgba(239,68,68,0.12)">
              <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <div>
              <p class="font-semibold fv-text-primary">Delete Expense?</p>
              <p class="text-xs fv-text-muted mt-1">This will also delete all payment records for this expense. This action cannot be undone.</p>
            </div>
            <div class="flex gap-3 justify-center">
              <button @click="showDeleteModal = false" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm">Cancel</button>
              <button @click="executeDelete" :disabled="submitting" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:#dc2626">
                {{ submitting ? 'Deleting…' : 'Yes, Delete' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// ── Props ────────────────────────────────────────────────────────────
const props = defineProps({
  company:           Object,
  property:          Object,
  expenses:          Array,
  expenseCategories: Array,
  currencyOptions:   Array,
})

// ── Company default currency ─────────────────────────────────────────
const companyCurrency = computed(() => props.company.currency ?? 'EGP')

// ── Summary computed ─────────────────────────────────────────────────
const totalCommitted   = computed(() => props.expenses.reduce((s, e) => s + e.expense_amount, 0))
const totalPaid        = computed(() => props.expenses.reduce((s, e) => s + e.total_paid, 0))
const totalOutstanding = computed(() => props.expenses.reduce((s, e) => s + e.balance, 0))

// ── Formatters ───────────────────────────────────────────────────────
function fmtDate(d) {
  if (!d) return '—'
  const dt = new Date(d + 'T00:00:00')
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function fmtAmount(n) {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}
function fmtCurrency(n, currency) {
  return `${currency ?? ''} ${fmtAmount(n)}`
}

// ── Status helpers ───────────────────────────────────────────────────
function statusLabel(s) {
  return { unpaid: 'Unpaid', partially_paid: 'Partial', fully_paid: 'Paid' }[s] ?? s
}
function statusClass(s) {
  return {
    unpaid:         'fv-badge-inactive',
    partially_paid: '',
    fully_paid:     'fv-badge-active',
  }[s] ?? ''
}

// ── FX Rate visibility ───────────────────────────────────────────────
const showFxRate = computed(() => form.value.currency && form.value.currency !== companyCurrency.value)

// ── Filtered expense items ───────────────────────────────────────────
const filteredItems = computed(() => {
  if (!form.value.expense_category_id) return []
  const cat = props.expenseCategories.find(c => c.id === Number(form.value.expense_category_id))
  return cat?.items ?? []
})

// ── Expense Modal (Add / Edit) ───────────────────────────────────────
const showExpenseModal = ref(false)
const editingExpense   = ref(null)
const submitting       = ref(false)
const errors           = ref({})

const defaultForm = () => ({
  expense_category_id: '',
  expense_item_id:     '',
  expense_date:        '',
  expense_amount:      '',
  currency:            companyCurrency.value,
  fx_rate:             '',
  notes:               '',
  payments:            [],
})

const form = ref(defaultForm())

function openAdd() {
  editingExpense.value = null
  form.value = defaultForm()
  errors.value = {}
  showExpenseModal.value = true
}

function openEdit(exp) {
  editingExpense.value = exp
  form.value = {
    expense_category_id: exp.expense_category_id ?? '',
    expense_item_id:     exp.expense_item_id ?? '',
    expense_date:        exp.expense_date ?? '',
    expense_amount:      exp.expense_amount ?? '',
    currency:            exp.currency ?? companyCurrency.value,
    fx_rate:             exp.fx_rate ?? '',
    notes:               exp.notes ?? '',
    payments:            [],
  }
  // Resolve category id from name if needed (index passes category name, not id)
  // We store the raw expense so we can get ids from props
  const rawExp = props.expenses.find(e => e.id === exp.id)
  if (rawExp) {
    // find category id
    const cat = props.expenseCategories.find(c => c.category_name === rawExp.expense_category)
    if (cat) form.value.expense_category_id = cat.id
    const item = cat?.items?.find(i => i.item_name === rawExp.expense_item)
    if (item) form.value.expense_item_id = item.id
  }
  errors.value = {}
  showExpenseModal.value = true
}

function closeExpenseModal() {
  showExpenseModal.value = false
  editingExpense.value   = null
}

function addPaymentRow()    { form.value.payments.push({ payment_date: '', amount: '' }) }
function removePaymentRow(i){ form.value.payments.splice(i, 1) }

function submitExpense() {
  errors.value = {}

  // Basic front-end validation
  const e = {}
  if (!form.value.expense_category_id) e.expense_category_id = 'Required'
  if (!form.value.expense_item_id)     e.expense_item_id     = 'Required'
  if (!form.value.expense_date)        e.expense_date        = 'Required'
  if (!form.value.expense_amount || Number(form.value.expense_amount) <= 0) e.expense_amount = 'Must be > 0'
  if (Object.keys(e).length) { errors.value = e; return }

  submitting.value = true

  const payload = {
    expense_category_id: form.value.expense_category_id,
    expense_item_id:     form.value.expense_item_id,
    expense_date:        form.value.expense_date,
    expense_amount:      form.value.expense_amount,
    currency:            form.value.currency,
    fx_rate:             showFxRate.value ? (form.value.fx_rate || null) : null,
    notes:               form.value.notes || null,
    payments:            form.value.payments.filter(p => p.payment_date && p.amount),
  }

  if (editingExpense.value) {
    router.put(
      route('company.properties.expenses.update', [props.company.id, props.property.id, editingExpense.value.id]),
      payload,
      {
        onSuccess: () => { closeExpenseModal() },
        onError:   (errs) => { errors.value = errs },
        onFinish:  () => { submitting.value = false },
      }
    )
  } else {
    router.post(
      route('company.properties.expenses.store', [props.company.id, props.property.id]),
      payload,
      {
        onSuccess: () => { closeExpenseModal() },
        onError:   (errs) => { errors.value = errs },
        onFinish:  () => { submitting.value = false },
      }
    )
  }
}

// ── Payment Modal ────────────────────────────────────────────────────
const showPaymentModal = ref(false)
const paymentTarget    = ref(null)
const newPayments      = ref([])

function openPayments(exp) {
  paymentTarget.value = exp
  newPayments.value   = []
  showPaymentModal.value = true
}

function closePaymentModal() {
  showPaymentModal.value = false
  paymentTarget.value    = null
  newPayments.value      = []
}

function addNewPaymentRow()    { newPayments.value.push({ payment_date: '', amount: '' }) }
function removeNewPaymentRow(i){ newPayments.value.splice(i, 1) }

function submitPayments() {
  const rows = newPayments.value.filter(p => p.payment_date && p.amount)
  if (!rows.length) return

  submitting.value = true
  router.post(
    route('company.properties.expenses.payments.store', [props.company.id, props.property.id, paymentTarget.value.id]),
    { payments: rows },
    {
      onSuccess: () => { closePaymentModal() },
      onFinish:  () => { submitting.value = false },
    }
  )
}

function deletePayment(payment) {
  if (!paymentTarget.value) return
  router.delete(
    route('company.properties.expenses.payments.destroy', [
      props.company.id,
      props.property.id,
      paymentTarget.value.id,
      payment.id,
    ]),
    {
      onSuccess: () => {
        // update local paymentTarget payments list
        paymentTarget.value.payments = paymentTarget.value.payments.filter(p => p.id !== payment.id)
      },
    }
  )
}

// ── Delete Expense ───────────────────────────────────────────────────
const showDeleteModal  = ref(false)
const deletingExpense  = ref(null)
const excelFileInput   = ref(null)
const importingExcel   = ref(false)

function confirmDelete(exp) {
  deletingExpense.value = exp
  showDeleteModal.value = true
}

function executeDelete() {
  if (!deletingExpense.value) return
  submitting.value = true
  router.delete(
    route('company.properties.expenses.destroy', [props.company.id, props.property.id, deletingExpense.value.id]),
    {
      onSuccess: () => { showDeleteModal.value = false; deletingExpense.value = null },
      onFinish:  () => { submitting.value = false },
    }
  )
}

function triggerExcelUpload() {
  if (importingExcel.value) return
  excelFileInput.value?.click()
}

function onExcelFileSelected(event) {
  const file = event.target?.files?.[0]
  if (!file) return

  importingExcel.value = true
  router.post(
    route('company.properties.expenses.import', [props.company.id, props.property.id]),
    { file },
    {
      forceFormData: true,
      onFinish: () => {
        importingExcel.value = false
        if (excelFileInput.value) excelFileInput.value.value = ''
      },
    }
  )
}
</script>