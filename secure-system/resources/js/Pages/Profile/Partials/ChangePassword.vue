<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const showCurrent  = ref(false);
const showNew      = ref(false);
const showConfirm  = ref(false);

const form = useForm({
    current_password: '',
    password:         '',
    password_confirmation: '',
});

// Strength checks
const checks = computed(() => ({
    length:    form.password.length >= 8,
    uppercase: /[A-Z]/.test(form.password),
    lowercase: /[a-z]/.test(form.password),
    number:    /[0-9]/.test(form.password),
    special:   /[^A-Za-z0-9]/.test(form.password),
}));

const score = computed(() => Object.values(checks.value).filter(Boolean).length);

const strengthInfo = computed(() => {
    const map = [
        { label: '', color: 'transparent' },
        { label: 'Very Weak',   color: '#ef4444' },
        { label: 'Weak',        color: '#f97316' },
        { label: 'Fair',        color: '#eab308' },
        { label: 'Strong',      color: '#22c55e' },
        { label: 'Very Strong', color: '#10b981' },
    ];
    return map[score.value] ?? map[0];
});

const passwordMatch = computed(() => {
    if (!form.password_confirmation) return null;
    return form.password === form.password_confirmation;
});

const allChecksMet = computed(() => score.value === 5);

const submit = () => {
    form.put(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => form.reset('current_password'),
    });
};
</script>

<template>
    <div class="panel">
        <!-- Header -->
        <div class="panel-header">
            <div class="panel-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                </svg>
            </div>
            <div>
                <h2 class="panel-title">Change Password</h2>
                <p class="panel-subtitle">Use a strong, unique password you haven't used before.</p>
            </div>
        </div>

        <!-- Security tips -->
        <div class="security-tip">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.661 2.237a.531.531 0 01.678 0 11.947 11.947 0 007.078 2.749.5.5 0 01.479.425c.069.52.104 1.05.104 1.589 0 5.162-3.26 9.563-7.834 11.256a.48.48 0 01-.332 0C5.26 16.563 2 12.162 2 7c0-.538.035-1.069.104-1.589a.5.5 0 01.48-.425 11.947 11.947 0 007.077-2.749z" clip-rule="evenodd"/></svg>
            <span>Changing your password will sign you out of all other devices.</span>
        </div>

        <form @submit.prevent="submit" class="panel-form">
            <!-- Current Password -->
            <div class="field-group">
                <label for="current_password" class="field-label">Current Password</label>
                <div class="input-wrapper" :class="{ 'is-invalid': form.errors.current_password }">
                    <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                    <input id="current_password" v-model="form.current_password" :type="showCurrent ? 'text' : 'password'" class="field-input" placeholder="Your current password" autocomplete="current-password" required/>
                    <button type="button" class="eye-btn" @click="showCurrent = !showCurrent" tabindex="-1">
                        <svg v-if="!showCurrent" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                    </button>
                </div>
                <span v-if="form.errors.current_password" class="field-error">{{ form.errors.current_password }}</span>
            </div>

            <div class="divider"></div>

            <!-- New Password -->
            <div class="field-group">
                <label for="password" class="field-label">New Password</label>
                <div class="input-wrapper" :class="{ 'is-invalid': form.errors.password }">
                    <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                    <input id="password" v-model="form.password" :type="showNew ? 'text' : 'password'" class="field-input" placeholder="New strong password" autocomplete="new-password" required/>
                    <button type="button" class="eye-btn" @click="showNew = !showNew" tabindex="-1">
                        <svg v-if="!showNew" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                    </button>
                </div>
                <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>

                <!-- Strength meter -->
                <div v-if="form.password" class="strength-meter">
                    <div class="strength-bars">
                        <div v-for="i in 5" :key="i" class="bar" :class="{ active: score >= i }" :style="score >= i ? { background: strengthInfo.color } : {}"></div>
                    </div>
                    <span class="strength-label" :style="{ color: strengthInfo.color }">{{ strengthInfo.label }}</span>
                </div>

                <!-- Requirements checklist -->
                <div class="requirements">
                    <div class="req" :class="{ met: checks.length }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        At least 8 characters
                    </div>
                    <div class="req" :class="{ met: checks.uppercase }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        Uppercase letter (A-Z)
                    </div>
                    <div class="req" :class="{ met: checks.lowercase }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        Lowercase letter (a-z)
                    </div>
                    <div class="req" :class="{ met: checks.number }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        Number (0-9)
                    </div>
                    <div class="req" :class="{ met: checks.special }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        Special character (!@#$...)
                    </div>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="field-group">
                <label for="password_confirmation" class="field-label">Confirm New Password</label>
                <div class="input-wrapper" :class="{ 'is-invalid': passwordMatch === false, 'is-valid': passwordMatch === true }">
                    <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                    <input id="password_confirmation" v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" class="field-input" placeholder="Repeat new password" autocomplete="new-password" required/>
                    <button type="button" class="eye-btn" @click="showConfirm = !showConfirm" tabindex="-1">
                        <svg v-if="!showConfirm" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.523a10.003 10.003 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                    </button>
                </div>
                <span v-if="passwordMatch === false" class="field-error">Passwords do not match.</span>
                <span v-else-if="passwordMatch === true" class="field-success">✓ Passwords match</span>
            </div>

            <!-- Footer -->
            <div class="form-footer">
                <button type="submit" class="btn-save" :disabled="form.processing || !allChecksMet || passwordMatch !== true">
                    <span v-if="!form.processing">Update Password</span>
                    <span v-else class="loading-content">
                        <svg class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                        Updating...
                    </span>
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.panel { font-family: 'Inter', sans-serif; color: #e2e8f0; }

.panel-header { display: flex; align-items: flex-start; gap: 1rem; padding: 1.5rem 1.5rem 0; }
.panel-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.panel-icon svg { width: 22px; height: 22px; color: #34d399; }
.panel-title { font-size: 1.0625rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.25rem; }
.panel-subtitle { font-size: 0.8125rem; color: #64748b; margin: 0; }

.security-tip {
    display: flex; align-items: center; gap: 0.5rem;
    margin: 1.25rem 1.5rem 0; padding: 0.75rem 1rem;
    border-radius: 0.75rem; font-size: 0.8125rem;
    background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); color: #a5b4fc;
}
.security-tip svg { width: 16px; height: 16px; flex-shrink: 0; }

.panel-form { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }

.field-group { display: flex; flex-direction: column; gap: 0.4rem; }
.field-label { font-size: 0.8rem; font-weight: 500; color: #94a3b8; }

.input-wrapper {
    display: flex; align-items: center; border-radius: 0.75rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.input-wrapper:focus-within { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.15); }
.input-wrapper.is-invalid { border-color: #f87171; }
.input-wrapper.is-valid   { border-color: #34d399; }
.input-icon { width: 16px; height: 16px; margin-left: 0.875rem; color: #64748b; flex-shrink: 0; }
.field-input { flex: 1; background: transparent; border: none; outline: none; padding: 0.75rem 0.75rem; font-size: 0.9rem; color: #f1f5f9; font-family: 'Inter', sans-serif; }
.field-input::placeholder { color: rgba(100,116,139,0.5); }
.field-error   { font-size: 0.75rem; color: #f87171; }
.field-success { font-size: 0.75rem; color: #34d399; }

.eye-btn { background: none; border: none; cursor: pointer; padding: 0.5rem 0.75rem; color: #64748b; display: flex; align-items: center; transition: color 0.2s; }
.eye-btn:hover { color: #94a3b8; }
.eye-btn svg { width: 16px; height: 16px; }

.divider { height: 1px; background: rgba(255,255,255,0.06); margin: 0 0 0.25rem; }

/* Strength meter */
.strength-meter { display: flex; align-items: center; gap: 0.75rem; }
.strength-bars { display: flex; gap: 0.25rem; flex: 1; }
.bar { flex: 1; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.1); transition: background 0.3s; }
.strength-label { font-size: 0.7rem; font-weight: 600; white-space: nowrap; }

/* Requirements */
.requirements {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem 0.75rem;
    padding: 0.875rem; background: rgba(255,255,255,0.02);
    border-radius: 0.625rem; border: 1px solid rgba(255,255,255,0.05);
}
.req {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.7125rem; color: rgba(148,163,184,0.5);
    transition: color 0.2s;
}
.req svg { width: 13px; height: 13px; flex-shrink: 0; opacity: 0.3; transition: opacity 0.2s, color 0.2s; }
.req.met { color: #6ee7b7; }
.req.met svg { opacity: 1; color: #34d399; }

/* Footer */
.form-footer { display: flex; justify-content: flex-end; padding-top: 0.5rem; }
.btn-save {
    padding: 0.75rem 1.75rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white; font-size: 0.9rem; font-weight: 600;
    border: none; border-radius: 0.75rem; cursor: pointer;
    transition: all 0.2s; font-family: 'Inter', sans-serif;
    box-shadow: 0 4px 14px rgba(16,185,129,0.3);
}
.btn-save:hover:not(:disabled) { box-shadow: 0 6px 20px rgba(16,185,129,0.45); transform: translateY(-1px); }
.btn-save:disabled { opacity: 0.45; cursor: not-allowed; transform: none; box-shadow: none; }
.loading-content { display: flex; align-items: center; gap: 0.4rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
</style>
