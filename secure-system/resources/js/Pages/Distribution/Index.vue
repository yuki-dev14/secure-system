<script setup>
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import DistributionList from '@/Components/Distribution/DistributionList.vue'
import ReconciliationDashboard from '@/Components/Distribution/ReconciliationDashboard.vue'
import BulkDistributionUpload from '@/Components/Distribution/BulkDistributionUpload.vue'
import DistributionReceipt from '@/Components/Distribution/DistributionReceipt.vue'

const props = defineProps({
  canRecord:    Boolean,
  canReconcile: Boolean,
  canBulk:      Boolean,
  isAdmin:      Boolean,
  currentUser:  Object,
})

const activeTab     = ref('list')
const selectedDist  = ref(null)
const showReceipt   = ref(false)

const tabs = [
  { id: 'list',      label: '📋 Distributions', show: true },
  { id: 'reconcile', label: '⚖️ Reconciliation', show: props.canReconcile },
  { id: 'bulk',      label: '📤 Bulk Upload',    show: props.canBulk },
]

function openReceipt (dist) {
  selectedDist.value = dist
  showReceipt.value  = true
}
</script>

<template>
  <Head title="Cash Grant Distributions" />

  <AuthenticatedLayout>
    <template #header>
      <div class="page-header-slot">
        <div>
          <h1 class="page-h1">💰 Cash Grant Distributions</h1>
          <p class="page-desc">Manage, record, and reconcile cash grant payouts</p>
        </div>
        <a
          v-if="canRecord"
          href="/beneficiaries"
          class="btn-new-dist"
        >
          + New Distribution
        </a>
      </div>
    </template>

    <div class="dist-page">
      <!-- Tabs -->
      <div class="tab-bar">
        <button
          v-for="tab in tabs.filter(t => t.show)"
          :key="tab.id"
          class="tab-btn"
          :class="{ active: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Tab content -->
      <div class="tab-content">
        <!-- List tab -->
        <div v-if="activeTab === 'list'" class="tab-panel">
          <DistributionList @view="openReceipt" />
        </div>

        <!-- Reconciliation tab -->
        <div v-if="activeTab === 'reconcile' && canReconcile" class="tab-panel">
          <ReconciliationDashboard />
        </div>

        <!-- Bulk upload tab -->
        <div v-if="activeTab === 'bulk' && canBulk" class="tab-panel">
          <BulkDistributionUpload @imported="activeTab = 'list'" />
        </div>
      </div>
    </div>

    <!-- Receipt slide-over -->
    <Teleport to="body">
      <div
        v-if="showReceipt && selectedDist"
        class="receipt-overlay"
        @click.self="showReceipt = false"
      >
        <div class="receipt-panel">
          <div class="panel-close-bar">
            <span class="panel-title">Distribution Receipt</span>
            <button class="btn-close" @click="showReceipt = false">✕ Close</button>
          </div>
          <div class="panel-body">
            <DistributionReceipt :distribution="selectedDist" />
          </div>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>

<style scoped>
.page-header-slot {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.page-h1   { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0; }
.page-desc { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }

.btn-new-dist {
  padding: 9px 20px;
  background: linear-gradient(135deg, #1e40af, #1565c0);
  color: white;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.15s;
  box-shadow: 0 4px 14px rgba(30, 64, 175, 0.35);
}
.btn-new-dist:hover { opacity: 0.9; transform: translateY(-1px); }

.dist-page {
  max-width: 1300px;
  margin: 0 auto;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tab-bar {
  display: flex;
  gap: 4px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 4px;
}

.tab-btn {
  padding: 9px 20px;
  border: none;
  border-radius: 9px;
  background: transparent;
  color: #64748b;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.tab-btn.active {
  background: linear-gradient(135deg, #1e40af, #1565c0);
  color: white;
  box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
}
.tab-btn:hover:not(.active) { background: #f1f5f9; color: #1e293b; }

.tab-content { flex: 1; }
.tab-panel   { animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Receipt slide-over */
.receipt-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 9000;
  display: flex;
  justify-content: flex-end;
}

.receipt-panel {
  width: min(640px, 95vw);
  height: 100%;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  box-shadow: -8px 0 40px rgba(0,0,0,0.2);
  animation: slideRight 0.25s ease;
}
@keyframes slideRight { from { transform: translateX(100%); } to { transform: none; } }

.panel-close-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: white;
  border-bottom: 1px solid #e2e8f0;
}
.panel-title { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
.btn-close {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 6px 14px;
  font-size: 0.78rem;
  color: #475569;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.15s;
}
.btn-close:hover { background: #fee2e2; color: #dc2626; border-color: #fecaca; }

.panel-body { flex: 1; overflow-y: auto; padding: 20px; }
</style>
