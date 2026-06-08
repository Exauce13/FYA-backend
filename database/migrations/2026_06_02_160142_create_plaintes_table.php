<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plaintes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plaignant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mise_en_cause_id')->constrained('users')->cascadeOnDelete();
            $table->string('motif');
            $table->text('description')->nullable();
            $table->enum('statut_plainte', ['en_attente', 'geree'])->default('en_attente');
            $table->timestamps();

            $table->index(['plaignant_id', 'mise_en_cause_id']);
            $table->index('statut_plainte');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plaintes');
    }
};
