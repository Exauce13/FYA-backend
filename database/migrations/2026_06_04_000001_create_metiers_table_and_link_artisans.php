<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metiers', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->timestamps();
        });

        $now = now();
        $metiers = [
            'Maçonnerie',
            'Plomberie',
            'Électricité',
            'Menuiserie',
            'Couture',
            'Soudure',
            'Peinture',
            'Tapisserie',
            'Mécanique',
            'Climatisation et Froid',
            'Coiffure et Esthétique',
            'Informatique et Réparation électronique',
            'Décoration et Événementiel',
            'Artisanat d\'art',
            'Transformation agroalimentaire',
        ];

        foreach ($metiers as $metier) {
            DB::table('metiers')->updateOrInsert(
                ['nom' => $metier],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $metierIdsByName = DB::table('metiers')->pluck('id', 'nom');

        if (Schema::hasTable('artisans')) {
            if (Schema::hasColumn('artisans', 'metiers')) {
                Schema::table('artisans', function (Blueprint $table) {
                    $table->unsignedBigInteger('metier_id_tmp')->nullable()->after('user_id');
                });

                $legacyMap = [
                    'Plombier' => 'Plomberie',
                    'Electricien' => 'Électricité',
                    'Menuisier' => 'Menuiserie',
                    'Peintre' => 'Peinture',
                    'Carreleur' => 'Maçonnerie',
                ];

                DB::table('artisans')->orderBy('id')->chunkById(100, function ($artisans) use ($metierIdsByName, $legacyMap) {
                    foreach ($artisans as $artisan) {
                        $legacyName = is_string($artisan->metiers) ? trim($artisan->metiers) : null;
                        $targetName = $legacyMap[$legacyName] ?? $legacyName;
                        $metierId = $targetName !== null ? ($metierIdsByName[$targetName] ?? null) : null;

                        if ($metierId === null) {
                            continue;
                        }

                        DB::table('artisans')
                            ->where('id', $artisan->id)
                            ->update(['metier_id_tmp' => $metierId]);
                    }
                });

                Schema::table('artisans', function (Blueprint $table) {
                    $table->dropColumn('metiers');
                });

                DB::statement('ALTER TABLE artisans CHANGE metier_id_tmp metier_id BIGINT UNSIGNED NULL');
            }

            if (Schema::hasColumn('artisans', 'metier_id')) {
                Schema::table('artisans', function (Blueprint $table) {
                    $table->foreign('metier_id')->references('id')->on('metiers')->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('appels_offres') && Schema::hasColumn('appels_offres', 'metier_id')) {
            Schema::table('appels_offres', function (Blueprint $table) {
                $table->foreign('metier_id')->references('id')->on('metiers')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('artisans')) {
            if (Schema::hasColumn('artisans', 'metier_id')) {
                Schema::table('artisans', function (Blueprint $table) {
                    $table->dropForeign(['metier_id']);
                });
            }

            if (Schema::hasColumn('artisans', 'metiers')) {
                Schema::table('artisans', function (Blueprint $table) {
                    $table->dropForeign(['metiers']);
                });
            }
        }

        if (Schema::hasTable('appels_offres') && Schema::hasColumn('appels_offres', 'metier_id')) {
            Schema::table('appels_offres', function (Blueprint $table) {
                $table->dropForeign(['metier_id']);
            });
        }

        Schema::dropIfExists('metiers');
    }
};
