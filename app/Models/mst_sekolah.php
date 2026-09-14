<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class mst_sekolah extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "mst_sekolah";

    protected $primaryKey = "urut";

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    public static function nextCode01(): string
    {
        $max = (int) static::query()->max(\Illuminate\Support\Facades\DB::raw('CAST(CODE01 AS UNSIGNED)'));

        return (string) ($max + 1);
    }

    public static function firstOrCreateByUnitName(string $name): self
    {
        $name = trim($name);
        $existing = static::query()
            ->where(function ($query) use ($name) {
                $query->whereRaw('UPPER(TRIM(DESC01)) = ?', [strtoupper($name)])
                    ->orWhereRaw('UPPER(TRIM(CODE01)) = ?', [strtoupper($name)]);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $model = new static();
        $columns = Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());
        if (!in_array($model->getKeyName(), $columns, true) && in_array('id', $columns, true)) {
            $model->setKeyName('id');
        }

        foreach (array_unique([$model->getKeyName(), 'id', 'urut']) as $keyColumn) {
            if (!in_array($keyColumn, $columns, true) || !blank($model->{$keyColumn} ?? null)) {
                continue;
            }
            $model->{$keyColumn} = ((int) static::query()->max($keyColumn)) + 1;
        }

        $model->CODE01 = static::nextCode01();
        $model->DESC01 = $name;
        $model->save();

        return $model->fresh() ?? $model;
    }
}
