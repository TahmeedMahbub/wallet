<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the login username. Safe to run where the column was already added by hand:
     * existing blank usernames are filled from the email name, then the column is made unique.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->after('name');
            });
        }

        $taken = DB::table('users')->where('username', '!=', '')->pluck('username')->map(fn ($name) => strtolower($name))->all();

        foreach (DB::table('users')->where('username', '')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = substr(preg_replace('/[^a-z0-9_.]/', '', strtolower(strstr($user->email, '@', true))) ?: 'user', 0, 24);
            $username = strlen($base) >= 3 ? $base : $base.'user';

            if (in_array($username, $taken, true)) {
                $username .= $user->id;
            }

            $taken[] = $username;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        $hasUnique = collect(Schema::getIndexes('users'))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['username']);

        if (! $hasUnique) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
