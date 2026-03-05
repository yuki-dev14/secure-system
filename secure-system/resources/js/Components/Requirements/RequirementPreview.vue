<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modelValue" class="modal-overlay" @click.self="close" id="requirement-preview-modal">
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="preview-modal-title">
          <!-- Header -->
          <div class="modal-header">
            <div>
              <h2 id="preview-modal-title" class="modal-title">Document Preview</h2>
              <p class="modal-subtitle">{{ typeLabel(requirement?.requirement_type) }}</p>
            </div>
            <button class="modal-close" @click="close" id="close-preview-btn" aria-label="Close preview">✕</button>
          </div>

          <!-- Body -->
          <div class="modal-body">
            <!-- Left: Preview pane -->
            <div class="preview-pane">
              <!-- PDF -->
              <iframe
                v-if="isPdf"
                :src="`/requirements/download/${requirement.id}#view=FitH`"
                class="preview-iframe"
                title="PDF Preview"
              ></iframe>
              <!-- Image -->
              <img
                v-else-if="isImage"
                :src="`/requirements/download/${requirement.id}`"
                :alt="requirement.file_name"
                class="preview-image"
              />
              <!-- Unsupported -->
              <div v-else class="preview-unsupported">
                <span class="preview-unsupported__icon">📄</span>
                <p>Preview not available for this file type.</p>
                <p class="preview-unsupported__hint">{{ requirement?.file_name }}</p>
              </div>
            </div>

            <!-- Right: Metadata + Approval -->
            <div class="meta-pane">
              <!-- Metadata -->
              <div class="meta-section">
                <h3 class="meta-heading">File Details</h3>
                <dl class="meta-grid">
                  <dt>Type</dt>    <dd>{{ typeLabel(requirement?.requirement_type) }}</dd>
                  <dt>Name</dt>   <dd class="dd-break">{{ requirement?.file_name }}</dd>
                  <dt>Size</dt>   <dd>{{ requirement?.file_size_human }}</dd>
                  <dt>MIME</dt>   <dd>{{ requirement?.file_type }}</dd>
                  <dt>Submitted</dt> <dd>{{ requirement?.submitted_at_fmt }}</dd>
                  <dt>By</dt>     <dd>{{ requirement?.submitted_by?.name ?? '—' }}</dd>
                </dl>
              </div>

              <!-- Status -->
              <div class="meta-section">
                <h3 class="meta-heading">Status</h3>
                <span class="badge" :class="statusBadgeClass(requirement?.approval_status)">
                  {{ requirement?.approval_status }}
                </span>
                <template v-if="requirement?.approval_status !== 'pending'">
                  <dl class="meta-grid mt-2">
                    <dt>{{ requirement.approval_status === 'approved' ? 'Approved By' : 'Rejected By' }}</dt>
                    <dd>{{ requirement?.approved_by?.name ?? '—' }}</dd>
                    <dt>Date</dt>
                    <dd>{{ approvalDateFmt }}</dd>
                  </dl>
                  <p v-if="requirement?.rejection_reason" class="rejection-reason">
                    <strong>Reason:</strong> {{ requirement.rejection_reason }}
                  </p>
                </template>
              </div>

              <!-- Expiration -->
              <div v-if="requirement?.expiration_date" class="meta-section">
                <h3 class="meta-heading">Expiration</h3>
                <p :class="['expiry-chip', requirement.is_expired ? 'expiry-chip--expired' : (requirement.days_until_expiry <= 30 ? 'expiry-chip--soon' : 'expiry-chip--ok')]">
                  <template v-if="requirement.is_expired">⚠ Expired</template>
                  <template v-else>{{ requirement.days_until_expiry }} day(s) remaining</template>
                  <span class="expiry-chip__date">({{ requirement.expiration_date }})</span>
                </p>
              </div>

              <!-- Actions (if allowed) -->
              <div v-if="canApprove && requirement?.approval_status === 'pending'" class="meta-section">
                <h3 class="meta-heading">Actions</h3>
                <div class="action-row">
                  <button id="preview-approve-btn" class="btn btn--approve" @click="$emit('approve', requirement)">✓ Approve</button>
                  <button id="preview-reject-btn"  class="btn btn--reject"  @click="$emit('reject',  requirement)">✕ Reject</button>
                </div>
              </div>

              <!-- Download / Print -->
              <div class="meta-section">
                <div class="action-row">
                  <a
                    :href="`/requirements/download/${requirement?.id}`"
                    class="btn btn--download"
                    target="_blank"
                    id="preview-download-btn"
                  >⬇ Download</a>
                  <button
                    class="btn btn--print"
                    id="preview-print-btn"
                    @click="printIframe"
                  >🖨 Print</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue:  { type: Boolean, default: false },
  requirement: { type: Object, default: null },
  canApprove:  { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'approve', 'reject'])

const close = () => emit('update:modelValue', false)

const requirementTypes = {
  birth_certificate: 'Birth Certificate',
  school_record:     'School Record',
  health_record:     'Health Record',
  proof_of_income:   'Proof of Income',
  valid_id:          'Valid ID',
  other:             'Other',
}
const typeLabel = (v) => requirementTypes[v] ?? v

const isPdf   = computed(() => props.requirement?.file_type === 'application/pdf')
const isImage = computed(() => props.requirement?.file_type?.startsWith('image/'))

const approvalDateFmt = computed(() => {
  if (!props.requirement?.approval_date) return '—'
  return new Date(props.requirement.approval_date).toLocaleDateString('en-PH', {
    month: 'short', day: 'numeric', year: 'numeric',
  })
})

const statusBadgeClass = (status) => ({
  'badge--pending':  status === 'pending',
  'badge--approved': status === 'approved',
  'badge--rejected': status === 'rejected',
})

const printIframe = () => {
  const iframe = document.querySelector('.preview-iframe')
  if (iframe) { iframe.contentWindow?.print() }
  else { window.print() }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; z-index: 9000;
  background: rgba(0,0,0,.75); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal-panel {
  background: #0f172a; border: 1px solid #1e293b; border-radius: 16px;
  width: 100%; max-width: 960px; max-height: 92vh;
  display: flex; flex-direction: column; overflow: hidden;
  box-shadow: 0 25px 60px rgba(0,0,0,.6);
}
.modal-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #1e293b; flex-shrink: 0;
}
.modal-title { color: #f1f5f9; font-size: 1.1rem; font-weight: 700; margin: 0; }
.modal-subtitle { color: #64748b; font-size: .82rem; margin: .2rem 0 0; }
.modal-close {
  background: rgba(30,41,59,.8); border: 1px solid #334155; color: #94a3b8;
  border-radius: 8px; width: 2rem; height: 2rem; font-size: .9rem;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all .15s; flex-shrink: 0;
}
.modal-close:hover { background: rgba(239,68,68,.2); border-color: #ef4444; color: #f87171; }

.modal-body {
  display: flex; flex: 1; overflow: hidden; min-height: 0;
}

/* Preview Pane */
.preview-pane {
  flex: 1; background: #060e1a; display: flex; align-items: center; justify-content: center;
  overflow: hidden; position: relative;
}
.preview-iframe { width: 100%; height: 100%; border: none; }
.preview-image  { max-width: 100%; max-height: 100%; object-fit: contain; padding: 1rem; }
.preview-unsupported {
  text-align: center; color: #475569;
  display: flex; flex-direction: column; align-items: center; gap: .5rem;
}
.preview-unsupported__icon { font-size: 3rem; }
.preview-unsupported__hint { color: #334155; font-size: .8rem; }

/* Meta Pane */
.meta-pane {
  width: 280px; flex-shrink: 0; padding: 1.25rem;
  border-left: 1px solid #1e293b; overflow-y: auto;
  display: flex; flex-direction: column; gap: 1.2rem;
}
.meta-section { display: flex; flex-direction: column; }
.meta-heading {
  color: #64748b; font-size: .72rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .06em; margin: 0 0 .6rem;
}
.meta-grid { display: grid; grid-template-columns: auto 1fr; gap: .25rem .75rem; font-size: .82rem; }
.meta-grid dt { color: #475569; white-space: nowrap; }
.meta-grid dd { color: #cbd5e1; margin: 0; }
.dd-break { word-break: break-all; }
.mt-2 { margin-top: .5rem; }
.rejection-reason { color: #f87171; font-size: .8rem; margin-top: .5rem; line-height: 1.4; }
.expiry-chip {
  display: inline-flex; flex-direction: column; gap: .15rem;
  font-size: .82rem; font-weight: 600; border-radius: 8px; padding: .4rem .75rem;
}
.expiry-chip--ok      { background: rgba(20,83,45,.2); color: #4ade80; }
.expiry-chip--soon    { background: rgba(120,83,14,.2); color: #fbbf24; }
.expiry-chip--expired { background: rgba(127,29,29,.2); color: #f87171; }
.expiry-chip__date { font-size: .74rem; font-weight: 400; opacity: .8; }

/* Actions */
.action-row { display: flex; gap: .6rem; flex-wrap: wrap; }
.btn {
  display: inline-flex; align-items: center; gap: .35rem;
  padding: .45rem .9rem; border-radius: 8px; font-size: .83rem; font-weight: 600;
  cursor: pointer; border: 1px solid transparent; text-decoration: none;
  transition: all .15s;
}
.btn--approve  { background: rgba(34,197,94,.15);  color: #4ade80;  border-color: rgba(34,197,94,.3); }
.btn--approve:hover { background: rgba(34,197,94,.3); }
.btn--reject   { background: rgba(239,68,68,.15);  color: #f87171;  border-color: rgba(239,68,68,.3); }
.btn--reject:hover  { background: rgba(239,68,68,.3); }
.btn--download { background: rgba(6,182,212,.15);   color: #22d3ee;  border-color: rgba(6,182,212,.3); }
.btn--download:hover { background: rgba(6,182,212,.3); }
.btn--print    { background: rgba(99,102,241,.15);  color: #a5b4fc;  border-color: rgba(99,102,241,.3); }
.btn--print:hover   { background: rgba(99,102,241,.3); }

/* Badges */
.badge { padding: .22rem .6rem; border-radius: 9999px; font-size: .74rem; font-weight: 600; text-transform: capitalize; }
.badge--pending  { background: #78350f22; color: #fbbf24; border: 1px solid #78350f55; }
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }

/* Transitions */
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity .2s ease, transform .2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; transform: scale(.97); }
</style>
