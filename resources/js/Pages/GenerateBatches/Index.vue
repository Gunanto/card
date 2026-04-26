<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, required: true },
    students: { type: Array, required: true },
    batches: { type: Array, required: true },
    defaultOptionsJsonText: { type: String, required: true },
});

const form = useForm({
    template_id: props.templates[0]?.id ?? '',
    student_ids: [],
    options_json_text: props.defaultOptionsJsonText,
});

const autoRefresh = ref(true);
const downloadProgress = ref({
    active: false,
    label: '',
    percent: 0,
    loaded: 0,
    total: 0,
    error: '',
});
let intervalId = null;
let downloadResetTimer = null;

const selectedTemplate = computed(() =>
    props.templates.find((template) => Number(template.id) === Number(form.template_id)) ?? null,
);

const visibleStudents = computed(() => {
    if (!selectedTemplate.value || selectedTemplate.value.institution_id === null) {
        return props.students;
    }

    return props.students.filter((student) => Number(student.institution_id) === Number(selectedTemplate.value.institution_id));
});

const toggleStudent = (studentId) => {
    const exists = form.student_ids.includes(studentId);

    form.student_ids = exists
        ? form.student_ids.filter((value) => value !== studentId)
        : [...form.student_ids, studentId];
};

const selectAllVisible = () => {
    form.student_ids = visibleStudents.value.map((student) => student.id);
};

const clearSelection = () => {
    form.student_ids = [];
};

const submit = () => {
    form.post(route('generate-batches.store'), { preserveScroll: true });
};

const hasRunningBatch = () => props.batches.some((batch) => ['pending', 'processing'].includes(batch.status));

const refreshBatches = () => {
    router.reload({
        only: ['batches'],
        preserveScroll: true,
        preserveState: true,
    });
};

const statusClasses = (status) => {
    if (status === 'done') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    if (status === 'failed') return 'bg-rose-100 text-rose-800 border-rose-200';
    if (status === 'processing') return 'bg-amber-100 text-amber-800 border-amber-200';
    return 'bg-sky-100 text-sky-800 border-sky-200';
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

const downloadWithProgress = async ({ streamUrl, label, fallbackFilename }) => {
    downloadProgress.value = {
        active: true,
        label,
        percent: 0,
        loaded: 0,
        total: 0,
        error: '',
    };

    const response = await fetch(streamUrl, {
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

    triggerBrowserDownload(blob, safeFilename(fallbackFilename, 'download.bin'));
    resetDownloadProgressLater();
};

const downloadGeneratedCardPdf = async (batchId, generatedCard) => {
    if (!generatedCard.pdf_stream_download_url) return;

    try {
        await downloadWithProgress({
            streamUrl: generatedCard.pdf_stream_download_url,
            label: `Downloading PDF siswa (Batch #${batchId})`,
            fallbackFilename: `generated-card-${generatedCard.id}.pdf`,
        });
    } catch (error) {
        downloadProgress.value = {
            ...downloadProgress.value,
            active: false,
            error: error instanceof Error ? error.message : 'Download gagal.',
        };
    }
};

const downloadBatchA4Pdf = async (batch) => {
    if (!batch.a4_pdf_resolve_url) return;

    try {
        const resolveResponse = await fetch(batch.a4_pdf_resolve_url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!resolveResponse.ok) {
            throw new Error('Gagal menyiapkan file PDF batch.');
        }

        const payload = await resolveResponse.json();

        if (!payload.stream_download_url) {
            throw new Error('Link download batch tidak tersedia.');
        }

        await downloadWithProgress({
            streamUrl: payload.stream_download_url,
            label: `Downloading PDF A4 Batch #${batch.id}`,
            fallbackFilename: payload.filename || `batch-${batch.id}-a4.pdf`,
        });
    } catch (error) {
        downloadProgress.value = {
            ...downloadProgress.value,
            active: false,
            error: error instanceof Error ? error.message : 'Download gagal.',
        };
    }
};

const deleteBatch = (batch) => {
    if (!['done', 'failed'].includes(batch.status)) return;

    const ok = window.confirm(`Hapus Batch #${batch.id} yang sudah ${batch.status}? Riwayat dan file hasil generate akan dihapus.`);
    if (!ok) return;

    router.delete(route('generate-batches.destroy', batch.id), {
        preserveScroll: true,
    });
};

onMounted(() => {
    intervalId = window.setInterval(() => {
        if (autoRefresh.value && hasRunningBatch()) {
            refreshBatches();
        }
    }, 5000);
});

onBeforeUnmount(() => {
    if (intervalId) {
        window.clearInterval(intervalId);
    }

    if (downloadResetTimer) {
        window.clearTimeout(downloadResetTimer);
    }
});
</script>

<template>
    <Head title="Generate Batches" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-[var(--app-text)]">Generate Batches</h2>
                <p class="text-sm text-[var(--app-text-muted)]">Queue dasar per-student dan simpan hasil render ke storage.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.95fr,1.45fr] lg:px-8">
                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--app-text)]">Buat Batch Baru</h3>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Template</label>
                            <select v-model="form.template_id" class="theme-input w-full rounded-lg text-sm">
                                <option v-for="template in templates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="block text-sm font-medium text-[var(--app-text-muted)]">Siswa</label>
                                <div class="flex gap-2">
                                    <button type="button" class="theme-btn-secondary rounded-lg px-3 py-1 text-xs font-medium" @click="selectAllVisible">
                                        Pilih Semua
                                    </button>
                                    <button type="button" class="theme-btn-secondary rounded-lg px-3 py-1 text-xs font-medium" @click="clearSelection">
                                        Kosongkan
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-80 space-y-2 overflow-y-auto rounded-lg border border-[var(--app-border)] p-3">
                                <label
                                    v-for="student in visibleStudents"
                                    :key="student.id"
                                    class="flex items-start gap-3 rounded-lg border border-[var(--app-border)] px-3 py-2 text-sm"
                                >
                                    <input :checked="form.student_ids.includes(student.id)" class="mt-1 rounded border-[var(--app-border)]" type="checkbox" @change="toggleStudent(student.id)" />
                                    <span>
                                        <span class="block font-medium text-[var(--app-text)]">{{ student.name }}</span>
                                        <span class="block text-xs text-[var(--app-text-muted)]">
                                            {{ student.student_code }} / {{ student.classroom_name || '-' }} / {{ student.institution_name }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Options JSON</label>
                            <textarea v-model="form.options_json_text" class="theme-input min-h-28 w-full rounded-lg font-mono text-xs" />
                        </div>
                        <button type="submit" class="theme-btn-primary rounded-lg px-4 py-2 text-sm font-medium">
                            Queue Batch
                        </button>
                    </form>
                </section>

                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-[var(--app-text)]">Riwayat Batch</h3>
                        <label class="flex items-center gap-2 text-xs text-[var(--app-text-muted)]">
                            <input v-model="autoRefresh" class="rounded border-[var(--app-border)]" type="checkbox">
                            Auto refresh
                        </label>
                    </div>
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
                    <div class="mt-4 space-y-4">
                        <article v-for="batch in batches" :key="batch.id" class="rounded-xl border border-[var(--app-border)] p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-[var(--app-text)]">Batch #{{ batch.id }} - {{ batch.template_name }}</p>
                                    <p class="text-xs text-[var(--app-text-muted)]">
                                        {{ batch.institution_name }} / oleh {{ batch.requested_by_name }} / {{ batch.created_at }}
                                    </p>
                                </div>
                                <div class="text-sm text-[var(--app-text)]">
                                    <p class="flex items-center gap-2">
                                        <span>Status:</span>
                                        <span
                                            class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-medium"
                                            :class="statusClasses(batch.status)"
                                        >
                                            <span
                                                v-if="['pending', 'processing'].includes(batch.status)"
                                                class="inline-block h-2 w-2 rounded-full bg-current animate-pulse"
                                            />
                                            {{ batch.status }}
                                        </span>
                                    </p>
                                    <p>{{ batch.success_count }} sukses / {{ batch.failed_count }} gagal / {{ batch.total_cards }} total</p>
                                    <p v-if="batch.total_cards > 0" class="text-xs text-[var(--app-text-muted)]">
                                        progress: {{ Math.round(((batch.success_count + batch.failed_count) / batch.total_cards) * 100) }}%
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button
                                            v-if="batch.a4_pdf_resolve_url"
                                            type="button"
                                            class="inline-block rounded-lg border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700"
                                            @click="downloadBatchA4Pdf(batch)"
                                        >
                                            Download PDF A4 2x5
                                        </button>
                                        <button
                                            v-if="['done', 'failed'].includes(batch.status)"
                                            type="button"
                                            class="inline-block rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700"
                                            @click="deleteBatch(batch)"
                                        >
                                            Hapus Batch
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-[var(--app-border)] text-sm">
                                    <thead>
                                        <tr class="text-left text-[var(--app-text-muted)]">
                                            <th class="px-3 py-2 font-medium">Siswa</th>
                                            <th class="px-3 py-2 font-medium">Status</th>
                                            <th class="px-3 py-2 font-medium">Error</th>
                                            <th class="px-3 py-2 font-medium">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[var(--app-border)]">
                                        <tr v-for="generatedCard in batch.generated_cards" :key="generatedCard.id">
                                            <td class="px-3 py-2 text-[var(--app-text)]">{{ generatedCard.student_name }}</td>
                                            <td class="px-3 py-2">
                                                <span
                                                    class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-medium"
                                                    :class="statusClasses(generatedCard.status)"
                                                >
                                                    <span
                                                        v-if="['pending', 'processing'].includes(generatedCard.status)"
                                                        class="inline-block h-2 w-2 rounded-full bg-current animate-pulse"
                                                    />
                                                    {{ generatedCard.status }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-xs text-rose-600">{{ generatedCard.error_message || '-' }}</td>
                                            <td class="px-3 py-2">
                                                <button
                                                    v-if="generatedCard.pdf_stream_download_url"
                                                    type="button"
                                                    class="rounded-lg border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700"
                                                    @click="downloadGeneratedCardPdf(batch.id, generatedCard)"
                                                >
                                                    Download
                                                </button>
                                                <span v-else class="text-xs text-[var(--app-text-muted)]">Belum ada</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
