<script setup>
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import AuditLogViewer     from '@/Components/Audit/AuditLogViewer.vue'
import SecurityAlertsPanel from '@/Components/Audit/SecurityAlertsPanel.vue'
import ActivityTimeline   from '@/Components/Audit/ActivityTimeline.vue'
import DataAccessLog      from '@/Components/Audit/DataAccessLog.vue'

defineProps({
  users: { type: Array, default: () => [] },
})

const activeTab = ref('logs')

const tabs = [
  { id: 'logs',     label: '📋 All Logs' },
  { id: 'security', label: '🛡️ Security Alerts' },
  { id: 'access',   label: '🔍 Data Access' },
  { id: 'timeline', label: '🕐 Timeline' },
]
</script>

<template>
  <Head title="Audit Logs" />

  <AuthenticatedLayout>
    <template #header>
      <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
          <h1 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin:0;">
            🔍 Audit Trail & Compliance Logs
          </h1>
          <p style="font-size:0.78rem; color:#94a3b8; margin-top:2px;">
            Immutable audit logs for all system activities — Administrator only
          </p>
        </div>
        <a
          href="/audit-logs/privacy-report"
          style="padding:9px 16px; background: linear-gradient(135deg,#1e40af,#1565c0);
                 color:white; border-radius:10px; font-weight:700; font-size:0.8rem;
                 text-decoration:none; box-shadow:0 4px 12px rgba(30,64,175,0.3);"
        >
          📄 Privacy Report (PDF)
        </a>
      </div>
    </template>

    <div class="audit-page">
      <!-- Tab bar -->
      <div class="tab-bar">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          class="tab-btn"
          :class="{ active: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Panels -->
      <div class="tab-panel" v-show="activeTab === 'logs'">
        <AuditLogViewer :users="users" />
      </div>

      <div class="tab-panel" v-show="activeTab === 'security'">
        <div class="two-col">
          <SecurityAlertsPanel />
        </div>
      </div>

      <div class="tab-panel" v-show="activeTab === 'access'">
        <DataAccessLog />
      </div>

      <div class="tab-panel" v-show="activeTab === 'timeline'">
        <ActivityTimeline />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.audit-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.tab-bar {
  display: flex;
  gap: 4px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 4px;
  width: fit-content;
}

.tab-btn {
  padding: 8px 18px;
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

.tab-panel { animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.two-col { max-width: 700px; }
</style>
