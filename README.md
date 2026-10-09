# Smart-Kas API
> Sistem Manajemen Uang Kas & Iuran Berbasis API (Laravel 13)

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]

---

## Informasi Kelompok
- **Nomor Kelompok:** 3
- **Shift Praktikum:** B

---

## Anggota Kelompok

| No | Nama Lengkap | NIM | Shift Awal | Shift Akhir | Jobdesk / Kontribusi | Link Video Penjelasan |
|---|---|---|---|---|---|---|
| 1 | Abyan Devadi | H1H024049 | [Shift Awal] | [Shift Akhir] | Backend Kelontong | [YouTube](https://...) |
| 2 | Muhammad Aziz Ihza Fahriza Salam | H1H024050 | C | B | Backend & Project Manager | [YouTube](https://youtu.be/FGMHCtKBf6E) |
| 3 | Khoirul Rosyid Gunawan | H1H024036 | B | B | Business logic | [YouTube](https://...) |

---

## Deskripsi Aplikasi
SmartKas adalah aplikasi berbasis REST API untuk mengelola uang kas/iuran secara terpusat dan transparan. Aplikasi ini dibuat untuk membantu bendahara dalam menagih, mencatat, dan memverifikasi pembayaran (termasuk pembayaran via QRIS), serta memberi anggota akses untuk melihat status tagihan dan catatan kas.

- **Target pengguna:** Ketua/Bendahara (Admin) dan anggota (Member) organisasi, kelas, atau komunitas.
- **Problem yang diselesaikan:** pencatatan kas manual yang rawan salah, penagihan yang tidak terstruktur, dan kurangnya transparansi pemasukan serta pengeluaran kas.

---

## Penjelasan Teknis

### 1. Teknologi (Tech Stack)
- **Backend:** Laravel 13 (PHP >= 8.3), diakses sebagai REST API (uji dengan Postman/Insomnia)
- **Frontend:** Tailwind CSS (build aset dengan Vite)
- **Database:** MySQL
- **Library / Package:** Laravel Sanctum (autentikasi token), Composer, Node.js & npm (Vite)

### 2. Fitur Utama & Modul
- **Autentikasi & Otorisasi:** Login berbasis token (Laravel Sanctum) dengan pemisahan akses Admin (Ketua/Bendahara) dan Member. Login/registrasi menggunakan email sintetis `nim@smartkas.local`.
- **Manajemen Anggota:** CRUD anggota dan verifikasi anggota oleh Admin (`status: verified`).
- **Manajemen Tagihan (Bills):** Pembuatan paket iuran/kas (`KasTypes`) dan tagihan per anggota.
- **Pembayaran (Payments):** Mendukung pembayaran via QRIS maupun Tunai.
- **Buku Kas (Ledger):** Pencatatan otomatis pemasukan dan pengeluaran secara transparan (`LedgerEntries`).

**Contoh endpoint:**

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| POST | `/api/auth/register` | Publik | Pendaftaran anggota baru |
| POST | `/api/auth/login` | Publik | Login untuk mendapatkan token akses |
| GET | `/api/users` | Admin | Melihat daftar anggota |
| PUT | `/api/users/{id}` | Admin | Memverifikasi anggota |

**Roadmap:**
- [x] Struktur database (Users, KasTypes, Bills, Payments, LedgerEntries)
- [x] Autentikasi Sanctum & Role Management
- [x] CRUD Anggota & Verifikasi
- [ ] Integrasi Tagihan Otomatis
- [ ] Integrasi Pembayaran QRIS & Webhook
- [ ] Integrasi Notifikasi Discord

### 3. Skema Data Singkat
- `users` (1 : N) `bills`
- `kas_types` (1 : N) `bills`
- `bills` (1 : N) `payments`
- `payments` (1 : 1) `ledger_entries`

---

## Panduan Instalasi Lokal

**Prasyarat:** PHP >= 8.3, Composer, MySQL, Node.js & npm.

```bash
# Clone repository
git clone https://github.com/AbyanDv/SmartKas-Kelontong.git
cd SmartKas-Kelontong

# Install dependensi PHP & Node
composer install
npm install

# Konfigurasi Environment
cp .env.example .env
php artisan key:generate

# Konfigurasi database di file .env, lalu migrasi & seed
php artisan migrate --seed

# Jalankan development server
php artisan serve
npm run dev
```

Contoh konfigurasi database di `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartkas
DB_USERNAME=root
DB_PASSWORD=
```

---

## Kontak & Lisensi
- Abyan - [GitHub](https://github.com/AbyanDv)
- Aziz - [GitHub](https://github.com/FahrizaSalam)
- Rosyid - [GitHub](https://github.com/justdotzy69)
- Project Link: [https://github.com/AbyanDv/SmartKas-Kelontong](https://github.com/AbyanDv/SmartKas-Kelontong)

Didistribusikan di bawah MIT License.

[contributors-shield]: https://img.shields.io/github/contributors/AbyanDv/SmartKas-Kelontong.svg?style=for-the-badge
[contributors-url]: https://github.com/AbyanDv/SmartKas-Kelontong/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/AbyanDv/SmartKas-Kelontong.svg?style=for-the-badge
[forks-url]: https://github.com/AbyanDv/SmartKas-Kelontong/network/members
[stars-shield]: https://img.shields.io/github/stars/AbyanDv/SmartKas-Kelontong.svg?style=for-the-badge
[stars-url]: https://github.com/AbyanDv/SmartKas-Kelontong/stargazers
[issues-shield]: https://img.shields.io/github/issues/AbyanDv/SmartKas-Kelontong.svg?style=for-the-badge
[issues-url]: https://github.com/AbyanDv/SmartKas-Kelontong/issues
[license-shield]: https://img.shields.io/github/license/AbyanDv/SmartKas-Kelontong.svg?style=for-the-badge
[license-url]: https://github.com/AbyanDv/SmartKas-Kelontong/blob/main/LICENSE
