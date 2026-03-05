<template>
  <div class="bulk-actions" id="bulk-document-actions">
    <!-- Selection Controls -->
    <div class="bulk-header">
      <label class="select-all-label" for="bulk-select-all">
        <input
          type="checkbox"
          id="bulk-select-all"
          :checked="allSelected"
          :indeterminate="someSelected && !allSelected"
          @change="toggleAll"
        />
        <span>{{ allSelected ? 'Deselect All' : 'Select All Pending' }}</span>
      </label>

      <span v-if="selectedIds.length" class="selection-count">
        {{ selectedIds.length }} selected
      </span>
    </div>

    <!-- Action Toolbar -->
    <div v-if="selectedIds.length > 0" class="action-toolbar">
      <!-- Bulk Approve — Compliance Verifier / Admin -->
      <button
        v-if="canApprove"
        id="bulk-approve-action-btn"
        class="toolbar-btn toolbar-btn--approve"
        :disabled="approving || !pendingSelectedCount"
        @click="confirmAction('approve')"
      >
        <span v-if="approving" class="spinner"></span>
        ✓ Approve ({{ pendingSelectedCount }})
      </button>

      <!-- Bulk Download — all -->
      <button
        id="bulk-download-action-btn"
        class="toolbar-btn toolbar-btn--download"
        :disabled="downloading"
        @click="bulkDownload"
      >
        <span v-if="downloading" class="spinner"></span>
        ⬇ Download ({{ selectedIds.length }})
      </button>

      <!-- Bulk Delete — Admin only -->
      <button
        v-if="isAdmin"
        id="bulk-delete-action-btn"
        class="toolbar-btn toolbar-btn--delete"
        :disabled="deleting"
        @click="confirmAction('delete')"
      >🗑 Delete ({{ selectedIds.length }})</button>

      <!-- Clear -->
      <button class="toolbar-btn toolbar-btn--ghost" @click="selectedIds = []" id="clear-selection-btn">
        ✕ Clear
      </button>
    </div>

    <!-- Selectable Document Rows -->
    <div class="doc-check-list">
      <div
        v-for="doc in documents"
        :key="doc.id"
        :class="['doc-check-row', { 'doc-check-row--selected': selectedIds.includes(doc.id) }]"
      >
        <input
          type="checkbox"
          :id="`bulk-check-${doc.id}`"
          :value="doc.id"
          v-model="selectedIds"
          class="doc-check"
        />
        <label :for="`bulk-check-${doc.id}`" class="doc-check-label">
          <span class="doc-check-type">{{ typeLabel(doc.requirement_type) }}</span>
          <span class="doc-check-name">{{ doc.file_name }}</span>
          <span class="badge" :class="statusBadgeClass(doc.approval_status)">{{ doc.approval_status }}</span>
        </label>
      </div>
    </div>

    <!-- Bulk Progress Indicator -->
    <div v-if="progress.active" class="progress-overlay">
      <div class="progress-modal">
        <p class="progress-title">{{ progress.label }}</p>
        <div class="progress-track">
          <div class="progress-fill" :style="{ width: progress.pct + '%' }"></div>
        </div>
        <p class="progress-sub">{{ progress.done }} / {{ progress.total }}</p>
      </div>
    </div>

    <!-- Confirmation Dialog -->
    <Transition name="modal-fade">
      <div v-if="confirmDialog.show" class="confirm-overlay" @click.self="confirmDialog.show = false" id="bulk-confirm-overlay">
        <div class="confirm-panel">
          <h3 class="confirm-title">{{ confirmDialog.title }}</h3>
          <p class="confirm-body">{{ confirmDialog.body }}</p>
          <div class="confirm-actions">
            <button class="btn btn--ghost" @click="confirmDialog.show = false" id="confirm-cancel-btn">Cancel</button>
            <button
              :class="['btn', confirmDialog.danger ? 'btn--danger' : 'btn--approve']"
              @click="executeConfirmed"
              id="confirm-execute-btn"
            >{{ confirmDialog.confirmLabel }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  documents:  { type: Array,   default: () => [] },
  canApprove: { type: Boolean, default: false },
  isAdmin:    { type: Boolean, default: false },
})

const emit = defineEmits(['approved', 'deleted', 'refresh'])

const selectedIds = ref([])
const approving   = ref(false)
const downloading = ref(false)
const deleting    = ref(false)

const pendingDocs = computed(() => props.documents.filter(d => d.approval_status === 'pending'))
const allSelected = computed(() => pendingDocs.value.length > 0 && pendingDocs.value.every(d => selectedIds.value.includes(d.id)))
const someSelected = computed(() => pendingDocs.value.some(d => selectedIds.value.includes(d.id)))
const pendingSelectedCount = computed(() => pendingDocs.value.filter(d => selectedIds.value.includes(d.id)).length)

const progress = ref({ active: false, label: '', done: 0, total: 0, pct: 0 })
const confirmDialog = ref({ show: false, title: '', body: '', confirmLabel: '', danger: false, action: '' })

const toggleAll = () => {
  if (allSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !pendingDocs.value.map(d => d.id).includes(id))
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...pendingDocs.value.map(d => d.id)])]
  }
}

const confirmAction = (action) => {
  if (action === 'approve') {
    confirmDialog.value = {
      show: true, action: 'approve', danger: false,
      title: `Approve ${pendingSelectedCount.value} Document(s)?`,
      body:  'These documents will be marked as approved and will count toward compliance.',
      confirmLabel: '✓ Approve All',
    }
  } else if (action === 'delete') {
    confirmDialog.value = {
      show: true, action: 'delete', danger: true,
      title: `Delete ${selectedIds.value.length} Document(s)?`,
      body:  'This action cannot be undone. The documents and files will be permanently removed.',
      confirmLabel: '🗑 Delete All',
    }
  }
}

const executeConfirmed = async () => {
  confirmDialog.value.show = false
  if (confirmDialog.value.action === 'approve') await doBulkApprove()
  if (confirmDialog.value.action === 'delete')  await doBulkDelete()
}

const doBulkApprove = async () => {
  const ids = pendingDocs.value.filter(d => selectedIds.value.includes(d.id)).map(d => d.id)
  if (!ids.length) return
  approving.value = true
  progress.value   = { active: true, label: 'Approving documents…', done: 0, total: ids.length, pct: 0 }

  try {
    const res = await axios.post('/requirements/bulk-approve', { requirement_ids: ids })
    progress.value.done = res.data.approved_count
    progress.value.pct  = 100
    emit('approved', res.data)
    emit('refresh')
    selectedIds.value = []
  } catch (e) { /* handle errors */ }

  approving.value = false
  setTimeout(() => { progress.value.active = false }, 800)
}

const bulkDownload = async () => {
  downloading.value = true
  for (const id of selectedIds.value) {
    const doc = props.documents.find(d => d.id === id)
    if (!doc) continue
    const a = document.createElement('a')
    a.href = doc.download_url
    a.download = doc.file_name
    a.target = '_blank'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    await new Promise(r => setTimeout(r, 200))
  }
  downloading.value = false
}

const doBulkDelete = async () => {
  // Extend with an actual bulk-delete endpoint if required
  deleting.value = true
  let deleted = 0
  progress.value = { active: true, label: 'Deleting documents…', done: 0, total: selectedIds.value.length, pct: 0 }

  for (const id of selectedIds.value) {
    try {
      await axios.delete(`/requirements/${id}`)
      deleted++
      progress.value.done = deleted
      progress.value.pct  = Math.round((deleted / selectedIds.value.length) * 100)
    } catch (_) { /* skip */ }
  }

  emit('deleted', selectedIds.value)
  emit('refresh')
  selectedIds.value = []
  deleting.value = false
  setTimeout(() => { progress.value.active = false }, 800)
}

const typeLabels = {
  birth_certificate: 'Birth Certificate', school_record: 'School Record',
  health_record: 'Health Record',        proof_of_income: 'Proof of Income',
  valid_id: 'Valid ID',                  other: 'Other',
}
const typeLabel = (v) => typeLabels[v] ?? v

const statusBadgeClass = (s) => ({
  'badge--pending':  s === 'pending',
  'badge--approved': s === 'approved',
  'badge--rejected': s === 'rejected',
})
</script>

<style scoped>
.bulk-actions { font-family: 'Inter', sans-serif; position: relative; }

/* Header */
.bulk-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
.select-all-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; color: #94a3b8; font-size: .85rem; font-weight: 500; }
.select-all-label input { accent-color: #6366f1; width: 1rem; height: 1rem; }
.selection-count { color: #a5b4fc; font-size: .82rem; font-weight: 700; }

/* Toolbar */
.action-toolbar {
  display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
  background: rgba(15,23,42,.8); border: 1px solid #1e293b;
  border-radius: 10px; padding: .65rem 1rem; margin-bottom: .85rem;
}
.toolbar-btn {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .4rem .9rem; border-radius: 7px; font-size: .82rem; font-weight: 600;
  cursor: pointer; border: 1px solid transparent; transition: all .15s;
}
.toolbar-btn:disabled { opacity: .4; cursor: not-allowed; }
.toolbar-btn--approve  { background: rgba(34,197,94,.15);  color: #4ade80; border-color: rgba(34,197,94,.3); }
.toolbar-btn--approve:hover:not(:disabled)  { background: rgba(34,197,94,.3); }
.toolbar-btn--download { background: rgba(6,182,212,.15);  color: #22d3ee; border-color: rgba(6,182,212,.3); }
.toolbar-btn--download:hover:not(:disabled) { background: rgba(6,182,212,.3); }
.toolbar-btn--delete   { background: rgba(239,68,68,.1);   color: #f87171; border-color: rgba(239,68,68,.25); }
.toolbar-btn--delete:hover:not(:disabled)   { background: rgba(239,68,68,.25); }
.toolbar-btn--ghost    { background: transparent; color: #64748b; border-color: #334155; }
.toolbar-btn--ghost:hover { color: #94a3b8; }

/* Spinner */
.spinner {
  display: inline-block; width: .85rem; height: .85rem;
  border: 2px solid currentColor; border-top-color: transparent;
  border-radius: 50%; animation: spin .6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Doc check rows */
.doc-check-list { display: flex; flex-direction: column; gap: .4rem; }
.doc-check-row {
  display: flex; align-items: center; gap: .75rem;
  background: rgba(15,23,42,.5); border: 1px solid #1e293b;
  border-radius: 9px; padding: .6rem .9rem; transition: all .15s;
}
.doc-check-row--selected { border-color: rgba(99,102,241,.4); background: rgba(99,102,241,.07); }
.doc-check { accent-color: #6366f1; width: 1rem; height: 1rem; flex-shrink: 0; }
.doc-check-label { display: flex; align-items: center; gap: .75rem; flex: 1; cursor: pointer; min-width: 0; }
.doc-check-type { color: #a5b4fc; font-size: .78rem; font-weight: 600; white-space: nowrap; }
.doc-check-name { color: #cbd5e1; font-size: .83rem; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Badges */
.badge { padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
.badge--pending  { background: #78350f22; color: #fbbf24; border: 1px solid #78350f55; }
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }

/* Progress Overlay */
.progress-overlay {
  position: fixed; inset: 0; z-index: 9900;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,.5); backdrop-filter: blur(4px);
}
.progress-modal {
  background: #0f172a; border: 1px solid #1e293b; border-radius: 14px;
  padding: 2rem; width: 320px; text-align: center;
}
.progress-title { color: #f1f5f9; font-size: .95rem; font-weight: 700; margin: 0 0 1rem; }
.progress-track { background: #1e293b; border-radius: 9999px; height: 8px; overflow: hidden; }
.progress-fill  { height: 100%; background: linear-gradient(90deg, #6366f1, #22d3ee); transition: width .3s; border-radius: 9999px; }
.progress-sub   { color: #64748b; font-size: .8rem; margin: .6rem 0 0; }

/* Confirm */
.confirm-overlay {
  position: fixed; inset: 0; z-index: 9500;
  background: rgba(0,0,0,.65); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.confirm-panel {
  background: #0f172a; border: 1px solid #1e293b; border-radius: 14px;
  padding: 1.75rem; width: 100%; max-width: 400px;
  box-shadow: 0 20px 50px rgba(0,0,0,.5);
}
.confirm-title  { color: #f1f5f9; font-size: 1rem; font-weight: 700; margin: 0 0 .6rem; }
.confirm-body   { color: #94a3b8; font-size: .87rem; line-height: 1.5; margin: 0 0 1.25rem; }
.confirm-actions{ display: flex; gap: .7rem; justify-content: flex-end; }
.btn { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem 1rem; border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all .15s; }
.btn--ghost   { background: transparent; color: #64748b; border-color: #334155; }
.btn--ghost:hover { color: #cbd5e1; }
.btn--approve { background: rgba(34,197,94,.15); color: #4ade80; border-color: rgba(34,197,94,.3); }
.btn--approve:hover { background: rgba(34,197,94,.3); }
.btn--danger  { background: rgba(239,68,68,.15);  color: #f87171; border-color: rgba(239,68,68,.3); }
.btn--danger:hover  { background: rgba(239,68,68,.3); }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity .2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
