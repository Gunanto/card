<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    institutions: { type: Array, required: true },
    brandingAssets: { type: Array, required: true },
    canCreate: { type: Boolean, required: true },
    canDelete: { type: Boolean, required: true },
});

const editingId = ref(null);

const blankForm = () => ({
    name: '',
    npsn: '',
    address: '',
    village: '',
    district: '',
    regency: '',
    province: '',
    postal_code: '',
    phone: '',
    email: '',
    website: '',
    leader_name: '',
    leader_nip: '',
    leader_title: '',
    logo_media_id: '',
    stamp_media_id: '',
    leader_signature_media_id: '',
});

const form = useForm(blankForm());

const setForm = (values = {}) => {
    const next = blankForm();

    Object.keys(next).forEach((key) => {
        form[key] = values[key] ?? next[key];
    });
};

const availableBrandingAssets = computed(() =>
    props.brandingAssets.filter((asset) => Number(asset.owner_id) === Number(editingId.value)),
);

const startCreate = () => {
    editingId.value = null;
    setForm();
    form.clearErrors();
};

const editInstitution = (institution) => {
    editingId.value = institution.id;
    setForm(institution);
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(route('institutions.update', editingId.value), { preserveScroll: true });
        return;
    }

    form.post(route('institutions.store'), { preserveScroll: true });
};

const destroyInstitution = (id) => {
    if (!window.confirm('Hapus institusi ini?')) {
        return;
    }

    form.delete(route('institutions.destroy', id), { preserveScroll: true });
};

if (!props.canCreate && props.institutions[0]) {
    editInstitution(props.institutions[0]);
}
</script>

<template>
    <Head title="Institusi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Institusi</h2>
                    <p class="text-sm text-gray-500">Kelola profil instansi dan branding default.</p>
                </div>
                <button
                    v-if="canCreate"
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700"
                    @click="startCreate"
                >
                    Form Baru
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.05fr,1.4fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ editingId ? 'Edit Institusi' : 'Buat Institusi' }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Branding media dipilih setelah file diunggah pada modul Media.
                    </p>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nama</label>
                                <input v-model="form.name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">NPSN</label>
                                <input v-model="form.npsn" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
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
                                <label class="mb-1 block text-sm font-medium text-gray-700">Telepon</label>
                                <input v-model="form.phone" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                                <input v-model="form.email" class="w-full rounded-lg border-gray-300 text-sm" type="email" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kode Pos</label>
                                <input v-model="form.postal_code" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Website</label>
                            <input v-model="form.website" class="w-full rounded-lg border-gray-300 text-sm" type="url" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nama Pimpinan</label>
                                <input v-model="form.leader_name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">NIP Pimpinan</label>
                                <input v-model="form.leader_nip" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Jabatan Pimpinan</label>
                                <input v-model="form.leader_title" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
                                <select v-model="form.logo_media_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Belum dipilih</option>
                                    <option
                                        v-for="asset in availableBrandingAssets.filter((item) => item.category === 'institution_logo')"
                                        :key="asset.id"
                                        :value="asset.id"
                                    >
                                        {{ asset.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Stempel</label>
                                <select v-model="form.stamp_media_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Belum dipilih</option>
                                    <option
                                        v-for="asset in availableBrandingAssets.filter((item) => item.category === 'institution_stamp')"
                                        :key="asset.id"
                                        :value="asset.id"
                                    >
                                        {{ asset.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Tanda Tangan</label>
                                <select v-model="form.leader_signature_media_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Belum dipilih</option>
                                    <option
                                        v-for="asset in availableBrandingAssets.filter((item) => item.category === 'institution_signature')"
                                        :key="asset.id"
                                        :value="asset.id"
                                    >
                                        {{ asset.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                {{ editingId ? 'Simpan Perubahan' : 'Buat Institusi' }}
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
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Institusi</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">Nama</th>
                                    <th class="px-3 py-2 font-medium">Kontak</th>
                                    <th class="px-3 py-2 font-medium">Pimpinan</th>
                                    <th class="px-3 py-2 font-medium">Branding</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="institution in institutions" :key="institution.id" class="align-top">
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-gray-900">{{ institution.name }}</p>
                                        <p class="text-xs text-gray-500">NPSN: {{ institution.npsn || '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ institution.address || '-' }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ institution.village || '-' }}, {{ institution.district || '-' }}, {{ institution.regency || '-' }}, {{ institution.province || '-' }}
                                            {{ institution.postal_code || '' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ institution.phone || '-' }}</p>
                                        <p>{{ institution.email || '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ institution.website || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <p>{{ institution.leader_name || '-' }}</p>
                                        <p class="text-xs text-gray-500">NIP: {{ institution.leader_nip || '-' }}</p>
                                        <p>{{ institution.leader_title || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-500">
                                        <p>Logo: {{ institution.logo_media_label || '-' }}</p>
                                        <p>Stempel: {{ institution.stamp_media_label || '-' }}</p>
                                        <p>TTD: {{ institution.leader_signature_media_label || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700"
                                                @click="editInstitution(institution)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="canDelete"
                                                type="button"
                                                class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700"
                                                @click="destroyInstitution(institution.id)"
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
