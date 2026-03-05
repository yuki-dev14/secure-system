<script setup>
/**
 * SearchBar.vue
 *
 * A premium search input with:
 *  - debounced emission (300 ms default)
 *  - quick-search autocomplete dropdown (calls /api/beneficiaries/quick-search)
 *  - keyboard navigation (↑↓ Enter Esc)
 *  - clear button
 *  - loading spinner while fetching suggestions
 *
 * Emits:
 *   search(value: string)  – debounced, for the main list query
 *   select(item)           – when a suggestion is picked
 */
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder:{ type: String, default: 'Search by name, BIN, or contact…' },
    debounce:   { type: Number, default: 300 },
    showSuggestions: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'search', 'select']);

const inputRef     = ref(null);
const query        = ref(props.modelValue);
const suggestions  = ref([]);
const loadingSugg  = ref(false);
const showDropdown = ref(false);
const activeIdx    = ref(-1);
let debounceTimer  = null;
let abortCtrl      = null;

// ── Sync prop → local ─────────────────────────────────────────────────────
watch(() => props.modelValue, (v) => { query.value = v; });

// ── Input handler ─────────────────────────────────────────────────────────
const onInput = (e) => {
    query.value = e.target.value;
    emit('update:modelValue', query.value);
    activeIdx.value = -1;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('search', query.value);
        if (props.showSuggestions && query.value.length >= 2) {
            fetchSuggestions(query.value);
        } else {
            suggestions.value = [];
            showDropdown.value = false;
        }
    }, props.debounce);
};

// ── Autocomplete fetch ────────────────────────────────────────────────────
const fetchSuggestions = async (term) => {
    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();
    loadingSugg.value = true;
    try {
        const res = await fetch(
            `/api/beneficiaries/quick-search?q=${encodeURIComponent(term)}`,
            { signal: abortCtrl.signal }
        );
        if (!res.ok) throw new Error('fetch failed');
        suggestions.value = await res.json();
        showDropdown.value = suggestions.value.length > 0;
    } catch (err) {
        if (err.name !== 'AbortError') suggestions.value = [];
    } finally {
        loadingSugg.value = false;
    }
};

// ── Keyboard navigation ────────────────────────────────────────────────────
const onKeydown = (e) => {
    if (!showDropdown.value) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx.value = Math.min(activeIdx.value + 1, suggestions.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx.value = Math.max(activeIdx.value - 1, -1);
    } else if (e.key === 'Enter') {
        if (activeIdx.value >= 0) {
            selectItem(suggestions.value[activeIdx.value]);
        } else {
            emit('search', query.value);
            showDropdown.value = false;
        }
    } else if (e.key === 'Escape') {
        showDropdown.value = false;
        activeIdx.value = -1;
    }
};

const selectItem = (item) => {
    query.value = item.name;
    emit('update:modelValue', item.name);
    emit('select', item);
    showDropdown.value = false;
    suggestions.value = [];
    router.visit(route('beneficiaries.show', item.id));
};

const clearSearch = () => {
    query.value = '';
    suggestions.value = [];
    showDropdown.value = false;
    emit('update:modelValue', '');
    emit('search', '');
    inputRef.value?.focus();
};

// ── Close on outside click ─────────────────────────────────────────────────
const onOutside = (e) => {
    if (!e.target.closest('.search-bar-root')) {
        showDropdown.value = false;
    }
};
onMounted(() => document.addEventListener('click', onOutside));
onBeforeUnmount(() => document.removeEventListener('click', onOutside));
</script>

<template>
    <div class="search-bar-root">
        <div class="search-bar" :class="{ 'search-bar-active': showDropdown || query }">
            <!-- Search icon -->
            <span class="search-icon">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                </svg>
            </span>

            <!-- Input -->
            <input
                id="beneficiary-search-input"
                ref="inputRef"
                :value="query"
                :placeholder="placeholder"
                type="search"
                autocomplete="off"
                class="search-input"
                @input="onInput"
                @keydown="onKeydown"
                @focus="showDropdown = suggestions.length > 0"
            />

            <!-- Loading spinner -->
            <span v-if="loadingSugg" class="search-spinner">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31" stroke-linecap="round"/>
                </svg>
            </span>

            <!-- Clear button -->
            <button v-else-if="query" class="clear-btn" type="button" @click="clearSearch" aria-label="Clear search">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
            </button>
        </div>

        <!-- Autocomplete dropdown -->
        <Transition name="drop">
            <div v-if="showDropdown && suggestions.length" class="suggestions-dropdown">
                <div class="sugg-header">Quick results</div>
                <button
                    v-for="(item, i) in suggestions"
                    :key="item.id"
                    class="sugg-item"
                    :class="{ 'sugg-active': i === activeIdx }"
                    type="button"
                    @click="selectItem(item)"
                    @mouseenter="activeIdx = i"
                >
                    <div class="sugg-avatar">{{ item.name.charAt(0).toUpperCase() }}</div>
                    <div class="sugg-body">
                        <div class="sugg-name">{{ item.name }}</div>
                        <div class="sugg-meta">
                            <span class="sugg-bin">{{ item.bin }}</span>
                            <span class="sugg-location">· {{ item.sub }}</span>
                        </div>
                    </div>
                    <svg class="sugg-arrow" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 010-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
* { box-sizing: border-box; }

.search-bar-root { position: relative; font-family: 'Inter', sans-serif; }

.search-bar {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.875rem; padding: 0 1rem;
    transition: all 0.2s; height: 44px;
}
.search-bar-active,
.search-bar:focus-within {
    border-color: rgba(99,102,241,0.5);
    background: rgba(99,102,241,0.06);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}

.search-icon { display: flex; color: #475569; flex-shrink: 0; }
.search-icon svg { width: 17px; height: 17px; }

.search-input {
    flex: 1; background: transparent; border: none; outline: none;
    color: #f1f5f9; font-size: 0.9rem; font-family: 'Inter', sans-serif;
    min-width: 0;
}
.search-input::placeholder { color: #475569; }
/* Hide default browser clear button */
.search-input::-webkit-search-cancel-button { display: none; }

.search-spinner { display: flex; color: #6366f1; }
.search-spinner svg { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.clear-btn {
    display: flex; align-items: center;
    background: none; border: none; color: #475569;
    cursor: pointer; padding: 0; transition: color 0.15s;
}
.clear-btn svg { width: 16px; height: 16px; }
.clear-btn:hover { color: #f1f5f9; }

/* Dropdown */
.suggestions-dropdown {
    position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 999;
    background: #0f172a; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.875rem; overflow: hidden;
    box-shadow: 0 16px 40px rgba(0,0,0,0.5);
}
.sugg-header {
    padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 700;
    color: #475569; text-transform: uppercase; letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sugg-item {
    display: flex; align-items: center; gap: 0.75rem; width: 100%;
    padding: 0.75rem 1rem; border: none; background: transparent;
    cursor: pointer; text-align: left; transition: background 0.15s;
    font-family: 'Inter', sans-serif;
}
.sugg-item:not(:last-child) { border-bottom: 1px solid rgba(255,255,255,0.04); }
.sugg-active, .sugg-item:hover { background: rgba(99,102,241,0.1); }
.sugg-avatar {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8125rem; font-weight: 700; color: white;
}
.sugg-body { flex: 1; min-width: 0; }
.sugg-name { font-size: 0.875rem; font-weight: 600; color: #f1f5f9; }
.sugg-meta { display: flex; align-items: center; gap: 0.375rem; }
.sugg-bin  { font-size: 0.72rem; color: #a5b4fc; font-family: monospace; }
.sugg-location { font-size: 0.72rem; color: #64748b; }
.sugg-arrow { width: 14px; height: 14px; color: #334155; flex-shrink: 0; }

/* Transition */
.drop-enter-active, .drop-leave-active { transition: all 0.18s ease; }
.drop-enter-from, .drop-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
