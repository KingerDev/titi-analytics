<template>
    <button
        type="button"
        :disabled="processing"
        :title="title"
        @click.stop.prevent="swap"
        :class="variant === 'full'
            ? 'inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-wait transition-colors'
            : 'inline-flex items-center justify-center rounded-md p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-wait transition-colors'"
    >
        <!-- Ikona: dve protismerné šípky -->
        <svg class="w-4 h-4 shrink-0" :class="{ 'animate-pulse': processing }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
        </svg>
        <span v-if="variant === 'full'">Prehodiť meno a priezvisko</span>
    </button>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    customerId: { type: Number, required: true },
    variant: { type: String, default: 'icon' }, // 'icon' | 'full'
    title: { type: String, default: 'Prehodiť meno a priezvisko' },
})

const processing = ref(false)

function swap() {
    if (processing.value) return
    processing.value = true

    router.post(route('zakaznici.swap-name', props.customerId), {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { processing.value = false },
    })
}
</script>
