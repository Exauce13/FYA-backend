<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetierModel;
use Illuminate\Http\JsonResponse;

class MetierController extends Controller
{
    public function listemetiers(): JsonResponse
    {
        $metiers = MetierModel::query()->orderBy('nom')->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des métiers récupérée avec succès.',
            'metiers' => $metiers,
        ]);
    }
}
