<template>
  <div class="requirements-list">
    <!-- Filters Bar -->
    <div class="filters-bar">
      <div class="filter-group">
        <label class="filter-label" for="filter-type">Type</label>
        <select id="filter-type" v-model="filter.type" class="filter-select">
          <option value="">All Types</option>
          <option v-for="t in requirementTypeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label" for="filter-status">Status</label>
        <select id="filter-status" v-model="filter.status" class="filter-select">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label" for="filter-sort">Sort</label>
        <select id="filter-sort" v-model="filter.sort" class="filter-select">
          <option value="desc">Newest First</option>
          <option value="asc">Oldest First</option>
        </select>
      </div>
    </div>

    <!-- Bulk Actions -->
    <div v-if="canApprove && selectedIds.length > 0" class="bulk-bar">
      <span class="bulk-bar__count">{{ selectedIds.length }} selected</span>
      <button id="bulk-approve-btn" class="btn btn--success btn--sm" @click="$emit('bulk-approve', selectedIds)">
        ✓ Bulk Approve
      </button>
      <button id="bulk-deselect-btn" class="btn btn--ghost btn--sm" @click="selectedIds = []">
        Deselect All
      </button>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
      <table class="req-table" id="requirements-table">
        <thead>
          <tr>
            <th v-if="canApprove" class="th-check">
              <input type="checkbox" :checked="allSelected" @change="toggleAll" id="select-all-checkbox" />
            </th>
            <th>Type</th>
            <th>File Name</th>
            <th>Size</th>
            <th>Submitted</th>
            <th>Submitted By</th>
            <th>Status</th>
            <th>Expires</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!filteredRequirements.length">
            <td :colspan="canApprove ? 9 : 8" class="empty-row">No documents found.</td>
          </tr>
          <tr
            v-for="req in paginatedRequirements"
            :key="req.id"
            :class="{ 'row--expired': req.is_expired }"
          >
            <td v-if="canApprove">
              <input
                type="checkbox"
                :value="req.id"
                v-model="selectedIds"
                :disabled="req.approval_status !== 'pending'"
                :id="`check-req-${req.id}`"
              />
            </td>
            <td>
              <span class="type-pill">{{ typeLabel(req.requirement_type) }}</span>
            </td>
            <td class="file-name-cell" :title="req.file_name">{{ req.file_name }}</td>
            <td class="text-muted">{{ req.file_size_human }}</td>
            <td class="text-muted" :title="req.submitted_at_fmt">{{ req.submitted_at_human }}</td>
            <td class="text-muted">{{ req.submitted_by?.name ?? '—' }}</td>
            <td>
              <span class="badge" :class="statusBadgeClass(req.approval_status)">
                {{ req.approval_status }}
              </span>
            </td>
            <td>
              <span v-if="req.expiration_date" :class="['expiry-text', req.is_expired ? 'expiry-text--expired' : (req.days_until_expiry <= 30 ? 'expiry-text--soon' : '')]">
                <template v-if="req.is_expired">⚠ Expired</template>
                <template v-else>{{ req.days_until_expiry }}d</template>
              </span>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <div class="actions-cell">
                <button
                  :id="`preview-btn-${req.id}`"
                  class="action-btn action-btn--view"
                  title="Preview"
                  @click="$emit('preview', req)"
                >👁</button>
                <a
                  :id="`download-btn-${req.id}`"
                  :href="req.download_url"
                  class="action-btn action-btn--download"
                  title="Download"
                  target="_blank"
                >⬇</a>
                <template v-if="canApprove && req.approval_status === 'pending'">
                  <button
                    :id="`approve-btn-${req.id}`"
                    class="action-btn action-btn--approve"
                    title="Approve"
                    @click="$emit('approve', req)"
                  >✓</button>
                  <button
                    :id="`reject-btn-${req.id}`"
                    class="action-btn action-btn--reject"
                    title="Reject"
                    @click="$emit('reject', req)"
                  >✕</button>
                </template>
                <button
                  v-if="isAdmin"
                  :id="`delete-btn-${req.id}`"
                  class="action-btn action-btn--delete"
                  title="Delete"
                  @click="$emit('delete', req)"
                >🗑</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="pagination">
      <button
        class="page-btn"
        :disabled="currentPage === 1"
        @click="currentPage--"
        id="prev-page-btn"
      >‹</button>
      <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <button
        class="page-btn"
        :disabled="currentPage === totalPages"
        @click="currentPage++"
        id="next-page-btn"
      >›</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  requirements: { type: Array, default: () => [] },
  canApprove:   { type: Boolean, default: false },
  isAdmin:      { type: Boolean, default: false },
})

defineEmits(['preview', 'approve', 'reject', 'delete', 'bulk-approve'])

const PER_PAGE = 10
const currentPage = ref(1)
const selectedIds = ref([])

const filter = ref({ type: '', status: '', sort: 'desc' })

watch(filter, () => { currentPage.value = 1; selectedIds.value = [] }, { deep: true })

const requirementTypeOptions = [
  { value: 'birth_certificate', label: 'Birth Certificate' },
  { value: 'school_record',     label: 'School Record' },
  { value: 'health_record',     label: 'Health Record' },
  { value: 'proof_of_income',   label: 'Proof of Income' },
  { value: 'valid_id',          label: 'Valid ID' },
  { value: 'other',             label: 'Other' },
]

const typeLabel = (v) => requirementTypeOptions.find(o => o.value === v)?.label ?? v

const filteredRequirements = computed(() => {
  let list = [...props.requirements]
  if (filter.value.type)   list = list.filter(r => r.requirement_type === filter.value.type)
  if (filter.value.status) list = list.filter(r => r.approval_status  === filter.value.status)
  list.sort((a, b) => {
    const diff = new Date(a.submitted_at) - new Date(b.submitted_at)
    return filter.value.sort === 'asc' ? diff : -diff
  })
  return list
})

const totalPages = computed(() => Math.ceil(filteredRequirements.value.length / PER_PAGE) || 1)

const paginatedRequirements = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filteredRequirements.value.slice(start, start + PER_PAGE)
})

const allSelected = computed(() => {
  const pending = paginatedRequirements.value.filter(r => r.approval_status === 'pending')
  return pending.length > 0 && pending.every(r => selectedIds.value.includes(r.id))
})

const toggleAll = () => {
  const pending = paginatedRequirements.value.filter(r => r.approval_status === 'pending').map(r => r.id)
  if (allSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !pending.includes(id))
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...pending])]
  }
}

const statusBadgeClass = (status) => ({
  'badge--pending':  status === 'pending',
  'badge--approved': status === 'approved',
  'badge--rejected': status === 'rejected',
})
</script>

<style scoped>
.requirements-list { font-family: 'Inter', sans-serif; }

/* Filters */
.filters-bar {
  display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;
}
.filter-group { display: flex; flex-direction: column; gap: .25rem; }
.filter-label { color: #64748b; font-size: .75rem; font-weight: 500; text-transform: uppercase; letter-spacing: .05em; }
.filter-select {
  background: rgba(15,23,42,.8); border: 1px solid #334155;
  border-radius: 6px; color: #cbd5e1; padding: .4rem .75rem;
  font-size: .85rem; outline: none; cursor: pointer;
}

/* Bulk Bar */
.bulk-bar {
  display: flex; align-items: center; gap: .75rem;
  background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.3);
  border-radius: 8px; padding: .5rem 1rem; margin-bottom: .75rem;
}
.bulk-bar__count { color: #a5b4fc; font-size: .85rem; font-weight: 600; flex: 1; }

/* Table */
.table-wrapper { overflow-x: auto; border-radius: 10px; border: 1px solid #1e293b; }
.req-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.req-table thead { background: rgba(15,23,42,.9); }
.req-table th {
  color: #64748b; font-weight: 600; text-transform: uppercase;
  letter-spacing: .05em; font-size: .72rem; padding: .75rem 1rem;
  text-align: left; white-space: nowrap;
}
.req-table td { padding: .7rem 1rem; border-top: 1px solid #1e293b; color: #cbd5e1; vertical-align: middle; }
.req-table tbody tr { transition: background .15s; }
.req-table tbody tr:hover { background: rgba(30,41,59,.5); }
.row--expired { background: rgba(127,29,29,.1) !important; }
.empty-row { text-align: center; color: #475569; padding: 2rem !important; }
.th-check { width: 2rem; }

.type-pill {
  background: rgba(99,102,241,.15); color: #a5b4fc;
  border-radius: 9999px; padding: .2rem .65rem;
  font-size: .75rem; font-weight: 600; white-space: nowrap;
}
.file-name-cell { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.text-muted { color: #475569 !important; }
.expiry-text { font-size: .78rem; font-weight: 600; }
.expiry-text--expired { color: #ef4444; }
.expiry-text--soon    { color: #f59e0b; }

/* Badges */
.badge { padding: .19rem .55rem; border-radius: 9999px; font-size: .72rem; font-weight: 600; text-transform: capitalize; }
.badge--pending  { background: #78350f22; color: #fbbf24; border: 1px solid #78350f55; }
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }

/* Actions */
.actions-cell { display: flex; gap: .35rem; }
.action-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 1.8rem; height: 1.8rem; border-radius: 6px; border: none;
  font-size: .9rem; cursor: pointer; transition: all .15s;
  text-decoration: none;
}
.action-btn--view     { background: rgba(99,102,241,.15); }
.action-btn--view:hover { background: rgba(99,102,241,.35); }
.action-btn--download { background: rgba(6,182,212,.15); }
.action-btn--download:hover { background: rgba(6,182,212,.35); }
.action-btn--approve  { background: rgba(34,197,94,.15); }
.action-btn--approve:hover { background: rgba(34,197,94,.35); }
.action-btn--reject   { background: rgba(239,68,68,.15); }
.action-btn--reject:hover { background: rgba(239,68,68,.35); }
.action-btn--delete   { background: rgba(100,116,139,.15); }
.action-btn--delete:hover { background: rgba(239,68,68,.2); }

/* Pagination */
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1rem; }
.page-btn {
  background: rgba(30,41,59,.8); border: 1px solid #334155; color: #94a3b8;
  border-radius: 6px; padding: .35rem .7rem; font-size: 1rem; cursor: pointer;
  transition: all .15s;
}
.page-btn:hover:not(:disabled) { border-color: #6366f1; color: #a5b4fc; }
.page-btn:disabled { opacity: .35; cursor: not-allowed; }
.page-info { color: #64748b; font-size: .83rem; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: .35rem; padding: .4rem 1rem; border-radius: 7px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; transition: all .15s; }
.btn--sm { padding: .3rem .75rem; font-size: .78rem; }
.btn--success { background: rgba(34,197,94,.2); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
.btn--success:hover { background: rgba(34,197,94,.35); }
.btn--ghost { background: transparent; color: #64748b; border: 1px solid #334155; }
.btn--ghost:hover { color: #cbd5e1; border-color: #475569; }
</style>
