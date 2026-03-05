<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="secure-guest-layout">
        <!-- Animated background -->
        <div class="bg-animated">
            <span></span><span></span><span></span>
            <span></span><span></span><span></span>
        </div>

        <!-- Left panel — branding -->
        <div class="brand-panel">
            <div class="brand-content">
                <div class="brand-logo">
                    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M24 4L6 12V24C6 33.94 13.94 43.28 24 46C34.06 43.28 42 33.94 42 24V12L24 4Z" fill="rgba(255,255,255,0.15)" stroke="white" stroke-width="2"/>
                        <path d="M17 24L22 29L31 20" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1 class="brand-title">SECURE</h1>
                <p class="brand-subtitle">System for Enhanced Compliance,<br>Unified Records & Eligibility</p>
                <div class="brand-features">
                    <div class="feature-item">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Role-Based Access Control
                    </div>
                    <div class="feature-item">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Secure QR Verification
                    </div>
                    <div class="feature-item">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Complete Audit Trails
                    </div>
                </div>
            </div>
        </div>

        <!-- Right panel — form slot -->
        <div class="form-panel">
            <div class="form-container">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

* { box-sizing: border-box; }

.secure-guest-layout {
    font-family: 'Inter', sans-serif;
    display: flex;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
    background: #0f172a;
}

/* ─── Animated Background ─── */
.bg-animated {
    position: fixed;
    inset: 0;
    z-index: 0;
    overflow: hidden;
}
.bg-animated span {
    position: absolute;
    border-radius: 50%;
    animation: float 18s infinite;
    opacity: 0.07;
}
.bg-animated span:nth-child(1) { width: 400px; height: 400px; background: #6366f1; top: -100px; left: -100px; animation-delay: 0s; }
.bg-animated span:nth-child(2) { width: 300px; height: 300px; background: #8b5cf6; top: 60%; left: 10%; animation-delay: 3s; }
.bg-animated span:nth-child(3) { width: 250px; height: 250px; background: #3b82f6; top: 30%; left: 40%; animation-delay: 6s; }
.bg-animated span:nth-child(4) { width: 200px; height: 200px; background: #06b6d4; bottom: -50px; right: 20%; animation-delay: 2s; }
.bg-animated span:nth-child(5) { width: 350px; height: 350px; background: #6366f1; top: 10%; right: -50px; animation-delay: 5s; }
.bg-animated span:nth-child(6) { width: 150px; height: 150px; background: #a78bfa; bottom: 20%; left: 50%; animation-delay: 8s; }

@keyframes float {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-40px) scale(1.05); }
}

/* ─── Brand Panel ─── */
.brand-panel {
    position: relative;
    z-index: 1;
    flex: 0 0 45%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    background: linear-gradient(135deg, rgba(99,102,241,0.2) 0%, rgba(139,92,246,0.1) 100%);
    border-right: 1px solid rgba(255,255,255,0.05);
}

@media (max-width: 900px) {
    .brand-panel { display: none; }
}

.brand-content { text-align: center; color: white; }

.brand-logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
}
.brand-logo svg { width: 100%; height: 100%; }

.brand-title {
    font-size: 3rem;
    font-weight: 800;
    letter-spacing: 0.2em;
    background: linear-gradient(135deg, #fff 0%, #a5b4fc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 0.5rem;
}

.brand-subtitle {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.6;
    margin-bottom: 3rem;
}

.brand-features { display: flex; flex-direction: column; gap: 1rem; }

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: rgba(255,255,255,0.8);
    background: rgba(255,255,255,0.05);
    padding: 0.75rem 1.25rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(255,255,255,0.08);
    text-align: left;
}
.feature-item svg { width: 18px; height: 18px; color: #a5b4fc; flex-shrink: 0; }

/* ─── Form Panel ─── */
.form-panel {
    position: relative;
    z-index: 1;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(20px);
}

.form-container {
    width: 100%;
    max-width: 420px;
}
</style>
