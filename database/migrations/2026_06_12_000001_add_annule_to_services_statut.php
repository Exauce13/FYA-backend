<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'statut')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE services MODIFY statut ENUM('en_attente', 'en_cours', 'terminer', 'annule') DEFAULT 'en_attente'");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE services DROP CONSTRAINT IF EXISTS services_statut_check");
            DB::statement("ALTER TABLE services ADD CONSTRAINT services_statut_check CHECK (statut IN ('en_attente', 'en_cours', 'terminer', 'annule'))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('services', 'statut')) {
            return;
        }

        DB::table('services')
            ->where('statut', 'annule')
            ->update(['statut' => 'en_attente']);

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE services MODIFY statut ENUM('en_attente', 'en_cours', 'terminer') DEFAULT 'en_attente'");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE services DROP CONSTRAINT IF EXISTS services_statut_check");
            DB::statement("ALTER TABLE services ADD CONSTRAINT services_statut_check CHECK (statut IN ('en_attente', 'en_cours', 'terminer'))");
        }
    }
};
