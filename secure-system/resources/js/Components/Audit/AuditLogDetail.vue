<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="detail-modal">
      <div class="modal-header">
        <div class="mh-left">
          <span class="mh-id">Log #{{ log.id }}</span>
          <span class="type-badge" :class="'type-' + log.activity_type">{{ log.activity_type }}</span>
          <span v-if="log.severity" class="sev-badge" :class="'sev-' + log.severity">{{ log.severity }}</span>
        </div>
        <button class="btn-close" @click="$emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Main info -->
        <div class="info-grid">
          <div class="info-item">
            <div class="info-lbl">Timestamp</div>
            <div class="info-val mono">{{ formatTs(log.timestamp) }}</div>
          </div>
          <div class="info-item">
            <div class="info-lbl">User</div>
            <div class="info-val">{{ log.user?.name ?? '—' }} <span class="role-chip">{{ log.user?.role }}</span></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">IP Address</div>
            <div class="info-val mono">{{ log.ip_address }}</div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Status</div>
            <div class="info-val" :class="log.status === 'success' ? 'clr-success' : 'clr-fail'">
              {{ log.status === 'success' ? '✅ Success' : '❌ Failed' }}
            </div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Category</div>
            <div class="info-val">{{ log.activity_category ?? '—' }}</div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Response Code</div>
            <div class="info-val mono" :class="responseClass(log.response_status)">{{ log.response_status ?? '—' }}</div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Execution Time</div>
            <div class="info-val mono">{{ log.execution_time != null ? log.execution_time.toFixed(4) + 's' : '—' }}</div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Beneficiary</div>
            <div class="info-val">{{ log.beneficiary?.family_head_name ?? '—' }} {{ log.beneficiary?.bin ? `(${log.beneficiary.bin})` : '' }}</div>
          </div>
        </div>

        <div class="desc-block">
          <div class="info-lbl">Description</div>
          <div class="desc-text">{{ log.activity_description }}</div>
        </div>

        <div v-if="log.remarks" class="desc-block">
          <div class="info-lbl">Remarks</div>
          <div class="desc-text">{{ log.remarks }}</div>
        </div>

        <div v-if="log.user_agent" class="desc-block">
          <div class="info-lbl">User Agent</div>
          <div class="desc-text mono small">{{ log.user_agent }}</div>
        </div>

        <!-- Change Diff -->
        <div v-if="log.old_values || log.new_values" class="diff-section">
          <div class="section-title">📝 Change Diff (Before → After)</div>
          <div class="diff-grid-header">
            <div class="diff-col-hd">Field</div>
            <div class="diff-col-hd old-col">Before</div>
            <div class="diff-col-hd new-col">After</div>
          </div>
          <div
            v-for="(entry, field) in changedFields"
            :key="field"
            class="diff-row"
          >
            <div class="diff-field">{{ field }}</div>
            <div class="diff-old">{{ formatVal(entry.old) }}</div>
            <div class="diff-new">{{ formatVal(entry.new) }}</div>
          </div>
          <div v-if="!Object.keys(changedFields).length" class="diff-none">
            No field differences detected.
          </div>
        </div>

        <!-- Request Data -->
        <div v-if="log.request_data && Object.keys(log.request_data).length" class="json-section">
          <div class="section-title" :class="{ collapsed: !showRequest }" @click="showRequest = !showRequest">
            📦 Request Payload <span class="toggle-arrow">{{ showRequest ? '▲' : '▼' }}</span>
          </div>
          <div v-show="showRequest" class="json-block">
            <pre>{{ JSON.stringify(log.request_data, null, 2) }}</pre>
          </div>
        </div>

        <!-- Timeline context -->
        <div v-if="timeline?.length" class="timeline-section">
          <div class="section-title">🕐 Context Timeline (±30 min)</div>
          <div class="tl-list">
            <div
              v-for="evt in timeline"
              :key="evt.id"
              class="tl-item"
              :class="{ 'tl-current': evt.id === log.id, 'tl-fail': evt.status === 'failed' }"
            >
              <div class="tl-dot"></div>
              <div class="tl-body">
                <div class="tl-ts">{{ formatTs(evt.timestamp) }}</div>
                <div class="tl-desc">{{ evt.description }}</div>
                <div class="tl-type-badge">{{ evt.type }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <span class="footer-note">Log entries are immutable — no modifications possible.</span>
        <button class="btn-close-footer" @click="$emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  log:      { type: Object, required: true },
  timeline: { type: Array, default: () => [] },
})

defineEmits(['close'])

const showRequest = ref(false)

const changedFields = computed(() => {
  const old = props.log.old_values ?? {}
  const nw  = props.log.new_values ?? {}
  const allKeys = [...new Set([...Object.keys(old), ...Object.keys(nw)])]
  const diff = {}
  for (const k of allKeys) {
    if (old[k] !== nw[k]) {
      diff[k] = { old: old[k] ?? null, new: nw[k] ?? null }
    }
  }
  return diff
})

function formatTs (ts) {
  if (!ts) return '—'
  return new Date(ts).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'medium' })
}

function formatVal (v) {
  if (v === null || v === undefined) return 'null'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function responseClass (code) {
  if (!code) return ''
  if (code < 300) return 'clr-success'
  if (code < 400) return 'clr-warn'
  return 'clr-fail'
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: flex-start; justify-content: flex-end; }
.detail-modal { width: min(680px, 95vw); height: 100vh; background: white; display: flex; flex-direction: column; box-shadow: -8px 0 40px rgba(0,0,0,0.2); animation: slideIn 0.25s ease; }
@keyframes slideIn { from { transform: translateX(100%); } to { transform: none; } }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0; }
.mh-left { display: flex; align-items: center; gap: 8px; }
.mh-id { font-size: 0.78rem; color: #94a3b8; font-family: monospace; }

.btn-close { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 12px; cursor: pointer; font-size: 0.8rem; color: #64748b; }
.btn-close:hover { background: #fee2e2; color: #dc2626; }

.modal-body { flex: 1; overflow-y: auto; padding: 18px 20px; display: flex; flex-direction: column; gap: 16px; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.info-item { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 10px 12px; }
.info-lbl { font-size: 0.68rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.info-val { font-size: 0.82rem; color: #1e293b; font-weight: 500; }
.info-val.mono { font-family: monospace; font-size: 0.78rem; }

.role-chip { font-size: 0.68rem; color: #94a3b8; margin-left: 6px; }
.clr-success { color: #15803d; font-weight: 700; }
.clr-fail    { color: #dc2626; font-weight: 700; }
.clr-warn    { color: #d97706; font-weight: 700; }

.desc-block { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 10px 12px; }
.desc-text { font-size: 0.82rem; color: #1e293b; line-height: 1.6; }
.desc-text.mono { font-family: monospace; font-size: 0.72rem; }
.desc-text.small { font-size: 0.7rem; word-break: break-all; }

.type-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; background: #f1f5f9; color: #475569; }
.type-delete  { background: #fff5f5; color: #dc2626; }
.type-edit    { background: #fff7ed; color: #c2410c; }
.type-approve { background: #f0fdf4; color: #15803d; }
.sev-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
.sev-critical { background: #fee2e2; color: #b91c1c; }
.sev-high     { background: #ffedd5; color: #c2410c; }
.sev-medium   { background: #fef9c3; color: #92400e; }
.sev-low      { background: #f0fdf4; color: #15803d; }

.section-title { font-size: 0.82rem; font-weight: 800; color: #0f172a; margin-bottom: 10px; cursor: pointer; user-select: none; }
.toggle-arrow { font-size: 0.7rem; margin-left: 6px; color: #94a3b8; }

.diff-section { background: white; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.diff-grid-header { display: grid; grid-template-columns: 1fr 1fr 1fr; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.diff-col-hd { padding: 7px 10px; font-size: 0.68rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
.old-col { background: #fff8f8; }
.new-col { background: #f0fdf4; }
.diff-row { display: grid; grid-template-columns: 1fr 1fr 1fr; border-bottom: 1px solid #f1f5f9; }
.diff-field { padding: 7px 10px; font-size: 0.78rem; font-weight: 600; color: #1e293b; background: #f8fafc; font-family: monospace; }
.diff-old { padding: 7px 10px; font-size: 0.72rem; color: #dc2626; background: #fff8f8; font-family: monospace; word-break: break-all; }
.diff-new { padding: 7px 10px; font-size: 0.72rem; color: #15803d; background: #f0fdf4; font-family: monospace; word-break: break-all; }
.diff-none { padding: 10px; font-size: 0.78rem; color: #94a3b8; text-align: center; }

.json-section { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.json-block { background: #0f172a; padding: 12px 14px; overflow-x: auto; }
.json-block pre { font-size: 0.72rem; color: #93c5fd; line-height: 1.6; white-space: pre-wrap; }

.timeline-section { margin-bottom: 4px; }
.tl-list { display: flex; flex-direction: column; gap: 0; position: relative; }
.tl-list::before { content: ''; position: absolute; left: 9px; top: 10px; bottom: 10px; width: 2px; background: #e2e8f0; }
.tl-item { display: flex; gap: 12px; align-items: flex-start; padding: 8px 0; position: relative; }
.tl-item.tl-current .tl-dot { background: #1e40af; }
.tl-item.tl-fail .tl-dot { background: #dc2626; }
.tl-dot { width: 18px; height: 18px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; margin-top: 2px; border: 3px solid white; box-shadow: 0 0 0 2px #e2e8f0; }
.tl-body { flex: 1; }
.tl-ts { font-size: 0.68rem; color: #94a3b8; font-family: monospace; }
.tl-desc { font-size: 0.78rem; color: #1e293b; margin-top: 1px; }
.tl-type-badge { display: inline-block; margin-top: 2px; font-size: 0.65rem; background: #f1f5f9; color: #64748b; padding: 1px 7px; border-radius: 10px; font-weight: 600; }

.modal-footer { padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.footer-note { font-size: 0.72rem; color: #94a3b8; font-style: italic; }
.btn-close-footer { padding: 8px 20px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; color: #475569; cursor: pointer; font-weight: 600; }
.btn-close-footer:hover { background: #e2e8f0; }
</style>
