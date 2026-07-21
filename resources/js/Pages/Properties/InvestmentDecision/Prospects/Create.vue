<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:         Object,
  categories:      Array,
  natureLabels:    Object,
  currencyOptions: Array,
})

const form = useForm({
  prospect_name:         '',
  status:                'evaluating',
  nature:                'unit',
  country:               'Egypt',
  governorate:           '',
  province:              '',
  location:              '',
  currency:              'EGP',
  notes:                 '',
  // Single-unit fields
  property_category_id:  '',
  property_type_id:      '',
  area:                  '',
  unit_of_measurement:   '',
  purchase_price:        '',
  expected_monthly_rent: '',
  // Multi-unit
  units: [],
})

const typesForCategory = (categoryId) => {
  const cat = props.categories.find(c => c.id === Number(categoryId))
  return cat ? cat.types : []
}

function newUnit() {
  return {
    unit_name: '',
    slot_type: 'built_unit',
    property_category_id: '',
    property_type_id: '',
    area: '',
    unit_of_measurement: '',
    purchase_price: '',
    expected_monthly_rent: '',
  }
}

function addUnit() {
  form.units.push(newUnit())
}

function removeUnit(idx) {
  form.units.splice(idx, 1)
}

const isMultiUnit = () => form.nature !== 'unit'

const unitsTotalPrice = () => form.units.reduce((s, u) => s + (parseFloat(u.purchase_price) || 0), 0)
const unitsTotalRent  = () => form.units.reduce((s, u) => s + (parseFloat(u.expected_monthly_rent) || 0), 0)
const fmt = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 0 })

function submit() {
  if (isMultiUnit() && form.units.length === 0) {
    addUnit()
  }
  form.post(route('company.properties.investment-decision.store', props.company.id))
}
</script>

<template>
  <Head title="New Investment Prospect" />
  <AuthenticatedLayout>
    <div class="max-w-6xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">New Investment Prospect</h1>
          <p class="fv-text-muted text-sm mt-1">A property RAM is considering buying — not yet part of your portfolio. Set the deal economics here, then move to the feasibility workspace to run the three scenarios.</p>
        </div>
        <Link :href="route('company.properties.investment-decision.index', company.id)" class="fv-btn-secondary rounded-lg px-4 py-2 text-sm font-semibold">
          Back to Prospects
        </Link>
      </div>

      <form @submit.prevent="submit" class="fv-card rounded-xl p-6 space-y-5">
        <div>
          <label class="fv-label">Prospect Name *</label>
          <input v-model="form.prospect_name" type="text" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="e.g. Sheikh Zayed Retail Block C" />
          <p v-if="form.errors.prospect_name" class="err-msg">{{ form.errors.prospect_name }}</p>
        </div>

        <div>
          <label class="fv-label">What are you evaluating? *</label>
          <div class="grid grid-cols-4 gap-3 mt-1">
            <button
              v-for="(label, key) in natureLabels" :key="key" type="button"
              @click="form.nature = key"
              class="text-left p-3 rounded-lg border text-xs transition"
              :style="form.nature === key
                ? { borderColor: 'var(--fv-gold)', background: 'rgba(186,117,23,0.08)', color: 'var(--fv-text-primary)' }
                : { borderColor: 'var(--fv-border)', background: 'var(--fv-bg)', color: 'var(--fv-text-label)' }"
            >
              {{ label }}
            </button>
          </div>
          <p class="fv-text-muted text-xs mt-2" v-if="isMultiUnit()">
            You'll add each unit below — its own category, type, price, and expected rent. The total purchase price and rent for this deal will be the sum of all units.
          </p>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="fv-label">Country</label>
            <input v-model="form.country" type="text" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Governorate</label>
            <input v-model="form.governorate" type="text" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="fv-label">Province / District</label>
            <input v-model="form.province" type="text" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>

        <div>
          <label class="fv-label">Location (free text)</label>
          <input v-model="form.location" type="text" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="e.g. 6th of October City" />
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div class="col-span-2"></div>
          <div>
            <label class="fv-label">Currency</label>
            <select v-model="form.currency" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
              <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
            </select>
            <p class="fv-text-muted text-xs mt-1" v-if="isMultiUnit()">Applies to every unit in this deal.</p>
          </div>
        </div>

        <hr class="fv-border" style="border-top-width:1px; opacity:0.4;" />

        <!-- ── Single Unit fields ─────────────────────────────────────── -->
        <template v-if="!isMultiUnit()">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="fv-label">Category</label>
              <select v-model="form.property_category_id" class="fv-select w-full rounded-lg px-3 py-2 text-sm" @change="form.property_type_id = ''">
                <option value="">— Not set —</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.category_name }}</option>
              </select>
            </div>
            <div>
              <label class="fv-label">Type</label>
              <select v-model="form.property_type_id" class="fv-select w-full rounded-lg px-3 py-2 text-sm" :disabled="!form.property_category_id">
                <option value="">— Not set —</option>
                <option v-for="t in typesForCategory(form.property_category_id)" :key="t.id" :value="t.id">{{ t.type_name }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="fv-label">Area</label>
              <input v-model="form.area" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="fv-label">Unit of Measurement</label>
              <input v-model="form.unit_of_measurement" type="text" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="sqm" />
            </div>
          

          <div>
            <label class="fv-label">Purchase Price *</label>
            <input v-model="form.purchase_price" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
            <p v-if="form.errors.purchase_price" class="err-msg">{{ form.errors.purchase_price }}</p>
          </div>

          <div>
            <label class="fv-label">Expected Monthly Rent</label>
            <input v-model="form.expected_monthly_rent" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
            <p class="fv-text-muted text-xs mt-1">The full rent once occupied — the workspace applies a vacancy assumption on top of this.</p>
          </div>
          </div>
        </template>

        <!-- ── Multi-Unit repeater ────────────────────────────────────── -->
        <template v-else>
          <div class="flex items-center justify-between">
            <label class="fv-label">Units *</label>
            <button type="button" @click="addUnit" class="fv-btn-secondary rounded-lg px-3 py-1.5 text-xs font-semibold">+ Add Unit</button>
          </div>
          <p v-if="form.errors.units" class="err-msg">{{ form.errors.units }}</p>

          <div v-if="form.units.length === 0" class="text-center py-6 fv-text-muted text-sm rounded-lg" style="border:1px dashed var(--fv-border);">
            No units added yet. Click "+ Add Unit" to add each shop/office/apartment in this {{ form.nature }}.
          </div>

          <div v-for="(unit, idx) in form.units" :key="idx" class="p-4 rounded-lg space-y-3" style="background:var(--fv-bg); border:1px solid var(--fv-border);">
            <div class="flex items-center justify-between">
              <span class="fv-text-label text-xs font-semibold">Unit {{ idx + 1 }}</span>
              <button type="button" @click.stop="removeUnit(idx)" class="fv-action-btn fv-action-btn-danger" title="Remove">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>

            <div>
              <label class="fv-label text-xs">Unit Name *</label>
              <input v-model="unit.unit_name" type="text" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" placeholder="e.g. Shop 3, Ground Floor" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="fv-label text-xs">Category</label>
                <select v-model="unit.property_category_id" class="fv-select w-full rounded-lg px-2 py-1.5 text-xs" @change="unit.property_type_id = ''">
                  <option value="">— Not set —</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.category_name }}</option>
                </select>
              </div>
              <div>
                <label class="fv-label text-xs">Type</label>
                <select v-model="unit.property_type_id" class="fv-select w-full rounded-lg px-2 py-1.5 text-xs" :disabled="!unit.property_category_id">
                  <option value="">— Not set —</option>
                  <option v-for="t in typesForCategory(unit.property_category_id)" :key="t.id" :value="t.id">{{ t.type_name }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="fv-label text-xs">Area</label>
                <input v-model="unit.area" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
              </div>
              <div>
                <label class="fv-label text-xs">Unit of Measurement</label>
                <input v-model="unit.unit_of_measurement" type="text" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" placeholder="sqm" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="fv-label text-xs">Purchase Price *</label>
                <input v-model="unit.purchase_price" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
              </div>
              <div>
                <label class="fv-label text-xs">Expected Monthly Rent</label>
                <input v-model="unit.expected_monthly_rent" type="number" min="0" step="0.01" class="fv-input w-full rounded-lg px-2 py-1.5 text-xs" />
              </div>
            </div>
          </div>

          <div v-if="form.units.length > 0" class="p-3 rounded-lg text-sm flex justify-between" style="background:rgba(186,117,23,0.06); border:1px solid var(--fv-gold-border);">
            <span class="fv-text-label">Total across {{ form.units.length }} unit(s)</span>
            <span class="fv-text-primary font-semibold">{{ fmt(unitsTotalPrice()) }} {{ form.currency }} purchase · {{ fmt(unitsTotalRent()) }} {{ form.currency }}/mo rent</span>
          </div>
        </template>

        <div>
          <label class="fv-label">Notes</label>
          <textarea v-model="form.notes" rows="3" class="fv-input w-full rounded-lg px-3 py-2 text-sm"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <Link :href="route('company.properties.investment-decision.index', company.id)" class="fv-btn-secondary rounded-lg px-4 py-2 text-sm font-semibold">Cancel</Link>
          <button type="submit" class="fv-btn-gold rounded-lg px-4 py-2 text-sm font-semibold" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : 'Save & Go to Workspace' }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.err-msg { color: #ef4444; font-size: 12px; margin-top: 4px; }
</style>
