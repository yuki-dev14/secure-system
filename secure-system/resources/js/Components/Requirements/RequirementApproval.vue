<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modelValue" class="modal-overlay" @click.self="close" id="requirement-approval-modal">
        <div class="modal-panel" role="dialog" aria-modal="true">
          <!-- Header -->
          <div class="modal-header">
            <div>
              <h2 class="modal-title">{{ isApproving ? '✓ Approve Document' : '✕ Reject Document' }}</h2>
              <p class="modal-subtitle">{{ typeLabel(requirement?.requirement_type) }} — {{ requirement?.file_name }}</p>
            </div>
            <button class="modal-close" @click="close" id="close-approval-modal-btn">✕</button>
          </div>

          <div class="modal-body">
            <!-- Mode Toggle -->
            <div class="mode-toggle">
              <button
                :class="['mode-btn', { 'mode-btn--active-approve': isApproving }]"
                @click="isApproving = true"
                id="mode-approve-btn"
              >✓ Approve</button>
              <button
                :class="['mode-btn', { 'mode-btn--active-reject': !isApproving }]"
                @click="isApproving = false"
                id="mode-reject-btn"
              >✕ Reject</button>
            </div>

            <!-- Approve Panel -->
            <div v-if="isApproving" class="action-panel action-panel--approve">
              <p class="panel-text">
                You are about to <strong>approve</strong> this document. It will be marked as valid and
                included in compliance calculations.
              </p>
              <!-- Document Summary -->
              <div class="doc-summary">
                <div class="doc-summary__row"><span>Type</span><span>{{ typeLabel(requirement?.requirement_type) }}</span></div>
                <div class="doc-summary__row"><span>File</span><span>{{ requirement?.file_name }}</span></div>
                <div class="doc-summary__row"><span>Submitted</span><span>{{ requirement?.submitted_at_fmt }}</span></div>
                <div class="doc-summary__row"><span>Submitted By</span><span>{{ requirement?.submitted_by?.name }}</span></div>
              </div>

              <!-- Confirm Checkbox -->
              <label class="confirm-check" for="approve-confirm-check">
                <input type="checkbox" v-model="approveConfirmed" id="approve-confirm-check" />
                I confirm this document is authentic and meets all requirements.
              </label>
            </div>

            <!-- Reject Panel -->
            <div v-else class="action-panel action-panel--reject">
              <p class="panel-text">
                You are about to <strong>reject</strong> this document. The submitter will be notified.
              </p>

              <!-- Preset Reasons -->
              <div class="form-group">
                <label class="form-label" for="preset-reason">Quick Reason</label>
                <div class="reason-chips">
                  <button
                    v-for="reason in presetReasons"
                    :key="reason"
                    type="button"
                    :class="['reason-chip', { 'reason-chip--active': rejectionReason === reason }]"
                    @click="rejectionReason = reason; customReason = ''"
                    :id="`preset-reason-${reason.toLowerCase().replace(/\s+/g, '-')}`"
                  >{{ reason }}</button>
                </div>
              </div>

              <div class="form-group mt-3">
                <label class="form-label" for="custom-rejection-reason">Custom Reason</label>
                <textarea
                  id="custom-rejection-reason"
                  v-model="customReason"
                  class="form-textarea"
                  placeholder="Describe the issue in detail…"
                  rows="3"
                  @input="rejectionReason = ''"
                ></textarea>
              </div>

              <p v-if="rejectionError" class="field-error">{{ rejectionError }}</p>
            </div>

            <!-- Approval History -->
            <div v-if="approvalHistory.length" class="history-section">
              <h4 class="history-heading">Approval History</h4>
              <div class="history-list">
                <div v-for="h in approvalHistory" :key="h.id" class="history-item">
                  <span class="badge" :class="statusBadgeClass(h.action)">{{ h.action }}</span>
                  <div class="history-meta">
                    <p class="history-by">{{ h.by }} <span class="history-date">— {{ h.date }}</span></p>
                    <p v-if="h.reason" class="history-reason">{{ h.reason }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <button class="btn btn--ghost" @click="close" id="cancel-approval-btn">Cancel</button>
            <button
              v-if="isApproving"
              class="btn btn--approve"
              :disabled="!approveConfirmed || submitting"
              @click="submitApprove"
              id="confirm-approve-btn"
            >
              <span v-if="submitting">Processing…</span>
              <span v-else>✓ Confirm Approval</span>
            </button>
            <button
              v-else
              class="btn btn--reject"
              :disabled="!finalReason || submitting"
              @click="submitReject"
              id="confirm-reject-btn"
            >
              <span v-if="submitting">Processing…</span>
              <span v-else>✕ Confirm Rejection</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue:  { type: Boolean, default: false },
  requirement: { type: Object, default: null },
  initialMode: { type: String, default: 'approve' }, // 'approve' | 'reject'
})

const emit = defineEmits(['update:modelValue', 'approved', 'rejected'])

const isApproving    = ref(props.initialMode === 'approve')
const approveConfirmed = ref(false)
const rejectionReason  = ref('')
const customReason     = ref('')
const rejectionError   = ref('')
const submitting       = ref(false)

watch(() => props.initialMode, v => { isApproving.value = v === 'approve' })
watch(() => props.modelValue, open => {
  if (!open) {
    approveConfirmed.value = false
    rejectionReason.value  = ''
    customReason.value     = ''
    rejectionError.value   = ''
    submitting.value       = false
  }
})

const presetReasons = [
  'Document expired',
  'Incomplete information',
  'Unclear / illegible',
  'Wrong document type',
  'Other',
]

const finalReason = computed(() => customReason.value.trim() || rejectionReason.value)

// Stub: could be passed as prop from parent
const approvalHistory = ref([])

const requirementTypes = {
  birth_certificate: 'Birth Certificate',
  school_record:     'School Record',
  health_record:     'Health Record',
  proof_of_income:   'Proof of Income',
  valid_id:          'Valid ID',
  other:             'Other',
}
const typeLabel = (v) => requirementTypes[v] ?? v

const close = () => emit('update:modelValue', false)

const submitApprove = async () => {
  if (!approveConfirmed.value) return
  submitting.value = true
  try {
    const res = await fetch(`/requirements/approve/${props.requirement.id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    })
    const data = await res.json()
    if (data.success) {
      emit('approved', data.requirement)
      close()
    }
  } catch (e) { /* handle */ }
  finally { submitting.value = false }
}

const submitReject = async () => {
  const reason = finalReason.value
  if (!reason) { rejectionError.value = 'Please provide a rejection reason.'; return }
  rejectionError.value = ''
  submitting.value = true
  try {
    const res = await fetch(`/requirements/reject/${props.requirement.id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ rejection_reason: reason }),
    })
    const data = await res.json()
    if (data.success) {
      emit('rejected', data.requirement)
      close()
    }
  } catch (e) { /* handle */ }
  finally { submitting.value = false }
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

const statusBadgeClass = (status) => ({
  'badge--approved': status === 'approved' || status === 'approve',
  'badge--rejected': status === 'rejected' || status === 'reject',
})
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; z-index: 9100;
  background: rgba(0,0,0,.75); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal-panel {
  background: #0f172a; border: 1px solid #1e293b; border-radius: 16px;
  width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto;
  box-shadow: 0 25px 60px rgba(0,0,0,.6); display: flex; flex-direction: column;
}
.modal-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #1e293b;
}
.modal-title   { color: #f1f5f9; font-size: 1.05rem; font-weight: 700; margin: 0; }
.modal-subtitle{ color: #64748b; font-size: .8rem; margin: .2rem 0 0; }
.modal-close {
  background: rgba(30,41,59,.8); border: 1px solid #334155; color: #94a3b8;
  border-radius: 8px; width: 2rem; height: 2rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: .9rem; transition: all .15s;
}
.modal-close:hover { background: rgba(239,68,68,.2); color: #f87171; }
.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: .75rem;
  padding: 1rem 1.5rem; border-top: 1px solid #1e293b;
}

/* Mode Toggle */
.mode-toggle { display: flex; gap: 0; border: 1px solid #1e293b; border-radius: 8px; overflow: hidden; }
.mode-btn {
  flex: 1; padding: .55rem 1rem; font-size: .87rem; font-weight: 600;
  cursor: pointer; border: none; background: rgba(15,23,42,.8); color: #64748b;
  transition: all .2s;
}
.mode-btn--active-approve { background: rgba(34,197,94,.15); color: #4ade80; }
.mode-btn--active-reject  { background: rgba(239,68,68,.15);  color: #f87171; }

/* Action Panels */
.action-panel { background: rgba(15,23,42,.5); border-radius: 10px; padding: 1.1rem; border: 1px solid #1e293b; }
.action-panel--approve { border-color: rgba(34,197,94,.25); }
.action-panel--reject  { border-color: rgba(239,68,68,.25); }
.panel-text { color: #94a3b8; font-size: .87rem; margin: 0 0 1rem; line-height: 1.5; }
.panel-text strong { color: #e2e8f0; }

.doc-summary { background: rgba(0,0,0,.25); border-radius: 8px; overflow: hidden; border: 1px solid #1e293b; margin-bottom: .9rem; }
.doc-summary__row {
  display: flex; justify-content: space-between; gap: 1rem;
  padding: .45rem .85rem; border-bottom: 1px solid #1e293b; font-size: .82rem;
}
.doc-summary__row:last-child { border-bottom: none; }
.doc-summary__row span:first-child { color: #475569; flex-shrink: 0; }
.doc-summary__row span:last-child  { color: #cbd5e1; text-align: right; word-break: break-word; }

.confirm-check {
  display: flex; align-items: flex-start; gap: .6rem; cursor: pointer;
  font-size: .84rem; color: #94a3b8; line-height: 1.4;
}
.confirm-check input { width: 1rem; height: 1rem; accent-color: #4ade80; margin-top: .1rem; flex-shrink: 0; }

/* Reason Chips */
.reason-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
.reason-chip {
  background: rgba(30,41,59,.8); border: 1px solid #334155; color: #94a3b8;
  border-radius: 9999px; padding: .3rem .8rem; font-size: .8rem; cursor: pointer;
  transition: all .15s;
}
.reason-chip:hover { border-color: #ef4444; color: #fca5a5; }
.reason-chip--active { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.4); color: #f87171; }

/* Form */
.form-group { display: flex; flex-direction: column; gap: .3rem; }
.form-label { color: #64748b; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.form-textarea {
  background: rgba(15,23,42,.8); border: 1px solid #334155; border-radius: 8px;
  color: #e2e8f0; padding: .6rem .9rem; font-size: .87rem;
  resize: vertical; outline: none; font-family: inherit; transition: border-color .2s;
}
.form-textarea:focus { border-color: #ef4444; }
.field-error { color: #f87171; font-size: .8rem; }
.mt-3 { margin-top: .75rem; }

/* History */
.history-section { border-top: 1px solid #1e293b; padding-top: 1rem; }
.history-heading { color: #64748b; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; margin: 0 0 .7rem; }
.history-list { display: flex; flex-direction: column; gap: .6rem; }
.history-item { display: flex; align-items: flex-start; gap: .7rem; }
.history-meta { font-size: .82rem; }
.history-by   { color: #cbd5e1; margin: 0; }
.history-date { color: #475569; }
.history-reason { color: #94a3b8; margin: .15rem 0 0; font-style: italic; }

/* Badges */
.badge { padding: .2rem .55rem; border-radius: 9999px; font-size: .72rem; font-weight: 600; text-transform: capitalize; }
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: .35rem;
  padding: .5rem 1.1rem; border-radius: 8px; font-size: .87rem;
  font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all .15s;
}
.btn--ghost   { background: transparent; color: #64748b; border-color: #334155; }
.btn--ghost:hover { color: #cbd5e1; border-color: #475569; }
.btn--approve { background: rgba(34,197,94,.15); color: #4ade80; border-color: rgba(34,197,94,.3); }
.btn--approve:hover:not(:disabled) { background: rgba(34,197,94,.3); }
.btn--approve:disabled { opacity: .4; cursor: not-allowed; }
.btn--reject  { background: rgba(239,68,68,.15);  color: #f87171; border-color: rgba(239,68,68,.3); }
.btn--reject:hover:not(:disabled)  { background: rgba(239,68,68,.3); }
.btn--reject:disabled  { opacity: .4; cursor: not-allowed; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity .2s, transform .2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; transform: scale(.96); }
</style>
