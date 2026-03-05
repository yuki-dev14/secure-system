<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import jsQR from 'jsqr';

const emit = defineEmits(['scanned', 'close', 'error']);
const props = defineProps({
    active: { type: Boolean, default: true },
});

// ── State ────────────────────────────────────────────────────────────────────
const videoEl      = ref(null);
const canvasEl     = ref(null);
const overlayEl    = ref(null);
const streamRef    = ref(null);
const scanStatus   = ref('initializing'); // initializing | searching | detected | processing | error | denied
const errorMessage = ref('');
const scannedToken = ref('');
const lastScanTime = ref(0);
const DEBOUNCE_MS  = 2000; // 2 second debounce
let animFrameId    = null;
let isScanning     = false;

// ── Camera Setup ─────────────────────────────────────────────────────────────
async function startCamera() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        });
        streamRef.value = stream;
        if (videoEl.value) {
            videoEl.value.srcObject = stream;
            videoEl.value.play();
            scanStatus.value = 'searching';
            scheduleFrame();
        }
    } catch (err) {
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            scanStatus.value = 'denied';
            errorMessage.value = 'Camera access denied. Please allow camera permission and refresh.';
        } else if (err.name === 'NotFoundError') {
            scanStatus.value = 'error';
            errorMessage.value = 'No camera device found on this device.';
        } else {
            scanStatus.value = 'error';
            errorMessage.value = 'Camera error: ' + err.message;
        }
        emit('error', errorMessage.value);
    }
}

function stopCamera() {
    if (animFrameId) {
        cancelAnimationFrame(animFrameId);
        animFrameId = null;
    }
    if (streamRef.value) {
        streamRef.value.getTracks().forEach(t => t.stop());
        streamRef.value = null;
    }
    isScanning = false;
}

// ── QR Detection Loop ────────────────────────────────────────────────────────
function scheduleFrame() {
    animFrameId = requestAnimationFrame(detectQRCode);
}

function detectQRCode() {
    if (!videoEl.value || videoEl.value.readyState !== videoEl.value.HAVE_ENOUGH_DATA) {
        scheduleFrame();
        return;
    }

    const video  = videoEl.value;
    const canvas = canvasEl.value;
    if (!canvas) { scheduleFrame(); return; }

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert',
    });

    if (code) {
        const now = Date.now();
        if (now - lastScanTime.value < DEBOUNCE_MS) {
            scheduleFrame();
            return;
        }
        lastScanTime.value = now;
        drawQRLocation(ctx, code.location, canvas.width, canvas.height);
        handleDetectedQR(code.data);
    } else {
        if (scanStatus.value !== 'processing') {
            scanStatus.value = 'searching';
        }
        scheduleFrame();
    }
}

function drawQRLocation(ctx, location, w, h) {
    ctx.beginPath();
    ctx.moveTo(location.topLeftCorner.x,     location.topLeftCorner.y);
    ctx.lineTo(location.topRightCorner.x,    location.topRightCorner.y);
    ctx.lineTo(location.bottomRightCorner.x, location.bottomRightCorner.y);
    ctx.lineTo(location.bottomLeftCorner.x,  location.bottomLeftCorner.y);
    ctx.closePath();
    ctx.lineWidth   = 4;
    ctx.strokeStyle = '#6ee7b7';
    ctx.stroke();
    ctx.fillStyle   = 'rgba(110,231,183,0.08)';
    ctx.fill();
}

function handleDetectedQR(data) {
    scanStatus.value  = 'detected';
    scannedToken.value = data;
    setTimeout(() => {
        scanStatus.value = 'processing';
        emit('scanned', data);
        scheduleFrame();
    }, 300);
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
        scanStatus.value = 'error';
        errorMessage.value = 'Camera API not available. Ensure the page is served over HTTPS.';
        emit('error', errorMessage.value);
        return;
    }
    await startCamera();
});

onUnmounted(() => stopCamera());

watch(() => props.active, (val) => {
    if (!val) stopCamera();
    else startCamera();
});

// ── Exposed ──────────────────────────────────────────────────────────────────
function resetScanner() {
    scannedToken.value = '';
    scanStatus.value   = 'searching';
}
defineExpose({ resetScanner });
</script>

<template>
    <div class="scanner-shell">
        <!-- Header -->
        <div class="scanner-header">
            <div class="scanner-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/>
                </svg>
                <span>QR Code Scanner</span>
            </div>
            <button class="close-btn" @click="$emit('close')">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
            </button>
        </div>

        <!-- Status bar -->
        <div :class="['status-bar', `status-${scanStatus}`]">
            <span class="status-dot"></span>
            <span v-if="scanStatus === 'initializing'">Initializing camera…</span>
            <span v-else-if="scanStatus === 'searching'">Searching for QR code…</span>
            <span v-else-if="scanStatus === 'detected'">QR Code Detected!</span>
            <span v-else-if="scanStatus === 'processing'">Processing…</span>
            <span v-else-if="scanStatus === 'denied'">Camera Permission Denied</span>
            <span v-else-if="scanStatus === 'error'">Camera Error</span>
        </div>

        <!-- Camera view -->
        <div class="camera-container" v-if="scanStatus !== 'denied' && scanStatus !== 'error'">
            <video ref="videoEl" class="camera-video" autoplay playsinline muted />
            <canvas ref="canvasEl" class="scan-canvas" />
            <div ref="overlayEl" class="scan-overlay">
                <!-- Corner brackets -->
                <div class="bracket tl"></div>
                <div class="bracket tr"></div>
                <div class="bracket bl"></div>
                <div class="bracket br"></div>
                <!-- Scan line -->
                <div class="scan-line" :class="{ animating: scanStatus === 'searching' }"></div>
            </div>
            <div class="camera-hint">Point camera at a beneficiary QR code</div>
        </div>

        <!-- Error / denied state -->
        <div class="error-state" v-else>
            <div class="error-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <p class="error-title">{{ scanStatus === 'denied' ? 'Camera Access Denied' : 'Camera Unavailable' }}</p>
            <p class="error-msg">{{ errorMessage }}</p>
            <p class="error-hint">Use the manual token entry below as an alternative.</p>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.scanner-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(15,23,42,0.97);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Header */
.scanner-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.125rem 1.375rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    background: rgba(255,255,255,0.02);
}
.scanner-title {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    font-size: 0.9375rem;
    font-weight: 700;
    color: #f1f5f9;
}
.scanner-title svg { width: 20px; height: 20px; color: #a5b4fc; }
.close-btn {
    width: 32px; height: 32px;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 0.5rem;
    color: #fca5a5;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.close-btn svg { width: 16px; height: 16px; }
.close-btn:hover { background: rgba(239,68,68,0.2); }

/* Status bar */
.status-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.375rem;
    font-size: 0.8125rem;
    font-weight: 600;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: all 0.3s;
}
.status-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-initializing  { color: #94a3b8; }
.status-initializing  .status-dot { background: #64748b; }
.status-searching     { color: #93c5fd; }
.status-searching     .status-dot { background: #3b82f6; animation: pulse-dot 1.2s infinite; }
.status-detected      { color: #6ee7b7; }
.status-detected      .status-dot { background: #10b981; }
.status-processing    { color: #c4b5fd; }
.status-processing    .status-dot { background: #8b5cf6; animation: pulse-dot 0.6s infinite; }
.status-denied,
.status-error         { color: #fca5a5; }
.status-denied        .status-dot,
.status-error         .status-dot { background: #ef4444; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.4; transform: scale(0.7); }
}

/* Camera */
.camera-container {
    position: relative;
    width: 100%;
    aspect-ratio: 4/3;
    background: #000;
    overflow: hidden;
    max-height: 380px;
}
.camera-video {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.scan-canvas {
    position: absolute;
    inset: 0;
    width: 100%; height: 100%;
    pointer-events: none;
}
.scan-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

/* Brackets */
.bracket {
    position: absolute;
    width: 44px; height: 44px;
    border-color: #a5b4fc;
    border-style: solid;
    border-width: 0;
    border-radius: 4px;
}
.tl { top: 20%; left: 15%; border-top-width: 3px; border-left-width: 3px; border-top-left-radius: 8px; }
.tr { top: 20%; right: 15%; border-top-width: 3px; border-right-width: 3px; border-top-right-radius: 8px; }
.bl { bottom: 20%; left: 15%; border-bottom-width: 3px; border-left-width: 3px; border-bottom-left-radius: 8px; }
.br { bottom: 20%; right: 15%; border-bottom-width: 3px; border-right-width: 3px; border-bottom-right-radius: 8px; }

/* Scan line */
.scan-line {
    position: absolute;
    left: 15%; right: 15%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #a5b4fc, transparent);
    border-radius: 999px;
    top: 20%;
    box-shadow: 0 0 8px rgba(165,180,252,0.5);
}
.scan-line.animating {
    animation: scan-sweep 2s ease-in-out infinite;
}
@keyframes scan-sweep {
    0%   { top: 20%; opacity: 0.4; }
    50%  { opacity: 1; }
    100% { top: 80%; opacity: 0.4; }
}

.camera-hint {
    position: absolute;
    bottom: 0;
    left: 0; right: 0;
    background: rgba(0,0,0,0.6);
    color: rgba(255,255,255,0.7);
    font-size: 0.75rem;
    text-align: center;
    padding: 0.5rem;
}

/* Error state */
.error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.75rem;
    text-align: center;
    gap: 0.75rem;
}
.error-icon {
    width: 56px; height: 56px;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 1rem;
    display: flex; align-items: center; justify-content: center;
    color: #f87171;
}
.error-icon svg { width: 28px; height: 28px; }
.error-title { font-size: 1rem; font-weight: 700; color: #fca5a5; margin: 0; }
.error-msg   { font-size: 0.8125rem; color: #94a3b8; margin: 0; line-height: 1.5; }
.error-hint  { font-size: 0.75rem; color: #64748b; margin: 0; }
</style>
