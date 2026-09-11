<?php

namespace App\Services;

use App\Models\Berita;
use App\Models\Ekskul;
use App\Models\Jurusan;
use App\Models\Prestasi;
use App\Models\Profil;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

trait ChatbotTrait
{
    protected function getContext(): string
    {
        return Cache::remember('chatbot_school_context', 600, function () {
            $dbContext = $this->getDatabaseContext();
            if (! empty($dbContext)) {
                return $dbContext;
            }

            return $this->getFileContext();
        });
    }

    protected function getFileContext(): string
    {
        $summaryFile = storage_path('app/data/summary_sekolah.txt');

        if (File::exists($summaryFile)) {
            return File::get($summaryFile);
        }

        $context = '';
        if (File::isDirectory(storage_path('app/data'))) {
            foreach (File::files(storage_path('app/data')) as $file) {
                $context .= File::get($file->getPathname())."\n";
            }
        }

        return mb_strimwidth($context, 0, 3000, '...');
    }

    protected function getDatabaseContext(): ?string
    {
        try {
            if (! Schema::hasTable('profils')) {
                return null;
            }

            $profil = Profil::with('misis')->first();
            if (! $profil) {
                return null;
            }

            $sections = [];

            $kepalaSekolah = ! empty($profil->visiImageName) ? $profil->visiImageName : 'M. Lukman Hakim, ST., MM';
            $profilText = "=== Profil SMK PGRI 3 Malang (SKARIGA) ===\n";
            $profilText .= "Nama: SMK PGRI 3 Malang (SKARIGA)\n";
            $profilText .= "Kepala Sekolah: {$kepalaSekolah}\n";
            if (! empty($profil->visiDesc)) {
                $profilText .= "Visi: {$profil->visiDesc}\n";
            }
            if ($profil->misis && $profil->misis->isNotEmpty()) {
                $misiList = $profil->misis->pluck('misiTitle')->filter()->implode(', ');
                $profilText .= "Misi: {$misiList}\n";
            }
            if (! empty($profil->profilDesc)) {
                $profilText .= "Sejarah & Profil: {$profil->profilDesc}\n";
            }
            $sections[] = trim($profilText);

            if (Schema::hasTable('jurusans')) {
                $jurusans = Jurusan::all();
                if ($jurusans->isNotEmpty()) {
                    $jurusanText = "=== PROGRAM KEAHLIAN / JURUSAN ===\n";
                    foreach ($jurusans as $j) {
                        $desc = ! empty($j->deskripsi) ? " - {$j->deskripsi}" : '';
                        $dept = ! empty($j->departemen) ? " [Dept: {$j->departemen}]" : '';
                        $jurusanText .= "• {$j->jurusan}{$dept}{$desc}\n";
                    }
                    $sections[] = trim($jurusanText);
                }
            }

            if (Schema::hasTable('ekskuls')) {
                $ekskuls = Ekskul::all();
                if ($ekskuls->isNotEmpty()) {
                    $ekskulList = $ekskuls->pluck('title')->filter()->implode(', ');
                    $sections[] = "=== EKSTRAKURIKULER ===\n{$ekskulList}";
                }
            }

            if (Schema::hasTable('prestasis')) {
                $prestasis = Prestasi::latest()->take(5)->get();
                if ($prestasis->isNotEmpty()) {
                    $prestasiText = "=== PRESTASI TERBARU ===\n";
                    foreach ($prestasis as $p) {
                        $sub = ! empty($p->subjudul) ? " ({$p->subjudul})" : '';
                        $prestasiText .= "• {$p->nama}{$sub}\n";
                    }
                    $sections[] = trim($prestasiText);
                }
            }

            if (Schema::hasTable('beritas')) {
                $beritas = Berita::latest()->take(3)->get();
                if ($beritas->isNotEmpty()) {
                    $beritaText = "=== BERITA TERKINI ===\n";
                    foreach ($beritas as $b) {
                        $beritaText .= "• {$b->title}\n";
                    }
                    $sections[] = trim($beritaText);
                }
            }

            $contactText = "=== LOKASI & KONTAK RESMI ===\n";
            $contactText .= "Alamat: Jl. Raya Tlogomas Gg. 9 No.29, Lowokwaru, Kota Malang, Jawa Timur 65144\n";
            $contactText .= "WhatsApp / Call Center: +62-821-3300-0370\n";
            $contactText .= "Telepon: (0341) 554383 / (0341) 362065\n";
            $contactText .= "Website: https://smkpgri3malang.sch.id\n";
            $contactText .= 'Google Maps: https://maps.app.goo.gl/WnFCmvAJwg9GwM4A8';
            $sections[] = $contactText;

            return implode("\n\n", $sections);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getSystemPrompt(string $context): string
    {
        return "
Kamu adalah SKARIBOT, AI Asisten Resmi SMK PGRI 3 Malang (SKARIGA).

Batasan Peran & Ruang Lingkup:
1. Kamu HANYA melayani pertanyaan yang berkaitan langsung dengan informasi resmi SMK PGRI 3 Malang (profil, visi-misi, jurusan/program keahlian, pendaftaran/PPDB, biaya, fasilitas, ekstrakurikuler, prestasi, kontak, lokasi, dan kegiatan sekolah).
2. DILARANG KERAS menjawab pertanyaan di luar informasi SMK PGRI 3 Malang, termasuk: pembuatan script/kode pemrograman (Python, PHP, JavaScript, dsb.), pengerjaan tugas/PR umum, rumus matematika/sains umum, resep, hiburan, politik, atau percakapan umum lainnya.
3. DILARANG mengait-ngaitkan pertanyaan umum (seperti coding/script) ke jurusan sekolah (misal RPL/TIK) agar bisa menjawabnya. Jika pengguna meminta script, kode, atau materi teknis umum, kamu WAJIB MENOLAK.
4. Jika pertanyaan di luar lingkup informasi SMK PGRI 3 Malang, TOLAK dengan sopan, ramah, dan tegas. Sampaikan bahwa kamu hanya melayani informasi seputar SMK PGRI 3 Malang, dan tawarkan bantuan terkait info sekolah.

Aturan Respon & Format:
1. Jawab ramah, sopan, ringkas, dan to-the-point seputar SMK PGRI 3 Malang.
2. Gunakan tag HTML <b>Judul</b> untuk teks tebal (JANGAN pernah gunakan Markdown asteris seperti **teks** atau *teks*).
3. JANGAN pernah menggunakan blok kode Markdown (```).
4. Untuk daftar list, gunakan format numbering (1, 2) atau bullet sederhana.
5. Jika user minta kontak admin/manusia, berikan link: <a href='https://wa.me/6282133000370' style='color: blue;'>Chat Admin</a>.
6. Jika ditanya lokasi, sertakan link: <a href='https://maps.app.goo.gl/WnFCmvAJwg9GwM4A8' style='color: blue;'>Lokasi Google Maps</a>.
7. Jika ditanya pembuatmu, jawab: 'Dibuat oleh tim pengembang SKARIGA CTRL + V'.

Data Acuan Sekolah:
{$context}
";
    }

    protected function getHttpClient(array $headers = []): PendingRequest
    {
        $http = Http::withHeaders(array_merge([
            'Content-Type' => 'application/json',
        ], $headers));

        if (! app()->environment('production')) {
            $http->withoutVerifying();
        }

        return $http;
    }

    protected function sanitizeKey(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        $cleaned = trim(preg_replace('/[\x00-\x1F\x7F\xA0"\']/u', '', $key));

        return ! empty($cleaned) ? $cleaned : null;
    }

    protected function parseApiKeys(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        $parts = explode(',', $raw);
        $keys = [];
        foreach ($parts as $part) {
            $cleaned = $this->sanitizeKey($part);
            if (! empty($cleaned)) {
                $keys[] = $cleaned;
            }
        }

        return array_values(array_unique($keys));
    }

    protected function getRotatedKeys(array $keys, string $cacheKey = 'groq_key_index'): array
    {
        $count = count($keys);
        if ($count <= 1) {
            return $keys;
        }

        try {
            $index = (int) cache()->increment($cacheKey);
        } catch (\Throwable) {
            $index = random_int(0, $count - 1);
        }

        $startIndex = abs($index) % $count;
        $ordered = [];
        for ($i = 0; $i < $count; $i++) {
            $ordered[] = $keys[($startIndex + $i) % $count];
        }

        return $ordered;
    }

    protected function getFriendlyFallbackMessage(): string
    {
        return "Maaf, asisten AI SKARIBOT saat ini sedang mengalami lonjakan antrean. Silakan coba beberapa saat lagi atau hubungi admin sekolah melalui WhatsApp di <a href='https://wa.me/6282133000370' target='_blank' style='color: blue;'>Chat Admin</a>.";
    }

    public function formatChatResponse(string $response): string
    {
        $text = stripslashes($response);
        $text = preg_replace('/\\\\([*_\-#`~\[\]\(\)])/u', '$1', $text);
        $text = preg_replace('/^[ \t]*[-*]\s+/m', '• ', $text);
        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '<b>$1</b>', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<b>$1</b>', $text);
        $text = preg_replace('/(?<!\*)\*([^\*\n]+)\*(?!\*)/u', '<b>$1</b>', $text);
        $text = preg_replace('/`{1,3}(.*?)`{1,3}/s', '$1', $text);

        return trim($text);
    }
}
