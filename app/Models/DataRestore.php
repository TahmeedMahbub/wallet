<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class DataRestore extends Model
{
    use BelongsToUser;

    protected $guarded = [];
}
