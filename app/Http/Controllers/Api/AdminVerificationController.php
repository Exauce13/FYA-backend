<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminVerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $artisans = ArtisanModel::query()
            ->with(['user', 'metier'])
            ->where(function (Builder $query): void {
                $query->whereNotNull('piece_identites')
                    ->orWhereNotNull('diplome')
                    ->orWhereNotNull('nom_association');
            })
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $q = $request->string('q')->toString();
                $query->where(function (Builder $query) use ($q): void {
                    $query->where('nom_association', 'like', "%{$q}%")
                        ->orWhere('telephone_association', 'like', "%{$q}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$q}%")->orWhere('ville', 'like', "%{$q}%"))
                        ->orWhereHas('metier', fn (Builder $query) => $query->where('nom', 'like', "%{$q}%"));
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $status = strtolower($request->string('status')->toString());
                if (in_array($status, ['valide', 'validé', 'validated', 'certified'], true)) {
                    $query->where('is_certifed', true);
                }
                if (in_array($status, ['en_attente', 'attente', 'pending'], true)) {
                    $query->where('is_certifed', false);
                }
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $artisans->getCollection()->transform(fn (ArtisanModel $artisan) => $this->formatArtisan($artisan));

        return response()->json($artisans);
    }

    public function show(ArtisanModel $artisan): JsonResponse
    {
        $artisan->load(['user', 'metier']);

        return response()->json([
            'data' => $this->formatArtisan($artisan),
        ]);
    }

    public function validateVerification(ArtisanModel $artisan): JsonResponse
    {
        $artisan->update(['is_certifed' => true]);

        return response()->json([
            'message' => 'Verification artisan validee.',
            'data' => $this->formatArtisan($artisan->refresh()->load(['user', 'metier'])),
        ]);
    }

    public function cancelVerification(ArtisanModel $artisan): JsonResponse
    {
        $artisan->update(['is_certifed' => false]);

        return response()->json([
            'message' => 'Verification artisan annulee.',
            'data' => $this->formatArtisan($artisan->refresh()->load(['user', 'metier'])),
        ]);
    }

    public function downloadDocument(ArtisanModel $artisan, string $document)
    {
        $column = match ($document) {
            'cip', 'piece_identites', 'piece-identites' => 'piece_identites',
            'diplome' => 'diplome',
            default => null,
        };

        if (! $column || ! $artisan->{$column} || ! Storage::disk('public')->exists($artisan->{$column})) {
            abort(404, 'Document introuvable.');
        }

        return Storage::disk('public')->download($artisan->{$column});
    }

    private function formatArtisan(ArtisanModel $artisan): array
    {
        return [
            'id' => $artisan->id,
            'name' => $artisan->user?->name,
            'association' => $artisan->nom_association,
            'leader' => $artisan->user?->name,
            'leaderPhone' => $artisan->telephone_association ?: $artisan->user?->telephone,
            'trade' => $artisan->metier?->nom,
            'city' => $artisan->user?->ville,
            'documents' => collect([
                ['label' => 'CIP', 'path' => $artisan->piece_identites],
                ['label' => 'Diplome', 'path' => $artisan->diplome],
            ])->filter(fn ($document) => filled($document['path']))->map(fn ($document) => [
                'label' => $document['label'],
                'url' => Storage::url($document['path']),
            ])->values(),
            'status' => $artisan->is_certifed ? 'Valide' : 'En attente',
            'raw_status' => $artisan->is_certifed ? 'valide' : 'en_attente',
        ];
    }
}
