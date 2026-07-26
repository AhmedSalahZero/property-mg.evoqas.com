<template>
  <AuthenticatedLayout :title="pageTitle">
    <div class="p-6 space-y-6" style="max-width:760px">

      <!-- ── Page Header ─────────────────────────────────────────── -->
      <div class="flex items-center gap-3">
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
          <h1 class="text-lg font-bold fv-text-primary">{{ pageTitle }}</h1>
          <p class="text-xs fv-text-muted mt-0.5">
            {{ targetLabel }}
            <template v-if="mode === 'whole'">
              — one lump sum for all {{ unitsCount }} remaining unit(s); it will be divided by total area to price each unit individually.
            </template>
          </p>
        </div>
      </div>

      <div class="fv-card !p-6">

        <div v-if="sellError" class="mb-4 px-3 py-2 rounded-lg text-sm"
          style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#f87171;">
          {{ sellError }}
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-xs fv-text-label mb-1">Sale Date *</label>
            <input type="date" v-model="form.sale_date" class="fv-input w-full rounded-lg px-3 py-2 text-sm"/>
          </div>
          <div>
            <label class="block text-xs fv-text-label mb-1">Buyer Name</label>
            <input type="text" v-model="form.buyer_name" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="Optional"/>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-xs fv-text-label mb-1">
              {{ mode === 'whole' ? 'Total Sale Price *' : 'Sale Price *' }}
            </label>
            <input type="number" min="0.01" step="0.01" v-model="form.sale_price"
              class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00"/>
          </div>
          <div>
            <label class="block text-xs fv-text-label mb-1">Currency</label>
            <select v-model="form.currency" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
              <option value="">Company base currency</option>
              <option v-for="c in ['EGP','USD','EUR','GBP','SAR','AED','QAR']" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-xs fv-text-label mb-1">Selling Costs %</label>
            <input type="number" min="0" max="100" step="0.01" v-model="form.selling_costs_pct"
              class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="e.g. 5"/>
          </div>
          <div>
            <label class="block text-xs fv-text-label mb-1">Payment Method *</label>
            <select v-model="form.payment_method" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
              <option value="cash">Cash</option>
              <option value="installments">Installments</option>
            </select>
          </div>
        </div>

        <!-- ── Installment Repeater ─────────────────────────────── -->
        <div v-if="form.payment_method === 'installments'" class="mb-4 p-4 rounded-lg" style="background:rgba(139,92,246,0.06); border:1px solid rgba(139,92,246,0.2);">
          <p class="text-xs fv-text-muted mb-3">
            Fill in exactly the amounts and dates agreed with the buyer. This becomes the receivable
            schedule tracked in Cash Forecast — rows must add up to the {{ mode === 'whole' ? 'total sale price' : 'sale price' }} above.
          </p>

          <div
            v-for="(row, idx) in form.installment_rows"
            :key="idx"
            class="grid gap-3 mb-2 items-end"
            style="grid-template-columns: 1fr 1fr auto"
          >
            <div>
              <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Amount</label>
              <input v-model="row.amount" type="number" min="0" step="0.01" placeholder="0.00" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label v-if="idx === 0" class="block text-xs fv-text-label mb-1">Installment Payment Date</label>
              <input v-model="row.date" type="date" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
            </div>
            <div class="flex items-end pb-0.5">
              <button
                v-if="form.installment_rows.length > 1"
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

          <button @click="addRow" class="fv-btn-gold text-xs font-semibold px-4 py-1.5 rounded-lg mt-1 mb-3">
            + Add Row
          </button>

          <!-- Live total vs sale price -->
          <div class="flex items-center justify-between text-xs px-1 py-2 rounded-lg"
            :style="totalsMatch
              ? 'background:rgba(52,211,153,0.08); color:#34d399;'
              : 'background:rgba(239,68,68,0.08); color:#f87171;'">
            <span>Rows total: <strong>{{ formatMoney(rowsTotal) }}</strong></span>
            <span>{{ mode === 'whole' ? 'Total Sale Price' : 'Sale Price' }}: <strong>{{ formatMoney(salePriceNum) }}</strong></span>
            <span v-if="!totalsMatch">Difference: {{ formatMoney(rowsTotal - salePriceNum) }}</span>
            <span v-else>✓ Matches</span>
          </div>
        </div>

        <div class="mb-5">
          <label class="block text-xs fv-text-label mb-1">Notes</label>
          <textarea v-model="form.notes" rows="2" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="Optional"></textarea>
        </div>

        <div class="flex gap-3">
          <Link :href="route('company.properties.index', company.id)" class="flex-1 fv-btn-secondary text-sm font-medium px-5 py-2 rounded-lg text-center">
            Cancel
          </Link>
          <button @click="submitSell" :disabled="!canSubmit"
            class="flex-1 fv-btn-gold text-sm font-semibold px-5 py-2 rounded-lg disabled:opacity-50">
            {{ selling ? 'Saving…' : 'Confirm Sale' }}
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:    { type: Object, required: true },
  property:   { type: Object, required: true },
  unit:       { type: Object, default: null },   // present only for a child unit inside Building/Land/Complex
  mode:       { type: String, required: true },  // 'unit' | 'whole'
  unitsCount: { type: Number, default: null },   // 'whole' mode only
})

const pageTitle   = computed(() => props.mode === 'whole' ? 'Sell Entire Property' : 'Sell Unit')
const targetLabel = computed(() => props.unit ? props.unit.unit_name : props.property.property_name)

const selling   = ref(false)
const sellError = ref('')

const form = ref({
  sale_date: new Date().toISOString().slice(0, 10),
  buyer_name: '',
  sale_price: '',
  currency: props.unit?.currency || props.property?.currency || '',
  selling_costs_pct: '',
  payment_method: 'cash',
  installment_rows: [{ amount: '', date: '' }],
  notes: '',
})

function addRow() {
  form.value.installment_rows.push({ amount: '', date: '' })
}
function removeRow(idx) {
  form.value.installment_rows.splice(idx, 1)
}

// ── Live totals check (backend enforces the same rule) ─────────────────────
const salePriceNum = computed(() => parseFloat(form.value.sale_price) || 0)
const rowsTotal = computed(() =>
  form.value.installment_rows.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0)
)
const totalsMatch = computed(() => Math.abs(rowsTotal.value - salePriceNum.value) <= 0.01)

const canSubmit = computed(() => {
  if (selling.value) return false
  if (!form.value.sale_date || !salePriceNum.value) return false
  if (form.value.payment_method === 'installments') {
    const rowsFilled = form.value.installment_rows.every(r => r.amount !== '' && r.date)
    return rowsFilled && totalsMatch.value
  }
  return true
})

function formatMoney(val) {
  return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function submitSell() {
  selling.value = true
  sellError.value = ''

  const cleaned = {
    sale_date: form.value.sale_date,
    buyer_name: form.value.buyer_name || null,
    currency: form.value.currency || null,
    selling_costs_pct: form.value.selling_costs_pct === '' ? null : form.value.selling_costs_pct,
    payment_method: form.value.payment_method,
    notes: form.value.notes || null,
    installment_rows: form.value.payment_method === 'installments'
      ? form.value.installment_rows.map(r => ({ amount: r.amount, date: r.date }))
      : [],
  }

  let url, payload
  if (props.mode === 'whole') {
    url = route('company.properties.sell-whole', [props.company.id, props.property.id])
    payload = { ...cleaned, total_sale_price: form.value.sale_price }
  } else if (props.unit) {
    url = route('company.properties.units.sell', [props.company.id, props.property.id, props.unit.id])
    payload = { ...cleaned, sale_price: form.value.sale_price }
  } else {
    url = route('company.properties.sell', [props.company.id, props.property.id])
    payload = { ...cleaned, sale_price: form.value.sale_price }
  }

  router.post(url, payload, {
    onError: (errors) => {
      sellError.value = errors.sale || errors.installment_rows || Object.values(errors)[0] || 'Could not record the sale.'
    },
    onFinish: () => { selling.value = false },
  })
}
</script>
