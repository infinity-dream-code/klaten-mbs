<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
