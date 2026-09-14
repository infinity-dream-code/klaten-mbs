<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class mst_sekolah extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "mst_sekolah";

    protected $primaryKey = "urut";

    public $timestamps = false;

    public $incrementing = false;
}
