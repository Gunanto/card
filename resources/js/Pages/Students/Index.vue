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
                <h2 class="text-xl font-semibold text-[var(--app-text)]">Siswa</h2>
                <p class="text-sm text-[var(--app-text-muted)]">Input manual peserta dan pengelompokan kelas.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr,1.5fr] lg:px-8">
                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--app-text)]">{{ editingId ? 'Edit Siswa' : 'Tambah Siswa' }}</h3>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Institusi</label>
                                <select v-model="form.institution_id" class="theme-input w-full rounded-lg text-sm" :disabled="forcedInstitutionId !== null">
                                    <option v-for="institution in institutions" :key="institution.id" :value="institution.id">
                                        {{ institution.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Kelas</label>
                                <select v-model="form.class_id" class="theme-input w-full rounded-lg text-sm">
                                    <option value="">Belum ditentukan</option>
                                    <option v-for="classroom in filteredClassrooms" :key="classroom.id" :value="classroom.id">
                                        {{ classroom.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Kode Siswa</label>
                                <input v-model="form.student_code" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">NIS</label>
                                <input v-model="form.nis" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">NISN</label>
                                <input v-model="form.nisn" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Nomor Ujian</label>
                                <input v-model="form.exam_number" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">NIK</label>
                                <input v-model="form.nik" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">NPWP</label>
                                <input v-model="form.npwp" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Nama</label>
                                <input v-model="form.name" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Nama Sekolah</label>
                                <input v-model="form.school_name" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Gender</label>
                                <select v-model="form.gender" class="theme-input w-full rounded-lg text-sm">
                                    <option value="">Belum dipilih</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Telepon</label>
                                <input v-model="form.phone" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">No. HP</label>
                                <input v-model="form.mobile_phone" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Agama</label>
                                <input v-model="form.religion" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Status</label>
                                <select v-model="form.status" class="theme-input w-full rounded-lg text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="graduated">Graduated</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Motto</label>
                                <input v-model="form.motto" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Alamat</label>
                            <textarea v-model="form.address" class="theme-input w-full rounded-lg text-sm" rows="3" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Desa</label>
                                <input v-model="form.village" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Kecamatan</label>
                                <input v-model="form.district" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Kabupaten</label>
                                <input v-model="form.regency" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Provinsi</label>
                                <input v-model="form.province" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Instagram</label>
                                <input v-model="form.social_instagram" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Facebook</label>
                                <input v-model="form.social_facebook" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">TikTok</label>
                                <input v-model="form.social_tiktok" class="theme-input w-full rounded-lg text-sm" type="text" />
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="theme-btn-primary rounded-lg px-4 py-2 text-sm font-medium">
                                {{ editingId ? 'Simpan Perubahan' : 'Tambah Siswa' }}
                            </button>
                            <button type="button" class="theme-btn-secondary rounded-lg px-4 py-2 text-sm font-medium" @click="startCreate">
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--app-text)]">Daftar Siswa</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-[var(--app-border)] text-sm">
                            <thead>
                                <tr class="text-left text-[var(--app-text-muted)]">
                                    <th class="px-3 py-2 font-medium">Siswa</th>
                                    <th class="px-3 py-2 font-medium">Institusi/Kelas</th>
                                    <th class="px-3 py-2 font-medium">Kontak</th>
                                    <th class="px-3 py-2 font-medium">Status</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--app-border)]">
                                <tr v-for="student in students" :key="student.id" class="align-top">
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-[var(--app-text)]">{{ student.name }}</p>
                                        <p class="text-xs text-[var(--app-text-muted)]">
                                            {{ student.student_code }} / NIS {{ student.nis || '-' }} / NISN {{ student.nisn || '-' }}
                                        </p>
                                        <p class="text-xs text-[var(--app-text-muted)]">
                                            NIK {{ student.nik || '-' }} / NPWP {{ student.npwp || '-' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-[var(--app-text)]">
                                        <p>{{ student.institution_name }}</p>
                                        <p class="text-xs text-[var(--app-text-muted)]">{{ student.classroom_name || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-[var(--app-text)]">
                                        <p>{{ student.phone || '-' }}</p>
                                        <p class="text-xs text-[var(--app-text-muted)]">HP: {{ student.mobile_phone || '-' }}</p>
                                        <p class="text-xs text-[var(--app-text-muted)]">Agama: {{ student.religion || '-' }}</p>
                                        <p class="text-xs text-[var(--app-text-muted)]">{{ student.school_name || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-[var(--app-text)]">{{ student.status }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="theme-btn-secondary rounded-lg px-3 py-1.5 text-xs font-medium" @click="editStudent(student)">
                                                Edit
                                            </button>
                                            <button type="button" class="theme-btn-danger rounded-lg px-3 py-1.5 text-xs font-medium" @click="destroyStudent(student.id)">
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
