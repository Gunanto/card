<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, required: true },
    institutions: { type: Array, required: true },
    cardTypes: { type: Array, required: true },
    backgroundAssets: { type: Array, required: true },
    defaults: { type: Object, required: true },
    forcedInstitutionId: { type: [Number, null], required: false, default: null },
});

const configSchemaVersion = 2;
const editingId = ref(null);
const mmScale = 6; // 1mm => 6px in editor canvas
const editorConfig = ref({
    schema_version: configSchemaVersion,
    canvas: { width_mm: 85.6, height_mm: 54 },
    elements: [],
});
const selectedElementIndex = ref(-1);
const dragState = ref(null);
const resizeState = ref(null);
const snapToGrid = ref(true);
const snapStepMm = ref(0.5);
const historyStack = ref([]);
const historyIndex = ref(-1);
const applyingHistory = ref(false);
const previewTemplate = ref(null);

const sourceByLegacyKey = {
    name: 'student.name',
    student_name: 'student.name',
    student_code: 'student.student_code',
    exam_number: 'student.exam_number',
    class_name: 'student.classroom_name',
    classroom_name: 'student.classroom_name',
    school_name: 'student.school_name',
    institution_name: 'institution.name',
    institution_address: 'institution.address',
    address: 'institution.address',
    leader_name: 'institution.leader_name',
    leader_nip: 'institution.leader_nip',
    leader_title: 'institution.leader_title',
    student_photo: 'media.student_photo',
    institution_logo: 'media.institution_logo',
    institution_stamp: 'media.institution_stamp',
    leader_signature: 'media.leader_signature',
};

const legacyKeyBySource = {
    'student.name': 'student_name',
    'student.student_code': 'student_code',
    'student.exam_number': 'exam_number',
    'student.classroom_name': 'classroom_name',
    'student.school_name': 'school_name',
    'institution.name': 'institution_name',
    'institution.address': 'institution_address',
    'institution.leader_name': 'leader_name',
    'institution.leader_nip': 'leader_nip',
    'institution.leader_title': 'leader_title',
    'media.student_photo': 'student_photo',
    'media.institution_logo': 'institution_logo',
    'media.institution_stamp': 'institution_stamp',
    'media.leader_signature': 'leader_signature',
};

const textSourceOptions = [
    { value: 'student.name', label: 'Student: Name' },
    { value: 'student.student_code', label: 'Student: Student Code' },
    { value: 'student.exam_number', label: 'Student: Exam Number' },
    { value: 'student.classroom_name', label: 'Student: Classroom Name' },
    { value: 'student.school_name', label: 'Student: School Name' },
    { value: 'institution.name', label: 'Institution: Name' },
    { value: 'institution.address', label: 'Institution: Address' },
    { value: 'institution.phone', label: 'Institution: Phone' },
    { value: 'institution.email', label: 'Institution: Email' },
    { value: 'institution.leader_name', label: 'Institution: Leader Name' },
    { value: 'institution.leader_nip', label: 'Institution: Leader NIP' },
    { value: 'institution.leader_title', label: 'Institution: Leader Title' },
];

const imageSourceOptions = [
    { value: 'media.student_photo', label: 'Media: Student Photo' },
    { value: 'media.institution_logo', label: 'Media: Institution Logo' },
    { value: 'media.institution_stamp', label: 'Media: Institution Stamp' },
    { value: 'media.leader_signature', label: 'Media: Leader Signature' },
];

const textColorOptions = [
    { value: '#FFFFFF', label: 'Putih' },
    { value: '#000000', label: 'Hitam' },
    { value: '#6B7280', label: 'Abu-abu' },
    { value: '#EF4444', label: 'Merah' },
    { value: '#F97316', label: 'Orange' },
    { value: '#EAB308', label: 'Kuning' },
    { value: '#22C55E', label: 'Hijau' },
    { value: '#3B82F6', label: 'Biru' },
    { value: '#8B5CF6', label: 'Ungu' },
    { value: '#EC4899', label: 'Pink' },
];

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
const page = usePage();

const toNumber = (value, fallback = 0) => {
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) return Number(value);
    return fallback;
};

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
const cloneConfig = (value) => JSON.parse(JSON.stringify(value));
const roundMm = (value) => Number(toNumber(value, 0).toFixed(2));
const snapMm = (value) => {
    const num = toNumber(value, 0);
    if (!snapToGrid.value) return roundMm(num);
    const step = Math.max(0.1, toNumber(snapStepMm.value, 0.5));
    return roundMm(Math.round(num / step) * step);
};

const createDefaultConfig = () => ({
    schema_version: configSchemaVersion,
    canvas: {
        width_mm: toNumber(form.width_mm, 85.6),
        height_mm: toNumber(form.height_mm, 54),
    },
    elements: [
        { type: 'photo', key: 'student_photo', source: 'media.student_photo', x: 6, y: 10, w: 20, h: 26, z: 10 },
        { type: 'text', key: 'student_name', mode: 'dynamic', source: 'student.name', text: '', x: 30, y: 14, font_size: 3.2, font_weight: '700', z: 20 },
        { type: 'text', key: 'student_code', mode: 'dynamic', source: 'student.student_code', text: '', x: 30, y: 20, font_size: 2.6, z: 21 },
        { type: 'image', key: 'institution_logo', source: 'media.institution_logo', x: 6, y: 4, w: 10, h: 10, z: 30 },
    ],
});

const sourceFromRaw = (raw) => {
    const explicit = typeof raw?.source === 'string' ? raw.source.trim() : '';
    if (explicit !== '') return explicit;

    const legacyKey = typeof raw?.key === 'string' ? raw.key.trim() : '';
    if (legacyKey === '') return '';
    return sourceByLegacyKey[legacyKey] ?? `legacy.${legacyKey}`;
};

const keyFromRaw = (raw, source, index) => {
    const explicit = typeof raw?.key === 'string' ? raw.key.trim() : '';
    if (explicit !== '') return explicit;
    if (typeof source === 'string' && source.startsWith('legacy.')) {
        const legacyKey = source.slice(7).trim();
        if (legacyKey !== '') return legacyKey;
    }
    return legacyKeyBySource[source] ?? `element_${index + 1}`;
};

const normalizeElement = (raw, index) => {
    const type = ['text', 'photo', 'image'].includes(raw?.type) ? raw.type : 'text';
    const source = sourceFromRaw(raw);
    const base = {
        type,
        key: keyFromRaw(raw, source, index),
        source,
        x: toNumber(raw?.x ?? raw?.x_mm, 0),
        y: toNumber(raw?.y ?? raw?.y_mm, 0),
        w: toNumber(raw?.w ?? raw?.w_mm, type === 'text' ? 0 : 20),
        h: toNumber(raw?.h ?? raw?.h_mm, type === 'text' ? 0 : 10),
        z: toNumber(raw?.z ?? raw?.z_index, (index + 1) * 10),
        opacity: raw?.opacity === undefined ? 1 : toNumber(raw.opacity, 1),
    };

    if (type === 'text') {
        const mode = ['dynamic', 'static'].includes(raw?.mode)
            ? raw.mode
            : ((typeof raw?.text === 'string' && raw.text.trim() !== '') ? 'static' : 'dynamic');
        return {
            ...base,
            mode,
            text: typeof raw?.text === 'string' ? raw.text : '',
            font_size: toNumber(raw?.font_size ?? raw?.font_size_mm, 2.8),
            font_weight: raw?.font_weight ? String(raw.font_weight) : '400',
            color: raw?.color ? String(raw.color) : '#111827',
        };
    }

    return base;
};

const loadEditorFromForm = () => {
    try {
        const parsed = JSON.parse(form.config_json_text || '{}');
        const canvas = {
            width_mm: toNumber(parsed?.canvas?.width_mm, toNumber(form.width_mm, 85.6)),
            height_mm: toNumber(parsed?.canvas?.height_mm, toNumber(form.height_mm, 54)),
        };
        const elements = Array.isArray(parsed?.elements)
            ? parsed.elements.filter((item) => item && typeof item === 'object').map((item, idx) => normalizeElement(item, idx))
            : [];

        editorConfig.value = {
            schema_version: toNumber(parsed?.schema_version, configSchemaVersion),
            canvas,
            elements: elements.length > 0 ? elements : createDefaultConfig().elements,
        };
    } catch {
        editorConfig.value = createDefaultConfig();
    }

    selectedElementIndex.value = editorConfig.value.elements.length > 0 ? 0 : -1;
    normalizeEditorConfig();
    syncFormConfigText();
    resetHistory();
};

const syncFormConfigText = () => {
    const sortedElements = [...editorConfig.value.elements].sort((a, b) => toNumber(a.z, 0) - toNumber(b.z, 0));
    form.config_json_text = JSON.stringify({
        schema_version: configSchemaVersion,
        canvas: {
            width_mm: toNumber(editorConfig.value.canvas.width_mm, 85.6),
            height_mm: toNumber(editorConfig.value.canvas.height_mm, 54),
        },
        elements: sortedElements,
    }, null, 2);
};

const normalizeElementBounds = (element) => {
    const canvasW = toNumber(editorConfig.value.canvas.width_mm, 85.6);
    const canvasH = toNumber(editorConfig.value.canvas.height_mm, 54);

    element.x = snapMm(element.x);
    element.y = snapMm(element.y);
    element.opacity = Number(clamp(toNumber(element.opacity, 1), 0, 1).toFixed(2));
    element.z = Math.round(toNumber(element.z, 10));

    if (element.type === 'text') {
        element.font_size = Math.max(0.5, snapMm(element.font_size ?? 2.8));
        element.x = clamp(element.x, 0, canvasW);
        element.y = clamp(element.y, 0, canvasH);
        return;
    }

    element.w = Math.max(1, snapMm(element.w ?? 20));
    element.h = Math.max(1, snapMm(element.h ?? 10));
    element.x = clamp(element.x, 0, Math.max(0, canvasW - element.w));
    element.y = clamp(element.y, 0, Math.max(0, canvasH - element.h));
    element.w = clamp(element.w, 1, Math.max(1, canvasW - element.x));
    element.h = clamp(element.h, 1, Math.max(1, canvasH - element.y));
};

const normalizeEditorConfig = () => {
    editorConfig.value.canvas.width_mm = Math.max(20, roundMm(editorConfig.value.canvas.width_mm));
    editorConfig.value.canvas.height_mm = Math.max(20, roundMm(editorConfig.value.canvas.height_mm));
    editorConfig.value.elements.forEach((element) => normalizeElementBounds(element));
};

const pushHistory = () => {
    if (applyingHistory.value) return;
    const snapshot = cloneConfig(editorConfig.value);
    const serialized = JSON.stringify(snapshot);
    const current = historyStack.value[historyIndex.value];

    if (current && JSON.stringify(current) === serialized) {
        return;
    }

    if (historyIndex.value < historyStack.value.length - 1) {
        historyStack.value = historyStack.value.slice(0, historyIndex.value + 1);
    }

    historyStack.value.push(snapshot);
    historyIndex.value = historyStack.value.length - 1;
};

const resetHistory = () => {
    historyStack.value = [cloneConfig(editorConfig.value)];
    historyIndex.value = 0;
};

const canUndo = computed(() => historyIndex.value > 0);
const canRedo = computed(() => historyIndex.value >= 0 && historyIndex.value < historyStack.value.length - 1);

const applyHistoryAt = (targetIndex) => {
    const snapshot = historyStack.value[targetIndex];
    if (!snapshot) return;

    applyingHistory.value = true;
    editorConfig.value = cloneConfig(snapshot);
    selectedElementIndex.value = editorConfig.value.elements.length === 0
        ? -1
        : clamp(selectedElementIndex.value, 0, editorConfig.value.elements.length - 1);
    syncFormConfigText();
    applyingHistory.value = false;
    historyIndex.value = targetIndex;
};

const undoEditor = () => {
    if (!canUndo.value) return;
    applyHistoryAt(historyIndex.value - 1);
};

const redoEditor = () => {
    if (!canRedo.value) return;
    applyHistoryAt(historyIndex.value + 1);
};

const commitEditorChange = () => {
    normalizeEditorConfig();
    syncFormConfigText();
    pushHistory();
};

const setForm = (values = {}) => {
    const next = blankForm();

    Object.keys(next).forEach((key) => {
        form[key] = values[key] ?? next[key];
    });

    editorConfig.value.canvas.width_mm = toNumber(form.width_mm, 85.6);
    editorConfig.value.canvas.height_mm = toNumber(form.height_mm, 54);
};

const availableBackgrounds = computed(() => {
    const templateId = Number(editingId.value);
    const institutionId = Number(form.institution_id);

    return props.backgroundAssets.filter((asset) => {
        if (asset.owner_type === 'institution') {
            return Number(asset.institution_id) === institutionId;
        }

        // Backward compatibility: old backgrounds may still be owned by template.
        if (asset.owner_type === 'card_template') {
            return Number(asset.owner_id) === templateId;
        }

        return false;
    });
});
const backgroundAssetById = computed(() => new Map(
    props.backgroundAssets.map((asset) => [Number(asset.id), asset]),
));
const editorBackgroundUrl = computed(() => {
    const assetId = Number(form.background_front_media_id);
    if (!Number.isFinite(assetId) || assetId <= 0) return '';

    return backgroundAssetById.value.get(assetId)?.stream_download_url ?? '';
});
const statusMessage = computed(() => page.props.flash?.status ?? '');
const hasFormErrors = computed(() => Object.keys(form.errors).length > 0);

const startCreate = () => {
    editingId.value = null;
    setForm();
    loadEditorFromForm();
    form.clearErrors();
};

const editTemplate = (template) => {
    editingId.value = template.id;
    setForm({
        ...template,
        config_json_text: JSON.stringify(template.config_json, null, 2),
        print_layout_json_text: JSON.stringify(template.print_layout_json, null, 2),
    });
    loadEditorFromForm();
    form.clearErrors();
};

const submit = () => {
    // Ensure in-progress drag/resize and input mutations are serialized before request.
    endPointerInteraction();
    commitEditorChange();

    const normalizedId = (value) => {
        const raw = typeof value === 'string' ? value.trim() : value;
        if (raw === '' || raw === null || raw === undefined) return null;
        const num = Number(raw);
        return Number.isFinite(num) && num > 0 ? num : null;
    };

    form.transform((data) => ({
        ...data,
        institution_id: props.forcedInstitutionId ?? normalizedId(data.institution_id),
        card_type_id: normalizedId(data.card_type_id),
        background_front_media_id: normalizedId(data.background_front_media_id),
        background_back_media_id: normalizedId(data.background_back_media_id),
        width_mm: toNumber(data.width_mm, 85.6),
        height_mm: toNumber(data.height_mm, 54),
        is_active: Boolean(data.is_active),
    }));

    if (editingId.value) {
        form.put(route('card-templates.update', editingId.value), {
            preserveScroll: true,
            preserveState: false,
        });
        return;
    }

    form.post(route('card-templates.store'), {
        preserveScroll: true,
        preserveState: false,
    });
};

const destroyTemplate = (id) => {
    if (!window.confirm('Hapus template ini?')) {
        return;
    }

    form.delete(route('card-templates.destroy', id), { preserveScroll: true });
};

const viewTemplate = (template) => {
    previewTemplate.value = template;
};

const closePreview = () => {
    previewTemplate.value = null;
};

const canvasWidthPx = computed(() => toNumber(editorConfig.value.canvas.width_mm, 85.6) * mmScale);
const canvasHeightPx = computed(() => toNumber(editorConfig.value.canvas.height_mm, 54) * mmScale);
const selectedElement = computed(() => editorConfig.value.elements[selectedElementIndex.value] ?? null);
const sortedElements = computed(() => (
    [...editorConfig.value.elements]
        .map((element, index) => ({ element, index }))
        .sort((a, b) => toNumber(a.element.z, 0) - toNumber(b.element.z, 0))
));

const mmToPx = (mm) => toNumber(mm, 0) * mmScale;
const pxToMm = (px) => px / mmScale;
const previewScale = 6;

const sourceLabel = (source) => {
    const candidate = [...textSourceOptions, ...imageSourceOptions]
        .find((item) => item.value === source);
    return candidate?.label ?? source;
};

const normalizeColorHex = (value, fallback = '#000000') => {
    const raw = typeof value === 'string' ? value.trim() : '';
    if (/^#([0-9a-fA-F]{6})$/.test(raw)) return raw.toUpperCase();
    return fallback;
};

const hasTextColorOption = (value) => textColorOptions.some((option) => option.value === normalizeColorHex(value, ''));

const selectedElementPreviewLabel = (element) => {
    if (element.type === 'text') {
        if (element.mode === 'static') {
            return element.text?.trim() || '(Static Text)';
        }

        return sourceLabel(element.source || element.key || '');
    }

    return sourceLabel(element.source || element.key || '');
};

const previewConfigFor = (template) => {
    const config = template?.config_json && typeof template.config_json === 'object'
        ? template.config_json
        : {};

    const canvas = {
        width_mm: toNumber(config?.canvas?.width_mm, template?.width_mm ?? 85.6),
        height_mm: toNumber(config?.canvas?.height_mm, template?.height_mm ?? 54),
    };
    const elements = Array.isArray(config?.elements)
        ? config.elements.filter((item) => item && typeof item === 'object').map((item, idx) => normalizeElement(item, idx))
        : [];

    return {
        canvas,
        elements: elements.sort((a, b) => toNumber(a.z, 0) - toNumber(b.z, 0)),
    };
};

const previewCanvas = computed(() => previewConfigFor(previewTemplate.value).canvas);
const previewElements = computed(() => previewConfigFor(previewTemplate.value).elements);
const previewCanvasStyle = computed(() => ({
    width: `${toNumber(previewCanvas.value.width_mm, 85.6) * previewScale}px`,
    height: `${toNumber(previewCanvas.value.height_mm, 54) * previewScale}px`,
}));

const previewBackgroundUrl = (template, side = 'front') => {
    const assetId = side === 'back'
        ? template?.background_back_media_id
        : template?.background_front_media_id;
    if (!assetId) return '';

    return backgroundAssetById.value.get(Number(assetId))?.stream_download_url ?? '';
};

const previewElementStyle = (element) => {
    const base = {
        position: 'absolute',
        left: `${toNumber(element.x, 0) * previewScale}px`,
        top: `${toNumber(element.y, 0) * previewScale}px`,
        opacity: toNumber(element.opacity, 1),
    };

    if (element.type === 'text') {
        return {
            ...base,
            width: element.w ? `${toNumber(element.w, 0) * previewScale}px` : 'max-content',
            maxWidth: `${Math.max(1, toNumber(previewCanvas.value.width_mm, 85.6) - toNumber(element.x, 0)) * previewScale}px`,
            color: element.color || '#111827',
            fontSize: `${toNumber(element.font_size, 2.8) * previewScale}px`,
            fontWeight: element.font_weight || '400',
            lineHeight: 1.15,
            whiteSpace: 'nowrap',
        };
    }

    return {
        ...base,
        width: `${toNumber(element.w, 20) * previewScale}px`,
        height: `${toNumber(element.h, 10) * previewScale}px`,
    };
};

const previewTextValue = (element) => {
    if (element.type !== 'text') return '';
    return element.mode === 'static'
        ? (element.text?.trim() || '(Static Text)')
        : sourceLabel(element.source || element.key || '');
};

const ensureElementSourceDefaults = (element) => {
    if (element.type === 'text') {
        if (!['dynamic', 'static'].includes(element.mode)) {
            element.mode = 'dynamic';
        }
        if (element.mode === 'dynamic' && (!element.source || !String(element.source).trim())) {
            element.source = 'student.name';
        }
        return;
    }

    element.mode = 'dynamic';
    if (!element.source || !String(element.source).trim()) {
        element.source = element.type === 'photo' ? 'media.student_photo' : 'media.institution_logo';
    }
};

const syncElementKeyFromSource = (element) => {
    const source = typeof element.source === 'string' ? element.source.trim() : '';
    if (source === '' || source.startsWith('legacy.')) return;

    const mapped = legacyKeyBySource[source];
    if (mapped) {
        element.key = mapped;
    }
};

const presetDefinitions = {
    student_name: {
        label: '+ Nama',
        config: {
            type: 'text',
            source: 'student.name',
            mode: 'dynamic',
            text: '',
            font_size: 3.2,
            font_weight: '700',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    exam_number: {
        label: '+ No Ujian',
        config: {
            type: 'text',
            source: 'student.exam_number',
            mode: 'dynamic',
            text: '',
            font_size: 2.8,
            font_weight: '500',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    classroom_name: {
        label: '+ Kelas',
        config: {
            type: 'text',
            source: 'student.classroom_name',
            mode: 'dynamic',
            text: '',
            font_size: 2.8,
            font_weight: '500',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    institution_name: {
        label: '+ Nama Sekolah',
        config: {
            type: 'text',
            source: 'institution.name',
            mode: 'dynamic',
            text: '',
            font_size: 3,
            font_weight: '700',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    institution_address: {
        label: '+ Alamat Sekolah',
        config: {
            type: 'text',
            source: 'institution.address',
            mode: 'dynamic',
            text: '',
            font_size: 2.2,
            font_weight: '400',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    no_urut_label: {
        label: '+ Label No Urut',
        config: {
            type: 'text',
            source: '',
            mode: 'static',
            text: 'NO URUT',
            font_size: 2.8,
            font_weight: '700',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    no_urut_value: {
        label: '+ Nilai No Urut',
        config: {
            type: 'text',
            source: 'student.exam_number',
            mode: 'dynamic',
            text: '',
            font_size: 2.8,
            font_weight: '600',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    institution_logo: {
        label: '+ Logo',
        config: {
            type: 'image',
            source: 'media.institution_logo',
            mode: 'dynamic',
            text: '',
            w: 12,
            h: 12,
        },
    },
    student_photo: {
        label: '+ Foto',
        config: {
            type: 'photo',
            source: 'media.student_photo',
            mode: 'dynamic',
            text: '',
            w: 20,
            h: 26,
        },
    },
    leader_signature: {
        label: '+ TTD',
        config: {
            type: 'image',
            source: 'media.leader_signature',
            mode: 'dynamic',
            text: '',
            w: 20,
            h: 8,
        },
    },
    institution_stamp: {
        label: '+ Stempel',
        config: {
            type: 'image',
            source: 'media.institution_stamp',
            mode: 'dynamic',
            text: '',
            w: 16,
            h: 16,
            opacity: 0.65,
        },
    },
    leader_nip: {
        label: '+ NIP Pimpinan',
        config: {
            type: 'text',
            source: 'institution.leader_nip',
            mode: 'dynamic',
            text: '',
            font_size: 2.2,
            font_weight: '500',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
    static_label: {
        label: '+ Label Statis',
        config: {
            type: 'text',
            source: '',
            mode: 'static',
            text: 'LABEL',
            font_size: 2.8,
            font_weight: '600',
            color: '#111827',
            w: 0,
            h: 0,
        },
    },
};

const elementPresets = Object.entries(presetDefinitions).map(([value, item]) => ({
    value,
    label: item.label,
}));

const addPresetElement = (presetKey) => {
    const preset = presetDefinitions[presetKey];
    if (!preset) return;

    const index = editorConfig.value.elements.length;
    const { config } = preset;
    const baseType = config.type;
    const item = normalizeElement({
        type: baseType,
        key: legacyKeyBySource[config.source] ?? '',
        source: config.source,
        mode: config.mode,
        text: config.text,
        x: 5,
        y: 5 + (index * 2),
        w: config.w,
        h: config.h,
        z: (index + 1) * 10,
        font_size: config.font_size ?? 2.8,
        font_weight: config.font_weight ?? '400',
        color: config.color ?? '#111827',
        opacity: config.opacity ?? 1,
    }, index);

    editorConfig.value.elements.push(item);
    selectedElementIndex.value = editorConfig.value.elements.length - 1;
    commitEditorChange();
};

const removeSelectedElement = () => {
    if (selectedElementIndex.value < 0) return;
    editorConfig.value.elements.splice(selectedElementIndex.value, 1);
    selectedElementIndex.value = editorConfig.value.elements.length > 0
        ? clamp(selectedElementIndex.value, 0, editorConfig.value.elements.length - 1)
        : -1;
    commitEditorChange();
};

const elementStyle = (element) => ({
    position: 'absolute',
    left: `${mmToPx(element.x)}px`,
    top: `${mmToPx(element.y)}px`,
    width: element.type === 'text' ? 'auto' : `${mmToPx(element.w)}px`,
    height: element.type === 'text' ? 'auto' : `${mmToPx(element.h)}px`,
    opacity: toNumber(element.opacity, 1),
});

const editorTextValue = (element) => {
    if (element.type !== 'text') return '';
    if (element.mode === 'static') {
        return element.text?.trim() || '(Static Text)';
    }

    return sourceLabel(element.source || element.key || '');
};

const editorTextStyle = (element, index) => ({
    color: element.color || '#111827',
    fontSize: `${mmToPx(toNumber(element.font_size, 2.8))}px`,
    fontWeight: element.font_weight || '400',
    lineHeight: 1,
    whiteSpace: 'nowrap',
    backgroundColor: selectedElementIndex.value === index ? 'rgba(14,165,233,0.08)' : 'transparent',
    outline: selectedElementIndex.value === index ? '1px dashed #0ea5e9' : 'none',
    outlineOffset: '1px',
    padding: selectedElementIndex.value === index ? '1px 2px' : '0',
    borderRadius: '2px',
    pointerEvents: 'none',
});

const startElementDrag = (event, index) => {
    if (event.button !== 0) return;
    selectedElementIndex.value = index;
    const element = editorConfig.value.elements[index];
    dragState.value = {
        index,
        startX: event.clientX,
        startY: event.clientY,
        originX: toNumber(element.x, 0),
        originY: toNumber(element.y, 0),
    };
};

const startElementResize = (event, index) => {
    event.stopPropagation();
    if (event.button !== 0) return;
    selectedElementIndex.value = index;
    const element = editorConfig.value.elements[index];
    resizeState.value = {
        index,
        startX: event.clientX,
        startY: event.clientY,
        originW: toNumber(element.w, 20),
        originH: toNumber(element.h, 10),
    };
};

const handlePointerMove = (event) => {
    if (dragState.value) {
        const state = dragState.value;
        const element = editorConfig.value.elements[state.index];
        if (!element) return;

        const deltaMmX = pxToMm(event.clientX - state.startX);
        const deltaMmY = pxToMm(event.clientY - state.startY);
        const maxX = Math.max(0, toNumber(editorConfig.value.canvas.width_mm, 85.6) - (element.type === 'text' ? 0 : toNumber(element.w, 0)));
        const maxY = Math.max(0, toNumber(editorConfig.value.canvas.height_mm, 54) - (element.type === 'text' ? 0 : toNumber(element.h, 0)));
        element.x = snapMm(clamp(state.originX + deltaMmX, 0, maxX));
        element.y = snapMm(clamp(state.originY + deltaMmY, 0, maxY));
        return;
    }

    if (resizeState.value) {
        const state = resizeState.value;
        const element = editorConfig.value.elements[state.index];
        if (!element || element.type === 'text') return;

        const deltaMmX = pxToMm(event.clientX - state.startX);
        const deltaMmY = pxToMm(event.clientY - state.startY);
        const maxW = Math.max(1, toNumber(editorConfig.value.canvas.width_mm, 85.6) - toNumber(element.x, 0));
        const maxH = Math.max(1, toNumber(editorConfig.value.canvas.height_mm, 54) - toNumber(element.y, 0));
        element.w = snapMm(clamp(state.originW + deltaMmX, 1, maxW));
        element.h = snapMm(clamp(state.originH + deltaMmY, 1, maxH));
    }
};

const endPointerInteraction = () => {
    if (dragState.value || resizeState.value) {
        commitEditorChange();
    }
    dragState.value = null;
    resizeState.value = null;
};

const resetToDefaultEditor = () => {
    editorConfig.value = createDefaultConfig();
    form.width_mm = editorConfig.value.canvas.width_mm;
    form.height_mm = editorConfig.value.canvas.height_mm;
    selectedElementIndex.value = editorConfig.value.elements.length > 0 ? 0 : -1;
    commitEditorChange();
};

const applyCanvasSizeFromForm = () => {
    editorConfig.value.canvas.width_mm = toNumber(form.width_mm, 85.6);
    editorConfig.value.canvas.height_mm = toNumber(form.height_mm, 54);
    commitEditorChange();
};

const onSelectedElementInput = () => {
    if (!selectedElement.value) return;
    ensureElementSourceDefaults(selectedElement.value);
    if (selectedElement.value.type === 'text' && selectedElement.value.mode !== 'static') {
        syncElementKeyFromSource(selectedElement.value);
    }
    if (selectedElement.value.type !== 'text') {
        syncElementKeyFromSource(selectedElement.value);
    }
    if (selectedElement.value.type === 'text') {
        selectedElement.value.color = normalizeColorHex(selectedElement.value.color, '#000000');
    }
    normalizeElementBounds(selectedElement.value);
    syncFormConfigText();
};

const onSelectedElementCommit = () => {
    if (!selectedElement.value) return;
    onSelectedElementInput();
    commitEditorChange();
};

const onEditorKeydown = (event) => {
    if (!(event.ctrlKey || event.metaKey)) return;

    const key = event.key.toLowerCase();
    if (key === 'z' && !event.shiftKey) {
        event.preventDefault();
        undoEditor();
        return;
    }

    if (key === 'y' || (key === 'z' && event.shiftKey)) {
        event.preventDefault();
        redoEditor();
    }
};

onMounted(() => {
    loadEditorFromForm();
    window.addEventListener('mousemove', handlePointerMove);
    window.addEventListener('mouseup', endPointerInteraction);
    window.addEventListener('keydown', onEditorKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', handlePointerMove);
    window.removeEventListener('mouseup', endPointerInteraction);
    window.removeEventListener('keydown', onEditorKeydown);
});
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
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <section class="order-2 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit Template' : 'Buat Template' }}</h3>
                    <p class="mt-1 text-sm text-gray-500">Background media baru bisa dipilih setelah template memiliki ID dan file diunggah.</p>
                    <div v-if="statusMessage" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        {{ statusMessage }}
                    </div>
                    <div v-if="hasFormErrors" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        <p class="font-medium">Template belum tersimpan. Periksa input berikut:</p>
                        <ul class="mt-1 list-inside list-disc">
                            <li v-for="(message, key) in form.errors" :key="key">{{ message }}</li>
                        </ul>
                    </div>

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
                                <p v-if="form.errors.card_type_id" class="mt-1 text-xs text-rose-600">{{ form.errors.card_type_id }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nama Template</label>
                            <input v-model="form.name" class="w-full rounded-lg border-gray-300 text-sm" type="text" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Lebar (mm)</label>
                                <input v-model="form.width_mm" class="w-full rounded-lg border-gray-300 text-sm" type="number" step="0.1" @change="applyCanvasSizeFromForm" />
                                <p v-if="form.errors.width_mm" class="mt-1 text-xs text-rose-600">{{ form.errors.width_mm }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Tinggi (mm)</label>
                                <input v-model="form.height_mm" class="w-full rounded-lg border-gray-300 text-sm" type="number" step="0.1" @change="applyCanvasSizeFromForm" />
                                <p v-if="form.errors.height_mm" class="mt-1 text-xs text-rose-600">{{ form.errors.height_mm }}</p>
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
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h4 class="text-sm font-semibold text-gray-900">Visual Editor (MVP)</h4>
                                <div class="flex flex-wrap gap-2">
                                    <label class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700">
                                        <input v-model="snapToGrid" type="checkbox" class="rounded border-gray-300" @change="commitEditorChange" />
                                        Snap
                                    </label>
                                    <select
                                        v-model.number="snapStepMm"
                                        class="rounded-lg border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700"
                                        :disabled="!snapToGrid"
                                        @change="commitEditorChange"
                                    >
                                        <option :value="0.25">0.25 mm</option>
                                        <option :value="0.5">0.5 mm</option>
                                        <option :value="1">1 mm</option>
                                        <option :value="2">2 mm</option>
                                    </select>
                                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50" :disabled="!canUndo" @click="undoEditor">
                                        Undo
                                    </button>
                                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50" :disabled="!canRedo" @click="redoEditor">
                                        Redo
                                    </button>
                                    <button
                                        v-for="preset in elementPresets"
                                        :key="preset.value"
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700"
                                        @click="addPresetElement(preset.value)"
                                    >
                                        {{ preset.label }}
                                    </button>
                                    <button type="button" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700" :disabled="selectedElementIndex < 0" @click="removeSelectedElement">
                                        Hapus Elemen
                                    </button>
                                    <button type="button" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700" @click="resetToDefaultEditor">
                                        Reset Default
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr),320px]">
                                <div class="overflow-auto rounded-lg border border-gray-200 bg-white p-4">
                                    <div
                                        class="relative rounded-md border border-dashed border-gray-300 bg-gradient-to-br from-white to-gray-100"
                                        :style="{ width: `${canvasWidthPx}px`, height: `${canvasHeightPx}px` }"
                                    >
                                        <img
                                            v-if="editorBackgroundUrl"
                                            :src="editorBackgroundUrl"
                                            alt=""
                                            class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover"
                                        />
                                        <div
                                            v-for="{ element, index } in sortedElements"
                                            :key="`editor-element-${index}-${element.key}`"
                                            :style="elementStyle(element)"
                                            class="group cursor-move select-none"
                                            @mousedown="startElementDrag($event, index)"
                                        >
                                            <template v-if="element.type === 'text'">
                                                <div :style="editorTextStyle(element, index)">
                                                    {{ editorTextValue(element) }}
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div
                                                    class="rounded border px-1 py-0.5 text-[10px] leading-tight"
                                                    :class="selectedElementIndex === index ? 'border-sky-500 bg-sky-50 text-sky-900' : 'border-gray-300 bg-white text-gray-600'"
                                                >
                                                    <template v-if="element.type === 'photo'">
                                                        P: {{ selectedElementPreviewLabel(element) }}
                                                    </template>
                                                    <template v-else>
                                                        I: {{ selectedElementPreviewLabel(element) }}
                                                    </template>
                                                </div>
                                            </template>
                                            <div
                                                v-if="element.type !== 'text'"
                                                class="absolute bottom-0 right-0 h-3 w-3 cursor-se-resize rounded-sm border border-sky-500 bg-sky-400"
                                                @mousedown="startElementResize($event, index)"
                                            />
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[11px] text-gray-500">
                                        Drag elemen untuk memindahkan posisi. Untuk elemen image/photo, tarik handle kanan-bawah untuk resize.
                                    </p>
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        Shortcut: Ctrl/Cmd+Z untuk undo, Ctrl/Cmd+Shift+Z atau Ctrl/Cmd+Y untuk redo.
                                    </p>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-white p-3">
                                    <h5 class="text-xs font-semibold uppercase tracking-wide text-gray-700">Properti Elemen</h5>
                                    <div v-if="selectedElement" class="mt-3 space-y-2 text-xs">
                                        <label class="block">
                                            <span class="mb-1 block text-gray-600">Type</span>
                                            <select v-model="selectedElement.type" class="w-full rounded border-gray-300 text-xs" @change="onSelectedElementCommit">
                                                <option value="text">text</option>
                                                <option value="photo">photo</option>
                                                <option value="image">image</option>
                                            </select>
                                        </label>
                                        <template v-if="selectedElement.type === 'text'">
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">Mode</span>
                                                <select v-model="selectedElement.mode" class="w-full rounded border-gray-300 text-xs" @change="onSelectedElementCommit">
                                                    <option value="dynamic">dynamic</option>
                                                    <option value="static">static</option>
                                                </select>
                                            </label>
                                            <label v-if="selectedElement.mode === 'dynamic'" class="block">
                                                <span class="mb-1 block text-gray-600">Source</span>
                                                <select v-model="selectedElement.source" class="w-full rounded border-gray-300 text-xs" @change="onSelectedElementCommit">
                                                    <option v-for="option in textSourceOptions" :key="option.value" :value="option.value">
                                                        {{ option.label }}
                                                    </option>
                                                </select>
                                            </label>
                                            <label v-else class="block">
                                                <span class="mb-1 block text-gray-600">Static Text</span>
                                                <input v-model="selectedElement.text" class="w-full rounded border-gray-300 text-xs" type="text" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                        </template>
                                        <label v-else class="block">
                                            <span class="mb-1 block text-gray-600">Source</span>
                                            <select v-model="selectedElement.source" class="w-full rounded border-gray-300 text-xs" @change="onSelectedElementCommit">
                                                <option v-for="option in imageSourceOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-gray-600">Legacy Key</span>
                                            <input v-model="selectedElement.key" class="w-full rounded border-gray-300 text-xs" type="text" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                        </label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">X (mm)</span>
                                                <input v-model.number="selectedElement.x" class="w-full rounded border-gray-300 text-xs" type="number" step="0.1" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">Y (mm)</span>
                                                <input v-model.number="selectedElement.y" class="w-full rounded border-gray-300 text-xs" type="number" step="0.1" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                        </div>
                                        <div v-if="selectedElement.type !== 'text'" class="grid grid-cols-2 gap-2">
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">W (mm)</span>
                                                <input v-model.number="selectedElement.w" class="w-full rounded border-gray-300 text-xs" type="number" step="0.1" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">H (mm)</span>
                                                <input v-model.number="selectedElement.h" class="w-full rounded border-gray-300 text-xs" type="number" step="0.1" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">Z</span>
                                                <input v-model.number="selectedElement.z" class="w-full rounded border-gray-300 text-xs" type="number" step="1" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">Opacity</span>
                                                <input v-model.number="selectedElement.opacity" class="w-full rounded border-gray-300 text-xs" type="number" min="0" max="1" step="0.05" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                        </div>
                                        <div v-if="selectedElement.type === 'text'" class="grid grid-cols-2 gap-2">
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">Font size</span>
                                                <input v-model.number="selectedElement.font_size" class="w-full rounded border-gray-300 text-xs" type="number" step="0.1" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                            <label class="block">
                                                <span class="mb-1 block text-gray-600">Weight</span>
                                                <input v-model="selectedElement.font_weight" class="w-full rounded border-gray-300 text-xs" type="text" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
                                            </label>
                                        </div>
                                        <label v-if="selectedElement.type === 'text'" class="block">
                                            <span class="mb-1 block text-gray-600">Color</span>
                                            <select v-model="selectedElement.color" class="w-full rounded border-gray-300 text-xs" @change="onSelectedElementCommit">
                                                <option
                                                    v-if="selectedElement.color && !hasTextColorOption(selectedElement.color)"
                                                    :value="selectedElement.color"
                                                >
                                                    Current → {{ selectedElement.color }}
                                                </option>
                                                <option v-for="option in textColorOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }} → {{ option.value }}
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                    <p v-else class="mt-3 text-xs text-gray-500">
                                        Pilih elemen di canvas untuk edit properti.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700">Config JSON</label>
                                <button type="button" class="rounded border border-gray-300 px-2 py-1 text-[11px] font-medium text-gray-700" @click="loadEditorFromForm">
                                    Reload Editor dari JSON
                                </button>
                            </div>
                            <textarea v-model="form.config_json_text" class="min-h-56 w-full rounded-lg border-gray-300 font-mono text-xs" />
                            <p v-if="form.errors.config_json_text" class="mt-1 text-xs text-rose-600">{{ form.errors.config_json_text }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Print Layout JSON</label>
                            <textarea v-model="form.print_layout_json_text" class="min-h-40 w-full rounded-lg border-gray-300 font-mono text-xs" />
                            <p v-if="form.errors.print_layout_json_text" class="mt-1 text-xs text-rose-600">{{ form.errors.print_layout_json_text }}</p>
                        </div>
                        <label class="flex items-center gap-3 text-sm text-gray-700">
                            <input v-model="form.is_active" class="rounded border-gray-300" type="checkbox" />
                            Template aktif
                        </label>
                        <div class="flex gap-3">
                            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="form.processing">
                                {{ editingId ? 'Simpan Perubahan' : 'Buat Template' }}
                            </button>
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700" @click="startCreate">
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section class="order-1 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
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
                                            <button
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-sky-300 px-2.5 text-xs font-medium text-sky-700 hover:bg-sky-50"
                                                title="Lihat template"
                                                @click="viewTemplate(template)"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M2.25 12s3.5-6.75 9.75-6.75S21.75 12 21.75 12 18.25 18.75 12 18.75 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                Lihat
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
                                                title="Edit template"
                                                @click="editTemplate(template)"
                                            >
                                                <span class="sr-only">Edit</span>
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M12 20h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50"
                                                title="Hapus template"
                                                @click="destroyTemplate(template.id)"
                                            >
                                                <span class="sr-only">Hapus</span>
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M3 6h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                                    <path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="M19 6l-1 14a1.75 1.75 0 0 1-1.75 1.5h-8.5A1.75 1.75 0 0 1 6 20L5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="M10 11v6M14 11v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                                </svg>
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

        <Modal :show="previewTemplate !== null" max-width="2xl" @close="closePreview">
            <div v-if="previewTemplate" class="p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ previewTemplate.name }}</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ previewTemplate.card_type_name }} - {{ previewTemplate.width_mm }} x {{ previewTemplate.height_mm }} mm
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50"
                        title="Tutup preview"
                        @click="closePreview"
                    >
                        <span class="sr-only">Tutup</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="mt-5 space-y-5 overflow-x-auto">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Depan</p>
                        <div class="inline-block rounded-xl bg-gray-100 p-3">
                            <div
                                class="relative overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                                :style="previewCanvasStyle"
                            >
                                <img
                                    v-if="previewBackgroundUrl(previewTemplate, 'front')"
                                    :src="previewBackgroundUrl(previewTemplate, 'front')"
                                    alt=""
                                    class="absolute inset-0 h-full w-full object-cover"
                                />
                                <div
                                    v-for="(element, index) in previewElements"
                                    :key="`preview-front-${index}-${element.key}`"
                                    :style="previewElementStyle(element)"
                                >
                                    <template v-if="element.type === 'text'">
                                        <span class="inline-flex rounded border border-sky-300 bg-white/90 px-1 py-0.5 text-[10px] font-medium leading-tight text-sky-900 shadow-sm">
                                            T: {{ previewTextValue(element) }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <div class="flex h-full w-full items-center justify-center rounded border border-dashed border-gray-400 bg-white/70 text-[10px] font-medium text-gray-500">
                                            {{ selectedElementPreviewLabel(element) }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="previewBackgroundUrl(previewTemplate, 'back')">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Belakang</p>
                        <div class="inline-block rounded-xl bg-gray-100 p-3">
                            <div
                                class="relative overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                                :style="previewCanvasStyle"
                            >
                                <img
                                    :src="previewBackgroundUrl(previewTemplate, 'back')"
                                    alt=""
                                    class="absolute inset-0 h-full w-full object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
