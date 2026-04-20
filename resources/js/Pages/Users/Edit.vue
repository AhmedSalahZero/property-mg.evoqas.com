<template>
  <Head :title="`Edit ${user.name} — FinVero NBFS`" />
  <AuthenticatedLayout>
    <div class="min-h-screen fv-bg text-white">

      <!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
      <div class="fv-header-bg border-b fv-border">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center gap-4">
            <Link :href="route('users.index')" class="fv-back-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
            </Link>
            <div class="min-w-0">
              <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-0.5">
                Users · Edit
              </p>
              <h1 class="text-2xl font-bold fv-text-primary truncate">{{ user.name }}</h1>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ FORM ══════════════════════════════════════════════════════════════ -->
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <UserForm
          :form="form"
          :errors="form.errors"
          :companies="companies"
          :roles="roles"
          :auth-role="authRole"
          :is-edit="true"
          submit-label="Save Changes"
          :processing="form.processing"
          :admin-count="adminCount"
          @submit="submit"
        />
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UserForm from './UserForm.vue'

const props = defineProps({
  user:        Object,
  companies:   Array,
  roles:       Array,
  authRole:    String,
  myCompanyId: Number,
})

const form = useForm({
  name:                  props.user.name ?? '',
  email:                 props.user.email ?? '',
  password:              '',
  password_confirmation: '',
  company_id:            props.user.company_id ?? null,
  role:                  props.user.role ?? null,
  job_title:             props.user.job_title ?? '',
  phone:                 props.user.phone ?? '',
  is_active:             props.user.is_active ?? true,
  max_users:             props.user.max_users ?? null,
})

const adminCount = computed(() => 0)

function submit() {
  form.put(route('users.update', props.user.id))
}
</script>

<style scoped>
.fv-bg          { background-color: #0B1120; }
.fv-header-bg   { background-color: #0d1426; }
.fv-border      { border-color: #1e2d45; }
.fv-text-primary { color: #f1f5f9; }

.fv-back-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  background-color: #101827; border: 1px solid #1e2d45;
  border-radius: 0.5rem; color: #64748b;
  transition: all 0.15s; flex-shrink: 0; text-decoration: none;
}
.fv-back-btn:hover { border-color: #3B82F6; color: #93c5fd; }
</style>