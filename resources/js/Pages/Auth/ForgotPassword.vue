<script setup>
import AuthBrandLayout from '@/Layouts/AuthBrandLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot Password — VERO Property Management" />

    <AuthBrandLayout
        title="Forgot password"
        subtitle="Enter your email and we will send you a reset link"
    >
        <div v-if="status" class="zav-status">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="zav-form">
            <div class="zav-field">
                <label class="zav-label" for="email">Email Address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="your@email.com"
                    required
                    autofocus
                    autocomplete="username"
                    class="zav-input"
                />
                <p v-if="form.errors.email" class="zav-error">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="zav-btn-submit"
            >
                <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ form.processing ? 'Sending...' : 'Email Password Reset Link' }}
            </button>
        </form>

        <div class="zav-back-wrap">
            <Link :href="route('login')" class="zav-forgot">
                Back to sign in
            </Link>
        </div>
    </AuthBrandLayout>
</template>
