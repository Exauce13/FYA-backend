<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppelOffreModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminOfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $offers = AppelOffreModel::query()
            ->with(['user', 'metier'])
            ->withCount('candidatures')
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $q = $request->string('q')->toString();
                $query->where(function (Builder $query) use ($q): void {
                    $query->where('titre', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('metier', fn (Builder $query) => $query->where('nom', 'like', "%{$q}%"));
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $this->statusValue($request->string('status')->toString())))
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('metier', fn (Builder $query) => $query->where('nom', $request->string('category')->toString())))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $offers->getCollection()->transform(fn (AppelOffreModel $offer) => $this->formatOffer($offer));

        return response()->json($offers);
    }

    public function show(AppelOffreModel $appelOffre): JsonResponse
    {
        $appelOffre->load(['user', 'metier', 'candidatures.artisan.user'])->loadCount('candidatures');

        return response()->json([
            'data' => $this->formatOffer($appelOffre) + [
                'description' => $appelOffre->description,
                'city' => $appelOffre->ville,
                'created_at' => $appelOffre->created_at?->toDateString(),
                'candidatures' => $appelOffre->candidatures,
            ],
        ]);
    }

    public function destroy(AppelOffreModel $appelOffre): JsonResponse
    {
        $appelOffre->delete();

        return response()->json([
            'message' => 'Appel d offres supprime avec succes.',
        ]);
    }

    private function formatOffer(AppelOffreModel $offer): array
    {
        $media = is_array($offer->appel_json) ? ($offer->appel_json[0] ?? null) : null;

        return [
            'id' => $offer->id,
            'title' => $offer->titre,
            'category' => $offer->metier?->nom,
            'owner' => $offer->user?->name,
            'budget' => $offer->budget,
            'proposals' => (int) ($offer->candidatures_count ?? 0),
            'status' => $this->statusLabel($offer->status),
            'raw_status' => $offer->status,
            'image' => $media ? Storage::url($media) : null,
        ];
    }

    private function statusValue(string $status): string
    {
        return match (strtolower($status)) {
            'ouvert', 'open' => 'open',
            'termine', 'ferme', 'closed' => 'closed',
            default => $status,
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'closed' => 'Termine',
            default => 'Ouvert',
        };
    }
}
