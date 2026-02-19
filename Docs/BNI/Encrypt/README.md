# BNIEncryptServices

Service untuk enkripsi dan dekripsi data transaksi BNI e-Collection (Virtual Account).

---

## Daftar Isi

- [Instalasi](#instalasi)
- [Cara Pakai](#cara-pakai)
  - [Encrypt](#encrypt)
  - [Decrypt](#decrypt)
  - [Contoh Alur Lengkap](#contoh-alur-lengkap)
- [Hasil Test](#hasil-test)

---

## Instalasi

Install package melalui Composer:

```bash
composer require ojiepermana/laravel
```

Tidak perlu konfigurasi tambahan. Service provider akan otomatis terdaftar melalui Laravel Package Auto-Discovery.

---

## Cara Pakai

### Import

```php
use OjiePermana\Laravel\Services\BNIEncryptServices;
```

---

### Encrypt

Mengenkripsi array data menggunakan `client_id` dan `secret_key` dari BNI.

```php
$data = [
    'client_id'        => '001',
    'trx_id'           => '1230000001',
    'trx_amount'       => '100000',
    'billing_type'     => 'c',
    'datetime_expired' => '2025-07-01 16:00:00',
    'virtual_account'  => '',
    'customer_name'    => 'Mr. X',
    'customer_email'   => 'xxx@email.com',
    'customer_phone'   => '08123123123',
];

$client_id  = '001';
$secret_key = 'ea0c88921fb033387e66ef7d1e82ab83';

$hashed = BNIEncryptServices::Enc($data, $client_id, $secret_key);

// Hasil: string terenkripsi, contoh:
// "GEhHGEwbHh0WE0QNA0ZPTU1VCkVPejghNx..."
```

**Catatan:** Setiap pemanggilan `Enc()` menghasilkan string berbeda karena menyertakan timestamp saat itu.

---

### Decrypt

Mendekripsi string hasil enkripsi BNI menjadi array data asli.

```php
$hashed_string = 'GkdDFUMcHh0WE0QNA1ZXRVxcCQggOEYXRQ...';

$client_id  = '001';
$secret_key = 'ea0c88921fb033387e66ef7d1e82ab83';

$result = BNIEncryptServices::Dec($hashed_string, $client_id, $secret_key);

// Hasil:
// [
//     'virtual_account'  => '8001000000000001',
//     'customer_name'    => 'Mr. X',
//     'payment_ntb'      => '0123456789',
//     'payment_amount'   => '100000',
//     'datetime_payment' => '2015-06-23 23:23:09',
//     'trx_amount'       => '100000',
//     'va_status'        => '2',
// ]
```

**Mengembalikan `null` jika:**
- `client_id` salah
- `secret_key` salah
- String bukan hasil enkripsi yang valid
- Waktu server berbeda lebih dari 480 detik (8 menit) dari waktu enkripsi

---

### Contoh Alur Lengkap

#### Create Virtual Account

```php
use OjiePermana\Laravel\Services\BNIEncryptServices;
use Illuminate\Support\Facades\Http;

$client_id  = '001';           // dari BNI
$secret_key = 'your-secret';  // dari BNI
$url        = 'https://apibeta.bni-ecollection.com/';

$data = [
    'client_id'        => $client_id,
    'trx_id'           => (string) mt_rand(),
    'trx_amount'       => 10000,
    'billing_type'     => 'c',
    'datetime_expired' => date('c', time() + 2 * 3600),
    'virtual_account'  => '',
    'customer_name'    => 'Mr. X',
    'customer_email'   => '',
    'customer_phone'   => '',
];

$hashed = BNIEncryptServices::Enc($data, $client_id, $secret_key);

$response = Http::post($url, [
    'client_id' => $client_id,
    'data'      => $hashed,
]);

$body = $response->json();

if ($body['status'] !== '000') {
    // Gagal
    throw new \Exception($body['message']);
}

$result = BNIEncryptServices::Dec($body['data'], $client_id, $secret_key);
// $result['virtual_account'] berisi nomor VA
```

#### Handle Callback dari BNI

```php
use OjiePermana\Laravel\Services\BNIEncryptServices;
use Illuminate\Http\Request;

public function callback(Request $request)
{
    $client_id  = '001';
    $secret_key = 'your-secret';

    $payload = $request->json()->all();

    if (!$payload || $payload['client_id'] !== $client_id) {
        return response()->json(['status' => '999', 'message' => 'Unauthorized']);
    }

    $data = BNIEncryptServices::Dec($payload['data'], $client_id, $secret_key);

    if (!$data) {
        return response()->json(['status' => '999', 'message' => 'Dekripsi gagal']);
    }

    // Proses $data:
    // $data['trx_id']
    // $data['virtual_account']
    // $data['payment_amount']
    // $data['payment_ntb']
    // $data['datetime_payment']

    return response()->json(['status' => '000']);
}
```

---

## Hasil Test

Dijalankan dengan **PHPUnit 13.0.5** pada **PHP 8.4.17**.

```
PHPUnit 13.0.5 by Sebastian Bergmann and contributors.

Runtime: PHP 8.4.17
Configuration: phpunit.xml

..............                                    14 / 14 (100%)

Time: 00:01.014, Memory: 16.00 MB
```

| # | Test Case | Status |
|---|-----------|--------|
| 1 | `Enc` mengembalikan string non-empty | PASS |
| 2 | `Enc` menghasilkan output berbeda setiap pemanggilan | PASS |
| 3 | `Enc` dengan array kosong tetap mengembalikan string | PASS |
| 4 | `Enc` dengan `client_id` berbeda menghasilkan output berbeda | PASS |
| 5 | `Enc` dengan `secret_key` berbeda menghasilkan output berbeda | PASS |
| 6 | `Enc` → `Dec` mengembalikan data asli (roundtrip lengkap) | PASS |
| 7 | Roundtrip dengan data minimal (1 key) | PASS |
| 8 | Roundtrip dengan array kosong | PASS |
| 9 | Roundtrip dengan karakter spesial (`&`, `<`, `"`, unicode) | PASS |
| 10 | Roundtrip dengan nilai numerik (int, float) | PASS |
| 11 | `Dec` dengan `client_id` salah mengembalikan `null` | PASS |
| 12 | `Dec` dengan `secret_key` salah mengembalikan `null` | PASS |
| 13 | `Dec` dengan string acak mengembalikan `null` | PASS |
| 14 | `Dec` dengan string kosong mengembalikan `null` | PASS |

**OK (14 tests, 24 assertions)**

---

## Referensi

- File utama: `src/Services/BNIEncryptServices.php`
- File test: `tests/Services/BNIEncryptServicesTest.php`
- Contoh penggunaan: `Example/BNI/`
