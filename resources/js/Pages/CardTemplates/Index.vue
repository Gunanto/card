<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
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

const availableBackgrounds = computed(() =>
    props.backgroundAssets.filter((asset) => Number(asset.owner_id) === Number(editingId.value)),
);

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

const sourceLabel = (source) => {
    const candidate = [...textSourceOptions, ...imageSourceOptions]
        .find((item) => item.value === source);
    return candidate?.label ?? source;
};

const selectedElementPreviewLabel = (element) => {
    if (element.type === 'text') {
        if (element.mode === 'static') {
            return element.text?.trim() || '(Static Text)';
        }

        return sourceLabel(element.source || element.key || '');
    }

    return sourceLabel(element.source || element.key || '');
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
                                <input v-model="form.width_mm" class="w-full rounded-lg border-gray-300 text-sm" type="number" step="0.1" @change="applyCanvasSizeFromForm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Tinggi (mm)</label>
                                <input v-model="form.height_mm" class="w-full rounded-lg border-gray-300 text-sm" type="number" step="0.1" @change="applyCanvasSizeFromForm" />
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
                                        <div
                                            v-for="{ element, index } in sortedElements"
                                            :key="`editor-element-${index}-${element.key}`"
                                            :style="elementStyle(element)"
                                            class="group cursor-move select-none"
                                            @mousedown="startElementDrag($event, index)"
                                        >
                                            <div
                                                class="rounded border px-1 py-0.5 text-[10px] leading-tight"
                                                :class="selectedElementIndex === index ? 'border-sky-500 bg-sky-50 text-sky-900' : 'border-gray-300 bg-white text-gray-600'"
                                            >
                                                <template v-if="element.type === 'text'">
                                                    T: {{ selectedElementPreviewLabel(element) }}
                                                </template>
                                                <template v-else-if="element.type === 'photo'">
                                                    P: {{ selectedElementPreviewLabel(element) }}
                                                </template>
                                                <template v-else>
                                                    I: {{ selectedElementPreviewLabel(element) }}
                                                </template>
                                            </div>
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
                                            <input v-model="selectedElement.color" class="w-full rounded border-gray-300 text-xs" type="text" @input="onSelectedElementInput" @change="onSelectedElementCommit" />
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
