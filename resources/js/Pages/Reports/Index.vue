<template>
  <AuthenticatedLayout title="Reports">
    <div class="p-6 space-y-8">

      <!-- Header -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Company Reports</h1>
          <p class="text-xs fv-text-muted mt-1">Standard reports and custom analytical views</p>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════
           SECTION 1 — STANDARD REPORTS
      ═══════════════════════════════════════════════════ -->
      <div>
        <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color:#26C6DA;">Standard Reports</p>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

          <!-- Tenant Ledger -->
          <Link
            :href="route('company.reports.tenant-ledger', company.id)"
            class="fv-card p-5 hover:scale-[1.01] transition-transform cursor-pointer"
            style="text-decoration:none;"
          >
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                style="background:rgba(20,144,168,0.15); color:#48C4D8;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
              <div>
                <p class="fv-text-primary font-semibold text-sm">Tenant Ledger</p>
                <p class="fv-text-muted text-xs mt-1 leading-relaxed">
                  Full financial history for one tenant: all contracts, collections due vs. collected, outstanding balance, insurance held.
                </p>
                <span class="inline-block mt-2 text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:rgba(20,144,168,0.12); color:#48C4D8;">
                  Per Tenant
                </span>
              </div>
            </div>
          </Link>

          <!-- Rent Collections Detail -->
          <Link
            :href="route('company.reports.rent-collections', company.id)"
            class="fv-card p-5 hover:scale-[1.01] transition-transform cursor-pointer"
            style="text-decoration:none;"
          >
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                style="background:rgba(186,117,23,0.15); color:#FAC775;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
              </div>
              <div>
                <p class="fv-text-primary font-semibold text-sm">Rent Collections Detail</p>
                <p class="fv-text-muted text-xs mt-1 leading-relaxed">
                  All collection rows for a date range: unit, tenant, due date, period, amount, status.
                </p>
                <span class="inline-block mt-2 text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:rgba(186,117,23,0.12); color:#FAC775;">
                  Date Range
                </span>
              </div>
            </div>
          </Link>

          <!-- Installments Detail -->
          <Link
            :href="route('company.reports.installments', company.id)"
            class="fv-card p-5 hover:scale-[1.01] transition-transform cursor-pointer"
            style="text-decoration:none;"
          >
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                style="background:rgba(99,102,241,0.15); color:#a5b4fc;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <p class="fv-text-primary font-semibold text-sm">Installments Detail</p>
                <p class="fv-text-muted text-xs mt-1 leading-relaxed">
                  All installment due rows for a date range: property, due date, type, amount, status.
                </p>
                <span class="inline-block mt-2 text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:rgba(99,102,241,0.12); color:#a5b4fc;">
                  Date Range
                </span>
              </div>
            </div>
          </Link>

          <!-- Annual Summary -->
          <Link
            :href="route('company.reports.annual-summary', company.id)"
            class="fv-card p-5 hover:scale-[1.01] transition-transform cursor-pointer"
            style="text-decoration:none;"
          >
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                style="background:rgba(16,185,129,0.15); color:#6ee7b7;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <p class="fv-text-primary font-semibold text-sm">Annual Summary</p>
                <p class="fv-text-muted text-xs mt-1 leading-relaxed">
                  Full-year review: rent collected, expenses paid, NOI, market value, unrealized gain — per unit or whole company.
                </p>
                <span class="inline-block mt-2 text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:rgba(16,185,129,0.12); color:#6ee7b7;">
                  Year · Investor Ready
                </span>
              </div>
            </div>
          </Link>

          <!-- Rent Benchmark -->
          <Link
            :href="route('company.reports.rent-benchmark', company.id)"
            class="fv-card p-5 hover:scale-[1.01] transition-transform cursor-pointer"
            style="text-decoration:none;"
          >
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                style="background:rgba(248,113,113,0.15); color:#f87171;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
              </div>
              <div>
                <p class="fv-text-primary font-semibold text-sm">Rent Benchmark</p>
                <p class="fv-text-muted text-xs mt-1 leading-relaxed">
                  Compare rent across units of the same type by governorate and province. Flags under-market contracts.
                </p>
                <span class="inline-block mt-2 text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:rgba(248,113,113,0.12); color:#f87171;">
                  Correction Radar
                </span>
              </div>
            </div>
          </Link>

          <!-- Company Expense Report -->
          <Link
            :href="route('company.reports.expense-report', company.id)"
            class="fv-card p-5 hover:scale-[1.01] transition-transform cursor-pointer"
            style="text-decoration:none;"
          >
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                style="background:rgba(186,117,23,0.15); color:#FAC775;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>
              <div>
                <p class="fv-text-primary font-semibold text-sm">Company Expense Report</p>
                <p class="fv-text-muted text-xs mt-1 leading-relaxed">
                  All expense payments for a date range grouped by category and item, with per-property breakdown.
                </p>
                <span class="inline-block mt-2 text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:rgba(186,117,23,0.12); color:#FAC775;">
                  Date Range
                </span>
              </div>
            </div>
          </Link>

        </div>
      </div>

      <!-- ══════════════════════════════════════════════════
           SECTION 2 — CUSTOM REPORT BUILDER
      ═══════════════════════════════════════════════════ -->
      <div>
        <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
          <p class="text-xs font-bold uppercase tracking-widest" style="color:#26C6DA;">Custom Reports</p>
          <Link
            :href="route('company.reports.custom.builder', company.id)"
            class="fv-btn-gold text-xs px-4 py-2 flex items-center gap-1.5"
            style="text-decoration:none;"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Custom Report
          </Link>
        </div>

        <!-- Saved reports table -->
        <div v-if="savedReports.length > 0" class="fv-card" style="padding:0; overflow:hidden;">
          <table class="w-full text-sm">
            <thead>
              <tr style="background:var(--fv-bg-header); border-bottom:1px solid var(--fv-border);">
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--fv-text-label);">Report Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--fv-text-label);">Data Source</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide hidden md:table-cell" style="color:var(--fv-text-label);">Last Run</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide hidden lg:table-cell" style="color:var(--fv-text-label);">Created By</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide" style="color:var(--fv-text-label);">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(rep, ri) in savedReports" :key="rep.id"
                style="border-bottom:1px solid var(--fv-border);"
                :style="ri % 2 === 0 ? 'background:var(--fv-bg-card)' : 'background:var(--fv-bg)'">

                <!-- Name + source badge -->
                <td class="px-5 py-3">
                  <span class="font-semibold" style="color:var(--fv-text-primary);">{{ rep.name }}</span>
                </td>

                <!-- Data source -->
                <td class="px-5 py-3">
                  <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-semibold"
                    :style="sourceStyle(rep.data_source)">
                    {{ sourceLabel(rep.data_source) }}
                  </span>
                </td>

                <!-- Last run -->
                <td class="px-5 py-3 hidden md:table-cell" style="color:var(--fv-text-muted);">
                  {{ rep.last_run_at ? formatDate(rep.last_run_at) : '—' }}
                </td>

                <!-- Created by -->
                <td class="px-5 py-3 hidden lg:table-cell" style="color:var(--fv-text-muted);">
                  {{ rep.creator?.name ?? '—' }}
                </td>

                <!-- Actions -->
                <td class="px-5 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <!-- Run (open builder with this report loaded) -->
                    <Link
                      :href="route('company.reports.custom.edit', { company: company.id, report: rep.id })"
                      class="fv-action-btn"
                      title="Run / Edit"
                      style="text-decoration:none;"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </Link>
                    <!-- Delete -->
                    <button @click="confirmDelete(rep)" class="fv-action-btn-danger" title="Delete">
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

        <!-- Empty state -->
        <div v-else class="fv-card text-center py-12">
          <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--fv-text-muted)">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
          </svg>
          <p class="text-sm font-semibold mb-1" style="color:var(--fv-text-primary)">No saved custom reports yet</p>
          <p class="text-xs mb-4" style="color:var(--fv-text-muted)">Build an analytical view, run it, then save it with a name to reuse it anytime.</p>
          <Link
            :href="route('company.reports.custom.builder', company.id)"
            class="fv-btn-gold text-xs px-5 py-2 inline-flex items-center gap-1.5"
            style="text-decoration:none;"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Build Your First Report
          </Link>
        </div>
      </div>

    </div>

    <!-- ── Delete Confirm Modal ────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-[200] flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.6)">
        <div class="fv-card w-full max-w-sm" style="padding:24px">
          <h3 class="text-base font-bold mb-2" style="color:var(--fv-text-primary)">Delete Report</h3>
          <p class="text-sm mb-5" style="color:var(--fv-text-muted)">
            Delete <span class="font-semibold" style="color:var(--fv-text-primary)">{{ deleteTarget.name }}</span>? This cannot be undone.
          </p>
          <div class="flex gap-3 justify-end">
            <button @click="deleteTarget = null" class="fv-btn-secondary px-4 py-2 text-sm">Cancel</button>
            <button @click="doDelete" :disabled="deleting"
              class="px-4 py-2 text-sm font-semibold rounded-lg disabled:opacity-40"
              style="background:#7F1D1D; color:#FCA5A5; border:1px solid #991B1B;">
              {{ deleting ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:      { type: Object, required: true },
  savedReports: { type: Array,  default: () => [] },
})

const deleteTarget = ref(null)
const deleting     = ref(false)

function sourceLabel(src) {
  return {
    rent_collections:  'Rent Collections',
    rent_revenues:     'Rent Revenues',
    property_expenses: 'Property Expenses',
    installment_dues:  'Installment Dues',
  }[src] ?? src
}

function sourceStyle(src) {
  return {
    rent_collections:  'background:rgba(20,144,168,0.12); color:#48C4D8;',
    rent_revenues:     'background:rgba(186,117,23,0.12); color:#FAC775;',
    property_expenses: 'background:rgba(248,113,113,0.12); color:#F87171;',
    installment_dues:  'background:rgba(167,139,250,0.12); color:#A78BFA;',
  }[src] ?? 'background:rgba(100,100,100,0.12); color:#94A3B8;'
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function confirmDelete(rep) {
  deleteTarget.value = rep
}

function doDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  router.delete(
    route('company.reports.custom.destroy', { company: props.company.id, report: deleteTarget.value.id }),
    {
      preserveScroll: true,
      onSuccess: () => { deleteTarget.value = null; deleting.value = false },
      onError:   () => { deleting.value = false },
    }
  )
}
</script>