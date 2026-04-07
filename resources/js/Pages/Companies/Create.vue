<template>
  <Head title="New Company — VERO Property Management" />
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg">

      <!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
      <div class="fv-header-bg border-b fv-border">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center gap-4">

            <Link :href="route('companies.index')" class="fv-back-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
            </Link>

            <div>
              <!-- Gold breadcrumb label — instantly visible ZAVERO identity -->
              <p class="text-xs font-bold uppercase tracking-widest mb-0.5 fv-breadcrumb">
                Companies · New
              </p>
              <h1 class="text-2xl font-bold fv-text-primary">Create Company</h1>
            </div>

          </div>
        </div>
      </div>

      <!-- ══ FORM ══════════════════════════════════════════════════════════════ -->
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <CompanyForm
          :form="form"
          :errors="form.errors"
          :parents="parents"
          :modules="modules"
          :processing="form.processing"
          submit-label="Create Company"
          @submit="submit"
        />
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CompanyForm from './CompanyForm.vue'

defineProps({
  parents: { type: Array,  default: () => [] },
  modules: { type: Object, default: () => ({}) },
})

const form = useForm({
  // Basic
  name:                '',
  trade_name:          '',
  legal_structure:     '',
  established_year:    null,
  parent_id:           null,
  // Tax
  tax_type:            '',
  // Financial
  currency:            'EGP',
  fiscal_year_start:   null,
  registration_number: '',
  tax_id:              '',
  // Modules
  enabled_modules:     [],
  // Contact
  country:             'Egypt',
  city:                '',
  address:             '',
  phone:               '',
  email:               '',
  website:             '',
  // Meta
  notes:               '',
  is_active:           true,
})

function submit() {
  form.post(route('companies.store'))
}
</script>

<style scoped>
/* Back button */
.fv-back-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  background-color: var(--fv-bg-card, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  border-radius: 0.5rem;
  color: var(--fv-text-muted, #6B96B8);
  transition: all 0.15s ease;
  flex-shrink: 0; text-decoration: none;
}
.fv-back-btn:hover {
  border-color: var(--fv-border-focus, #1490A8);
  color: #48C4D8;
}

/* Gold breadcrumb — the first thing your eye hits */
.fv-breadcrumb {
  color: var(--fv-gold, #BA7517);
}
</style>