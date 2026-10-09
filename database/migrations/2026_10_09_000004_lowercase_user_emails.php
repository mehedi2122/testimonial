<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Login lowercases the typed email (fortify.lowercase_usernames), so a
 * stored mixed-case email can never log in on SQLite. Lowercase them,
 * skipping any that would collide with an existing account.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->select(['id', 'email'])->get();
        $taken = $users->pluck('email')->map(fn ($e) => mb_strtolower($e))->countBy();

        foreach ($users as $user) {
            $lower = mb_strtolower(trim($user->email));

            if ($lower === $user->email) {
                continue;
            }

            if (($taken[$lower] ?? 0) > 1) {
                Log::warning('Not lowercasing user email: another account uses it in a different case.', ['user_id' => $user->id]);

                continue;
            }

            DB::table('users')->where('id', $user->id)->update(['email' => $lower]);
        }
    }

    public function down(): void
    {
        // Irreversible: original casing is not kept.
    }
};
