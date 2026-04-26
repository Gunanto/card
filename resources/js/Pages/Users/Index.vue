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
    card_generation_limit: '',
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
const showCardLimitInput = computed(() => form.role === 'guru');

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
        form.card_generation_limit = '';
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
                    <h2 class="text-xl font-semibold text-[var(--app-text)]">Users</h2>
                    <p class="text-sm text-[var(--app-text-muted)]">Kelola akun admin dan guru.</p>
                </div>
                <button
                    type="button"
                    class="theme-btn-secondary rounded-lg px-4 py-2 text-sm font-medium"
                    @click="startCreate"
                >
                    Form Baru
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.9fr,1.5fr] lg:px-8">
                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--app-text)]">
                        {{ editingId ? 'Edit User' : 'Tambah User' }}
                    </h3>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Name</label>
                            <input v-model="form.name" class="theme-input w-full rounded-lg text-sm" type="text" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Email</label>
                            <input v-model="form.email" class="theme-input w-full rounded-lg text-sm" type="email" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">
                                Password {{ editingId ? '(kosongkan jika tidak diganti)' : '' }}
                            </label>
                            <input v-model="form.password" class="theme-input w-full rounded-lg text-sm" type="password" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Role</label>
                                <select v-model="form.role" class="theme-input w-full rounded-lg text-sm">
                                    <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="showInstitutionSelect">
                                <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Institution</label>
                                <select v-model="form.institution_id" class="theme-input w-full rounded-lg text-sm">
                                    <option value="">Pilih institusi</option>
                                    <option v-for="institution in institutions" :key="institution.id" :value="institution.id">
                                        {{ institution.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div v-if="showCardLimitInput">
                            <label class="mb-1 block text-sm font-medium text-[var(--app-text-muted)]">Batas Card Guru</label>
                            <input
                                v-model="form.card_generation_limit"
                                class="theme-input w-full rounded-lg text-sm"
                                type="number"
                                min="1"
                                placeholder="Kosong = tanpa batas"
                            />
                            <p class="mt-1 text-xs text-[var(--app-text-muted)]">Batas total kartu yang boleh digenerate oleh akun guru ini.</p>
                            <p v-if="form.errors.card_generation_limit" class="mt-1 text-xs text-rose-600">{{ form.errors.card_generation_limit }}</p>
                        </div>
                        <label class="flex items-center gap-3 text-sm text-[var(--app-text-muted)]">
                            <input v-model="form.is_active" class="rounded border-[var(--app-border)] bg-[var(--app-surface)] text-[var(--app-primary)]" type="checkbox" />
                            Active
                        </label>
                        <div class="flex gap-3">
                            <button type="submit" class="theme-btn-primary rounded-lg px-4 py-2 text-sm font-medium">
                                {{ editingId ? 'Simpan Perubahan' : 'Tambah User' }}
                            </button>
                            <button
                                type="button"
                                class="theme-btn-secondary rounded-lg px-4 py-2 text-sm font-medium"
                                @click="startCreate"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section class="theme-surface rounded-xl border p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-[var(--app-text)]">Daftar Users</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-[var(--app-border)] text-sm">
                            <thead>
                                <tr class="text-left text-[var(--app-text-muted)]">
                                    <th class="px-3 py-2 font-medium">Name</th>
                                    <th class="px-3 py-2 font-medium">Email</th>
                                    <th class="px-3 py-2 font-medium">Role</th>
                                    <th class="px-3 py-2 font-medium">Institution</th>
                                    <th class="px-3 py-2 font-medium">Kuota Card</th>
                                    <th class="px-3 py-2 font-medium">Status</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--app-border)]">
                                <tr v-for="user in users" :key="user.id" class="align-top">
                                    <td class="px-3 py-3 font-medium text-[var(--app-text)]">{{ user.name }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text-muted)]">{{ user.email }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text-muted)]">{{ user.role }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text-muted)]">{{ user.institution_name || '-' }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text-muted)]">{{ user.role === 'guru' ? (user.card_generation_limit ?? 'Tanpa batas') : '-' }}</td>
                                    <td class="px-3 py-3 text-[var(--app-text-muted)]">{{ user.is_active ? 'active' : 'inactive' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="theme-btn-secondary rounded-lg px-3 py-1.5 text-xs font-medium"
                                                @click="editUser(user)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="theme-btn-danger rounded-lg px-3 py-1.5 text-xs font-medium"
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
