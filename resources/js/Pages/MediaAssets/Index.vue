<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    assets: { type: Array, required: true },
    categories: { type: Array, required: true },
    owners: { type: Object, required: true },
});

const defaultCategory = props.categories[0]?.value ?? 'institution_logo';
const defaultOwnerType = defaultCategory.startsWith('student') ? 'student' : 'institution';

const form = useForm({
    category: defaultCategory,
    owner_type: defaultOwnerType,
    owner_id: '',
    file: null,
});

const ownerOptions = computed(() => props.owners[form.owner_type] ?? []);

watch(
    () => form.category,
    (category) => {
        if (category.startsWith('student')) {
            form.owner_type = 'student';
        } else if (category.startsWith('template')) {
            form.owner_type = 'card_template';
        } else {
            form.owner_type = 'institution';
        }
    },
    { immediate: true },
);

watch(
    ownerOptions,
    (options) => {
        if (!options.some((owner) => Number(owner.id) === Number(form.owner_id))) {
            form.owner_id = options[0]?.id ?? '';
        }
    },
    { immediate: true },
);

const submit = () => {
    form.post(route('media-assets.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
        },
    });
};
</script>

<template>
    <Head title="Media Assets" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Media Assets</h2>
                <p class="text-sm text-gray-500">Upload ke disk private dan akses lewat presigned URL.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.9fr,1.5fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Upload Media</h3>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Kategori</label>
                            <select v-model="form.category" class="w-full rounded-lg border-gray-300 text-sm">
                                <option v-for="category in categories" :key="category.value" :value="category.value">
                                    {{ category.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Owner</label>
                            <select v-model="form.owner_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option v-for="owner in ownerOptions" :key="owner.id" :value="owner.id">
                                    {{ owner.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">File</label>
                            <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" type="file" @input="form.file = $event.target.files[0]" />
                        </div>
                        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                            Upload
                        </button>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Media</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">ID</th>
                                    <th class="px-3 py-2 font-medium">Kategori</th>
                                    <th class="px-3 py-2 font-medium">Owner</th>
                                    <th class="px-3 py-2 font-medium">File</th>
                                    <th class="px-3 py-2 font-medium">Ukuran</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="asset in assets" :key="asset.id">
                                    <td class="px-3 py-3 font-medium text-gray-900">#{{ asset.id }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ asset.category_label }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ asset.owner_label }}</td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ asset.original_name || '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ asset.mime_type }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ asset.size_bytes }} bytes</p>
                                        <p class="text-xs text-gray-500">{{ asset.width || '-' }} x {{ asset.height || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <a :href="asset.download_url" class="rounded-lg border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700">
                                            Presigned Link
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
