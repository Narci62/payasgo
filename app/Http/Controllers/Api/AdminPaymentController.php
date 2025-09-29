<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Financing_plan;
use App\Services\FinancingPlanService;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function __construct(protected FinancingPlanService $paymentService) {}

    public function store(Request $request)
    {
        // 1. Valider la requête de l'admin
        $validated = $request->validate([
            'financing_plan_id' => ['required', 'exists:financing_plans,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_id' => ['nullable', 'string'], // Référence manuelle optionnelle
        ]);

        // 2. Trouver le plan
        $plan = Financing_plan::findOrFail($validated['financing_plan_id']);

        // 3. Appeler LE MÊME service de paiement !
        $success = $this->paymentService->savePayment(
            $plan,
            $validated['amount'],
            'manual', // La méthode est 'manual'
            $validated['transaction_id'] ?? null
        );

        if (!$success) {
            return response()->json(['message' => 'Le traitement du paiement a échoué.'], 500);
        }

        return response()->json([
            'message' => 'Paiement manuel enregistré avec succès.',
            'plan' => $plan->fresh(), // Renvoyer le plan mis à jour
        ], 200);
    }
}
