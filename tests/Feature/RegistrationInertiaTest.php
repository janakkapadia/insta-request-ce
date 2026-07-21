<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Tests\TestCase;

class RegistrationInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_flow_with_inertia()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test_inertia@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], ['X-Inertia' => 'true']);

        $response->assertStatus(302);

        $redirectUrl = $response->headers->get('Location');

        $dashboardResponse = $this->get($redirectUrl, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ]);
        $dashboardResponse->assertStatus(302);

        $redirectUrl2 = $dashboardResponse->headers->get('Location');

        $verifyResponse = $this->get($redirectUrl2, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ]);
        $verifyResponse->assertStatus(200);
    }
}
