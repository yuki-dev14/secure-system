<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    familyMembers: { type: Array, required: true },
    beneficiaryId: { type: Number, required: true },
});

const emit = defineEmits(['submitted']);

function currentPeriod() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}
function todayDate() { return new Date().toISOString().split('T')[0]; }

/* ── Form state ─────────────────────────────────────────── */
const form = ref({
    beneficiary_id:    props.beneficiaryId,
    compliance_period: currentPeriod(),
    fds_session_date:  todayDate(),
    fds_topic:         '',
    fds_location:      '',
    attendees:         [],
});

const loading      = ref(false);
const success      = ref('');
const errors       = ref({});
const sessionCount = ref(null);  // fetched from backend

/* ── Computed ────────────────────────────────────────────── */
const familyHead = computed(() =>
    props.familyMembers.find(m => m.relationship_to_head?.toLowerCase() === 'head')
    ?? props.familyMembers[0]
);

const headAttending = computed(() =>
    familyHead.value ? form.value.attendees.includes(familyHead.value.id) : false
);

const sessionCountAfter = computed(() =>
    sessionCount.value !== null ? sessionCount.value + 1 : '?'
);

const willMeetMinimum = computed(() =>
    sessionCount.value !== null ? (sessionCount.value + 1) >= 2 : false
);

/* ── Fetch current session count ─────────────────────────── */
async function fetchSessionCount() {
    sessionCount.value = null;
    try {
        const res = await axios.get(`/compliance/${props.beneficiaryId}`, {
            params: { type: 'fds', period: form.value.compliance_period },
        });
        // Count distinct FDS records for this period
        const records = res.data?.grouped?.filter(g => g.compliance_type === 'fds') ?? [];
        sessionCount.value = records.length > 0
            ? (records[0]?.records?.filter(r => r.fds_attendance)?.length ?? 0)
            : 0;
    } catch {
        sessionCount.value = 0;
    }
}

watch(() => form.value.compliance_period, fetchSessionCount, { immediate: true });

/* ── Toggle attendee ─────────────────────────────────────── */
function toggleAttendee(memberId) {
    const idx = form.value.attendees.indexOf(memberId);
    if (idx === -1) form.value.attendees.push(memberId);
    else form.value.attendees.splice(idx, 1);
}

function selectAll() {
    form.value.attendees = props.familyMembers.map(m => m.id);
}

function clearAll() {
    form.value.attendees = [];
}

/* ── Submit ──────────────────────────────────────────────── */
async function submit() {
    loading.value = true;
    errors.value  = {};
    success.value = '';

    try {
        const res = await axios.post('/compliance/fds', form.value);
        success.value = res.data.message ?? 'FDS attendance recorded!';
        emit('submitted', res.data);
        form.value.fds_topic    = '';
        form.value.fds_location = '';
        await fetchSessionCount();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            errors.value = { _general: [e.response?.data?.message ?? 'Server error.'] };
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="fds-shell">
        <div class="fds-header">
            <div class="fds-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="fds-title">FDS Attendance</h3>
                <p class="fds-subtitle">Record Family Development Session attendance (min. 2 sessions/month)</p>
            </div>
        </div>

        <Transition name="fade">
            <div v-if="success" class="alert-success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                {{ success }}
            </div>
        </Transition>
        <div v-if="errors._general" class="alert-error">{{ errors._general[0] }}</div>

        <!-- Sessions meter -->
        <div class="sessions-meter">
            <div class="sm-top">
                <span class="sm-label">Sessions this period ({{ form.compliance_period }})</span>
                <span class="sm-count" :class="willMeetMinimum ? 'clr-green' : 'clr-orange'">
                    {{ sessionCountAfter }} / 2
                </span>
            </div>
            <div class="sm-bar-bg">
                <div class="sm-bar-fill"
                     :style="{
                       width: Math.min(((sessionCount ?? 0) + 1) / 2 * 100, 100) + '%',
                       background: willMeetMinimum ? '#10b981' : '#f59e0b'
                     }">
                </div>
            </div>
            <p class="sm-note" :class="willMeetMinimum ? 'clr-green' : 'clr-orange'">
                {{ willMeetMinimum ? '✓ Will meet minimum requirement' : '✗ Submit one more session to meet minimum' }}
            </p>
        </div>

        <form @submit.prevent="submit" class="fds-form">
            <!-- Compliance Period -->
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="fds-period">Compliance Period <span class="req">*</span></label>
                    <input id="fds-period" type="month" v-model="form.compliance_period" class="field-input" required/>
                    <p v-if="errors.compliance_period" class="field-error">{{ errors.compliance_period[0] }}</p>
                </div>
                <div class="field-group">
                    <label class="field-label" for="fds-date">Session Date <span class="req">*</span></label>
                    <input id="fds-date" type="date" v-model="form.fds_session_date" class="field-input" required/>
                    <p v-if="errors.fds_session_date" class="field-error">{{ errors.fds_session_date[0] }}</p>
                </div>
            </div>

            <!-- Topic & Location -->
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="fds-topic">Session Topic</label>
                    <input id="fds-topic" type="text" v-model="form.fds_topic"
                           placeholder="e.g. Child Nutrition" class="field-input" maxlength="255"/>
                </div>
                <div class="field-group">
                    <label class="field-label" for="fds-loc">Session Location</label>
                    <input id="fds-loc" type="text" v-model="form.fds_location"
                           placeholder="e.g. Barangay Hall" class="field-input" maxlength="255"/>
                </div>
            </div>

            <!-- Attendees -->
            <div class="field-group">
                <div class="attendees-header">
                    <label class="field-label">Attendees <span class="req">*</span>
                        <span class="att-count">({{ form.attendees.length }} selected)</span>
                    </label>
                    <div class="att-actions">
                        <button type="button" class="btn-sm" @click="selectAll">All</button>
                        <button type="button" class="btn-sm btn-sm-ghost" @click="clearAll">Clear</button>
                    </div>
                </div>

                <!-- Head warning -->
                <div v-if="familyHead && !headAttending" class="head-warning">
                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;flex-shrink:0;">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                    Family head ({{ familyHead.full_name }}) must attend — please include them.
                </div>

                <div class="attendees-grid">
                    <label v-for="m in familyMembers" :key="m.id"
                           class="attendee-item"
                           :class="{
                               'att-selected': form.attendees.includes(m.id),
                               'att-head': familyHead?.id === m.id
                           }">
                        <input type="checkbox"
                               :value="m.id"
                               :checked="form.attendees.includes(m.id)"
                               @change="toggleAttendee(m.id)"/>
                        <div class="att-check">
                            <svg v-if="form.attendees.includes(m.id)" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="att-info">
                            <span class="att-name">{{ m.full_name }}</span>
                            <span class="att-rel">
                                {{ m.relationship_to_head }}
                                <span v-if="familyHead?.id === m.id" class="head-badge">HEAD</span>
                            </span>
                        </div>
                    </label>
                </div>
                <p v-if="errors.attendees" class="field-error">{{ errors.attendees[0] }}</p>
            </div>

            <button type="submit" class="btn-submit" :disabled="loading || form.attendees.length === 0" id="fds-submit-btn">
                <span v-if="loading" class="spinner"></span>
                <svg v-else viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                Record FDS Attendance
            </button>
        </form>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }
.fds-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1.25rem;
    padding: 1.75rem;
    display: flex; flex-direction: column; gap: 1.25rem;
}
.fds-header { display: flex; align-items: flex-start; gap: 0.875rem; }
.fds-icon {
    width: 44px; height: 44px; border-radius: 0.75rem;
    background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.25);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    color: #fcd34d;
}
.fds-icon svg { width: 22px; height: 22px; }
.fds-title   { font-size: 1.0625rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.fds-subtitle { font-size: 0.8rem; color: #64748b; margin: 0; }

.alert-success {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25);
    border-radius: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; color: #6ee7b7;
}
.alert-success svg { width: 16px; height: 16px; flex-shrink: 0; }
.alert-error {
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);
    border-radius: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; color: #fca5a5;
}

/* Sessions meter */
.sessions-meter {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.875rem; padding: 1rem;
    display: flex; flex-direction: column; gap: 0.5rem;
}
.sm-top { display: flex; justify-content: space-between; align-items: center; }
.sm-label { font-size: 0.8125rem; color: #64748b; font-weight: 500; }
.sm-count { font-size: 1rem; font-weight: 800; }
.sm-bar-bg { height: 8px; background: rgba(255,255,255,0.07); border-radius: 999px; overflow: hidden; }
.sm-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; }
.sm-note { font-size: 0.78rem; font-weight: 600; margin: 0; }
.clr-green  { color: #6ee7b7; }
.clr-orange { color: #fcd34d; }

/* Form */
.fds-form { display: flex; flex-direction: column; gap: 1.25rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8125rem; font-weight: 600; color: #94a3b8; }
.req { color: #f87171; }
.field-error { font-size: 0.75rem; color: #f87171; margin: 0; }
.field-input {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; padding: 0.65rem 0.875rem;
    color: #f1f5f9; font-size: 0.875rem; font-family: 'Inter', sans-serif;
    width: 100%; outline: none; transition: border-color 0.2s; appearance: none;
}
.field-input:focus { border-color: rgba(99,102,241,0.5); }

/* Attendees */
.attendees-header { display: flex; justify-content: space-between; align-items: center; }
.att-count { font-weight: 400; color: #475569; margin-left: 0.25rem; }
.att-actions { display: flex; gap: 0.375rem; }
.btn-sm {
    padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem;
    background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.3);
    color: #a5b4fc; cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.15s;
}
.btn-sm:hover { background: rgba(99,102,241,0.35); }
.btn-sm-ghost {
    background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); color: #64748b;
}
.btn-sm-ghost:hover { background: rgba(255,255,255,0.08); color: #94a3b8; }

.head-warning {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2);
    border-radius: 0.5rem; padding: 0.5rem 0.75rem;
    font-size: 0.78rem; color: #fcd34d;
}
.attendees-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.5rem;
}
.attendee-item {
    display: flex; align-items: center; gap: 0.625rem;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.625rem; padding: 0.625rem 0.875rem;
    cursor: pointer; transition: all 0.15s;
}
.attendee-item input { display: none; }
.attendee-item:hover { background: rgba(255,255,255,0.06); }
.att-selected { background: rgba(99,102,241,0.12) !important; border-color: rgba(99,102,241,0.3) !important; }
.att-head { border-color: rgba(245,158,11,0.3) !important; }

.att-check {
    width: 18px; height: 18px; border-radius: 0.3rem;
    border: 1.5px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.15s;
    background: rgba(255,255,255,0.04);
}
.att-selected .att-check {
    background: #6366f1; border-color: #6366f1;
}
.att-check svg { width: 12px; height: 12px; color: white; }
.att-info { display: flex; flex-direction: column; gap: 0.1rem; overflow: hidden; }
.att-name { font-size: 0.8125rem; font-weight: 600; color: #f1f5f9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.att-rel  { font-size: 0.72rem; color: #64748b; display: flex; align-items: center; gap: 0.25rem; }
.head-badge {
    background: rgba(245,158,11,0.2); color: #fcd34d;
    border: 1px solid rgba(245,158,11,0.3);
    border-radius: 999px; padding: 0 0.3rem; font-size: 0.65rem; font-weight: 700;
}

.btn-submit {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; border: none; border-radius: 0.75rem;
    padding: 0.75rem 1.5rem; font-size: 0.9375rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: opacity 0.2s, transform 0.15s;
}
.btn-submit:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-submit svg { width: 18px; height: 18px; }
.spinner {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
