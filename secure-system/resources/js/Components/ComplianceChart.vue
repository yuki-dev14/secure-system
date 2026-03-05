<script setup>
import { ref, onMounted, watch } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    chartType: { type: String, default: 'line' }, // 'line' | 'bar' | 'pie' | 'doughnut'
    chartData: { type: Object, required: true },
    options:   { type: Object, default: () => ({}) },
    height:    { type: Number, default: 260 },
    title:     { type: String, default: '' },
});

const canvasRef = ref(null);
let chartInstance = null;

const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            labels: {
                color: '#94a3b8',
                font: { family: 'Inter', size: 12 },
                boxWidth: 12,
                padding: 16,
            },
        },
        tooltip: {
            backgroundColor: 'rgba(15,23,42,0.95)',
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
            titleColor: '#f1f5f9',
            bodyColor: '#94a3b8',
            padding: 12,
            titleFont: { family: 'Inter', weight: 'bold' },
            bodyFont: { family: 'Inter' },
        },
    },
    scales: props.chartType === 'pie' || props.chartType === 'doughnut'
        ? {}
        : {
            x: {
                grid:  { color: 'rgba(255,255,255,0.04)' },
                ticks: { color: '#64748b', font: { family: 'Inter', size: 11 } },
            },
            y: {
                grid:  { color: 'rgba(255,255,255,0.04)' },
                ticks: { color: '#64748b', font: { family: 'Inter', size: 11 } },
                min: 0,
                max: 100,
            },
        },
};

function buildChart() {
    if (!canvasRef.value) return;
    if (chartInstance) {
        chartInstance.destroy();
        chartInstance = null;
    }
    const ctx = canvasRef.value.getContext('2d');
    chartInstance = new Chart(ctx, {
        type: props.chartType,
        data: props.chartData,
        options: { ...baseOptions, ...props.options },
    });
}

onMounted(buildChart);

watch(() => [props.chartData, props.chartType], buildChart, { deep: true });
</script>

<template>
    <div class="cc-shell">
        <p v-if="title" class="cc-title">{{ title }}</p>
        <div class="cc-canvas-wrap" :style="`height:${height}px`">
            <canvas ref="canvasRef"></canvas>
        </div>
    </div>
</template>

<style scoped>
.cc-shell { display: flex; flex-direction: column; gap: 0.5rem; width: 100%; }
.cc-title {
    font-size: 0.8125rem; font-weight: 700; color: #64748b;
    margin: 0; text-transform: uppercase; letter-spacing: 0.05em;
}
.cc-canvas-wrap { position: relative; width: 100%; }
.cc-canvas-wrap canvas { width: 100% !important; height: 100% !important; }
</style>
