<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->timestamp('client_lu_at')->nullable()->after('devis');
            $table->timestamp('client_valide_at')->nullable()->after('client_lu_at');
            $table->timestamp('artisan_termine_at')->nullable()->after('client_valide_at');
            $table->timestamp('client_termine_at')->nullable()->after('artisan_termine_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'client_lu_at',
                'client_valide_at',
                'artisan_termine_at',
                'client_termine_at',
            ]);
        });
    }
};
