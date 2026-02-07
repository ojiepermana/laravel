# ojiepermana/laravel

Paket utilitas Laravel berisi:

- Blade directive `@currency`
- Helper global `format_rupiah()`
- Helper Indonesia untuk format tanggal, angka, dan utilitas lainnya
- Service `ExcelExportService` untuk export Excel

## Instalasi

```bash
composer require ojiepermana/laravel
```

Atau jika lokal:

```bash
composer config repositories.ojie path ./path/ke/folder/laravel
composer require ojiepermana/laravel:*
```

## Penggunaan

### Blade Directive

```blade
@currency(1500000)
```

Output:
```
Rp 1.500.000
```

### Helper

```php
format_rupiah(250000); // Rp 250.000
```

### Export Excel

```php
ExcelExportService::exportArray('laporan.xlsx', $data, $headers);
```

### Helper Indonesia

Library ini menyediakan berbagai helper untuk format tanggal, angka, dan utilitas Indonesia lainnya.

#### Format Tanggal Indonesia

```php
// Format default (tanggal bulan tahun)
tanggal_indo('2026-02-07'); 
// Output: 07 Februari 2026

// Dengan nama hari
tanggal_indo('2026-02-07', 'mf', true); 
// Output: Jumat, 07 Februari 2026

// Dengan waktu
tanggal_indo('2026-02-07 14:30:00', 'mf', false, true); 
// Output: 07 Februari 2026 : 14:30

// Format bulan pendek
tanggal_indo('2026-02-07', 'mh'); 
// Output: 07 Feb 2026

// Menggunakan class
use OjiePermana\Laravel\Helpers\IndonesiaHelper;
IndonesiaHelper::tanggal('2026-02-07', 'mf', true, true);
```

#### Nama Bulan Indonesia

```php
// Bulan penuh
bulan_indo('mf', 2); // Februari

// Bulan pendek
bulan_indo('mh', 2); // Feb
```

#### Format Mata Uang

```php
// Format rupiah
format_uang(1500000); 
// Output: Rp 1.500.000

// Tanpa lambang
format_uang(1500000, 'ya', false); 
// Output: 1.500.000

// Sembunyikan nilai
format_uang(1500000, 'tidak'); 
// Output: rahasia
```

#### Angka Romawi

```php
angka_romawi(12); // XII
angka_romawi(2026); // MMXXVI
```

#### Terbilang

```php
terbilang(1500000); 
// Output: satu juta lima ratus ribu

terbilang(2026); 
// Output: dua ribu dua puluh enam
```

#### Nama Hari

```php
nama_hari('2026-02-07'); // Jumat
nama_hari('2026-01-01'); // Kamis
```

#### Bulan dan Tahun

```php
bulan_tahun_indo('2026-02-07'); // Februari 2026
```

#### Perhitungan Tanggal

```php
// Jumlah hari antara dua tanggal
jumlah_hari('2026-02-01', '2026-02-28'); // 27

// Jumlah bulan antara dua tanggal
jumlah_bulan('2026-01-01', '2026-06-30'); // 5

// Minggu keberapa dalam bulan
minggu_ke_bulan('2026-02-07'); // pertama

// Hari terakhir bulan
hari_terakhir_bulan('2026-02-07'); // 2026-02-28

// Hari pertama minggu (Senin)
hari_pertama_minggu('2026-02-07'); // 2026-02-02

// Hari terakhir minggu (Minggu)
hari_terakhir_minggu('2026-02-07'); // 2026-02-08
```

#### Menggunakan Class Helper

Semua fungsi di atas juga tersedia melalui class `IndonesiaHelper`:

```php
use OjiePermana\Laravel\Helpers\IndonesiaHelper;

// Contoh penggunaan
IndonesiaHelper::tanggal('2026-02-07', 'mf', true, true);
IndonesiaHelper::uang(1500000);
IndonesiaHelper::terbilang(2026);
IndonesiaHelper::romawi(12);
IndonesiaHelper::nameday('2026-02-07');
IndonesiaHelper::jumlahHari2Tanggal('2026-02-01', '2026-02-28');
// dll.
```

