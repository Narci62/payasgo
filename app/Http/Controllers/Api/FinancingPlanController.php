<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Financing_plan;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\FinancingPlanService;
use App\Http\Resources\ClientRessource;
use App\Http\Resources\FinancingPlanResource;

class FinancingPlanController extends Controller
{
    public function __construct(private FinancingPlanService $financingPlanService) {}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validated([
            // validation rules here
        ]);

        $financing_plan = DB::transaction(function () use ($validated) {

            //  $registration_token = app("App\Services\RegistrationTokenService")->createToken(['client_id' => $validated['client_id']]);

            $financing_plan = $this->financingPlanService->createFinancingPlan($validated);

            // save payment histories
            (new PaymentService())->store([
                'financing_plan_id' => $financing_plan->id,
                'amount' => $financing_plan->down_payment,
                'method' => "manual",
                'transaction_id' => uniqid('txn'),
                'status' => 'completed',
                'paid_at' => now(),
            ]);


            return $financing_plan;
        });


        return (new FinancingPlanResource($financing_plan));
    }

    /**
     * Display the specified resource.
     */
    public function show(Financing_plan $financing_plan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Financing_plan $financing_plan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Financing_plan $financing_plan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Financing_plan $financing_plan)
    {
        //
    }
}
