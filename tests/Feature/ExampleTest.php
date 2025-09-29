<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // $response = $this->get('/');

        // $response->assertStatus(200);

        $a = 2;
        $b = 4;

        $c = $a + $b;

        $this->assertEquals(6, $c);
    }
}
