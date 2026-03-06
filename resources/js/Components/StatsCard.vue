<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ label }}</p>
                <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ formattedValue }}</p>
                <p v-if="sublabel" class="mt-1 text-sm text-gray-400 dark:text-gray-500">{{ sublabel }}</p>

                <!-- Trend badge -->
                <div v-if="trend !== null && trend !== undefined"
                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium"
                    :class="trendClass"
                >
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="trend > 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
                        <path v-else-if="trend < 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"/>
                    </svg>
                    <span>{{ trendLabel }}</span>
                </div>
            </div>
            <div v-if="icon" class="p-3 rounded-full shrink-0 ml-3" :class="iconBg">
                <component :is="icon" class="w-6 h-6" :class="iconColor" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    label:     String,
    value:     [Number, String],
    sublabel:  String,
    trend:     { type: Number, default: null },   // % zmena, null = nezobrazovať
    icon:      Object,
    iconBg:    { type: String, default: 'bg-blue-50 dark:bg-blue-900/30' },
    iconColor: { type: String, default: 'text-blue-600 dark:text-blue-400' },
    currency:  { type: Boolean, default: false },
})

const formattedValue = computed(() => {
    if (props.currency) {
        return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(props.value)
    }
    if (typeof props.value === 'number') {
        return new Intl.NumberFormat('sk-SK').format(props.value)
    }
    return props.value
})

const trendClass = computed(() => {
    if (props.trend === null || props.trend === undefined) return ''
    if (props.trend > 0)  return 'text-green-600 dark:text-green-400'
    if (props.trend < 0)  return 'text-red-500 dark:text-red-400'
    return 'text-gray-400 dark:text-gray-500'
})

const trendLabel = computed(() => {
    if (props.trend === null || props.trend === undefined) return ''
    const abs = Math.abs(props.trend)
    const sign = props.trend > 0 ? '+' : props.trend < 0 ? '−' : ''
    return `${sign}${abs}% vs predch. obdobie`
})
</script>
