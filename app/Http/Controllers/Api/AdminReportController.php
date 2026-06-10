<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plainte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = Plainte::query()
            ->with(['plaignant', 'miseEnCause'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $q = $request->string('q')->toString();
                $query->where(function (Builder $query) use ($q): void {
                    $query->where('motif', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhereHas('plaignant', fn (Builder $query) => $query->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('miseEnCause', fn (Builder $query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('admin_status', $this->statusValue($request->string('status')->toString())))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $reports->getCollection()->transform(fn (Plainte $plainte) => $this->formatReport($plainte));

        return response()->json($reports);
    }

    public function show(Plainte $plainte): JsonResponse
    {
        $plainte->load(['plaignant', 'miseEnCause']);

        return response()->json([
            'data' => $this->formatReport($plainte),
        ]);
    }

    public function markAsTreated(Plainte $plainte): JsonResponse
    {
        $plainte->update([
            'statut_plainte' => Plainte::STATUT_GEREE,
            'admin_status' => 'traite',
        ]);

        return response()->json([
            'message' => 'Signalement marque comme traite.',
            'data' => $this->formatReport($plainte->refresh()->load(['plaignant', 'miseEnCause'])),
        ]);
    }

    public function ignore(Plainte $plainte): JsonResponse
    {
        $plainte->update([
            'statut_plainte' => Plainte::STATUT_GEREE,
            'admin_status' => 'ignore',
        ]);

        return response()->json([
            'message' => 'Signalement ignore.',
            'data' => $this->formatReport($plainte->refresh()->load(['plaignant', 'miseEnCause'])),
        ]);
    }

    private function formatReport(Plainte $plainte): array
    {
        $status = $plainte->admin_status ?? $plainte->statut_plainte;

        return [
            'id' => 'REP-' . $plainte->id,
            'reported_by' => $plainte->plaignant?->name,
            'target' => $plainte->miseEnCause?->name,
            'reason' => $plainte->motif,
            'description' => $plainte->description,
            'status' => $this->statusLabel($status),
            'raw_status' => $status,
        ];
    }

    private function statusValue(string $status): string
    {
        return match (strtolower($status)) {
            'en attente', 'en_attente', 'pending' => 'en_attente',
            'traite', 'treated', 'geree' => 'traite',
            'ignore', 'ignored' => 'ignore',
            default => $status,
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'traite' => 'Traite',
            'ignore' => 'Ignore',
            default => 'En attente',
        };
    }
}
