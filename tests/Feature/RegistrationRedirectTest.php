<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_redirect()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test_redirect@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(302);
        $redirectUrl = $response->headers->get('Location');
        echo "Post register redirect: " . $redirectUrl . "\n";
        
        $dashboardResponse = $this->get($redirectUrl);
        $dashboardResponse->assertStatus(302);
        
        $redirectUrl2 = $dashboardResponse->headers->get('Location');
        echo "Dashboard redirect: " . $redirectUrl2 . "\n";
        
        $verifyResponse = $this->get($redirectUrl2);
        echo "Verify response status: " . $verifyResponse->getStatusCode() . "\n";
    }
}
