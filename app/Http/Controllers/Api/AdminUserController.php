<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['artisan.metier', 'client'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $q = $request->string('q')->toString();
                $query->where(function (Builder $query) use ($q): void {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('telephone', 'like', "%{$q}%")
                        ->orWhere('ville', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('role'), fn (Builder $query) => $query->where('statut', $this->roleValue($request->string('role')->toString())))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $this->statusValue($request->string('status')->toString())))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $users->getCollection()->transform(fn (User $user) => $this->formatUser($user));

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['artisan.metier', 'client', 'appeloffres']);

        return response()->json([
            'data' => $this->formatUser($user) + [
                'email' => $user->email,
                'phone' => $user->telephone,
                'quarter' => $user->quartier,
                'offers_count' => $user->appeloffres->count(),
                'artisan' => $user->artisan,
                'client' => $user->client,
            ],
        ]);
    }

    public function suspend(User $user): JsonResponse
    {
        $user->update(['status' => 'suspendu']);

        return response()->json([
            'message' => 'Utilisateur suspendu avec succes.',
            'data' => $this->formatUser($user->refresh()),
        ]);
    }

    public function activate(User $user): JsonResponse
    {
        $user->update(['status' => 'actif']);

        return response()->json([
            'message' => 'Utilisateur active avec succes.',
            'data' => $this->formatUser($user->refresh()),
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $this->roleLabel($user->statut),
            'raw_role' => $user->statut,
            'city' => $user->ville,
            'status' => $this->statusLabel($user->status ?? 'actif'),
            'raw_status' => $user->status ?? 'actif',
            'joined' => $user->created_at?->toDateString(),
            'avatar' => $user->photo ? Storage::url($user->photo) : null,
        ];
    }

    private function roleValue(string $role): string
    {
        return match (strtolower($role)) {
            'artisan', 'artisans' => 'artisans',
            'client', 'clients' => 'clients',
            'admin' => 'admin',
            default => $role,
        };
    }

    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'artisans' => 'Artisan',
            'clients' => 'Client',
            'admin' => 'Admin',
            default => ucfirst((string) $role),
        };
    }

    private function statusValue(string $status): string
    {
        return match (strtolower($status)) {
            'actif', 'active' => 'actif',
            'suspendu', 'suspended' => 'suspendu',
            default => $status,
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'suspendu' => 'Suspendu',
            default => 'Actif',
        };
    }
}
