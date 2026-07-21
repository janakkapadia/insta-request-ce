<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_flow()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(302);

        $this->assertAuthenticated();

        $response2 = $this->actingAs(User::first())->get('/dashboard');

        file_put_contents('test_output.log', 'Dashboard Status: '.$response2->status()."\n", FILE_APPEND);
        file_put_contents('test_output.log', 'Dashboard Location: '.$response2->headers->get('Location')."\n", FILE_APPEND);

        $response3 = $this->get('/email/verify');

        file_put_contents('test_output.log', 'Verify Status: '.$response3->status()."\n", FILE_APPEND);
        file_put_contents('test_output.log', 'Verify Location: '.$response3->headers->get('Location')."\n", FILE_APPEND);
    }
}
