<script setup>
/**
 * FamilyMembers/Index.vue  (route: beneficiaries/{id}/family-members)
 *
 * Full family-member management page:
 *  - Desktop table view + mobile card view
 *  - Inline "Add member" modal (single)
 *  - Batch builder (build list before submitting)
 *  - CSV import with template download
 *  - Edit modal
 *  - Delete confirmation
 *  - Relationship count summary strip
 */
import { ref, computed, reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FamilyMemberForm from '@/Components/FamilyMemberForm.vue';
import FamilyMemberCard from '@/Components/FamilyMemberCard.vue';

const props = defineProps({
    beneficiary: Object,
    members:     { type: Array, default: () => [] },
    counts:      { type: Object, default: () => ({}) },
    canManage:   Boolean,
});

// ── View mode ─────────────────────────────────────────────────────────────
const viewMode = ref('table'); // 'table' | 'cards'

// ── Modals ────────────────────────────────────────────────────────────────
const showAddModal    = ref(false);
const showEditModal   = ref(false);
const showDeleteModal = ref(false);
const showBatchPanel  = ref(false);
const showImportPanel = ref(false);
const submitting      = ref(false);
const memberToDelete  = ref(null);
const importErrors    = ref([]);

// ── Empty member template ─────────────────────────────────────────────────
const emptyMember = () => ({
    full_name: '', birthdate: '', gender: '',
    relationship_to_head: '', birth_certificate_no: '',
    school_enrollment_status: 'Not Applicable',
    health_center_registered: false,
});

// ── Single add ────────────────────────────────────────────────────────────
const newMember  = ref(emptyMember());
const addErrors  = ref({});

const openAddModal = () => {
    newMember.value = emptyMember();
    addErrors.value = {};
    showAddModal.value = true;
};

const submitAdd = () => {
    submitting.value = true;
    router.post(
        route('family-members.store', props.beneficiary.id),
        newMember.value,
        {
            onError: (e) => { addErrors.value = e; submitting.value = false; },
            onSuccess: () => { showAddModal.value = false; submitting.value = false; },
            onFinish: () => { submitting.value = false; },
        }
    );
};

// ── Edit ──────────────────────────────────────────────────────────────────
const editMember = ref(emptyMember());
const editErrors = ref({});
const editingId  = ref(null);

const openEdit = (member) => {
    editMember.value = { ...member };
    editingId.value  = member.id;
    editErrors.value = {};
    showEditModal.value = true;
};

const submitEdit = () => {
    submitting.value = true;
    router.put(
        route('family-members.update', editingId.value),
        editMember.value,
        {
            onError: (e) => { editErrors.value = e; submitting.value = false; },
            onSuccess: () => { showEditModal.value = false; submitting.value = false; },
            onFinish: () => { submitting.value = false; },
        }
    );
};

// ── Delete ────────────────────────────────────────────────────────────────
const openDelete = (member) => {
    memberToDelete.value = member;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    submitting.value = true;
    router.delete(route('family-members.destroy', memberToDelete.value.id), {
        onSuccess: () => { showDeleteModal.value = false; submitting.value = false; },
        onFinish:  () => { submitting.value = false; },
    });
};

// ── Batch builder ─────────────────────────────────────────────────────────
const batch = ref([emptyMember()]);
const batchErrors = ref({});

const addBatchRow = () => batch.value.push(emptyMember());
const removeBatchRow = (i) => {
    if (batch.value.length > 1) batch.value.splice(i, 1);
};

const submitBatch = () => {
    submitting.value = true;
    router.post(
        route('family-members.store', props.beneficiary.id),
        { members: batch.value },
        {
            onError: (e) => { batchErrors.value = e; submitting.value = false; },
            onSuccess: () => {
                showBatchPanel.value = false;
                batch.value = [emptyMember()];
                submitting.value = false;
            },
            onFinish: () => { submitting.value = false; },
        }
    );
};

// ── CSV Import ────────────────────────────────────────────────────────────
const csvFile = ref(null);
const importForm = useForm({ csv_file: null });

const onFileChange = (e) => { importForm.csv_file = e.target.files[0]; };

const submitImport = () => {
    importForm.post(route('family-members.import', props.beneficiary.id), {
        onSuccess: () => { showImportPanel.value = false; },
    });
};

const downloadTemplate = () => {
    const headers = ['full_name', 'birthdate', 'gender', 'relationship_to_head', 'birth_certificate_no', 'school_enrollment_status', 'health_center_registered'];
    const sample  = ['Juan Dela Cruz', '2008-05-12', 'Male', 'Child', '', 'Enrolled', 'true'];
    const csv = [headers.join(','), sample.join(',')].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'family_members_template.csv';
    a.click();
};

// ── Helpers ───────────────────────────────────────────────────────────────
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

const enrollClass = (status) => ({
    'Enrolled':       'badge-green',
    'Not Enrolled':   'badge-red',
    'Not Applicable': 'badge-gray',
}[status] ?? 'badge-gray');

const RELATIONSHIPS = ['Spouse', 'Child', 'Parent', 'Sibling', 'Other'];

const totalCount = computed(() => props.members.length);
const schoolAgeCount = computed(() => props.members.filter(m => m.is_school_age).length);
const healthMonCount  = computed(() => props.members.filter(m => m.needs_health_monitoring).length);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div class="hd-left">
                    <Link :href="route('beneficiaries.show', beneficiary.id)" class="back-link">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
                        {{ beneficiary.family_head_name }}
                    </Link>
                    <h1 class="page-title">Family Members</h1>
                    <p class="page-sub">{{ beneficiary.bin }} — manage household composition</p>
                </div>
                <!-- Toolbar -->
                <div v-if="canManage" class="hd-actions">
                    <button class="btn-ghost-sm" @click="showImportPanel = !showImportPanel">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a.75.75 0 01.75.75V5.5h2.69L10 2.058 6.56 5.5H9.25V1.75A.75.75 0 0110 1zm-4.5 6H13.5a1 1 0 010 2h-8a1 1 0 010-2zm-3 5a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 012.5 12zm15 0a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                        CSV Import
                    </button>
                    <button class="btn-ghost-sm" @click="showBatchPanel = !showBatchPanel">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                        Batch Add
                    </button>
                    <button id="add-member-btn" class="btn-primary" @click="openAddModal">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                        Add Member
                    </button>
                </div>
            </div>
        </template>

        <div class="page-wrap">
            <!-- ── Stat strip ─────────────────────────────────────────── -->
            <div class="stat-strip">
                <div class="sitem">
                    <span class="slabel">Total Members</span>
                    <span class="sval">{{ totalCount }}</span>
                </div>
                <div v-for="rel in RELATIONSHIPS" :key="rel" class="sitem">
                    <span class="slabel">{{ rel }}</span>
                    <span class="sval">{{ counts[rel] ?? 0 }}</span>
                </div>
                <div class="sitem accent-blue">
                    <span class="slabel">School-Age</span>
                    <span class="sval">{{ schoolAgeCount }}</span>
                </div>
                <div class="sitem accent-amber">
                    <span class="slabel">Health Monitor</span>
                    <span class="sval">{{ healthMonCount }}</span>
                </div>
            </div>

            <!-- ── CSV Import panel ───────────────────────────────────── -->
            <Transition name="slide-down">
                <div v-if="showImportPanel" class="panel import-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">CSV Bulk Import</h3>
                        <button class="panel-close" @click="showImportPanel = false">✕</button>
                    </div>
                    <p class="panel-desc">
                        Upload a CSV with these columns: <code>full_name, birthdate (YYYY-MM-DD), gender, relationship_to_head, birth_certificate_no, school_enrollment_status, health_center_registered (true/false)</code>
                    </p>
                    <div class="import-actions">
                        <button class="btn-ghost-sm" @click="downloadTemplate">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/></svg>
                            Download Template
                        </button>
                        <input id="csv-upload" type="file" accept=".csv,.txt" class="file-input" @change="onFileChange" />
                        <label for="csv-upload" class="btn-ghost-sm file-label">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zM10 8a.75.75 0 01.75.75v1.5h1.5a.75.75 0 010 1.5h-1.5v1.5a.75.75 0 01-1.5 0v-1.5h-1.5a.75.75 0 010-1.5h1.5v-1.5A.75.75 0 0110 8z" clip-rule="evenodd"/></svg>
                            {{ importForm.csv_file ? importForm.csv_file.name : 'Choose CSV' }}
                        </label>
                        <button
                            class="btn-primary"
                            :disabled="!importForm.csv_file || importForm.processing"
                            @click="submitImport"
                        >
                            Upload &amp; Import
                        </button>
                    </div>
                    <div v-if="importForm.errors.csv_file" class="import-error">{{ importForm.errors.csv_file }}</div>
                </div>
            </Transition>

            <!-- ── Batch builder panel ────────────────────────────────── -->
            <Transition name="slide-down">
                <div v-if="showBatchPanel" class="panel batch-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Batch — Add Multiple Members</h3>
                        <button class="panel-close" @click="showBatchPanel = false">✕</button>
                    </div>

                    <div v-for="(m, i) in batch" :key="i" class="batch-row">
                        <div class="batch-row-num">
                            <span class="row-num">{{ i + 1 }}</span>
                            <button
                                v-if="batch.length > 1"
                                class="row-del"
                                title="Remove row"
                                @click="removeBatchRow(i)"
                            >✕</button>
                        </div>
                        <div class="batch-form-wrap">
                            <FamilyMemberForm
                                v-model="batch[i]"
                                :errors="{}"
                                @submit="() => {}"
                                @cancel="() => {}"
                            />
                        </div>
                    </div>

                    <div class="batch-footer">
                        <button class="btn-ghost-sm" @click="addBatchRow">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                            Add Another Row
                        </button>
                        <div class="batch-right">
                            <span class="batch-count">{{ batch.length }} member{{ batch.length !== 1 ? 's' : '' }} to add</span>
                            <button class="btn-primary" :disabled="submitting" @click="submitBatch">
                                Submit All
                            </button>
                        </div>
                    </div>
                    <div v-if="Object.keys(batchErrors).length" class="import-error">
                        <p v-for="(msg, f) in batchErrors" :key="f">{{ msg }}</p>
                    </div>
                </div>
            </Transition>

            <!-- ── View toggle ────────────────────────────────────────── -->
            <div class="view-toggle-row">
                <div class="view-toggle">
                    <button :class="['vtb', viewMode === 'table' ? 'vtb-active' : '']" @click="viewMode = 'table'">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M.99 5.24A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119.01 5.24l.001 9.527a2.25 2.25 0 01-2.26 2.229l-13.5-.012A2.25 2.25 0 01.99 14.77l-.001-9.528zM8.25 8.005a.75.75 0 01.75-.75h2a.75.75 0 010 1.5H9a.75.75 0 01-.75-.75zm-.75 3.245a.75.75 0 01.75-.75h2a.75.75 0 010 1.5H9a.75.75 0 01-.75-.75zm-3-3.495A.75.75 0 015.25 7h.008a.75.75 0 010 1.5H5.25a.75.75 0 01-.75-.75zm0 3.495a.75.75 0 01.75-.745h.008a.745.745 0 010 1.49H5.25a.745.745 0 01-.75-.745z" clip-rule="evenodd"/></svg>
                        Table
                    </button>
                    <button :class="['vtb', viewMode === 'cards' ? 'vtb-active' : '']" @click="viewMode = 'cards'">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 4.25A2.25 2.25 0 014.25 2h2.5A2.25 2.25 0 019 4.25v2.5A2.25 2.25 0 016.75 9h-2.5A2.25 2.25 0 012 6.75v-2.5zm9 0A2.25 2.25 0 0113.25 2h2.5A2.25 2.25 0 0118 4.25v2.5A2.25 2.25 0 0115.75 9h-2.5A2.25 2.25 0 0111 6.75v-2.5zm-9 9A2.25 2.25 0 014.25 11h2.5A2.25 2.25 0 019 13.25v2.5A2.25 2.25 0 016.75 18h-2.5A2.25 2.25 0 012 15.75v-2.5zm9 0A2.25 2.25 0 0113.25 11h2.5A2.25 2.25 0 0118 13.25v2.5A2.25 2.25 0 0115.75 18h-2.5A2.25 2.25 0 0111 15.75v-2.5z"/></svg>
                        Cards
                    </button>
                </div>
                <span class="member-count-label">{{ totalCount }} household member{{ totalCount !== 1 ? 's' : '' }}</span>
            </div>

            <!-- ── Empty state ────────────────────────────────────────── -->
            <div v-if="members.length === 0" class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                </svg>
                <p>No family members registered yet.</p>
                <p class="empty-sub">Use "Add Member" to start building the household roster.</p>
                <button v-if="canManage" class="btn-primary" @click="openAddModal">Add First Member</button>
            </div>

            <!-- ── Table View ─────────────────────────────────────────── -->
            <div v-else-if="viewMode === 'table'" class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Relationship</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>School Status</th>
                            <th>Health Ctr.</th>
                            <th>PSA No.</th>
                            <th v-if="canManage">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in members" :key="m.id" class="data-row">
                            <td>
                                <div class="name-cell">
                                    <div class="row-avatar">{{ m.full_name.charAt(0).toUpperCase() }}</div>
                                    <div>
                                        <div class="row-name">{{ m.full_name }}</div>
                                        <div v-if="m.needs_health_monitoring" class="row-flag">⚕ Health monitoring</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ m.relationship_to_head }}</td>
                            <td>
                                <span class="age-pill">{{ m.age_label }}</span>
                            </td>
                            <td>{{ m.gender }}</td>
                            <td>
                                <span :class="['enroll-badge', enrollClass(m.school_enrollment_status)]">
                                    {{ m.school_enrollment_status }}
                                </span>
                            </td>
                            <td>
                                <span :class="['health-dot', m.health_center_registered ? 'hd-yes' : 'hd-no']">
                                    {{ m.health_center_registered ? '✓ Yes' : '✗ No' }}
                                </span>
                            </td>
                            <td class="mono-sm">{{ m.birth_certificate_no || '—' }}</td>
                            <td v-if="canManage">
                                <div class="row-actions">
                                    <button class="row-btn edit-row-btn" title="Edit" @click="openEdit(m)">
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z"/></svg>
                                    </button>
                                    <button class="row-btn del-row-btn" title="Remove" @click="openDelete(m)">
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4z" clip-rule="evenodd"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Card View ──────────────────────────────────────────── -->
            <div v-else class="cards-grid">
                <FamilyMemberCard
                    v-for="m in members"
                    :key="m.id"
                    :member="m"
                    :canManage="canManage"
                    @edit="openEdit"
                    @delete="openDelete"
                />
            </div>
        </div>

        <!-- ═══════════════════ MODALS ═══════════════════ -->

        <!-- Add Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
                <div class="modal-box">
                    <div class="modal-hdr">
                        <h2 class="modal-title">Add Family Member</h2>
                        <button class="modal-close" @click="showAddModal = false">✕</button>
                    </div>
                    <div class="modal-body">
                        <FamilyMemberForm
                            v-model="newMember"
                            :errors="addErrors"
                            :loading="submitting"
                            @submit="submitAdd"
                            @cancel="showAddModal = false"
                        />
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Edit Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
                <div class="modal-box">
                    <div class="modal-hdr">
                        <h2 class="modal-title">Edit — {{ editMember.full_name }}</h2>
                        <button class="modal-close" @click="showEditModal = false">✕</button>
                    </div>
                    <div class="modal-body">
                        <FamilyMemberForm
                            v-model="editMember"
                            :errors="editErrors"
                            :loading="submitting"
                            :is-edit="true"
                            @submit="submitEdit"
                            @cancel="showEditModal = false"
                        />
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Delete Confirm Modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
                <div class="modal-box modal-sm">
                    <div class="modal-hdr">
                        <h2 class="modal-title danger-title">Remove Member</h2>
                        <button class="modal-close" @click="showDeleteModal = false">✕</button>
                    </div>
                    <div class="modal-body">
                        <div class="delete-warn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                            <p>
                                Remove <strong>{{ memberToDelete?.full_name }}</strong> from this household?
                                All associated compliance records will also be deleted.
                            </p>
                        </div>
                        <div class="delete-actions">
                            <button class="btn-ghost-sm" @click="showDeleteModal = false">Cancel</button>
                            <button class="btn-danger" :disabled="submitting" @click="confirmDelete">
                                Yes, Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.page-wrap { display: flex; flex-direction: column; gap: 1.5rem; font-family: 'Inter', sans-serif; }

/* Header */
.page-hd { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.hd-left { display: flex; flex-direction: column; gap: 0.4rem; }
.back-link { display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; color: #64748b; text-decoration: none; transition: color 0.2s; }
.back-link svg { width: 15px; height: 15px; }
.back-link:hover { color: #a5b4fc; }
.page-title { font-size: 1.375rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.page-sub   { font-size: 0.8125rem; color: #64748b; margin: 0; }
.hd-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

/* Stat strip */
.stat-strip {
    display: flex; gap: 0; flex-wrap: wrap;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden;
}
.sitem {
    flex: 1; min-width: 100px; padding: 1rem 1.25rem;
    display: flex; flex-direction: column; gap: 0.2rem;
    border-right: 1px solid rgba(255,255,255,0.06);
}
.sitem:last-child { border-right: none; }
.slabel { font-size: 0.68rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.sval   { font-size: 1.25rem; font-weight: 700; color: #f1f5f9; }
.accent-blue .sval { color: #a5b4fc; }
.accent-amber .sval { color: #fcd34d; }

/* Panels */
.panel {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem; padding: 1.5rem;
}
.batch-panel { display: flex; flex-direction: column; gap: 1.25rem; }
.panel-header { display: flex; align-items: center; justify-content: space-between; }
.panel-title { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.panel-close { background: none; border: none; color: #64748b; font-size: 1.2rem; cursor: pointer; padding: 0 0.25rem; transition: color 0.2s; }
.panel-close:hover { color: #f87171; }
.panel-desc { font-size: 0.8125rem; color: #64748b; margin: 0; }
.panel-desc code { color: #a5b4fc; font-family: monospace; background: rgba(99,102,241,0.1); padding: 0.1rem 0.3rem; border-radius: 4px; }

/* CSV import */
.import-panel {}
.import-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.75rem; }
.file-input { position: absolute; opacity: 0; pointer-events: none; }
.file-label { cursor: pointer; }
.import-error { margin-top: 0.75rem; padding: 0.75rem 1rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 0.75rem; font-size: 0.8125rem; color: #fca5a5; }

/* Batch */
.batch-row {
    display: flex; gap: 1rem; align-items: flex-start;
    padding: 1rem; background: rgba(0,0,0,0.15);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 0.875rem;
}
.batch-row-num { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding-top: 0.5rem; }
.row-num { width: 28px; height: 28px; border-radius: 50%; background: rgba(99,102,241,0.2); color: #a5b4fc; font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.row-del { background: rgba(239,68,68,0.12); border: none; color: #fca5a5; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 0.7rem; transition: all 0.15s; }
.row-del:hover { background: rgba(239,68,68,0.25); }
.batch-form-wrap { flex: 1; }
.batch-footer { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.06); }
.batch-right { display: flex; align-items: center; gap: 0.75rem; }
.batch-count { font-size: 0.8125rem; color: #64748b; }

/* View toggle */
.view-toggle-row { display: flex; align-items: center; justify-content: space-between; }
.view-toggle { display: flex; gap: 0.25rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 0.625rem; padding: 0.25rem; }
.vtb {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.375rem 0.75rem; border-radius: 0.5rem;
    font-size: 0.8rem; font-weight: 600; color: #64748b;
    background: none; border: none; cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.15s;
}
.vtb svg { width: 14px; height: 14px; }
.vtb:hover { color: #94a3b8; }
.vtb-active { background: rgba(99,102,241,0.2); color: #a5b4fc; }
.member-count-label { font-size: 0.8125rem; color: #64748b; }

/* Table */
.table-wrap { overflow-x: auto; border-radius: 1rem; border: 1px solid rgba(255,255,255,0.07); }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th {
    padding: 0.875rem 1rem; text-align: left;
    font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;
    background: rgba(0,0,0,0.25); border-bottom: 1px solid rgba(255,255,255,0.07);
}
.data-table td { padding: 0.875rem 1rem; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
.data-row:hover td { background: rgba(99,102,241,0.04); }
.data-row:last-child td { border-bottom: none; }

.name-cell { display: flex; align-items: center; gap: 0.75rem; }
.row-avatar {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 700; color: white;
}
.row-name { font-weight: 600; color: #f1f5f9; }
.row-flag { font-size: 0.7rem; color: #fcd34d; margin-top: 2px; }
.age-pill {
    display: inline-block; padding: 0.2rem 0.55rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 700; color: #a5b4fc;
    background: rgba(99,102,241,0.15);
}
.enroll-badge {
    display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 700;
}
.badge-green { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.badge-red   { background: rgba(239,68,68,0.12);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.28); }
.badge-gray  { background: rgba(100,116,139,0.12); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2); }
.health-dot { font-size: 0.75rem; font-weight: 700; }
.hd-yes { color: #6ee7b7; }
.hd-no  { color: #64748b; }
.mono-sm { font-family: monospace; font-size: 0.75rem; color: #64748b; }

.row-actions { display: flex; gap: 0.375rem; }
.row-btn {
    width: 32px; height: 32px; border-radius: 0.5rem; border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s;
}
.row-btn svg { width: 14px; height: 14px; }
.edit-row-btn { background: rgba(245,158,11,0.12); color: #fcd34d; }
.edit-row-btn:hover { background: rgba(245,158,11,0.25); }
.del-row-btn  { background: rgba(239,68,68,0.1);  color: #fca5a5; }
.del-row-btn:hover  { background: rgba(239,68,68,0.22); }

/* Cards grid */
.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }

/* Empty state */
.empty-state {
    display: flex; flex-direction: column; align-items: center; gap: 1rem;
    padding: 4rem 2rem; text-align: center;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 1rem;
}
.empty-state svg { width: 56px; height: 56px; color: #334155; }
.empty-state p { font-size: 1rem; font-weight: 600; color: #64748b; margin: 0; }
.empty-sub { font-size: 0.875rem; color: #475569 !important; font-weight: 400 !important; }

/* Modals */
.modal-overlay {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center; padding: 1rem;
    animation: fadeIn 0.18s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-box {
    background: #0f172a; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem; width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 25px 80px rgba(0,0,0,0.7);
    animation: slideUp 0.22s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    font-family: 'Inter', sans-serif;
}
.modal-sm { max-width: 440px; }
@keyframes slideUp { from { opacity: 0; transform: translateY(16px) scale(0.97); } to { opacity: 1; transform: none; } }
.modal-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.07);
    position: sticky; top: 0; background: #0f172a; z-index: 2;
}
.modal-title { font-size: 1.1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.danger-title { color: #fca5a5; }
.modal-close { background: none; border: none; color: #64748b; font-size: 1.1rem; cursor: pointer; transition: color 0.15s; }
.modal-close:hover { color: #f87171; }
.modal-body { padding: 1.5rem; }

/* Delete warning */
.delete-warn {
    display: flex; gap: 1rem; align-items: flex-start;
    padding: 1rem; background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2); border-radius: 0.875rem; margin-bottom: 1.5rem;
}
.delete-warn svg { width: 24px; height: 24px; color: #fca5a5; flex-shrink: 0; }
.delete-warn p  { font-size: 0.9rem; color: #fca5a5; margin: 0; line-height: 1.5; }
.delete-warn strong { color: #fecaca; }
.delete-actions { display: flex; justify-content: flex-end; gap: 0.75rem; }

/* Buttons */
.btn-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; padding: 0.625rem 1.25rem; border-radius: 0.75rem;
    font-size: 0.875rem; font-weight: 600; border: none;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    font-family: 'Inter', sans-serif;
}
.btn-primary svg { width: 16px; height: 16px; }
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ghost-sm {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #94a3b8;
    font-size: 0.8125rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-ghost-sm svg { width: 15px; height: 15px; }
.btn-ghost-sm:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
.btn-danger {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.575rem 1.125rem;
    background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.35);
    border-radius: 0.75rem; color: #fca5a5;
    font-size: 0.875rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-danger:hover:not(:disabled) { background: rgba(239,68,68,0.25); }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }

/* Transitions */
.slide-down-enter-active,
.slide-down-leave-active { transition: all 0.25s ease; }
.slide-down-enter-from,
.slide-down-leave-to { transform: translateY(-10px); opacity: 0; }
</style>
