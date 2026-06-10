<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = $this->filteredQuery($request)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $payments->getCollection()->transform(fn (PaymentModel $payment) => $this->formatPayment($payment));

        return response()->json($payments);
    }

    public function show(PaymentModel $payment): JsonResponse
    {
        $payment->load('artisan.user');

        return response()->json([
            'data' => $this->formatPayment($payment) + [
                'fedapay_transaction_id' => $payment->fedapay_transaction_id,
                'local_reference' => $payment->local_reference,
                'payment_url' => $payment->payment_url,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
            ],
        ]);
    }

    public function downloadReceipt(PaymentModel $payment): StreamedResponse
    {
        $payment->load('artisan.user');
        $filename = ($payment->local_reference ?: 'PAY-' . $payment->id) . '-receipt.txt';

        return response()->streamDownload(function () use ($payment): void {
            echo "Recu de paiement\n";
            echo "Reference: " . ($payment->local_reference ?: 'PAY-' . $payment->id) . "\n";
            echo "Utilisateur: " . ($payment->artisan?->user?->name ?? 'N/A') . "\n";
            echo "Type: " . $this->typeLabel($payment) . "\n";
            echo "Montant: " . (int) $payment->montant . " FCFA\n";
            echo "Provider: FedaPay\n";
            echo "Statut: " . $this->statusLabel($payment->statut) . "\n";
            echo "Date: " . ($payment->paid_at?->toDateString() ?: $payment->created_at?->toDateString()) . "\n";
        }, $filename, ['Content-Type' => 'text/plain']);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'payments-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'user', 'type', 'amount', 'provider', 'status', 'date']);

            $this->filteredQuery($request)
                ->orderByDesc('created_at')
                ->chunk(200, function ($payments) use ($handle): void {
                    foreach ($payments as $payment) {
                        $formatted = $this->formatPayment($payment);
                        fputcsv($handle, [
                            $formatted['id'],
                            $formatted['user'],
                            $formatted['type'],
                            $formatted['amount'],
                            $formatted['provider'],
                            $formatted['status'],
                            $formatted['date'],
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request): Builder
    {
        return PaymentModel::query()
            ->with('artisan.user')
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $q = $request->string('q')->toString();
                $query->where(function (Builder $query) use ($q): void {
                    $query->where('fedapay_transaction_id', 'like', "%{$q}%")
                        ->orWhere('local_reference', 'like', "%{$q}%")
                        ->orWhereHas('artisan.user', fn (Builder $query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $this->typeValue($request->string('type')->toString())))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('statut', $this->statusValue($request->string('status')->toString())));
    }

    private function formatPayment(PaymentModel $payment): array
    {
        return [
            'id' => $payment->local_reference ?: 'PAY-' . $payment->id,
            'user' => $payment->artisan?->user?->name,
            'type' => $this->typeLabel($payment),
            'raw_type' => $payment->type,
            'amount' => (int) $payment->montant,
            'provider' => 'FedaPay',
            'status' => $this->statusLabel($payment->statut),
            'raw_status' => $payment->statut,
            'date' => ($payment->paid_at ?: $payment->created_at)?->toDateString(),
        ];
    }

    private function typeValue(string $type): string
    {
        return match (strtolower($type)) {
            'abonnement', 'badge' => 'badge',
            'renouvellement', 'boost' => 'boost',
            default => $type,
        };
    }

    private function typeLabel(PaymentModel $payment): string
    {
        if ((int) $payment->montant === 500 || $payment->type === 'boost') {
            return 'Renouvellement';
        }

        return 'Abonnement';
    }

    private function statusValue(string $status): string
    {
        return match (strtolower($status)) {
            'paye', 'paid' => 'paid',
            'en_attente', 'pending' => 'pending',
            'annule', 'cancelled' => 'cancelled',
            'echoue', 'failed' => 'failed',
            default => $status,
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => 'Paye',
            'failed' => 'Echoue',
            'cancelled' => 'Annule',
            default => 'En attente',
        };
    }
}
