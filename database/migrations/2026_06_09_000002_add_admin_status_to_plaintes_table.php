<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plaintes', function (Blueprint $table) {
            $table->string('admin_status')->default('en_attente')->after('statut_plainte');
        });
    }

    public function down(): void
    {
        Schema::table('plaintes', function (Blueprint $table) {
            $table->dropColumn('admin_status');
        });
    }
};
