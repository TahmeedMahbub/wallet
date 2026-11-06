<?php

namespace App\Models;

use App\Enums\DueDirection;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Due extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['direction' => DueDirection::class, 'due_date' => 'date'];
    }
}
