<template>
  <div class="signature-pad-wrapper">
    <div class="pad-header">
      <span class="pad-label">✍️ Beneficiary Signature</span>
      <div class="pad-actions">
        <button type="button" class="btn-undo" @click="undo" :disabled="strokes.length === 0">
          ↩ Undo
        </button>
        <button type="button" class="btn-clear" @click="clear" :disabled="isEmpty">
          🗑 Clear
        </button>
      </div>
    </div>

    <div class="canvas-container" :class="{ 'has-sig': !isEmpty }">
      <canvas
        ref="canvasRef"
        :width="canvasWidth"
        :height="canvasHeight"
        @mousedown="startDrawing"
        @mousemove="draw"
        @mouseup="stopDrawing"
        @mouseleave="stopDrawing"
        @touchstart.prevent="onTouchStart"
        @touchmove.prevent="onTouchMove"
        @touchend.prevent="stopDrawing"
        class="sig-canvas"
      ></canvas>
      <div v-if="isEmpty" class="placeholder-text">
        Sign here
      </div>
    </div>

    <div v-if="!isEmpty" class="sig-preview-bar">
      <span class="preview-label">✅ Signature captured</span>
      <button type="button" class="btn-save" @click="emitSave">
        💾 Use This Signature
      </button>
    </div>

    <div v-if="savedSignature" class="saved-display">
      <div class="saved-header">Saved Signature Preview</div>
      <img :src="savedSignature" alt="Saved Signature" class="saved-img" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'

const props = defineProps({
  canvasWidth:  { type: Number, default: 520 },
  canvasHeight: { type: Number, default: 160 },
  lineWidth:    { type: Number, default: 2.5 },
  inkColor:     { type: String, default: '#1a1a2e' },
})

const emit = defineEmits(['save', 'clear'])

const canvasRef      = ref(null)
const strokes        = ref([])   // array of point arrays — one per stroke
const isDrawing      = ref(false)
const savedSignature = ref(null)

const isEmpty = computed(() => strokes.value.length === 0)

let ctx = null

onMounted(() => {
  ctx = canvasRef.value.getContext('2d')
  ctx.lineCap    = 'round'
  ctx.lineJoin   = 'round'
  ctx.lineWidth  = props.lineWidth
  ctx.strokeStyle= props.inkColor
  drawBackground()
})

function drawBackground () {
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, props.canvasWidth, props.canvasHeight)
}

function getPos (e) {
  const rect = canvasRef.value.getBoundingClientRect()
  const scaleX = props.canvasWidth  / rect.width
  const scaleY = props.canvasHeight / rect.height
  return {
    x: (e.clientX - rect.left) * scaleX,
    y: (e.clientY - rect.top)  * scaleY,
  }
}

function startDrawing (e) {
  isDrawing.value = true
  const pos = getPos(e)
  strokes.value.push([pos])
  ctx.beginPath()
  ctx.moveTo(pos.x, pos.y)
}

function draw (e) {
  if (!isDrawing.value) return
  const pos = getPos(e)
  strokes.value[strokes.value.length - 1].push(pos)
  ctx.lineTo(pos.x, pos.y)
  ctx.stroke()
}

function stopDrawing () {
  if (!isDrawing.value) return
  isDrawing.value = false
  ctx.beginPath()
}

function onTouchStart (e) {
  const touch = e.touches[0]
  startDrawing(touch)
}

function onTouchMove (e) {
  const touch = e.touches[0]
  draw(touch)
}

function undo () {
  if (!strokes.value.length) return
  strokes.value.pop()
  redrawAll()
}

function clear () {
  strokes.value = []
  savedSignature.value = null
  drawBackground()
  emit('clear')
}

function redrawAll () {
  drawBackground()
  ctx.beginPath()
  ctx.lineCap    = 'round'
  ctx.lineJoin   = 'round'
  ctx.lineWidth  = props.lineWidth
  ctx.strokeStyle= props.inkColor
  for (const stroke of strokes.value) {
    if (!stroke.length) continue
    ctx.beginPath()
    ctx.moveTo(stroke[0].x, stroke[0].y)
    for (let i = 1; i < stroke.length; i++) {
      ctx.lineTo(stroke[i].x, stroke[i].y)
    }
    ctx.stroke()
  }
}

function emitSave () {
  const dataUrl = canvasRef.value.toDataURL('image/png')
  savedSignature.value = dataUrl
  emit('save', dataUrl)
}
</script>

<style scoped>
.signature-pad-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pad-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.pad-label {
  font-weight: 600;
  font-size: 0.85rem;
  color: #334155;
}

.pad-actions {
  display: flex;
  gap: 8px;
}

.btn-undo, .btn-clear {
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  color: #475569;
  transition: all 0.15s ease;
}

.btn-undo:hover:not(:disabled), .btn-clear:hover:not(:disabled) {
  background: #e2e8f0;
  border-color: #94a3b8;
}

.btn-undo:disabled, .btn-clear:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.canvas-container {
  position: relative;
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  overflow: hidden;
  background: #ffffff;
  transition: border-color 0.2s ease;
  cursor: crosshair;
}

.canvas-container.has-sig {
  border: 2px solid #3b82f6;
  border-style: solid;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.sig-canvas {
  display: block;
  width: 100%;
  touch-action: none;
}

.placeholder-text {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  color: #cbd5e1;
  pointer-events: none;
  font-style: italic;
}

.sig-preview-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 8px;
}

.preview-label {
  font-size: 0.8rem;
  color: #16a34a;
  font-weight: 600;
}

.btn-save {
  padding: 6px 14px;
  background: linear-gradient(135deg, #059669, #16a34a);
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-save:hover { opacity: 0.9; transform: translateY(-1px); }

.saved-display {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px;
  background: #f8fafc;
}

.saved-header {
  font-size: 0.75rem;
  color: #64748b;
  margin-bottom: 6px;
  font-weight: 600;
}

.saved-img {
  max-height: 70px;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  background: white;
}
</style>
