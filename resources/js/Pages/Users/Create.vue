<template>
  <Head title="New User — FinVero NBFS" />
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
            <div>
              <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-0.5">
                Users · New
              </p>
              <h1 class="text-2xl font-bold fv-text-primary">Add User</h1>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ FORM ══════════════════════════════════════════════════════════════ -->
      <div class="max-w-3xl mx-auto  px-4 sm:px-6 lg:px-8 py-8">
        <UserForm
          :form="form"
          :errors="form.errors"
          :companies="companies"
          :roles="roles"
          :auth-role="authRole"
          :is-edit="false"
          submit-label="Create User"
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
  companies:   Array,
  roles:       Array,
  authRole:    String,
  myCompanyId: Number,
})

const form = useForm({
  name:                  '',
  email:                 '',
  password:              '',
  password_confirmation: '',
  company_id:            props.myCompanyId ?? null,
  role:                  null,
  job_title:             '',
  phone:                 '',
  is_active:             true,
})

// Count existing admins for selected company (passed from controller if needed)
const adminCount = computed(() => 0)

function submit() {
  form.post(route('users.store'))
}
</script>

<style scoped>
.fv-bg          { background-color: #0C1829; }
.fv-header-bg   { background-color: #0E1E34; }
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