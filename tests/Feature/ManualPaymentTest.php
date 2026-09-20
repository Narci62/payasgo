<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Financing_plan;
use App\Models\Installment;
use App\Models\Registration_token;
use App\Services\FinancingPlanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    private FinancingPlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FinancingPlanService;
    }

    private function createPlanWithInstallment(array $planAttrs = [], array $installmentAttrs = []): array
    {
        $client = Client::create([
            'full_name' => 'Test Client',
            'phone_number' => '+22901000000',
            'reference' => uniqid('REF-'),
        ]);

        $token = Registration_token::create([
            'client_id' => $client->id,
            'token' => uniqid('token-'),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $plan = Financing_plan::create(array_merge([
            'registration_token_id' => $token->id,
            'total_price' => 300000,
            'down_payment' => 50000,
            'remaining_balance' => 250000,
            'installment_amount' => 50000,
            'status' => 'active',
            'days_interval' => 30,
            'next_payment_due_date' => Carbon::now()->subDays(10),
            'grace_period_ends_at' => Carbon::now()->subDays(5),
            'next_offline_unlock_code' => 'ABC123',
        ], $planAttrs));

        $installment = Installment::create(array_merge([
            'financing_plan_id' => $plan->id,
            'due_date' => Carbon::now()->subDays(10),
            'amount' => 50000,
            'remaining_amount' => 50000,
            'status' => 'pending',
        ], $installmentAttrs));

        return ['plan' => $plan, 'installment' => $installment, 'client' => $client];
    }

    public function test_full_payment_updates_next_payment_due_date(): void
    {
        $dueDate = Carbon::now()->subDays(10);
        ['plan' => $plan] = $this->createPlanWithInstallment(
            ['days_interval' => 30],
            ['due_date' => $dueDate, 'remaining_amount' => 50000]
        );

        $result = $this->service->savePayment($plan, 50000, 'manual', 'txn-full-1');

        $expectedNextDue = $dueDate->copy()->addDays(30);
        $this->assertNotNull($result->next_payment_due_date);
        $this->assertEquals($expectedNextDue->format('Y-m-d'), Carbon::parse($result->next_payment_due_date)->format('Y-m-d'));
    }

    public function test_full_payment_creates_next_installment(): void
    {
        $dueDate = Carbon::now()->subDays(10);
        ['plan' => $plan] = $this->createPlanWithInstallment(
            ['remaining_balance' => 250000, 'installment_amount' => 50000],
            ['due_date' => $dueDate, 'remaining_amount' => 50000]
        );

        $this->assertEquals(1, $plan->installments()->count());

        $this->service->savePayment($plan, 50000, 'manual', 'txn-create-next');

        $plan->refresh();
        $this->assertEquals(2, $plan->installments()->count());

        $nextInstallment = $plan->installments()->where('status', 'pending')->orderBy('due_date', 'asc')->first();
        $this->assertNotNull($nextInstallment);
        $this->assertEquals($dueDate->copy()->addDays(30)->format('Y-m-d'), $nextInstallment->due_date->format('Y-m-d'));
        $this->assertEquals(50000, (float) $nextInstallment->remaining_amount);
        $this->assertEquals('pending', $nextInstallment->status);
    }

    public function test_full_payment_decrements_remaining_balance(): void
    {
        ['plan' => $plan] = $this->createPlanWithInstallment(
            ['remaining_balance' => 250000],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 50000, 'manual', 'txn-balance');

        $plan->refresh();
        $this->assertEquals(200000, (float) $plan->remaining_balance);
    }

    public function test_full_payment_marks_installment_as_paid(): void
    {
        ['plan' => $plan, 'installment' => $installment] = $this->createPlanWithInstallment(
            [],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 50000, 'manual', 'txn-paid');

        $installment->refresh();
        $this->assertEquals('paid', $installment->status);
        $this->assertEquals(0, (float) $installment->remaining_amount);
    }

    public function test_partial_payment_does_not_create_next_installment(): void
    {
        ['plan' => $plan] = $this->createPlanWithInstallment(
            ['remaining_balance' => 250000],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 20000, 'manual', 'txn-partial');

        $plan->refresh();
        $this->assertEquals(1, $plan->installments()->count());
    }

    public function test_partial_payment_does_not_update_next_payment_due_date(): void
    {
        $originalDueDate = Carbon::now()->subDays(10);
        ['plan' => $plan] = $this->createPlanWithInstallment(
            ['next_payment_due_date' => $originalDueDate],
            ['due_date' => $originalDueDate, 'remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 20000, 'manual', 'txn-partial-date');

        $plan->refresh();
        $this->assertEquals($originalDueDate->format('Y-m-d'), Carbon::parse($plan->next_payment_due_date)->format('Y-m-d'));
    }

    public function test_partial_payment_reduces_installment_remaining(): void
    {
        ['plan' => $plan, 'installment' => $installment] = $this->createPlanWithInstallment(
            [],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 20000, 'manual', 'txn-partial-remain');

        $installment->refresh();
        $this->assertEquals(30000, (float) $installment->remaining_amount);
        $this->assertEquals('pending', $installment->status);
    }

    public function test_last_payment_sets_status_to_paid_in_full(): void
    {
        ['plan' => $plan] = $this->createPlanWithInstallment(
            ['remaining_balance' => 50000],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 50000, 'manual', 'txn-last');

        $plan->refresh();
        $this->assertEquals('paid_in_full', $plan->getRawOriginal('status'));
        $this->assertEquals(0, (float) $plan->remaining_balance);
        $this->assertNotNull($plan->uninstall_code);
    }

    public function test_non_last_payment_sets_status_to_active(): void
    {
        ['plan' => $plan] = $this->createPlanWithInstallment(
            ['remaining_balance' => 250000, 'status' => 'defaulted'],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 50000, 'manual', 'txn-active');

        $plan->refresh();
        $this->assertEquals('active', $plan->getRawOriginal('status'));
    }

    public function test_payment_records_in_payments_table(): void
    {
        ['plan' => $plan] = $this->createPlanWithInstallment(
            [],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 50000, 'manual', 'txn-record');

        $this->assertDatabaseHas('payments', [
            'financing_plan_id' => $plan->id,
            'amount' => 50000,
            'method' => 'manual',
            'transaction_id' => 'txn-record',
            'status' => 'completed',
        ]);
    }

    public function test_payment_without_installment_fallback(): void
    {
        $client = Client::create([
            'full_name' => 'No Installment Client',
            'phone_number' => '+22901000001',
            'reference' => uniqid('REF-'),
        ]);

        $token = Registration_token::create([
            'client_id' => $client->id,
            'token' => uniqid('token-'),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $plan = Financing_plan::create([
            'registration_token_id' => $token->id,
            'total_price' => 300000,
            'down_payment' => 50000,
            'remaining_balance' => 250000,
            'installment_amount' => 50000,
            'status' => 'active',
            'days_interval' => 30,
            'next_payment_due_date' => Carbon::now()->addDays(20),
            'grace_period_ends_at' => Carbon::now()->addDays(25),
            'next_offline_unlock_code' => 'DEF456',
        ]);

        $result = $this->service->savePayment($plan, 30000, 'manual', 'txn-fallback');

        $result->refresh();
        $this->assertEquals(220000, (float) $result->remaining_balance);
    }

    public function test_next_installment_amount_is_capped_at_remaining_balance(): void
    {
        ['plan' => $plan, 'installment' => $firstInstallment] = $this->createPlanWithInstallment(
            ['remaining_balance' => 80000, 'installment_amount' => 50000],
            ['remaining_amount' => 50000]
        );

        $this->service->savePayment($plan, 50000, 'manual', 'txn-cap');

        $plan->refresh();
        $nextInstallment = $plan->installments()
            ->where('status', 'pending')
            ->where('id', '!=', $firstInstallment->id)
            ->first();
        $this->assertNotNull($nextInstallment);
        $this->assertEquals(30000, (float) $nextInstallment->remaining_amount);
    }
}
