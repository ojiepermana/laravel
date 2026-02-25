<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Tests\Excel;

use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use Orchestra\Testbench\TestCase;
use OjiePermana\Laravel\Services\Excel\Export\MainExcelExport;

class MainExcelExportTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ExcelServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Excel' => Excel::class];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeMeta(int $qty = 2, int $total = 500000000): array
    {
        $data = [];
        for ($i = 1; $i <= $qty; $i++) {
            $data[] = [
                'main_nama' => "Item {$i}",
                'total'     => (int) ($total / $qty),
                'nilai'     => array_fill(0, 12, (int) ($total / $qty / 12)),
            ];
        }

        return [
            'title' => 'Laporan Test',
            'total' => $total,
            'qty'   => $qty,
            'data'  => $data,
        ];
    }

    // ---------------------------------------------------------------
    // Constructor & properties
    // ---------------------------------------------------------------

    /** Constructor menyimpan meta dan menginisialisasi properti dengan benar */
    public function test_constructor_initializes_properties(): void
    {
        $meta   = $this->makeMeta();
        $export = new MainExcelExport($meta);

        $this->assertInstanceOf(MainExcelExport::class, $export);
    }

    // ---------------------------------------------------------------
    // array()
    // ---------------------------------------------------------------

    /** array() harus mengembalikan 3 baris awal kosong + 1 header + n data + 1 footer */
    public function test_array_returns_correct_row_count(): void
    {
        $qty    = 3;
        $meta   = $this->makeMeta($qty);
        $export = new MainExcelExport($meta);
        $rows   = $export->array();

        // 3 baris kosong + 1 header + qty baris data + 1 footer
        $expected = 3 + 1 + $qty + 1;
        $this->assertCount($expected, $rows);
    }

    /** Baris header (index 3) harus berisi kolom 'No', 'Nama', 'Total', '%', dan 12 bulan */
    public function test_array_header_row_has_correct_structure(): void
    {
        $export = new MainExcelExport($this->makeMeta());
        $rows   = $export->array();

        $header = $rows[3];
        $this->assertCount(21, $header); // 5 kosong + No + Nama + Total + % + 12 bulan
        $this->assertSame('No', $header[5]);
        $this->assertSame('Nama', $header[6]);
        $this->assertSame('Total', $header[7]);
        $this->assertSame('%', $header[8]);
    }

    /** Baris data harus berisi main_nama dan total yang benar */
    public function test_array_data_rows_contain_correct_values(): void
    {
        $meta   = $this->makeMeta(2, 200000000);
        $export = new MainExcelExport($meta);
        $rows   = $export->array();

        $firstDataRow = $rows[4]; // index 0-2 kosong, index 3 header, index 4 data pertama
        $this->assertSame('Item 1', $firstDataRow[6]);
        $this->assertSame(100000000, $firstDataRow[7]);
    }

    /** Baris footer harus berisi 'Total' dan grand total yang benar */
    public function test_array_footer_row_contains_grand_total(): void
    {
        $meta   = $this->makeMeta(2, 200000000);
        $export = new MainExcelExport($meta);
        $rows   = $export->array();

        $footer = end($rows);
        $this->assertSame('Total', $footer[6]);
        $this->assertSame(200000000, $footer[7]);
        $this->assertSame(100, $footer[8]);
    }

    /** Setiap baris data harus memiliki 21 kolom (5 kosong + 4 info + 12 bulan) */
    public function test_array_data_rows_have_21_columns(): void
    {
        $export = new MainExcelExport($this->makeMeta(3));
        $rows   = $export->array();

        foreach (array_slice($rows, 4, 3) as $row) {
            $this->assertCount(21, $row);
        }
    }

    /** grand_total nol tidak boleh menyebabkan division by zero, persentase harus 0 */
    public function test_array_handles_zero_grand_total_without_division_error(): void
    {
        $meta = [
            'title' => 'Test',
            'total' => 0,
            'qty'   => 1,
            'data'  => [
                [
                    'main_nama' => 'Item A',
                    'total'     => 0,
                    'nilai'     => array_fill(0, 12, 0),
                ],
            ],
        ];

        $export = new MainExcelExport($meta);
        $rows   = $export->array();
        $dataRow = $rows[4];

        $this->assertSame(0, $dataRow[8]); // persentase harus 0
    }

    // ---------------------------------------------------------------
    // columnWidths()
    // ---------------------------------------------------------------

    /** columnWidths() harus mencakup kolom F sampai U */
    public function test_column_widths_covers_all_expected_columns(): void
    {
        $export  = new MainExcelExport($this->makeMeta());
        $widths  = $export->columnWidths();
        $expected = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];

        foreach ($expected as $col) {
            $this->assertArrayHasKey($col, $widths, "Kolom {$col} tidak ada di columnWidths()");
        }
    }

    // ---------------------------------------------------------------
    // columnFormats()
    // ---------------------------------------------------------------

    /** columnFormats() harus mencakup kolom H sampai U */
    public function test_column_formats_covers_all_expected_columns(): void
    {
        $export   = new MainExcelExport($this->makeMeta());
        $formats  = $export->columnFormats();
        $expected = ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];

        foreach ($expected as $col) {
            $this->assertArrayHasKey($col, $formats, "Kolom {$col} tidak ada di columnFormats()");
        }
    }

    // ---------------------------------------------------------------
    // export() static
    // ---------------------------------------------------------------

    /** export() static harus memicu Excel::download */
    public function test_static_export_triggers_download(): void
    {
        Excel::fake();

        MainExcelExport::export($this->makeMeta(), 'test.xlsx');

        Excel::assertDownloaded('test.xlsx');
    }

    /** export() menggunakan nama file default jika tidak diberikan */
    public function test_static_export_uses_default_filename(): void
    {
        Excel::fake();

        MainExcelExport::export($this->makeMeta());

        Excel::assertDownloaded('laporan-main.xlsx');
    }

    /** export() harus men-download dengan class MainExcelExport */
    public function test_static_export_downloads_with_correct_export_class(): void
    {
        Excel::fake();

        MainExcelExport::export($this->makeMeta(), 'laporan.xlsx');

        Excel::assertDownloaded('laporan.xlsx', function (MainExcelExport $export) {
            return true;
        });
    }
}
