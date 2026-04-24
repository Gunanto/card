<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    students: { type: Array, required: true },
    institutions: { type: Array, required: true },
    classrooms: { type: Array, required: true },
    forcedInstitutionId: { type: [Number, null], required: false, default: null },
});

const editingId = ref(null);

const blankForm = () => ({
    institution_id: props.forcedInstitutionId ?? props.institutions[0]?.id ?? '',
    class_id: '',
    student_code: '',
    nis: '',
    nisn: '',
    nik: '',
    npwp: '',
    exam_number: '',
    name: '',
    school_name: '',
    gender: '',
    religion: '',
    address: '',
    village: '',
    district: '',
    regency: '',
    province: '',
    phone: '',
    mobile_phone: '',
    motto: '',
    social_instagram: '',
    social_facebook: '',
    social_tiktok: '',
    status: 'active',
});

const form = useForm(blankForm());

const setForm = (values = {}) => {
    const next = blankForm();

    Object.keys(next).forEach((key) => {
        form[key] = values[key] ?? next[key];
    });
};

const filteredClassrooms = computed(() =>
    props.classrooms.filter((classroom) => Number(classroom.institution_id) === Number(form.institution_id)),
);

const startCreate = () => {
    editingId.value = null;
    setForm();
    form.clearErrors();
};

const editStudent = (student) => {
    editingId.value = student.id;
    setForm(student);
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(route('students.update', editingId.value), { preserveScroll: true });
        return;
    }

    form.post(route('students.store'), { preserveScroll: true });
};

const destroyStudent = (id) => {
    if (!window.confirm('Hapus siswa ini?')) {
        return;
    }

    form.delete(route('students.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Siswa" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Siswa</h2>
                <p class="text-sm text-gray-500">Input manual peserta dan pengelompokan kelas.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr,1.5fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit Siswa' : 'Tambah Siswa' }}</h3>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Institusi</label>
                                <select v-model="form.institution_id" class="w-full rounded-lg border-gray-300 text-sm" :disabled="forcedInstitutionId !== null">
                                    <option v-for="institution in institutions" :key="institution.id" :value="institution.id">
                                        {{ institution.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kelas</label>
                                <select v-model="form.class_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Belum ditentukan</option>
                                    <option v-for="classroom in filteredClassrooms" :key="classroom.id" :value="classroom.id">
                                        {{ classroom.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kode Siswa</label>
                                <input v-model="form.student_code" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">NIS</label>
                                <input v-model="form.nis" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">NISN</label>
                                <input v-model="form.nisn" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nomor Ujian</label>
                                <input v-model="form.exam_number" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">NIK</label>
                                <input v-model="form.nik" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">NPWP</label>
                                <input v-model="form.npwp" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nama</label>
                                <input v-model="form.name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nama Sekolah</label>
                                <input v-model="form.school_name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Gender</label>
                                <select v-model="form.gender" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Belum dipilih</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Telepon</label>
                                <input v-model="form.phone" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">No. HP</label>
                                <input v-model="form.mobile_phone" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Agama</label>
                                <input v-model="form.religion" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                <select v-model="form.status" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="graduated">Graduated</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Motto</label>
                                <input v-model="form.motto" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Alamat</label>
                            <textarea v-model="form.address" class="w-full rounded-lg border-gray-300 text-sm" rows="3" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Desa</label>
                                <input v-model="form.village" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kecamatan</label>
                                <input v-model="form.district" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kabupaten</label>
                                <input v-model="form.regency" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Provinsi</label>
                                <input v-model="form.province" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Instagram</label>
                                <input v-model="form.social_instagram" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Facebook</label>
                                <input v-model="form.social_facebook" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">TikTok</label>
                                <input v-model="form.social_tiktok" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                {{ editingId ? 'Simpan Perubahan' : 'Tambah Siswa' }}
                            </button>
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700" @click="startCreate">
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Siswa</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">Siswa</th>
                                    <th class="px-3 py-2 font-medium">Institusi/Kelas</th>
                                    <th class="px-3 py-2 font-medium">Kontak</th>
                                    <th class="px-3 py-2 font-medium">Status</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="student in students" :key="student.id" class="align-top">
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-gray-900">{{ student.name }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ student.student_code }} / NIS {{ student.nis || '-' }} / NISN {{ student.nisn || '-' }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            NIK {{ student.nik || '-' }} / NPWP {{ student.npwp || '-' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ student.institution_name }}</p>
                                        <p class="text-xs text-gray-500">{{ student.classroom_name || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ student.phone || '-' }}</p>
                                        <p class="text-xs text-gray-500">HP: {{ student.mobile_phone || '-' }}</p>
                                        <p class="text-xs text-gray-500">Agama: {{ student.religion || '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ student.school_name || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">{{ student.status }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700" @click="editStudent(student)">
                                                Edit
                                            </button>
                                            <button type="button" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700" @click="destroyStudent(student.id)">
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
