<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import DarkModeToggle from '@/Components/DarkModeToggle.vue'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import ToastNotification from '@/Components/ToastNotification.vue'

const showingNavigationDropdown = ref(false)

const navItems = [
    { label: 'Dashboard', route: 'dashboard' },
    { label: 'Registrácie', route: 'registracie' },
    { label: 'Objednávky', route: 'objednavky' },
    { label: 'Produkty', route: 'produkty' },
    { label: 'Zákazníci', route: 'zakaznici' },
]

function logout() {
    router.post(route('logout'))
}
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center me-4">
                                <Link :href="route('dashboard')" class="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                                    Analytika
                                </Link>
                            </div>
                            <div class="hidden space-x-1 sm:-my-px sm:flex sm:items-center">
                                <NavLink
                                    v-for="item in navItems"
                                    :key="item.route"
                                    :href="route(item.route)"
                                    :active="route().current(item.route)"
                                >
                                    {{ item.label }}
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">
                            <DarkModeToggle />
                            <button
                                @click="logout"
                                class="px-3 py-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Odhlásiť
                            </button>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <DarkModeToggle />
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="ms-1 inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            v-for="item in navItems"
                            :key="item.route"
                            :href="route(item.route)"
                            :active="route().current(item.route)"
                        >
                            {{ item.label }}
                        </ResponsiveNavLink>
                    </div>
                    <div class="border-t border-gray-200 pb-3 pt-4 dark:border-gray-600">
                        <div class="px-4">
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                Odhlásiť
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="bg-white shadow dark:bg-gray-800">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>

        <!-- In-app toast notifikácie -->
        <ToastNotification />
    </div>
</template>
