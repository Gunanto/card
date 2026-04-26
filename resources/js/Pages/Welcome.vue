<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: false,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
    landingStats: {
        type: Object,
        default: () => ({
            pdf_total_label: '0',
            uptime_label: 'N/A',
        }),
    },
});

const landingThemeStorageKey = 'landing_theme';
const isDark = ref(false);
const themeIconLabel = computed(() => (isDark.value ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'));

const applyTheme = (mode) => {
    isDark.value = mode === 'dark';
    try {
        localStorage.setItem(landingThemeStorageKey, mode);
    } catch {
        // noop for private browsing/storage-disabled
    }
};

const toggleTheme = () => {
    applyTheme(isDark.value ? 'light' : 'dark');
};

onMounted(() => {
    try {
        const stored = localStorage.getItem(landingThemeStorageKey);
        if (stored === 'dark' || stored === 'light') {
            isDark.value = stored === 'dark';
            return;
        }
    } catch {
        // noop
    }
    isDark.value = false;
});
</script>

<template>
    <Head title="CardGen - Generate Kartu, Sertifikat, dan Piagam Otomatis" />

    <div class="landing-root gradient-bg min-h-screen text-slate-800" :class="isDark ? 'theme-dark' : 'theme-light'">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-8">
            <div class="flex items-center gap-2">
                <svg
                    class="h-10 w-auto text-[#FF2D20]"
                    viewBox="0 0 62 65"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M61.8548 14.6253L29.0048 0.14524C28.7021 -0.00835413 28.3558 -0.00835413 28.0531 0.14524L1.20312 12.0152C0.468246 12.3372 0 13.0563 0 13.8452V51.1452C0 51.9342 0.468246 52.6533 1.20312 52.9753L28.0531 64.8453C28.2045 64.922 28.3698 64.9603 28.535 64.9603C28.7003 64.9603 28.8656 64.922 29.017 64.8453L60.792 50.8453C61.5269 50.5233 62 49.8042 62 49.0152V16.4452C62 15.6563 61.5318 14.9372 60.8548 14.6253H61.8548ZM28.535 5.28524L53.11 16.1452L28.535 27.0052L3.96 16.1452L28.535 5.28524ZM3.96 20.3052L26.56 30.3052V59.8252L3.96 49.8252V20.3052ZM30.51 59.8252V30.3052L53.11 20.3052V27.4252L38.435 33.9152C37.7001 34.2372 37.2319 34.9563 37.2319 35.7452V42.0252C37.2319 42.8142 37.7001 43.5333 38.435 43.8553L53.11 50.3453V53.2552L30.51 63.2252V59.8252ZM58.04 48.1752L53.11 50.3453V46.0252L58.04 43.8553V48.1752ZM58.04 39.5452L41.195 32.1152V28.7152L58.04 21.2852V39.5452Z"
                        fill="currentColor"
                    />
                </svg>
                <span class="text-2xl font-bold tracking-tight">Card<span class="text-[#FF2D20]">Gen</span></span>
            </div>

            <div class="hidden gap-8 text-sm font-medium text-slate-600 md:flex">
                <Link :href="route('landing.demo')" class="transition hover:text-[#FF2D20]">Fitur</Link>
                <Link :href="route('landing.template')" class="transition hover:text-[#FF2D20]">Template</Link>
                <Link :href="route('landing.pricing')" class="transition hover:text-[#FF2D20]">Harga</Link>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white/90 text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-white"
                    :aria-label="themeIconLabel"
                    @click="toggleTheme"
                >
                    <svg
                        v-if="isDark"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-5 w-5"
                    >
                        <circle cx="12" cy="12" r="4.5" />
                        <path d="M12 2.75v2.5M12 18.75v2.5M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M2.75 12h2.5M18.75 12h2.5M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77" />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-5 w-5"
                    >
                        <path d="M20.354 14.604A9 9 0 1 1 9.396 3.646a7 7 0 1 0 10.958 10.958Z" />
                    </svg>
                </button>

                <div v-if="canLogin" class="flex items-center">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-4 py-2 text-sm font-semibold hover:text-slate-900"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 text-sm font-semibold hover:text-slate-900"
                        >
                            Masuk
                        </Link>
                        <Link
                            :href="route('landing.signup')"
                            class="ml-2 rounded-lg bg-[#FF2D20] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#e02a1d]"
                        >
                            Mulai Gratis
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-7xl px-6 pb-24 pt-12">
            <div class="grid items-center gap-12 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#22c55e]">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                        </span>
                        Solusi Otomatisasi Dokumen
                    </div>

                    <h1 class="mb-6 text-5xl font-extrabold leading-[1.1] text-slate-900 lg:text-6xl">
                        Generate Kartu, Sertifikat, dan Piagam Otomatis
                        <span class="text-[#FF2D20]">dalam Hitungan Menit.</span>
                    </h1>

                    <p class="mb-10 text-lg leading-relaxed text-slate-600">
                        Unggah template desain, impor data peserta, lalu biarkan CardGen membuat dokumen massal yang rapi, konsisten, dan siap kirim.
                    </p>

                    <div class="flex flex-wrap gap-4">
                        <Link
                            v-if="canLogin"
                            :href="$page.props.auth.user ? route('dashboard') : route('login')"
                            class="flex items-center gap-2 rounded-xl bg-[#FF2D20] px-8 py-4 font-bold text-white shadow-lg shadow-red-200 transition hover:scale-105"
                        >
                            Mulai Gratis
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path
                                    fill-rule="evenodd"
                                    d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </Link>
                        <a
                            v-else
                            :href="route('landing.signup')"
                            class="flex items-center gap-2 rounded-xl bg-[#FF2D20] px-8 py-4 font-bold text-white shadow-lg shadow-red-200 transition hover:scale-105"
                        >
                            Mulai Gratis
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path
                                    fill-rule="evenodd"
                                    d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </a>
                        <Link :href="route('landing.demo')" class="rounded-xl border border-slate-200 bg-white px-8 py-4 font-bold text-slate-700 transition hover:bg-slate-50">
                            Lihat Demo
                        </Link>
                    </div>

                    <div class="mt-12 flex items-center gap-6 text-sm text-slate-500">
                        <div class="flex -space-x-2">
                            <img
                                class="h-8 w-8 rounded-full border-2 border-white ring-2 ring-slate-100"
                                src="https://ui-avatars.com/api/?name=User+1&background=random"
                                alt="User 1"
                            />
                            <img
                                class="h-8 w-8 rounded-full border-2 border-white ring-2 ring-slate-100"
                                src="https://ui-avatars.com/api/?name=User+2&background=random"
                                alt="User 2"
                            />
                            <img
                                class="h-8 w-8 rounded-full border-2 border-white ring-2 ring-slate-100"
                                src="https://ui-avatars.com/api/?name=User+3&background=random"
                                alt="User 3"
                            />
                        </div>
                        <p>Dipakai institusi pendidikan dan organisasi untuk mempercepat proses administrasi dokumen.</p>
                    </div>
                </div>

                <div class="relative lg:col-span-7">
                    <div class="animate-blob absolute -right-20 -top-20 h-96 w-96 rounded-full bg-red-100 opacity-30 blur-3xl"></div>

                    <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-white p-4 shadow-2xl">
                        <div class="group flex aspect-[4/3] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 p-8 transition hover:border-[#FF2D20]">
                            <div class="mb-6 w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div class="mb-4 flex items-center gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-50 text-[#FF2D20]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="mb-2 h-3 w-32 rounded-full bg-slate-200"></div>
                                        <div class="h-2 w-20 rounded-full bg-slate-100"></div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="h-2 w-full rounded-full bg-slate-50"></div>
                                    <div class="h-2 w-full rounded-full bg-slate-50"></div>
                                    <div class="h-2 w-2/3 rounded-full bg-slate-50"></div>
                                </div>
                            </div>
                            <p class="text-center font-medium text-slate-400">
                                Import Desain Eksternal (PNG/JPG)<br />
                                Lalu Generate PDF Masal
                            </p>
                        </div>

                        <div class="absolute bottom-10 right-10 flex animate-bounce items-center gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl">
                            <div class="rounded-full bg-green-100 p-2 text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Status</p>
                                <p class="text-sm font-bold text-slate-800">Batch Dokumen Berhasil Diproses</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="fitur" class="mt-32 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <div class="card-hover rounded-3xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"
                            />
                        </svg>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900">Generate Dokumen Massal</h3>
                    <p class="mb-6 leading-relaxed text-slate-600">
                        Buat ratusan hingga ribuan kartu, sertifikat, dan piagam sekaligus dari satu sumber data.
                    </p>
                    <Link :href="route('landing.template')" class="group flex items-center gap-1 font-semibold text-[#FF2D20]">
                        Selengkapnya
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </Link>
                </div>

                <div id="template" class="card-hover rounded-3xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-1.947m5.445 0a3.42 3.42 0 001.946 1.947m2.923 2.923a3.42 3.42 0 001.947 1.946m0 5.445a3.42 3.42 0 00-1.947 1.946m-2.923 2.923a3.42 3.42 0 00-1.946 1.947m-5.445 0a3.42 3.42 0 00-1.946-1.947m-2.923-2.923a3.42 3.42 0 00-1.947-1.946m0-5.445a3.42 3.42 0 001.947-1.946M12 3v1m0 16v1m9-9h-1M3 12H2m3.314-6.314l.707.707m12.686 12.686l.707.707M6.343 17.657l-.707.707m12.686-12.686l-.707.707"
                            />
                        </svg>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900">Template Fleksibel</h3>
                    <p class="mb-6 leading-relaxed text-slate-600">
                        Gunakan desain Anda sendiri dan atur posisi elemen agar hasil sesuai standar lembaga.
                    </p>
                    <Link :href="route('landing.pricing')" class="group flex items-center gap-1 font-semibold text-[#FF2D20]">
                        Pelajari Alur Kerja
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </Link>
                </div>

                <div id="harga" class="card-hover rounded-3xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-[#FF2D20]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900">Output PDF Siap Cetak</h3>
                    <p class="mb-6 leading-relaxed text-slate-600">
                        Hasilkan PDF berkualitas tinggi yang siap dicetak atau dibagikan secara digital.
                    </p>
                    <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-400">
                        <span class="rounded bg-slate-100 px-2 py-1">PDF/A</span>
                        <span class="rounded bg-slate-100 px-2 py-1">CMYK Ready</span>
                    </div>
                </div>

                <div class="card-hover rounded-3xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7h8m-8 4h8m-8 4h5m7 3a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2h14z"
                            />
                        </svg>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900">Import Data Cepat</h3>
                    <p class="mb-6 leading-relaxed text-slate-600">
                        Upload CSV/Excel untuk data peserta, termasuk dukungan foto agar proses produksi lebih efisien.
                    </p>
                </div>

                <div class="card-hover rounded-3xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 7h18M6 7V5a1 1 0 011-1h10a1 1 0 011 1v2m-1 0v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7"
                            />
                        </svg>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900">Manajemen Terpusat</h3>
                    <p class="mb-6 leading-relaxed text-slate-600">
                        Kelola institusi, kelas, peserta, media, dan riwayat batch dokumen dalam satu dashboard.
                    </p>
                </div>

                <div class="card-hover rounded-3xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 11c0-1.657 1.343-3 3-3h4v10h-4a3 3 0 01-3-3v-4zM5 8h7a3 3 0 013 3v4a3 3 0 01-3 3H5V8z"
                            />
                        </svg>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900">Akses Berbasis Peran</h3>
                    <p class="mb-6 leading-relaxed text-slate-600">
                        Pisahkan hak akses admin dan guru untuk menjaga keamanan serta kontrol operasional.
                    </p>
                </div>
            </div>

            <section class="mt-20 rounded-3xl border border-slate-100 bg-white p-8 md:p-10">
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-[#FF2D20]">Segera Hadir</p>
                <h2 class="mb-4 text-3xl font-bold text-slate-900">Sertifikat/Piagam Otomatis ke Email Peserta</h2>
                <p class="mb-8 max-w-3xl text-slate-600">
                    Setelah proses generate selesai, CardGen akan mengirim dokumen personal langsung ke email masing-masing user secara otomatis.
                </p>
                <div class="grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                    <p>- Template sertifikat/piagam khusus per program atau kegiatan</p>
                    <p>- Pengiriman email otomatis per batch</p>
                    <p>- Status pengiriman: terkirim, gagal, retry</p>
                    <p>- Log aktivitas dan riwayat distribusi dokumen</p>
                    <p>- Opsi jadwal kirim: instan atau terjadwal</p>
                </div>
            </section>

            <section class="mt-12 text-center">
                <p class="mx-auto max-w-3xl text-lg leading-relaxed text-slate-700">
                    CardGen membantu tim Anda mengurangi pekerjaan manual, menekan risiko salah data, dan mempercepat distribusi dokumen resmi.
                </p>
            </section>
        </main>

        <footer class="border-t border-slate-100 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-8 px-6 py-12 md:flex-row">
                <div class="text-sm text-slate-500">
                    &copy; 2026 CardGen dibuat oleh <span class="text-[#FF2D20]">PakGun</span>.
                </div>
                <div class="flex gap-12">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-slate-900">{{ landingStats.pdf_total_label }}</p>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PDF Dibuat</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-slate-900">{{ landingStats.uptime_label }}</p>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Uptime Sistem</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-slate-900">24/7</p>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dukungan</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:global(body) {
    font-family: 'Inter', sans-serif;
    background-color: #f8fafc;
}

.gradient-bg {
    background: radial-gradient(circle at 0% 0%, rgba(255, 45, 32, 0.05) 0%, transparent 50%);
}

.theme-dark {
    background-color: #0f172a;
    color: #e2e8f0;
}

.theme-dark.gradient-bg {
    background:
        radial-gradient(circle at 55% -24%, rgba(59, 130, 246, 0.24) 0%, rgba(15, 23, 42, 0) 60%),
        radial-gradient(circle at 0% 0%, rgba(56, 189, 248, 0.11) 0%, transparent 46%),
        #0f172a;
}

.theme-dark .bg-white {
    background-color: rgba(15, 23, 42, 0.72) !important;
}

.theme-dark .bg-white\/90 {
    background-color: rgba(15, 23, 42, 0.82) !important;
}

.theme-dark .bg-slate-50 {
    background-color: rgba(30, 41, 59, 0.65) !important;
}

.theme-dark .bg-slate-100 {
    background-color: rgba(51, 65, 85, 0.55) !important;
}

.theme-dark .border-slate-100,
.theme-dark .border-slate-200 {
    border-color: rgba(148, 163, 184, 0.22) !important;
}

.theme-dark .text-slate-900 {
    color: #f8fafc !important;
}

.theme-dark .text-slate-800 {
    color: #e2e8f0 !important;
}

.theme-dark .text-slate-700 {
    color: #cbd5e1 !important;
}

.theme-dark .text-slate-600 {
    color: #94a3b8 !important;
}

.theme-dark .text-slate-500,
.theme-dark .text-slate-400 {
    color: #94a3b8 !important;
}

.theme-dark .text-\[\#FF2D20\] {
    color: #facc15 !important;
}

.theme-dark .bg-\[\#FF2D20\] {
    background: linear-gradient(135deg, #fde047 0%, #facc15 58%, #eab308 100%) !important;
    color: #0f172a !important;
    border-color: rgba(250, 204, 21, 0.5) !important;
}

.theme-dark .bg-\[\#FF2D20\]:hover {
    filter: brightness(0.97);
    color: #0f172a !important;
}

.theme-dark .hover\:bg-\[\#e02a1d\]:hover {
    background-color: #eab308 !important;
    color: #0f172a !important;
}

.theme-dark .shadow-red-200 {
    --tw-shadow-color: rgba(250, 204, 21, 0.35) !important;
}

.theme-dark footer {
    background-color: rgba(15, 23, 42, 0.9) !important;
}

.theme-dark .animate-blob {
    background-color: rgba(56, 189, 248, 0.18) !important;
}

.card-hover {
    transition: all 0.3s ease;
}

.card-hover:hover {
    transform: translateY(-5px);
}

@keyframes blob {
    0% {
        transform: translate(0, 0) scale(1);
    }
    33% {
        transform: translate(20px, -30px) scale(1.05);
    }
    66% {
        transform: translate(-15px, 15px) scale(0.95);
    }
    100% {
        transform: translate(0, 0) scale(1);
    }
}

.animate-blob {
    animation: blob 9s infinite ease-in-out;
}
</style>
