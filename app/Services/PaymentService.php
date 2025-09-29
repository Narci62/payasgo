<?php
namespace App\Services;

use App\Models\Payment;

class PaymentService
{
    public function store(array $data)
    {
        return Payment::create($data);
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
