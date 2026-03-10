<script setup>
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import DistributionApprovalForm from '@/Components/Distribution/DistributionApprovalForm.vue'

const props = defineProps({
  beneficiary: Object,
  calculation: Object,
  summary:     Object,
  canApprove:  Boolean,
  isAdmin:     { type: Boolean, default: false },
})

function onDistributed (data) {
  setTimeout(() => {
    router.visit('/distribution/page')
  }, 3000)
}
</script>

<template>
  <Head :title="`Distribution — ${beneficiary.family_head_name}`" />

  <AuthenticatedLayout>
    <template #header>
      <div style="display:flex; align-items:center; gap:12px;">
        <button
          onclick="history.back()"
          style="padding:6px 14px; border:1px solid #e2e8f0; border-radius:8px;
                 background:white; color:#475569; font-weight:600; cursor:pointer;
                 font-size:0.82rem;"
        >
          ← Back
        </button>
        <div>
          <h1 style="font-size:1.2rem; font-weight:800; color:#0f172a; margin:0;">
            💰 Distribution Approval
          </h1>
          <p style="font-size:0.78rem; color:#94a3b8; margin-top:2px;">
            {{ beneficiary.family_head_name }} · BIN: {{ beneficiary.bin }}
          </p>
        </div>
      </div>
    </template>

    <div style="max-width:1000px; margin:0 auto; padding:24px 20px;">
      <DistributionApprovalForm
        :beneficiary="beneficiary"
        :calculation="calculation"
        :summary="summary"
        :can-approve="canApprove"
        :is-admin="isAdmin"
        @distributed="onDistributed"
      />
    </div>
  </AuthenticatedLayout>
</template>
