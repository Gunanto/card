<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    classrooms: { type: Array, required: true },
    institutions: { type: Array, required: true },
    teachers: { type: Array, required: true },
    forcedInstitutionId: { type: [Number, null], required: false, default: null },
});

const editingId = ref(null);

const blankForm = () => ({
    institution_id: props.forcedInstitutionId ?? props.institutions[0]?.id ?? '',
    code: '',
    name: '',
    level: '',
    major: '',
    homeroom_teacher_user_id: '',
});

const form = useForm(blankForm());

const setForm = (values = {}) => {
    const next = blankForm();

    Object.keys(next).forEach((key) => {
        form[key] = values[key] ?? next[key];
    });
};

const filteredTeachers = computed(() =>
    props.teachers.filter((teacher) => Number(teacher.institution_id) === Number(form.institution_id)),
);

const startCreate = () => {
    editingId.value = null;
    setForm();
    form.clearErrors();
};

const editClassroom = (classroom) => {
    editingId.value = classroom.id;
    setForm(classroom);
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(route('classrooms.update', editingId.value), { preserveScroll: true });
        return;
    }

    form.post(route('classrooms.store'), { preserveScroll: true });
};

const destroyClassroom = (id) => {
    if (!window.confirm('Hapus kelas ini?')) {
        return;
    }

    form.delete(route('classrooms.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Kelas" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Kelas</h2>
                <p class="text-sm text-gray-500">CRUD dasar untuk data kelas dan wali kelas.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.95fr,1.45fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit Kelas' : 'Tambah Kelas' }}</h3>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Institusi</label>
                            <select
                                v-model="form.institution_id"
                                class="w-full rounded-lg border-gray-300 text-sm"
                                :disabled="forcedInstitutionId !== null"
                            >
                                <option v-for="institution in institutions" :key="institution.id" :value="institution.id">
                                    {{ institution.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kode</label>
                                <input v-model="form.code" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nama</label>
                                <input v-model="form.name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Level</label>
                                <input v-model="form.level" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Jurusan</label>
                                <input v-model="form.major" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Wali Kelas</label>
                            <select v-model="form.homeroom_teacher_user_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Belum ditentukan</option>
                                <option v-for="teacher in filteredTeachers" :key="teacher.id" :value="teacher.id">
                                    {{ teacher.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                {{ editingId ? 'Simpan Perubahan' : 'Tambah Kelas' }}
                            </button>
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700" @click="startCreate">
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Kelas</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">Kode</th>
                                    <th class="px-3 py-2 font-medium">Nama</th>
                                    <th class="px-3 py-2 font-medium">Institusi</th>
                                    <th class="px-3 py-2 font-medium">Wali Kelas</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="classroom in classrooms" :key="classroom.id">
                                    <td class="px-3 py-3 font-medium text-gray-900">{{ classroom.code }}</td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ classroom.name }}</p>
                                        <p class="text-xs text-gray-500">{{ classroom.level || '-' }} / {{ classroom.major || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">{{ classroom.institution_name }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ classroom.homeroom_teacher_name || '-' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700" @click="editClassroom(classroom)">
                                                Edit
                                            </button>
                                            <button type="button" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700" @click="destroyClassroom(classroom.id)">
                                                Hapus
                                            </button>
                                        </div>
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
