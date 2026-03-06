<template>
    <Head title="Objednávky" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Objednávky</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Vyhľadávanie</label>
                        <input
                            type="text"
                            v-model="filters.search"
                            @input="debounceSearch"
                            placeholder="Meno, email, ID objednávky..."
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
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
                    <button @click="resetFilters" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 underline">
                        Zrušiť filtre
                    </button>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <SortableHeader field="order_id" :current-sort="filters.sort" :current-direction="filters.direction" @sort="handleSort">ID</SortableHeader>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Zákazník</th>
                                <SortableHeader field="date_added" :current-sort="filters.sort" :current-direction="filters.direction" @sort="handleSort">Dátum</SortableHeader>
                                <SortableHeader field="total_sdph" :current-sort="filters.sort" :current-direction="filters.direction" align="right" @sort="handleSort">Suma</SortableHeader>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platba</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Položky</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="o in objednavky.data" :key="o.order_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">#{{ o.order_id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ o.meno_zakaznika || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatDate(o.date_added) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(o.total_sdph) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ o.payment_method || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">{{ o.pocet_poloziek }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('objednavky.show', o.order_id)"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Detail →
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!objednavky.data?.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Žiadne objednávky</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="objednavky.links"
                    :from="objednavky.from"
                    :to="objednavky.to"
                    :total="objednavky.total"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SortableHeader from '@/Components/SortableHeader.vue'

const props = defineProps({
    objednavky: Object,
    filters: Object,
})

const filters = reactive({
    search:    props.filters?.search    ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to:   props.filters?.date_to   ?? '',
    sort:      props.filters?.sort      ?? 'date_added',
    direction: props.filters?.direction ?? 'desc',
})

let searchTimer = null
function debounceSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
    router.get(route('objednavky'), filters, { preserveState: true, replace: true })
}

function resetFilters() {
    Object.assign(filters, { search: '', date_from: '', date_to: '', sort: 'date_added', direction: 'desc' })
    applyFilters()
}

function handleSort({ field, direction }) {
    filters.sort = field
    filters.direction = direction
    applyFilters()
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatCurrency(v) {
    return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}
</script>
