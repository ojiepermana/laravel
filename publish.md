# Panduan Publish & Install Package

---

## Cara 1 — Install Langsung dari GitHub (Tanpa Packagist)

Cara tercepat, tidak perlu daftar ke Packagist. Cocok untuk package internal atau private.

### Di aplikasi yang akan menginstall package

Buka `composer.json` aplikasi Laravel-nya, tambahkan blok `repositories`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ojiepermana/laravel"
        }
    ],
    "require": {
        "ojiepermana/laravel": "dev-main"
    }
}
```

Lalu jalankan:

```bash
composer install
```

Atau langsung via command tanpa edit manual:

```bash
composer config repositories.ojiepermana vcs https://github.com/ojiepermana/laravel
composer require ojiepermana/laravel:dev-main
```

### Install versi/tag tertentu

Jika ingin install versi spesifik, buat **Git Tag** dulu di repo ini:

```bash
# Di repo package ini
git tag v1.0.0
git push origin v1.0.0
```

Maka di aplikasi bisa install:

```bash
composer require ojiepermana/laravel:^1.0.0
```

---

## Cara 2 — Publish ke Packagist (Cara Resmi)

Packagist adalah registry resmi Composer. Setelah publish, package bisa diinstall
cukup dengan `composer require ojiepermana/laravel` tanpa konfigurasi tambahan.

### Langkah-langkah

**1. Daftar atau login ke Packagist**

Buka [https://packagist.org](https://packagist.org) → login dengan akun GitHub.

**2. Submit package**

- Klik **"Submit"** di pojok kanan atas
- Masukkan URL repo: `https://github.com/ojiepermana/laravel`
- Klik **"Check"** → Packagist membaca `composer.json`
- Klik **"Submit"**

**3. Pasang GitHub Webhook (agar Packagist auto-update saat push)**

**Cara A — GitHub Integration (Rekomendasi)**

- Buka profil Packagist → **Settings** → **GitHub API Token**
- Generate token GitHub dengan scope `write:repo_hook`
- Paste token di Packagist → otomatis pasang webhook ke semua repo terdaftar

**Cara B — Manual Webhook**

Buka repo GitHub → **Settings** → **Webhooks** → **Add webhook**:

| Field | Nilai |
|---|---|
| Payload URL | `https://packagist.org/api/github?username=ojiepermana` |
| Content type | `application/json` |
| Secret | (kosongkan) |
| Events | Just the push event |

**4. Install di aplikasi lain**

```bash
composer require ojiepermana/laravel
```

---

## Versioning — Cara Buat Release Baru

Format: `vMAJOR.MINOR.PATCH`

| Jenis perubahan | Contoh |
|---|---|
| `PATCH` — bug fix | `v1.0.0` → `v1.0.1` |
| `MINOR` — fitur baru (backward compatible) | `v1.0.0` → `v1.1.0` |
| `MAJOR` — breaking change | `v1.0.0` → `v2.0.0` |

```bash
git tag v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

Packagist otomatis mendeteksi tag baru jika webhook sudah terpasang.

---

## Konfigurasi Setelah Install di Aplikasi

Tambahkan ke `.env`:

```env
BNI_CLIENT_ID=001
BNI_SECRET_KEY=ea0c88921fb033387e66ef7d1e82ab83
BNI_PREFIX=8
BNI_ECOLLECTION_URL=https://apibeta.bni-ecollection.com/
```

Service provider terdaftar otomatis via Laravel Package Auto-Discovery —
tidak perlu tambahkan manual ke `config/app.php`.

---

## Ringkasan Perbandingan

| | GitHub Langsung | Packagist |
|---|---|---|
| Daftar akun | Tidak perlu | Perlu (gratis) |
| Konfigurasi di app | Tambah `repositories` di composer.json | Tidak perlu |
| Install command | `composer require ojiepermana/laravel:dev-main` | `composer require ojiepermana/laravel` |
| Cocok untuk | Package internal / private | Package publik |
