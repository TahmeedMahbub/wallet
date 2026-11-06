<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
