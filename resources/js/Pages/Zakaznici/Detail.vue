<template>
    <Head :title="`${zakaznik.firstname} ${zakaznik.lastname}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('zakaznici')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    ← Zákazníci
                </Link>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {{ zakaznik.firstname }} {{ zakaznik.lastname }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <StatsCard label="Objednávky" :value="stats.pocet_objednavok" icon-bg="bg-blue-50 dark:bg-blue-900/30" />
                    <StatsCard label="Celková útrata" :value="stats.celkova_suma" :currency="true" icon-bg="bg-green-50 dark:bg-green-900/30" />
                    <StatsCard label="Body" :value="zakaznik.points ?? 0" icon-bg="bg-yellow-50 dark:bg-yellow-900/30" />
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Stav účtu</p>
                        <div class="mt-2">
                            <StatusBadge :value="!!zakaznik.active" true-label="Aktivovaný" false-label="Neaktivovaný" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Profil -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Profil zákazníka</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Email</span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ zakaznik.email }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Mobil</span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ zakaznik.mobil || '—' }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Registrácia</span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ formatDate(zakaznik.date_added) }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Metóda reg.</span>
                                <StatusBadge :value="registrationMethod" type="method" />
                            </div>
                        </div>
                    </div>

                    <!-- Prázdna karta pre budúci obsah -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Posledné objednávky</h3>
                        <p class="text-sm text-gray-400 dark:text-gray-500">
                            Celkom {{ stats.pocet_objednavok }} objednávok v hodnote {{ formatCurrency(stats.celkova_suma) }}
                        </p>
                    </div>
                </div>

                <!-- Objednávky -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Všetky objednávky</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Dátum</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Platba</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Položky</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Suma</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="o in objednavky" :key="o.order_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">#{{ o.order_id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatDate(o.date_added) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ o.payment_method || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">{{ o.pocet_poloziek }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(o.total_sdph) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('objednavky.show', o.order_id)"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Detail →
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!objednavky?.length">
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
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatsCard from '@/Components/StatsCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
    zakaznik: Object,
    objednavky: Array,
    stats: Object,
})

const registrationMethod = computed(() => {
    if (props.zakaznik.google_id) return 'google'
    if (props.zakaznik.apple_id) return 'apple'
    return 'email'
})

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatCurrency(v) {
    return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}
</script>
