<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: { type: Array, required: true },
    institutions: { type: Array, required: true },
    currentUserId: { type: Number, required: true },
});

const editingId = ref(null);

const blankForm = () => ({
    name: '',
    email: '',
    password: '',
    role: 'guru',
    is_active: true,
    institution_id: '',
});

const form = useForm(blankForm());

const setForm = (values = {}) => {
    const next = blankForm();

    Object.keys(next).forEach((key) => {
        form[key] = values[key] ?? next[key];
    });

    form.password = '';
};

const roleOptions = [
    { value: 'admin', label: 'Admin' },
    { value: 'guru', label: 'Guru' },
];

const showInstitutionSelect = computed(() => form.role === 'guru');

const startCreate = () => {
    editingId.value = null;
    setForm();
    form.clearErrors();
};

const editUser = (user) => {
    editingId.value = user.id;
    setForm({
        ...user,
        institution_id: user.institution_id ?? '',
    });
    form.clearErrors();
};

const submit = () => {
    if (form.role === 'admin') {
        form.institution_id = '';
    }

    if (editingId.value) {
        form.put(route('users.update', editingId.value), { preserveScroll: true });
        return;
    }

    form.post(route('users.store'), { preserveScroll: true });
};

const destroyUser = (user) => {
    if (user.id === props.currentUserId) {
        window.alert('Akun sendiri tidak bisa dihapus.');
        return;
    }

    if (!window.confirm(`Hapus user ${user.email}?`)) {
        return;
    }

    form.delete(route('users.destroy', user.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Users</h2>
                    <p class="text-sm text-gray-500">Kelola akun admin dan guru.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700"
                    @click="startCreate"
                >
                    Form Baru
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.9fr,1.5fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ editingId ? 'Edit User' : 'Tambah User' }}
                    </h3>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                            <input v-model="form.name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                            <input v-model="form.email" class="w-full rounded-lg border-gray-300 text-sm" type="email" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Password {{ editingId ? '(kosongkan jika tidak diganti)' : '' }}
                            </label>
                            <input v-model="form.password" class="w-full rounded-lg border-gray-300 text-sm" type="password" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                                <select v-model="form.role" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="showInstitutionSelect">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Institution</label>
                                <select v-model="form.institution_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Pilih institusi</option>
                                    <option v-for="institution in institutions" :key="institution.id" :value="institution.id">
                                        {{ institution.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-3 text-sm text-gray-700">
                            <input v-model="form.is_active" class="rounded border-gray-300" type="checkbox" />
                            Active
                        </label>
                        <div class="flex gap-3">
                            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                {{ editingId ? 'Simpan Perubahan' : 'Tambah User' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700"
                                @click="startCreate"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Users</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">Name</th>
                                    <th class="px-3 py-2 font-medium">Email</th>
                                    <th class="px-3 py-2 font-medium">Role</th>
                                    <th class="px-3 py-2 font-medium">Institution</th>
                                    <th class="px-3 py-2 font-medium">Status</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="user in users" :key="user.id" class="align-top">
                                    <td class="px-3 py-3 font-medium text-gray-900">{{ user.name }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ user.email }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ user.role }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ user.institution_name || '-' }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ user.is_active ? 'active' : 'inactive' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700"
                                                @click="editUser(user)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700"
                                                @click="destroyUser(user)"
                                            >
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

