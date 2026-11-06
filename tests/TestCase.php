<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests share the real hishabiz_wallet database, so they may only use
     * DatabaseTransactions (every change is rolled back). Traits that drop,
     * migrate or truncate tables would destroy real data and are refused.
     *
     * The check runs before parent::setUp(), because that is where Laravel
     * would run the destructive trait.
     */
    protected function setUp(): void
    {
        static::assertNoDestructiveDatabaseTraits(static::class);

        parent::setUp();
    }

    /**
     * @param  class-string  $class
     *
     * @throws LogicException
     */
    public static function assertNoDestructiveDatabaseTraits(string $class): void
    {
        $forbidden = [RefreshDatabase::class, DatabaseMigrations::class, DatabaseTruncation::class];

        foreach (class_uses_recursive($class) as $trait) {
            if (in_array($trait, $forbidden, true)) {
                throw new LogicException($class.' uses '.class_basename($trait).', which could wipe the shared database. Use DatabaseTransactions.');
            }
        }
    }
}
