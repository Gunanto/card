<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

const showingNavigationDropdown = ref(false);
const page = usePage();

const navItems = computed(() => {
    const items = [
        { label: 'Dashboard', route: 'dashboard' },
        { label: 'Institusi', route: 'institutions.index' },
        { label: 'Kelas', route: 'classrooms.index' },
        { label: 'Siswa', route: 'students.index' },
        { label: 'Imports', route: 'imports.index' },
        { label: 'Media', route: 'media-assets.index' },
        { label: 'Template', route: 'card-templates.index' },
        { label: 'Generate', route: 'generate-batches.index' },
    ];

    if (page.props.auth?.user?.role === 'admin') {
        items.push({ label: 'Users', route: 'users.index' });
    }

    if (page.props.auth?.user?.role === 'guru') {
        items.push({ label: 'Ketentuan', route: 'guru.ketentuan' });
    }

    return items;
});

const flashStatus = computed(() => page.props.flash?.status ?? null);
const { isDark, toggleTheme } = useTheme();
const themeToggleLabel = computed(() => (isDark.value ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'));
</script>

<template>
    <div>
        <div class="theme-shell">
            <nav
                class="theme-surface border-b"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-[var(--app-text)]"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
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

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <button
                                type="button"
                                class="theme-btn-secondary inline-flex h-9 w-9 items-center justify-center rounded-full p-0"
                                :aria-label="themeToggleLabel"
                                @click="toggleTheme"
                            >
                                <svg
                                    v-if="isDark"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-4 w-4"
                                >
                                    <circle cx="12" cy="12" r="4.5" />
                                    <path d="M12 2.75v2.5M12 18.75v2.5M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M2.75 12h2.5M18.75 12h2.5M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77" />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-4 w-4"
                                >
                                    <path d="M20.354 14.604A9 9 0 1 1 9.396 3.646a7 7 0 1 0 10.958 10.958Z" />
                                </svg>
                            </button>
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-4 text-[var(--app-text-muted)] transition duration-150 ease-in-out hover:text-[var(--app-text)] focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                type="button"
                                class="theme-btn-secondary me-2 inline-flex h-9 w-9 items-center justify-center rounded-full p-0"
                                :aria-label="themeToggleLabel"
                                @click="toggleTheme"
                            >
                                <svg
                                    v-if="isDark"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-4 w-4"
                                >
                                    <circle cx="12" cy="12" r="4.5" />
                                    <path d="M12 2.75v2.5M12 18.75v2.5M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M2.75 12h2.5M18.75 12h2.5M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77" />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-4 w-4"
                                >
                                    <path d="M20.354 14.604A9 9 0 1 1 9.396 3.646a7 7 0 1 0 10.958 10.958Z" />
                                </svg>
                            </button>
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="theme-btn-secondary inline-flex items-center justify-center rounded-md p-2 text-[var(--app-text-muted)] transition duration-150 ease-in-out hover:text-[var(--app-text)] focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
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

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-[var(--app-border)] pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-[var(--app-text)]"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-[var(--app-text-muted)]">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="theme-surface border-b border-[var(--app-border)] shadow-sm"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <div
                    v-if="flashStatus"
                    class="mx-auto mt-6 max-w-7xl rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 sm:px-6 lg:px-8"
                >
                    {{ flashStatus }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
