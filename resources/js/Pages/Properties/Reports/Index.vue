<template>
  <AuthenticatedLayout :title="`Reports - ${property.property_name}`">
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Property Reports</h1>
          <p class="text-xs fv-text-muted mt-1">
            {{ property.property_name }}
          </p>
        </div>
        <Link
          :href="route('company.properties.index', company.id)"
          class="btn-sm btn-ghost"
        >
          Back To Properties
        </Link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <Link
          :href="route('company.properties.reports.rent-expenses', [company.id, property.id])"
          class="fv-card p-5 hover:scale-[1.01] transition-transform"
        >
          <div class="flex items-start gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              style="background:rgba(20,144,168,0.2); color:#48C4D8;"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
            <div>
              <p class="fv-text-primary font-semibold text-sm">Rent / Expenses Report</p>
              <p class="fv-text-muted text-xs mt-1">
                Monthly rent revenues, expenses, and cashflow view for selected period.
              </p>
            </div>
          </div>
        </Link>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
  company: { type: Object, required: true },
  property: { type: Object, required: true },
})
</script>
