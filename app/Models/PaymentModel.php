<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentModel extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'artisan_id',
        'fedapay_transaction_id',
        'local_reference',
        'type_evenement',
        'montant',
        'type',
        'statut',
        'paid_at',
        'payment_url',
        'certification_payload',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'paid_at' => 'datetime',
            'certification_payload' => 'array',
        ];
    }

    public function artisan()
    {
        return $this->belongsTo(ArtisanModel::class, 'artisan_id');
    }
}
