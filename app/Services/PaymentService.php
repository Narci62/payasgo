<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Enregistre un paiement avec protection contre les doublons.
     *
     * @return array{success: bool, payment: Payment|null, duplicate: bool}
     */
    public function store(array $data): array
    {
        $existingPayment = $this->findByTransactionId($data['transaction_id']);

        if ($existingPayment) {
            Log::warning('Tentative de double paiement détectée', [
                'transaction_id' => $data['transaction_id'],
                'existing_status' => $existingPayment->status,
            ]);

            return [
                'success' => true,
                'payment' => $existingPayment,
                'duplicate' => true,
            ];
        }

        $payment = Payment::create($data);

        return [
            'success' => true,
            'payment' => $payment,
            'duplicate' => false,
        ];
    }

    public function findByTransactionId($transactionId)
    {
        return Payment::where('transaction_id', $transactionId)->first();
    }

    public function updateStatus($transactionId, $status)
    {
        $payment = $this->findByTransactionId($transactionId);
        if ($payment) {
            $payment->status = $status;
            $payment->save();
        }

        return $payment;
    }
}
