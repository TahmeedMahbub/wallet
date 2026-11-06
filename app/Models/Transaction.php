<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => TransactionType::class, 'occurred_at' => 'datetime', 'amount' => 'integer'];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * @return BelongsTo<IncomeSource, $this>
     */
    public function incomeSource(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Due, $this>
     */
    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }

    /**
     * @return HasMany<LedgerPosting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(LedgerPosting::class);
    }

    /**
     * @return HasMany<SavingsMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(SavingsMovement::class);
    }
}
