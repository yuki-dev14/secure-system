<script setup>
import { ref, computed, watch } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
    role:                  '',
    office_location:       '',
});

const showPassword        = ref(false);
const showPasswordConfirm = ref(false);
const emailTouched        = ref(false);

// Email validation
const emailValid = computed(() => {
    if (!emailTouched.value || !form.email) return null;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email);
});

// Password strength
const strengthChecks = computed(() => ({
    length:    form.password.length >= 8,
    uppercase: /[A-Z]/.test(form.password),
    lowercase: /[a-z]/.test(form.password),
    number:    /[0-9]/.test(form.password),
    special:   /[^A-Za-z0-9]/.test(form.password),
}));

const strengthScore = computed(() =>
    Object.values(strengthChecks.value).filter(Boolean).length
);

const strengthLabel = computed(() => {
    if (strengthScore.value <= 1) return { text: 'Very Weak', color: '#ef4444' };
    if (strengthScore.value === 2) return { text: 'Weak', color: '#f97316' };
    if (strengthScore.value === 3) return { text: 'Fair', color: '#eab308' };
    if (strengthScore.value === 4) return { text: 'Strong', color: '#22c55e' };
    return { text: 'Very Strong', color: '#10b981' };
});

const passwordMatch = computed(() => {
    if (!form.password_confirmation) return null;
    return form.password === form.password_confirmation;
});

const roles = ['Administrator', 'Field Officer', 'Compliance Verifier'];

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create User — SECURE System" />

        <div class="auth-form">
            <!-- Header -->
            <div class="form-header">
                <div class="form-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
                    </svg>
                </div>
                <h2 class="form-title">Create User Account</h2>
                <p class="form-subtitle">Administrator access required</p>
            </div>

            <form @submit.prevent="submit" class="form-body" novalidate>
                <!-- Name -->
                <div class="field-group">
                    <label for="name" class="field-label">Full Name</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.name }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                        <input id="name" v-model="form.name" type="text" class="field-input" placeholder="Juan dela Cruz" autocomplete="name" required autofocus/>
                    </div>
                    <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
                </div>

                <!-- Email -->
                <div class="field-group">
                    <label for="email" class="field-label">Email Address</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.email || emailValid === false, 'is-valid': emailValid === true }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z"/><path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z"/></svg>
                        <input id="email" v-model="form.email" type="email" class="field-input" placeholder="juan@dswd.gov.ph" autocomplete="username" required @blur="emailTouched = true"/>
                        <span class="input-status">
                            <svg v-if="emailValid === true" viewBox="0 0 20 20" fill="currentColor" class="status-valid"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        </span>
                    </div>
                    <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                </div>

                <!-- Role -->
                <div class="field-group">
                    <label for="role" class="field-label">System Role</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.role }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 12a5.99 5.99 0 00-4.793 2.39A6.483 6.483 0 0010 16.5a6.483 6.483 0 004.793-2.11A5.99 5.99 0 0010 12z" clip-rule="evenodd"/></svg>
                        <select id="role" v-model="form.role" class="field-input field-select" required>
                            <option value="" disabled>Select a role...</option>
                            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                        </select>
                        <svg class="select-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </div>
                    <span v-if="form.errors.role" class="field-error">{{ form.errors.role }}</span>
                </div>

                <!-- Office Location -->
                <div class="field-group">
                    <label for="office_location" class="field-label">Office Location</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.office_location }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 15.012 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 6.012 3.354 7.385a13.202 13.202 0 002.273 1.765 11.88 11.88 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd"/></svg>
                        <input id="office_location" v-model="form.office_location" type="text" class="field-input" placeholder="e.g. Quezon City Regional Office" required/>
                    </div>
                    <span v-if="form.errors.office_location" class="field-error">{{ form.errors.office_location }}</span>
                </div>

                <!-- Password -->
                <div class="field-group">
                    <label for="password" class="field-label">Password</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.password }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="field-input" placeholder="Min 8 characters" autocomplete="new-password" required/>
                        <button type="button" class="toggle-password" @click="showPassword = !showPassword" tabindex="-1">
                            <svg v-if="!showPassword" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                        </button>
                    </div>
                    <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>

                    <!-- Strength meter -->
                    <div v-if="form.password" class="strength-meter">
                        <div class="strength-bars">
                            <div v-for="i in 5" :key="i" class="strength-bar" :class="{ active: strengthScore >= i }" :style="{ background: strengthScore >= i ? strengthLabel.color : '' }"></div>
                        </div>
                        <span class="strength-label" :style="{ color: strengthLabel.color }">{{ strengthLabel.text }}</span>
                    </div>

                    <!-- Requirements -->
                    <div v-if="form.password" class="password-requirements">
                        <div class="req-item" :class="{ met: strengthChecks.length }">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            At least 8 characters
                        </div>
                        <div class="req-item" :class="{ met: strengthChecks.uppercase }">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            One uppercase letter
                        </div>
                        <div class="req-item" :class="{ met: strengthChecks.lowercase }">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            One lowercase letter
                        </div>
                        <div class="req-item" :class="{ met: strengthChecks.number }">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            One number
                        </div>
                        <div class="req-item" :class="{ met: strengthChecks.special }">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            One special character
                        </div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="field-group">
                    <label for="password_confirmation" class="field-label">Confirm Password</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.password_confirmation || passwordMatch === false, 'is-valid': passwordMatch === true }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        <input id="password_confirmation" v-model="form.password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" class="field-input" placeholder="Repeat password" autocomplete="new-password" required/>
                        <button type="button" class="toggle-password" @click="showPasswordConfirm = !showPasswordConfirm" tabindex="-1">
                            <svg v-if="!showPasswordConfirm" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                        </button>
                    </div>
                    <span v-if="passwordMatch === false" class="field-error">Passwords do not match.</span>
                    <span v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</span>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <Link :href="route('login')" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing" :class="{ 'is-loading': form.processing }">
                        <span v-if="!form.processing">Create Account</span>
                        <span v-else class="loading-content">
                            <svg class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                            Creating...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.auth-form { font-family: 'Inter', sans-serif; color: #e2e8f0; }

.form-header { text-align: center; margin-bottom: 1.75rem; }
.form-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem; box-shadow: 0 8px 24px rgba(99,102,241,0.4);
}
.form-icon svg { width: 28px; height: 28px; color: white; }
.form-title { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.4rem; }
.form-subtitle { font-size: 0.8125rem; color: rgba(148,163,184,0.8); margin: 0; }

.form-body { display: flex; flex-direction: column; gap: 1.1rem; }
.field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.field-label { font-size: 0.8rem; font-weight: 500; color: #94a3b8; letter-spacing: 0.025em; }

.input-wrapper {
    position: relative; border-radius: 0.75rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
    display: flex; align-items: center;
}
.input-wrapper:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
.input-wrapper.is-invalid { border-color: #f87171; }
.input-wrapper.is-valid   { border-color: #34d399; }

.input-icon { width: 16px; height: 16px; margin-left: 0.875rem; color: #64748b; flex-shrink: 0; }
.field-input {
    flex: 1; background: transparent; border: none; outline: none;
    padding: 0.7rem 0.75rem; font-size: 0.9rem; color: #f1f5f9; font-family: 'Inter', sans-serif;
}
.field-input::placeholder { color: rgba(100,116,139,0.6); }

.field-select {
    -webkit-appearance: none; -moz-appearance: none; appearance: none;
    cursor: pointer;
}
.field-select option { background: #1e293b; color: #f1f5f9; }
.select-arrow { width: 16px; height: 16px; margin-right: 0.75rem; color: #64748b; pointer-events: none; flex-shrink: 0; }

.input-status { display: flex; align-items: center; padding-right: 0.5rem; }
.status-valid { width: 16px; height: 16px; color: #34d399; }

.toggle-password {
    background: none; border: none; cursor: pointer;
    padding: 0.5rem 0.75rem; color: #64748b;
    display: flex; align-items: center; transition: color 0.2s;
}
.toggle-password:hover { color: #94a3b8; }
.toggle-password svg { width: 16px; height: 16px; }

.field-error { font-size: 0.75rem; color: #f87171; }

/* Strength meter */
.strength-meter {
    display: flex; align-items: center; gap: 0.75rem; margin-top: 0.25rem;
}
.strength-bars { display: flex; gap: 0.25rem; flex: 1; }
.strength-bar {
    flex: 1; height: 4px; border-radius: 2px;
    background: rgba(255,255,255,0.1);
    transition: background 0.3s;
}
.strength-label { font-size: 0.7rem; font-weight: 600; white-space: nowrap; }

/* Requirements */
.password-requirements {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0.35rem;
    padding: 0.75rem; background: rgba(255,255,255,0.03);
    border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.06);
    margin-top: 0.25rem;
}
.req-item {
    display: flex; align-items: center; gap: 0.375rem;
    font-size: 0.7rem; color: rgba(148,163,184,0.6);
    transition: color 0.2s;
}
.req-item svg { width: 13px; height: 13px; flex-shrink: 0; opacity: 0.4; transition: opacity 0.2s, color 0.2s; }
.req-item.met { color: #6ee7b7; }
.req-item.met svg { opacity: 1; color: #34d399; }

/* Actions */
.form-actions { display: flex; gap: 0.75rem; margin-top: 0.25rem; }
.btn-secondary {
    flex: 1; padding: 0.75rem;
    background: rgba(255,255,255,0.06);
    color: #94a3b8; font-size: 0.875rem; font-weight: 500;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.75rem; cursor: pointer;
    text-align: center; text-decoration: none;
    transition: background 0.2s, color 0.2s; font-family: 'Inter', sans-serif;
    display: flex; align-items: center; justify-content: center;
}
.btn-secondary:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.btn-primary {
    flex: 2; padding: 0.75rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; font-size: 0.9375rem; font-weight: 600;
    border: none; border-radius: 0.75rem; cursor: pointer;
    transition: opacity 0.2s, transform 0.1s, box-shadow 0.2s;
    box-shadow: 0 4px 16px rgba(99,102,241,0.4);
    font-family: 'Inter', sans-serif;
}
.btn-primary:hover:not(:disabled) { box-shadow: 0 6px 24px rgba(99,102,241,0.55); transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.loading-content { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 18px; height: 18px; animation: spin 0.8s linear infinite; }
</style>
