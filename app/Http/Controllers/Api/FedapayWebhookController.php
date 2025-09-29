<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FedapayWebhookResource;
use App\Models\Financing_plan;
use App\Services\FinancingPlanService;
use FedaPay\FedaPay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FedapayWebhookController extends Controller
{
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

                return $financingPlan->savePayment($financingPlan,$payload['object']['amount'], 'fedapay', $payload['object']['id']);
            });
        } catch (\Throwable $e) {
            Log::critical("Webhook Fedapay : Échec du traitement BDD. Erreur: " . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }

        Log::info("Financing plan updated successfully for ID: " . $financingPlanId);
        return new FedapayWebhookResource($plan);
    }
}
