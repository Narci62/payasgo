<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Financing_plan;
use App\Services\FinancingPlanService;
use App\Services\PenaltyService;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function __construct(protected FinancingPlanService $paymentService) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'financing_plan_id' => ['required', 'exists:financing_plans,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_id' => ['nullable', 'string'],
        ]);

        $plan = Financing_plan::findOrFail($validated['financing_plan_id']);

        // Calculer les pénalités si l'échéance est en retard
        $penaltyService = new PenaltyService;
        $currentInstallment = $plan->installments()
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->first();

        if ($currentInstallment && $currentInstallment->isOverdue()) {
            $penalty = $penaltyService->calculatePenalty($currentInstallment);
            $penaltyService->storePenalties($currentInstallment, $penalty);
        }

        $success = $this->paymentService->savePayment(
            $plan,
            $validated['amount'],
            'manual',
            $validated['transaction_id'] ?? null
        );

        if (! $success) {
            return response()->json(['message' => 'Le traitement du paiement a échoué.'], 500);
        }

        return response()->json([
            'message' => 'Paiement manuel enregistré avec succès.',
            'plan' => $plan->fresh(),
        ], 200);
    }
}
