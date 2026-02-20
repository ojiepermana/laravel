# BNI e-Collection

Integrasi BNI e-Collection API — membuat, mengupdate, dan mengecek status billing/invoice Virtual Account BNI, lengkap dengan enkripsi data transaksi.

---

## Daftar Isi

- [Konfigurasi](#konfigurasi)
- [Inisialisasi](#inisialisasi)
  - [Facade (Rekomendasi)](#facade-rekomendasi)
  - [Manual / Injection](#manual--injection)
- [API Reference](#api-reference)
  - [create](#create)
  - [update](#update)
  - [show](#show)
- [Enkripsi](#enkripsi)
    - [encryptPayload](#encryptpayload)
    - [decryptPayload](#decryptpayload)
  - [Contoh Alur Lengkap](#contoh-alur-lengkap)
- [Response Format](#response-format)
- [Jenis Billing](#jenis-billing)
- [Status Code](#status-code)
- [Hasil Test](#hasil-test)

---

## Konfigurasi

Tambahkan variabel berikut ke file `.env`:

```env
BNI_BILLING_CLIENT_ID=001
BNI_BILLING_SECRET_KEY=ea0c88921fb033387e66ef7d1e82ab83
BNI_BILLING_PREFIX=8
BNI_BILLING_URL=https://apibeta.bni-ecollection.com/
```

> Untuk production, ganti URL menjadi `https://api.bni-ecollection.com/`

| Variabel | Keterangan |
|---|---|
| `BNI_BILLING_CLIENT_ID` | Client ID diberikan oleh BNI (2, 3, atau 5 digit) |
| `BNI_BILLING_SECRET_KEY` | Secret key 32 karakter hexadecimal dari BNI |
| `BNI_BILLING_PREFIX` | Prefix nomor Virtual Account dari BNI |
| `BNI_BILLING_URL` | URL API BNI e-Collection (dev/production) |

> Catatan: Billing dan Payment sekarang berbagi satu file konfigurasi `config/bni.php`,
> dengan namespace `bni.billing.*` untuk e-Collection dan `bni.payment.*` untuk payment H2H.

Publish config (opsional):

```bash
php artisan vendor:publish --tag=bni-config
```

### Konfigurasi Terpadu BNI (Billing + Payment)

Semua konfigurasi BNI sekarang berada di satu file `config/bni.php` dengan dua namespace:

- `bni.billing.*` untuk e-Collection (VA billing)
- `bni.payment.*` untuk OGP H2H v2 (payment gateway)

Struktur singkat:

```php
return [
    'billing' => [
        'client_id' => env('BNI_BILLING_CLIENT_ID'),
        'secret_key' => env('BNI_BILLING_SECRET_KEY'),
        'prefix' => env('BNI_BILLING_PREFIX'),
        'url' => env('BNI_BILLING_URL', ''),
    ],
    'payment' => [
        'base_url' => env('BNI_PAYMENT_BASE_URL'),
        'oauth_url' => env('BNI_PAYMENT_OAUTH_URL', env('BNI_PAYMENT_BASE_URL') . '/api/oauth/token'),
        'client_id' => env('BNI_PAYMENT_CLIENT_ID'),
        'client_secret' => env('BNI_PAYMENT_CLIENT_SECRET'),
        'api_key' => env('BNI_PAYMENT_API_KEY'),
        'api_secret' => env('BNI_PAYMENT_API_SECRET'),
        'client_name' => env('BNI_PAYMENT_CLIENT_NAME'),
        'client_id_prefix' => env('BNI_PAYMENT_CLIENT_ID_PREFIX', 'IDBNI'),
    ],
];
```

Dengan struktur ini, konfigurasi billing dan payment tidak saling menimpa saat dipakai bersamaan.

---

## Inisialisasi

### Facade (Rekomendasi)

Konfigurasi otomatis terbaca dari `config/bni.php` — cukup import dan langsung pakai:

```php
use OjiePermana\Laravel\Facades\BNI;

BNI::create(...);
BNI::update(...);
BNI::show(...);
```

### Manual / Injection

```php
use OjiePermana\Laravel\Bank\BNI\Billing\BniBillingClient;

$bni = new BniBillingClient(
    clientId:  env('BNI_BILLING_CLIENT_ID'),
    secretKey: env('BNI_BILLING_SECRET_KEY'),
    prefix:    env('BNI_BILLING_PREFIX'),
    url:       env('BNI_BILLING_URL'),
);
```

Atau daftarkan sebagai singleton di `AppServiceProvider` lalu inject via constructor:

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(BniBillingClient::class, fn () => new BniBillingClient(
        clientId:  env('BNI_BILLING_CLIENT_ID'),
        secretKey: env('BNI_BILLING_SECRET_KEY'),
        prefix:    env('BNI_BILLING_PREFIX'),
        url:       env('BNI_BILLING_URL'),
    ));
}
```

```php
class PaymentController extends Controller
{
    public function __construct(private BniBillingClient $bni) {}
}
```

---

## API Reference

### create

Membuat invoice/billing baru.

```php
public function create(
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
| `customerName` | Ya | string (max 255) | Nama customer. Maks 14 karakter jika `sendSms = true` |
| `customerEmail` | Tidak | string\|null | Email customer |
| `customerPhone` | Tidak | string\|null | Nomor telepon. Harus diawali `628` jika `sendSms = true` |
| `virtualAccount` | Tidak | string\|null | Nomor VA 16 digit. Jika tidak diisi, BNI generate otomatis |
| `datetimeExpired` | Tidak | string\|null | Tanggal kedaluwarsa ISO 8601, contoh: `2025-12-31T23:59:00+07:00` |
| `description` | Tidak | string\|null | Keterangan tambahan (max 100 karakter) |
| `sendSms` | Tidak | bool | Kirim SMS notifikasi via BNI SMS Banking (default: `false`) |

#### Contoh

```php
// Billing tetap (fixed payment)
$result = BNI::create(
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
$result = BNI::create(
    trxId:        'INV-OPEN-001',
    trxAmount:    '0',
    billingType:  'o',
    customerName: 'Siti Aminah',
);

// Dengan SMS notifikasi
$result = BNI::create(
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

### update

Mengupdate detail invoice/billing yang sudah ada.

> **Catatan:**
> - `billing_type` dan `virtual_account` tidak bisa diubah
> - Update hanya bisa dilakukan jika billing belum pernah dibayar, atau nominal baru >= nominal yang sudah dibayar
> - Field optional yang tidak dikirim akan diganti string kosong di sisi BNI — selalu kirim ulang semua field yang ingin dipertahankan

```php
public function update(
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
| `datetimeExpired` | Tidak | string\|null | Tanggal kedaluwarsa baru ISO 8601 |
| `description` | Tidak | string\|null | Keterangan baru (max 100 karakter) |

#### Contoh

```php
$result = BNI::update(
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

### show

Mengambil detail status invoice/billing yang sudah ada.

```php
public function show(string $trxId): array
```

#### Parameter

| Parameter | Wajib | Tipe | Keterangan |
|---|---|---|---|
| `trxId` | Ya | string | ID invoice/billing yang akan dicek |

#### Contoh

```php
$result = BNI::show('INV-2025-001');
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

## Enkripsi

`BniBillingEncryptor` menangani enkripsi dan dekripsi data transaksi secara otomatis di balik layar. Bisa juga digunakan langsung, misalnya untuk handle callback dari BNI.

```php
use OjiePermana\Laravel\Bank\BNI\Billing\BniBillingEncryptor;
```

### encryptPayload

Mengenkripsi array data menggunakan `client_id` dan `secret_key`.

```php
$hashed = BniBillingEncryptor::encryptPayload($data, $client_id, $secret_key);
// Mengembalikan string terenkripsi
```

> Setiap pemanggilan menghasilkan string berbeda karena menyertakan timestamp saat itu.

### decryptPayload

Mendekripsi string hasil enkripsi BNI menjadi array data asli.

```php
$result = BniBillingEncryptor::decryptPayload($hashed_string, $client_id, $secret_key);
// Mengembalikan array | null
```

**Mengembalikan `null` jika:**
- `client_id` atau `secret_key` salah
- String bukan hasil enkripsi yang valid
- Waktu server berbeda lebih dari 480 detik (8 menit) dari waktu enkripsi

### Contoh Alur Lengkap

#### Handle Callback dari BNI

```php
use OjiePermana\Laravel\Bank\BNI\Billing\BniBillingEncryptor;
use Illuminate\Http\Request;

public function callback(Request $request)
{
    $client_id  = env('BNI_BILLING_CLIENT_ID');
    $secret_key = env('BNI_BILLING_SECRET_KEY');

    $payload = $request->json()->all();

    if (!$payload || $payload['client_id'] !== $client_id) {
        return response()->json(['status' => '999', 'message' => 'Unauthorized']);
    }

    $data = BniBillingEncryptor::decryptPayload($payload['data'], $client_id, $secret_key);

    if (!$data) {
        return response()->json(['status' => '999', 'message' => 'Dekripsi gagal']);
    }

    // $data['trx_id']
    // $data['virtual_account']
    // $data['payment_amount']
    // $data['payment_ntb']
    // $data['datetime_payment']

    return response()->json(['status' => '000']);
}
```

---

## Response Format

Setiap method mengembalikan array dengan struktur berikut:

### Sukses (`status = "000"`)

```php
[
    'status' => '000',
    'data'   => [...], // array terdekripsi otomatis
]
```

### Gagal (`status != "000"`)

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
| `o` | Open payment | Bisa dibayar berkali-kali selama aktif. `trx_amount` harus `"0"` |
| `c` | Fixed payment | Harus dibayar dengan nominal **tepat sama** |
| `i` | Installment / Partial | Bisa dibayar berkali-kali selama jumlah < nominal dan masih aktif |
| `m` | Minimum payment | Bisa dibayar dengan nominal >= yang diminta |
| `n` | Open minimum payment | Bisa dibayar berkali-kali dengan nominal >= yang diminta selama aktif |
| `x` | Open maximum payment | Bisa dibayar berkali-kali dengan nominal <= yang diminta selama aktif |

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

OK (127 tests, 350 assertions)
```

### BniBillingClient

| # | Test Case | Status |
|---|---|---|
| 1 | `create` sukses mengembalikan data terdekripsi | PASS |
| 2 | `create` mengirim payload terenkripsi yang benar | PASS |
| 3 | `create` dengan `sendSms = true` menggunakan type `createbillingsms` | PASS |
| 4 | `create` mengabaikan field optional yang `null` | PASS |
| 5 | `update` sukses mengembalikan data terdekripsi | PASS |
| 6 | `update` mengirim type dan field mandatory yang benar | PASS |
| 7 | `show` sukses mengembalikan data billing lengkap | PASS |
| 8 | `show` mengirim type dan `trx_id` yang benar | PASS |
| 9 | Response error API dikembalikan apa adanya tanpa decrypt | PASS |
| 10 | Response error tidak memiliki field `data` terdekripsi | PASS |
| 11 | Request dikirim ke URL yang benar dengan `Content-Type: application/json` | PASS |
| 12 | Payload selalu mengandung `client_id`, `prefix`, dan `data` | PASS |

### BniBillingEncryptor

| # | Test Case | Status |
|---|---|---|
| 1 | `encryptPayload` mengembalikan string non-empty | PASS |
| 2 | `encryptPayload` menghasilkan output berbeda setiap pemanggilan | PASS |
| 3 | `encryptPayload` dengan array kosong tetap mengembalikan string | PASS |
| 4 | `encryptPayload` dengan `client_id` berbeda menghasilkan output berbeda | PASS |
| 5 | `encryptPayload` dengan `secret_key` berbeda menghasilkan output berbeda | PASS |
| 6 | `encryptPayload` → `decryptPayload` mengembalikan data asli (roundtrip lengkap) | PASS |
| 7 | Roundtrip dengan data minimal (1 key) | PASS |
| 8 | Roundtrip dengan array kosong | PASS |
| 9 | Roundtrip dengan karakter spesial (`&`, `<`, `"`, unicode) | PASS |
| 10 | Roundtrip dengan nilai numerik (int, float) | PASS |
| 11 | `decryptPayload` dengan `client_id` salah mengembalikan `null` | PASS |
| 12 | `decryptPayload` dengan `secret_key` salah mengembalikan `null` | PASS |
| 13 | `decryptPayload` dengan string acak mengembalikan `null` | PASS |
| 14 | `decryptPayload` dengan string kosong mengembalikan `null` | PASS |

---

## Referensi

- [`src/Bank/BNI/Billing/BniBillingClient.php`](../../../src/Bank/BNI/Billing/BniBillingClient.php)
- [`src/Bank/BNI/Billing/BniBillingEncryptor.php`](../../../src/Bank/BNI/Billing/BniBillingEncryptor.php)
- [`src/Facades/BNI.php`](../../../src/Facades/BNI.php)
- [`tests/BNI/BniBillingClientTest.php`](../../../tests/BNI/BniBillingClientTest.php)
- [`tests/BNI/BniBillingEncryptorTest.php`](../../../tests/BNI/BniBillingEncryptorTest.php)
- Spesifikasi resmi: BNI eCollection Technical Specification v3.0.6
