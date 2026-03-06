<template>
    <Head :title="produkt.nazov || `Produkt #${produkt.product_id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('produkty')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    ← Produkty
                </Link>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {{ produkt.nazov || `Produkt #${produkt.product_id}` }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Metrics -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                    <StatsCard label="Performance score" :value="produkt.performance_score ?? '—'" />
                    <StatsCard label="Predaje" :value="produkt.sales ?? 0" icon-bg="bg-green-50 dark:bg-green-900/30" />
                    <StatsCard label="Návštevy" :value="produkt.visited ?? 0" icon-bg="bg-blue-50 dark:bg-blue-900/30" />
                    <StatsCard label="Cena (s DPH)" :value="produkt.mopcena" :currency="true" icon-bg="bg-yellow-50 dark:bg-yellow-900/30" />
                    <StatsCard label="Objednávky" :value="pocetObjednavok" icon-bg="bg-purple-50 dark:bg-purple-900/30" />
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Info -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Informácie o produkte</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">ID produktu</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white font-mono">{{ produkt.product_id }}</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                                <StatusBadge :value="produkt.status == 1" true-label="Aktívny" false-label="Neaktívny" />
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">V e-shope</span>
                                <StatusBadge :value="produkt.titi_eshop == 1" true-label="Áno" false-label="Nie" />
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Množstvo na sklade</span>
                                <span class="text-sm font-medium" :class="produkt.quantity > 1 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ produkt.quantity }} ks
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Popis</span>
                                <StatusBadge :value="produkt.ma_popis == 1" true-label="Má popis" false-label="Bez popisu" />
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">AI enrichment</span>
                                <StatusBadge
                                    :value="produkt.ma_popis == 1 && produkt.popis?.includes('<')"
                                    true-label="Má AI obsah"
                                    false-label="Bez AI obsahu"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Popis -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Popis produktu</h3>
                        <div v-if="produkt.popis"
                            class="text-sm text-gray-600 dark:text-gray-400 max-h-64 overflow-y-auto prose dark:prose-invert prose-sm max-w-none"
                            v-html="produkt.popis"
                        />
                        <p v-else class="text-sm text-gray-400 dark:text-gray-500 italic">Produkt nemá popis</p>
                    </div>
                </div>

                <!-- Posledné objednávky -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Posledné objednávky s týmto produktom</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Objednávka</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Zákazník</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Dátum</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Počet</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cena</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="o in poslednehObjednavky" :key="o.order_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">#{{ o.order_id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ o.meno_zakaznika || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatDate(o.date_added) }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">{{ o.quantity }}x</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(o.price_sdph) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('objednavky.show', o.order_id)"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Detail →
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!poslednehObjednavky?.length">
                                <td colspan="6" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500">Žiadne objednávky</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatsCard from '@/Components/StatsCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

defineProps({
    produkt: Object,
    pocetObjednavok: Number,
    poslednehObjednavky: Array,
})

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatCurrency(v) {
    return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}
</script>
