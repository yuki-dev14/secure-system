<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ status: String });

const form = useForm({ email: '' });

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password — SECURE System" />

        <div class="auth-form">
            <!-- Header -->
            <div class="form-header">
                <div class="form-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </div>
                <h2 class="form-title">Forgot Password?</h2>
                <p class="form-subtitle">
                    Enter your email and we'll send you a secure<br>password reset link valid for 24 hours.
                </p>
            </div>

            <!-- Success status -->
            <div v-if="status" class="alert alert-success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="form-body">
                <div class="field-group">
                    <label for="email" class="field-label">Email Address</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.email }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z"/>
                            <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z"/>
                        </svg>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="field-input"
                            placeholder="you@example.com"
                            required
                            autofocus
                            autocomplete="username"
                        />
                    </div>
                    <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                </div>

                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.processing"
                >
                    <span v-if="!form.processing">Send Reset Link</span>
                    <span v-else class="loading-content">
                        <svg class="spinner" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/>
                        </svg>
                        Sending...
                    </span>
                </button>

                <div class="back-link">
                    <a :href="route('login')" class="link">
                        ← Back to Sign In
                    </a>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.auth-form { font-family: 'Inter', sans-serif; color: #e2e8f0; }

.form-header { text-align: center; margin-bottom: 2rem; }
.form-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem; box-shadow: 0 8px 24px rgba(245,158,11,0.35);
}
.form-icon svg { width: 28px; height: 28px; color: white; }
.form-title { font-size: 1.75rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.5rem; }
.form-subtitle { font-size: 0.8125rem; color: rgba(148,163,184,0.8); margin: 0; line-height: 1.6; }

.alert { display: flex; align-items: center; gap: 0.5rem; padding: 0.875rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-bottom: 1.25rem; }
.alert svg { width: 18px; height: 18px; flex-shrink: 0; }
.alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }

.form-body { display: flex; flex-direction: column; gap: 1.25rem; }
.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8125rem; font-weight: 500; color: #94a3b8; }

.input-wrapper {
    position: relative; border-radius: 0.75rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
    display: flex; align-items: center;
}
.input-wrapper:focus-within { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.2); }
.input-wrapper.is-invalid { border-color: #f87171; }
.input-icon { width: 18px; height: 18px; margin-left: 0.875rem; color: #64748b; flex-shrink: 0; }
.field-input {
    flex: 1; background: transparent; border: none; outline: none;
    padding: 0.75rem; font-size: 0.9375rem; color: #f1f5f9; font-family: 'Inter', sans-serif;
}
.field-input::placeholder { color: rgba(100,116,139,0.6); }
.field-error { font-size: 0.75rem; color: #f87171; }

.btn-primary {
    width: 100%; padding: 0.875rem;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    color: white; font-size: 0.9375rem; font-weight: 600;
    border: none; border-radius: 0.75rem; cursor: pointer;
    transition: opacity 0.2s, transform 0.1s, box-shadow 0.2s;
    box-shadow: 0 4px 16px rgba(245,158,11,0.35); font-family: 'Inter', sans-serif;
}
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(245,158,11,0.5); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.loading-content { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 18px; height: 18px; animation: spin 0.8s linear infinite; }

.back-link { text-align: center; }
.link { font-size: 0.875rem; color: #94a3b8; text-decoration: none; transition: color 0.2s; }
.link:hover { color: #f1f5f9; }
</style>
