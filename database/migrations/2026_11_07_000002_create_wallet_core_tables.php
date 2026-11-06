<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal finance tables. Money is stored as integer poisha (1 taka = 100).
 *
 * Every table has a NOT NULL user_id. Child tables reference their parents with
 * composite foreign keys (parent_id, user_id), so the database itself refuses a
 * row that points at another user's record.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name', 120);
            $table->string('type', 20);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedTinyInteger('active_key')->nullable()->storedAs('if(`deleted_at` is null, 1, null)');

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'name', 'active_key']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedTinyInteger('active_key')->nullable()->storedAs('if(`deleted_at` is null, 1, null)');

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'name', 'active_key']);
        });

        Schema::create('income_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name', 120);
            $table->boolean('is_salary')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedTinyInteger('active_key')->nullable()->storedAs('if(`deleted_at` is null, 1, null)');

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'name', 'active_key']);
        });

        Schema::create('category_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('monthly_limit');
            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
            $table->foreign(['category_id', 'user_id'])->references(['id', 'user_id'])->on('categories');
        });

        Schema::create('saving_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'name']);
        });

        Schema::create('saving_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'name']);
        });

        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('reason_id');
            $table->unsignedBigInteger('place_id');
            $table->string('kind', 10);
            $table->unsignedBigInteger('target_amount');
            $table->unsignedSmallInteger('months')->nullable();
            $table->unsignedBigInteger('monthly_amount');
            $table->boolean('auto_save')->default(true);
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->foreign(['reason_id', 'user_id'])->references(['id', 'user_id'])->on('saving_reasons');
            $table->foreign(['place_id', 'user_id'])->references(['id', 'user_id'])->on('saving_places');
        });

        Schema::create('dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('direction', 10);
            $table->string('person', 120);
            $table->unsignedBigInteger('amount');
            $table->string('note', 255)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->index(['user_id', 'direction']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 24);
            $table->unsignedBigInteger('amount');
            $table->dateTime('occurred_at');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('to_account_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('income_source_id')->nullable();
            $table->unsignedBigInteger('due_id')->nullable();
            $table->unsignedBigInteger('parent_transaction_id')->nullable();
            $table->string('area', 255)->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('auto_save_status', 24)->nullable();
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'type', 'occurred_at']);
            $table->index(['account_id', 'occurred_at']);
            $table->foreign(['account_id', 'user_id'])->references(['id', 'user_id'])->on('accounts');
            $table->foreign(['to_account_id', 'user_id'])->references(['id', 'user_id'])->on('accounts');
            $table->foreign(['category_id', 'user_id'])->references(['id', 'user_id'])->on('categories');
            $table->foreign(['income_source_id', 'user_id'])->references(['id', 'user_id'])->on('income_sources');
            $table->foreign(['due_id', 'user_id'])->references(['id', 'user_id'])->on('dues');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign(['parent_transaction_id', 'user_id'])->references(['id', 'user_id'])->on('transactions');
        });

        Schema::create('ledger_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('account_id');
            $table->bigInteger('delta');

            $table->index(['account_id', 'transaction_id']);
            $table->index(['transaction_id']);
            $table->foreign(['transaction_id', 'user_id'])->references(['id', 'user_id'])->on('transactions');
            $table->foreign(['account_id', 'user_id'])->references(['id', 'user_id'])->on('accounts');
        });

        Schema::create('savings_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('goal_id');
            $table->unsignedBigInteger('transaction_id');
            $table->bigInteger('amount');

            $table->index(['goal_id']);
            $table->index(['transaction_id']);
            $table->foreign(['goal_id', 'user_id'])->references(['id', 'user_id'])->on('savings_goals');
            $table->foreign(['transaction_id', 'user_id'])->references(['id', 'user_id'])->on('transactions');
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('key', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'key']);
            $table->index('created_at');
        });

        Schema::create('data_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 20);
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedInteger('entries')->default(0);
            $table->string('failure', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations. Child tables are dropped before their parents.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_restores');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('savings_movements');
        Schema::dropIfExists('ledger_postings');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('dues');
        Schema::dropIfExists('savings_goals');
        Schema::dropIfExists('saving_places');
        Schema::dropIfExists('saving_reasons');
        Schema::dropIfExists('category_budgets');
        Schema::dropIfExists('income_sources');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('accounts');
    }
};
