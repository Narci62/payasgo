<?php

namespace App\Http\Resources;

use App\Services\PenaltyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $financingPlan = $this->financingPlan;

        // Si le plan n'existe pas ou est déjà entièrement payé, tout est OK.
        if (! $financingPlan || $financingPlan->getRawOriginal('status') === 'paid_in_full') {
            return $this->formatActiveResponse($financingPlan);
        }

        // Vérifier si l'échéance courante est en retard via la table installments
        $penaltyService = new PenaltyService;
        if ($penaltyService->isCurrentInstallmentOverdue($financingPlan)) {
            return $this->formatPaymentDueResponse($financingPlan, $penaltyService);
        }

        // Si aucune des conditions ci-dessus n'est remplie, l'appareil est actif.
        return $this->formatActiveResponse($financingPlan);
    }

    /**
     * Formate la réponse pour un appareil actif.
     */
    protected function formatActiveResponse($financingPlan): array
    {
        $expiresAt = Carbon::parse($financingPlan?->next_payment_due_date)->format('d-m-Y H:i:s');
        $gracePeriodEndsAt = $financingPlan?->grace_period_ends_at;

        return [
            'status' => 'active',
            'lock_required' => false,
            'subscription' => [
                'expires_at' => $expiresAt,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'next_offline_unlock_code' => $this->financingPlan?->next_offline_unlock_code,
                'amount_paid' => $this->financingPlan?->total_price - $this->financingPlan?->remaining_balance,
                'amount_remaining' => $this->financingPlan?->remaining_balance,
                'payment_instructions' => '*880*41*38761*'.(int) $this->financingPlan?->installment_amount.'*'.$this->client?->reference.'#',
                'identifiant_client' => 'Référence client : '.$this->client?->reference,
                'uninstall_code' => $this->financingPlan?->uninstall_code,

            ],
            'config' => [
                'check_interval_minutes' => 120,
            ],
            'next_offline_unlock_code' => $financingPlan?->next_offline_unlock_code,
        ];
    }

    /**
     * Formate la réponse pour un appareil en retard de paiement.
     */
    protected function formatPaymentDueResponse($financingPlan, PenaltyService $penaltyService): array
    {
        // Trouver l'échéance courante pour calculer le total dû
        $currentInstallment = $financingPlan->installments()
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->first();

        $totalDue = $currentInstallment
            ? $penaltyService->getTotalDue($currentInstallment)
            : $financingPlan->installment_amount;

        $daysLate = $currentInstallment ? $currentInstallment->getDaysLate() : 0;

        return [
            'status' => 'bloqué',
            'lock_required' => true,
            'lock_screen_info' => [
                'title' => 'Téléphone suspendu',
                'message' => 'Votre versement est en retard. Téléphone suspendu',
                'days_late' => $daysLate,
                'amount_due' => number_format($totalDue, 0, ',', ' ').' FCFA',
                'installment_amount' => number_format($financingPlan->installment_amount, 0, ',', ' ').' FCFA',
                'payment_instructions' => '*880*41*38761*'.(int) $this->financingPlan?->installment_amount.'*'.$this->client?->reference.'#',
                'payment_link' => env('PAYMENT_LINK', 'https://example.com/payment'),
                'support_phone_number' => '+229 01 76 65 65',
                'identifiant_client' => 'Référence client : '.$this->client?->reference,
                'uninstall_code' => $this->financingPlan?->uninstall_code,

            ],
            'config' => [
                'check_interval_minutes' => 15,
            ],
        ];
    }
}
