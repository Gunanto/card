<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    imports: { type: Array, required: true },
    institutions: { type: Array, required: true },
    forcedInstitutionId: { type: [Number, null], required: false, default: null },
    sampleHeaders: { type: Array, required: true },
    mappingFields: { type: Array, required: true },
});

const form = useForm({
    institution_id: props.forcedInstitutionId ?? props.institutions[0]?.id ?? '',
    file: null,
    mapping_json_text: '',
});

const autoRefresh = ref(true);
const detectedHeaders = ref([]);
const mappingByField = ref(
    Object.fromEntries(
        props.mappingFields.map((field) => [field.key, field.key]),
    ),
);
let intervalId = null;

const hasRunningImport = () => props.imports.some((item) => ['pending', 'processing'].includes(item.status));

const refreshImports = () => {
    router.reload({
        only: ['imports'],
        preserveScroll: true,
        preserveState: true,
    });
};

const normalizeHeader = (header) => header
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');

const parseCsvHeaders = async (file) => {
    const chunk = await file.slice(0, 32 * 1024).text();
    const firstLine = chunk.split(/\r?\n/).find((line) => line.trim() !== '');

    if (!firstLine) {
        return [];
    }

    const matches = firstLine.match(/("([^"]|"")*"|[^,]+)/g) || [];

    return matches
        .map((header) => header.replace(/^"|"$/g, '').replace(/""/g, '"').trim())
        .filter((header) => header !== '')
        .map((header) => normalizeHeader(header));
};

const handleFileInput = async (event) => {
    const file = event.target.files?.[0] ?? null;
    form.file = file;
    detectedHeaders.value = [];

    if (!file) {
        return;
    }

    const ext = file.name.split('.').pop()?.toLowerCase();
    if (!ext || !['csv', 'txt'].includes(ext)) {
        return;
    }

    detectedHeaders.value = await parseCsvHeaders(file);
};

const mappingPayload = () => {
    const payload = {};
    for (const field of props.mappingFields) {
        const source = (mappingByField.value[field.key] ?? '').trim();
        if (source !== '') {
            payload[field.key] = source;
        }
    }

    return payload;
};

const submit = () => {
    const payload = mappingPayload();
    form.mapping_json_text = Object.keys(payload).length > 0 ? JSON.stringify(payload) : '';

    form.post(route('imports.students.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
            detectedHeaders.value = [];
        },
    });
};

const statusClasses = (status) => {
    if (status === 'done') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    if (status === 'failed') return 'bg-rose-100 text-rose-800 border-rose-200';
    if (status === 'processing') return 'bg-amber-100 text-amber-800 border-amber-200';
    return 'bg-sky-100 text-sky-800 border-sky-200';
};

onMounted(() => {
    intervalId = window.setInterval(() => {
        if (autoRefresh.value && hasRunningImport()) {
            refreshImports();
        }
    }, 5000);
});

onBeforeUnmount(() => {
    if (intervalId) {
        window.clearInterval(intervalId);
    }
});
</script>

<template>
    <Head title="Imports" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Imports</h2>
                <p class="text-sm text-gray-500">Import data siswa dari CSV/Excel dengan proses async queue.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.9fr,1.5fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Upload File Import</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Header standar: {{ sampleHeaders.join(', ') }}
                    </p>
                    <p class="mt-1 text-xs text-sky-700">
                        Sample file:
                        <a class="underline" href="/samples/students_import_sample.csv" target="_blank">CSV</a>
                        /
                        <a class="underline" href="/samples/students_import_sample.xlsx" target="_blank">XLSX</a>
                    </p>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Institusi</label>
                            <select v-model="form.institution_id" class="w-full rounded-lg border-gray-300 text-sm" :disabled="forcedInstitutionId !== null">
                                <option v-for="institution in institutions" :key="institution.id" :value="institution.id">
                                    {{ institution.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">File CSV/XLSX</label>
                            <input class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" type="file" @input="handleFileInput" />
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3">
                                <label class="block text-sm font-medium text-gray-700">Column Mapping (opsional)</label>
                                <p class="text-xs text-gray-500">
                                    {{ detectedHeaders.length > 0 ? `Detected CSV headers: ${detectedHeaders.join(', ')}` : 'Untuk XLS/XLSX, isi source column manual.' }}
                                </p>
                            </div>
                            <div class="max-h-64 overflow-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead class="bg-gray-50">
                                        <tr class="text-left text-gray-600">
                                            <th class="px-3 py-2 font-medium">Target field</th>
                                            <th class="px-3 py-2 font-medium">Source column</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr v-for="field in mappingFields" :key="field.key">
                                            <td class="px-3 py-2">
                                                <span class="font-medium text-gray-800">{{ field.label }}</span>
                                                <span v-if="field.required" class="ml-1 text-rose-600">*</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="mappingByField[field.key]"
                                                    class="w-full rounded-md border-gray-300 text-xs"
                                                    :list="detectedHeaders.length > 0 ? 'detected-header-options' : null"
                                                    :placeholder="field.key"
                                                >
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <datalist id="detected-header-options">
                                <option v-for="header in detectedHeaders" :key="header" :value="header" />
                            </datalist>
                            <p class="mt-2 text-xs text-gray-500">
                                Mapping akan dikirim otomatis sebagai JSON saat submit.
                            </p>
                        </div>
                        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                            Upload & Proses
                        </button>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Riwayat Import</h3>
                        <label class="flex items-center gap-2 text-xs text-gray-600">
                            <input v-model="autoRefresh" class="rounded border-gray-300" type="checkbox">
                            Auto refresh
                        </label>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">ID</th>
                                    <th class="px-3 py-2 font-medium">Institusi</th>
                                    <th class="px-3 py-2 font-medium">File</th>
                                    <th class="px-3 py-2 font-medium">Status</th>
                                    <th class="px-3 py-2 font-medium">Rows</th>
                                    <th class="px-3 py-2 font-medium">Errors</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="item in imports" :key="item.id" class="align-top">
                                    <td class="px-3 py-3 font-medium text-gray-900">#{{ item.id }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ item.institution_name }}</td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ item.source_filename || '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ item.type }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-medium"
                                            :class="statusClasses(item.status)"
                                        >
                                            <span
                                                v-if="['pending', 'processing'].includes(item.status)"
                                                class="inline-block h-2 w-2 rounded-full bg-current animate-pulse"
                                            />
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-600">
                                        <p>total: {{ item.total_rows }}</p>
                                        <p>sukses: {{ item.success_rows }}</p>
                                        <p>gagal: {{ item.failed_rows }}</p>
                                        <p v-if="item.total_rows > 0" class="mt-1">
                                            progress: {{ Math.round(((item.success_rows + item.failed_rows) / item.total_rows) * 100) }}%
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-rose-600">
                                        <p v-if="!item.error_summary_json || item.error_summary_json.length === 0">-</p>
                                        <p v-for="(error, idx) in (item.error_summary_json || []).slice(0, 3)" :key="idx">
                                            Row {{ error.row || '?' }}: {{ error.message }}
                                        </p>
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
