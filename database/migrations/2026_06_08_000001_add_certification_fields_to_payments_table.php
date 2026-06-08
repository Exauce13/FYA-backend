<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('local_reference')->nullable()->unique()->after('fedapay_transaction_id');
            $table->text('payment_url')->nullable()->after('statut');
            $table->json('certification_payload')->nullable()->after('payment_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['local_reference']);
            $table->dropColumn(['local_reference', 'payment_url', 'certification_payload']);
        });
    }
};
