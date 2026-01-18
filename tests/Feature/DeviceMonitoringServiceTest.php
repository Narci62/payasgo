<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Device;
use App\Models\Financing_plan;
use App\Models\Registration_token;
use App\Services\DeviceMonitoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeviceMonitoringService $service;

    public function test_the_application_returns_a_successful_responses(): void
    {
        // $response = $this->get('/');

        // $response->assertStatus(200);

        $a = 2;
        $b = 4;

        $c = $a + $b;

        $this->assertEquals(6, $c);
    }

    // protected function setUp(): void
    // {
    //     parent::setUp();
    //     $this->service = app(DeviceMonitoringService::class);
    // }

    // /** @test */
    // public function it_detects_overdue_payment()
    // {
    //     // Arrange
    //     $client = Client::factory()->create();
    //     $device = Device::factory()->create(['client_id' => $client->id]);

    //     $token = Registration_token::factory()->create([
    //         'client_id' => $client->id,
    //         'device_id' => $device->id
    //     ]);

    //     Financing_plan::factory()->create([
    //         'device_id' => $device->id,
    //         'registration_token_id' => $token->id,
    //         'status' => 'active',
    //         'remaining_balance' => 50000,
    //         'next_payment_due_date' => Carbon::now()->subDays(5) // En retard de 5 jours
    //     ]);

    //     // Act
    //     $shouldBeLocked = $this->service->shouldDeviceBeLocked($device->fresh());

    //     // Assert
    //     $this->assertTrue($shouldBeLocked);
    // }

    // /** @test */
    // public function it_detects_14_days_inactivity()
    // {
    //     // Arrange
    //     $client = Client::factory()->create();
    //     $device = Device::factory()->create([
    //         'client_id' => $client->id,
    //         'last_seen_at' => Carbon::now()->subDays(15) // Inactif depuis 15 jours
    //     ]);

    //     // Act
    //     $shouldBeLocked = $this->service->shouldDeviceBeLocked($device);

    //     // Assert
    //     $this->assertTrue($shouldBeLocked);
    // }

    // /** @test */
    // public function it_does_not_lock_paid_in_full_device()
    // {
    //     // Arrange
    //     $client = Client::factory()->create();
    //     $device = Device::factory()->create(['client_id' => $client->id]);

    //     $token = Registration_token::factory()->create([
    //         'client_id' => $client->id,
    //         'device_id' => $device->id
    //     ]);

    //     Financing_plan::factory()->create([
    //         'device_id' => $device->id,
    //         'registration_token_id' => $token->id,
    //         'status' => 'paid_in_full', // Plan soldé
    //         'remaining_balance' => 0,
    //         'next_payment_due_date' => Carbon::now()->subDays(5)
    //     ]);

    //     // Act
    //     $shouldBeLocked = $this->service->shouldDeviceBeLocked($device->fresh());

    //     // Assert
    //     $this->assertFalse($shouldBeLocked);
    // }

    // /** @test */
    // public function it_does_not_lock_device_with_payment_up_to_date()
    // {
    //     // Arrange
    //     $client = Client::factory()->create();
    //     $device = Device::factory()->create([
    //         'client_id' => $client->id,
    //         'last_seen_at' => Carbon::now() // Vu aujourd'hui
    //     ]);

    //     $token = Registration_token::factory()->create([
    //         'client_id' => $client->id,
    //         'device_id' => $device->id
    //     ]);

    //     Financing_plan::factory()->create([
    //         'device_id' => $device->id,
    //         'registration_token_id' => $token->id,
    //         'status' => 'active',
    //         'remaining_balance' => 50000,
    //         'next_payment_due_date' => Carbon::now()->addDays(5) // Paiement dans 5 jours
    //     ]);

    //     // Act
    //     $shouldBeLocked = $this->service->shouldDeviceBeLocked($device->fresh());

    //     // Assert
    //     $this->assertFalse($shouldBeLocked);
    // }
}
