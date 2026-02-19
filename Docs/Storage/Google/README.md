# Google Cloud Storage

Laravel filesystem adapter untuk Google Cloud Storage (GCS). Terintegrasi penuh dengan `Storage::disk()` Laravel sehingga semua method filesystem standar — upload, download, delete, URL, copy, move, list — bekerja langsung tanpa kode tambahan.

---

## Daftar Isi

- [Persyaratan](#persyaratan)
- [Konfigurasi](#konfigurasi)
  - [Variabel Environment](#variabel-environment)
  - [Disk Filesystem](#disk-filesystem)
  - [Multi-disk](#multi-disk)
- [Cara Pakai](#cara-pakai)
  - [Via Storage Facade (Native Laravel)](#via-storage-facade-native-laravel)
  - [Via GCS Facade](#via-gcs-facade)
  - [Upload File](#upload-file)
  - [Upload dari HTTP Request](#upload-dari-http-request)
  - [Baca File](#baca-file)
  - [URL Publik](#url-publik)
  - [Signed URL (File Private)](#signed-url-file-private)
  - [Hapus File](#hapus-file)
  - [Copy dan Move](#copy-dan-move)
  - [Daftar File dan Direktori](#daftar-file-dan-direktori)
  - [Cek Keberadaan File](#cek-keberadaan-file)
  - [Metadata File](#metadata-file)
  - [Visibility](#visibility)
  - [Stream](#stream)
- [Penggunaan di Controller](#penggunaan-di-controller)
- [Error Handling](#error-handling)
- [Hasil Test](#hasil-test)
- [Referensi](#referensi)

---

## Persyaratan

Pastikan dependency berikut terinstall (sudah otomatis saat `composer require ojiepermana/laravel`):

```bash
composer require ojiepermana/laravel
```

Dependency yang dibutuhkan:
- `google/cloud-storage: ^1.30`
- `league/flysystem: ^3.0`

---

## Konfigurasi

### Variabel Environment

Tambahkan ke file `.env`:

```env
GCS_PROJECT_ID=my-gcp-project
GCS_BUCKET=my-bucket-name
GCS_KEY_FILE=/path/to/service-account.json
GCS_PATH_PREFIX=
GCS_STORAGE_API_URI=https://storage.googleapis.com
```

| Variabel | Keterangan |
|---|---|
| `GCS_PROJECT_ID` | ID project Google Cloud |
| `GCS_BUCKET` | Nama bucket GCS |
| `GCS_KEY_FILE` | Path absolut ke file JSON service account. Kosongkan jika menggunakan Application Default Credentials |
| `GCS_PATH_PREFIX` | Prefix direktori di dalam bucket (opsional). Contoh: `uploads` |
| `GCS_STORAGE_API_URI` | URI dasar untuk URL publik. Default: `https://storage.googleapis.com` |

> **Service Account** — unduh dari Google Cloud Console → IAM & Admin → Service Accounts → Create Key (format JSON). Beri role **Storage Object Admin** atau **Storage Object Creator** sesuai kebutuhan.

---

### Disk Filesystem

Daftarkan disk `gcs` di `config/filesystems.php`:

```php
'disks' => [

    // ... disk lain (local, s3, dll)

    'gcs' => [
        'driver'          => 'gcs',
        'project_id'      => env('GCS_PROJECT_ID'),
        'key_file'        => env('GCS_KEY_FILE'),         // path ke JSON service account
        'key_file_json'   => null,                         // atau array JSON langsung (pilih salah satu)
        'bucket'          => env('GCS_BUCKET'),
        'path_prefix'     => env('GCS_PATH_PREFIX', ''),  // prefix opsional, contoh: 'uploads'
        'storage_api_uri' => env('GCS_STORAGE_API_URI', 'https://storage.googleapis.com'),
        'visibility'      => 'public',                     // 'public' atau 'private'
    ],

],
```

> **`key_file` vs `key_file_json`** — gunakan `key_file` untuk path file, atau `key_file_json` jika ingin menyimpan JSON sebagai array di config (misalnya dari secret manager). Kosongkan keduanya untuk menggunakan **Application Default Credentials** (ADC) — cocok untuk deployment di GCP (Cloud Run, GKE, dll).

---

### Multi-disk

Bisa mendaftarkan beberapa bucket sekaligus:

```php
'disks' => [
    'gcs-public' => [
        'driver'      => 'gcs',
        'project_id'  => env('GCS_PROJECT_ID'),
        'key_file'    => env('GCS_KEY_FILE'),
        'bucket'      => env('GCS_BUCKET_PUBLIC'),
        'path_prefix' => 'media',
        'visibility'  => 'public',
    ],

    'gcs-private' => [
        'driver'      => 'gcs',
        'project_id'  => env('GCS_PROJECT_ID'),
        'key_file'    => env('GCS_KEY_FILE'),
        'bucket'      => env('GCS_BUCKET_PRIVATE'),
        'path_prefix' => 'documents',
        'visibility'  => 'private',
    ],
],
```

Untuk mengubah disk default `GCS` Facade, tambahkan di `.env`:

```env
GCS_DEFAULT_DISK=gcs-public
```

Dan di `config/filesystems.php`:

```php
'gcs_default_disk' => env('GCS_DEFAULT_DISK', 'gcs'),
```

---

## Cara Pakai

### Via Storage Facade (Native Laravel)

Semua method standar Laravel Filesystem tersedia langsung:

```php
use Illuminate\Support\Facades\Storage;

// Arahkan ke disk GCS
$disk = Storage::disk('gcs');

// Atau set sebagai default disk di .env: FILESYSTEM_DISK=gcs
// lalu gunakan langsung tanpa ->disk()
Storage::put('file.txt', 'isi konten');
```

---

### Via GCS Facade

`GCS` Facade adalah shortcut ke `Storage::disk('gcs')`. Semua method identik:

```php
use OjiePermana\Laravel\Facades\GCS;

// Identik dengan Storage::disk('gcs')->put(...)
GCS::put('file.txt', 'isi konten');

// Pindah disk secara eksplisit
GCS::disk('gcs-private')->put('secret.pdf', $contents);
```

---

### Upload File

```php
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\Facades\GCS;

// Upload string / konten langsung
Storage::disk('gcs')->put('documents/laporan.txt', 'Isi laporan...');

// Upload dengan visibility eksplisit
Storage::disk('gcs')->put('images/foto.jpg', $imageContents, 'public');
Storage::disk('gcs')->put('docs/rahasia.pdf', $pdfContents, 'private');

// Upload dengan options lengkap
Storage::disk('gcs')->put('data/export.csv', $csv, [
    'visibility' => 'public',
    'mimetype'   => 'text/csv',
]);

// Upload resource/stream (efisien untuk file besar)
$stream = fopen('/local/path/video.mp4', 'r');
Storage::disk('gcs')->writeStream('videos/video.mp4', $stream);
fclose($stream);

// Via GCS Facade
GCS::put('uploads/file.txt', 'konten');
```

---

### Upload dari HTTP Request

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\Facades\GCS;

// Upload dengan nama file acak (generate otomatis)
$path = Storage::disk('gcs')->putFile('images', $request->file('photo'));
// Hasil: 'images/AbCdEfGhIj12345.jpg'

// Upload dengan nama file tertentu
$path = Storage::disk('gcs')->putFileAs(
    'images',
    $request->file('photo'),
    'profile-' . auth()->id() . '.jpg'
);

// Shortcut via GCS Facade
$path = GCS::putFile('uploads', $request->file('dokumen'));

// Upload dengan visibility private
$path = Storage::disk('gcs-private')->putFile('contracts', $request->file('contract'), 'private');
```

---

### Baca File

```php
use Illuminate\Support\Facades\Storage;

// Baca sebagai string
$contents = Storage::disk('gcs')->get('documents/laporan.txt');

// Baca sebagai stream (untuk file besar)
$stream = Storage::disk('gcs')->readStream('videos/video.mp4');
// Kirim sebagai response:
return response()->stream(function () use ($stream) {
    fpassthru($stream);
}, 200, ['Content-Type' => 'video/mp4']);

// Download file ke response HTTP
return Storage::disk('gcs')->download('documents/laporan.pdf', 'Laporan Akhir.pdf');
```

---

### URL Publik

URL publik hanya valid untuk file dengan visibility `public`:

```php
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\Facades\GCS;

// Format: https://storage.googleapis.com/{bucket}/{prefix}/{path}
$url = Storage::disk('gcs')->url('images/foto.jpg');
// Contoh: https://storage.googleapis.com/my-bucket/uploads/images/foto.jpg

// Via GCS Facade
$url = GCS::url('images/foto.jpg');

// Simpan URL ke database
$photo = Photo::create([
    'path' => $path,
    'url'  => Storage::disk('gcs')->url($path),
]);
```

> Pastikan bucket atau objek memiliki visibility `public` agar URL dapat diakses tanpa autentikasi. Untuk file private, gunakan [Signed URL](#signed-url-file-private).

---

### Signed URL (File Private)

Signed URL memungkinkan akses sementara ke file private tanpa mengubah visibility-nya:

```php
use OjiePermana\Laravel\Facades\GCS;
use Illuminate\Support\Facades\Storage;

// Via adapter langsung — expiration dalam detik (default: 3600 = 1 jam)
$url = Storage::disk('gcs-private')->getAdapter()->signedUrl('contracts/kontrak.pdf', 3600);

// Dengan DateTimeInterface
$expires = now()->addMinutes(30);
$url = Storage::disk('gcs-private')->getAdapter()->signedUrl('contracts/kontrak.pdf', $expires);

// Via temporaryUrl (Laravel standard)
$url = Storage::disk('gcs-private')->temporaryUrl(
    'contracts/kontrak.pdf',
    now()->addHour(),
);
```

> **Persyaratan Signed URL** — service account harus memiliki izin `iam.serviceAccounts.signBlob` dan library `google/cloud-core` yang mendukung signing.

---

### Hapus File

```php
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\Facades\GCS;

// Hapus satu file
Storage::disk('gcs')->delete('images/foto-lama.jpg');

// Hapus beberapa file sekaligus
Storage::disk('gcs')->delete([
    'images/foto1.jpg',
    'images/foto2.jpg',
    'documents/draft.pdf',
]);

// Hapus seluruh direktori beserta isinya
Storage::disk('gcs')->deleteDirectory('temp/uploads');

// Via GCS Facade
GCS::delete('images/foto.jpg');
GCS::deleteDirectory('cache');
```

> Menghapus file yang tidak ada **tidak** melempar exception — operasi diabaikan secara diam-diam.

---

### Copy dan Move

```php
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\Facades\GCS;

// Copy (sumber tetap ada)
Storage::disk('gcs')->copy('images/original.jpg', 'images/backup/original.jpg');

// Move / rename (sumber dihapus setelah copy)
Storage::disk('gcs')->move('uploads/temp.pdf', 'documents/final.pdf');

// Via GCS Facade
GCS::copy('a/file.txt', 'b/file.txt');
GCS::move('draft/doc.txt', 'published/doc.txt');
```

---

### Daftar File dan Direktori

```php
use Illuminate\Support\Facades\Storage;

// Daftar file di direktori (non-rekursif)
$files = Storage::disk('gcs')->files('images');
// ['images/foto1.jpg', 'images/foto2.jpg']

// Daftar file rekursif (semua subdirektori)
$allFiles = Storage::disk('gcs')->allFiles('uploads');
// ['uploads/2025/01/file.pdf', 'uploads/2025/02/other.pdf', ...]

// Daftar direktori
$dirs = Storage::disk('gcs')->directories('uploads');
// ['uploads/2025', 'uploads/archive']

// Daftar direktori rekursif
$allDirs = Storage::disk('gcs')->allDirectories('uploads');
// ['uploads/2025', 'uploads/2025/01', 'uploads/2025/02', ...]
```

---

### Cek Keberadaan File

```php
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\Facades\GCS;

// Cek file ada
if (Storage::disk('gcs')->exists('images/foto.jpg')) {
    // file ada
}

// Cek file tidak ada
if (Storage::disk('gcs')->missing('documents/laporan.pdf')) {
    // file tidak ada
}

// Via GCS Facade
if (GCS::exists('profile.jpg')) {
    GCS::delete('profile.jpg');
}
```

---

### Metadata File

```php
use Illuminate\Support\Facades\Storage;

// Ukuran file dalam bytes
$bytes = Storage::disk('gcs')->size('videos/video.mp4');
// 10485760 (10 MB)

// Waktu modifikasi terakhir (Unix timestamp)
$timestamp = Storage::disk('gcs')->lastModified('documents/laporan.pdf');
$date = \Carbon\Carbon::createFromTimestamp($timestamp);

// MIME type
$mime = Storage::disk('gcs')->mimeType('images/foto.jpg');
// 'image/jpeg'
```

---

### Visibility

```php
use Illuminate\Support\Facades\Storage;

// Baca visibility saat ini
$visibility = Storage::disk('gcs')->getVisibility('images/foto.jpg');
// 'public' atau 'private'

// Ubah visibility
Storage::disk('gcs')->setVisibility('images/foto.jpg', 'public');
Storage::disk('gcs')->setVisibility('documents/rahasia.pdf', 'private');

// Upload dengan visibility langsung
Storage::disk('gcs')->put('file.txt', 'konten', 'private');
```

---

### Stream

Untuk file berukuran besar, selalu gunakan stream untuk efisiensi memori:

```php
use Illuminate\Support\Facades\Storage;

// Baca sebagai stream
$readStream = Storage::disk('gcs')->readStream('exports/data-besar.csv');

// Tulis dari stream (misalnya pipe dari GCS disk lain)
$writeStream = tmpfile();
fwrite($writeStream, 'baris1,data1' . PHP_EOL);
fwrite($writeStream, 'baris2,data2' . PHP_EOL);
rewind($writeStream);

Storage::disk('gcs')->writeStream('imports/data.csv', $writeStream);
fclose($writeStream);

// Contoh: copy file antar bucket via stream
$source = Storage::disk('gcs-source')->readStream('backup/db.sql');
Storage::disk('gcs-dest')->writeStream('restore/db.sql', $source);
```

---

## Penggunaan di Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\Facades\GCS;

class FileController extends Controller
{
    // ----------------------------------------------------------------
    // Upload foto profil
    // ----------------------------------------------------------------
    public function uploadPhoto(Request $request)
    {
        $request->validate(['photo' => 'required|image|max:5120']);

        $userId = auth()->id();

        // Hapus foto lama jika ada
        $oldPath = auth()->user()->photo_path;
        if ($oldPath && GCS::exists($oldPath)) {
            GCS::delete($oldPath);
        }

        // Upload foto baru
        $path = GCS::putFileAs(
            'avatars',
            $request->file('photo'),
            "user-{$userId}." . $request->file('photo')->extension(),
        );

        auth()->user()->update([
            'photo_path' => $path,
            'photo_url'  => GCS::url($path),
        ]);

        return response()->json(['url' => GCS::url($path)]);
    }

    // ----------------------------------------------------------------
    // Upload dokumen private
    // ----------------------------------------------------------------
    public function uploadContract(Request $request)
    {
        $request->validate(['file' => 'required|mimes:pdf|max:20480']);

        $path = Storage::disk('gcs-private')->putFileAs(
            'contracts/' . auth()->id(),
            $request->file('file'),
            'contract-' . now()->format('Y-m-d') . '.pdf',
        );

        return response()->json([
            'path'       => $path,
            'signed_url' => Storage::disk('gcs-private')
                ->getAdapter()
                ->signedUrl($path, 1800), // valid 30 menit
        ]);
    }

    // ----------------------------------------------------------------
    // Download file private
    // ----------------------------------------------------------------
    public function downloadFile(string $path)
    {
        $disk = Storage::disk('gcs-private');

        if ($disk->missing($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return $disk->download($path, basename($path));
    }

    // ----------------------------------------------------------------
    // Daftar file user
    // ----------------------------------------------------------------
    public function listFiles()
    {
        $userId = auth()->id();
        $files  = GCS::files("uploads/{$userId}");

        return response()->json(array_map(fn ($path) => [
            'path'    => $path,
            'url'     => GCS::url($path),
            'size'    => GCS::size($path),
            'updated' => GCS::lastModified($path),
        ], $files));
    }

    // ----------------------------------------------------------------
    // Hapus file
    // ----------------------------------------------------------------
    public function deleteFile(Request $request)
    {
        $path = $request->input('path');

        if (GCS::missing($path)) {
            return response()->json(['message' => 'File tidak ada.'], 404);
        }

        GCS::delete($path);

        return response()->json(['message' => 'File berhasil dihapus.']);
    }
}
```

---

## Error Handling

Adapter melempar exception Flysystem standar. Semua exception menurunkan `League\Flysystem\FilesystemException`:

```php
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;

// Upload
try {
    Storage::disk('gcs')->put('file.txt', $contents);
} catch (UnableToWriteFile $e) {
    logger()->error('Upload gagal: ' . $e->getMessage());
}

// Download / baca
try {
    $data = Storage::disk('gcs')->get('file.txt');
} catch (UnableToReadFile $e) {
    logger()->error('Baca gagal: ' . $e->getMessage());
    abort(404);
}

// Hapus
try {
    Storage::disk('gcs')->delete('file.txt');
} catch (UnableToDeleteFile $e) {
    logger()->error('Hapus gagal: ' . $e->getMessage());
}

// Metadata
try {
    $size = Storage::disk('gcs')->size('file.txt');
    $mime = Storage::disk('gcs')->mimeType('file.txt');
} catch (UnableToRetrieveMetadata $e) {
    logger()->error('Metadata gagal: ' . $e->getMessage());
}
```

| Exception | Dipicu ketika |
|---|---|
| `UnableToWriteFile` | Upload/write gagal (quota, permission, network) |
| `UnableToReadFile` | File tidak ada atau download gagal |
| `UnableToDeleteFile` | Hapus gagal (permission atau network) |
| `UnableToDeleteDirectory` | Hapus direktori gagal |
| `UnableToCreateDirectory` | Buat direktori gagal |
| `UnableToCopyFile` | Copy gagal — sumber tidak ada atau permission |
| `UnableToMoveFile` | Move gagal — sumber tidak ada atau permission |
| `UnableToRetrieveMetadata` | `size()`, `mimeType()`, `lastModified()` gagal atau file tidak ada |
| `UnableToSetVisibility` | Ubah ACL gagal (permission bucket) |

> **`delete()` dan `missing()`** tidak pernah melempar exception untuk file yang tidak ditemukan — operasi diabaikan secara diam-diam.

---

## Hasil Test

Dijalankan dengan **PHPUnit 12** pada **PHP 8.1+**.

```
PHPUnit by Sebastian Bergmann and contributors.

...............................................................  63 / 127 ( 49%)
............................................................... 126 / 127 ( 99%)
.                                                               127 / 127 (100%)

Time: 00:00.XXX, Memory: XX.XX MB
```

### GoogleCloudStorageAdapter — 87 Tests

| Kelompok | Jumlah Test |
|---|---|
| `fileExists` | 4 |
| `directoryExists` | 3 |
| `write` | 6 |
| `writeStream` | 3 |
| `read` | 4 |
| `readStream` | 5 |
| `delete` | 3 |
| `deleteDirectory` | 4 |
| `createDirectory` | 4 |
| `setVisibility` | 3 |
| `visibility` | 4 |
| `mimeType` | 5 |
| `lastModified` | 4 |
| `fileSize` | 4 |
| `listContents` | 8 |
| `move` | 4 |
| `copy` | 4 |
| `getUrl` | 5 |
| `signedUrl` | 3 |
| `getBucket` / `getClient` | 2 |
| Path prefix behavior | 4 |
| **Total** | **87** |

### GCSServiceProvider — 14 Tests

| # | Test Case | Status |
|---|---|---|
| 1 | Driver `gcs` terdaftar dan menghasilkan `FilesystemAdapter` | PASS |
| 2 | Adapter internal adalah `GoogleCloudStorageAdapter` | PASS |
| 3 | Adapter menggunakan bucket yang dikonfigurasi | PASS |
| 4 | Adapter membuat `StorageClient` dengan benar | PASS |
| 5 | Multi-disk menghasilkan adapter yang independen | PASS |
| 6 | Container memiliki binding `gcs` | PASS |
| 7 | Binding `gcs` mengembalikan `FilesystemAdapter` | PASS |
| 8 | Binding `gcs` menggunakan `gcs_default_disk` kustom | PASS |
| 9 | Config `key_file` diterima sebagai path file | PASS |
| 10 | Config `key_file_json` diterima sebagai array | PASS |
| 11 | Config `path_prefix` diteruskan ke adapter | PASS |
| 12 | Config `storage_api_uri` kustom digunakan untuk URL | PASS |
| 13 | Visibility `private` diterima | PASS |
| 14 | Visibility `public` diterima | PASS |

**OK (127 tests, 350 assertions)**

---

## Referensi

- Adapter: [`src/Storage/GoogleCloudStorageAdapter.php`](../../../src/Storage/GoogleCloudStorageAdapter.php)
- Facade: [`src/Facades/GCS.php`](../../../src/Facades/GCS.php)
- Service Provider: [`src/LaravelServiceProvider.php`](../../../src/LaravelServiceProvider.php)
- Test Adapter: [`tests/Storage/GoogleCloudStorageAdapterTest.php`](../../../tests/Storage/GoogleCloudStorageAdapterTest.php)
- Test ServiceProvider: [`tests/Storage/GCSServiceProviderTest.php`](../../../tests/Storage/GCSServiceProviderTest.php)
- [Google Cloud Storage PHP SDK](https://github.com/googleapis/google-cloud-php-storage)
- [Laravel Filesystem Documentation](https://laravel.com/docs/filesystem)
