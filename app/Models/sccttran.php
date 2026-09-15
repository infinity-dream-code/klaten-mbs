<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class sccttran extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "sccttran";

    protected $primaryKey = "urut";

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        "CUSTID",
        "METODE",
        "TRXDATE",
        "NOREFF",
        "FIDBANK",
        "KDCHANNEL",
        "DEBET",
        "KREDIT",
        "REFFBANK",
        "TRANSNO",
        "BILLID",
        "BILLTARGET",
        "INSTALLMENT",
        "isreversal",
    ];

    public static function hasIsReversalColumn(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::connection((new static())->getConnectionName())
                ->hasColumn((new static())->getTable(), 'isreversal');
        }

        return $exists;
    }

    public static function logsGroupedByBillId(array $billIds): array
    {
        $billIds = array_values(array_unique(array_filter(
            $billIds,
            static fn ($id) => $id !== null && $id !== ''
        )));
        if ($billIds === []) {
            return [];
        }

        $grouped = [];
        foreach (array_chunk($billIds, 1000) as $chunk) {
            $rows = static::query()
                ->whereIn('BILLID', $chunk)
                ->orderBy('TRXDATE', 'desc')
                ->get(['BILLID', 'TRXDATE', 'METODE', 'DEBET', 'KREDIT', 'FIDBANK', 'NOREFF', 'TRANSNO']);

            foreach ($rows as $trx) {
                $billId = (string) $trx->BILLID;
                $grouped[$billId][] = [
                    'trxdate' => $trx->TRXDATE
                        ? Carbon::parse($trx->TRXDATE)->format('d-m-Y H:i:s')
                        : null,
                    'metode' => $trx->METODE,
                    'debet' => (int) ($trx->DEBET ?? 0),
                    'kredit' => (int) ($trx->KREDIT ?? 0),
                    'fidbank' => $trx->FIDBANK,
                    'noreff' => $trx->NOREFF,
                    'transno' => $trx->TRANSNO,
                ];
            }
        }

        return $grouped;
    }

    public static function applyNotReversedScope($query, string $column = 'sccttran.isreversal')
    {
        if (!self::hasIsReversalColumn()) {
            return $query;
        }

        return $query->where(function ($q) use ($column) {
            $q->whereNull($column)
                ->orWhere($column, 0)
                ->orWhere($column, '0');
        });
    }
}
