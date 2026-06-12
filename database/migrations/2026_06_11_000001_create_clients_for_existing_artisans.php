<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('artisans')
            ->select('user_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('clients')
                    ->whereColumn('clients.user_id', 'artisans.user_id');
            })
            ->orderBy('user_id')
            ->chunk(100, function ($artisans) use ($now): void {
                $clients = $artisans->map(fn ($artisan) => [
                    'user_id' => $artisan->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($clients) {
                    DB::table('clients')->insert($clients);
                }
            });
    }

    public function down(): void
    {
        // Les lignes clients peuvent avoir ete utilisees depuis; on ne les supprime pas automatiquement.
    }
};
