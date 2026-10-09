<template>
    <Head title="Vyhľadávanie" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Vyhľadávanie</h2>
                <span class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"
                            :class="{ 'animate-ping': liveOk }"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full"
                            :class="liveOk ? 'bg-green-500' : 'bg-gray-400'"></span>
                    </span>
                    Naživo · dnes {{ today.hladani }} hľadaní, {{ today.prazdnych }} bez výsledkov,
                    {{ today.zachranitelnych }} by zachránilo AI, {{ usd(today.naklady) }}
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Obdobie -->
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
                </div>

                <!-- Karty -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatsCard label="Hľadaní" :value="stats.total" :trend="trends.total"
                        sublabel="Web aj appka, prvé načítanie výsledkov"
                        icon-bg="bg-indigo-50 dark:bg-indigo-900/30" icon-color="text-indigo-600 dark:text-indigo-400" />
                    <StatsCard label="Bez výsledkov" :value="stats.zero" :trend="trends.zero"
                        :sublabel="stats.zeroRate !== null ? `${stats.zeroRate} % hľadaní` : 'Žiadne hľadania'"
                        icon-bg="bg-red-50 dark:bg-red-900/30" icon-color="text-red-600 dark:text-red-400" />
                    <StatsCard label="AI by zachránilo" :value="stats.rescuable"
                        :sublabel="stats.zero ? `${pct(stats.rescuable, stats.zero)} % prázdnych hľadaní · zobrazené ${stats.shown}×` : 'Z prázdnych hľadaní'"
                        icon-bg="bg-green-50 dark:bg-green-900/30" icon-color="text-green-600 dark:text-green-400" />
                    <StatsCard label="Náklady AI" :value="usd(stats.aiCost)"
                        :sublabel="aiSublabel"
                        icon-bg="bg-purple-50 dark:bg-purple-900/30" icon-color="text-purple-600 dark:text-purple-400" />
                </div>

                <!-- Konverzie web vs AI -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Záujem o výsledky</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Otvorenie produktu a pridanie do košíka priamo z výsledkov hľadania</p>
                    </div>
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Zdroj výsledkov</th>
                                <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Otvorení produktu</th>
                                <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Pridaní do košíka</th>
                                <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Hľadaní s pridaním do košíka</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="row in eventRows" :key="row.key">
                                <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ row.label }}</td>
                                <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ row.click }}</td>
                                <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ row.cart }}</td>
                                <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-500 dark:text-gray-400">{{ row.cart_hladani }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Denný graf -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Denný trend</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Všetky hľadania a z nich tie bez výsledkov</p>
                        </div>
                        <div class="flex gap-3 text-xs">
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-indigo-500 rounded"></span> S výsledkami</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-red-500 rounded"></span> Bez výsledkov</span>
                        </div>
                    </div>
                    <div v-if="dailyTrend?.length" class="flex items-end gap-1 h-48">
                        <div v-for="d in dailyChart" :key="d.den" class="flex-1 group relative flex flex-col justify-end gap-px">
                            <div class="bg-red-500 dark:bg-red-400 rounded-t" :style="{ height: d.zeroHeight + '%' }"></div>
                            <div class="bg-indigo-500 dark:bg-indigo-400 rounded-b" :style="{ height: d.okHeight + '%' }"></div>
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs px-2 py-1 rounded whitespace-nowrap z-10">
                                {{ d.den }}: {{ d.hladani }} ({{ d.prazdnych }} bez výsledkov)
                            </div>
                        </div>
                    </div>
                    <div v-else class="h-48 flex items-center justify-center text-sm text-gray-400">
                        Žiadne dáta za toto obdobie
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Bez výsledkov -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Hľadania bez výsledkov</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Čo zákazníci hľadajú a web nenájde – a či by pomohlo AI</p>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Hľadanie</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Krát</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">AI našlo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="q in topZero" :key="q.query" @click="openCompare(q.last_id)"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer">
                                        <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">{{ q.query }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ q.pocet }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums">
                                            <span v-if="q.ai_count === null" class="text-gray-400">–</span>
                                            <span v-else :class="q.ai_count > 0 ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-400'">{{ q.ai_count }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!topZero?.length">
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-400">Žiadne hľadania bez výsledkov</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Rozdiely web vs AI -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Kde sa web a AI líšia</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Koľko produktov AI je aj v top 10 webu – nízka zhoda = kandidát na opravu radenia</p>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Hľadanie</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Krát</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Zhoda</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="d in differences" :key="d.query" @click="openCompare(d.last_id)"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer">
                                        <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">{{ d.query }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ d.pocet }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums">
                                            <span :class="d.zhoda < 34 ? 'text-red-600 dark:text-red-400 font-semibold' : d.zhoda < 67 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400'">
                                                {{ d.zhoda }} %
                                            </span>
                                            <span class="text-xs text-gray-400 ml-1">({{ d.spolocnych }}/{{ d.ai_count }})</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!differences?.length">
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-400">Zatiaľ žiadne porovnania</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top hľadania -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Najčastejšie hľadania</h3>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Hľadanie</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Krát</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Výsledkov</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="q in topQueries" :key="q.query" @click="openCompare(q.last_id)"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer">
                                        <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">{{ q.query }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ q.pocet }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums"
                                            :class="Number(q.vysledkov) === 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'">{{ q.vysledkov }}</td>
                                    </tr>
                                    <tr v-if="!topQueries?.length">
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-400">Žiadne dáta za toto obdobie</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Naživo -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Naživo</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Posledné hľadania, obnovuje sa každých 10 s</p>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Čas</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Hľadanie</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Web</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">AI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="r in liveRows" :key="r.id" @click="openCompare(r.id)"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer transition-colors duration-1000"
                                        :class="{ 'bg-green-50 dark:bg-green-900/20': freshIds.has(r.id) }">
                                        <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">{{ formatTime(r.created_at) }}</td>
                                        <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">{{ r.query }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums"
                                            :class="r.results_count === 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-900 dark:text-white'">{{ r.results_count }}</td>
                                        <td class="px-4 py-2.5 text-sm text-right tabular-nums text-gray-500 dark:text-gray-400">
                                            <span v-if="r.ai_error" class="text-red-500" title="Agent zlyhal">!</span>
                                            <span v-else-if="r.ai_count === null" title="Agent ešte nebežal alebo je vypnutý">–</span>
                                            <span v-else :class="{ 'text-green-600 dark:text-green-400 font-semibold': r.results_count === 0 && r.ai_count > 0 }">{{ r.ai_count }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!liveRows.length">
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">Zatiaľ žiadne hľadania</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Porovnanie web vs AI pre jedno hľadanie -->
        <Modal :show="compare.open" max-width="4xl" @close="compare.open = false">
            <div class="p-6">
                <div v-if="compare.loading" class="py-10 text-center text-sm text-gray-400">Načítavam…</div>
                <template v-else-if="compare.data">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">„{{ compare.data.query }}“</h3>
                    <p class="text-xs text-gray-400 mt-0.5 mb-4">
                        Web našiel {{ compare.data.results_count }} produktov (top 10 nižšie)
                        <template v-if="compare.data.ai_ms !== null"> · AI {{ compare.data.ai_ms }} ms{{ compare.data.ai_cached ? ' (z cache)' : '' }}</template>
                        · <span class="text-green-600 dark:text-green-400">zelené</span> = našiel aj druhý zdroj
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="col in compareColumns" :key="col.key">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ col.label }}</h4>
                            <ol class="space-y-1.5">
                                <li v-for="(p, i) in compare.data[col.key]" :key="p.product_id" class="flex items-start gap-2 text-sm">
                                    <span class="text-xs text-gray-400 tabular-nums w-5 text-right pt-0.5">{{ i + 1 }}.</span>
                                    <a :href="`${eshopBase}/produkt/detail/${p.product_id}`" target="_blank" rel="noopener"
                                        class="flex-1 hover:underline"
                                        :class="p.v_druhom ? 'text-green-700 dark:text-green-400' : 'text-gray-900 dark:text-white'">{{ p.name }}</a>
                                    <span v-if="p.kliky || p.kosik" class="text-xs text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                                        {{ p.kliky }}× otvorené · {{ p.kosik }}× košík
                                    </span>
                                </li>
                                <li v-if="!compare.data[col.key].length" class="text-sm text-gray-400">{{ col.empty }}</li>
                            </ol>
                        </div>
                    </div>
                </template>
                <div v-else class="py-10 text-center text-sm text-gray-400">Porovnanie sa nepodarilo načítať</div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive, computed, ref, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatsCard from '@/Components/StatsCard.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    stats: Object,
    trends: Object,
    events: Object,
    dailyTrend: Array,
    topQueries: Array,
    topZero: Array,
    differences: Array,
    live: Array,
    obdobie: Object,
})

const eshopBase = 'https://titi.shopweb.sk'

// --- Obdobie ---
const period = reactive({
    date_from: props.obdobie?.date_from ?? '',
    date_to: props.obdobie?.date_to ?? '',
})

const presets = [
    { label: 'Dnes', days: 0 },
    { label: '7 dní', days: 7 },
    { label: '30 dní', days: 30 },
    { label: '90 dní', days: 90 },
]

function isoDate(d) {
    return d.toISOString().slice(0, 10)
}

function presetRange(days) {
    const to = new Date()
    const from = new Date()
    if (days > 0) from.setDate(from.getDate() - (days - 1))
    return [isoDate(from), isoDate(to)]
}

function setPreset({ days }) {
    ;[period.date_from, period.date_to] = presetRange(days)
    applyPeriod()
}

function isActivePreset({ days }) {
    const [from, to] = presetRange(days)
    return period.date_from === from && period.date_to === to
}

function applyPeriod() {
    router.get(route('vyhladavanie'), { ...period }, { preserveState: false, replace: true })
}

// --- Formátovanie ---
function usd(v) {
    return '$' + Number(v || 0).toFixed(v >= 1 ? 2 : 4)
}

function pct(part, whole) {
    return whole ? Math.round((part / whole) * 100) : 0
}

function formatTime(ts) {
    const d = new Date(String(ts).replace(' ', 'T'))
    const today = new Date().toDateString() === d.toDateString()
    return d.toLocaleString('sk-SK', today
        ? { hour: '2-digit', minute: '2-digit', second: '2-digit' }
        : { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const aiSublabel = computed(() => {
    const s = props.stats
    if (!s.aiChecked) return 'AI zatiaľ nebežalo'
    const parts = [`${s.aiChecked} hľadaní`]
    if (s.aiAvgMs !== null) parts.push(`⌀ ${s.aiAvgMs} ms`)
    if (s.aiCacheRate !== null) parts.push(`cache ${s.aiCacheRate} %`)
    if (s.aiErrors) parts.push(`${s.aiErrors} chýb`)
    return parts.join(' · ')
})

const eventRows = computed(() => [
    { key: 'web', label: 'Bežné vyhľadávanie (web)', ...props.events.web },
    { key: 'ai', label: 'Záchrana AI („možno hľadáte“)', ...props.events.ai },
])

const dailyChart = computed(() => {
    if (!props.dailyTrend?.length) return []
    const max = Math.max(...props.dailyTrend.map(d => d.hladani), 1)
    return props.dailyTrend.map(d => {
        const ok = d.hladani - d.prazdnych
        return {
            ...d,
            okHeight: Math.max(Math.round((ok / max) * 100), ok ? 2 : 0),
            zeroHeight: Math.max(Math.round((d.prazdnych / max) * 100), d.prazdnych ? 2 : 0),
        }
    })
})

// --- Naživo (polling) ---
const liveRows = ref([...(props.live ?? [])])
const freshIds = ref(new Set())
const liveOk = ref(true)
const today = reactive({ hladani: 0, prazdnych: 0, zachranitelnych: 0, naklady: 0 })
let pollTimer = null

async function poll() {
    if (document.visibilityState !== 'visible') return // na pozadí nezaťažuj DB
    try {
        const sinceId = liveRows.value[0]?.id ?? 0
        const res = await fetch(route('vyhladavanie.live', { since_id: sinceId }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        if (!res.ok) throw new Error(res.status)
        const data = await res.json()
        Object.assign(today, data.today)
        if (data.rows.length && sinceId) {
            const ids = data.rows.map(r => r.id)
            freshIds.value = new Set(ids)
            setTimeout(() => { freshIds.value = new Set() }, 4000)
            liveRows.value = [...data.rows, ...liveRows.value].slice(0, 50)
        }
        liveOk.value = true
    } catch {
        liveOk.value = false
    }
}

onMounted(() => {
    poll()
    pollTimer = setInterval(poll, 10_000)
})

onUnmounted(() => clearInterval(pollTimer))

// --- Porovnanie web vs AI ---
const compare = reactive({ open: false, loading: false, data: null })
const compareColumns = [
    { key: 'web', label: 'Web (fulltext)', empty: 'Web nenašiel nič' },
    { key: 'ai', label: 'AI agent', empty: 'AI nenašlo nič relevantné (alebo nebežalo)' },
]

async function openCompare(id) {
    if (!id) return
    Object.assign(compare, { open: true, loading: true, data: null })
    try {
        const res = await fetch(route('vyhladavanie.porovnanie', { id }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        compare.data = res.ok ? await res.json() : null
    } catch {
        compare.data = null
    } finally {
        compare.loading = false
    }
}
</script>
