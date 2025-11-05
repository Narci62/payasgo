<?php

namespace App\Http\Resources;

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
        if (!$financingPlan || $financingPlan->status === 'paid_in_full') {
            return $this->formatActiveResponse($financingPlan);
        }

        // Vérifier si la date du prochain paiement est dépassée
        if (now()->greaterThan($financingPlan->next_payment_due_date)) {
            return $this->formatPaymentDueResponse($financingPlan);
        }

        // Vérifier si la date du période de grâce est dépassée
        if (now()->greaterThan($financingPlan->grace_period_ends_at)) {
            return $this->formatPaymentDueGracePeriodResponse($financingPlan);
        }

        // Si aucune des conditions ci-dessus n'est remplie, l'appareil est actif.
        return $this->formatActiveResponse($financingPlan);
    }

    /**
     * Formate la réponse pour un appareil actif.
     */
    protected function formatActiveResponse($financingPlan): array
    {
        $expiresAt = $financingPlan?->next_payment_due_date;
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
                'payment_instructions' => '*880*41*38761*'. (int) $this->financingPlan?->installment_amount .'*' . $this->client?->reference  .'#',
                'identifiant_client' => "Référence client : " . $this->client?->reference,
                'uninstall_code' =>  $this->financingPlan?->uninstall_code,

            ],
            'config' => [
                'check_interval_minutes' => 120, // Intervalle plus long
            ],
            'next_offline_unlock_code' => $financingPlan?->next_offline_unlock_code,
        ];
    }

    /**
     * Formate la réponse pour un appareil en retard de paiement.
     */
    protected function formatPaymentDueResponse($financingPlan): array
    {
        return [
            'status' => 'bloqué',
            'lock_required' => true,
            'lock_screen_info' => [
                'title' => 'Téléphone suspendu',
                'message' => 'Votre versement est en retard. Téléphone suspendu',
                'amount_due' => number_format($financingPlan->installment_amount, 0, ',', ' ') . ' FCFA',
                'payment_instructions' => '*880*41*38761*'. (int) $this->financingPlan?->installment_amount .'*' . $this->client?->reference  .'#',
                'payment_link' => env('PAYMENT_LINK', 'https://example.com/payment'),
                'support_phone_number' => '+229 01 76 65 65',
                'identifiant_client' => "Référence client : " . $this->client?->reference,
                'uninstall_code' =>  $this->financingPlan?->uninstall_code,

            ],
            'config' => [
                'check_interval_minutes' => 15, // Intervalle plus court pour débloquer rapidement
            ],
        ];
    }


    /**
     * Formate la réponse pour un appareil en retard de paiement.
     */
    protected function formatPaymentDueGracePeriodResponse($financingPlan): array
    {
        return [
            'status' => 'payment_due_grace_period',
            'lock_required' => true,
            'lock_screen_info' => [
                'title' => 'Téléphone suspendu',
                'message' => 'Votre versement est en retard et vous avez dépassé la période de grâce. Veuillez régler votre facture pour débloquer votre appareil.',
                'amount_due' => number_format($financingPlan->installment_amount, 0, ',', ' ') . ' FCFA',
                'payment_instructions' => '*880*41*38761*'. (int) $this->financingPlan?->installment_amount .'*' . $this->client?->reference  .'#',
                'payment_link' => env('PAYMENT_LINK', 'https://example.com/payment'),
                'support_phone_number' => '+229 01 76 65 65',
                'identifiant_client' => "Référence client : " . $this->client?->reference,
                'uninstall_code' =>  $this->financingPlan?->uninstall_code,

            ],
            'config' => [
                'check_interval_minutes' => 15, // Intervalle plus court pour débloquer rapidement
            ],
        ];
    }
}
