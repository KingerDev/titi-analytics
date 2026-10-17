<template>
    <Head title="AI vo vyhľadávaní" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">AI vo vyhľadávaní – oplatí sa?</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Náklady AI oproti zisku z objednávok produktov, ku ktorým zákazník prišiel z výsledkov AI.
                    Zisk aj tržba bez DPH, náklady prepočítané kurzom {{ usdEur }} €/$.
                </p>
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

                <div v-if="!ready" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-5 text-sm text-amber-800 dark:text-amber-300">
                    E-shop ešte nezapisuje cestu od hľadania k objednávke – chýba tabuľka <code>titi_search_order</code>
                    alebo stĺpec <code>titi_search_log.visitor_id</code> (SQL na produkcii a nasadenie e-shopu).
                </div>

                <template v-else>
                    <!-- Karty -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatsCard label="Náklady AI" :value="eur(naklady.spolu)"
                            :sublabel="`Vyhľadávanie ${eur(naklady.vyhladavanie)} · aktualizácia produktov ${eur(naklady.aktualizacia)}`"
                            icon-bg="bg-purple-50 dark:bg-purple-900/30" icon-color="text-purple-600 dark:text-purple-400" />
                        <StatsCard label="Zisk z výsledkov AI" :value="eur(funnel.ai.zisk)"
                            :sublabel="`${funnel.ai.objednavok} objednávok · tržba ${eur(funnel.ai.trzba)}`"
                            icon-bg="bg-green-50 dark:bg-green-900/30" icon-color="text-green-600 dark:text-green-400" />
                        <StatsCard label="Bilancia (zisk − náklady)" :value="eur(bilancia)"
                            :sublabel="naklady.spolu > 0 ? `Na 1 € nákladov ${eur(funnel.ai.zisk / naklady.spolu)} zisku` : 'Bez nákladov za obdobie'"
                            :icon-bg="bilancia >= 0 ? 'bg-green-50 dark:bg-green-900/30' : 'bg-red-50 dark:bg-red-900/30'"
                            :icon-color="bilancia >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" />
                        <StatsCard label="AI ukázalo výsledky" :value="funnel.ai.zobrazene"
                            :sublabel="funnel.ai.zobrazene ? `${pct(funnel.ai.klik, funnel.ai.zobrazene)} % s klikom · ${pct(funnel.ai.objednane, funnel.ai.zobrazene)} % s objednávkou` : 'Zatiaľ nič (režim shadow?)'"
                            icon-bg="bg-indigo-50 dark:bg-indigo-900/30" icon-color="text-indigo-600 dark:text-indigo-400" />
                    </div>

                    <!-- Lievik -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Od hľadania k objednávke</h3>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Počet hľadaní, ktoré došli do daného kroku (% zo zobrazených). Košík a objednávka sa rátajú,
                                aj keď ich zákazník spravil z detailu produktu – do 24 h, resp. 7 dní od kliku.
                                AI výsledky sa ukazujú len pri prázdnom hľadaní, takže zisk z AI je predaj, ktorý by inak takmer určite nebol.
                            </p>
                        </div>
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Krok</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Záchrana AI</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Bežné vyhľadávanie (web)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="row in funnelRows" :key="row.key">
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ row.label }}</td>
                                    <td v-for="src in ['ai', 'web']" :key="src" class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">
                                        {{ row.format(funnel[src][row.key]) }}
                                        <span v-if="row.ratio" class="text-xs text-gray-400 ml-1">({{ pct(funnel[src][row.key], funnel[src].zobrazene) }} %)</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Kontrolná skupina -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">S AI vs. bez AI (kontrolná skupina)</h3>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Návštevníci, ktorým hľadanie nenašlo nič – koľkí z nich do 7 dní objednali čokoľvek.
                                Rozdiel je predaj, ktorý AI naozaj pridá (nielen presunie).
                            </p>
                        </div>
                        <div v-if="!holdout" class="px-5 py-6 text-sm text-gray-400">
                            Kontrolná skupina zatiaľ nebežala. Zapína sa v e-shope cez <code>TITI_AI_HOLDOUT</code> (% návštevníkov bez AI) v režime <code>all</code>.
                        </div>
                        <template v-else>
                            <table class="min-w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Skupina</th>
                                        <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Návštevníkov s prázdnym hľadaním</th>
                                        <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Objednali do 7 dní</th>
                                        <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Zisk na návštevníka</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="g in holdoutRows" :key="g.key">
                                        <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ g.label }}</td>
                                        <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ g.navstevnikov }}</td>
                                        <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">
                                            {{ g.objednali }} <span class="text-xs text-gray-400">({{ g.konverzia ?? '–' }} %)</span>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ g.zisk_na_navstevnika !== null ? eur(g.zisk_na_navstevnika) : '–' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-if="holdout.bez_ai.navstevnikov < 100" class="px-5 py-3 text-xs text-amber-600 dark:text-amber-400 border-t border-gray-100 dark:border-gray-700">
                                V kontrolnej skupine je zatiaľ len {{ holdout.bez_ai.navstevnikov }} návštevníkov – rozdiel môže byť náhoda, počkaj na aspoň ~100.
                            </p>
                        </template>
                    </div>

                    <!-- Po mesiacoch -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Po mesiacoch</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Posledných 6 mesiacov ku koncu obdobia, len záchrana AI</p>
                        </div>
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Mesiac</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Náklady</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">AI ukázalo</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">S klikom</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Objednávok</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Zisk</th>
                                    <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Bilancia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="m in mesiace" :key="m.mesiac">
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ m.mesiac }}</td>
                                    <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ eur(m.naklady) }}</td>
                                    <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ m.zobrazene }}</td>
                                    <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ m.s_klikom }}</td>
                                    <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ m.objednavok }}</td>
                                    <td class="px-5 py-3 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ eur(m.zisk) }}</td>
                                    <td class="px-5 py-3 text-sm text-right tabular-nums font-semibold"
                                        :class="m.bilancia >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ eur(m.bilancia) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- AI zarába -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Kde AI zarába</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Hľadania, pri ktorých produkty od AI skončili v objednávke</p>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Hľadanie</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Objednávok</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Tržba</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Zisk</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <tr v-for="q in zarabajuce" :key="q.query">
                                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">{{ q.query }}</td>
                                            <td class="px-4 py-2.5 text-sm text-right tabular-nums text-gray-900 dark:text-white">{{ q.objednavok }}</td>
                                            <td class="px-4 py-2.5 text-sm text-right tabular-nums text-gray-500 dark:text-gray-400">{{ eur(q.trzba) }}</td>
                                            <td class="px-4 py-2.5 text-sm text-right tabular-nums text-green-600 dark:text-green-400 font-semibold">{{ eur(q.zisk) }}</td>
                                        </tr>
                                        <tr v-if="!zarabajuce?.length">
                                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">Zatiaľ žiadna objednávka z výsledkov AI</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Nezmysly -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Kde AI nikoho nezaujalo</h3>
                                <p class="text-xs text-gray-400 mt-0.5">AI ukázalo produkty aspoň 3×, nikto neklikol – nezmysel alebo zlé poradie</p>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Hľadanie</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Ukázané</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Produktov</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <tr v-for="q in nezmysly" :key="q.query">
                                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">{{ q.query }}</td>
                                            <td class="px-4 py-2.5 text-sm text-right tabular-nums text-red-600 dark:text-red-400">{{ q.zobrazene }}×</td>
                                            <td class="px-4 py-2.5 text-sm text-right tabular-nums text-gray-500 dark:text-gray-400">{{ q.produktov }}</td>
                                        </tr>
                                        <tr v-if="!nezmysly?.length">
                                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-400">Nič také – na výsledky AI sa kliká</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>

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
    ready: Boolean, // e-shop už zapisuje cestu hľadanie → objednávka
    obdobie: Object,
    usdEur: Number,
    naklady: Object,
    funnel: Object,
    holdout: Object, // null, kým kontrolná skupina nebežala
    mesiace: Array,
    zarabajuce: Array,
    nezmysly: Array,
})

// --- Obdobie ---
const period = reactive({
    date_from: props.obdobie?.date_from ?? '',
    date_to: props.obdobie?.date_to ?? '',
})

const presets = [
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
    router.get(route('vyhladavanie.ai'), { ...period }, { preserveState: false, replace: true })
}

// --- Formátovanie ---
function eur(v) {
    return Number(v || 0).toLocaleString('sk-SK', { style: 'currency', currency: 'EUR' })
}

function pct(part, whole) {
    return whole ? Math.round((part / whole) * 100) : 0
}

const bilancia = computed(() => (props.funnel?.ai.zisk ?? 0) - (props.naklady?.spolu ?? 0))

const funnelRows = [
    { key: 'zobrazene', label: 'Ukázalo výsledky', format: v => v },
    { key: 'klik', label: 'Otvoril produkt', format: v => v, ratio: true },
    { key: 'kosik', label: 'Pridal do košíka', format: v => v, ratio: true },
    { key: 'objednane', label: 'Objednal', format: v => v, ratio: true },
    { key: 'objednavok', label: 'Objednávok', format: v => v },
    { key: 'kusov', label: 'Kusov', format: v => v },
    { key: 'trzba', label: 'Tržba bez DPH', format: eur },
    { key: 'zisk', label: 'Zisk bez DPH', format: eur },
]

const holdoutRows = computed(() => props.holdout ? [
    { key: 'ai', label: 'S AI', ...props.holdout.ai },
    { key: 'bez_ai', label: 'Bez AI (kontrolná)', ...props.holdout.bez_ai },
] : [])
</script>
