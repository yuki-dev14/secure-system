<script setup>
import { ref, computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: String,
    token: String,
});

const showPassword        = ref(false);
const showPasswordConfirm = ref(false);

const form = useForm({
    token:                 props.token,
    email:                 props.email,
    password:              '',
    password_confirmation: '',
});

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
    const map = [
        { text: 'Very Weak', color: '#ef4444' },
        { text: 'Very Weak', color: '#ef4444' },
        { text: 'Weak',      color: '#f97316' },
        { text: 'Fair',      color: '#eab308' },
        { text: 'Strong',    color: '#22c55e' },
        { text: 'Very Strong', color: '#10b981' },
    ];
    return map[strengthScore.value];
});

const passwordMatch = computed(() => {
    if (!form.password_confirmation) return null;
    return form.password === form.password_confirmation;
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password — SECURE System" />

        <div class="auth-form">
            <div class="form-header">
                <div class="form-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                    </svg>
                </div>
                <h2 class="form-title">Reset Password</h2>
                <p class="form-subtitle">Create a new secure password for your account.</p>
            </div>

            <form @submit.prevent="submit" class="form-body">
                <!-- Email (read-only) -->
                <div class="field-group">
                    <label class="field-label">Account Email</label>
                    <div class="input-wrapper readonly">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z"/><path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z"/></svg>
                        <input v-model="form.email" type="email" class="field-input" readonly/>
                    </div>
                    <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                </div>

                <!-- New Password -->
                <div class="field-group">
                    <label for="password" class="field-label">New Password</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': form.errors.password }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="field-input" placeholder="New password" autocomplete="new-password" required autofocus/>
                        <button type="button" class="toggle-password" @click="showPassword = !showPassword" tabindex="-1">
                            <svg v-if="!showPassword" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                        </button>
                    </div>
                    <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>

                    <div v-if="form.password" class="strength-meter">
                        <div class="strength-bars">
                            <div v-for="i in 5" :key="i" class="strength-bar" :class="{ active: strengthScore >= i }" :style="{ background: strengthScore >= i ? strengthLabel.color : '' }"></div>
                        </div>
                        <span class="strength-label" :style="{ color: strengthLabel.color }">{{ strengthLabel.text }}</span>
                    </div>

                    <div v-if="form.password" class="password-requirements">
                        <div class="req-item" :class="{ met: strengthChecks.length }"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>8+ characters</div>
                        <div class="req-item" :class="{ met: strengthChecks.uppercase }"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>Uppercase</div>
                        <div class="req-item" :class="{ met: strengthChecks.lowercase }"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>Lowercase</div>
                        <div class="req-item" :class="{ met: strengthChecks.number }"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>Number</div>
                        <div class="req-item" :class="{ met: strengthChecks.special }"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>Special char</div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="field-group">
                    <label for="password_confirmation" class="field-label">Confirm New Password</label>
                    <div class="input-wrapper" :class="{ 'is-invalid': passwordMatch === false, 'is-valid': passwordMatch === true }">
                        <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        <input id="password_confirmation" v-model="form.password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" class="field-input" placeholder="Confirm new password" autocomplete="new-password" required/>
                        <button type="button" class="toggle-password" @click="showPasswordConfirm = !showPasswordConfirm" tabindex="-1">
                            <svg v-if="!showPasswordConfirm" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                        </button>
                    </div>
                    <span v-if="passwordMatch === false" class="field-error">Passwords do not match.</span>
                </div>

                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="!form.processing">Reset Password</span>
                    <span v-else class="loading-content">
                        <svg class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                        Resetting...
                    </span>
                </button>
            </form>
        </div>
    </GuestLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.auth-form { font-family: 'Inter', sans-serif; color: #e2e8f0; }
.form-header { text-align: center; margin-bottom: 1.75rem; }
.form-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem; box-shadow: 0 8px 24px rgba(16,185,129,0.35);
}
.form-icon svg { width: 28px; height: 28px; color: white; }
.form-title { font-size: 1.75rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.5rem; }
.form-subtitle { font-size: 0.8125rem; color: rgba(148,163,184,0.8); margin: 0; }
.form-body { display: flex; flex-direction: column; gap: 1.1rem; }
.field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.field-label { font-size: 0.8rem; font-weight: 500; color: #94a3b8; }
.input-wrapper {
    position: relative; border-radius: 0.75rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
    display: flex; align-items: center;
}
.input-wrapper.readonly { opacity: 0.6; }
.input-wrapper:focus-within:not(.readonly) { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.2); }
.input-wrapper.is-invalid { border-color: #f87171; }
.input-wrapper.is-valid { border-color: #34d399; }
.input-icon { width: 16px; height: 16px; margin-left: 0.875rem; color: #64748b; flex-shrink: 0; }
.field-input {
    flex: 1; background: transparent; border: none; outline: none;
    padding: 0.7rem 0.75rem; font-size: 0.9rem; color: #f1f5f9; font-family: 'Inter', sans-serif;
}
.field-input::placeholder { color: rgba(100,116,139,0.6); }
.field-error { font-size: 0.75rem; color: #f87171; }
.toggle-password { background: none; border: none; cursor: pointer; padding: 0.5rem 0.75rem; color: #64748b; display: flex; align-items: center; transition: color 0.2s; }
.toggle-password:hover { color: #94a3b8; }
.toggle-password svg { width: 16px; height: 16px; }

.strength-meter { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.25rem; }
.strength-bars { display: flex; gap: 0.25rem; flex: 1; }
.strength-bar { flex: 1; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.1); transition: background 0.3s; }
.strength-label { font-size: 0.7rem; font-weight: 600; white-space: nowrap; }

.password-requirements { display: grid; grid-template-columns: 1fr 1fr; gap: 0.3rem; padding: 0.75rem; background: rgba(255,255,255,0.03); border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.06); margin-top: 0.25rem; }
.req-item { display: flex; align-items: center; gap: 0.375rem; font-size: 0.7rem; color: rgba(148,163,184,0.6); }
.req-item svg { width: 12px; height: 12px; flex-shrink: 0; opacity: 0.4; }
.req-item.met { color: #6ee7b7; }
.req-item.met svg { opacity: 1; color: #34d399; }

.btn-primary {
    width: 100%; padding: 0.875rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white; font-size: 0.9375rem; font-weight: 600;
    border: none; border-radius: 0.75rem; cursor: pointer;
    transition: opacity 0.2s, transform 0.1s, box-shadow 0.2s;
    box-shadow: 0 4px 16px rgba(16,185,129,0.35); font-family: 'Inter', sans-serif; margin-top: 0.25rem;
}
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(16,185,129,0.5); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.loading-content { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 18px; height: 18px; animation: spin 0.8s linear infinite; }
</style>
