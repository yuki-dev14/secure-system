<template>
  <div class="document-upload">
    <!-- Drop Zone -->
    <div
      :class="['dropzone', { 'dropzone--active': isDragging, 'dropzone--error': hasDropError }]"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
      id="document-dropzone"
    >
      <input
        ref="fileInput"
        type="file"
        class="sr-only"
        :accept="acceptTypes"
        multiple
        @change="handleFileSelect"
        id="document-file-input"
      />
      <div class="dropzone__content">
        <div class="dropzone__icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
          </svg>
        </div>
        <p class="dropzone__title">
          <span v-if="isDragging">Drop files here</span>
          <span v-else>Drag & drop files or <span class="dropzone__browse">browse</span></span>
        </p>
        <p class="dropzone__hint">PDF, JPG, PNG, DOC, DOCX · Max 5 MB per file</p>
      </div>
    </div>
    <p v-if="hasDropError" class="upload-error-msg">{{ dropErrorMessage }}</p>

    <!-- Requirement Type Selector -->
    <div class="form-group mt-4">
      <label class="form-label" for="requirement-type-select">Document Type</label>
      <select
        id="requirement-type-select"
        v-model="selectedType"
        class="form-select"
      >
        <option value="" disabled>Select document type...</option>
        <option v-for="type in requirementTypes" :key="type.value" :value="type.value">
          {{ type.label }}
        </option>
      </select>
    </div>

    <!-- Staged Files List -->
    <div v-if="stagedFiles.length" class="staged-files mt-4">
      <h4 class="staged-files__heading">Files ready to upload ({{ stagedFiles.length }})</h4>
      <div class="staged-file-list">
        <div
          v-for="(sf, idx) in stagedFiles"
          :key="sf.id"
          :class="['staged-file', { 'staged-file--error': sf.error }]"
        >
          <!-- Preview Thumbnail -->
          <div class="staged-file__preview">
            <img v-if="sf.previewUrl" :src="sf.previewUrl" :alt="sf.file.name" class="staged-file__img" />
            <div v-else class="staged-file__icon">
              <span>{{ fileIcon(sf.file) }}</span>
            </div>
          </div>

          <div class="staged-file__info">
            <p class="staged-file__name">{{ sf.file.name }}</p>
            <p class="staged-file__meta">{{ formatBytes(sf.file.size) }} · {{ sf.file.type }}</p>
            <p v-if="sf.error" class="staged-file__error">{{ sf.error }}</p>
          </div>

          <!-- Progress Bar -->
          <div v-if="sf.uploading" class="staged-file__progress-wrap">
            <div class="staged-file__progress-bar" :style="{ width: sf.progress + '%' }"></div>
          </div>
          <span v-if="sf.done" class="staged-file__done">✓</span>

          <button
            v-if="!sf.uploading && !sf.done"
            type="button"
            class="staged-file__remove"
            @click.stop="removeStaged(idx)"
            :aria-label="'Remove ' + sf.file.name"
          >✕</button>
        </div>
      </div>

      <!-- Upload Button -->
      <button
        type="button"
        id="upload-submit-btn"
        :disabled="!selectedType || uploading || allDone"
        class="btn btn--primary mt-3"
        @click="uploadAll"
      >
        <span v-if="uploading">Uploading…</span>
        <span v-else-if="allDone">✓ All Uploaded</span>
        <span v-else>Upload {{ stagedFiles.length }} File(s)</span>
      </button>
    </div>

    <!-- Recent Uploads -->
    <div v-if="uploadedFiles.length" class="uploaded-list mt-5">
      <h4 class="uploaded-list__heading">Recently Uploaded</h4>
      <div v-for="doc in uploadedFiles" :key="doc.id" class="uploaded-item">
        <span class="uploaded-item__type badge" :class="statusBadgeClass('approved')">{{ doc.requirement_type }}</span>
        <span class="uploaded-item__name">{{ doc.file_name }}</span>
        <span class="uploaded-item__size">{{ doc.file_size_human }}</span>
        <span class="uploaded-item__status badge" :class="statusBadgeClass(doc.approval_status)">{{ doc.approval_status }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  beneficiaryId: { type: Number, required: true },
})

const emit = defineEmits(['uploaded'])

const fileInput   = ref(null)
const isDragging  = ref(false)
const hasDropError   = ref(false)
const dropErrorMessage = ref('')
const selectedType   = ref('')
const stagedFiles    = ref([])
const uploadedFiles  = ref([])
const uploading      = ref(false)

const acceptTypes = '.pdf,.jpg,.jpeg,.png,.doc,.docx'

const ALLOWED_TYPES = [
  'application/pdf',
  'image/jpeg',
  'image/png',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]

const requirementTypes = [
  { value: 'birth_certificate', label: 'Birth Certificate' },
  { value: 'school_record',     label: 'School Record' },
  { value: 'health_record',     label: 'Health Record' },
  { value: 'proof_of_income',   label: 'Proof of Income' },
  { value: 'valid_id',          label: 'Valid ID' },
  { value: 'other',             label: 'Other' },
]

const allDone = computed(() => stagedFiles.value.length > 0 && stagedFiles.value.every(f => f.done))

const validateFile = (file) => {
  const maxSize = 5 * 1024 * 1024
  if (!ALLOWED_TYPES.includes(file.type)) {
    return { valid: false, error: `File type "${file.type}" is not allowed.` }
  }
  if (file.size > maxSize) {
    return { valid: false, error: `File "${file.name}" exceeds 5 MB limit.` }
  }
  return { valid: true }
}

const makeStagedFile = async (file) => {
  const validation = validateFile(file)
  let previewUrl = null

  if (file.type.startsWith('image/')) {
    previewUrl = await new Promise(resolve => {
      const reader = new FileReader()
      reader.onload = e => resolve(e.target.result)
      reader.readAsDataURL(file)
    })
  }

  return {
    id: Math.random().toString(36).slice(2),
    file,
    previewUrl,
    error: validation.valid ? null : validation.error,
    uploading: false,
    progress: 0,
    done: false,
  }
}

const triggerFileInput = () => fileInput.value?.click()

const handleFileSelect = async (e) => {
  const files = Array.from(e.target.files || [])
  await addFiles(files)
  e.target.value = ''
}

const handleDrop = async (e) => {
  isDragging.value = false
  const files = Array.from(e.dataTransfer?.files || [])
  await addFiles(files)
}

const addFiles = async (files) => {
  hasDropError.value = false
  if (!files.length) return
  for (const file of files) {
    const sf = await makeStagedFile(file)
    stagedFiles.value.push(sf)
  }
}

const removeStaged = (idx) => {
  stagedFiles.value.splice(idx, 1)
}

const uploadAll = async () => {
  if (!selectedType.value) return
  uploading.value = true

  for (const sf of stagedFiles.value) {
    if (sf.done || sf.error) continue
    sf.uploading = true

    const formData = new FormData()
    formData.append('file', sf.file)
    formData.append('requirement_type', selectedType.value)

    try {
      const response = await axios.post(
        `/requirements/upload/${props.beneficiaryId}`,
        formData,
        {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: (progressEvent) => {
            if (progressEvent.total) {
              sf.progress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            }
          },
        }
      )
      sf.done      = true
      sf.uploading = false
      sf.progress  = 100
      uploadedFiles.value.unshift(response.data.requirement)
      emit('uploaded', response.data.requirement)
    } catch (err) {
      sf.uploading = false
      sf.error = err.response?.data?.message || 'Upload failed. Please retry.'
    }
  }

  uploading.value = false
}

const formatBytes = (bytes) => {
  if (bytes < 1024)       return `${bytes} B`
  if (bytes < 1048576)    return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

const fileIcon = (file) => {
  if (file.type === 'application/pdf') return '📄'
  if (file.type.startsWith('image/'))  return '🖼️'
  return '📝'
}

const statusBadgeClass = (status) => ({
  'badge--pending':  status === 'pending',
  'badge--approved': status === 'approved',
  'badge--rejected': status === 'rejected',
})
</script>

<style scoped>
.document-upload { font-family: 'Inter', sans-serif; }

/* Drop Zone */
.dropzone {
  border: 2px dashed #475569;
  border-radius: 12px;
  padding: 2.5rem 1.5rem;
  text-align: center;
  cursor: pointer;
  transition: all .2s ease;
  background: rgba(30,41,59,.4);
}
.dropzone:hover, .dropzone--active {
  border-color: #6366f1;
  background: rgba(99,102,241,.08);
}
.dropzone--error { border-color: #ef4444; }
.dropzone__content { display: flex; flex-direction: column; align-items: center; gap: .5rem; }
.dropzone__icon svg { width: 2.5rem; height: 2.5rem; color: #6366f1; }
.dropzone__title { color: #cbd5e1; font-size: .95rem; margin: 0; }
.dropzone__browse { color: #6366f1; font-weight: 600; text-decoration: underline; }
.dropzone__hint { color: #64748b; font-size: .8rem; margin: 0; }
.upload-error-msg { color: #ef4444; font-size: .82rem; margin-top: .5rem; }

/* Form */
.form-group { display: flex; flex-direction: column; gap: .4rem; }
.form-label { color: #94a3b8; font-size: .83rem; font-weight: 500; }
.form-select {
  background: rgba(15,23,42,.7); border: 1px solid #334155;
  border-radius: 8px; color: #e2e8f0; padding: .6rem 1rem;
  font-size: .9rem; outline: none; transition: border-color .2s;
}
.form-select:focus { border-color: #6366f1; }

/* Staged Files */
.staged-files__heading, .uploaded-list__heading {
  color: #94a3b8; font-size: .82rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .05em; margin-bottom: .75rem;
}
.staged-file-list { display: flex; flex-direction: column; gap: .6rem; }
.staged-file {
  display: flex; align-items: center; gap: .85rem;
  background: rgba(30,41,59,.6); border: 1px solid #1e293b;
  border-radius: 10px; padding: .65rem 1rem; position: relative;
  transition: border-color .2s;
}
.staged-file--error { border-color: #7f1d1d; background: rgba(127,29,29,.15); }
.staged-file__preview { width: 3rem; height: 3rem; flex-shrink: 0; }
.staged-file__img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
.staged-file__icon {
  width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
  background: rgba(99,102,241,.15); border-radius: 6px; font-size: 1.4rem;
}
.staged-file__info { flex: 1; min-width: 0; }
.staged-file__name { color: #e2e8f0; font-size: .88rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; }
.staged-file__meta { color: #64748b; font-size: .76rem; margin: .1rem 0 0; }
.staged-file__error { color: #f87171; font-size: .76rem; margin: .15rem 0 0; }
.staged-file__progress-wrap {
  position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
  background: #1e293b; border-radius: 0 0 10px 10px; overflow: hidden;
}
.staged-file__progress-bar { height: 100%; background: #6366f1; transition: width .25s; }
.staged-file__done { color: #22c55e; font-size: 1.1rem; }
.staged-file__remove {
  background: transparent; border: none; color: #64748b;
  cursor: pointer; font-size: 1rem; padding: .2rem;
  transition: color .15s;
}
.staged-file__remove:hover { color: #ef4444; }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: .6rem 1.4rem; border-radius: 8px; font-size: .9rem;
  font-weight: 600; cursor: pointer; border: none; transition: all .2s;
}
.btn--primary { background: #6366f1; color: #fff; }
.btn--primary:hover:not(:disabled) { background: #4f46e5; }
.btn--primary:disabled { opacity: .5; cursor: not-allowed; }
.mt-3 { margin-top: .75rem; }
.mt-4 { margin-top: 1rem; }
.mt-5 { margin-top: 1.5rem; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

/* Uploaded list */
.uploaded-item {
  display: flex; align-items: center; gap: .75rem;
  padding: .5rem 0; border-bottom: 1px solid #1e293b; font-size: .86rem;
}
.uploaded-item__name { flex: 1; color: #cbd5e1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.uploaded-item__size { color: #64748b; white-space: nowrap; }

/* Badges */
.badge {
  padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem;
  font-weight: 600; text-transform: capitalize; white-space: nowrap;
}
.badge--pending  { background: #78350f22; color: #fbbf24; border: 1px solid #78350f55; }
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }
</style>
