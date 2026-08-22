<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Financing_plan;
use Carbon\Carbon;

class UserController extends Controller
{
    public function show($token)
    {
        // Valider le token
        try {
            $user = $this->getUserFromToken($token);
        } catch (\Exception $e) {
            return abort(404, $e->getMessage());
        }

        if ($token == '44750') {
            return to_route('compliance.show', ['imat' => $token]);
        }

        // Calculer les données
        $data = [
            'employee_name' => $user->employee_name,
            'reference' => $token,
            'device_model' => $user->device_model,
            'total_price' => number_format($user->total_price, 0, ',', ' '),
            'paid_amount' => number_format($user->paid_amount, 0, ',', ' '),
            'remaining_amount' => number_format($user->remaining_amount, 0, ',', ' '),
            'compliance_percentage' => round(($user->paid_amount / $user->total_price) * 100),
            'due_date' => $user->due_date,
            'compliance_status' => $this->getComplianceStatus($user),
        ];

        return view('user.information', $data);
    }

    public function compliance($imat)
    {
        // Valider l'imat
        try {
            $user = $this->getUserFromToken($imat);
        } catch (\Exception $e) {
            return abort(404, $e->getMessage());
        }

        // Calculer les données
        $data = [
            'device_id' => $user->device_id,
            'last_sync' => $user->last_sync,
            'next_check' => $user->due_date,
            'department' => 'S',
            'employee_name' => $user->employee_name,
            'reference' => $imat,
            'device_model' => $user->device_model,
            'total_price' => number_format($user->total_price, 0, ',', ' '),
            'paid_amount' => number_format($user->paid_amount, 0, ',', ' '),
            'remaining_amount' => number_format($user->remaining_amount, 0, ',', ' '),
            'compliance_percentage' => round(($user->paid_amount / $user->total_price) * 100),
            'due_date' => $user->due_date,
            'compliance_status' => $this->getComplianceStatus($user),
        ];

        return view('payment.no-payment', $data);
    }

    private function getUserFromToken($token)
    {
        $client = Client::where('reference', $token)->firstOrFail();
        $fianncing_plan = Financing_plan::whereHas('device', function ($query) use ($client) {
            $query->where('client_id', $client->id)->orderBy('id', 'desc')->limit(1);
        })->with('device.client')->firstOrFail();

        return (object) [
            'employee_name' => $client->full_name,
            'device_model' => $fianncing_plan->device->phone_model,
            'device_id' => $client->reference,
            'last_sync' => $fianncing_plan->device->last_seen_at,
            'total_price' => $fianncing_plan->total_price,
            'paid_amount' => $fianncing_plan->total_price - $fianncing_plan->remaining_balance,
            'remaining_amount' => $fianncing_plan->remaining_balance,
            'due_date' => $fianncing_plan->next_payment_due_date,
        ];
    }

    private function getComplianceStatus($user)
    {

        $expiresAt = Carbon::parse($user->due_date);
        $now = Carbon::now();

        if ($expiresAt->isFuture()) {
            return 'compliant';
        } elseif ($expiresAt->isPast() && $expiresAt->diffInDays($now) <= 7) {
            return 'restricted';
        } else {
            return 'non_compliant';
        }
    }
}
