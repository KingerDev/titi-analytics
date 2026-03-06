<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Dashboard</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Period selector -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rýchly výber</p>
                        <div class="flex gap-1">
                            <button v-for="p in presets" :key="p.label"
                                @click="setPreset(p)"
                                :class="[
                                    'px-3 py-1.5 text-xs rounded-lg border transition-colors',
                                    isActivePreset(p)
                                        ? 'bg-indigo-600 border-indigo-600 text-white'
                                        : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                ]"
                            >{{ p.label }}</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Od</label>
                        <input type="date" v-model="period.date_from" @change="applyPeriod"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Do</label>
                        <input type="date" v-model="period.date_to" @change="applyPeriod"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 self-center">
                        {{ formatDate(period.date_from) }} – {{ formatDate(period.date_to) }}
                    </p>
                </div>

                <!-- Stats — obdobie -->
                <div>
                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2 px-1">Za vybrané obdobie</p>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatsCard
                            label="Objednávky"
                            :value="stats.objednavkyObdobi"
                            :trend="trends.objednavky"
                            icon-bg="bg-blue-50 dark:bg-blue-900/30"
                            icon-color="text-blue-600 dark:text-blue-400"
                        />
                        <StatsCard
                            label="Tržba"
                            :value="stats.trzbaObdobi"
                            :currency="true"
                            :trend="trends.trzba"
                            icon-bg="bg-green-50 dark:bg-green-900/30"
                            icon-color="text-green-600 dark:text-green-400"
                        />
                        <StatsCard
                            label="Nové registrácie"
                            :value="stats.noveRegistracie"
                            :trend="trends.registracie"
                            icon-bg="bg-orange-50 dark:bg-orange-900/30"
                            icon-color="text-orange-600 dark:text-orange-400"
                        />
                        <StatsCard
                            label="Priem. objednávka"
                            :value="stats.priemObjednavky"
                            :currency="true"
                            :trend="trends.priem"
                            icon-bg="bg-purple-50 dark:bg-purple-900/30"
                            icon-color="text-purple-600 dark:text-purple-400"
                        />
                    </div>
                </div>

                <!-- Stats — globálne -->
                <div>
                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2 px-1">Celkové štatistiky</p>
                    <div class="grid grid-cols-3 gap-4">
                        <StatsCard
                            label="Zákazníci celkom"
                            :value="stats.celkemZakaznikov"
                            :sublabel="`${stats.aktivovanych} aktivovaných`"
                            icon-bg="bg-blue-50 dark:bg-blue-900/30"
                            icon-color="text-blue-600 dark:text-blue-400"
                        />
                        <StatsCard
                            label="Aktívne produkty"
                            :value="stats.aktivneProduktov"
                            sublabel="v e-shope"
                            icon-bg="bg-purple-50 dark:bg-purple-900/30"
                            icon-color="text-purple-600 dark:text-purple-400"
                        />
                        <StatsCard
                            label="Aktivačná miera"
                            :value="activationRate + '%'"
                            icon-bg="bg-yellow-50 dark:bg-yellow-900/30"
                            icon-color="text-yellow-600 dark:text-yellow-400"
                        />
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Objednávky</h3>
                        <div class="h-40 flex items-end gap-px">
                            <template v-for="(item, i) in objednavkyChart" :key="i">
                                <div class="flex-1 group relative">
                                    <div
                                        class="w-full bg-indigo-500 dark:bg-indigo-600 rounded-t-sm min-h-[2px] hover:bg-indigo-400 transition-colors"
                                        :style="{ height: `${item.height}%` }"
                                    />
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block z-10 whitespace-nowrap bg-gray-900 text-white text-xs rounded px-2 py-1 pointer-events-none">
                                        {{ item.den }}: {{ item.pocet }}
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="mt-2 text-xs text-gray-400 text-center">Každý stĺpec = 1 deň</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Registrácie</h3>
                        <div class="h-40 flex items-end gap-px">
                            <template v-for="(item, i) in registracieChart" :key="i">
                                <div class="flex-1 group relative">
                                    <div
                                        class="w-full bg-green-500 dark:bg-green-600 rounded-t-sm min-h-[2px] hover:bg-green-400 transition-colors"
                                        :style="{ height: `${item.height}%` }"
                                    />
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block z-10 whitespace-nowrap bg-gray-900 text-white text-xs rounded px-2 py-1 pointer-events-none">
                                        {{ item.den }}: {{ item.pocet }}
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="mt-2 text-xs text-gray-400 text-center">Každý stĺpec = 1 deň</p>
                    </div>
                </div>

                <!-- Top tabuľky -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top produkty -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Top 10 produktov</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Podľa predaných kusov za obdobie</p>
                        </div>
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Produkt</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Kusov</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Tržba</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="(p, i) in topProdukty" :key="p.product_id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 font-mono">{{ i + 1 }}</td>
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('produkty.show', p.product_id)"
                                            class="text-sm text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-1">
                                            {{ p.nazov }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-right text-gray-600 dark:text-gray-400">{{ p.kusov }}</td>
                                    <td class="px-4 py-2.5 text-sm text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(p.trzba) }}</td>
                                </tr>
                                <tr v-if="!topProdukty?.length">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">Žiadne dáta za toto obdobie</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Top zákazníci -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Top 10 zákazníkov</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Podľa útrat za obdobie</p>
                        </div>
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Zákazník</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Obj.</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Útrata</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="(z, i) in topZakaznici" :key="z.customer_id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 font-mono">{{ i + 1 }}</td>
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('zakaznici.show', z.customer_id)"
                                            class="text-sm text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                            {{ z.meno }}
                                        </Link>
                                        <p class="text-xs text-gray-400">{{ z.email }}</p>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-right text-gray-600 dark:text-gray-400">{{ z.pocet_obj }}</td>
                                    <td class="px-4 py-2.5 text-sm text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(z.celkova_suma) }}</td>
                                </tr>
                                <tr v-if="!topZakaznici?.length">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">Žiadne dáta za toto obdobie</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatsCard from '@/Components/StatsCard.vue'

const props = defineProps({
    stats: Object,
    trends: Object,
    objednavkyTrend: Array,
    registracieTrend: Array,
    topProdukty: Array,
    topZakaznici: Array,
    obdobie: Object,
})

const period = reactive({
    date_from: props.obdobie?.date_from ?? '',
    date_to:   props.obdobie?.date_to   ?? '',
})

const presets = [
    { label: 'Dnes',   days: 0 },
    { label: '7 dní',  days: 7 },
    { label: '30 dní', days: 30 },
    { label: '90 dní', days: 90 },
    { label: 'Rok',    days: 365 },
]

function isoDate(d) {
    return d.toISOString().slice(0, 10)
}

function setPreset({ days }) {
    const to   = new Date()
    const from = new Date()
    if (days === 0) {
        period.date_from = isoDate(to)
        period.date_to   = isoDate(to)
    } else {
        from.setDate(from.getDate() - (days - 1))
        period.date_from = isoDate(from)
        period.date_to   = isoDate(to)
    }
    applyPeriod()
}

function isActivePreset({ days }) {
    const to   = new Date()
    const from = new Date()
    if (days === 0) {
        return period.date_from === isoDate(to) && period.date_to === isoDate(to)
    }
    from.setDate(from.getDate() - (days - 1))
    return period.date_from === isoDate(from) && period.date_to === isoDate(to)
}

function applyPeriod() {
    router.get(route('dashboard'), {
        date_from: period.date_from,
        date_to:   period.date_to,
    }, { preserveState: false, replace: true })
}

const activationRate = computed(() => {
    if (!props.stats.celkemZakaznikov) return 0
    return Math.round((props.stats.aktivovanych / props.stats.celkemZakaznikov) * 100)
})

function toBarChart(data, key = 'pocet') {
    if (!data?.length) return []
    const max = Math.max(...data.map(d => d[key]), 1)
    return data.map(d => ({
        ...d,
        height: Math.max(Math.round((d[key] / max) * 100), 2),
    }))
}

const objednavkyChart  = computed(() => toBarChart(props.objednavkyTrend))
const registracieChart = computed(() => toBarChart(props.registracieTrend))

function formatDate(d) {
    if (!d) return '—'
    return new Date(d + 'T00:00:00').toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatCurrency(v) {
    return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}
</script>
