<script setup>
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';

const toasts = ref([]);

let counter = 0;

const addToast = (message, type = 'success', duration = 4000) => {
    const id = ++counter;
    toasts.value.push({ id, message, type });
    setTimeout(() => removeToast(id), duration);
};

const removeToast = (id) => {
    const index = toasts.value.findIndex((t) => t.id === id);
    if (index !== -1) toasts.value.splice(index, 1);
};

// Watch Inertia flash messages
const page = usePage();
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) addToast(flash.success, 'success');
        if (flash?.error) addToast(flash.error, 'error');
    },
    { immediate: true, deep: true }
);

// Expose for programmatic use
defineExpose({ addToast });
</script>

<template>
    <Teleport to="body">
        <div class="toast-container">
            <TransitionGroup name="toast" tag="div" class="toast-inner">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="toast-item"
                    :class="[`toast-${toast.type}`]"
                    @click="removeToast(toast.id)"
                >
                    <div class="toast-icon">
                        <!-- Success -->
                        <svg v-if="toast.type === 'success'" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                        </svg>
                        <!-- Error -->
                        <svg v-else-if="toast.type === 'error'" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <!-- Warning -->
                        <svg v-else viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <span class="toast-message">{{ toast.message }}</span>
                    <button class="toast-close" @click.stop="removeToast(toast.id)">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-container {
    position: fixed; top: 1.5rem; right: 1.5rem;
    z-index: 9999; pointer-events: none;
}
.toast-inner { display: flex; flex-direction: column; gap: 0.75rem; }

.toast-item {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.875rem 1rem;
    min-width: 300px; max-width: 420px;
    border-radius: 0.875rem;
    backdrop-filter: blur(16px);
    cursor: pointer; pointer-events: all;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    font-family: 'Inter', sans-serif;
    transition: transform 0.2s;
}
.toast-item:hover { transform: scale(1.02); }

.toast-success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
.toast-error   { background: rgba(239,68,68,0.15);  border: 1px solid rgba(239,68,68,0.3);  color: #fca5a5; }
.toast-warning { background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: #fcd34d; }

.toast-icon { width: 20px; height: 20px; flex-shrink: 0; }
.toast-icon svg { width: 100%; height: 100%; }
.toast-message { flex: 1; font-size: 0.875rem; font-weight: 500; line-height: 1.4; }
.toast-close {
    background: none; border: none; cursor: pointer; color: inherit;
    opacity: 0.6; padding: 0; display: flex; align-items: center;
    transition: opacity 0.2s; flex-shrink: 0;
}
.toast-close:hover { opacity: 1; }
.toast-close svg { width: 16px; height: 16px; }

/* Transitions */
.toast-enter-active { transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.toast-leave-active { transition: all 0.25s ease-in; }
.toast-enter-from { opacity: 0; transform: translateX(100%) scale(0.8); }
.toast-leave-to { opacity: 0; transform: translateX(100%) scale(0.8); }
</style>
