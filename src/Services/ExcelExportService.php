<?php

namespace OjiePermana\Laravel\Services;

use Maatwebsite\Excel\Facades\Excel;

class ExcelExportService
{
    public static function exportArray(string $filename, array $data, array $headers = [])
    {
        $export = new class($data, $headers) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            protected $data;
            protected $headers;

            public function __construct($data, $headers)
            {
                $this->data = $data;
                $this->headers = $headers;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headers;
            }
        };

        return Excel::download($export, $filename);
    }
}
