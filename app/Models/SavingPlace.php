<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class SavingPlace extends Model
{
    use BelongsToUser;

    protected $guarded = [];
}
