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
let intervalId = null;

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
});
</script>

<template>
    <Head title="Generate Batches" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Generate Batches</h2>
                <p class="text-sm text-gray-500">Queue dasar per-student dan simpan hasil render ke storage.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.95fr,1.45fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Buat Batch Baru</h3>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Template</label>
                            <select v-model="form.template_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option v-for="template in templates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700">Siswa</label>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700" @click="selectAllVisible">
                                        Pilih Semua
                                    </button>
                                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700" @click="clearSelection">
                                        Kosongkan
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-80 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3">
                                <label
                                    v-for="student in visibleStudents"
                                    :key="student.id"
                                    class="flex items-start gap-3 rounded-lg border border-gray-100 px-3 py-2 text-sm"
                                >
                                    <input :checked="form.student_ids.includes(student.id)" class="mt-1 rounded border-gray-300" type="checkbox" @change="toggleStudent(student.id)" />
                                    <span>
                                        <span class="block font-medium text-gray-900">{{ student.name }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ student.student_code }} / {{ student.classroom_name || '-' }} / {{ student.institution_name }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Options JSON</label>
                            <textarea v-model="form.options_json_text" class="min-h-28 w-full rounded-lg border-gray-300 font-mono text-xs" />
                        </div>
                        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                            Queue Batch
                        </button>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Riwayat Batch</h3>
                        <label class="flex items-center gap-2 text-xs text-gray-600">
                            <input v-model="autoRefresh" class="rounded border-gray-300" type="checkbox">
                            Auto refresh
                        </label>
                    </div>
                    <div class="mt-4 space-y-4">
                        <article v-for="batch in batches" :key="batch.id" class="rounded-xl border border-gray-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Batch #{{ batch.id }} - {{ batch.template_name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ batch.institution_name }} / oleh {{ batch.requested_by_name }} / {{ batch.created_at }}
                                    </p>
                                </div>
                                <div class="text-sm text-gray-700">
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
                                    <p v-if="batch.total_cards > 0" class="text-xs text-gray-500">
                                        progress: {{ Math.round(((batch.success_count + batch.failed_count) / batch.total_cards) * 100) }}%
                                    </p>
                                    <a
                                        v-if="batch.a4_pdf_download_url"
                                        :href="batch.a4_pdf_download_url"
                                        class="mt-2 inline-block rounded-lg border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700"
                                    >
                                        Download PDF A4 2x5
                                    </a>
                                </div>
                            </div>
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead>
                                        <tr class="text-left text-gray-500">
                                            <th class="px-3 py-2 font-medium">Siswa</th>
                                            <th class="px-3 py-2 font-medium">Status</th>
                                            <th class="px-3 py-2 font-medium">Error</th>
                                            <th class="px-3 py-2 font-medium">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="generatedCard in batch.generated_cards" :key="generatedCard.id">
                                            <td class="px-3 py-2 text-gray-700">{{ generatedCard.student_name }}</td>
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
                                                <a
                                                    v-if="generatedCard.pdf_download_url"
                                                    :href="generatedCard.pdf_download_url"
                                                    class="rounded-lg border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700"
                                                >
                                                    Download
                                                </a>
                                                <span v-else class="text-xs text-gray-400">Belum ada</span>
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
