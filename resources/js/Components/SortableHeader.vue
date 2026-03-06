<template>
    <th
        @click="toggle"
        class="px-4 py-3 text-xs font-medium uppercase tracking-wider cursor-pointer select-none group"
        :class="[alignClass, isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300']"
    >
        <div class="inline-flex items-center gap-1" :class="align === 'right' ? 'justify-end w-full' : align === 'center' ? 'justify-center w-full' : ''">
            <slot />
            <span class="transition-opacity" :class="isActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-50'">
                <svg v-if="isActive && currentDirection === 'asc'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                </svg>
                <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </div>
    </th>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    field: String,
    currentSort: String,
    currentDirection: { type: String, default: 'asc' },
    align: { type: String, default: 'left' }, // left | center | right
})

const emit = defineEmits(['sort'])

const isActive = computed(() => props.currentSort === props.field)

const alignClass = computed(() => ({
    'text-left': props.align === 'left',
    'text-center': props.align === 'center',
    'text-right': props.align === 'right',
}))

function toggle() {
    const direction = isActive.value && props.currentDirection === 'asc' ? 'desc' : 'asc'
    emit('sort', { field: props.field, direction })
}
</script>
