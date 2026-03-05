<script setup>
import { ref, computed, watch } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

const showPassword   = ref(false);
const emailTouched   = ref(false);

// Email format validation
const emailValid = computed(() => {
    if (!emailTouched.value || !form.email) return null;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email);
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Sign In — SECURE System" />

        <div class="auth-form">
            <!-- Header -->
            <div class="form-header">
                <div class="form-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                </div>
                <h2 class="form-title">Welcome Back</h2>
                <p class="form-subtitle">Sign in to your SECURE account</p>
            </div>

            <!-- Status message -->
            <div v-if="status" class="alert alert-success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="form-body" novalidate>
                <!-- Email -->
                <div class="field-group">
                    <label for="email" class="field-label">Email Address</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.email || emailValid === false, 'is-valid': emailValid === true }">
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
                            autocomplete="username"
                            required
                            autofocus
                            @blur="emailTouched = true"
                        />
                        <span class="input-status">
                            <svg v-if="emailValid === true" viewBox="0 0 20 20" fill="currentColor" class="status-valid"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            <svg v-if="emailValid === false" viewBox="0 0 20 20" fill="currentColor" class="status-invalid"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                        </span>
                    </div>
                    <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                    <span v-else-if="emailValid === false" class="field-error">Please enter a valid email address.</span>
                </div>

                <!-- Password -->
                <div class="field-group">
                    <label for="password" class="field-label">Password</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.password }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                        </svg>
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="field-input"
                            placeholder="Your password"
                            autocomplete="current-password"
                            required
                        />
                        <button type="button" class="toggle-password" @click="showPassword = !showPassword" tabindex="-1">
                            <svg v-if="!showPassword" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                        </button>
                    </div>
                    <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>
                </div>

                <!-- Remember me + Forgot password -->
                <div class="form-row">
                    <label class="checkbox-label">
                        <input id="remember" type="checkbox" v-model="form.remember" class="checkbox-input" />
                        <span class="checkbox-custom"></span>
                        Remember me
                    </label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="forgot-link">
                        Forgot password?
                    </Link>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.processing"
                    :class="{ 'is-loading': form.processing }"
                >
                    <span v-if="!form.processing">Sign In</span>
                    <span v-else class="loading-content">
                        <svg class="spinner" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/>
                        </svg>
                        Signing In...
                    </span>
                </button>
            </form>
        </div>
    </GuestLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

* { box-sizing: border-box; }

.auth-form {
    font-family: 'Inter', sans-serif;
    color: #e2e8f0;
}

.form-header { text-align: center; margin-bottom: 2rem; }

.form-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 24px rgba(99,102,241,0.4);
}
.form-icon svg { width: 28px; height: 28px; color: white; }

.form-title { font-size: 1.75rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.5rem; }
.form-subtitle { font-size: 0.875rem; color: rgba(148,163,184,0.8); margin: 0; }

/* Alerts */
.alert {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.875rem 1rem; border-radius: 0.75rem;
    font-size: 0.875rem; margin-bottom: 1.25rem;
}
.alert svg { width: 18px; height: 18px; flex-shrink: 0; }
.alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }

/* Form body */
.form-body { display: flex; flex-direction: column; gap: 1.25rem; }

/* Fields */
.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8125rem; font-weight: 500; color: #94a3b8; letter-spacing: 0.025em; }

.input-wrapper {
    position: relative;
    border-radius: 0.75rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
    display: flex; align-items: center;
}
.input-wrapper:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
.input-wrapper.is-invalid { border-color: #f87171; box-shadow: 0 0 0 3px rgba(248,113,113,0.15); }
.input-wrapper.is-valid { border-color: #34d399; }

.input-icon { width: 18px; height: 18px; margin-left: 0.875rem; color: #64748b; flex-shrink: 0; }

.field-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    padding: 0.75rem 0.75rem;
    font-size: 0.9375rem;
    color: #f1f5f9;
    font-family: 'Inter', sans-serif;
}
.field-input::placeholder { color: rgba(100,116,139,0.6); }

.input-status { display: flex; align-items: center; padding-right: 0.75rem; }
.status-valid { width: 18px; height: 18px; color: #34d399; }
.status-invalid { width: 18px; height: 18px; color: #f87171; }

.toggle-password {
    background: none; border: none; cursor: pointer; padding: 0.5rem 0.75rem;
    color: #64748b; display: flex; align-items: center;
    transition: color 0.2s;
}
.toggle-password:hover { color: #94a3b8; }
.toggle-password svg { width: 18px; height: 18px; }

.field-error { font-size: 0.75rem; color: #f87171; }

/* Row */
.form-row { display: flex; align-items: center; justify-content: space-between; }

.checkbox-label {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.875rem; color: #94a3b8; cursor: pointer; user-select: none;
}
.checkbox-input { display: none; }
.checkbox-custom {
    width: 18px; height: 18px;
    border: 2px solid rgba(255,255,255,0.15);
    border-radius: 5px;
    background: rgba(255,255,255,0.04);
    transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.checkbox-input:checked + .checkbox-custom {
    background: #6366f1;
    border-color: #6366f1;
}
.checkbox-input:checked + .checkbox-custom::after {
    content: '';
    display: block;
    width: 5px; height: 9px;
    border: 2px solid white;
    border-top: none; border-left: none;
    transform: rotate(45deg) translateY(-1px);
}

.forgot-link {
    font-size: 0.8125rem; color: #a5b4fc;
    text-decoration: none; font-weight: 500;
    transition: color 0.2s;
}
.forgot-link:hover { color: #c7d2fe; }

/* Submit button */
.btn-primary {
    width: 100%;
    padding: 0.875rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    font-size: 0.9375rem;
    font-weight: 600;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s, box-shadow 0.2s;
    box-shadow: 0 4px 16px rgba(99,102,241,0.4);
    font-family: 'Inter', sans-serif;
    margin-top: 0.25rem;
}
.btn-primary:hover:not(:disabled) {
    box-shadow: 0 6px 24px rgba(99,102,241,0.55);
    transform: translateY(-1px);
}
.btn-primary:active:not(:disabled) { transform: translateY(0); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.loading-content { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 20px; height: 20px; animation: spin 0.8s linear infinite; }
</style>
