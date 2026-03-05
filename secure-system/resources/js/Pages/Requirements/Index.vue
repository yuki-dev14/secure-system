<template>
  <Head title="Requirements" />

  <AuthenticatedLayout>
    <template #header>
      <div class="page-header">
        <div>
          <h1 class="page-title">Requirements</h1>
          <p class="page-breadcrumb">
            {{ beneficiary.family_head_name }}
            <span class="breadcrumb-sep">·</span>
            <span class="breadcrumb-bin">BIN: {{ beneficiary.bin }}</span>
            <span class="breadcrumb-sep">·</span>
            {{ beneficiary.municipality }}
          </p>
        </div>
        <div class="header-actions">
          <!-- Eligibility pill (shows immediately from SSR) -->
          <div
            v-if="eligibility"
            :class="['elig-pill', eligibility.eligible ? 'elig-pill--ok' : 'elig-pill--no']"
            id="eligibility-status-pill"
          >
            {{ eligibility.eligible ? '✅ Eligible' : '⛔ Not Eligible' }}
          </div>
          <button
            v-if="canUpload"
            class="btn-upload-trigger"
            id="open-upload-panel-btn"
            @click="showUpload = !showUpload"
          >
            <span>{{ showUpload ? '✕ Close Upload' : '+ Upload Document' }}</span>
          </button>
        </div>
      </div>
    </template>

    <!-- ── Tab Bar ── -->
    <div class="tab-bar" id="requirements-tab-bar">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        :id="`tab-${tab.id}`"
        :class="['tab-btn', { 'tab-btn--active': activeTab === tab.id }]"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
        <span v-if="tab.badge" :class="['tab-badge', tab.badgeClass]">{{ tab.badge }}</span>
      </button>
    </div>

    <div class="requirements-page">

      <!-- ══════════════ TAB: PROGRESS ══════════════ -->
      <template v-if="activeTab === 'progress'">
        <div class="layout-full">

          <!-- Requirement Progress checklist -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">📋 4Ps Document Checklist</h2>
              <div class="card-meta">
                <span class="meta-pct"
                  :class="(completionStatus?.completion_percentage ?? 0) === 100 ? 'text-green' : 'text-muted'"
                >
                  {{ completionStatus?.completion_percentage ?? 0 }}% complete
                </span>
                <button class="btn-refresh" id="refresh-progress-btn" @click="refreshAll" :disabled="loadingCompletion">
                  {{ loadingCompletion ? '…' : '↻' }}
                </button>
              </div>
            </div>
            <div class="card-padded">
              <RequirementProgress
                :items="completionStatus?.items ?? []"
                :completion-pct="completionStatus?.completion_percentage ?? 0"
                :eligible="eligibilityData?.eligible ?? false"
                :blocking-reasons="eligibilityData?.blocking_reasons ?? []"
                :can-upload="canUpload"
                :loading="loadingCompletion"
                @view-document="openPreviewFromItem"
                @upload="onQuickUpload"
              />
            </div>
          </div>

          <!-- Compliance Integration — right of checklist on wide screens -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">🔗 Compliance Integration</h2>
            </div>
            <div class="card-padded">
              <ComplianceIntegration
                :overall-status="complianceStatus"
                :education-pct="educationPct"
                :health-pct="healthPct"
                :fds-pct="fdsPct"
                :items="completionStatus?.items ?? []"
                :missing-requirements="eligibilityData?.missing_items ?? []"
                :blocking-reasons="eligibilityData?.blocking_reasons ?? []"
                :can-upload="canUpload"
                :can-approve="canApprove"
                @upload="onQuickUpload"
              />
            </div>
          </div>
        </div>
      </template>

      <!-- ══════════════ TAB: DOCUMENTS ══════════════ -->
      <template v-if="activeTab === 'documents'">
        <div class="layout-two-col">
          <!-- Left -->
          <div class="layout-left">

            <!-- Status Summary -->
            <div class="card">
              <div class="card-header">
                <h2 class="card-title">Document Status</h2>
              </div>
              <div class="card-padded">
                <RequirementStatus
                  :grouped="grouped"
                  :missing-types="missingTypes"
                  :can-upload="canUpload"
                  @quick-upload="onQuickUpload"
                />
              </div>
            </div>

            <!-- Expiration Alerts -->
            <div v-if="expiringDocs.length" class="card card--alert">
              <div class="card-header">
                <h2 class="card-title">⚠ Expiration Alerts</h2>
                <span class="alert-count">{{ expiringDocs.length }}</span>
              </div>
              <div class="card-padded">
                <ExpirationAlert
                  :documents="expiringDocs"
                  :can-upload="canUpload"
                  @resubmit="onQuickUpload"
                />
              </div>
            </div>

          </div>

          <!-- Right -->
          <div class="layout-right">

            <!-- Upload Panel -->
            <Transition name="slide-down">
              <div v-if="showUpload && canUpload" class="card card--upload">
                <div class="card-header">
                  <h2 class="card-title">Upload Document</h2>
                  <button class="btn-close-upload" @click="showUpload = false" id="close-upload-panel-btn">✕</button>
                </div>
                <div class="card-padded">
                  <DocumentUpload
                    :beneficiary-id="beneficiary.id"
                    @uploaded="onUploaded"
                  />
                </div>
              </div>
            </Transition>

            <!-- Bulk Actions (approvers/admin only) -->
            <div v-if="(canApprove || isAdmin) && allRequirements.length" class="card">
              <div class="card-header">
                <h2 class="card-title">Bulk Actions</h2>
              </div>
              <div class="card-padded">
                <BulkDocumentActions
                  :documents="allRequirements"
                  :can-approve="canApprove"
                  :is-admin="isAdmin"
                  @approved="refreshAll"
                  @deleted="refreshAll"
                  @refresh="refreshAll"
                />
              </div>
            </div>

            <!-- Requirements Table -->
            <div class="card">
              <div class="card-header">
                <h2 class="card-title">All Submitted Documents</h2>
                <div class="card-meta">
                  <span class="meta-count">{{ allRequirements.length }} total</span>
                  <button
                    class="btn-refresh"
                    id="refresh-requirements-btn"
                    @click="refreshAll"
                    :disabled="loading"
                  >{{ loading ? '…' : '↻' }}</button>
                </div>
              </div>

              <div v-if="loading" class="loading-state">
                <div class="spinner-lg"></div>
                <p>Loading documents…</p>
              </div>
              <div v-else class="card-padded">
                <RequirementsList
                  :requirements="allRequirements"
                  :can-approve="canApprove"
                  :is-admin="isAdmin"
                  @preview="openPreview"
                  @approve="openApproval($event, 'approve')"
                  @reject="openApproval($event, 'reject')"
                  @delete="deleteRequirement"
                  @bulk-approve="doBulkApprove"
                />
              </div>
            </div>

          </div>
        </div>
      </template>

    </div><!-- .requirements-page -->

    <!-- ── Modals ── -->
    <RequirementPreview
      v-model="previewOpen"
      :requirement="selectedReq"
      :can-approve="canApprove"
      @approve="openApproval($event, 'approve')"
      @reject="openApproval($event, 'reject')"
    />

    <RequirementApproval
      v-model="approvalOpen"
      :requirement="selectedReq"
      :initial-mode="approvalMode"
      @approved="refreshAll"
      @rejected="refreshAll"
    />

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head }  from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout    from '@/Layouts/AuthenticatedLayout.vue'

import DocumentUpload         from '@/Components/Requirements/DocumentUpload.vue'
import RequirementsList       from '@/Components/Requirements/RequirementsList.vue'
import RequirementPreview     from '@/Components/Requirements/RequirementPreview.vue'
import RequirementApproval    from '@/Components/Requirements/RequirementApproval.vue'
import RequirementStatus      from '@/Components/Requirements/RequirementStatus.vue'
import ExpirationAlert        from '@/Components/Requirements/ExpirationAlert.vue'
import BulkDocumentActions    from '@/Components/Requirements/BulkDocumentActions.vue'
import RequirementProgress    from '@/Components/Requirements/RequirementProgress.vue'
import ComplianceIntegration  from '@/Components/Requirements/ComplianceIntegration.vue'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
  beneficiary:      { type: Object,  required: true },
  requirementTypes: { type: Array,   default: () => [] },
  canApprove:       { type: Boolean, default: false },
  isAdmin:          { type: Boolean, default: false },
  eligibility:      { type: Object,  default: null },  // server-side pre-loaded
})

// ── Auth ──────────────────────────────────────────────────────────────────────
const canUpload = computed(() => props.isAdmin || !props.canApprove)

// ── Tab state ─────────────────────────────────────────────────────────────────
const activeTab = ref('progress')

const tabs = computed(() => [
  { id: 'progress',  label: '📋 Progress & Compliance', badge: null },
  {
    id: 'documents', label: '📁 Documents',
    badge: pendingCount.value || null,
    badgeClass: 'tab-badge--pending',
  },
])

// ── State ─────────────────────────────────────────────────────────────────────
const loading           = ref(false)
const loadingCompletion = ref(false)
const showUpload        = ref(false)
const grouped           = ref([])
const missingTypes      = ref([])
const completionStatus  = ref(null)  // from /requirements/completion/{id}
const eligibilityData   = ref(props.eligibility)  // pre-loaded from Inertia; refreshed client-side
const complianceCache   = ref(null)  // from compliance_summary_cache

const previewOpen  = ref(false)
const approvalOpen = ref(false)
const approvalMode = ref('approve')
const selectedReq  = ref(null)

// ── Derived ───────────────────────────────────────────────────────────────────
const allRequirements = computed(() => grouped.value.flatMap(g => g.documents ?? []))

const pendingCount = computed(() =>
  allRequirements.value.filter(r => r.approval_status === 'pending').length
)

const expiringDocs = computed(() =>
  allRequirements.value.filter(r =>
    r.expiration_date && (r.is_expired || (r.days_until_expiry != null && r.days_until_expiry <= 30))
  )
)

// Compliance dimension scores from cache or default 0
const complianceStatus = computed(() => complianceCache.value?.overall_compliance_status ?? 'non_compliant')
const educationPct     = computed(() => complianceCache.value?.education_compliance_percentage ?? 0)
const healthPct        = computed(() => complianceCache.value?.health_compliance_percentage ?? 0)
const fdsPct           = computed(() => complianceCache.value?.fds_compliance_percentage ?? 0)

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => refreshAll())

// ── Data loading ──────────────────────────────────────────────────────────────
const refreshAll = async () => {
  await Promise.all([loadDocuments(), loadCompletion(), loadComplianceCache()])
}

const loadDocuments = async () => {
  loading.value = true
  try {
    const res = await axios.get(`/requirements/list/${props.beneficiary.id}`)
    if (res.data.success) {
      grouped.value      = res.data.grouped ?? []
      missingTypes.value = res.data.missing_types ?? []
    }
  } catch (e) {
    console.error('Failed to load requirements:', e)
  } finally {
    loading.value = false
  }
}

const loadCompletion = async () => {
  loadingCompletion.value = true
  try {
    const res = await axios.get(`/requirements/completion/${props.beneficiary.id}`)
    if (res.data.success) {
      completionStatus.value = res.data.completion_status
      eligibilityData.value  = res.data.eligibility
    }
  } catch (e) {
    console.error('Failed to load completion status:', e)
  } finally {
    loadingCompletion.value = false
  }
}

const loadComplianceCache = async () => {
  try {
    const res = await axios.get(`/compliance/cache/${props.beneficiary.id}`)
    if (res.data.success) complianceCache.value = res.data.cache
  } catch (_) {
    // Compliance cache endpoint may not exist yet — gracefully ignore
  }
}

// ── Actions ───────────────────────────────────────────────────────────────────
const onUploaded = () => {
  refreshAll()
  showUpload.value = false
}

const onQuickUpload = (type) => {
  showUpload.value = true
  activeTab.value  = 'documents'
}

const openPreview = (req) => {
  selectedReq.value = req
  previewOpen.value = true
}

const openPreviewFromItem = (item) => {
  if (item.document) {
    selectedReq.value = item.document
    previewOpen.value = true
  } else {
    onQuickUpload(item.type)
  }
}

const openApproval = (req, mode = 'approve') => {
  selectedReq.value  = req
  approvalMode.value = mode
  previewOpen.value  = false
  approvalOpen.value = true
}

const deleteRequirement = async (req) => {
  if (!confirm(`Delete document "${req.file_name}"? This cannot be undone.`)) return
  try {
    await axios.delete(`/requirements/${req.id}`)
    refreshAll()
  } catch (e) { console.error('Delete failed:', e) }
}

const doBulkApprove = async (ids) => {
  try {
    await axios.post('/requirements/bulk-approve', { requirement_ids: ids })
    refreshAll()
  } catch (e) { console.error('Bulk approve failed:', e) }
}
</script>

<style scoped>
/* ── Page Layout ── */
.requirements-page { padding: 1.25rem 1.5rem; min-height: calc(100vh - 160px); }

.layout-full {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
  align-items: start;
}
@media (max-width: 1100px) { .layout-full { grid-template-columns: 1fr; } }

.layout-two-col {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 1.25rem;
  align-items: start;
}
@media (max-width: 900px) { .layout-two-col { grid-template-columns: 1fr; } }

.layout-left  { display: flex; flex-direction: column; gap: 1.1rem; }
.layout-right { display: flex; flex-direction: column; gap: 1.1rem; }

/* ── Page Header ── */
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}
.page-title      { color: #f1f5f9; font-size: 1.35rem; font-weight: 800; margin: 0; }
.page-breadcrumb { color: #64748b; font-size: .83rem; margin: .2rem 0 0; }
.breadcrumb-sep  { margin: 0 .4rem; }
.breadcrumb-bin  { color: #6366f1; font-weight: 600; }

.header-actions { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

.elig-pill {
  padding: .35rem .9rem; border-radius: 9999px; font-size: .8rem; font-weight: 700;
  border: 1px solid transparent;
}
.elig-pill--ok { background: rgba(20,83,45,.2); border-color: rgba(34,197,94,.3); color: #4ade80; }
.elig-pill--no { background: rgba(127,29,29,.2); border-color: rgba(239,68,68,.3); color: #f87171; }

.btn-upload-trigger {
  background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff;
  border: none; border-radius: 10px; padding: .55rem 1.15rem;
  font-size: .88rem; font-weight: 700; cursor: pointer;
  transition: all .2s; box-shadow: 0 4px 15px rgba(99,102,241,.3);
}
.btn-upload-trigger:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,.45); }

/* ── Tab Bar ── */
.tab-bar {
  display: flex; gap: 0; padding: 0 1.5rem;
  border-bottom: 1px solid #1e293b; background: rgba(15,23,42,.6);
  backdrop-filter: blur(8px);
}
.tab-btn {
  display: flex; align-items: center; gap: .45rem;
  padding: .75rem 1.25rem; font-size: .875rem; font-weight: 600; color: #64748b;
  background: transparent; border: none; border-bottom: 2px solid transparent;
  cursor: pointer; transition: all .2s; white-space: nowrap;
}
.tab-btn:hover { color: #94a3b8; }
.tab-btn--active { color: #a5b4fc; border-bottom-color: #6366f1; }

.tab-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 1.3rem; height: 1.3rem; border-radius: 50%;
  font-size: .68rem; font-weight: 800;
}
.tab-badge--pending { background: #78350f33; color: #fbbf24; }

/* ── Cards ── */
.card {
  background: rgba(15,23,42,.8); border: 1px solid #1e293b;
  border-radius: 14px; overflow: hidden;
  backdrop-filter: blur(10px); box-shadow: 0 4px 24px rgba(0,0,0,.25);
}
.card--upload { border-color: rgba(99,102,241,.3); }
.card--alert  { border-color: rgba(239,68,68,.25); }

.card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: .95rem 1.25rem; border-bottom: 1px solid #1e293b;
}
.card-title  { color: #f1f5f9; font-size: .95rem; font-weight: 700; margin: 0; }
.card-meta   { display: flex; align-items: center; gap: .6rem; }
.card-padded { padding: 1.25rem; }
.meta-count  { color: #64748b; font-size: .8rem; }
.meta-pct    { font-size: .82rem; font-weight: 700; }
.text-green  { color: #4ade80; }
.text-muted  { color: #64748b; }
.alert-count {
  background: rgba(239,68,68,.15); color: #f87171; border: 1px solid rgba(239,68,68,.3);
  border-radius: 9999px; padding: .1rem .55rem; font-size: .72rem; font-weight: 700;
}

.btn-refresh {
  background: transparent; border: 1px solid #334155; color: #64748b;
  border-radius: 7px; width: 1.8rem; height: 1.8rem; cursor: pointer;
  font-size: 1rem; transition: all .15s; display: flex; align-items: center; justify-content: center;
}
.btn-refresh:hover:not(:disabled) { color: #6366f1; border-color: #6366f1; }
.btn-refresh:disabled { opacity: .4; }
.btn-close-upload {
  background: rgba(30,41,59,.8); border: 1px solid #334155; color: #94a3b8;
  border-radius: 7px; padding: .2rem .55rem; font-size: .85rem;
  cursor: pointer; transition: all .15s;
}
.btn-close-upload:hover { color: #f87171; border-color: #ef4444; }

/* Loading */
.loading-state {
  display: flex; flex-direction: column; align-items: center; gap: .85rem;
  padding: 2.5rem; color: #475569; font-size: .9rem;
}
.spinner-lg {
  width: 2rem; height: 2rem; border: 3px solid #1e293b; border-top-color: #6366f1;
  border-radius: 50%; animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Slide-down */
.slide-down-enter-active, .slide-down-leave-active {
  transition: max-height .35s ease, opacity .3s ease;
  overflow: hidden; max-height: 700px;
}
.slide-down-enter-from, .slide-down-leave-to { max-height: 0; opacity: 0; }
</style>
