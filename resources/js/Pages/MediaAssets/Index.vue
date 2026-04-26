<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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
const downloadProgress = ref({
    active: false,
    label: '',
    percent: 0,
    loaded: 0,
    total: 0,
    error: '',
});
let downloadResetTimer = null;

watch(
    () => form.category,
    (category) => {
        if (category.startsWith('student')) {
            form.owner_type = 'student';
        } else if (category.startsWith('template')) {
            form.owner_type = 'institution';
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

const formatBytes = (value) => {
    const bytes = Number(value) || 0;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`;
};

const safeFilename = (filename, fallback) => {
    const candidate = (filename || '').trim();
    if (candidate === '') return fallback;
    return candidate.replace(/[\\/:*?"<>|]/g, '_');
};

const triggerBrowserDownload = (blob, filename) => {
    const objectUrl = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(objectUrl);
};

const resetDownloadProgressLater = () => {
    if (downloadResetTimer) {
        window.clearTimeout(downloadResetTimer);
    }
    downloadResetTimer = window.setTimeout(() => {
        downloadProgress.value = {
            active: false,
            label: '',
            percent: 0,
            loaded: 0,
            total: 0,
            error: '',
        };
    }, 1500);
};

const downloadAsset = async (asset) => {
    if (!asset?.stream_download_url) return;

    try {
        downloadProgress.value = {
            active: true,
            label: `Downloading ${asset.original_name || `asset-${asset.id}`}`,
            percent: 0,
            loaded: 0,
            total: 0,
            error: '',
        };

        const response = await fetch(asset.stream_download_url, {
            method: 'GET',
            credentials: 'same-origin',
        });

        if (!response.ok || !response.body) {
            throw new Error('Download gagal dijalankan.');
        }

        const total = Number(response.headers.get('content-length') ?? 0);
        const chunks = [];
        let loaded = 0;
        const reader = response.body.getReader();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            if (value) {
                chunks.push(value);
                loaded += value.byteLength;
                const percent = total > 0 ? Math.min(100, Math.round((loaded / total) * 100)) : 0;
                downloadProgress.value = {
                    ...downloadProgress.value,
                    loaded,
                    total,
                    percent,
                };
            }
        }

        const blob = new Blob(chunks, {
            type: response.headers.get('content-type') || 'application/octet-stream',
        });

        downloadProgress.value = {
            ...downloadProgress.value,
            loaded: total > 0 ? total : loaded,
            total: total > 0 ? total : loaded,
            percent: 100,
        };
        triggerBrowserDownload(
            blob,
            safeFilename(asset.original_name, `asset-${asset.id}`),
        );
        resetDownloadProgressLater();
    } catch (error) {
        downloadProgress.value = {
            ...downloadProgress.value,
            active: false,
            error: error instanceof Error ? error.message : 'Download gagal.',
        };
    }
};

onBeforeUnmount(() => {
    if (downloadResetTimer) {
        window.clearTimeout(downloadResetTimer);
    }
});
</script>

<template>
    <Head title="Media Assets" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-[var(--app-text)]">Media Assets</h2>
                <p class="text-sm text-[var(--app-text-muted)]">Upload ke disk private dan akses lewat presigned URL.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.9fr,1.5fr] lg:px-8">
                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--app-text)]">Upload Media</h3>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Kategori</label>
                            <select v-model="form.category" class="theme-input w-full rounded-lg text-sm">
                                <option v-for="category in categories" :key="category.value" :value="category.value">
                                    {{ category.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Owner</label>
                            <select v-model="form.owner_id" class="theme-input w-full rounded-lg text-sm">
                                <option v-for="owner in ownerOptions" :key="owner.id" :value="owner.id">
                                    {{ owner.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">File</label>
                            <input class="theme-input w-full rounded-lg px-3 py-2 text-sm" type="file" @input="form.file = $event.target.files[0]" />
                        </div>
                        <button type="submit" class="theme-btn-primary rounded-lg px-4 py-2 text-sm font-medium">
                            Upload
                        </button>
                    </form>
                </section>

                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--app-text)]">Daftar Media</h3>
                    <div v-if="downloadProgress.active || downloadProgress.error" class="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <p class="text-xs font-medium text-sky-900">
                            {{ downloadProgress.label || 'Download' }}
                        </p>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-sky-100">
                            <div
                                class="h-full bg-sky-600 transition-all duration-200"
                                :style="{ width: `${downloadProgress.percent}%` }"
                            />
                        </div>
                        <p class="mt-1 text-xs text-sky-800">
                            {{ downloadProgress.percent }}%
                            <span v-if="downloadProgress.total > 0">
                                ({{ formatBytes(downloadProgress.loaded) }} / {{ formatBytes(downloadProgress.total) }})
                            </span>
                        </p>
                        <p v-if="downloadProgress.error" class="mt-1 text-xs text-rose-700">
                            {{ downloadProgress.error }}
                        </p>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-[var(--app-border)] text-sm">
                            <thead>
                                <tr class="text-left text-[var(--app-text-muted)]">
                                    <th class="px-3 py-2 font-medium">ID</th>
                                    <th class="px-3 py-2 font-medium">Kategori</th>
                                    <th class="px-3 py-2 font-medium">Owner</th>
                                    <th class="px-3 py-2 font-medium">File</th>
                                    <th class="px-3 py-2 font-medium">Ukuran</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--app-border)]">
                                <tr v-for="asset in assets" :key="asset.id">
                                    <td class="px-3 py-3 font-medium text-[var(--app-text)]">#{{ asset.id }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text)]">{{ asset.category_label }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text)]">{{ asset.owner_label }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text)]">
                                        <p>{{ asset.original_name || '-' }}</p>
                                        <p class="text-xs text-[var(--app-text-muted)]">{{ asset.mime_type }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-[var(--app-text)]">
                                        <p>{{ asset.size_bytes }} bytes</p>
                                        <p class="text-xs text-[var(--app-text-muted)]">{{ asset.width || '-' }} x {{ asset.height || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700"
                                            @click="downloadAsset(asset)"
                                        >
                                            Download
                                        </button>
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
