<template>
  <div class="change-history">
    <div class="ch-header">
      <div class="ch-title">📝 Change History</div>
      <div class="ch-meta" v-if="resourceLabel">
        <span class="resource-pill">{{ resourceLabel }}</span>
      </div>
      <button class="btn-export-ch" @click="exportHistory">📄 Export</button>
    </div>

    <div v-if="loading" class="ch-loading">
      <div v-for="i in 4" :key="i" class="ch-skel"></div>
    </div>

    <div v-else-if="!changes.length" class="ch-empty">
      <div class="ce-icon">📜</div>
      <div class="ce-text">No changes recorded for this record.</div>
    </div>

    <div v-else class="ch-timeline">
      <div v-for="(change, idx) in changes" :key="change.id" class="ch-entry">
        <!-- Step indicator -->
        <div class="ce-step-wrap">
          <div class="ce-step-num">{{ changes.length - idx }}</div>
          <div class="ce-step-line" v-if="idx < changes.length - 1"></div>
        </div>

        <!-- Change card -->
        <div class="ce-card" :class="{ 'ce-delete': change.activity_type === 'delete' }">
          <div class="ce-card-header">
            <div class="ce-action-badge" :class="'action-' + change.activity_type">
              {{ change.activity_type }}
            </div>
            <div class="ce-actor">by <strong>{{ change.user?.name ?? 'System' }}</strong></div>
            <div class="ce-ts">{{ formatTs(change.timestamp) }}</div>
          </div>

          <!-- Changed fields diff -->
          <div v-if="getChangedFields(change).length" class="ce-diff">
            <div class="diff-header">
              <span class="dh-field">Field</span>
              <span class="dh-old">Before</span>
              <span class="dh-new">After</span>
            </div>
            <div
              v-for="f in getChangedFields(change)"
              :key="f.key"
              class="diff-row"
            >
              <span class="dr-field">{{ f.key }}</span>
              <span class="dr-old">{{ displayVal(f.old) }}</span>
              <span class="dr-new">{{ displayVal(f.new) }}</span>
            </div>
          </div>

          <div v-else class="ce-no-diff">
            {{ change.activity_description }}
          </div>

          <!-- Remarks -->
          <div v-if="change.remarks" class="ce-remarks">
            💬 {{ change.remarks }}
          </div>

          <!-- Admin revert option (shows confirmation) -->
          <div v-if="canRevert && change.old_values && !revertingId" class="ce-revert">
            <button class="btn-revert" @click="confirmRevert(change)">↩ Revert to this state</button>
          </div>
          <div v-if="revertingChange?.id === change.id" class="revert-confirm">
            <span>⚠️ Are you sure? This will overwrite current data.</span>
            <button class="btn-confirm-revert" @click="doRevert(change)">Confirm</button>
            <button class="btn-cancel-revert" @click="revertingChange = null">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  resourceType:  { type: String, required: true }, // e.g. 'Beneficiary'
  resourceId:    { type: Number, required: true },
  resourceLabel: { type: String, default: '' },
  canRevert:     { type: Boolean, default: false },
})

const emit = defineEmits(['reverted'])

const changes       = ref([])
const loading       = ref(false)
const revertingChange = ref(null)

async function loadHistory () {
  loading.value = true
  try {
    const res = await axios.get('/audit-logs', {
      params: {
        resource_type:    props.resourceType,
        resource_id:      props.resourceId,
        activity_category: 'data_change',
        per_page: 100,
      },
    })
    changes.value = res.data.data ?? []
  } catch (e) {
    console.error('ChangeHistoryViewer: load failed', e)
  } finally {
    loading.value = false
  }
}

function getChangedFields (change) {
  const old = change.old_values ?? {}
  const nw  = change.new_values ?? {}
  const keys = [...new Set([...Object.keys(old), ...Object.keys(nw)])]
    .filter(k => !['updated_at', 'created_at'].includes(k))

  return keys
    .filter(k => old[k] !== nw[k])
    .map(k => ({ key: k, old: old[k] ?? null, new: nw[k] ?? null }))
}

function displayVal (v) {
  if (v === null || v === undefined) return '—'
  if (typeof v === 'object') return JSON.stringify(v)
  if (typeof v === 'boolean') return v ? 'Yes' : 'No'
  if (String(v).length > 80) return String(v).slice(0, 80) + '…'
  return String(v)
}

function formatTs (ts) {
  if (!ts) return '—'
  return new Date(ts).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
}

function confirmRevert (change) {
  revertingChange.value = change
}

async function doRevert (change) {
  // Actual revert requires a dedicated backend endpoint; here we signal intent.
  emit('reverted', { change, oldValues: change.old_values })
  revertingChange.value = null
}

function exportHistory () {
  const rows = [
    ['#', 'Timestamp', 'Action', 'Actor', 'Changed Fields'],
    ...changes.value.map((c, i) => [
      changes.value.length - i,
      new Date(c.timestamp).toLocaleString('en-PH'),
      c.activity_type,
      c.user?.name ?? 'System',
      getChangedFields(c).map(f => `${f.key}: ${displayVal(f.old)} → ${displayVal(f.new)}`).join(' | '),
    ]),
  ]
  const csv  = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href = url; a.download = `change_history_${props.resourceType}_${props.resourceId}.csv`; a.click()
  URL.revokeObjectURL(url)
}

onMounted(loadHistory)
</script>

<style scoped>
.change-history { background: white; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }

.ch-header {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
}
.ch-title { font-weight: 800; font-size: 0.88rem; color: #0f172a; }
.ch-meta { flex: 1; }
.resource-pill { background: #eff6ff; color: #1d4ed8; padding: 2px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 700; }
.btn-export-ch { padding: 5px 12px; background: white; border: 1px solid #3b82f6; color: #1d4ed8; border-radius: 7px; font-size: 0.75rem; font-weight: 700; cursor: pointer; margin-left: auto; }
.btn-export-ch:hover { background: #eff6ff; }

.ch-loading { padding: 12px 16px; display: flex; flex-direction: column; gap: 10px; }
.ch-skel { height: 80px; background: #f1f5f9; border-radius: 8px; animation: pulse 1.2s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

.ch-empty { padding: 36px; text-align: center; }
.ce-icon { font-size: 1.8rem; margin-bottom: 6px; }
.ce-text { font-size: 0.82rem; color: #94a3b8; }

.ch-timeline { padding: 16px; max-height: 600px; overflow-y: auto; display: flex; flex-direction: column; gap: 0; }

.ch-entry { display: flex; gap: 14px; }

.ce-step-wrap { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; width: 28px; }
.ce-step-num { width: 28px; height: 28px; border-radius: 50%; background: #1e40af; color: white; font-size: 0.7rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ce-step-line { flex: 1; width: 2px; background: #e2e8f0; margin-top: 3px; min-height: 20px; }

.ce-card {
  flex: 1; border: 1px solid #e2e8f0; border-radius: 10px;
  margin-bottom: 12px; overflow: hidden;
}
.ce-card.ce-delete { border-color: #fecaca; }

.ce-card-header { display: flex; align-items: center; gap: 8px; padding: 9px 12px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; }
.ce-action-badge { padding: 2px 10px; border-radius: 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
.action-edit   { background: #fff7ed; color: #c2410c; }
.action-delete { background: #fee2e2; color: #b91c1c; }
.action-approve { background: #f0fdf4; color: #15803d; }
.action-create { background: #eff6ff; color: #1d4ed8; }
.ce-actor { font-size: 0.75rem; color: #475569; }
.ce-ts { font-size: 0.68rem; color: #94a3b8; font-family: monospace; margin-left: auto; }

.ce-diff { overflow-x: auto; }
.diff-header { display: grid; grid-template-columns: 1fr 1fr 1fr; background: #f1f5f9; }
.dh-field, .dh-old, .dh-new { padding: 5px 10px; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.4px; }
.diff-row { display: grid; grid-template-columns: 1fr 1fr 1fr; border-top: 1px solid #f1f5f9; }
.diff-row:hover { background: #fafafa; }
.dr-field { padding: 6px 10px; font-size: 0.75rem; font-family: monospace; font-weight: 600; color: #1e293b; background: #f8fafc; }
.dr-old { padding: 6px 10px; font-size: 0.72rem; color: #dc2626; background: #fff8f8; word-break: break-all; }
.dr-new { padding: 6px 10px; font-size: 0.72rem; color: #15803d; background: #f0fdf4; word-break: break-all; }

.ce-no-diff { padding: 10px; font-size: 0.78rem; color: #475569; }
.ce-remarks { padding: 7px 12px; background: #fefce8; border-top: 1px solid #fef08a; font-size: 0.75rem; color: #92400e; }

.ce-revert { padding: 8px 12px; border-top: 1px solid #f1f5f9; }
.btn-revert { padding: 4px 12px; background: white; border: 1px solid #f59e0b; color: #92400e; border-radius: 6px; font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.btn-revert:hover { background: #fef3c7; }

.revert-confirm { padding: 8px 12px; background: #fff5f5; border-top: 1px solid #fecaca; display: flex; align-items: center; gap: 10px; font-size: 0.75rem; color: #dc2626; }
.btn-confirm-revert { padding: 4px 12px; background: #dc2626; color: white; border: none; border-radius: 6px; font-size: 0.72rem; font-weight: 700; cursor: pointer; }
.btn-cancel-revert { padding: 4px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.72rem; cursor: pointer; }
</style>
