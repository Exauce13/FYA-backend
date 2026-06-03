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
        Schema::create('artisans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->enum('metiers', ['Plombier', 'Electricien', 'Menuisier', 'Peintre', 'Carreleur']);
            $table->string('bio');
            $table->string('npi');
            $table->string('annees_experiences');
            $table->string('nom_association')->nullable();
            $table->string('telephone_association')->nullable();
            $table->string('diplome')->nullable();
            $table->boolean('is_certifed')->default(false);
            $table->boolean('is_boost')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void

    {
        Schema::dropIfExists('artisans');
    }
};
