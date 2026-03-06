<template>
    <Head title="Produkty" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Produkty</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Stats bar (globálne štatistiky pre e-shop produkty) -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-3 flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">V e-shope celkom</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ formatNumber(produktyStats.celkomVEshope) }}</span>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-orange-200 dark:border-orange-800/50 px-5 py-3 flex items-center justify-between">
                        <span class="text-sm text-orange-600 dark:text-orange-400">Bez popisu</span>
                        <div class="text-right">
                            <span class="text-lg font-bold text-orange-600 dark:text-orange-400">{{ formatNumber(produktyStats.bezPopisu) }}</span>
                            <span class="ml-1 text-xs text-gray-400">({{ bezPopisuPct }}%)</span>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-red-200 dark:border-red-800/50 px-5 py-3 flex items-center justify-between">
                        <span class="text-sm text-red-600 dark:text-red-400">Bez predajov</span>
                        <div class="text-right">
                            <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ formatNumber(produktyStats.bezPredajov) }}</span>
                            <span class="ml-1 text-xs text-gray-400">({{ bezPredajovPct }}%)</span>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4">
                    <div class="flex flex-wrap gap-3 items-end">
                        <!-- Search -->
                        <div class="flex-1 min-w-48">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Vyhľadávanie</label>
                            <input type="text" v-model="filters.search" @input="debounceSearch"
                                placeholder="Názov alebo ID produktu..."
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        </div>

                        <!-- V e-shope toggle -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rýchly filter</label>
                            <button
                                @click="toggleVEshope"
                                :class="[
                                    'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border transition-colors',
                                    filters.v_eshope === '1'
                                        ? 'bg-indigo-600 border-indigo-600 text-white hover:bg-indigo-700'
                                        : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                V e-shope
                            </button>
                        </div>

                        <!-- Individuálne filtre — iba keď v_eshope=0 -->
                        <template v-if="filters.v_eshope !== '1'">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                                <select v-model="filters.status" @change="applyFilters"
                                    class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="all">Všetky</option>
                                    <option value="1">Aktívny</option>
                                    <option value="0">Neaktívny</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Titi e-shop</label>
                                <select v-model="filters.titi_eshop" @change="applyFilters"
                                    class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="all">Všetky</option>
                                    <option value="1">Áno</option>
                                    <option value="0">Nie</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cena</label>
                                <select v-model="filters.ma_cenu" @change="applyFilters"
                                    class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="all">Všetky</option>
                                    <option value="1">Má cenu</option>
                                    <option value="0">Bez ceny</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sklad</label>
                                <select v-model="filters.na_sklade" @change="applyFilters"
                                    class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="all">Všetky</option>
                                    <option value="1">Na sklade</option>
                                    <option value="0">Nie na sklade</option>
                                </select>
                            </div>
                        </template>

                        <!-- Popis -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Popis</label>
                            <select v-model="filters.ma_popis" @change="applyFilters"
                                class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="all">Všetky</option>
                                <option value="1">Má popis</option>
                                <option value="0">Bez popisu</option>
                            </select>
                        </div>

                        <!-- Bez predajov -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Predaje</label>
                            <select v-model="filters.bez_predajov" @change="applyFilters"
                                class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">Všetky</option>
                                <option value="1">Bez predajov (0)</option>
                            </select>
                        </div>

                        <button @click="resetFilters" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 underline">
                            Zrušiť filtre
                        </button>
                    </div>

                    <p v-if="filters.v_eshope === '1'" class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                        Zobrazujú sa produkty: status=1, titi_eshop=1, mopcena&gt;0, quantity&gt;1 · Celkom: <strong>{{ produkty.total }}</strong>
                    </p>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <SortableHeader field="nazov" :current-sort="filters.sort" :current-direction="filters.direction" @sort="handleSort">Názov</SortableHeader>
                                <SortableHeader field="performance_score" :current-sort="filters.sort" :current-direction="filters.direction" align="center" @sort="handleSort">Perf. score</SortableHeader>
                                <SortableHeader field="sales" :current-sort="filters.sort" :current-direction="filters.direction" align="center" @sort="handleSort">Predaje</SortableHeader>
                                <SortableHeader field="visited" :current-sort="filters.sort" :current-direction="filters.direction" align="center" @sort="handleSort">Návštevy</SortableHeader>
                                <SortableHeader field="mopcena" :current-sort="filters.sort" :current-direction="filters.direction" align="center" @sort="handleSort">Cena</SortableHeader>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-shop</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Popis</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="p in produkty.data" :key="p.product_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ p.nazov || '(bez názvu)' }}</p>
                                    <p class="text-xs text-gray-400">ID: {{ p.product_id }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-sm font-medium" :class="scoreColor(p.performance_score)">
                                        {{ p.performance_score ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-sm" :class="p.sales ? 'text-gray-600 dark:text-gray-400' : 'text-red-500 dark:text-red-400 font-medium'">
                                    {{ p.sales ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ formatNumber(p.visited) }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ formatCurrency(p.mopcena) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <StatusBadge :value="p.status == 1" true-label="Aktívny" false-label="Neaktívny" />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <StatusBadge :value="p.titi_eshop == 1" true-label="Áno" false-label="Nie" />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <StatusBadge :value="p.ma_popis == 1" true-label="Má" false-label="Bez" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('produkty.show', p.product_id)"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">
                                        Detail →
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!produkty.data?.length">
                                <td colspan="9" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">Žiadne produkty</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="produkty.links"
                    :from="produkty.from"
                    :to="produkty.to"
                    :total="produkty.total"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import SortableHeader from '@/Components/SortableHeader.vue'

const props = defineProps({
    produkty: Object,
    produktyStats: Object,
    filters: Object,
})

const filters = reactive({
    search:      props.filters?.search      ?? '',
    v_eshope:    props.filters?.v_eshope    ?? '1',
    status:      props.filters?.status      ?? 'all',
    titi_eshop:  props.filters?.titi_eshop  ?? 'all',
    ma_cenu:     props.filters?.ma_cenu     ?? 'all',
    na_sklade:   props.filters?.na_sklade   ?? 'all',
    ma_popis:    props.filters?.ma_popis    ?? 'all',
    bez_predajov: props.filters?.bez_predajov ?? '',
    sort:        props.filters?.sort        ?? 'product_id',
    direction:   props.filters?.direction   ?? 'desc',
})

const bezPopisuPct = computed(() => {
    if (!props.produktyStats?.celkomVEshope) return 0
    return Math.round((props.produktyStats.bezPopisu / props.produktyStats.celkomVEshope) * 100)
})

const bezPredajovPct = computed(() => {
    if (!props.produktyStats?.celkomVEshope) return 0
    return Math.round((props.produktyStats.bezPredajov / props.produktyStats.celkomVEshope) * 100)
})

let searchTimer = null
function debounceSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
    router.get(route('produkty'), filters, { preserveState: true, replace: true })
}

function toggleVEshope() {
    filters.v_eshope = filters.v_eshope === '1' ? '0' : '1'
    if (filters.v_eshope === '1') {
        Object.assign(filters, { status: 'all', titi_eshop: 'all', ma_cenu: 'all', na_sklade: 'all' })
    }
    applyFilters()
}

function resetFilters() {
    Object.assign(filters, {
        search: '', v_eshope: '1',
        status: 'all', titi_eshop: 'all', ma_cenu: 'all', na_sklade: 'all',
        ma_popis: 'all', bez_predajov: '',
        sort: 'product_id', direction: 'desc',
    })
    applyFilters()
}

function handleSort({ field, direction }) {
    filters.sort = field
    filters.direction = direction
    applyFilters()
}

function scoreColor(score) {
    if (score === null || score === undefined) return 'text-gray-400 dark:text-gray-500'
    if (score >= 70) return 'text-green-600 dark:text-green-400'
    if (score >= 40) return 'text-yellow-600 dark:text-yellow-400'
    return 'text-red-600 dark:text-red-400'
}

function formatCurrency(v) {
    return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}

function formatNumber(v) {
    if (!v) return '0'
    return new Intl.NumberFormat('sk-SK').format(v)
}
</script>
