import { computed, ref } from 'vue';

const storageKey = 'app_theme';
const mode = ref('light');
let initialized = false;

const resolveSystemPrefersDark = () => {
    if (typeof window === 'undefined' || !window.matchMedia) return false;
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const resolveThemeClass = (requestedMode) => {
    if (requestedMode === 'system') {
        return resolveSystemPrefersDark() ? 'dark' : 'light';
    }
    return requestedMode === 'dark' ? 'dark' : 'light';
};

const applyDocumentClass = (requestedMode) => {
    if (typeof document === 'undefined') return;
    const actual = resolveThemeClass(requestedMode);
    document.documentElement.classList.toggle('dark', actual === 'dark');
};

const initTheme = () => {
    if (initialized) return;

    let stored = 'light';
    try {
        const raw = localStorage.getItem(storageKey);
        if (raw === 'light' || raw === 'dark' || raw === 'system') {
            stored = raw;
        }
    } catch {
        stored = 'light';
    }

    mode.value = stored;
    applyDocumentClass(mode.value);
    initialized = true;
};

const setThemeMode = (nextMode) => {
    const safeMode = ['light', 'dark', 'system'].includes(nextMode) ? nextMode : 'light';
    mode.value = safeMode;
    applyDocumentClass(safeMode);

    try {
        localStorage.setItem(storageKey, safeMode);
    } catch {
        // ignore storage errors
    }
};

const toggleTheme = () => {
    const isDarkNow = resolveThemeClass(mode.value) === 'dark';
    setThemeMode(isDarkNow ? 'light' : 'dark');
};

export const useTheme = () => {
    initTheme();

    const isDark = computed(() => resolveThemeClass(mode.value) === 'dark');

    return {
        mode,
        isDark,
        setThemeMode,
        toggleTheme,
    };
};

