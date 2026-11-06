<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('wallet:prune-keys {--days=2 : Keep keys for this many days}')]
#[Description('Delete old form-submission keys (they only exist to ignore double taps)')]
class PruneIdempotencyKeys extends Command
{
    public function handle(): int
    {
        $deleted = DB::table('idempotency_keys')->where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();

        $this->components->info("Removed $deleted old keys.");

        return self::SUCCESS;
    }
}
