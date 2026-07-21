<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
  company:       Object,
  prospects:     Array,
  statusLabels:  Object,
  natureLabels:  Object,
})

const statusColor = (status) => ({
  evaluating: '#eab308',
  pursuing:   '#3b82f6',
  passed:     'var(--fv-text-muted)',
  acquired:   '#22c55e',
}[status] ?? 'var(--fv-text-muted)')

const fmt = (v) => v == null ? '—' : Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 })

async function deleteProspect(prospect) {
  if (!confirm(`Delete "${prospect.prospect_name}"? This removes the prospect and cannot be undone.`)) return
  await axios.delete(route('company.properties.investment-decision.destroy', [props.company.id, prospect.id]))
  router.reload({ only: ['prospects'] })
}
</script>

<template>
  <Head title="Investment Decision — Prospects" />
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Investment Decision — Prospects</h1>
          <p class="fv-text-muted text-sm mt-1">Candidate acquisitions you're evaluating. None of these are part of your portfolio yet.</p>
        </div>
        <Link :href="route('company.properties.investment-decision.create', company.id)" class="fv-btn-gold rounded-lg px-4 py-2 text-sm font-semibold">
          + New Prospect
        </Link>
      </div>

      <div v-if="prospects.length === 0" class="fv-card rounded-xl p-10 text-center">
        <p class="fv-text-muted">No prospects yet. Add your first candidate acquisition to start a feasibility study.</p>
        <Link :href="route('company.properties.investment-decision.create', company.id)" class="fv-btn-gold rounded-lg px-4 py-2 text-sm font-semibold inline-block mt-4">
          + New Prospect
        </Link>
      </div>

      <div v-else class="fv-card rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="fv-border" style="border-bottom-width:1px;">
              <th class="text-left py-3 px-4 fv-text-label">Prospect</th>
              <th class="text-left py-3 px-4 fv-text-label">Status</th>
              <th class="text-left py-3 px-4 fv-text-label">Location</th>
              <th class="text-right py-3 px-4 fv-text-label">Purchase Price</th>
              <th class="text-right py-3 px-4 fv-text-label">Expected Monthly Rent</th>
              <th class="text-right py-3 px-4 fv-text-label">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in prospects" :key="p.id" class="fv-border" style="border-bottom-width:1px;">
              <td class="py-3 px-4">
                <div class="font-semibold fv-text-primary">{{ p.prospect_name }}</div>
                <div class="fv-text-muted text-xs">
                  <span class="fv-tag text-xs" style="margin-right:6px;">{{ natureLabels[p.nature] }}</span>
                  <template v-if="p.nature === 'unit'">{{ p.property_category?.category_name || '—' }}<span v-if="p.property_type"> · {{ p.property_type.type_name }}</span></template>
                  <template v-else>{{ p.unit_count }} unit{{ p.unit_count === 1 ? '' : 's' }}</template>
                </div>
              </td>
              <td class="py-3 px-4">
                <span class="fv-badge text-xs" :style="{ color: statusColor(p.status) }">{{ statusLabels[p.status] }}</span>
              </td>
              <td class="py-3 px-4 fv-text-muted">{{ p.location || p.governorate || '—' }}</td>
              <td class="py-3 px-4 text-right fv-text-primary">{{ fmt(p.total_purchase_price) }} {{ p.currency }}</td>
              <td class="py-3 px-4 text-right fv-text-primary">{{ fmt(p.total_expected_monthly_rent) }} {{ p.currency }}</td>
              <td class="py-3 px-4 text-right">
                <div class="flex justify-end gap-2">
                  <Link :href="route('company.properties.investment-decision.workspace', [company.id, p.id])" class="fv-btn-gold rounded-lg text-xs font-semibold px-3 py-1.5">
                    Feasibility Study
                  </Link>
                  <Link :href="route('company.properties.investment-decision.edit', [company.id, p.id])" class="fv-action-btn" title="Edit">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </Link>
                  <button @click="deleteProspect(p)" class="fv-action-btn fv-action-btn-danger" title="Delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
  </AuthenticatedLayout>
</template>
