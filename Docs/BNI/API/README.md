# BNIAPIServices

Service untuk integrasi BNI e-Collection API — membuat, mengupdate, dan mengecek status billing/invoice Virtual Account BNI.

---

## Daftar Isi

- [Konfigurasi](#konfigurasi)
- [Inisialisasi](#inisialisasi)
- [API Reference](#api-reference)
  - [createBilling](#createbilling)
  - [updateBilling](#updatebilling)
  - [inquiryBilling](#inquirybilling)
- [Response Format](#response-format)
- [Jenis Billing](#jenis-billing)
- [Status Code](#status-code)
- [Hasil Test](#hasil-test)

---

## Konfigurasi

Tambahkan variabel berikut ke file `.env`:

```env
BNI_CLIENT_ID=001
BNI_SECRET_KEY=ea0c88921fb033387e66ef7d1e82ab83
BNI_PREFIX=8
BNI_ECOLLECTION_URL=https://apibeta.bni-ecollection.com/
```

> Untuk production, ganti URL menjadi `https://api.bni-ecollection.com/`

| Variabel | Keterangan |
|---|---|
| `BNI_CLIENT_ID` | Client ID diberikan oleh BNI (2, 3, atau 5 digit) |
| `BNI_SECRET_KEY` | Secret key 32 karakter hexadecimal dari BNI |
| `BNI_PREFIX` | Prefix nomor Virtual Account dari BNI |
| `BNI_ECOLLECTION_URL` | URL API BNI e-Collection (dev/production) |

---

## Inisialisasi

### Manual

```php
use OjiePermana\Laravel\Services\BNIAPIServices;

$bni = new BNIAPIServices(
    clientId:  env('BNI_CLIENT_ID'),
    secretKey: env('BNI_SECRET_KEY'),
    prefix:    env('BNI_PREFIX'),
    url:       env('BNI_ECOLLECTION_URL'),
);
```

### Via AppServiceProvider (Rekomendasi)

Daftarkan sebagai singleton agar tidak perlu repeat konfigurasi di setiap controller:

```php
// app/Providers/AppServiceProvider.php

use OjiePermana\Laravel\Services\BNIAPIServices;

public function register(): void
{
    $this->app->singleton(BNIAPIServices::class, fn () => new BNIAPIServices(
        clientId:  env('BNI_CLIENT_ID'),
        secretKey: env('BNI_SECRET_KEY'),
        prefix:    env('BNI_PREFIX'),
        url:       env('BNI_ECOLLECTION_URL'),
    ));
}
```

Kemudian inject via constructor di controller:

```php
use OjiePermana\Laravel\Services\BNIAPIServices;

class PaymentController extends Controller
{
    public function __construct(private BNIAPIServices $bni) {}
}
```

---

## API Reference

### createBilling

Membuat invoice/billing baru.

```php
public function createBilling(
    string  $trxId,
    string  $trxAmount,
    string  $billingType,
    string  $customerName,
    ?string $customerEmail   = null,
    ?string $customerPhone   = null,
    ?string $virtualAccount  = null,
    ?string $datetimeExpired = null,
    ?string $description     = null,
    bool    $sendSms         = false,
): array
```

#### Parameter

| Parameter | Wajib | Tipe | Keterangan |
|---|---|---|---|
| `trxId` | Ya | string (max 30) | ID invoice/billing unik. Tidak bisa dibuat ulang meski sudah expired |
| `trxAmount` | Ya | string (integer) | Nominal tagihan. Set ke `"0"` untuk billing tipe open (`o`) |
| `billingType` | Ya | string | Kode jenis billing. Lihat [Jenis Billing](#jenis-billing) |
| `customerName` | Ya | string (max 255) | Nama customer, ditampilkan saat bayar VA. Maks 14 karakter jika `sendSms = true` |
| `customerEmail` | Tidak | string\|null | Email customer untuk notifikasi |
| `customerPhone` | Tidak | string\|null | Nomor telepon customer. Harus diawali `628` jika `sendSms = true` |
| `virtualAccount` | Tidak | string\|null | Nomor VA 16 digit. Jika tidak diisi, BNI akan generate otomatis |
| `datetimeExpired` | Tidak | string\|null | Tanggal kedaluwarsa format ISO 8601, contoh: `2025-12-31T23:59:00+07:00` |
| `description` | Tidak | string\|null | Keterangan tambahan (max 100 karakter) |
| `sendSms` | Tidak | bool | Kirim SMS notifikasi ke customer via BNI SMS Banking (default: `false`) |

#### Contoh

```php
// Billing tetap (fixed payment)
$result = $bni->createBilling(
    trxId:           'INV-2025-001',
    trxAmount:       '150000',
    billingType:     'c',
    customerName:    'Budi Santoso',
    customerEmail:   'budi@email.com',
    customerPhone:   '08123123123',
    virtualAccount:  '8001000000000001',
    datetimeExpired: '2025-12-31T23:59:00+07:00',
    description:     'Pembayaran tagihan bulan Desember',
);

// Billing open payment (amount harus "0")
$result = $bni->createBilling(
    trxId:        'INV-OPEN-001',
    trxAmount:    '0',
    billingType:  'o',
    customerName: 'Siti Aminah',
);

// Dengan SMS notifikasi
$result = $bni->createBilling(
    trxId:        'INV-SMS-001',
    trxAmount:    '75000',
    billingType:  'c',
    customerName: 'Rini Wulandari',
    customerPhone:'6281234567890',
    sendSms:      true,
);
```

#### Response Sukses

```php
[
    'status' => '000',
    'data'   => [
        'virtual_account' => '8001000000000001',
        'trx_id'          => 'INV-2025-001',
    ],
]
```

---

### updateBilling

Mengupdate detail invoice/billing yang sudah ada.

> **Catatan:**
> - `billing_type` dan `virtual_account` tidak bisa diubah
> - Update hanya bisa dilakukan jika billing belum pernah dibayar, atau nominal baru >= nominal yang sudah dibayar
> - Field optional yang tidak dikirim akan diganti dengan string kosong di sisi BNI — selalu kirim ulang semua field yang ingin dipertahankan

```php
public function updateBilling(
    string  $trxId,
    string  $trxAmount,
    string  $customerName,
    ?string $customerEmail   = null,
    ?string $customerPhone   = null,
    ?string $datetimeExpired = null,
    ?string $description     = null,
): array
```

#### Parameter

| Parameter | Wajib | Tipe | Keterangan |
|---|---|---|---|
| `trxId` | Ya | string | ID invoice/billing yang akan diupdate |
| `trxAmount` | Ya | string (integer) | Nominal baru. Harus >= nominal yang sudah dibayar |
| `customerName` | Ya | string | Nama customer baru |
| `customerEmail` | Tidak | string\|null | Email baru (kosong = hapus nilai lama) |
| `customerPhone` | Tidak | string\|null | Telepon baru (kosong = hapus nilai lama) |
| `datetimeExpired` | Tidak | string\|null | Tanggal kedaluwarsa baru format ISO 8601 |
| `description` | Tidak | string\|null | Keterangan baru (max 100 karakter) |

#### Contoh

```php
$result = $bni->updateBilling(
    trxId:           'INV-2025-001',
    trxAmount:       '200000',
    customerName:    'Budi Santoso',
    customerEmail:   'budi.baru@email.com',
    customerPhone:   '08199999999',
    datetimeExpired: '2026-03-31T23:59:00+07:00',
    description:     'Tagihan diperbarui',
);
```

#### Response Sukses

```php
[
    'status' => '000',
    'data'   => [
        'virtual_account' => '8001000000000001',
        'trx_id'          => 'INV-2025-001',
    ],
]
```

---

### inquiryBilling

Mengambil detail status invoice/billing yang sudah ada.

```php
public function inquiryBilling(string $trxId): array
```

#### Parameter

| Parameter | Wajib | Tipe | Keterangan |
|---|---|---|---|
| `trxId` | Ya | string | ID invoice/billing yang akan dicek |

#### Contoh

```php
$result = $bni->inquiryBilling('INV-2025-001');
```

#### Response Sukses

```php
[
    'status' => '000',
    'data'   => [
        'client_id'                     => '001',
        'trx_id'                        => 'INV-2025-001',
        'trx_amount'                    => '150000',
        'virtual_account'               => '8001000000000001',
        'customer_name'                 => 'Budi Santoso',
        'customer_email'                => 'budi@email.com',
        'customer_phone'                => '08123123123',
        'va_status'                     => '1',       // 1=aktif, 2=nonaktif
        'billing_type'                  => 'c',
        'payment_amount'                => '150000',  // total yang sudah dibayar
        'payment_ntb'                   => '023589',  // nomor referensi (null jika belum bayar)
        'description'                   => 'Pembayaran tagihan bulan Desember',
        'datetime_created_iso8601'      => '2025-01-01T08:00:00+07:00',
        'datetime_expired_iso8601'      => '2025-12-31T23:59:00+07:00',
        'datetime_last_updated_iso8601' => '2025-06-01T10:00:00+07:00',
        'datetime_payment_iso8601'      => '2025-06-15T14:30:00+07:00', // null jika belum bayar
    ],
]
```

---

## Response Format

Setiap method mengembalikan array dengan struktur berikut:

### Sukses (`status = "000"`)

Data response dari BNI sudah didekripsi secara otomatis:

```php
[
    'status' => '000',
    'data'   => [...], // array terdekripsi
]
```

### Gagal (`status != "000"`)

Response error dari BNI dikirim tanpa enkripsi dan dikembalikan apa adanya:

```php
[
    'status'  => '101',
    'message' => 'Billing not found.',
]
```

---

## Jenis Billing

| Kode | Nama | Keterangan |
|---|---|---|
| `o` | Open payment | Bisa dibayar berkali-kali selama masih aktif. `trx_amount` harus `"0"` |
| `c` | Fixed payment | Harus dibayar dengan nominal **tepat sama** dengan yang diminta |
| `i` | Installment / Partial | Bisa dibayar berkali-kali selama jumlah < nominal dan masih aktif |
| `m` | Minimum payment | Bisa dibayar dengan nominal >= yang diminta |
| `n` | Open minimum payment | Bisa dibayar berkali-kali dengan nominal >= yang diminta selama masih aktif |
| `x` | Open maximum payment | Bisa dibayar berkali-kali dengan nominal <= yang diminta selama masih aktif |

---

## Status Code

| Status | Pesan |
|---|---|
| `000` | Success |
| `001` | Incomplete/invalid Parameter(s) |
| `002` | IP address not allowed or wrong Client ID |
| `004` | Service not found |
| `005` | Service not defined |
| `006` | Invalid VA Number |
| `007` | Invalid Billing Number |
| `008` | Technical Failure |
| `009` | Unexpected Error |
| `010` | Request Timeout |
| `011` | Billing type does not match billing amount |
| `012` | Invalid expiry date/time |
| `013` | IDR currency cannot have billing amount with decimal fraction |
| `014` | VA Number should not be defined when Billing Number is set |
| `015` | Invalid Permission(s) |
| `016` | Invalid Billing Type |
| `017` | Customer Name cannot be used |
| `100` | Billing has been paid |
| `101` | Billing not found |
| `102` | VA Number is in use |
| `103` | Billing has been expired |
| `104` | Billing Number is in use |
| `105` | Duplicate Billing ID |
| `107` | Amount cannot be changed |
| `108` | Data not found |
| `200` | Failed to send SMS Payment |
| `201` | SMS Payment can only be used with Fixed Payment |
| `801` | Billing type not supported for this Client ID |
| `996` | Too many inquiry request per hour |
| `997` | System is temporarily offline |
| `998` | Content-Type header not defined as it should be |
| `999` | Internal Error |

---

## Hasil Test

Dijalankan dengan **PHPUnit 12.5.14** pada **PHP 8.4.17**.

```
PHPUnit 12.5.14 by Sebastian Bergmann and contributors.

Runtime: PHP 8.4.17
Configuration: phpunit.xml

............                                      12 / 12 (100%)

Time: 00:00.116, Memory: 34.50 MB
```

| # | Test Case | Status |
|---|---|---|
| 1 | `createBilling` sukses mengembalikan data terdekripsi | PASS |
| 2 | `createBilling` mengirim payload terenkripsi yang benar | PASS |
| 3 | `createBilling` dengan `sendSms = true` menggunakan type `createbillingsms` | PASS |
| 4 | `createBilling` mengabaikan field optional yang `null` | PASS |
| 5 | `updateBilling` sukses mengembalikan data terdekripsi | PASS |
| 6 | `updateBilling` mengirim type dan field mandatory yang benar | PASS |
| 7 | `inquiryBilling` sukses mengembalikan data billing lengkap | PASS |
| 8 | `inquiryBilling` mengirim type dan `trx_id` yang benar | PASS |
| 9 | Response error API dikembalikan apa adanya tanpa decrypt | PASS |
| 10 | Response error tidak memiliki field `data` terdekripsi | PASS |
| 11 | Request dikirim ke URL yang benar dengan `Content-Type: application/json` | PASS |
| 12 | Payload selalu mengandung `client_id`, `prefix`, dan `data` | PASS |

**OK (12 tests, 54 assertions)**

---

## Referensi

- File utama: [`src/Services/BNIAPIServices.php`](../../../src/Services/BNIAPIServices.php)
- File test: [`tests/Services/BNIAPIServicesTest.php`](../../../tests/Services/BNIAPIServicesTest.php)
- Dokumentasi enkripsi: [`Docs/BNI/Encrypt/README.md`](../Encrypt/README.md)
- Spesifikasi resmi: BNI eCollection Technical Specification v3.0.6
