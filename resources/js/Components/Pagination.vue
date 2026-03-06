<template>
    <div v-if="links.length > 3" class="flex items-center justify-between mt-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Zobrazené {{ from }}–{{ to }} z {{ total }} záznamov
        </p>
        <div class="flex gap-1">
            <template v-for="link in links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                    :class="link.active
                        ? 'bg-indigo-600 border-indigo-600 text-white'
                        : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                    preserve-scroll
                    v-html="link.label"
                />
                <span
                    v-else
                    class="px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    links: Array,
    from: Number,
    to: Number,
    total: Number,
})
</script>
