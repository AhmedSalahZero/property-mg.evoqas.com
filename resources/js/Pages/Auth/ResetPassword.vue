<script setup>
import AuthBrandLayout from '@/Layouts/AuthBrandLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password — VERO Property Management" />

    <AuthBrandLayout
        title="Reset password"
        subtitle="Choose a new password for your account"
    >
        <form @submit.prevent="submit" class="zav-form">
            <div class="zav-field">
                <label class="zav-label" for="email">Email Address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="zav-input"
                />
                <p v-if="form.errors.email" class="zav-error">{{ form.errors.email }}</p>
            </div>

            <div class="zav-field">
                <label class="zav-label" for="password">New Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    placeholder="••••••••"
                    required
                    autocomplete="new-password"
                    class="zav-input"
                />
                <p v-if="form.errors.password" class="zav-error">{{ form.errors.password }}</p>
            </div>

            <div class="zav-field">
                <label class="zav-label" for="password_confirmation">Confirm Password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    placeholder="••••••••"
                    required
                    autocomplete="new-password"
                    class="zav-input"
                />
                <p v-if="form.errors.password_confirmation" class="zav-error">
                    {{ form.errors.password_confirmation }}
                </p>
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
                {{ form.processing ? 'Resetting...' : 'Reset Password' }}
            </button>
        </form>

        <div class="zav-back-wrap">
            <Link :href="route('login')" class="zav-forgot">
                Back to sign in
            </Link>
        </div>
    </AuthBrandLayout>
</template>
