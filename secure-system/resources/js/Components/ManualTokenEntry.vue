<script setup>
import { ref, computed } from 'vue';

const emit = defineEmits(['submit']);

const token     = ref('');
const error     = ref('');

const charCount  = computed(() => token.value.length);
const isValid    = computed(() => /^[a-zA-Z0-9]{64}$/.test(token.value));
const charStatus = computed(() => {
    if (charCount.value === 0)  return 'empty';
    if (charCount.value < 64)   return 'short';
    if (charCount.value === 64) return isValid.value ? 'valid' : 'invalid';
    return 'long';
});

function onInput() {
    error.value = '';
    // Strip non-alphanumeric, keep max 64
    token.value = token.value.replace(/[^a-zA-Z0-9]/g, '').slice(0, 64);
}

function submit() {
    if (!isValid.value) {
        error.value = 'Token must be exactly 64 alphanumeric characters.';
        return;
    }
    emit('submit', token.value);
}

function clear() {
    token.value = '';
    error.value = '';
}

async function pasteFromClipboard() {
    try {
        const text = await navigator.clipboard.readText();
        token.value = text.replace(/[^a-zA-Z0-9]/g, '').slice(0, 64);
        error.value = '';
    } catch {
        error.value = 'Clipboard access denied. Please paste manually.';
    }
}
</script>

<template>
    <div class="manual-card">
        <div class="manual-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
            </svg>
            <div>
                <p class="manual-title">Manual Token Entry</p>
                <p class="manual-sub">Enter or paste a 64-character verification token</p>
            </div>
        </div>

        <div class="field-wrap">
            <div class="input-row">
                <input
                    id="manual-token-input"
                    v-model="token"
                    type="text"
                    class="token-input"
                    :class="{ 'input-valid': charStatus === 'valid', 'input-invalid': charStatus === 'invalid' || charStatus === 'long' }"
                    placeholder="Paste or type the 64-character token…"
                    spellcheck="false"
                    autocomplete="off"
                    maxlength="64"
                    @input="onInput"
                    @keyup.enter="submit"
                />
                <button class="paste-btn" @click="pasteFromClipboard" title="Paste from clipboard">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/>
                        <path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/>
                    </svg>
                </button>
            </div>

            <!-- Character counter -->
            <div class="char-row">
                <span :class="['char-count', `char-${charStatus}`]">
                    {{ charCount }} / 64
                </span>
                <span v-if="charStatus === 'valid'" class="char-hint clr-green">✓ Token format valid</span>
                <span v-else-if="charStatus === 'long'" class="char-hint clr-red">Too many characters</span>
                <span v-else-if="charStatus === 'short' && charCount > 0" class="char-hint">{{ 64 - charCount }} more character(s) needed</span>
            </div>

            <!-- Error -->
            <p v-if="error" class="field-error">{{ error }}</p>
        </div>

        <div class="manual-actions">
            <button class="btn-clear" @click="clear" :disabled="!token">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
                Clear
            </button>
            <button class="btn-submit" @click="submit" :disabled="!isValid">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                </svg>
                Verify Token
            </button>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.manual-card {
    font-family: 'Inter', sans-serif;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem;
    padding: 1.375rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.manual-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.manual-header svg { width: 20px; height: 20px; color: #a5b4fc; flex-shrink: 0; margin-top: 2px; }
.manual-title { font-size: 0.9rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.125rem; }
.manual-sub   { font-size: 0.75rem; color: #64748b; margin: 0; }

.field-wrap { display: flex; flex-direction: column; gap: 0.5rem; }

.input-row { display: flex; gap: 0.5rem; }
.token-input {
    flex: 1;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem;
    color: #e2e8f0;
    font-size: 0.8125rem;
    font-family: 'Courier New', monospace;
    padding: 0.625rem 0.875rem;
    outline: none;
    transition: border-color 0.2s;
    letter-spacing: 0.02em;
}
.token-input::placeholder { color: #475569; }
.token-input:focus { border-color: rgba(165,180,252,0.4); background: rgba(255,255,255,0.05); }
.input-valid  { border-color: rgba(16,185,129,0.4) !important; }
.input-invalid { border-color: rgba(239,68,68,0.4) !important; }

.paste-btn {
    width: 40px; height: 40px; flex-shrink: 0;
    background: rgba(99,102,241,0.1);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 0.625rem;
    color: #a5b4fc;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.paste-btn svg { width: 16px; height: 16px; }
.paste-btn:hover { background: rgba(99,102,241,0.2); }

.char-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.char-count { font-size: 0.75rem; font-weight: 700; font-family: monospace; }
.char-empty   { color: #475569; }
.char-short   { color: #94a3b8; }
.char-valid   { color: #6ee7b7; }
.char-invalid, .char-long { color: #f87171; }
.char-hint  { font-size: 0.75rem; color: #64748b; }
.clr-green  { color: #6ee7b7 !important; }
.clr-red    { color: #f87171 !important; }

.field-error { margin: 0; font-size: 0.75rem; color: #f87171; }

.manual-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

.btn-clear, .btn-submit {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5625rem 1rem; border-radius: 0.625rem;
    font-size: 0.8125rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
}
.btn-clear svg, .btn-submit svg { width: 15px; height: 15px; }

.btn-clear {
    background: rgba(100,116,139,0.1);
    border: 1px solid rgba(100,116,139,0.2);
    color: #94a3b8;
}
.btn-clear:hover:not(:disabled) { background: rgba(100,116,139,0.2); }
.btn-clear:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-submit {
    background: rgba(99,102,241,0.15);
    border: 1px solid rgba(99,102,241,0.3);
    color: #a5b4fc;
}
.btn-submit:hover:not(:disabled) { background: rgba(99,102,241,0.25); }
.btn-submit:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
