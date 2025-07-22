# ojiepermana/laravel

Paket utilitas Laravel berisi:

- Blade directive `@currency`
- Helper global `format_rupiah()`
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

