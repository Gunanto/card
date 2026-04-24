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
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
                <p class="text-sm text-gray-500">
                    Role aktif: <span class="font-medium text-gray-700">{{ scope.role }}</span>
                </p>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto grid max-w-7xl gap-6 sm:px-6 lg:grid-cols-5 lg:px-8">
                <div
                    v-for="(value, key) in stats"
                    :key="key"
                    class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                >
                    <div class="p-6">
                        <p class="text-sm font-medium capitalize text-gray-500">
                            {{ key }}
                        </p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">
                            {{ value }}
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-sky-200 bg-sky-50 shadow-sm lg:col-span-5">
                    <div class="grid gap-4 p-6 md:grid-cols-3 xl:grid-cols-6">
                        <Link
                            :href="route('institutions.index')"
                            class="rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100"
                        >
                            Kelola Institusi
                        </Link>
                        <Link
                            :href="route('classrooms.index')"
                            class="rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100"
                        >
                            Kelola Kelas
                        </Link>
                        <Link
                            :href="route('students.index')"
                            class="rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100"
                        >
                            Kelola Siswa
                        </Link>
                        <Link
                            :href="route('imports.index')"
                            class="rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100"
                        >
                            Import Data
                        </Link>
                        <Link
                            :href="route('media-assets.index')"
                            class="rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100"
                        >
                            Upload Media
                        </Link>
                        <Link
                            :href="route('card-templates.index')"
                            class="rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100"
                        >
                            Kelola Template
                        </Link>
                        <Link
                            :href="route('generate-batches.index')"
                            class="rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100"
                        >
                            Generate Batch
                        </Link>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-emerald-200 bg-emerald-50 shadow-sm lg:col-span-5">
                    <div class="p-6 text-emerald-950">
                        <p class="text-sm font-semibold uppercase tracking-wide">
                            Scope Login
                        </p>
                        <p class="mt-2 text-sm">
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
