<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_home_redirects_users_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/panel');
    }

    public function test_login_page_is_in_spanish(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión')->assertDontSee('Register');
    }
}
