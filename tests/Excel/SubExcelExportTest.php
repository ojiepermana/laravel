<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Tests\Excel;

use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use Orchestra\Testbench\TestCase;
use OjiePermana\Laravel\Services\Excel\Export\SubExcelExport;

class SubExcelExportTest extends TestCase
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

    private function makeSubItems(int $qty): array
    {
        $items = [];
        for ($i = 1; $i <= $qty; $i++) {
            $items[] = [
                'sub_nama' => "Sub Item {$i}",
                'total'    => 10000000,
                'nilai'    => array_fill(0, 12, 833333),
            ];
        }

        return $items;
    }

    private function makeMeta(int $kelompokQty = 2, int $subQty = 3): array
    {
        $data = [];
        for ($i = 1; $i <= $kelompokQty; $i++) {
            $data[] = [
                'kelompok' => "Kelompok {$i}",
                'total'    => 10000000 * $subQty,
                'qty'      => $subQty,
                'data'     => $this->makeSubItems($subQty),
            ];
        }

        return [
            'title' => 'Laporan Sub Test',
            'total' => 10000000 * $subQty * $kelompokQty,
            'qty'   => $kelompokQty,
            'data'  => $data,
        ];
    }

    // ---------------------------------------------------------------
    // Constructor & properties
    // ---------------------------------------------------------------

    /** Constructor menginisialisasi instance dengan benar */
    public function test_constructor_initializes_instance(): void
    {
        $export = new SubExcelExport($this->makeMeta());

        $this->assertInstanceOf(SubExcelExport::class, $export);
    }

    // ---------------------------------------------------------------
    // array()
    // ---------------------------------------------------------------

    /**
     * array() harus menghasilkan:
     * 3 baris kosong + 1 header + per kelompok: (1 judul + n sub + 1 total + 1 kosong) + 1 footer
     */
    public function test_array_returns_correct_row_count(): void
    {
        $kelompokQty = 2;
        $subQty      = 3;
        $meta        = $this->makeMeta($kelompokQty, $subQty);
        $export      = new SubExcelExport($meta);
        $rows        = $export->array();

        // Per kelompok: 1 judul + subQty sub-item + 1 total + 1 baris kosong = subQty + 3
        $rowsPerKelompok = $subQty + 3;
        $expected        = 3 + 1 + ($kelompokQty * $rowsPerKelompok) + 1;

        $this->assertCount($expected, $rows);
    }

    /** Baris header (index 3) harus memiliki 20 kolom */
    public function test_array_header_row_has_correct_column_count(): void
    {
        $export = new SubExcelExport($this->makeMeta());
        $rows   = $export->array();
        $header = $rows[3];

        // 4 kosong + Nama + kosong + Total + % + 12 bulan = 20
        $this->assertCount(20, $header);
        $this->assertSame('Nama', $header[4]);
        $this->assertSame('Total', $header[6]);
        $this->assertSame('%', $header[7]);
    }

    /** Baris judul kelompok harus berisi nama kelompok di index 4 */
    public function test_array_kelompok_row_contains_kelompok_name(): void
    {
        $meta   = $this->makeMeta(2, 2);
        $export = new SubExcelExport($meta);
        $rows   = $export->array();

        // index 4 = baris judul kelompok pertama
        $kelompokRow = $rows[4];
        $this->assertSame('Kelompok 1', $kelompokRow[4]);
    }

    /** Baris sub-item harus berisi sub_nama di index 5 */
    public function test_array_sub_item_row_contains_sub_nama(): void
    {
        $meta   = $this->makeMeta(1, 2);
        $export = new SubExcelExport($meta);
        $rows   = $export->array();

        // index 4 = judul kelompok, index 5 = sub item pertama
        $subRow = $rows[5];
        $this->assertSame('Sub Item 1', $subRow[5]);
        $this->assertSame(10000000, $subRow[6]);
    }

    /** Baris footer harus berisi grand_total dan persentase 100 */
    public function test_array_footer_contains_grand_total(): void
    {
        $meta        = $this->makeMeta(2, 2);
        $export      = new SubExcelExport($meta);
        $rows        = $export->array();
        $footer      = end($rows);
        $grandTotal  = 10000000 * 2 * 2;

        $this->assertSame('Total', $footer[4]);
        $this->assertSame($grandTotal, $footer[6]);
        $this->assertSame(100, $footer[7]);
    }

    /** grand_total nol tidak boleh menyebabkan division by zero */
    public function test_array_handles_zero_grand_total_without_division_error(): void
    {
        $meta = [
            'title' => 'Test',
            'total' => 0,
            'qty'   => 1,
            'data'  => [
                [
                    'kelompok' => 'Kelompok A',
                    'total'    => 0,
                    'qty'      => 1,
                    'data'     => [
                        [
                            'sub_nama' => 'Sub A',
                            'total'    => 0,
                            'nilai'    => array_fill(0, 12, 0),
                        ],
                    ],
                ],
            ],
        ];

        $export = new SubExcelExport($meta);
        $rows   = $export->array();

        $subRow = $rows[5];
        $this->assertEquals(0, $subRow[4]); // persen kelompok
        $this->assertEquals(0, $subRow[7]); // persen all
    }

    // ---------------------------------------------------------------
    // columnWidths()
    // ---------------------------------------------------------------

    /** columnWidths() harus mencakup kolom E sampai U */
    public function test_column_widths_covers_all_expected_columns(): void
    {
        $export   = new SubExcelExport($this->makeMeta());
        $widths   = $export->columnWidths();
        $expected = ['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];

        foreach ($expected as $col) {
            $this->assertArrayHasKey($col, $widths, "Kolom {$col} tidak ada di columnWidths()");
        }
    }

    // ---------------------------------------------------------------
    // columnFormats()
    // ---------------------------------------------------------------

    /** columnFormats() harus mencakup kolom E, G, H, dan kolom angka lainnya */
    public function test_column_formats_covers_all_expected_columns(): void
    {
        $export   = new SubExcelExport($this->makeMeta());
        $formats  = $export->columnFormats();
        $expected = ['E', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];

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

        SubExcelExport::export($this->makeMeta(), 'test-sub.xlsx');

        Excel::assertDownloaded('test-sub.xlsx');
    }

    /** export() menggunakan nama file default jika tidak diberikan */
    public function test_static_export_uses_default_filename(): void
    {
        Excel::fake();

        SubExcelExport::export($this->makeMeta());

        Excel::assertDownloaded('laporan-sub.xlsx');
    }

    /** export() harus men-download dengan class SubExcelExport */
    public function test_static_export_downloads_with_correct_export_class(): void
    {
        Excel::fake();

        SubExcelExport::export($this->makeMeta(), 'laporan.xlsx');

        Excel::assertDownloaded('laporan.xlsx', function (SubExcelExport $export) {
            return true;
        });
    }
}
