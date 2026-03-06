<template>
    <!-- Toast container — fixed vpravo dole -->
    <Teleport to="body">
        <div class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 w-80 pointer-events-none">
            <TransitionGroup
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="translate-x-full opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-full opacity-0"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex items-start gap-3 rounded-xl border shadow-lg p-4 cursor-pointer"
                    :class="toast.type === 'order'
                        ? 'bg-white dark:bg-gray-800 border-blue-200 dark:border-blue-700'
                        : 'bg-white dark:bg-gray-800 border-green-200 dark:border-green-700'"
                    @click="remove(toast.id)"
                >
                    <!-- Ikona -->
                    <div class="shrink-0 rounded-full p-1.5 mt-0.5"
                        :class="toast.type === 'order'
                            ? 'bg-blue-100 dark:bg-blue-900/40'
                            : 'bg-green-100 dark:bg-green-900/40'"
                    >
                        <!-- Košík (objednávka) -->
                        <svg v-if="toast.type === 'order'" class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3A1 1 0 006 17h12m-5 3a1 1 0 11-2 0 1 1 0 012 0zm6 0a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                        <!-- Osoba (registrácia) -->
                        <svg v-else class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>

                    <!-- Text -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold"
                            :class="toast.type === 'order'
                                ? 'text-blue-700 dark:text-blue-300'
                                : 'text-green-700 dark:text-green-300'"
                        >{{ toast.title }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 truncate mt-0.5">{{ toast.body }}</p>
                    </div>

                    <!-- X -->
                    <button class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xs mt-0.5">✕</button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const toasts = ref([])
let nextId = 0
let pollInterval = null
let maxOrderId = 0
let maxCustomerId = 0

function add(type, title, body) {
    const id = ++nextId
    toasts.value.push({ id, type, title, body })
    setTimeout(() => remove(id), 6000)
}

function remove(id) {
    toasts.value = toasts.value.filter(t => t.id !== id)
}

async function poll() {
    try {
        const res = await fetch(
            `/notifications/poll?since_order_id=${maxOrderId}&since_customer_id=${maxCustomerId}`,
            { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
        )
        if (!res.ok) return
        const data = await res.json()

        // Aktualizuj max IDčka
        if (data.max_order_id)    maxOrderId    = data.max_order_id
        if (data.max_customer_id) maxCustomerId = data.max_customer_id

        // Zobraz toasty pre nové objednávky
        for (const o of (data.orders ?? [])) {
            const suma = new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(o.total_sdph ?? 0)
            add('order', `Nová objednávka #${o.order_id}`, `${o.meno?.trim() || 'Neznámy zákazník'} · ${suma}`)
        }

        // Zobraz toasty pre nové registrácie
        for (const r of (data.registrations ?? [])) {
            const meno = [r.firstname, r.lastname].filter(Boolean).join(' ')
            add('registration', 'Nová registrácia', meno || r.email)
        }
    } catch {
        // Ticho ignoruj sieťové chyby
    }
}

onMounted(async () => {
    // Prvé volanie — inicializuj max IDčka bez zobrazenia toastov
    await poll()
    // Potom polluj každých 30 sekúnd
    pollInterval = setInterval(poll, 30_000)
})

onUnmounted(() => {
    clearInterval(pollInterval)
})
</script>
