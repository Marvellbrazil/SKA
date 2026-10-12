<?php

namespace App\Services;

use App\Models\Berita;
use App\Models\Ekskul;
use App\Models\Jurusan;
use App\Models\Pendaftaran;
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
        return Cache::remember('chatbot_school_context_v4', 600, function () {
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

            $prestasiText = "=== PRESTASI SEKOLAH BERDASARKAN TINGKATAN (PRIORITAS PRESTASI) ===\n";
            $prestasiText .= "1. Prestasi Tingkat Internasional (Prioritas Pertama - 3 Terbaru):\n";
            $prestasiText .= "• Medali Emas ASEAN WorldSkill 2025 (Bidang IT Software Solution for Business - Edsel Parama Mustapa)\n";
            $prestasiText .= "• Medali Perunggu ASEAN WorldSkill 2025 (Bidang IT - Risca Revan Suasmara)\n";
            $prestasiText .= "• Delegasi WorldSkill Asia 2026\n\n";

            $prestasiText .= "2. Prestasi Tingkat Nasional (Prioritas Kedua - 3 Terbaru):\n";
            $prestasiText .= "• Juara 1 LKS Nasional 2025 (Bidang Robot Manufacturing System / RMS)\n";
            $prestasiText .= "• Juara 2 LKS Nasional 2025 (Bidang Industrial Control)\n";
            $prestasiText .= "• Juara 1 Sistem Informasi Festival 2024 (UI/UX Design) / Juara Nasional Lomba Coding SMK\n\n";

            $prestasiText .= "3. Prestasi Tingkat Regional (Prioritas Ketiga - 3 Terbaru):\n";
            $prestasiText .= "• Juara 1 LKS DikMen Jatim ke-33 (Industrial Control & Robot Manufacturing System)\n";
            $prestasiText .= "• Juara 1 LKS Jatim ke-32 (Electronics, Car Painting, IT Software Solution, Prototype Modelling)\n";
            $prestasiText .= "• Juara 2 LKS Jatim ke-32 (Web Technology) & Juara 3 (Marketing Online)\n";

            if (Schema::hasTable('prestasis')) {
                $dbPrestasis = Prestasi::latest()->take(6)->get();
                if ($dbPrestasis->isNotEmpty()) {
                    $prestasiText .= "\nPrestasi Tambahan Siswa di Database:\n";
                    foreach ($dbPrestasis as $p) {
                        $sub = ! empty($p->subjudul) ? " ({$p->subjudul})" : '';
                        $prestasiText .= "• {$p->nama}{$sub}\n";
                    }
                }
            }
            $sections[] = trim($prestasiText);

            $fasilitasText = "=== FASILITAS SEKOLAH ===\n";
            $fasilitasText .= "Fasilitas yang ada di SMK PGRI 3 Malang antara lain:\n";
            $fasilitasText .= "• Ruang kelas\n";
            $fasilitasText .= "• Laboratorium dan bengkel praktik\n";
            $fasilitasText .= "• Lapangan basket dan futsal\n";
            $fasilitasText .= "• Tablet gratis saat pendaftaran\n";
            $fasilitasText .= "• Sarana penunjang lainnya seperti BKI (Badan Kejasama Industri), Ruang BK, Kesiswaan, UKS, Ruang Kabid, Parkiran, dan lain-lain\n";
            $sections[] = trim($fasilitasText);

            if (Schema::hasTable('beritas')) {
                // Ambil berita terkait pendaftaran/PPDB dan berita terkini
                $beritaPpdb = Berita::where(function ($query) {
                    $query->where('title', 'like', '%daftar%')
                        ->orWhere('title', 'like', '%ppdb%')
                        ->orWhere('title', 'like', '%gelombang%')
                        ->orWhere('title', 'like', '%tutup%')
                        ->orWhere('deskripsi', 'like', '%daftar%')
                        ->orWhere('deskripsi', 'like', '%ppdb%')
                        ->orWhere('content', 'like', '%penutupan%');
                })->latest()->take(3)->get();

                $beritaTerbaru = Berita::latest()->take(3)->get();
                $allBeritas = $beritaPpdb->concat($beritaTerbaru)->unique('id');

                if ($allBeritas->isNotEmpty()) {
                    $beritaText = "=== BERITA & PENGUMUMAN SEKOLAH TERKINI ===\n";
                    foreach ($allBeritas as $b) {
                        $date = $b->created_at ? $b->created_at->format('d M Y') : '';
                        $desc = ! empty($b->deskripsi) ? " - {$b->deskripsi}" : '';
                        $beritaText .= "• [{$date}] {$b->title}{$desc}\n";
                    }
                    $sections[] = trim($beritaText);
                }
            }

            $pendaftaranStats = '';
            if (Schema::hasTable('pendaftarans')) {
                $pendaftarans = Pendaftaran::orderBy('tahun', 'desc')->take(3)->get();
                if ($pendaftarans->isNotEmpty()) {
                    $pendaftaranStats = "Data Statistik Penerimaan Siswa Baru:\n";
                    foreach ($pendaftarans as $p) {
                        $pendaftaranStats .= "• Tahun {$p->tahun}: Pendaftar {$p->jumlah_pendaftar}, Diterima {$p->jumlah_diterima}\n";
                    }
                }
            }

            $ppdbText = "=== INFORMASI PENDAFTARAN SISWA BARU (PPDB) ===\n";
            $ppdbText .= "1. Jadwal Pendaftaran:\n";
            $ppdbText .= "   • Pembukaan: Gelombang 1 SELALU dibuka pada tanggal 10 bulan 10 (10 Oktober).\n";
            $ppdbText .= "   • Penutupan: Mengikuti informasi pengumuman resmi di berita/database atau saat kuota terpenuhi. Jika belum ada tanggal penutupan resmi pada berita/database, sampaikan bahwa penutupan menyesuaikan terpenuhinya kuota dan pantau pengumuman di web atau WhatsApp admin.\n";
            $ppdbText .= "2. Dokumen Persyaratan Pendaftaran:\n";
            $ppdbText .= "   • Fotokopi Kartu Keluarga (KK)\n";
            $ppdbText .= "   • Fotokopi KTP orang tua / wali\n";
            $ppdbText .= "   • Ijazah atau Surat Keterangan Lulus (SKL) SMP/MTs (atau nilai rapor semester terakhir jika belum terbit)\n";
            $ppdbText .= "   • Pas foto ukuran 3x4 cm, berwarna dengan latar belakang MERAH atau BIRU\n";
            $ppdbText .= "   (Aturan Khusus: Jangan sebutkan catatan mengenai surat keterangan sehat pada dokumen persyaratan umum ini. Informasi bahwa surat sehat tidak diperlukan HANYA disampaikan jika pengguna bertanya mengenai surat sehat/kesehatan).\n";
            $ppdbText .= "3. Alur / Cara Pendaftaran:\n";
            $ppdbText .= "   • Pendaftaran Online: Hubungi WhatsApp Call Center 0821-3300-0370 (<a href='https://wa.me/6282133000370' style='color: blue;'>Chat Admin</a>), panitia akan memandu proses pendaftaran digital hingga tuntas.\n";
            $ppdbText .= "   • Pendaftaran Offline: Datang langsung ke kampus SMK PGRI 3 Malang di Jl. Raya Tlogomas Gg. 9 No.29, Lowokwaru, Malang. Buat akun pendaftaran di ruang administrasi, cek fisik & ukur seragam, isi formulir serta serahkan berkas persyaratan (fotokopi KK, KTP ortu, ijazah/SKL/rapor, pas foto background merah atau biru).\n";
            if (! empty($pendaftaranStats)) {
                $ppdbText .= "4. " . trim($pendaftaranStats) . "\n";
            }
            $sections[] = trim($ppdbText);

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

Panduan Khusus Informasi Pendaftaran (PPDB) & Pertanyaan 'Bagaimana cara mendaftar?':
1. Jika pengguna bertanya 'bagaimana cara daftar?' atau seputar jadwal dan persyaratan PPDB:
   - Jadwal Pembukaan: Beri tahu bahwa pendaftaran Gelombang 1 SELALU dibuka pada tanggal 10 bulan 10 (10 Oktober).
   - Informasi Penutupan Pendaftaran: Analisis informasi penutupan dari data berita atau data pendaftaran yang tercantum pada 'Data Acuan Sekolah'. Lakukan reasoning secara mandiri (internal) untuk menyimpulkan jadwal/status penutupan pendaftaran.
   - PENTING TENTANG REASONING: JANGAN PERNAH menampilkan langkah, proses, atau teks penalaran/reasoning Anda kepada pengguna (jangan tulis 'Berdasarkan analisis...', jangan tampilkan tag <think>, jangan perlihatkan pemikiran internal). Pengguna cukup menerima jawaban akhir yang rapi, ramah, dan jelas. Jika tidak ada tanggal penutupan pasti di berita/database, sampaikan bahwa penutupan biasanya mengikuti kuota yang telah terpenuhi atau dapat dipantau berkala melalui web resmi dan WhatsApp admin.
   - Dokumen Persyaratan:
     * Membawa fotokopi Kartu Keluarga (KK)
     * Fotokopi KTP orang tua / wali
     * Ijazah atau Surat Keterangan Lulus (SKL) SMP/MTs (atau nilai rapor semester terakhir)
     * Pas foto ukuran 3×4 cm, berwarna dengan latar belakang MERAH atau BIRU
     * ATURAN KETAT SURAT SEHAT: DILARANG menyebutkan atau menampilkan 'Catatan penting: Tidak diperlukan surat keterangan sehat' atau menyinggung surat kesehatan pada jawaban cara mendaftar / dokumen persyaratan ini. Informasi bahwa surat keterangan sehat tidak diperlukan CUKUP ditampilkan saat pengguna secara spesifik bertanya mengenai surat sehat / surat keterangan sehat.
   - Alur Pendaftaran: Jelaskan pilihan Online (WhatsApp Call Center 0821-3300-0370 / chat admin) dan Offline (datang ke kampus SKARIGA Jl. Raya Tlogomas Gg. 9 No.29 Malang).

Panduan Khusus Fasilitas Sekolah:
2. Jika pengguna bertanya mengenai 'Fasilitas sekolah apa saja?' atau seputar fasilitas yang ada di SMK PGRI 3 Malang, sebutkan fasilitas antara lain:
   • Ruang kelas
   • Laboratorium dan bengkel praktik
   • Lapangan basket dan futsal
   • Tablet gratis saat pendaftaran
   • Sarana penunjang lainnya seperti BKI (Badan Kejasama Industri), Ruang BK, Kesiswaan, UKS, Ruang Kabid, Parkiran, dan lain-lain

Panduan Khusus Kelebihan Sekolah ('kelebihan smk pgri 3 malang dengan sekolah lain?'):
3. Jika pengguna menanyakan 'kelebihan smk pgri 3 malang dengan sekolah lain?' atau perbandingan keunggulan sekolah:
   - Pada bagian prestasi sekolah/siswa, WAJIB menampilkan prestasi dengan urutan prioritas:
     1. Prioritas Pertama: Prestasi Internasional (sebutkan maksimal 3 terbaru jika ada datanya).
     2. Prioritas Kedua: Prestasi Nasional (sebutkan maksimal 3 terbaru jika ada datanya).
     3. Prioritas Ketiga: Prestasi Regional (sebutkan maksimal 3 terbaru jika ada datanya).
     (Jika suatu tingkatan tidak ada datanya, tidak perlu ditampilkan).
   - DILARANG KERAS menampilkan poin 'Lokasi Strategis' (seperti: Terletak di Jl. Raya Tlogomas Gg. 9 No. 29, Lowokwaru, Malang, mudah diakses dan dekat dengan fasilitas umum. Lokasi Google Maps) dalam daftar kelebihan sekolah. Jangan pernah tampilkan poin lokasi strategis sebagai poin kelebihan sekolah.

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

    public function formatChatResponse(string $response, string $prompt = ''): string
    {
        $text = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $response);
        $text = preg_replace('/^Reasoning:[\s\S]*?(?=\n\n|\n[0-9•<b>]|$)/i', '', $text);

        // Jika user tidak bertanya khusus tentang surat sehat, bersihkan catatan tidak perlunya surat sehat dari respons persyaratan
        if (! preg_match('/(?:surat\s*(?:keterangan\s*)?sehat|kesehatan)/i', $prompt)) {
            $text = preg_replace('/^[ \t]*[•\-\*]?\s*(?:Catatan\s*(?:penting)?\s*:\s*)?.*(?:tidak\s*(?:perlu|diperlukan|disyaratkan)\s*surat\s*(?:keterangan\s*)?sehat|surat\s*(?:keterangan\s*)?sehat\s*tidak\s*(?:perlu|diperlukan|disyaratkan)).*$\n?/im', '', $text);
        }

        // Jika user menanyakan kelebihan/keunggulan sekolah, jangan tampilkan poin lokasi strategis
        if (preg_match('/(?:kelebihan|keunggulan|banding|dibandingkan)/i', $prompt)) {
            $text = preg_replace('/(?:\n+)?(?:[0-9]+[.)]\s*(?:<b>)?)?Lokasi\s+Strategis(?:<\/b>)?[\s\S]*?(?=(?:\n+[0-9]+[.)]|\n+<b>[0-9]+[.)]|$))/i', '', $text);
        }

        $text = stripslashes($text);
        $text = preg_replace('/\\\\([*_\-#`~\[\]\(\)])/u', '$1', $text);
        $text = preg_replace('/^[ \t]*[-*]\s+/m', '• ', $text);
        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '<b>$1</b>', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<b>$1</b>', $text);
        $text = preg_replace('/(?<!\*)\*([^\*\n]+)\*(?!\*)/u', '<b>$1</b>', $text);
        $text = preg_replace('/`{1,3}(.*?)`{1,3}/s', '$1', $text);

        return trim($text);
    }
}
