<?php

namespace App\Http\Controllers\Api;

use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use App\Models\Financing_plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\FinancingPlanService;
use App\Http\Resources\FedapayWebhookResource;
use App\Models\Client;
use App\Services\PaymentService;
use FedaPay\Webhook;

class FedapayWebhookController extends Controller
{
    protected $financingPlanService;
    protected $paymentService;
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.mode')); // sandbox ou live

        $this->financingPlanService = new FinancingPlanService();
        $this->paymentService = new PaymentService();
    }

    public function showForm()
    {
        return view('payment.form');
    }

    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $client = Client::where('reference', $validated['reference'])->first();
        if (!$client) {
            return back()->withErrors(['reference' => 'Client non trouvé pour cette référence.'])->withInput();
        }

        $device = $client->devices()->latest()->first();
        if (!$device) {
            return back()->withErrors(['device' => 'Aucun appareil trouvé pour ce client.'])->withInput();
        }

        $financing_plan = Financing_plan::where('device_id', $device->id)->whereNot('status', 'paid_in_full')->first();
        if (!$financing_plan) {
            return back()->withErrors(['financing_plan' => 'Aucun plan de financement trouvé pour ce client.'])->withInput();
        }

        $check_eli = $this->financingPlanService->checkEligibilityAndReturnNewAmount($financing_plan, $validated['amount']);

        if ($check_eli['status'] === false) {
            return back()->withErrors(['amount' => $check_eli['message']])->withInput();
        }

        $transaction = Transaction::create([
            'description' => 'Paiement client ' . $validated['reference'],
            'amount' => $validated['amount'],
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('fedapay.end'),
            'metadata' => [
                'reference' => $validated['reference'],
                'financing_plan_id' => $financing_plan->id,
                "nbr_interval" => $check_eli['nbr_interval'],
                "total_normal" => $check_eli['total_normal'],
                "penalite" => $check_eli['penalite'],
            ],
        ]);

        // save payment with status pending
        $financing_plan->payments()->create([
            'amount' => $validated['amount'],
            'method' => 'fedapay',
            'transaction_id' => $transaction->reference,
            'status' => 'pending',
            'paid_at' => null
        ]);

        return redirect($transaction->generateToken()->url);
    }

    /**
     * 🔹 Webhook FedaPay : reçoit la confirmation automatique
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
            Log::error('❌ Webhook payload invalide: ' . $e->getMessage());
            return response('Invalid payload', 400);
        } catch (\FedaPay\Error\SignatureVerification $e) {
            Log::error('❌ Signature webhook invalide: ' . $e->getMessage());
            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            Log::error('❌ Erreur webhook inattendue: ' . $e->getMessage());
            return response('Webhook error', 400);
        }

        Log::info('📬 Event reçu', ['event' => $event]);

        if (empty($event->name)) {
            return response('Invalid event', 400);
        }

        if ($event->name !== 'transaction.approved') {
            Log::info("Événement ignoré : " . $event->name);
            return response('Event not handled', 200);
        }

        // Accédez directement à l'objet transaction (ajustez si structure différente)
        $data = $event->entity; // Ou $event->object si c'est le cas

        if (!$data) {
            Log::error('❌ Transaction non trouvée dans l\'événement');
            return response('No transaction object', 400);
        }

        try {
            $transaction = Transaction::retrieve($data->id);
        } catch (\FedaPay\Error\Base $e) {
            Log::error('❌ Erreur lors de la récupération de la transaction: ' . $e->getMessage());
            return response('Error retrieving transaction', 400);
        }

        if ($transaction) {
            $payment = $this->paymentService->findByTransactionID($transaction->reference);
            if (!$payment) {
                Log::error("❌ Paiement non trouvé pour la transaction : " . $transaction->reference);
                return response('Payment not found', 404);
            }

            $record = $payment->financingPlan;
            if (!$record) {
                Log::error("❌ Plan de financement non trouvé pour l'ID : " . $payment->financing_plan_id);
                return response('Financing plan not found', 404);
            }

            $payments = $this->financingPlanService->savePayment($record, $payment->amount, 'fedapay', $payment->transaction_id);

            Log::info("✅ Paiement confirmé pour $payment->transaction_id");
        } else {
            Log::warning('❌ Métadonnées incomplètes pour le traitement');
        }

        return response('OK', 200);
    }

    public function callback(Request $request)
    {
        $status = $request->get('status') == 'approved' ? 'success' : 'failed';

        return view('payment.callback', compact('status'));
    }
}
