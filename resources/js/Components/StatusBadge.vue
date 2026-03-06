<template>
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="classes">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    value: [Boolean, Number, String],
    trueLabel: { type: String, default: 'Áno' },
    falseLabel: { type: String, default: 'Nie' },
    type: { type: String, default: 'boolean' }, // boolean | method | custom
    customLabel: String,
    customColor: String,
})

const label = computed(() => {
    if (props.type === 'custom') return props.customLabel
    if (props.type === 'method') {
        const m = { email: 'Email', google: 'Google', apple: 'Apple' }
        return m[props.value] ?? props.value
    }
    return props.value ? props.trueLabel : props.falseLabel
})

const classes = computed(() => {
    if (props.type === 'custom') return props.customColor
    if (props.type === 'method') {
        const m = {
            google: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            apple: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            email: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        }
        return m[props.value] ?? 'bg-gray-100 text-gray-700'
    }
    return props.value
        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
})
</script>
