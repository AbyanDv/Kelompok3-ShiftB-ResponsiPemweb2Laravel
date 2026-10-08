<a id="readme-top"></a>

<!-- PROJECT SHIELDS -->
[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <h3 align="center">Smart-Kas API</h3>

  <p align="center">
    Sistem Manajemen Uang Kas & Iuran Berbasis API (Laravel 13)
    <br />
    <br />
    <a href="https://github.com/AbyanDv/SmartKas-Kelontong/issues">Report Bug</a>
    &middot;
    <a href="https://github.com/AbyanDv/SmartKas-Kelontong/issues">Request Feature</a>
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->
## About The Project

Smart-Kas adalah aplikasi berbasis API untuk mengelola uang kas/iuran secara terpusat dan transparan. Proyek ini dibangun untuk memudahkan bendahara dalam menagih, mencatat, dan memverifikasi pembayaran (termasuk integrasi QRIS), serta memberikan transparansi bagi para anggota untuk melihat status tagihan dan catatan kas.

Fitur Utama:
- **Autentikasi & Otorisasi**: Login berbasis token menggunakan Laravel Sanctum dengan pemisahan akses Admin (Ketua/Bendahara) dan Member.
- **Manajemen Tagihan (Bills)**: Pembuatan paket iuran/kas dan tagihan per anggota.
- **Pembayaran (Payments)**: Mendukung pembayaran via QRIS maupun Tunai.
- **Buku Kas (Ledger)**: Pencatatan otomatis pemasukan dan pengeluaran secara transparan.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

* [![Laravel][Laravel.com]][Laravel-url]
* [![PHP][PHP.com]][PHP-url]
* [![MySQL][MySQL.com]][MySQL-url]

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->
## Getting Started

Berikut adalah instruksi untuk menjalankan proyek ini di mesin lokal Anda.

### Prerequisites

Pastikan Anda telah menginstal beberapa perangkat lunak berikut:
* PHP >= 8.3
* Composer
* MySQL / MariaDB
* Node.js & npm

### Installation

1. Clone repository ini
   ```sh
   git clone https://github.com/AbyanDv/SmartKas-Kelontong.git
   ```
2. Masuk ke direktori proyek
   ```sh
   cd SmartKas-Kelontong
   ```
3. Install dependensi PHP via Composer
   ```sh
   composer install
   ```
4. Install dependensi Frontend (Vite)
   ```sh
   npm install
   ```
5. Salin file `.env.example` menjadi `.env`
   ```sh
   cp .env.example .env
   ```
6. Sesuaikan konfigurasi database di dalam file `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=smartkas
   DB_USERNAME=root
   DB_PASSWORD=
   ```
7. Generate APP_KEY Laravel
   ```sh
   php artisan key:generate
   ```
8. Jalankan migrasi dan seeder database
   ```sh
   php artisan migrate --seed
   ```
9. Jalankan server backend (dan frontend build)
   ```sh
   php artisan serve
   npm run dev
   ```

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE EXAMPLES -->
## Usage

Aplikasi ini berjalan sebagai REST API. Anda dapat menggunakan tools seperti Postman atau Insomnia untuk mengakses endpoint yang tersedia.

Beberapa contoh endpoint:
- `POST /api/auth/register`: Pendaftaran anggota baru
- `POST /api/auth/login`: Login untuk mendapatkan token akses
- `GET /api/users`: (Admin) Melihat daftar anggota
- `PUT /api/users/{id}`: (Admin) Memverifikasi anggota (`status: verified`)

_Catatan: Sistem secara default menggunakan email sintetis (`nim@smartkas.local`) untuk login dan pendaftaran._

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ROADMAP -->
## Roadmap

- [x] Struktur Database (Users, KasTypes, Bills, Payments, LedgerEntries)
- [x] Autentikasi Sanctum & Role Management
- [x] CRUD Anggota & Verifikasi
- [ ] Integrasi Tagihan Otomatis
- [ ] Integrasi Pembayaran QRIS & Webhook
- [ ] Integrasi Notifikasi Discord

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTRIBUTING -->
## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- LICENSE -->
## License

Distributed under the MIT License.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTACT -->
## Contact

Abyan - [GitHub Profile](https://github.com/AbyanDv)
Zeehza - [GitHub Profile](https://github.com/FahrizaSalam)
Rosyid - [GitHub Profile](https://github.com/justdotzy69)

Project Link: [https://github.com/AbyanDv/SmartKas-Kelontong](https://github.com/AbyanDv/SmartKas-Kelontong)

<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- MARKDOWN LINKS & IMAGES -->
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
[Laravel.com]: https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
[Laravel-url]: https://laravel.com
[PHP.com]: https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white
[PHP-url]: https://www.php.net/
[MySQL.com]: https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white
[MySQL-url]: https://www.mysql.com/
