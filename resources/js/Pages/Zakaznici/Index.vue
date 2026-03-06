<template>
    <Head title="Zákazníci" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Zákazníci</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Vyhľadávanie</label>
                        <input type="text" v-model="filters.search" @input="debounceSearch"
                            placeholder="Meno alebo email..."
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Aktivovaný</label>
                        <select v-model="filters.activated" @change="applyFilters"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">Všetci</option>
                            <option value="1">Aktivovaní</option>
                            <option value="0">Neaktivovaní</option>
                        </select>
                    </div>
                    <button @click="resetFilters" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 underline">
                        Zrušiť filtre
                    </button>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meno</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <SortableHeader field="date_added" :current-sort="filters.sort" :current-direction="filters.direction" @sort="handleSort">Registrácia</SortableHeader>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Metóda</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktivovaný</th>
                                <SortableHeader field="pocet_objednavok" :current-sort="filters.sort" :current-direction="filters.direction" align="center" @sort="handleSort">Obj.</SortableHeader>
                                <SortableHeader field="celkova_suma" :current-sort="filters.sort" :current-direction="filters.direction" align="right" @sort="handleSort">Celk. suma</SortableHeader>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="z in zakaznici.data" :key="z.customer_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ z.firstname }} {{ z.lastname }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ z.email }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatDate(z.date_added) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <StatusBadge :value="registrationMethod(z)" type="method" />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <StatusBadge :value="!!z.active" true-label="Áno" false-label="Nie" />
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ z.pocet_objednavok }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatCurrency(z.celkova_suma) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('zakaznici.show', z.customer_id)"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">
                                        Detail →
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!zakaznici.data?.length">
                                <td colspan="8" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Žiadni zákazníci</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="zakaznici.links"
                    :from="zakaznici.from"
                    :to="zakaznici.to"
                    :total="zakaznici.total"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import SortableHeader from '@/Components/SortableHeader.vue'

const props = defineProps({
    zakaznici: Object,
    filters: Object,
})

const filters = reactive({
    search:    props.filters?.search    ?? '',
    activated: props.filters?.activated ?? 'all',
    sort:      props.filters?.sort      ?? 'date_added',
    direction: props.filters?.direction ?? 'desc',
})

let searchTimer = null
function debounceSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
    router.get(route('zakaznici'), filters, { preserveState: true, replace: true })
}

function resetFilters() {
    Object.assign(filters, { search: '', activated: 'all', sort: 'date_added', direction: 'desc' })
    applyFilters()
}

function handleSort({ field, direction }) {
    filters.sort = field
    filters.direction = direction
    applyFilters()
}

function registrationMethod(z) {
    if (z.google_id) return 'google'
    if (z.apple_id) return 'apple'
    return 'email'
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatCurrency(v) {
    return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}
</script>
