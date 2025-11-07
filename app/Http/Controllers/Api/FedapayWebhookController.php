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
use FedaPay\Webhook;

class FedapayWebhookController extends Controller
{
    protected $financingPlanService;
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapayT.secret_key'));
        FedaPay::setEnvironment(config('services.fedapayT.mode')); // sandbox ou live

        $this->financingPlanService = new FinancingPlanService();
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

        $financing_plan = Financing_plan::where('device_id', $device->id)->whereNot('status','paid_in_full')->first();
        if (!$financing_plan) {
            return back()->withErrors(['financing_plan' => 'Aucun plan de financement trouvé pour ce client.'])->withInput();
        }

        $check_eli = $this->financingPlanService->checkEligibilityAndReturnNewAmount($financing_plan, $validated['amount']);

        if($check_eli['status'] === false){
            return back()->withErrors(['amount' => $check_eli['message']])->withInput();
        }



        $transaction = Transaction::create([
            'description' => 'Paiement client ' . $validated['reference'],
            'amount' => $validated['amount'],
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('fedapay.webhook'),
            'metadata' => [
                'reference' => $validated['reference'],
                'financing_plan_id' => $financing_plan->id,
                "nbr_interval" => $check_eli['nbr_interval'],
                "total_normal" => $check_eli['total_normal'],
                "penalite" => $check_eli['penalite'],
            ],
        ]);

        return redirect($transaction->generateToken()->url);
    }

    /**
     * 🔹 Webhook FedaPay : reçoit la confirmation automatique
     */
    public function webhook(Request $request)
    {
        $event = Webhook::constructEvent(
            $request->getContent(),
            $request->header('X-FEDAPAY-SIGNATURE'),
            config('services.fedapayT.webhook_signature_key')
        );
        dd($event);

        $payload = $request->getContent();
        $signature = $request->header('X-Fedapay-Signature');

        // $expected = hash_hmac('sha256', $payload, config('services.fedapay.webhook_signature_key'));
        // if (!hash_equals($expected, $signature)) {
        //     return response('Invalid signature', 403);
        // }


        // log the payload for debugging
        Log::info('📬 Webhook reçu : ' . $payload);

        $data = json_decode($payload, true);
        if (!$data || empty($data['event'])) {
            return response('Invalid payload', 400);
        }

        if ($data['event']['name'] === 'transaction.approved') {

            $transaction = $data['event']['object'] ?? [];
            $fedapayId = $transaction['id'] ?? null;
            $metadata = $transaction['metadata'] ?? [];
            $reference = $metadata['reference'] ?? null;
            $financing_plan = $metadata['financing_plan_id'] ?? null;
            $nbr_intervall = $metadata["nbr_interval"] ?? null;
            $total_normal = $metadata["total_normal"] ?? null;
            $penalite = $metadata["penalite"] ?? null;

            if ($financing_plan && $reference) {
                // mise à jour de ma base de données
                $record = Financing_plan::find($financing_plan);
                if (!$record) {
                    Log::error("❌ Plan de financement non trouvé pour l'ID : $financing_plan");
                    return response('Financing plan not found', 404);
                }
                $payments = $this->financingPlanService->savePayment($record, $total_normal, 'manual',  $fedapayId ?? uniqid("txn-"));

                Log::info("✅ Paiement confirmé pour $reference");
            }
        }

        return response('OK', 200);
    }

    public function handleWebhook(Request $request)
    {
        // Log the incoming webhook payload for debugging
        Log::info("Received FedaPay webhook: ", $request->all());

        // Check signature (if applicable)
        $secret = config('services.fedapay.webhook_secret');
        $signature = $request->header('X-FEDAPAY-SIGNATURE');

        $payload = $request->input("data");
        $event = null;

        // if (! $signature || $signature !== hash_hmac('sha256', $request->getContent(), $secret)) {
        //     Log::warning("Invalid FedaPay webhook signature.");
        //     return response()->json(['message' => 'Invalid signature'], 400);
        // }

        try {
            $event = \FedaPay\Webhook::constructEvent(
                $payload,
                $signature,
                $secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['message' => 'Invalid payload'], 400);
            exit();
        } catch (\FedaPay\Error\SignatureVerification $e) {
            // Invalid signature
            return response()->json(['message' => 'Invalid signature'], 400);
            //exit();
        }

        // Extract relevant data from the webhook payload
        $eventType = $event->name ?? null;

        if ($eventType !== "transaction.approved") {
            Log::info("Ignoring non-approval event: " . $eventType);
            return response()->json(['message' => 'Événement non traité'], 200);
        }

        // check financing plan associated with the transaction
        $financingPlanId = $payload['metadata']['financing_plan_id'] ?? null;

        $financingPlan = new FinancingPlanService();

        $financingPlan = Financing_plan::find($financingPlanId);
        if (!$financingPlan) {
            Log::error("Financing plan not found for ID: " . $financingPlanId);
            return response()->json(['message' => 'Financing plan not found'], 404);
        }

        // Update financing plan status

        try {
            $plan = DB::transaction(function () use ($financingPlan, $payload) {

                return $financingPlan->savePayment($financingPlan, $payload['object']['amount'], 'fedapay', $payload['object']['id']);
            });
        } catch (\Throwable $e) {
            Log::critical("Webhook Fedapay : Échec du traitement BDD. Erreur: " . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }

        Log::info("Financing plan updated successfully for ID: " . $financingPlanId);
        return new FedapayWebhookResource($plan);
    }
}
