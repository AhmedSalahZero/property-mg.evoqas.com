<template>
  <AuthenticatedLayout title="Corporate Expenses">
    <div class="p-6 space-y-6">

      <!-- ── Page Header ─────────────────────────────────────────── -->
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
          <Link :href="route('company.properties.index', company.id)" class="fv-action-btn" title="Back to Properties">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
          </Link>
          <div>
            <h1 class="text-lg font-bold fv-text-primary">Corporate Expenses</h1>
            <p class="text-xs fv-text-muted mt-0.5">Company-level costs, allocated across the portfolio by area</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <a :href="route('company.properties.corporate-expenses.template', company.id)"
             class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m0 0l-5-5m5 5l5-5M19 19H5"/>
            </svg>
            Download Excel Template
          </a>

          <button @click="openImportModal" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V3"/>
            </svg>
            Upload Excel
          </button>

          <button @click="openAdd" class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Corporate Expense
          </button>
        </div>
      </div>

      <!-- ── Month Filter ─────────────────────────────────────────── -->
      <div class="inline-flex items-center gap-3 fv-card !p-3">
        <label class="fv-text-label text-xs whitespace-nowrap">Viewing Month</label>
        <input type="month" v-model="selectedMonth" @change="onMonthChange" class="fv-input !w-auto" />
        <span class="text-xs fv-text-muted whitespace-nowrap">{{ monthSummary.count }} expense(s)</span>
      </div>

      <!-- ── Summary Strip (whole selected month, not just this page) ── -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Total Committed</p>
          <p class="text-base font-bold fv-text-primary">{{ fmtAmount(monthSummary.total_committed) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Total Paid</p>
          <p class="text-base font-bold" style="color:#34d399">{{ fmtAmount(monthSummary.total_paid) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Outstanding</p>
          <p class="text-base font-bold" style="color:#f87171">{{ fmtAmount(monthSummary.total_outstanding) }}</p>
        </div>
        <div class="fv-card !p-4 space-y-1">
          <p class="text-xs fv-text-muted">Expenses Count</p>
          <p class="text-base font-bold fv-text-primary">{{ monthSummary.count }}</p>
        </div>
      </div>

      <!-- ── Table ───────────────────────────────────────────────── -->
      <div class="fv-card !p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border)">
                <th class="text-left px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider"></th>
                <th class="text-left px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Category / Item</th>
                <th class="text-left px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Expense Date</th>
                <th class="text-left px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Allocation Scope</th>
                <th class="text-right px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Amount</th>
                <th class="text-right px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Paid</th>
                <th class="text-right px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Balance</th>
                <th class="text-center px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Status</th>
                <th class="text-center px-4 py-3 text-xs font-semibold fv-text-muted uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="expenses.length === 0">
                <td colspan="9" class="text-center py-12 fv-text-muted text-sm">No corporate expenses recorded yet.</td>
              </tr>
              <template v-for="exp in expenses" :key="exp.id">
                <tr class="transition-colors" style="border-bottom:1px solid var(--fv-border)"
                    :style="{ backgroundColor: 'transparent' }"
                    @mouseenter="e => e.currentTarget.style.backgroundColor='var(--fv-bg-hover)'"
                    @mouseleave="e => e.currentTarget.style.backgroundColor='transparent'">
                  <td class="px-4 py-3">
                    <button class="fv-action-btn !w-6 !h-6" @click="toggleExpand(exp)" title="Show allocation breakdown">
                      <svg class="w-3.5 h-3.5 transition-transform" :style="{ transform: expanded[exp.id] ? 'rotate(90deg)' : 'none' }"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                      </svg>
                    </button>
                  </td>
                  <td class="px-4 py-3">
                    <p class="fv-text-primary font-medium">{{ exp.expense_category }}</p>
                    <p class="text-xs fv-text-muted">{{ exp.expense_item }}</p>
                  </td>
                  <td class="px-4 py-3 fv-text-muted">{{ fmtDate(exp.expense_date) }}</td>
                  <td class="px-4 py-3">
                    <span class="fv-tag">{{ scopeLabel(exp.allocation_scope) }}</span>
                    <span class="text-xs fv-text-muted ml-1">({{ exp.allocations_count }} units)</span>
                  </td>
                  <td class="px-4 py-3 text-right fv-text-primary font-semibold">{{ fmtCurrency(exp.expense_amount, exp.currency) }}</td>
                  <td class="px-4 py-3 text-right" style="color:#34d399">{{ fmtAmount(exp.total_paid) }}</td>
                  <td class="px-4 py-3 text-right" style="color:#f87171">{{ fmtAmount(exp.balance) }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="fv-badge" :class="statusClass(exp.status)">{{ statusLabel(exp.status) }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1.5">
                      <button class="fv-action-btn" title="Payments" @click="openPayments(exp)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2"/>
                          <circle cx="12" cy="12" r="9" stroke-width="2"/>
                        </svg>
                      </button>
                      <button class="fv-action-btn" title="Edit" @click="openEdit(exp)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                      </button>
                      <button class="fv-action-btn-danger" title="Delete" @click="confirmDelete(exp)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="expanded[exp.id]" style="border-bottom:1px solid var(--fv-border)">
                  <td colspan="9" class="px-4 py-3" style="background:var(--fv-bg-input)">
                    <p class="text-xs fv-text-muted mb-2">Allocation breakdown — snapshot taken {{ fmtDate(exp.expense_date) }}</p>
                    <p v-if="allocationsLoading[exp.id]" class="text-xs fv-text-muted">Loading allocation breakdown…</p>
                    <table v-else class="w-full text-xs">
                      <thead>
                        <tr class="fv-text-muted">
                          <th class="text-left py-1 font-medium">Unit</th>
                          <th class="text-left py-1 font-medium">Status at Allocation</th>
                          <th class="text-right py-1 font-medium">Area</th>
                          <th class="text-right py-1 font-medium">%</th>
                          <th class="text-right py-1 font-medium">Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="a in (allocationsCache[exp.id] || [])" :key="a.id">
                          <td class="py-1 fv-text-primary">{{ a.unit_label }}</td>
                          <td class="py-1 fv-text-muted capitalize">{{ a.eligibility_status.replace('_', ' ') }}</td>
                          <td class="py-1 text-right fv-text-muted">{{ a.area || '—' }}</td>
                          <td class="py-1 text-right fv-text-muted">{{ a.allocation_pct.toFixed(2) }}%</td>
                          <td class="py-1 text-right fv-text-primary">{{ fmtAmount(a.allocated_amount) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- ── Pagination ─────────────────────────────────────────── -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3" style="border-top:1px solid var(--fv-border)">
          <p class="text-xs fv-text-muted">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total)</p>
          <div class="flex items-center gap-2">
            <button class="fv-btn-secondary px-3 py-1.5 rounded-lg text-xs" :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">Previous</button>
            <button class="fv-btn-secondary px-3 py-1.5 rounded-lg text-xs" :disabled="pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         ADD / EDIT MODAL
    ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showExpenseModal" class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(0,0,0,0.6); padding-top:5rem">
          <div class="fv-modal rounded-xl w-full max-w-2xl p-6 space-y-5">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-bold fv-text-primary">{{ editingExpense ? 'Edit' : 'Add' }} Corporate Expense</h2>
              <button @click="closeExpenseModal" class="fv-action-btn">✕</button>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="fv-text-label text-xs">Expense Category</label>
                <select v-model="form.expense_category_id" class="fv-select w-full mt-1" @change="form.expense_item_id = ''">
                  <option value="">Select…</option>
                  <option v-for="c in expenseCategories" :key="c.id" :value="c.id">{{ c.category_name }}</option>
                </select>
                <p v-if="errors.expense_category_id" class="text-xs text-red-400 mt-1">{{ errors.expense_category_id }}</p>
              </div>
              <div>
                <label class="fv-text-label text-xs">Expense Item</label>
                <select v-model="form.expense_item_id" class="fv-select w-full mt-1" :disabled="!filteredItems.length">
                  <option value="">Select…</option>
                  <option v-for="i in filteredItems" :key="i.id" :value="i.id">{{ i.item_name }}</option>
                </select>
                <p v-if="errors.expense_item_id" class="text-xs text-red-400 mt-1">{{ errors.expense_item_id }}</p>
              </div>
              <div>
                <label class="fv-text-label text-xs">Expense Date <span class="text-[10px]">(accrual — drives allocation eligibility)</span></label>
                <input type="date" v-model="form.expense_date" class="fv-input w-full mt-1" @change="runPreview" />
                <p v-if="errors.expense_date" class="text-xs text-red-400 mt-1">{{ errors.expense_date }}</p>
              </div>
              <div>
                <label class="fv-text-label text-xs">Amount</label>
                <input type="number" step="0.01" v-model="form.expense_amount" class="fv-input w-full mt-1" @change="runPreview" />
                <p v-if="errors.expense_amount" class="text-xs text-red-400 mt-1">{{ errors.expense_amount }}</p>
              </div>
              <div>
                <label class="fv-text-label text-xs">Currency</label>
                <select v-model="form.currency" class="fv-select w-full mt-1">
                  <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div v-if="showFxRate">
                <label class="fv-text-label text-xs">FX Rate (optional override)</label>
                <input type="number" step="0.000001" v-model="form.fx_rate" class="fv-input w-full mt-1" placeholder="Uses currency_rates if blank" />
              </div>
            </div>

            <!-- ── Allocation Scope ─────────────────────────────────── -->
            <div class="pt-2" style="border-top:1px solid var(--fv-border)">
              <label class="fv-text-label text-xs">Allocation Scope</label>
              <select v-model="form.allocation_scope" class="fv-select w-full mt-1" @change="runPreview">
                <option v-for="(label, key) in scopeOptions" :key="key" :value="key">{{ label }}</option>
              </select>

              <!-- Custom unit picker -->
              <div v-if="form.allocation_scope === 'custom'" class="mt-3 fv-card !p-3 max-h-48 overflow-y-auto space-y-1">
                <label v-for="u in unitPicker" :key="u.key" class="flex items-center gap-2 text-xs fv-text-primary py-1 cursor-pointer">
                  <input type="checkbox" :value="u.key" v-model="form.custom_unit_keys" @change="runPreview" />
                  {{ u.label }}
                  <span class="fv-text-muted">({{ u.area || 0 }} · {{ u.status }})</span>
                </label>
              </div>

              <!-- Live allocation preview -->
              <div class="mt-3">
                <p v-if="previewLoading" class="text-xs fv-text-muted">Calculating eligible units…</p>
                <div v-else-if="previewRows.length" class="fv-card !p-3 max-h-40 overflow-y-auto">
                  <p class="text-xs fv-text-muted mb-1">{{ previewRows.length }} eligible unit(s) as of {{ fmtDate(form.expense_date) }}:</p>
                  <table class="w-full text-xs">
                    <tbody>
                      <tr v-for="r in previewRows" :key="r.key">
                        <td class="py-0.5 fv-text-primary">{{ r.label }}</td>
                        <td class="py-0.5 text-right fv-text-muted">{{ r.allocation_pct.toFixed(2) }}%</td>
                        <td class="py-0.5 text-right fv-text-primary">{{ fmtAmount(r.allocated_amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-else-if="previewRan" class="text-xs" style="color:#f87171">No eligible units for this scope and date — nothing will be allocated.</p>
              </div>
              <p v-if="errors.allocation_scope" class="text-xs text-red-400 mt-1">{{ errors.allocation_scope }}</p>
            </div>

            <div>
              <label class="fv-text-label text-xs">Notes</label>
              <textarea v-model="form.notes" rows="2" class="fv-input w-full mt-1"></textarea>
            </div>

            <!-- Payment Schedule — % / auto-amount / term / forecasted date.
                 Required on every save; this is what Cash Forecast reads
                 instead of guessing from expense_date alone. -->
            <PaymentScheduleRepeater
              v-model="form.payment_schedule"
              :expense-amount="form.expense_amount"
              :expense-date="form.expense_date"
              :error="errors.payment_schedule"
            />

            <!-- ── Initial Payments (add only) ─────────────────────── -->
            <div v-if="!editingExpense" class="pt-2" style="border-top:1px solid var(--fv-border)">
              <div class="flex items-center justify-between mb-2">
                <label class="fv-text-label text-xs">Initial Payments (optional)</label>
                <button @click="addPaymentRow" class="text-xs fv-text-muted underline">+ Add payment</button>
              </div>
              <div v-for="(p, i) in form.payments" :key="i" class="flex items-center gap-2 mb-2">
                <input type="date" v-model="p.payment_date" class="fv-input flex-1" />
                <input type="number" step="0.01" v-model="p.amount" class="fv-input flex-1" placeholder="Amount" />
                <button @click="removePaymentRow(i)" class="fv-action-btn-danger !w-8 !h-8">✕</button>
              </div>
            </div>

            <div class="flex gap-3 justify-end pt-2">
              <button @click="closeExpenseModal" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm">Cancel</button>
              <button @click="submitExpense" :disabled="submitting" class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold">
                {{ submitting ? 'Saving…' : (editingExpense ? 'Save Changes' : 'Add Expense') }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ══════════════════════════════════════════════════════════════
         PAYMENTS MODAL
    ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
          <div class="fv-modal rounded-xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-bold fv-text-primary">Payments</h2>
              <button @click="closePaymentModal" class="fv-action-btn">✕</button>
            </div>

            <div class="space-y-2 max-h-40 overflow-y-auto">
              <div v-for="p in paymentTarget?.payments" :key="p.id" class="flex items-center justify-between text-sm fv-text-primary">
                <span>{{ fmtDate(p.payment_date) }}</span>
                <span>{{ fmtAmount(p.amount) }}</span>
                <button @click="deletePayment(p)" class="fv-action-btn-danger !w-6 !h-6 text-xs">✕</button>
              </div>
              <p v-if="!paymentTarget?.payments?.length" class="text-xs fv-text-muted">No payments recorded yet.</p>
            </div>

            <div style="border-top:1px solid var(--fv-border)" class="pt-3">
              <div class="flex items-center justify-between mb-2">
                <label class="fv-text-label text-xs">Add Payment(s)</label>
                <button @click="addNewPaymentRow" class="text-xs fv-text-muted underline">+ Add row</button>
              </div>
              <div v-for="(p, i) in newPayments" :key="i" class="flex items-center gap-2 mb-2">
                <input type="date" v-model="p.payment_date" class="fv-input flex-1" />
                <input type="number" step="0.01" v-model="p.amount" class="fv-input flex-1" placeholder="Amount" />
                <button @click="removeNewPaymentRow(i)" class="fv-action-btn-danger !w-8 !h-8">✕</button>
              </div>
            </div>

            <div class="flex gap-3 justify-end">
              <button @click="closePaymentModal" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm">Close</button>
              <button @click="submitPayments" :disabled="submitting || !newPayments.length" class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold">
                Save Payment(s)
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
              <p class="font-semibold fv-text-primary">Delete Corporate Expense?</p>
              <p class="text-xs fv-text-muted mt-1">This will also delete its allocation snapshot and all payment records. This action cannot be undone.</p>
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

    <!-- ══════════════════════════════════════════════════════════════
         EXCEL IMPORT MODAL — 2-step: preview rows → pick scope → save
    ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(0,0,0,0.6); padding-top:5rem">
          <div class="fv-modal rounded-xl w-full max-w-2xl p-6 space-y-5">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-bold fv-text-primary">Import Corporate Expenses</h2>
              <button @click="closeImportModal" class="fv-action-btn">✕</button>
            </div>

            <!-- Step 1: upload -->
            <div v-if="!importPreviewed">
              <input ref="importFileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="onImportFileSelected" />
              <button @click="importFileInput?.click()" :disabled="importUploading" class="fv-btn-secondary w-full px-4 py-3 rounded-lg text-sm font-semibold">
                {{ importUploading ? 'Reading file…' : 'Choose Excel File' }}
              </button>
            </div>

            <!-- Step 2: preview + scope -->
            <div v-else class="space-y-4">
              <div>
                <p class="text-xs fv-text-muted">{{ importValid.length }} valid row(s), {{ importInvalid.length }} invalid row(s).</p>
                <div v-if="importInvalid.length" class="fv-card !p-3 mt-2 max-h-24 overflow-y-auto">
                  <p v-for="e in importInvalid" :key="e.row" class="text-xs" style="color:#f87171">Row {{ e.row }}: {{ e.error }}</p>
                </div>
              </div>

              <div class="fv-card !p-3 max-h-40 overflow-y-auto">
                <table class="w-full text-xs">
                  <thead><tr class="fv-text-muted"><th class="text-left py-1">Category</th><th class="text-left py-1">Item</th><th class="text-left py-1">Date</th><th class="text-right py-1">Amount</th></tr></thead>
                  <tbody>
                    <tr v-for="r in importValid" :key="r.row">
                      <td class="py-0.5 fv-text-primary">{{ r.expense_category }}</td>
                      <td class="py-0.5 fv-text-muted">{{ r.expense_item }}</td>
                      <td class="py-0.5 fv-text-muted">{{ fmtDate(r.expense_date) }}</td>
                      <td class="py-0.5 text-right fv-text-primary">{{ fmtCurrency(r.expense_amount, r.currency) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div>
                <label class="fv-text-label text-xs">Allocation Scope for this batch</label>
                <select v-model="importForm.allocation_scope" class="fv-select w-full mt-1">
                  <option v-for="(label, key) in scopeOptions" :key="key" :value="key">{{ label }}</option>
                </select>
                <p class="text-[10px] fv-text-muted mt-1">Applies to every row — each row's own expense_date still determines which units are eligible on that date.</p>
              </div>

              <div v-if="importForm.allocation_scope === 'custom'" class="fv-card !p-3 max-h-40 overflow-y-auto space-y-1">
                <label v-for="u in unitPicker" :key="u.key" class="flex items-center gap-2 text-xs fv-text-primary py-1 cursor-pointer">
                  <input type="checkbox" :value="u.key" v-model="importForm.custom_unit_keys" />
                  {{ u.label }}
                </label>
              </div>
            </div>

            <div class="flex gap-3 justify-end pt-2">
              <button @click="closeImportModal" class="fv-btn-secondary px-4 py-2 rounded-lg text-sm">Cancel</button>
              <button v-if="importPreviewed" @click="submitImport" :disabled="importSaving || !importValid.length"
                      class="fv-btn-gold px-4 py-2 rounded-lg text-sm font-semibold">
                {{ importSaving ? 'Saving…' : `Save ${importValid.length} Expense(s)` }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PaymentScheduleRepeater from '@/Components/PaymentScheduleRepeater.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  company:           Object,
  expenses:          Array,
  pagination:        Object,
  monthSummary:      Object,
  expenseCategories: Array,
  scopeOptions:      Object,
  unitPicker:        Array,
  currencyOptions:   Array,
})

const companyCurrency = computed(() => props.company.currency ?? 'EGP')

// ── Month filter ──────────────────────────────────────────────────────
// monthSummary.month comes from the server (defaults to current month if
// no ?month= query param was passed) — this is the single source of truth
// for totals, since totals must reflect the WHOLE month, not just the
// current page of a paginated list. selectedMonth mirrors it locally so
// the native <input type="month"> has something to bind to.
const selectedMonth = ref(props.monthSummary.month)

// Keep the picker in sync with the server's truth — e.g. if someone edits
// the URL with a malformed ?month=, the controller falls back to the
// current month and this makes sure the input reflects what's ACTUALLY
// being shown rather than the invalid value still sitting in the box.
watch(() => props.monthSummary.month, (m) => { selectedMonth.value = m })

function onMonthChange() {
  if (!selectedMonth.value) return
  router.get(route('company.properties.corporate-expenses.index', props.company.id), { month: selectedMonth.value }, { preserveState: true, preserveScroll: true })
}

function goToPage(page) {
  router.get(route('company.properties.corporate-expenses.index', props.company.id), { month: props.monthSummary.month, page }, { preserveState: true, preserveScroll: true })
}

// ── Formatters ───────────────────────────────────────────────────────
function fmtDate(d) {
  if (!d) return '—'
  const dt = new Date(d + 'T00:00:00')
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function fmtAmount(n) {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0)
}
function fmtCurrency(n, currency) { return `${currency ?? ''} ${fmtAmount(n)}` }
function scopeLabel(key) { return props.scopeOptions[key] ?? key }
function statusLabel(s) { return { unpaid: 'Unpaid', partially_paid: 'Partial', fully_paid: 'Paid' }[s] ?? s }
function statusClass(s) { return { unpaid: 'fv-badge-inactive', partially_paid: '', fully_paid: 'fv-badge-active' }[s] ?? '' }

// ── Row expand — allocation breakdown fetched on demand ──────────────
// Fix for the "shipping 2,000+ rows on every page load" concern: the
// index payload only ever carries allocations_count. The actual rows for
// a given expense are fetched here, the first time it's expanded, and
// cached locally so re-collapsing/re-expanding doesn't refetch.
const expanded          = reactive({})
const allocationsCache  = reactive({})
const allocationsLoading = reactive({})

async function toggleExpand(exp) {
  const id = exp.id
  expanded[id] = !expanded[id]
  if (!expanded[id] || allocationsCache[id]) return

  allocationsLoading[id] = true
  try {
    const { data } = await axios.get(route('company.properties.corporate-expenses.allocations', [props.company.id, id]))
    allocationsCache[id] = data.rows
  } finally {
    allocationsLoading[id] = false
  }
}

// ── Filtered items for selected category ────────────────────────────
const filteredItems = computed(() => {
  if (!form.value.expense_category_id) return []
  const cat = props.expenseCategories.find(c => c.id === Number(form.value.expense_category_id))
  return cat?.items ?? []
})

const showFxRate = computed(() => form.value.currency && form.value.currency !== companyCurrency.value)

// ── Add / Edit Modal ─────────────────────────────────────────────────
const showExpenseModal = ref(false)
const editingExpense    = ref(null)
const submitting        = ref(false)
const errors             = ref({})

const previewRows    = ref([])
const previewLoading = ref(false)
const previewRan      = ref(false)
let previewTimer = null

const defaultForm = () => ({
  expense_category_id: '',
  expense_item_id:     '',
  expense_date:        '',
  expense_amount:      '',
  currency:            companyCurrency.value,
  fx_rate:             '',
  allocation_scope:    'occupied',
  custom_unit_keys:    [],
  notes:               '',
  payments:            [],
  payment_schedule:    [{ percentage: '', forecasted_date: '', payment_term: '' }],
})

const form = ref(defaultForm())

function openAdd() {
  editingExpense.value = null
  form.value = defaultForm()
  errors.value = {}
  previewRows.value = []
  previewRan.value = false
  showExpenseModal.value = true
}

function openEdit(exp) {
  editingExpense.value = exp
  const cat  = props.expenseCategories.find(c => c.category_name === exp.expense_category)
  const item = cat?.items?.find(i => i.item_name === exp.expense_item)
  form.value = {
    expense_category_id: cat?.id ?? '',
    expense_item_id:     item?.id ?? '',
    expense_date:        exp.expense_date ?? '',
    expense_amount:      exp.expense_amount ?? '',
    currency:            exp.currency ?? companyCurrency.value,
    fx_rate:             '',
    allocation_scope:    exp.allocation_scope ?? 'occupied',
    custom_unit_keys:    [],
    notes:               exp.notes ?? '',
    payments:            [],
    payment_schedule:    (exp.payment_schedule && exp.payment_schedule.length)
      ? exp.payment_schedule.map(s => ({ percentage: s.percentage, forecasted_date: s.forecasted_date, payment_term: s.payment_term ?? '' }))
      : [{ percentage: '', forecasted_date: '', payment_term: '' }],
  }
  errors.value = {}
  showExpenseModal.value = true
  runPreview()
}

function closeExpenseModal() {
  showExpenseModal.value = false
  editingExpense.value   = null
}

function addPaymentRow()     { form.value.payments.push({ payment_date: '', amount: '' }) }
function removePaymentRow(i) { form.value.payments.splice(i, 1) }

// Debounced allocation preview — calls the backend engine so the user sees
// exactly who's eligible and what each unit's share is before saving.
function runPreview() {
  clearTimeout(previewTimer)
  previewTimer = setTimeout(async () => {
    if (!form.value.expense_date || !form.value.expense_amount || Number(form.value.expense_amount) <= 0) return
    if (form.value.allocation_scope === 'custom' && !form.value.custom_unit_keys.length) {
      previewRows.value = []; previewRan.value = false; return
    }
    previewLoading.value = true
    try {
      const { data } = await axios.post(route('company.properties.corporate-expenses.preview-allocation', props.company.id), {
        expense_date:     form.value.expense_date,
        expense_amount:   form.value.expense_amount,
        allocation_scope: form.value.allocation_scope,
        custom_unit_keys: form.value.custom_unit_keys,
      })
      previewRows.value = data.rows
      previewRan.value = true
    } catch (e) {
      previewRows.value = []
    } finally {
      previewLoading.value = false
    }
  }, 400)
}

function submitExpense() {
  errors.value = {}
  const e = {}
  if (!form.value.expense_category_id) e.expense_category_id = 'Required'
  if (!form.value.expense_item_id)     e.expense_item_id     = 'Required'
  if (!form.value.expense_date)        e.expense_date        = 'Required'
  if (!form.value.expense_amount || Number(form.value.expense_amount) <= 0) e.expense_amount = 'Must be > 0'
  if (form.value.allocation_scope === 'custom' && !form.value.custom_unit_keys.length) e.allocation_scope = 'Pick at least one unit'

  const scheduleRows = form.value.payment_schedule.filter(r => r.percentage)
  const scheduleTotal = scheduleRows.reduce((s, r) => s + (parseFloat(r.percentage) || 0), 0)
  if (scheduleRows.length === 0) {
    e.payment_schedule = 'At least one payment schedule row is required.'
  } else if (Math.abs(scheduleTotal - 100) > 0.01) {
    e.payment_schedule = `Payment schedule percentages must total 100%. Currently: ${scheduleTotal.toFixed(2)}%.`
  } else if (scheduleRows.some(r => !r.forecasted_date)) {
    e.payment_schedule = 'Every payment schedule row needs a forecasted date (pick a term or enter one manually).'
  }

  if (Object.keys(e).length) { errors.value = e; return }

  submitting.value = true
  const payload = {
    expense_category_id: form.value.expense_category_id,
    expense_item_id:     form.value.expense_item_id,
    expense_date:        form.value.expense_date,
    expense_amount:      form.value.expense_amount,
    currency:            form.value.currency,
    fx_rate:             showFxRate.value ? (form.value.fx_rate || null) : null,
    allocation_scope:    form.value.allocation_scope,
    custom_unit_keys:    form.value.custom_unit_keys,
    notes:               form.value.notes || null,
    payments:            form.value.payments.filter(p => p.payment_date && p.amount),
    payment_schedule:    scheduleRows,
  }

  const onDone = { onSuccess: () => closeExpenseModal(), onError: (errs) => { errors.value = errs }, onFinish: () => { submitting.value = false } }

  if (editingExpense.value) {
    router.put(route('company.properties.corporate-expenses.update', [props.company.id, editingExpense.value.id]), payload, onDone)
  } else {
    router.post(route('company.properties.corporate-expenses.store', props.company.id), payload, onDone)
  }
}

// ── Payments ─────────────────────────────────────────────────────────
const showPaymentModal = ref(false)
const paymentTarget    = ref(null)
const newPayments      = ref([])

function openPayments(exp) { paymentTarget.value = exp; newPayments.value = []; showPaymentModal.value = true }
function closePaymentModal() { showPaymentModal.value = false; paymentTarget.value = null; newPayments.value = [] }
function addNewPaymentRow()    { newPayments.value.push({ payment_date: '', amount: '' }) }
function removeNewPaymentRow(i){ newPayments.value.splice(i, 1) }

function submitPayments() {
  const rows = newPayments.value.filter(p => p.payment_date && p.amount)
  if (!rows.length) return
  submitting.value = true
  router.post(route('company.properties.corporate-expenses.payments.store', [props.company.id, paymentTarget.value.id]), { payments: rows }, {
    onSuccess: () => closePaymentModal(),
    onFinish:  () => { submitting.value = false },
  })
}

function deletePayment(payment) {
  if (!paymentTarget.value) return
  router.delete(route('company.properties.corporate-expenses.payments.destroy', [props.company.id, paymentTarget.value.id, payment.id]), {
    onSuccess: () => { paymentTarget.value.payments = paymentTarget.value.payments.filter(p => p.id !== payment.id) },
  })
}

// ── Delete ───────────────────────────────────────────────────────────
const showDeleteModal = ref(false)
const deletingExpense = ref(null)

function confirmDelete(exp) { deletingExpense.value = exp; showDeleteModal.value = true }
function executeDelete() {
  if (!deletingExpense.value) return
  submitting.value = true
  router.delete(route('company.properties.corporate-expenses.destroy', [props.company.id, deletingExpense.value.id]), {
    onSuccess: () => { showDeleteModal.value = false; deletingExpense.value = null },
    onFinish:  () => { submitting.value = false },
  })
}

// ── Excel Import ─────────────────────────────────────────────────────
const showImportModal  = ref(false)
const importFileInput  = ref(null)
const importUploading  = ref(false)
const importPreviewed  = ref(false)
const importValid      = ref([])
const importInvalid    = ref([])
const importSaving     = ref(false)
const importForm       = ref({ allocation_scope: 'occupied', custom_unit_keys: [] })

function openImportModal() {
  importPreviewed.value = false
  importValid.value = []
  importInvalid.value = []
  importForm.value = { allocation_scope: 'occupied', custom_unit_keys: [] }
  showImportModal.value = true
}
function closeImportModal() { showImportModal.value = false }

async function onImportFileSelected(event) {
  const file = event.target?.files?.[0]
  if (!file) return
  importUploading.value = true
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data } = await axios.post(route('company.properties.corporate-expenses.import-preview', props.company.id), fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importValid.value = data.valid
    importInvalid.value = data.invalid
    importPreviewed.value = true
  } finally {
    importUploading.value = false
    if (importFileInput.value) importFileInput.value.value = ''
  }
}

async function submitImport() {
  importSaving.value = true
  try {
    await axios.post(route('company.properties.corporate-expenses.import-save', props.company.id), {
      rows: importValid.value,
      allocation_scope: importForm.value.allocation_scope,
      custom_unit_keys: importForm.value.custom_unit_keys,
    })
    closeImportModal()
    router.reload()
  } finally {
    importSaving.value = false
  }
}
</script>