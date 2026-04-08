<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: { type: Object, required: true },
  property: { type: Object, required: true },
  defaultStartDate: { type: String, required: true },
  defaultEndDate: { type: String, required: true },
})

const startDate = ref(props.defaultStartDate)
const endDate = ref(props.defaultEndDate)
const loading = ref(false)
const loaded = ref(false)
const activeTab = ref('rent-expenses')
const months = ref([])
const rentByMonth = ref({})
const expensesByMonth = ref({})
const cashflowByMonth = ref({})
const accumulatedByMonth = ref({})
const errorMessage = ref('')

const monthName = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const fmtMonth = (ym) => {
  if (!ym) return ''
  const [y, m] = ym.split('-')
  return `${monthName[Number(m) - 1]} ${y}`
}
const n = (v) => Number(v || 0)
const fmt = (v) => {
  const val = Number(v || 0)
  if (!val) return '—'
  return val.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

const totalRent = computed(() =>
  months.value.reduce((sum, m) => sum + n(rentByMonth.value[m]), 0)
)
const totalExpenses = computed(() =>
  months.value.reduce((sum, m) => sum + n(expensesByMonth.value[m]), 0)
)

async function submitReport() {
  if (!startDate.value || !endDate.value) return
  if (startDate.value > endDate.value) {
    errorMessage.value = 'Start date must be before or equal to end date.'
    return
  }

  loading.value = true
  errorMessage.value = ''

  try {
    const url = route('company.properties.reports.rent-expenses.data', [props.company.id, props.property.id])
      + `?start_date=${startDate.value}&end_date=${endDate.value}`
    const res = await fetch(url, {
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    if (!res.ok) {
      const text = await res.text()
      throw new Error(text || `HTTP ${res.status}`)
    }
    const data = await res.json()
    months.value = data.months || []
    rentByMonth.value = data.rentByMonth || {}
    expensesByMonth.value = data.expensesByMonth || {}
    cashflowByMonth.value = data.cashflowByMonth || {}
    accumulatedByMonth.value = data.accumulatedByMonth || {}
    loaded.value = true
  } catch (err) {
    errorMessage.value = 'Failed to load report data.'
    console.error(err)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthenticatedLayout :title="`Rent / Expenses Report - ${property.property_name}`">
    <div class="p-6 space-y-5">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Rent / Expenses Report</h1>
          <p class="text-xs fv-text-muted mt-1">{{ property.property_name }}</p>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="route('company.properties.reports.index', [company.id, property.id])" class="btn-sm btn-ghost">
            Back To Reports
          </Link>
        </div>
      </div>

      <div class="fv-card p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
          <div>
            <label class="text-xs fv-text-muted block mb-1">Start Date</label>
            <input v-model="startDate" type="date" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs fv-text-muted block mb-1">End Date</label>
            <input v-model="endDate" type="date" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="md:col-span-2">
            <button
              :disabled="loading"
              @click="submitReport"
              class="btn-sm btn-teal"
            >
              {{ loading ? 'Loading...' : 'Submit' }}
            </button>
          </div>
        </div>
        <p v-if="errorMessage" class="text-xs mt-2" style="color:#f87171;">{{ errorMessage }}</p>
      </div>

      <div v-if="loaded" class="space-y-4">
        <div class="flex items-center gap-2">
          <button
            class="px-3 py-1.5 rounded-lg text-sm"
            :class="activeTab === 'rent-expenses' ? 'tab-active' : 'tab-inactive'"
            @click="activeTab = 'rent-expenses'"
          >
            Rent / Expenses Report
          </button>
          <button
            class="px-3 py-1.5 rounded-lg text-sm"
            :class="activeTab === 'cashflow' ? 'tab-active' : 'tab-inactive'"
            @click="activeTab = 'cashflow'"
          >
            Cashflow Report
          </button>
        </div>

        <div v-if="activeTab === 'rent-expenses'" class="fv-card overflow-x-auto">
          <table class="w-full min-w-max">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border); background:rgba(11,26,48,0.6);">
                <th class="text-left px-4 py-3 text-xs text-amber-400 uppercase">Item</th>
                <th v-for="m in months" :key="m" class="text-center px-4 py-3 text-xs text-amber-400 uppercase">
                  {{ fmtMonth(m) }}
                </th>
                <th class="text-right px-4 py-3 text-xs text-amber-400 uppercase">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid var(--fv-border);">
                <td class="px-4 py-3 text-sm fv-text-primary">Rent Revenues</td>
                <td v-for="m in months" :key="`r-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(rentByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#4ade80;">
                  {{ fmt(totalRent) }}
                </td>
              </tr>
              <tr>
                <td class="px-4 py-3 text-sm fv-text-primary">Expenses</td>
                <td v-for="m in months" :key="`e-${m}`" class="px-4 py-3 text-center text-sm fv-text-primary">
                  {{ fmt(expensesByMonth[m]) }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold" style="color:#f87171;">
                  {{ fmt(totalExpenses) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="activeTab === 'cashflow'" class="fv-card overflow-x-auto">
          <table class="w-full min-w-max">
            <thead>
              <tr style="border-bottom:1px solid var(--fv-border); background:rgba(11,26,48,0.6);">
                <th class="text-left px-4 py-3 text-xs text-amber-400 uppercase">Item</th>
                <th v-for="m in months" :key="`c-${m}`" class="text-center px-4 py-3 text-xs text-amber-400 uppercase">
                  {{ fmtMonth(m) }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid var(--fv-border);">
                <td class="px-4 py-3 text-sm fv-text-primary">Net Cashflow</td>
                <td
                  v-for="m in months"
                  :key="`n-${m}`"
                  class="px-4 py-3 text-center text-sm"
                  :style="{ color: n(cashflowByMonth[m]) >= 0 ? '#4ade80' : '#f87171' }"
                >
                  {{ fmt(cashflowByMonth[m]) }}
                </td>
              </tr>
              <tr>
                <td class="px-4 py-3 text-sm fv-text-primary">Accumulated Cashflow</td>
                <td
                  v-for="m in months"
                  :key="`a-${m}`"
                  class="px-4 py-3 text-center text-sm"
                  :style="{ color: n(accumulatedByMonth[m]) >= 0 ? '#4ade80' : '#f87171' }"
                >
                  {{ fmt(accumulatedByMonth[m]) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.tab-active {
  background: var(--fv-blue, #1490A8);
  color: #fff;
}
.tab-inactive {
  color: var(--fv-text-muted, #6B96B8);
  background: rgba(11,26,48,0.6);
}
</style>
