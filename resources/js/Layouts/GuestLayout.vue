<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

const { isDark, toggleTheme } = useTheme();
const themeToggleLabel = computed(() => (isDark.value ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'));
</script>

<template>
    <div
        class="theme-shell flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0"
    >
        <div class="mb-4 flex w-full max-w-md justify-end px-2">
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
        </div>
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-[var(--app-text-muted)]" />
            </Link>
        </div>

        <div
            class="theme-surface mt-6 w-full overflow-hidden px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
