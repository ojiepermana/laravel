<?php

namespace OjiePermana\Laravel\Helpers;

class IndonesiaHelper
{
    public static function tanggal(string $tgl, string $type = 'mf', bool $day = false, bool $time = false): string
    {
        $tanggal = substr($tgl, 8, 2);
        $bln = substr($tgl, 5, 2);
        $tahun = substr($tgl, 0, 4);

        // Array nama bulan Indonesia
        $bulanPendek = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
        $bulanPanjang = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        if ($type != 'mn') {
            $bln = $bln * 1;
            $bulan = ($type == 'mf') ? $bulanPanjang[$bln] : $bulanPendek[$bln];
        } else {
            $bulan = $bln;
        }

        $text = $tanggal.' '.$bulan.' '.$tahun;

        if ($time == true) {
            $timeStr = substr($tgl, 11, 5);
            $text = $text.' : '.$timeStr;
        }

        if ($day == true) {
            $hariInggris = strtolower(date('l', mktime(0, 0, 0, $bln, $tanggal, $tahun)));
            $namaHari = [
                'monday' => 'Senin',
                'tuesday' => 'Selasa',
                'wednesday' => 'Rabu',
                'thursday' => 'Kamis',
                'friday' => 'Jumat',
                'saturday' => 'Sabtu',
                'sunday' => 'Minggu',
            ];
            $text = $namaHari[$hariInggris].', '.$text;
        }

        return $text;
    }

    public static function bulan(string $type, int|string $bulan): string
    {
        $bulan = $bulan * 1;

        // Array nama bulan Indonesia
        $bulanPendek = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
        $bulanPanjang = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return ($type == 'mf') ? $bulanPanjang[$bulan] : $bulanPendek[$bulan];
    }

    public static function uang(mixed $nilai, string $show = 'ya', bool|string $lambang = false): string
    {
        if ($show == 'tidak') {
            return 'rahasia';
        } else {
            if ($nilai == true) {
                $jumlah_desimal = '0';
                $pemisah_desimal = ',';
                $pemisah_ribuan = '.';
                if ($lambang != false) {
                    $lambang = '';
                }

                return $lambang.' '.number_format(floatval($nilai), $jumlah_desimal, $pemisah_desimal, $pemisah_ribuan);
            } else {
                return $lambang.' 0';
            }
        }
    }

    public static function romawi(int $n): string
    {
        $hasil = '';
        $iromawi = [
            '', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 20 => 'XX', 30 => 'XXX', 40 => 'XL', 50 => 'L',
            60 => 'LX', 70 => 'LXX', 80 => 'LXXX', 90 => 'XC', 100 => 'C', 200 => 'CC', 300 => 'CCC', 400 => 'CD', 500 => 'D', 600 => 'DC', 700 => 'DCC',
            800 => 'DCCC', 900 => 'CM', 1000 => 'M', 2000 => 'MM', 3000 => 'MMM',
        ];

        if (array_key_exists($n, $iromawi)) {
            $hasil = $iromawi[$n];
        } elseif ($n >= 11 && $n <= 99) {
            $i = $n % 10;
            $hasil = $iromawi[$n - $i].self::romawi($n % 10);
        } elseif ($n >= 101 && $n <= 999) {
            $i = $n % 100;
            $hasil = $iromawi[$n - $i].self::romawi($n % 100);
        } else {
            $i = $n % 1000;
            $hasil = $iromawi[$n - $i].self::romawi($n % 1000);
        }

        return $hasil;
    }

    public static function jumlahBulan(string $awal, string $selesai): int
    {
        $df = explode('-', $awal);
        $awalnya = $df[0].'-'.$df[1].'-01';

        return round((strtotime($selesai) - strtotime($awalnya)) / (60 * 60 * 24 * 30));
    }

    public static function JumlahBulanRKT(string $awal, string $selesai): int
    {
        $df = explode('-', $awal);
        $awalnya = $df[0].'-'.$df[1].'-01';

        return round((strtotime(date('Y-m-t', strtotime($selesai))) - strtotime($awalnya)) / (60 * 60 * 24 * 30));
    }

    public static function weekofmont(string $tgl): string
    {
        $tanggal = substr($tgl, 8, 2);
        $bulan = substr($tgl, 5, 2);
        $tahun = substr($tgl, 0, 4);
        // echo $bulan."-".$tanggal."-".$tahun."<br />";;
        $no = date('j', mktime(0, 0, 0, $bulan, $tanggal, $tahun));

        if ($no <= 7) {
            return 'pertama';
        }
        if ($no <= 14 and $no >= 8) {
            return 'kedua';
        }
        if ($no <= 21 and $no >= 15) {
            return 'ketiga';
        }
        if ($no >= 22) {
            return 'keempat';
        }

        return '';
    }

    public static function nameday(string $tgl): string
    {
        $tgls = explode('-', $tgl);
        $namahari = date('l', mktime(0, 0, 0, $tgls[1], $tgls[2], $tgls[0]));

        switch ($namahari) {
            case 'Monday':
                return 'Senin';
            case 'Tuesday':
                return 'Selasa';
            case 'Wednesday':
                return 'Rabu';
            case 'Thursday':
                return 'Kamis';
            case 'Friday':
                return 'Jumat';
            case 'Saturday':
                return 'Sabtu';
            case 'Sunday':
                return 'Minggu';
            default:
                return '';
        }
    }

    public static function bulan_tahun(string $tgl): string
    {
        $oje = explode('-', $tgl);
        $bln = $oje[1] * 1;
        $bulan = self::bulan('mh', $bln);
        $tahun = $oje[0];

        return $bulan.' '.$tahun;
    }

    public static function terbilang(float|int $angka): string
    {
        // pastikan kita hanya berususan dengan tipe data numeric
        $angka = (float) $angka;

        // array bilangan
        // sepuluh dan sebelas merupakan special karena awalan 'se'
        $bilangan = [
            '',
            'satu',
            'dua',
            'tiga',
            'empat',
            'lima',
            'enam',
            'tujuh',
            'delapan',
            'sembilan',
            'sepuluh',
            'sebelas',
        ];

        // pencocokan dimulai dari satuan angka terkecil
        if ($angka < 12) {
            // mapping angka ke index array $bilangan
            return $bilangan[$angka];
        } elseif ($angka < 20) {
            // bilangan 'belasan'
            // misal 18 maka 18 - 10 = 8
            return $bilangan[$angka - 10].' belas';
        } elseif ($angka < 100) {
            // bilangan 'puluhan'
            // misal 27 maka 27 / 10 = 2.7 (integer => 2) 'dua'
            // untuk mendapatkan sisa bagi gunakan modulus
            // 27 mod 10 = 7 'tujuh'
            $hasil_bagi = (int) ($angka / 10);
            $hasil_mod = $angka % 10;

            return trim(sprintf('%s puluh %s', $bilangan[$hasil_bagi], $bilangan[$hasil_mod]));
        } elseif ($angka < 200) {
            // bilangan 'seratusan' (itulah indonesia knp tidak satu ratus saja? :))
            // misal 151 maka 151 = 100 = 51 (hasil berupa 'puluhan')
            // daripada menulis ulang rutin kode puluhan maka gunakan
            // saja fungsi rekursif dengan memanggil fungsi self::terbilang(51)
            return sprintf('seratus %s', self::terbilang($angka - 100));
        } elseif ($angka < 1000) {
            // bilangan 'ratusan'
            // misal 467 maka 467 / 100 = 4,67 (integer => 4) 'empat'
            // sisanya 467 mod 100 = 67 (berupa puluhan jadi gunakan rekursif self::terbilang(67))
            $hasil_bagi = (int) ($angka / 100);
            $hasil_mod = $angka % 100;

            return trim(sprintf('%s ratus %s', $bilangan[$hasil_bagi], self::terbilang($hasil_mod)));
        } elseif ($angka < 2000) {
            // bilangan 'seribuan'
            // misal 1250 maka 1250 - 1000 = 250 (ratusan)
            // gunakan rekursif self::terbilang(250)
            return trim(sprintf('seribu %s', self::terbilang($angka - 1000)));
        } elseif ($angka < 1000000) {
            // bilangan 'ribuan' (sampai ratusan ribu
            $hasil_bagi = (int) ($angka / 1000); // karena hasilnya bisa ratusan jadi langsung digunakan rekursif
            $hasil_mod = $angka % 1000;

            return sprintf('%s ribu %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod));
        } elseif ($angka < 1000000000) {
            // bilangan 'jutaan' (sampai ratusan juta)
            // 'satu puluh' => SALAH
            // 'satu ratus' => SALAH
            // 'satu juta' => BENAR
            // @#$%^ WT*

            // hasil bagi bisa satuan, belasan, ratusan jadi langsung kita gunakan rekursif
            $hasil_bagi = (int) ($angka / 1000000);
            $hasil_mod = $angka % 1000000;

            return trim(sprintf('%s juta %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod)));
        } elseif ($angka < 1000000000000) {
            // bilangan 'milyaran'
            $hasil_bagi = (int) ($angka / 1000000000);
            // karena batas maksimum integer untuk 32bit sistem adalah 2147483647
            // maka kita gunakan fmod agar dapat menghandle angka yang lebih besar
            $hasil_mod = fmod($angka, 1000000000);

            return trim(sprintf('%s milyar %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod)));
        } elseif ($angka < 1000000000000000) {
            // bilangan 'triliun'
            $hasil_bagi = $angka / 1000000000000;
            $hasil_mod = fmod($angka, 1000000000000);

            return trim(sprintf('%s triliun %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod)));
        } else {
            return 'Wow...';
        }
    }

    public static function firstDayonWeek(string $date): string
    {
        $day = 0;
        $dayofweek = date('w', strtotime($date));

        return date('Y-m-d', strtotime(($day - $dayofweek).' day', strtotime($date)));
    }

    public static function lastDayonWeek(string $date): string
    {
        $day = 6;
        $dayofweek = date('w', strtotime($date));

        return date('Y-m-d', strtotime(($day - $dayofweek).' day', strtotime($date)));
    }

    public static function lastDayOfMonth(string $date): string
    {
        return date('Y-m-t', strtotime($date));
    }

    public static function klasifikasi(int $val): string
    {
        if ($val == 60) {
            return 'C';
        } elseif ($val == 70) {
            return 'B';
        } else {
            return 'A';
        }
    }

    public static function awalBulanDepan(string $tanggal): string
    {
        $tgl = explode('-', $tanggal);

        return date('Y-m-d', mktime(0, 0, 0, $tgl[1] + 1, 1, $tgl[0]));
    }

    public static function bulanDepan(): string
    {
        return date('Y-m-d', mktime(0, 0, 0, date('m') + 1, date('d'), date('Y')));
    }

    public static function sekarangToBulan(int $bulan): string
    {
        $bulan = $bulan + 1;

        return date('Y-m-d', mktime(0, 0, 0, date('m') + $bulan, 0, date('Y')));
    }

    public static function sekarangToTanggal(int $bulan): string
    {
        return date('Y-m-d', mktime(0, 0, 0, date('m') + $bulan, date('d'), date('Y')));
    }

    public static function tanggalUntukNHari(string $tanggal, int $n): string
    {
        $tgl = explode('-', $tanggal);
        $hari = $tgl[2] + $n;
        $bulan = $tgl[1];
        $tahun = $tgl[0];

        return date('Y-m-d', mktime(0, 0, 0, $bulan, $hari, $tahun));
    }

    public static function jumlahHari2Tanggal(string $start, string $end): int
    {
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        $diff = $end_ts - $start_ts;

        return round($diff / 86400);
    }

    public static function dealinePerpanjangan(string $selesai): string
    {
        $tgl = explode('-', $selesai);

        return date('Y-m-d', mktime(0, 0, 0, $tgl[1], $tgl[2] - 7, $tgl[0]));
    }

    public static function bulanMinggu(int $minggu, int $bulan, int $tahun): string
    {
        if ($minggu > 52) {
            $tahun = $tahun + 1;
            $minggu = $minggu - 52;
        }
        $minggukebulan = date('F', strtotime($tahun.'-W'.$minggu));
        $bulan = date('n', strtotime($tahun.'-W'.$minggu));
        $awal_bulan = $tahun.'-'.$bulan.'-01';
        $awal = date('W', strtotime($awal_bulan));
        $minggu_ke = $minggu - $awal;

        return $minggukebulan.' KE  '.self::romawi($minggu_ke);
    }
}

// Helper Functions
if (!function_exists('tanggal_indo')) {
    /**
     * Format tanggal ke format Indonesia
     *
     * @param string $tgl Tanggal dalam format Y-m-d atau Y-m-d H:i:s
     * @param string $type Format bulan: 'mf' (penuh), 'mh' (pendek), 'mn' (numerik)
     * @param bool $day Tampilkan nama hari
     * @param bool $time Tampilkan waktu
     * @return string
     */
    function tanggal_indo(string $tgl, string $type = 'mf', bool $day = false, bool $time = false): string
    {
        return IndonesiaHelper::tanggal($tgl, $type, $day, $time);
    }
}

if (!function_exists('bulan_indo')) {
    /**
     * Konversi angka bulan ke nama bulan Indonesia
     *
     * @param string $type Format: 'mf' (penuh), 'mh' (pendek)
     * @param int|string $bulan Nomor bulan (1-12)
     * @return string
     */
    function bulan_indo(string $type, int|string $bulan): string
    {
        return IndonesiaHelper::bulan($type, $bulan);
    }
}

if (!function_exists('format_uang')) {
    /**
     * Format angka ke format mata uang Indonesia
     *
     * @param mixed $nilai Nilai yang akan diformat
     * @param string $show Tampilkan nilai: 'ya' atau 'tidak'
     * @param bool|string $lambang Lambang mata uang (default: Rp)
     * @return string
     */
    function format_uang(mixed $nilai, string $show = 'ya', bool|string $lambang = 'Rp'): string
    {
        return IndonesiaHelper::uang($nilai, $show, $lambang);
    }
}

if (!function_exists('angka_romawi')) {
    /**
     * Konversi angka ke angka Romawi
     *
     * @param int $n Angka yang akan dikonversi
     * @return string
     */
    function angka_romawi(int $n): string
    {
        return IndonesiaHelper::romawi($n);
    }
}

if (!function_exists('terbilang')) {
    /**
     * Konversi angka ke terbilang Indonesia
     *
     * @param float|int $angka Angka yang akan dikonversi
     * @return string
     */
    function terbilang(float|int $angka): string
    {
        return IndonesiaHelper::terbilang($angka);
    }
}

if (!function_exists('nama_hari')) {
    /**
     * Dapatkan nama hari dalam Bahasa Indonesia
     *
     * @param string $tgl Tanggal dalam format Y-m-d
     * @return string
     */
    function nama_hari(string $tgl): string
    {
        return IndonesiaHelper::nameday($tgl);
    }
}

if (!function_exists('bulan_tahun_indo')) {
    /**
     * Format bulan dan tahun dalam Bahasa Indonesia
     *
     * @param string $tgl Tanggal dalam format Y-m-d
     * @return string
     */
    function bulan_tahun_indo(string $tgl): string
    {
        return IndonesiaHelper::bulan_tahun($tgl);
    }
}

if (!function_exists('jumlah_hari')) {
    /**
     * Hitung jumlah hari antara dua tanggal
     *
     * @param string $start Tanggal mulai (Y-m-d)
     * @param string $end Tanggal selesai (Y-m-d)
     * @return int
     */
    function jumlah_hari(string $start, string $end): int
    {
        return IndonesiaHelper::jumlahHari2Tanggal($start, $end);
    }
}

if (!function_exists('jumlah_bulan')) {
    /**
     * Hitung jumlah bulan antara dua tanggal
     *
     * @param string $awal Tanggal awal (Y-m-d)
     * @param string $selesai Tanggal selesai (Y-m-d)
     * @return int
     */
    function jumlah_bulan(string $awal, string $selesai): int
    {
        return IndonesiaHelper::jumlahBulan($awal, $selesai);
    }
}

if (!function_exists('minggu_ke_bulan')) {
    /**
     * Dapatkan minggu keberapa dalam bulan
     *
     * @param string $tgl Tanggal dalam format Y-m-d
     * @return string
     */
    function minggu_ke_bulan(string $tgl): string
    {
        return IndonesiaHelper::weekofmont($tgl);
    }
}

if (!function_exists('hari_terakhir_bulan')) {
    /**
     * Dapatkan tanggal hari terakhir bulan
     *
     * @param string $date Tanggal dalam format Y-m-d
     * @return string
     */
    function hari_terakhir_bulan(string $date): string
    {
        return IndonesiaHelper::lastDayOfMonth($date);
    }
}

if (!function_exists('hari_pertama_minggu')) {
    /**
     * Dapatkan hari pertama dalam minggu (Senin)
     *
     * @param string $date Tanggal dalam format Y-m-d
     * @return string
     */
    function hari_pertama_minggu(string $date): string
    {
        return IndonesiaHelper::firstDayonWeek($date);
    }
}

if (!function_exists('hari_terakhir_minggu')) {
    /**
     * Dapatkan hari terakhir dalam minggu (Minggu)
     *
     * @param string $date Tanggal dalam format Y-m-d
     * @return string
     */
    function hari_terakhir_minggu(string $date): string
    {
        return IndonesiaHelper::lastDayonWeek($date);
    }
}
