<template>
    <Head title="LLM návštevy" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">LLM návštevy</h2>
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

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatsCard
                        label="Celkom návštev"
                        :value="stats.totalVisits"
                        :trend="trends.total"
                        icon-bg="bg-indigo-50 dark:bg-indigo-900/30"
                        icon-color="text-indigo-600 dark:text-indigo-400"
                    />
                    <StatsCard
                        label="AI boty (crawlery)"
                        :value="stats.botVisits"
                        :trend="trends.bot"
                        sublabel="GPTBot, ClaudeBot, …"
                        icon-bg="bg-blue-50 dark:bg-blue-900/30"
                        icon-color="text-blue-600 dark:text-blue-400"
                    />
                    <StatsCard
                        label="Ľudia z LLM"
                        :value="stats.humanVisits"
                        :trend="trends.human"
                        sublabel="Kliky z ChatGPT, Claude, Perplexity…"
                        icon-bg="bg-green-50 dark:bg-green-900/30"
                        icon-color="text-green-600 dark:text-green-400"
                    />
                    <StatsCard
                        label="Unikátnych zdrojov"
                        :value="stats.uniqueSources"
                        sublabel="Rôznych LLM platforiem"
                        icon-bg="bg-purple-50 dark:bg-purple-900/30"
                        icon-color="text-purple-600 dark:text-purple-400"
                    />
                </div>

                <!-- Denný graf bot vs human -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Denný trend</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Modrá = AI boti, Zelená = ľudia z LLM</p>
                        </div>
                        <div class="flex gap-3 text-xs">
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-blue-500 rounded"></span> Bot</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-green-500 rounded"></span> Človek</span>
                        </div>
                    </div>
                    <div v-if="dailyTrend?.length" class="flex items-end gap-1 h-48">
                        <div v-for="d in dailyChart" :key="d.den"
                            class="flex-1 group relative flex flex-col justify-end gap-px">
                            <div class="bg-green-500 dark:bg-green-400 rounded-t transition-opacity hover:opacity-80"
                                :style="{ height: d.humanHeight + '%' }"
                                :title="`${d.den}: ${d.human} ľudí`">
                            </div>
                            <div class="bg-blue-500 dark:bg-blue-400 rounded-b transition-opacity hover:opacity-80"
                                :style="{ height: d.botHeight + '%' }"
                                :title="`${d.den}: ${d.bot} botov`">
                            </div>
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs px-2 py-1 rounded whitespace-nowrap z-10">
                                {{ d.den }}: {{ d.bot + d.human }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="h-48 flex items-center justify-center text-sm text-gray-400">
                        Žiadne dáta za toto obdobie
                    </div>
                </div>

                <!-- Rozdelenie podľa zdroja + Top stránky -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- By source -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Podľa zdroja</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Ktoré LLM ťa najviac navštevujú</p>
                        </div>
                        <div class="p-5 space-y-2">
                            <div v-for="s in bySourceWithBars" :key="s.source + s.kind"
                                class="group">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0"
                                            :class="s.kind === 'bot' ? 'bg-blue-500' : 'bg-green-500'"></span>
                                        <span class="font-medium text-gray-900 dark:text-white truncate">{{ s.label }}</span>
                                        <span class="text-xs text-gray-400 flex-shrink-0">
                                            {{ s.kind === 'bot' ? 'bot' : 'klik' }}
                                        </span>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white tabular-nums ml-2">{{ s.pocet }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full transition-all"
                                        :class="s.kind === 'bot' ? 'bg-blue-500' : 'bg-green-500'"
                                        :style="{ width: s.percent + '%' }"></div>
                                </div>
                            </div>
                            <p v-if="!bySource?.length" class="text-center text-sm text-gray-400 py-6">
                                Žiadne dáta za toto obdobie
                            </p>
                        </div>
                    </div>

                    <!-- Top paths -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Top stránky</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Ktoré stránky LLM/AI najviac vidí</p>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Stránka</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-blue-600 dark:text-blue-400">Bot</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-green-600 dark:text-green-400">Človek</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Spolu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="p in topPaths" :key="p.path"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-4 py-2.5">
                                            <a :href="eshopBase + p.path" target="_blank" rel="noopener"
                                                class="text-sm text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 font-mono truncate block max-w-xs"
                                                :title="p.path">
                                                {{ p.path }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-right text-blue-600 dark:text-blue-400 tabular-nums">{{ p.bot_pocet }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right text-green-600 dark:text-green-400 tabular-nums">{{ p.human_pocet }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right font-semibold text-gray-900 dark:text-white tabular-nums">{{ p.pocet }}</td>
                                    </tr>
                                    <tr v-if="!topPaths?.length">
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">
                                            Žiadne dáta za toto obdobie
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatsCard from '@/Components/StatsCard.vue'

const props = defineProps({
    stats: Object,
    trends: Object,
    dailyTrend: Array,
    bySource: Array,
    topPaths: Array,
    obdobie: Object,
})

const eshopBase = 'https://titi.shopweb.sk'

const period = reactive({
    date_from: props.obdobie?.date_from ?? '',
    date_to: props.obdobie?.date_to ?? '',
})

const presets = [
    { label: 'Dnes', days: 0 },
    { label: '7 dní', days: 7 },
    { label: '30 dní', days: 30 },
    { label: '90 dní', days: 90 },
    { label: 'Rok', days: 365 },
]

function isoDate(d) {
    return d.toISOString().slice(0, 10)
}

function setPreset({ days }) {
    const to = new Date()
    const from = new Date()
    if (days === 0) {
        period.date_from = isoDate(to)
        period.date_to = isoDate(to)
    } else {
        from.setDate(from.getDate() - (days - 1))
        period.date_from = isoDate(from)
        period.date_to = isoDate(to)
    }
    applyPeriod()
}

function isActivePreset({ days }) {
    const to = new Date()
    const from = new Date()
    if (days === 0) {
        return period.date_from === isoDate(to) && period.date_to === isoDate(to)
    }
    from.setDate(from.getDate() - (days - 1))
    return period.date_from === isoDate(from) && period.date_to === isoDate(to)
}

function applyPeriod() {
    router.get(route('llm-visits'), {
        date_from: period.date_from,
        date_to: period.date_to,
    }, { preserveState: false, replace: true })
}

// Denný graf — bot/human stacked, normalizovaný max
const dailyChart = computed(() => {
    if (!props.dailyTrend?.length) return []
    const max = Math.max(...props.dailyTrend.map(d => d.bot + d.human), 1)
    return props.dailyTrend.map(d => ({
        ...d,
        botHeight: Math.max(Math.round((d.bot / max) * 100), d.bot ? 2 : 0),
        humanHeight: Math.max(Math.round((d.human / max) * 100), d.human ? 2 : 0),
    }))
})

// By source s percentom voči najvyššej hodnote
const bySourceWithBars = computed(() => {
    if (!props.bySource?.length) return []
    const max = Math.max(...props.bySource.map(s => s.pocet), 1)
    return props.bySource.map(s => ({
        ...s,
        percent: Math.round((s.pocet / max) * 100),
    }))
})

function formatDate(d) {
    if (!d) return '—'
    return new Date(d + 'T00:00:00').toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>
