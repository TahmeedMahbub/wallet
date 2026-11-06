<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class SavingReason extends Model
{
    use BelongsToUser;

    protected $guarded = [];
}
