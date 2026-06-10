<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanModel;
use App\Models\MetierModel;
use App\Models\PaymentModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        $registrations = User::query()
            ->selectRaw('MONTH(created_at) as month_number, COUNT(*) as value')
            ->whereYear('created_at', now()->year)
            ->groupBy('month_number')
            ->orderBy('month_number')
            ->get()
            ->map(fn ($row) => [
                'month' => $this->monthLabel((int) $row->month_number),
                'value' => (int) $row->value,
            ]);

        $usersWithCity = max(1, User::query()->whereNotNull('ville')->count());
        $cityShare = User::query()
            ->select('ville', DB::raw('COUNT(*) as total'))
            ->whereNotNull('ville')
            ->groupBy('ville')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'city' => $row->ville,
                'value' => (int) round(((int) $row->total / $usersWithCity) * 100),
            ]);

        $categoryActivity = MetierModel::query()
            ->withCount(['artisans', 'appelsOffres'])
            ->orderBy('nom')
            ->get()
            ->map(fn (MetierModel $metier) => [
                'name' => $metier->nom,
                'artisans' => (int) $metier->artisans_count,
                'offers' => (int) $metier->appels_offres_count,
            ]);

        return response()->json([
            'stats' => [
                'users' => User::query()->count(),
                'artisans' => ArtisanModel::query()->count(),
                'turnover' => (int) PaymentModel::query()->where('statut', 'paid')->sum('montant'),
            ],
            'registrations' => $registrations,
            'city_share' => $cityShare,
            'category_activity' => $categoryActivity,
        ]);
    }

    private function monthLabel(int $month): string
    {
        return [
            1 => 'Jan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juil',
            8 => 'Aout',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ][$month] ?? (string) $month;
    }
}
