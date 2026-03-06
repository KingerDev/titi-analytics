<template>
    <Head :title="`Objednávka #${objednavka.order_id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('objednavky')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    ← Objednávky
                </Link>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Objednávka #{{ objednavka.order_id }}</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Zákazník -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Zákazník</h3>
                        <p class="font-medium text-gray-900 dark:text-white">{{ objednavka.meno_zakaznika || '—' }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ objednavka.zakaznik_email }}</p>
                        <Link v-if="objednavka.customer_id" :href="route('zakaznici.show', objednavka.customer_id)"
                            class="mt-2 inline-block text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                            Profil zákazníka →
                        </Link>
                    </div>

                    <!-- Platba -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Platba</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Metóda: <span class="text-gray-900 dark:text-white font-medium">{{ objednavka.payment_method || '—' }}</span></p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Dátum: <span class="text-gray-900 dark:text-white font-medium">{{ formatDate(objednavka.date_added) }}</span></p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Doprava: <span class="text-gray-900 dark:text-white font-medium">{{ formatCurrency(objednavka.cena_dopravy) }}</span></p>
                    </div>

                    <!-- Suma -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 flex flex-col justify-center items-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Celková suma</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(objednavka.total_sdph) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">vrátane DPH</p>
                    </div>
                </div>

                <!-- Produkty -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Položky objednávky</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produkt</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Počet</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Jednotková cena</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Celkom</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="p in polozky" :key="p.order_product_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ p.name }}</p>
                                    <p class="text-xs text-gray-400">ID: {{ p.product_id }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">{{ p.quantity }}x</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(p.jprice_sdph) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(p.price_sdph) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link v-if="p.product_id" :href="route('produkty.show', p.product_id)"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">
                                        Analytika →
                                    </Link>
                                </td>
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

defineProps({
    objednavka: Object,
    polozky: Array,
})

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatCurrency(v) {
    return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}
</script>
