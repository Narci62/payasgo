<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Financing_plan;
use Illuminate\Http\Request;

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

    private function getUserFromToken($token)
    {
        $client = Client::where('reference', $token)->firstOrFail();
        $fianncing_plan = Financing_plan::whereHas('device', function ($query) use ($client) {
            $query->where('client_id', $client->id);
        })->with('device.client')->firstOrFail();


        return (object) [
            'employee_name' => $client->full_name,
            'device_model' => $fianncing_plan->device->phone_model,
            'total_price' => $fianncing_plan->total_price,
            'paid_amount' => $fianncing_plan->total_price - $fianncing_plan->remaining_balance,
            'remaining_amount' => $fianncing_plan->remaining_balance,
            'due_date' => $fianncing_plan->next_payment_due_date,
        ];
    }

    private function getComplianceStatus($user)
    {
        if ($user->remaining_amount == 0) return 'compliant';

        $daysOverdue = now()->diffInDays($user->due_date, false);
        if ($daysOverdue >= 0 && $daysOverdue <= 7) return 'restricted';

        return 'non_compliant';
    }
}
