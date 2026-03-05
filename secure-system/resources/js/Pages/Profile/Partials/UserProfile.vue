<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const form = useForm({
    name:            user.value?.name  ?? '',
    email:           user.value?.email ?? '',
    office_location: user.value?.office_location ?? '',
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="panel">
        <!-- Section header -->
        <div class="panel-header">
            <div class="panel-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
            </div>
            <div>
                <h2 class="panel-title">Profile Information</h2>
                <p class="panel-subtitle">Update your name, email address, and office location.</p>
            </div>
        </div>

        <!-- Email verification banner -->
        <div v-if="mustVerifyEmail && !user?.email_verified_at" class="verify-banner">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            Your email is unverified. Check your inbox for a verification link.
        </div>
        <div v-if="status === 'verification-link-sent'" class="verify-success">
            A new verification link has been sent to your email.
        </div>

        <form @submit.prevent="submit" class="panel-form">
            <!-- Name -->
            <div class="field-group">
                <label for="name" class="field-label">Full Name</label>
                <div class="input-wrapper" :class="{ 'is-invalid': form.errors.name }">
                    <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                    <input id="name" v-model="form.name" type="text" class="field-input" placeholder="Your full name" required autocomplete="name"/>
                </div>
                <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
            </div>

            <!-- Email -->
            <div class="field-group">
                <label for="email" class="field-label">Email Address</label>
                <div class="input-wrapper" :class="{ 'is-invalid': form.errors.email }">
                    <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z"/><path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z"/></svg>
                    <input id="email" v-model="form.email" type="email" class="field-input" placeholder="you@example.com" required autocomplete="username"/>
                </div>
                <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                <span class="field-hint">Changing your email will require re-verification.</span>
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

            <!-- Read-only fields -->
            <div class="readonly-row">
                <div class="readonly-field">
                    <span class="field-label">System Role</span>
                    <span class="role-badge" :class="{
                        'role-admin': user?.role === 'Administrator',
                        'role-officer': user?.role === 'Field Officer',
                        'role-verifier': user?.role === 'Compliance Verifier',
                    }">{{ user?.role }}</span>
                </div>
                <div class="readonly-field">
                    <span class="field-label">Account Status</span>
                    <span class="status-badge" :class="user?.status === 'active' ? 'status-active' : 'status-inactive'">
                        {{ user?.status }}
                    </span>
                </div>
                <div class="readonly-field">
                    <span class="field-label">Last Login</span>
                    <span class="readonly-value">
                        {{ user?.last_login_at
                            ? new Date(user.last_login_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
                            : '—' }}
                    </span>
                </div>
            </div>

            <!-- Save -->
            <div class="form-footer">
                <span class="dirty-indicator" v-if="form.isDirty">You have unsaved changes</span>
                <button type="submit" class="btn-save" :disabled="form.processing || !form.isDirty" :class="{ 'is-loading': form.processing }">
                    <span v-if="!form.processing">Save Changes</span>
                    <span v-else class="loading-content">
                        <svg class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                        Saving...
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
.panel-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.panel-icon svg { width: 22px; height: 22px; color: #a5b4fc; }
.panel-title { font-size: 1.0625rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.25rem; }
.panel-subtitle { font-size: 0.8125rem; color: #64748b; margin: 0; }

/* Alerts */
.verify-banner, .verify-success {
    display: flex; align-items: center; gap: 0.5rem;
    margin: 1.25rem 1.5rem 0; padding: 0.75rem 1rem;
    border-radius: 0.75rem; font-size: 0.8125rem;
}
.verify-banner svg { width: 16px; height: 16px; flex-shrink: 0; }
.verify-banner { background: rgba(234,179,8,0.1); border: 1px solid rgba(234,179,8,0.25); color: #fcd34d; }
.verify-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }

/* Form */
.panel-form { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8rem; font-weight: 500; color: #94a3b8; }

.input-wrapper {
    display: flex; align-items: center;
    border-radius: 0.75rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.input-wrapper:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
.input-wrapper.is-invalid { border-color: #f87171; }
.input-icon { width: 16px; height: 16px; margin-left: 0.875rem; color: #64748b; flex-shrink: 0; }
.field-input { flex: 1; background: transparent; border: none; outline: none; padding: 0.75rem 0.75rem; font-size: 0.9rem; color: #f1f5f9; font-family: 'Inter', sans-serif; }
.field-input::placeholder { color: rgba(100,116,139,0.5); }
.field-error { font-size: 0.75rem; color: #f87171; }
.field-hint  { font-size: 0.7rem; color: #475569; }

/* Read-only row */
.readonly-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; padding: 1rem; background: rgba(255,255,255,0.02); border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.05); }
.readonly-field { display: flex; flex-direction: column; gap: 0.375rem; }
.readonly-value { font-size: 0.8125rem; color: #94a3b8; }

.role-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; align-self: flex-start; }
.role-admin    { background: rgba(99,102,241,0.2); color: #a5b4fc; }
.role-officer  { background: rgba(16,185,129,0.2); color: #6ee7b7; }
.role-verifier { background: rgba(245,158,11,0.2); color: #fcd34d; }

.status-badge { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; align-self: flex-start; }
.status-active   { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
.status-inactive { background: rgba(239,68,68,0.15);  color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

/* Footer */
.form-footer { display: flex; align-items: center; justify-content: flex-end; gap: 1rem; padding-top: 0.5rem; }
.dirty-indicator { font-size: 0.75rem; color: #f59e0b; }
.btn-save {
    padding: 0.75rem 1.75rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; font-size: 0.9rem; font-weight: 600;
    border: none; border-radius: 0.75rem; cursor: pointer;
    transition: all 0.2s; font-family: 'Inter', sans-serif;
    box-shadow: 0 4px 14px rgba(99,102,241,0.35);
}
.btn-save:hover:not(:disabled) { box-shadow: 0 6px 20px rgba(99,102,241,0.5); transform: translateY(-1px); }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
.loading-content { display: flex; align-items: center; gap: 0.4rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
</style>
