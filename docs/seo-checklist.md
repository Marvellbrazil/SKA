# Checklist SEO di luar kode

Daftar pekerjaan yang tidak bisa dikerjakan lewat kode. Kodenya sudah ada di PR #12 (sitemap, canonical, structured data, 404 yang benar, satu `h1` per halaman, performa). Dokumen ini berisi sisanya.

Domain: `https://smkpgri3-malang.sch.id`

## 0. Deploy dulu

Search Console butuh sitemap yang sudah live.

```bash
git pull
npm install && npm run build
php artisan migrate --force
php artisan config:clear
```

`.env` produksi:

```env
APP_URL=https://smkpgri3-malang.sch.id
GOOGLE_SITE_VERIFICATION=
```

Setelah deploy, cek tiga URL:

- [ ] `/sitemap.xml` berisi XML dengan domain produksi, bukan `127.0.0.1`
- [ ] `/ngawur-xyz` membalas 404
- [ ] `/robots.txt` memuat baris `Sitemap:`

Jika `APP_URL` salah, canonical dan sitemap berisi domain yang salah.

## 1. Google Search Console

Tanpa langkah ini Google tidak punya alasan memeriksa situs.

- [ ] Tambah properti **Domain** `smkpgri3-malang.sch.id`, verifikasi lewat DNS TXT di Cloudflare. Alternatif: properti **URL prefix** dengan meta tag, token disalin ke `GOOGLE_SITE_VERIFICATION`, lalu deploy ulang.
- [ ] **Sitemaps**: kirim `sitemap.xml`.
- [ ] **Pemeriksaan URL**: minta pengindeksan untuk `/`, `/pendaftaran`, `/jurusan`.
- [ ] Buka laporan **Halaman**, cari status "Soft 404" atau "Dikecualikan" dari URL lama.
- [ ] Daftarkan **Bing Webmaster Tools**. Bisa impor dari Search Console.

## 2. Cloudflare

- [ ] **SSL/TLS > Edge Certificates**: aktifkan Always Use HTTPS.
- [ ] **DNS**: tambah record `www` (CNAME ke domain utama, proxied). Saat diaudit, `www.smkpgri3-malang.sch.id` tidak ada (NXDOMAIN).
- [ ] Redirect `www` ke non-`www` dengan 301.
- [ ] Opsional: aturan cache untuk `/build/*` (Edge TTL 1 tahun) dan `/assets/*`.

## 3. Google Business Profile

Ini yang paling menentukan untuk pencarian "SMK di Malang".

- [ ] Cari "SMK PGRI 3 Malang" di Google Maps. Jika sudah ada, klaim. Jika belum, buat.
- [ ] Kategori utama: Sekolah Menengah Kejuruan.
- [ ] Isi alamat, telepon, jam layanan, URL situs, dan tautan pendaftaran.
- [ ] Unggah foto gedung dan kegiatan praktik, lalu unggah rutin.
- [ ] Minta guru, staf, dan alumni menulis ulasan. Balas semua ulasan.

### Konfirmasi data NAP sebelum deploy

Nama, alamat, dan telepon harus sama persis di situs, Google Maps, Facebook, dan Instagram. Data di `config/seo.php` saat ini:

| Field | Nilai | Sumber |
|---|---|---|
| Alamat | Jl. Raya Tlogomas Gg. 9 No.29, Lowokwaru, Kota Malang, Jawa Timur 65144 | `app/Services/ChatbotTrait.php` |
| Telepon | (0341) 554383 | footer situs |
| WhatsApp | +62 821-3300-0370 | footer situs |
| Email | mail.smkpgri3malang@gmail.com | footer situs |

- [ ] Alamat benar? PDF rencana SEO menulis "Jl. Raya Tlogomas No. 29" tanpa "Gg. 9". Jika salah, ubah `address.street` di `config/seo.php`.
- [ ] Telepon dan email benar dan masih aktif?

## 4. Backlink

- [ ] Tautkan situs ke `bki-skariga.web.id` dan sebaliknya.
- [ ] Minta mitra industri (Daihatsu, Yamaha, Alfamart, dan lainnya) menautkan ke situs sekolah di halaman mitra mereka.
- [ ] Kirim siaran pers prestasi ke Suryamalang, Radar Malang, dan JatimTimes. Minta tautan balik ke situs.
- [ ] Cek URL situs di Dapodik dan `referensi.data.kemendikdasmen.go.id`.
- [ ] Taruh URL situs di bio Instagram, TikTok, Facebook, dan deskripsi kanal YouTube.

## 5. Konten

Halaman jurusan sudah ada, tetapi isinya masih deskripsi pendek dari admin (maksimal 500 karakter). Google tidak punya banyak bahan untuk dinilai.

- [ ] Lengkapi tiap jurusan: apa yang dipelajari, fasilitas, peluang kerja, kelas industri.
- [ ] Buat konten SPMB: jalur, biaya, fasilitas, kontak.
- [ ] Tulis berita rutin, minimal dua per bulan. Judul yang menjawab pertanyaan calon siswa, misalnya "Prospek karier lulusan Teknik Pembangkit Tenaga Listrik".
- [ ] Isi foto jurusan dan gambar hero profil lewat admin.

## 6. Pemantauan

| Kapan | Apa |
|---|---|
| Mingguan | Search Console: error indeks, kueri baru, halaman yang masuk |
| Bulanan | Posisi untuk "smk pgri 3 malang", "skariga", "smk di malang" |
| Kuartalan | Perbarui artikel lama, ukur ulang di PageSpeed Insights |

Google Analytics 4 belum dipasang. Pasang bila ingin melihat perilaku pengunjung, dengan mempertimbangkan persetujuan cookie.

## Perawatan kode

- Tampilan mengikuti commit `8d1ee1f`: font, ikon Font Awesome lengkap, dan Swiper 8 tidak diganti sebagai bagian optimasi SEO. Test presentasi menjaga dependensi tersebut.
- Mengganti nama jurusan mengubah slug-nya tanpa redirect.
- Gambar yang diunggah lewat admin (`/storage`) belum dikompresi otomatis.

## Ekspektasi

- Kueri nama sekolah ("smk pgri 3 malang", "skariga"): biasanya cepat setelah Search Console dan sitemap aktif.
- Kueri umum ("smk terbaik di malang"): butuh berbulan-bulan dan bergantung pada konten, backlink, dan ulasan. Kode tidak menjamin posisi.
