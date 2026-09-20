<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Financing_plan;
use App\Services\FinancingPlanService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FedapayWebhookController extends Controller
{
    protected $financingPlanService;

    protected $paymentService;

    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.mode')); // sandbox ou live

        $this->financingPlanService = new FinancingPlanService;
        $this->paymentService = new PaymentService;
    }

    public function showForm(Request $request, $imat = null)
    {
        if ($imat == '44750') {
            return to_route('compliance.show', ['imat' => $imat]);
        }

        $client = Client::where('reference', $imat)->first();
        if (! $client) {
            return abort(404, 'Client non trouvé pour cette référence.');
        }

        $device = $client->devices()->latest()->first();
        $financing_plan = Financing_plan::where('device_id', $device->id)->whereNot('status', 'paid_in_full')->first();

        $installment_amount = $financing_plan->installment_amount ?? null;
        $total_price = $financing_plan->total_price ?? 0;
        $remaining_amount = $financing_plan->remaining_balance ?? 0;
        $paid_amount = $total_price - $remaining_amount;
        $client_name = $client->full_name;

        $is_late = false;
        $days_late = 0;
        $penalty_amount = 0;
        $total_due = 0;
        $due_date = null;
        $penalty_breakdown = null;

        if ($financing_plan) {
            $penaltyService = new PenaltyService;
            $currentInstallment = $financing_plan->installments()
                ->where('status', '!=', 'paid')
                ->orderBy('due_date', 'asc')
                ->first();

            if ($currentInstallment) {
                $is_late = $currentInstallment->isOverdue();
                $days_late = $currentInstallment->getDaysLate();
                $penalty_amount = $penaltyService->calculatePenalty($currentInstallment);
                $total_due = $installment_amount + $penalty_amount;
                $due_date = $currentInstallment->due_date->format('d/m/Y');
                $penalty_breakdown = $penaltyService->getPenaltyBreakdown($currentInstallment);
            }
        }

        return view('payment.form', compact(
            'imat', 'installment_amount', 'total_price', 'remaining_amount', 'paid_amount', 'client_name',
            'is_late', 'days_late', 'penalty_amount', 'total_due', 'due_date', 'penalty_breakdown'
        ));
    }

    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $client = Client::where('reference', $validated['reference'])->first();
        if (! $client) {
            return back()->withErrors(['reference' => 'Client non trouvé pour cette référence.'])->withInput();
        }

        $device = $client->devices()->latest()->first();
        if (! $device) {
            return back()->withErrors(['device' => 'Aucun appareil trouvé pour ce client.'])->withInput();
        }

        $financing_plan = Financing_plan::where('device_id', $device->id)->whereNot('status', 'paid_in_full')->first();
        if (! $financing_plan) {
            return back()->withErrors(['financing_plan' => 'Aucun plan de financement trouvé pour ce client.'])->withInput();
        }

        $check_eli = $this->financingPlanService->checkEligibilityAndReturnNewAmount($financing_plan, $validated['amount']);

        if ($check_eli['status'] === false) {
            return back()->withErrors(['amount' => $check_eli['message']])->withInput();
        }

        $transaction = Transaction::create([
            'description' => 'Paiement client '.$validated['reference'],
            'amount' => $validated['amount'],
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('fedapay.end'),
            'metadata' => [
                'reference' => $validated['reference'],
                'financing_plan_id' => $financing_plan->id,
                'nbr_interval' => $check_eli['nbr_interval'],
                'total_normal' => $check_eli['total_normal'],
                'penalite' => $check_eli['penalite'],
            ],
        ]);

        // save payment with status pending
        $financing_plan->payments()->create([
            'amount' => $validated['amount'],
            'method' => 'fedapay',
            'transaction_id' => $transaction->reference,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        return redirect($transaction->generateToken()->url);
    }

    /**
     * Webhook FedaPay : reçoit la confirmation automatique
     */
    public function webhook(Request $request)
    {
        $endpoint_secret = config('services.fedapay.webhook_signature_key');

        $payload = $request->getContent();
        $sig_header = $request->header('X-FEDAPAY-SIGNATURE');
        $event = null;

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Webhook payload invalide: '.$e->getMessage());

            return response('Invalid payload', 400);
        } catch (\FedaPay\Error\SignatureVerification $e) {
            Log::error('Signature webhook invalide: '.$e->getMessage());

            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            Log::error('Erreur webhook inattendue: '.$e->getMessage());

            return response('Webhook error', 400);
        }

        Log::info('Event reçu', ['event' => $event]);

        if (empty($event->name)) {
            return response('Invalid event', 400);
        }

        if ($event->name !== 'transaction.approved') {
            Log::info('Événement ignoré : '.$event->name);

            return response('Event not handled', 200);
        }

        $data = $event->entity;

        if (! $data) {
            Log::error('Transaction non trouvée dans l\'événement');

            return response('No transaction object', 400);
        }

        try {
            $transaction = Transaction::retrieve($data->id);
        } catch (\FedaPay\Error\Base $e) {
            Log::error('Erreur lors de la récupération de la transaction: '.$e->getMessage());

            return response('Error retrieving transaction', 400);
        }

        if ($transaction) {
            $payment = $this->paymentService->findByTransactionID($transaction->reference);
            if (! $payment) {
                Log::error('Paiement non trouvé pour la transaction : '.$transaction->reference);

                return response('Payment not found', 404);
            }

            $record = $payment->financingPlan;
            if (! $record) {
                Log::error("Plan de financement non trouvé pour l'ID : ".$payment->financing_plan_id);

                return response('Financing plan not found', 404);
            }

            // Utiliser le nouveau système de pénalités
            $penaltyService = new PenaltyService;
            $currentInstallment = $record->installments()
                ->where('status', '!=', 'paid')
                ->orderBy('due_date', 'asc')
                ->first();

            if ($currentInstallment) {
                // Calculer les pénalités
                $penalty = $penaltyService->calculatePenalty($currentInstallment);

                // Enregistrer les pénalités dans la base
                $penaltyService->storePenalties($currentInstallment, $penalty, $payment->id);

                // Le montant réel = montant total - pénalités (pour le split)
                $real_amount = max(0, $payment->amount - $penalty);
            } else {
                $real_amount = $payment->amount;
            }

            $this->financingPlanService->savePayment($record, $payment->amount, 'fedapay', $payment->transaction_id, 0);

            Log::info("Paiement confirmé pour $payment->transaction_id");
        } else {
            Log::warning('Métadonnées incomplètes pour le traitement');
        }

        return response('OK', 200);
    }

    public function callback(Request $request)
    {
        $status = $request->get('status') == 'approved' ? 'success' : 'failed';

        return view('payment.callback', compact('status'));
    }
}
