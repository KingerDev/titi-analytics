<template>
    <Head title="Registrácie" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Registrácie</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                    <StatsCard label="Celkom" :value="stats.celkom" />
                    <StatsCard label="Aktivovaných" :value="stats.aktivovanych" icon-bg="bg-green-50 dark:bg-green-900/30" icon-color="text-green-600" />
                    <StatsCard label="Neaktivovaných" :value="stats.neaktivovanych" icon-bg="bg-red-50 dark:bg-red-900/30" icon-color="text-red-600" />
                    <StatsCard label="Email" :value="stats.email" icon-bg="bg-blue-50 dark:bg-blue-900/30" icon-color="text-blue-600" />
                    <StatsCard label="Google" :value="stats.google" icon-bg="bg-orange-50 dark:bg-orange-900/30" icon-color="text-orange-600" />
                    <StatsCard label="Apple" :value="stats.apple" icon-bg="bg-gray-50 dark:bg-gray-700" icon-color="text-gray-600" />
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Aktivovaný</label>
                        <select v-model="filters.activated" @change="applyFilters"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">Všetci</option>
                            <option value="1">Aktivovaní</option>
                            <option value="0">Neaktivovaní</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Metóda</label>
                        <select v-model="filters.method" @change="applyFilters"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">Všetky</option>
                            <option value="email">Email</option>
                            <option value="google">Google</option>
                            <option value="apple">Apple</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Od dátumu</label>
                        <input type="date" v-model="filters.date_from" @change="applyFilters"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Do dátumu</label>
                        <input type="date" v-model="filters.date_to" @change="applyFilters"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <button @click="resetFilters" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline">
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
                                <SortableHeader field="date_added" :current-sort="filters.sort" :current-direction="filters.direction" @sort="handleSort">Dátum registrácie</SortableHeader>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Metóda</th>
                                <SortableHeader field="active" :current-sort="filters.sort" :current-direction="filters.direction" @sort="handleSort">Aktivovaný</SortableHeader>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="z in zakaznici.data" :key="z.customer_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ z.firstname }} {{ z.lastname }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ z.email }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    {{ formatDate(z.date_added) }}
                                </td>
                                <td class="px-4 py-3">
                                    <StatusBadge :value="registrationMethod(z)" type="method" />
                                </td>
                                <td class="px-4 py-3">
                                    <StatusBadge :value="!!z.active" true-label="Aktivovaný" false-label="Neaktivovaný" />
                                </td>
                            </tr>
                            <tr v-if="!zakaznici.data?.length">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Žiadne záznamy</td>
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
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatsCard from '@/Components/StatsCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import SortableHeader from '@/Components/SortableHeader.vue'

const props = defineProps({
    zakaznici: Object,
    stats: Object,
    filters: Object,
})

const filters = reactive({
    activated:  props.filters?.activated  ?? 'all',
    method:     props.filters?.method     ?? 'all',
    date_from:  props.filters?.date_from  ?? '',
    date_to:    props.filters?.date_to    ?? '',
    sort:       props.filters?.sort       ?? 'date_added',
    direction:  props.filters?.direction  ?? 'desc',
})

function applyFilters() {
    router.get(route('registracie'), filters, { preserveState: true, replace: true })
}

function resetFilters() {
    Object.assign(filters, { activated: 'all', method: 'all', date_from: '', date_to: '', sort: 'date_added', direction: 'desc' })
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

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>
