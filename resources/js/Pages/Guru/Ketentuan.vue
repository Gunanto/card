<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Ketentuan Guru" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Ketentuan Penggunaan Guru</h2>
                <p class="text-sm text-gray-500">Aturan operasional agar data valid, proses generate stabil, dan hasil cetak konsisten.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">1. Ketentuan Data Siswa</h3>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-700">
                        <li><span class="font-mono">student_code</span> wajib unik per institusi dan tidak boleh berubah sembarangan.</li>
                        <li>Nama siswa wajib terisi dan gunakan penulisan sesuai dokumen resmi.</li>
                        <li>Pastikan siswa berada di kelas yang benar sebelum generate batch.</li>
                    </ul>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">2. Ketentuan Import Foto Siswa (ZIP)</h3>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-700">
                        <li>Nama file foto harus sama dengan <span class="font-mono">student_code</span>, contoh: <span class="font-mono">SIS-0001.jpg</span>.</li>
                        <li>Format foto yang diterima: <span class="font-mono">jpg</span>, <span class="font-mono">jpeg</span>, <span class="font-mono">png</span>, <span class="font-mono">webp</span>.</li>
                        <li>Ukuran maksimal setiap foto: <span class="font-mono">500KB</span>.</li>
                        <li>Dimensi minimal setiap foto: <span class="font-mono">300x300 px</span>.</li>
                        <li>ZIP hanya boleh berisi file foto yang relevan untuk siswa di institusi Anda.</li>
                    </ul>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">3. Ketentuan Generate Kartu</h3>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-700">
                        <li>Gunakan template yang aktif dan sesuai kebutuhan kartu.</li>
                        <li>Lakukan pengecekan data inti (nama, kode, kelas, foto) sebelum queue batch.</li>
                        <li>Jika ada status gagal, perbaiki data/foto lalu generate ulang siswa yang gagal.</li>
                    </ul>
                </section>

                <section class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-amber-900">4. Checklist Sebelum Cetak</h3>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-amber-900">
                        <li>Pastikan batch berstatus selesai dan progres 100%.</li>
                        <li>Cek sampel 1-2 kartu untuk validasi posisi teks/foto.</li>
                        <li>Unduh PDF final hanya setelah semua error import dan generate terselesaikan.</li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
