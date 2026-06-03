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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->enum('type', ['private', 'group'])->default('private'); # Distingue une conversation privée (1v1) d'un groupe
            $table->foreignId('user_1_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_2_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_1_id', 'user_2_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
