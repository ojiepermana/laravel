<?php

namespace OjiePermana\Laravel\Services\Excel\Export;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Sheet;
use OjiePermana\Laravel\Helpers\IndonesiaHelper;

class SubExcelExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents
{
    use Exportable;

    protected $meta;

    protected $data;

    protected $sub;

    protected $title;

    protected $grand_total;

    protected $start = 4;

    protected $rows;

    protected $all_rows;

    protected $columns_start = 'E';

    protected $columns_end = 'T';

    public function __construct(array $meta)
    {
        $this->meta = $meta;
        $this->data();
        $this->rows();
        $this->all_rows($this->data);
        $this->total();
        $this->title();
    }

    public static function export(array $meta, string $filename = 'laporan-sub.xlsx')
    {
        return (new static($meta))->download($filename);
    }

    protected function data()
    {
        $this->data = $this->meta['data'];
    }

    protected function all_rows($data)
    {
        $num = 0;
        foreach ($data as $row) {
            $num += $row['qty'] + 3;
        }
        $this->all_rows = $num;
    }

    protected function rows()
    {
        $this->rows = $this->meta['qty'];
    }

    protected function total()
    {
        $this->grand_total = $this->meta['total'];
    }

    protected function title()
    {
        $judul = $this->meta['title'];
        $this->title = $judul;
    }

    public function array(): array
    {
        $rows = [];

        for ($no = 0; $no < 3; $no++) {
            $rows[] = array_fill(0, 20, '');
        }

        $header = ['', '', '', '', 'Nama', '', 'Total', '%'];
        for ($month = 1; $month <= 12; $month++) {
            $header[] = IndonesiaHelper::bulan('mf', $month);
        }
        $rows[] = $header;

        foreach ($this->data as $item) {
            $rows[] = array_merge(['', '', '', '', $item['kelompok']], array_fill(0, 15, ''));

            $kelompok = array_fill(0, 12, 0);

            foreach ($item['data'] as $row) {
                $persen = ($item['total'] ?? 0) !== 0 ? (($row['total'] ?? 0) / $item['total']) * 100 : 0;
                $persenAll = ($this->grand_total ?? 0) !== 0 ? (($row['total'] ?? 0) / $this->grand_total) * 100 : 0;

                $detail = ['', '', '', '', $persen, $row['sub_nama'], $row['total'], $persenAll];
                for ($month = 0; $month < 12; $month++) {
                    $value = $row['nilai'][$month] ?? 0;
                    $kelompok[$month] += $value;
                    $detail[] = $value;
                }

                $rows[] = $detail;
            }

            $persenKelompok = ($this->grand_total ?? 0) !== 0 ? (($item['total'] ?? 0) / $this->grand_total) * 100 : 0;
            $totalRow = ['', '', '', '', 'Total', '', $item['total'], $persenKelompok];
            for ($month = 0; $month < 12; $month++) {
                $totalRow[] = $kelompok[$month];
            }

            $rows[] = $totalRow;
            $rows[] = array_fill(0, 20, '');
        }

        $footer = ['', '', '', '', 'Total', '', $this->grand_total, 100];
        for ($month = 0; $month < 12; $month++) {
            $footer[] = 'a';
        }
        $rows[] = $footer;

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'E' => 8,
            'F' => 25,
            'G' => 20,
            'H' => 8,
            'I' => 15,
            'J' => 15,
            'K' => 15,
            'L' => 15,
            'M' => 15,
            'N' => 15,
            'O' => 15,
            'P' => 15,
            'Q' => 15,
            'R' => 15,
            'S' => 15,
            'T' => 15,
            'U' => 15,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0.00_-',
            'G' => '#,##0_-',
            'H' => '#,##0.00_-',
            'J' => '#,##0_-',
            'I' => '#,##0_-',
            'K' => '#,##0_-',
            'L' => '#,##0_-',
            'M' => '#,##0_-',
            'N' => '#,##0_-',
            'O' => '#,##0_-',
            'P' => '#,##0_-',
            'Q' => '#,##0_-',
            'R' => '#,##0_-',
            'S' => '#,##0_-',
            'T' => '#,##0_-',
        ];
    }

    public function registerEvents(): array
    {
        Sheet::macro('styleCells', function (Sheet $sheet, string $cellRange, array $style) {
            $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($style);
        });

        return [
            AfterSheet::class => function (AfterSheet $event) {
                $mulai = $this->start;
                // all border
                self::allBorder($this->all_rows, $event, $mulai, $this->columns_start, $this->columns_end);
                // outline border
                self::outLineBorderDouble($this->all_rows, $event, $mulai, $this->columns_start, $this->columns_end);
                // header
                self::header($event, $mulai, $this->columns_start, $this->columns_end);
            },
        ];
    }

    public static function header($event, $mulai, $columns_start, $columns_end)
    {
        $event->sheet->styleCells(
            $columns_start.$mulai.':'.$columns_end.$mulai,
            [
                'borders' => [
                    'bottom' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE,
                        'color' => ['argb' => '00000000'],
                    ],
                ],
            ]
        );
        $event->sheet->styleCells(
            $columns_start.$mulai.':'.$columns_end.$mulai,
            [
                'font' => [
                    'size' => 12,
                    'color' => ['argb' => '00000000'],
                ],
            ]
        );
    }

    public static function allBorder($total, $event, $mulai, $columns_start, $columns_end)
    {
        $akhir = $mulai + $total + 1;
        $event->sheet->styleCells(
            $columns_start.$mulai.':'.$columns_end.$akhir,
            [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => '00000000'],
                    ],
                ],
            ]
        );
    }

    public static function outLineBorderDouble($total, $event, $mulai, $columns_start, $columns_end)
    {
        $akhir = $mulai + $total + 1;
        $event->sheet->styleCells(
            $columns_start.$mulai.':'.$columns_end.$akhir,
            [
                'borders' => [
                    'outline' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                        'color' => ['argb' => '00000000'],
                    ],
                ],
            ]
        );
    }
}
