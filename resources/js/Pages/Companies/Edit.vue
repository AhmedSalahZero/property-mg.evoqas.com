<template>
  <Head :title="`Edit ${company.name} — VERO Property Management`" />
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg">

      <!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
      <div class="fv-header-bg border-b fv-border">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center gap-4">

            <Link :href="route('companies.show', company.id)" class="fv-back-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
            </Link>

            <div class="min-w-0">
              <p class="text-xs font-bold uppercase tracking-widest mb-0.5 fv-breadcrumb">
                Companies · Edit
              </p>
              <h1 class="text-2xl font-bold fv-text-primary truncate">{{ company.name }}</h1>
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
          submit-label="Save Changes"
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

const props = defineProps({
  company: Object,
  parents: Array,
  modules: Object,
})

const form = useForm({
  // Basic
  name:                props.company.name               ?? '',
  trade_name:          props.company.trade_name         ?? '',
  legal_structure:     props.company.legal_structure    ?? '',
  established_year:    props.company.established_year   ?? null,
  parent_id:           props.company.parent_id          ?? null,
  // Tax
  tax_type:            props.company.tax_type           ?? '',
  // Financial
  currency:            props.company.currency           ?? 'EGP',
  fiscal_year_start:   props.company.fiscal_year_start  ?? null,
  registration_number: props.company.registration_number ?? '',
  tax_id:              props.company.tax_id             ?? '',
  // Modules
  enabled_modules:     props.company.enabled_modules    ?? [],
  // Contact
  country:             props.company.country            ?? '',
  city:                props.company.city               ?? '',
  address:             props.company.address            ?? '',
  phone:               props.company.phone              ?? '',
  email:               props.company.email              ?? '',
  website:             props.company.website            ?? '',
  // Meta
  notes:               props.company.notes              ?? '',
  is_active:           props.company.is_active          ?? true,
})

function submit() {
  form.put(route('companies.update', props.company.id))
}
</script>

<style scoped>
.fv-breadcrumb { color: var(--fv-gold, #BA7517); }

.fv-back-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  background-color: var(--fv-bg-card, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  border-radius: 0.5rem; color: var(--fv-text-muted, #6B96B8);
  transition: all 0.15s ease; flex-shrink: 0; text-decoration: none;
}
.fv-back-btn:hover {
  border-color: var(--fv-border-focus, #1490A8);
  color: #48C4D8;
}
</style>