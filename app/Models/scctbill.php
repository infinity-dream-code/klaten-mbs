<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class scctbill extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "scctbill";

    protected $primaryKey = "AA";

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        "CUSTID",
        "BILLCD",
        "BILLAC",
        "BILLNM",
        "BILLAM",
        "BILLPAID",
        "PAYMENTLEFT",
        "FLPART",
        "PAIDST",
        "PAIDDT",
        "PAIDDT_ACTUAL",
        "NOREFF",
        "FSTSBolehBayar",
        "FUrutan",
        "FTGLTagihan",
        "FIDBANK",
        "FRecID",
        "AA",
        "BTA",
        "BILLTOT",
        "TRANSNO",
        "BAYAR",
        "INSTALLMENT",
        "isINSTALLABLE",
    ];

    public array $metodeBayar = [
        "1140000" => "Manual Cash",
        "1140001" => "Manual BMI",
        "1140002" => "Manual SALDO",
        "1140003" => "Transfer Bank Lain",
        "1140004" => "INFAQ",
        "1140005" => "Transfer Bank BRI",
        "1200001" => "Loket Manual - Beasiswa",
        "1200002" => "Loket Manual - Potongan",
        "1" => "H2H VA BMI - ATM",
        "2" => "H2H VA BMI - Teller",
        "3" => "H2H VA BMI - IBANK",
        "4" => "H2H VA BMI - EDC",
        "5" => "H2H VA BMI - MOBILE",
        "6" => "ANDROID",
        null => "Nomor VA",
        "" => "Nomor VA",
    ];

    /** BILLAC YYYYMM (contoh 202609) → "September 2026". */
    public static function formatPeriodeBulan(mixed $billac): string
    {
        $digits = preg_replace('/\D/', '', (string) $billac) ?? '';
        if (strlen($digits) < 6) {
            return trim((string) $billac);
        }

        $digits = substr($digits, 0, 6);
        $month = (int) substr($digits, 4, 2);
        $year = substr($digits, 0, 4);
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        if (!isset($names[$month])) {
            return $digits;
        }

        return $names[$month] . ' ' . $year;
    }

    public static function normalizeBillac(mixed $billac): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $billac) ?? '';
        if (strlen($digits) < 6) {
            $raw = trim((string) $billac);

            return $raw !== '' ? $raw : null;
        }

        return substr($digits, 0, 6);
    }
}
