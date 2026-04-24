<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, required: true },
    institutions: { type: Array, required: true },
    cardTypes: { type: Array, required: true },
    backgroundAssets: { type: Array, required: true },
    defaults: { type: Object, required: true },
    forcedInstitutionId: { type: [Number, null], required: false, default: null },
});

const editingId = ref(null);

const blankForm = () => ({
    institution_id: props.forcedInstitutionId ?? '',
    card_type_id: props.cardTypes[0]?.id ?? '',
    name: '',
    width_mm: 85.6,
    height_mm: 54,
    background_front_media_id: '',
    background_back_media_id: '',
    config_json_text: props.defaults.config_json_text,
    print_layout_json_text: props.defaults.print_layout_json_text,
    is_active: true,
});

const form = useForm(blankForm());

const setForm = (values = {}) => {
    const next = blankForm();

    Object.keys(next).forEach((key) => {
        form[key] = values[key] ?? next[key];
    });
};

const availableBackgrounds = computed(() =>
    props.backgroundAssets.filter((asset) => Number(asset.owner_id) === Number(editingId.value)),
);

const startCreate = () => {
    editingId.value = null;
    setForm();
    form.clearErrors();
};

const editTemplate = (template) => {
    editingId.value = template.id;
    setForm({
        ...template,
        config_json_text: JSON.stringify(template.config_json, null, 2),
        print_layout_json_text: JSON.stringify(template.print_layout_json, null, 2),
    });
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(route('card-templates.update', editingId.value), { preserveScroll: true });
        return;
    }

    form.post(route('card-templates.store'), { preserveScroll: true });
};

const destroyTemplate = (id) => {
    if (!window.confirm('Hapus template ini?')) {
        return;
    }

    form.delete(route('card-templates.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Card Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Card Templates</h2>
                <p class="text-sm text-gray-500">Simpan konfigurasi elemen kartu dan print layout A4 2x5.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.05fr,1.35fr] lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit Template' : 'Buat Template' }}</h3>
                    <p class="mt-1 text-sm text-gray-500">Background media baru bisa dipilih setelah template memiliki ID dan file diunggah.</p>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Institusi</label>
                                <select v-model="form.institution_id" class="w-full rounded-lg border-gray-300 text-sm" :disabled="forcedInstitutionId !== null">
                                    <option value="">Global</option>
                                    <option v-for="institution in institutions" :key="institution.id" :value="institution.id">
                                        {{ institution.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Jenis Kartu</label>
                                <select v-model="form.card_type_id" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option v-for="cardType in cardTypes" :key="cardType.id" :value="cardType.id">
                                        {{ cardType.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nama Template</label>
                            <input v-model="form.name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Lebar (mm)</label>
                                <input v-model="form.width_mm" class="w-full rounded-lg border-gray-300 text-sm" type="number" step="0.1" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Tinggi (mm)</label>
                                <input v-model="form.height_mm" class="w-full rounded-lg border-gray-300 text-sm" type="number" step="0.1" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Background Depan</label>
                                <select v-model="form.background_front_media_id" class="w-full rounded-lg border-gray-300 text-sm" :disabled="!editingId">
                                    <option value="">Belum dipilih</option>
                                    <option
                                        v-for="asset in availableBackgrounds.filter((item) => item.category === 'template_background_front')"
                                        :key="asset.id"
                                        :value="asset.id"
                                    >
                                        {{ asset.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Background Belakang</label>
                                <select v-model="form.background_back_media_id" class="w-full rounded-lg border-gray-300 text-sm" :disabled="!editingId">
                                    <option value="">Belum dipilih</option>
                                    <option
                                        v-for="asset in availableBackgrounds.filter((item) => item.category === 'template_background_back')"
                                        :key="asset.id"
                                        :value="asset.id"
                                    >
                                        {{ asset.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Config JSON</label>
                            <textarea v-model="form.config_json_text" class="min-h-56 w-full rounded-lg border-gray-300 font-mono text-xs" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Print Layout JSON</label>
                            <textarea v-model="form.print_layout_json_text" class="min-h-40 w-full rounded-lg border-gray-300 font-mono text-xs" />
                        </div>
                        <label class="flex items-center gap-3 text-sm text-gray-700">
                            <input v-model="form.is_active" class="rounded border-gray-300" type="checkbox" />
                            Template aktif
                        </label>
                        <div class="flex gap-3">
                            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                                {{ editingId ? 'Simpan Perubahan' : 'Buat Template' }}
                            </button>
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700" @click="startCreate">
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Template</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">Nama</th>
                                    <th class="px-3 py-2 font-medium">Scope</th>
                                    <th class="px-3 py-2 font-medium">Ukuran</th>
                                    <th class="px-3 py-2 font-medium">Status</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="template in templates" :key="template.id" class="align-top">
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-gray-900">{{ template.name }}</p>
                                        <p class="text-xs text-gray-500">{{ template.card_type_name }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">{{ template.institution_name }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ template.width_mm }} x {{ template.height_mm }} mm</td>
                                    <td class="px-3 py-3 text-gray-600">{{ template.is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700" @click="editTemplate(template)">
                                                Edit
                                            </button>
                                            <button type="button" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700" @click="destroyTemplate(template.id)">
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
