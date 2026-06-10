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

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->query()
            ->where('local_reference', $value)
            ->orWhere('fedapay_transaction_id', $value);

        if ($field) {
            $query->orWhere($field, $value);
        } elseif (ctype_digit((string) $value)) {
            $query->orWhere($this->getRouteKeyName(), (int) $value);
        }

        if (str_starts_with((string) $value, 'PAY-')) {
            $numericId = substr((string) $value, 4);

            if (ctype_digit($numericId)) {
                $query->orWhere('id', (int) $numericId);
            }
        }

        return $query->first();
    }
}
