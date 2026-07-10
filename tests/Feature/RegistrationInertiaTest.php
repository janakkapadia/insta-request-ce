<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        echo "Redirecting to: " . $redirectUrl . "\n";
        
        $dashboardResponse = $this->get($redirectUrl, ['X-Inertia' => 'true']);
        $dashboardResponse->assertStatus(302);
        
        $redirectUrl2 = $dashboardResponse->headers->get('Location');
        echo "Redirecting to 2: " . $redirectUrl2 . "\n";
        
        $verifyResponse = $this->get($redirectUrl2, ['X-Inertia' => 'true']);
        echo "Verify Response Status: " . $verifyResponse->getStatusCode() . "\n";
        if ($verifyResponse->getStatusCode() !== 200) {
            echo "Body: " . substr($verifyResponse->getContent(), 0, 500) . "\n";
        }
    }
}
