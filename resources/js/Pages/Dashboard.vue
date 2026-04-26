<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    scope: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-[var(--app-text)]">
                    Dashboard
                </h2>
                <p class="text-sm text-[var(--app-text-muted)]">
                    Role aktif: <span class="font-medium text-[var(--app-text)]">{{ scope.role }}</span>
                </p>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto grid max-w-7xl gap-6 sm:px-6 lg:grid-cols-5 lg:px-8">
                <div
                    v-for="(value, key) in stats"
                    :key="key"
                    class="theme-surface overflow-hidden rounded-lg border shadow-sm"
                >
                    <div class="p-6">
                        <p class="text-sm font-medium capitalize text-[var(--app-text-muted)]">
                            {{ key }}
                        </p>
                        <p class="mt-3 text-3xl font-semibold text-[var(--app-text)]">
                            {{ value }}
                        </p>
                    </div>
                </div>

                <div class="theme-surface-muted overflow-hidden rounded-lg border shadow-sm lg:col-span-5">
                    <div class="grid gap-4 p-6 md:grid-cols-3 xl:grid-cols-6">
                        <Link
                            :href="route('institutions.index')"
                            class="theme-btn-secondary rounded-lg px-4 py-3 text-sm font-medium transition"
                        >
                            Kelola Institusi
                        </Link>
                        <Link
                            :href="route('classrooms.index')"
                            class="theme-btn-secondary rounded-lg px-4 py-3 text-sm font-medium transition"
                        >
                            Kelola Kelas
                        </Link>
                        <Link
                            :href="route('students.index')"
                            class="theme-btn-secondary rounded-lg px-4 py-3 text-sm font-medium transition"
                        >
                            Kelola Siswa
                        </Link>
                        <Link
                            :href="route('imports.index')"
                            class="theme-btn-secondary rounded-lg px-4 py-3 text-sm font-medium transition"
                        >
                            Import Data
                        </Link>
                        <Link
                            :href="route('media-assets.index')"
                            class="theme-btn-secondary rounded-lg px-4 py-3 text-sm font-medium transition"
                        >
                            Upload Media
                        </Link>
                        <Link
                            :href="route('card-templates.index')"
                            class="theme-btn-secondary rounded-lg px-4 py-3 text-sm font-medium transition"
                        >
                            Kelola Template
                        </Link>
                        <Link
                            :href="route('generate-batches.index')"
                            class="theme-btn-secondary rounded-lg px-4 py-3 text-sm font-medium transition"
                        >
                            Generate Batch
                        </Link>
                    </div>
                </div>

                <div class="theme-surface-muted overflow-hidden rounded-lg border shadow-sm lg:col-span-5">
                    <div class="p-6 text-[var(--app-text)]">
                        <p class="text-sm font-semibold uppercase tracking-wide">
                            Scope Login
                        </p>
                        <p class="mt-2 text-sm text-[var(--app-text-muted)]">
                            {{
                                scope.institution_id
                                    ? `User terikat ke institution_id ${scope.institution_id}.`
                                    : 'User ini memiliki cakupan lintas instansi.'
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
