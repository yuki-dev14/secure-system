<template>
  <div class="alerts-panel">
    <div class="panel-header">
      <div class="ph-left">
        <div class="ph-title">🛡️ Security Alerts</div>
        <span v-if="unackedCount" class="alert-count-badge">{{ unackedCount }}</span>
      </div>
      <button class="btn-refresh-sm" @click="load" :disabled="loading">⟳</button>
    </div>

    <div v-if="loading" class="loading-state">
      <div v-for="i in 3" :key="i" class="skel-row"></div>
    </div>

    <div v-else-if="!alerts.length" class="safe-state">
      <div class="safe-icon">✅</div>
      <div class="safe-text">No active security alerts</div>
    </div>

    <div v-else class="alerts-list">
      <div
        v-for="(alert, idx) in alerts"
        :key="idx"
        class="alert-item"
        :class="'sev-' + alert.severity"
      >
        <div class="ai-left">
          <span class="sev-icon">{{ severityIcon(alert.severity) }}</span>
        </div>
        <div class="ai-body">
          <div class="ai-type">{{ formatType(alert.type) }}</div>
          <div class="ai-message">{{ alert.message }}</div>
          <div class="ai-meta">
            <span v-if="alert.ip">IP: {{ alert.ip }}</span>
            <span v-if="alert.count">Count: {{ alert.count }}</span>
            <span class="ai-ts">{{ formatTs(alert.detected_at) }}</span>
          </div>
        </div>
        <div class="ai-right">
          <span class="sev-badge" :class="'badge-' + alert.severity">{{ alert.severity }}</span>
          <button
            v-if="alert.log_id"
            class="btn-ack"
            @click="acknowledge(alert.log_id, idx)"
            title="Acknowledge alert"
          >
            ✓
          </button>
        </div>
      </div>
    </div>

    <div v-if="alerts.length" class="panel-footer">
      <a href="/audit-logs/page" class="view-all-link">View full audit log →</a>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const alerts  = ref([])
const loading = ref(false)
let   pollInterval = null

const unackedCount = computed(() =>
  alerts.value.filter(a => !a.acknowledged).length
)

async function load () {
  loading.value = true
  try {
    const res = await axios.get('/audit-logs/security-alerts')
    alerts.value = res.data.alerts ?? []
  } catch (e) {
    // Non-admin or network error — silently ignore
  } finally {
    loading.value = false
  }
}

async function acknowledge (logId, idx) {
  try {
    await axios.post(`/audit-logs/${logId}/acknowledge`)
    alerts.value.splice(idx, 1)
  } catch (e) {
    console.error('Acknowledge failed', e)
  }
}

function severityIcon (s) {
  return { critical: '🚨', high: '🔴', medium: '🟡', low: '🟢' }[s] ?? '⚪'
}

function formatType (type) {
  return (type ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function formatTs (ts) {
  if (!ts) return ''
  const d = new Date(ts)
  const diff = Math.floor((Date.now() - d) / 1000)
  if (diff < 60)    return `${diff}s ago`
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return d.toLocaleDateString('en-PH')
}

onMounted(() => {
  load()
  // Auto-refresh every 2 minutes
  pollInterval = setInterval(load, 120_000)
})

onUnmounted(() => clearInterval(pollInterval))
</script>

<style scoped>
.alerts-panel { background: white; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }

.panel-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 13px 16px;
  background: #f8fafc; border-bottom: 1px solid #e2e8f0;
}
.ph-left { display: flex; align-items: center; gap: 8px; }
.ph-title { font-weight: 800; font-size: 0.88rem; color: #0f172a; }
.alert-count-badge {
  background: #dc2626; color: white;
  font-size: 0.68rem; font-weight: 900;
  padding: 1px 7px; border-radius: 999px;
  min-width: 18px; text-align: center;
}

.btn-refresh-sm {
  width: 28px; height: 28px; border-radius: 7px;
  border: 1px solid #e2e8f0; background: white;
  cursor: pointer; font-size: 0.85rem; color: #64748b;
  transition: all 0.15s;
}
.btn-refresh-sm:hover:not(:disabled) { background: #eff6ff; color: #1d4ed8; border-color: #3b82f6; }
.btn-refresh-sm:disabled { opacity: 0.5; }

.loading-state { padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.skel-row { height: 56px; background: #f1f5f9; border-radius: 8px; animation: pulse 1.2s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

.safe-state { padding: 28px; text-align: center; }
.safe-icon { font-size: 2rem; margin-bottom: 6px; }
.safe-text { font-size: 0.82rem; color: #94a3b8; }

.alerts-list { display: flex; flex-direction: column; max-height: 400px; overflow-y: auto; }

.alert-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.1s;
}
.alert-item:hover { background: #f8fafc; }
.alert-item:last-child { border-bottom: none; }

.alert-item.sev-critical { border-left: 4px solid #dc2626; }
.alert-item.sev-high     { border-left: 4px solid #ea580c; }
.alert-item.sev-medium   { border-left: 4px solid #d97706; }
.alert-item.sev-low      { border-left: 4px solid #65a30d; }

.ai-left { flex-shrink: 0; font-size: 1.1rem; margin-top: 1px; }
.ai-body { flex: 1; }
.ai-type { font-size: 0.78rem; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
.ai-message { font-size: 0.75rem; color: #475569; line-height: 1.5; margin-bottom: 4px; }
.ai-meta { display: flex; gap: 10px; font-size: 0.68rem; color: #94a3b8; flex-wrap: wrap; }
.ai-ts { margin-left: auto; }

.ai-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }
.sev-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.badge-critical { background: #fee2e2; color: #b91c1c; }
.badge-high     { background: #ffedd5; color: #c2410c; }
.badge-medium   { background: #fef9c3; color: #92400e; }
.badge-low      { background: #f0fdf4; color: #15803d; }

.btn-ack {
  width: 22px; height: 22px; border-radius: 50%;
  background: #f0fdf4; border: 1px solid #86efac;
  color: #15803d; font-size: 0.7rem;
  cursor: pointer; font-weight: 900;
  transition: all 0.15s;
  display: flex; align-items: center; justify-content: center;
}
.btn-ack:hover { background: #dcfce7; }

.panel-footer { padding: 10px 16px; border-top: 1px solid #f1f5f9; text-align: right; }
.view-all-link { font-size: 0.75rem; font-weight: 700; color: #1d4ed8; text-decoration: none; }
.view-all-link:hover { text-decoration: underline; }
</style>
