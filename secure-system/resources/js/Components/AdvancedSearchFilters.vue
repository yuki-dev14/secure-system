<script setup>
/**
 * AdvancedSearchFilters.vue
 *
 * Collapsible filter panel emitting a `filters` object on Apply.
 * Features:
 *  - Municipality → Barangay cascaded dropdowns
 *  - Status toggle pills
 *  - Date range pickers
 *  - Income range (dual inputs + visual bar)
 *  - Household size stepper
 *  - Save / load filter presets (localStorage)
 *  - Clear all
 */
import { ref, watch, computed } from 'vue';

const props = defineProps({
    municipalities: { type: Array, default: () => [] },
    allBarangays:   { type: Array, default: () => [] },
    initialFilters: { type: Object, default: () => ({}) },
    resultCount:    { type: Number, default: null },
    loading:        { type: Boolean, default: false },
});

const emit = defineEmits(['apply', 'clear']);

// ── State ──────────────────────────────────────────────────────────────────
const open = ref(false);
const PRESET_KEY = 'beneficiary_search_presets';

const f = ref({
    municipality:  props.initialFilters.municipality  ?? '',
    barangay:      props.initialFilters.barangay      ?? '',
    status:        props.initialFilters.status        ?? 'all',
    date_from:     props.initialFilters.date_from     ?? '',
    date_to:       props.initialFilters.date_to       ?? '',
    min_income:    props.initialFilters.min_income    ?? '',
    max_income:    props.initialFilters.max_income    ?? '',
    household_size:props.initialFilters.household_size?? '',
    gender:        props.initialFilters.gender        ?? '',
    civil_status:  props.initialFilters.civil_status  ?? '',
    sort:          props.initialFilters.sort          ?? 'newest',
});

// ── Cascaded barangay list ─────────────────────────────────────────────────
const filteredBarangays = computed(() => {
    if (!f.value.municipality) return props.allBarangays;
    // If barangays were pre-filtered server-side, just return them
    return props.allBarangays;
});

watch(() => f.value.municipality, () => {
    f.value.barangay = '';
});

// ── Active filter count (badge) ────────────────────────────────────────────
const activeCount = computed(() =>
    Object.entries(f.value).filter(([k, v]) =>
        v !== '' && v !== 'all' && v !== 'newest' && k !== 'sort'
    ).length
);

// ── Presets (localStorage) ─────────────────────────────────────────────────
const presets = ref(JSON.parse(localStorage.getItem(PRESET_KEY) || '[]'));
const presetName = ref('');

const savePreset = () => {
    if (!presetName.value.trim()) return;
    const updated = [...presets.value.filter(p => p.name !== presetName.value), {
        name: presetName.value.trim(),
        filters: { ...f.value },
        savedAt: new Date().toISOString(),
    }];
    presets.value = updated;
    localStorage.setItem(PRESET_KEY, JSON.stringify(updated));
    presetName.value = '';
};

const loadPreset = (preset) => {
    f.value = { ...f.value, ...preset.filters };
};

const deletePreset = (name) => {
    presets.value = presets.value.filter(p => p.name !== name);
    localStorage.setItem(PRESET_KEY, JSON.stringify(presets.value));
};

// ── Actions ────────────────────────────────────────────────────────────────
const apply = () => {
    emit('apply', { ...f.value });
};

const clearAll = () => {
    f.value = {
        municipality: '', barangay: '', status: 'all',
        date_from: '', date_to: '', min_income: '', max_income: '',
        household_size: '', gender: '', civil_status: '', sort: 'newest',
    };
    emit('clear');
};

const STATUS_OPTIONS  = [
    { v: 'all', label: 'All' },
    { v: 'active', label: 'Active' },
    { v: 'inactive', label: 'Inactive' },
];
const SORT_OPTIONS = [
    { v: 'newest',     label: 'Newest first' },
    { v: 'oldest',     label: 'Oldest first' },
    { v: 'alpha',      label: 'A → Z' },
    { v: 'alpha_desc', label: 'Z → A' },
    { v: 'income_asc', label: 'Income ↑' },
    { v: 'income_desc','label': 'Income ↓' },
];
const GENDER_OPTS = ['', 'Male', 'Female'];
const CIVIL_OPTS  = ['', 'Single', 'Married', 'Widowed', 'Separated'];
</script>

<template>
    <div class="asf-root">
        <!-- Toggle button -->
        <button id="advanced-filter-toggle" class="filter-toggle" @click="open = !open">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" clip-rule="evenodd"/></svg>
            Filters
            <span v-if="activeCount > 0" class="filter-badge">{{ activeCount }}</span>
            <svg class="chevron" :class="{ 'chevron-open': open }" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd"/>
            </svg>
        </button>

        <!-- Panel -->
        <Transition name="slide-panel">
            <div v-if="open" class="filter-panel">
                <div class="panel-grid">

                    <!-- ── Status ────────────────────────────────────── -->
                    <div class="filter-group full-width">
                        <label class="filter-label">Status</label>
                        <div class="pill-row">
                            <button
                                v-for="opt in STATUS_OPTIONS"
                                :key="opt.v"
                                :class="['pill', f.status === opt.v ? 'pill-active' : '']"
                                @click="f.status = opt.v"
                            >{{ opt.label }}</button>
                        </div>
                    </div>

                    <!-- ── Municipality ──────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label" for="f-municipality">Municipality</label>
                        <select id="f-municipality" v-model="f.municipality" class="field-input">
                            <option value="">All municipalities</option>
                            <option v-for="m in municipalities" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </div>

                    <!-- ── Barangay ──────────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label" for="f-barangay">Barangay</label>
                        <select id="f-barangay" v-model="f.barangay" class="field-input" :disabled="!f.municipality && filteredBarangays.length === 0">
                            <option value="">All barangays</option>
                            <option v-for="b in filteredBarangays" :key="b" :value="b">{{ b }}</option>
                        </select>
                    </div>

                    <!-- ── Gender ────────────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label" for="f-gender">Gender</label>
                        <select id="f-gender" v-model="f.gender" class="field-input">
                            <option value="">Any gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <!-- ── Civil Status ──────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label" for="f-civil">Civil Status</label>
                        <select id="f-civil" v-model="f.civil_status" class="field-input">
                            <option value="">Any status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                    </div>

                    <!-- ── Date range ────────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label">Date Registered: From</label>
                        <input id="f-date-from" v-model="f.date_from" type="date" class="field-input"/>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date Registered: To</label>
                        <input id="f-date-to" v-model="f.date_to" type="date" class="field-input" :min="f.date_from"/>
                    </div>

                    <!-- ── Income range ──────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label">
                            Annual Income — Min
                            <span v-if="f.min_income" class="val-chip">₱{{ Number(f.min_income).toLocaleString() }}</span>
                        </label>
                        <div class="prefix-wrap">
                            <span class="prefix-icon">₱</span>
                            <input id="f-min-income" v-model="f.min_income" type="number" min="0" step="1000" class="field-input prefix-field" placeholder="0"/>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            Annual Income — Max
                            <span v-if="f.max_income" class="val-chip">₱{{ Number(f.max_income).toLocaleString() }}</span>
                        </label>
                        <div class="prefix-wrap">
                            <span class="prefix-icon">₱</span>
                            <input id="f-max-income" v-model="f.max_income" type="number" min="0" step="1000" class="field-input prefix-field" placeholder="No limit"/>
                        </div>
                    </div>

                    <!-- ── Household size ────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label">
                            Household Size
                            <span v-if="f.household_size" class="val-chip">{{ f.household_size }} member(s)</span>
                        </label>
                        <div class="stepper">
                            <button class="stp-btn" :disabled="!f.household_size || f.household_size <= 1" @click="f.household_size = Math.max(1, (f.household_size || 1) - 1)">−</button>
                            <input id="f-household" v-model="f.household_size" type="number" min="1" class="stp-input" placeholder="Any"/>
                            <button class="stp-btn" @click="f.household_size = (f.household_size || 0) + 1">+</button>
                        </div>
                    </div>

                    <!-- ── Sort ──────────────────────────────────────── -->
                    <div class="filter-group">
                        <label class="filter-label" for="f-sort">Sort By</label>
                        <select id="f-sort" v-model="f.sort" class="field-input">
                            <option v-for="s in SORT_OPTIONS" :key="s.v" :value="s.v">{{ s.label }}</option>
                        </select>
                    </div>

                </div>

                <!-- ── Presets ─────────────────────────────────────── -->
                <div v-if="presets.length || true" class="preset-section">
                    <div class="preset-save">
                        <input v-model="presetName" type="text" placeholder="Name this filter set…" class="field-input preset-input" maxlength="40"/>
                        <button class="btn-ghost-sm" :disabled="!presetName.trim()" @click="savePreset">Save Preset</button>
                    </div>
                    <div v-if="presets.length" class="preset-list">
                        <button
                            v-for="p in presets"
                            :key="p.name"
                            class="preset-chip"
                            @click="loadPreset(p)"
                        >
                            {{ p.name }}
                            <span class="preset-del" @click.stop="deletePreset(p.name)">✕</span>
                        </button>
                    </div>
                </div>

                <!-- ── Footer actions ──────────────────────────────── -->
                <div class="panel-footer">
                    <div class="result-hint" v-if="resultCount !== null">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z"/></svg>
                        <span><strong>{{ resultCount.toLocaleString() }}</strong> result{{ resultCount !== 1 ? 's' : '' }} match</span>
                    </div>
                    <div class="footer-btns">
                        <button class="btn-ghost-sm" @click="clearAll">Clear All</button>
                        <button id="apply-filters-btn" class="btn-primary" :disabled="loading" @click="apply">
                            <svg v-if="loading" class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31" stroke-linecap="round"/></svg>
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.asf-root { font-family: 'Inter', sans-serif; }

/* Toggle button */
.filter-toggle {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #94a3b8;
    font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.filter-toggle svg:first-child { width: 15px; height: 15px; }
.filter-toggle:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
.filter-badge {
    min-width: 18px; height: 18px; border-radius: 9999px;
    background: #6366f1; color: white;
    font-size: 0.65rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; padding: 0 4px;
}
.chevron { width: 14px; height: 14px; transition: transform 0.2s; }
.chevron-open { transform: rotate(180deg); }

/* Panel */
.filter-panel {
    margin-top: 0.75rem;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem; padding: 1.5rem;
}
.panel-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 1.25rem 1rem;
}
.full-width { grid-column: 1 / -1; }

.filter-group { display: flex; flex-direction: column; gap: 0.375rem; }
.filter-label { font-size: 0.78rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 0.375rem; }
.val-chip { font-size: 0.65rem; background: rgba(99,102,241,0.2); color: #a5b4fc; padding: 0.1rem 0.4rem; border-radius: 9999px; font-weight: 700; }

.field-input {
    padding: 0.575rem 0.875rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; color: #f1f5f9; font-size: 0.875rem;
    font-family: 'Inter', sans-serif; outline: none; transition: border-color 0.2s;
    appearance: auto;
}
.field-input:focus { border-color: rgba(99,102,241,0.5); background: rgba(99,102,241,0.05); }
.field-input:disabled { opacity: 0.4; cursor: not-allowed; }

/* Prefix input */
.prefix-wrap { position: relative; }
.prefix-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 0.875rem; pointer-events: none; }
.prefix-field { padding-left: 1.5rem; }

/* Status pills */
.pill-row { display: flex; gap: 0.375rem; }
.pill {
    padding: 0.375rem 0.875rem; border-radius: 9999px;
    font-size: 0.8125rem; font-weight: 600; color: #64748b;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    cursor: pointer; transition: all 0.15s; font-family: 'Inter', sans-serif;
}
.pill:hover { background: rgba(255,255,255,0.1); color: #94a3b8; }
.pill-active { background: rgba(99,102,241,0.2) !important; border-color: rgba(99,102,241,0.4) !important; color: #a5b4fc !important; }

/* Stepper */
.stepper { display: flex; align-items: center; }
.stp-btn {
    width: 36px; height: 38px; border-radius: 0.5rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    color: #94a3b8; font-size: 1.1rem; cursor: pointer; transition: all 0.15s;
    font-family: 'Inter', sans-serif;
}
.stp-btn:hover:not(:disabled) { background: rgba(255,255,255,0.12); color: #f1f5f9; }
.stp-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.stp-input {
    flex: 1; text-align: center; border-radius: 0;
    border-left: none; border-right: none;
}

/* Presets */
.preset-section { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgba(255,255,255,0.06); }
.preset-save { display: flex; gap: 0.75rem; align-items: center; }
.preset-input { flex: 1; }
.preset-list  { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; }
.preset-chip {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.3rem 0.75rem; border-radius: 9999px;
    background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.25); color: #a5b4fc;
    font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
    font-family: 'Inter', sans-serif;
}
.preset-chip:hover { background: rgba(99,102,241,0.22); }
.preset-del {
    font-size: 0.65rem; color: #6366f1; line-height: 1;
    padding: 1px; border-radius: 50%; transition: color 0.15s;
}
.preset-del:hover { color: #fca5a5; }

/* Footer */
.panel-footer {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem;
    margin-top: 1.25rem; padding-top: 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.result-hint { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: #64748b; }
.result-hint svg { width: 15px; height: 15px; }
.result-hint strong { color: #a5b4fc; }
.footer-btns { display: flex; align-items: center; gap: 0.75rem; }

/* Buttons */
.btn-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; padding: 0.575rem 1.25rem; border-radius: 0.75rem;
    font-size: 0.875rem; font-weight: 600; border: none;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    font-family: 'Inter', sans-serif;
}
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ghost-sm {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 0.875rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #94a3b8;
    font-size: 0.8125rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-ghost-sm:hover:not(:disabled) { background: rgba(255,255,255,0.1); color: #f1f5f9; }
.btn-ghost-sm:disabled { opacity: 0.5; cursor: not-allowed; }

/* Spinner */
.spinner { width: 14px; height: 14px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Transitions */
.slide-panel-enter-active, .slide-panel-leave-active { transition: all 0.2s ease; }
.slide-panel-enter-from, .slide-panel-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
