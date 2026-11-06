<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class SavingsMovement extends Model
{
    use BelongsToUser;

    public $timestamps = false;

    protected $guarded = [];
}
