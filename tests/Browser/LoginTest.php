<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    /**
     * Teste de login.
     */
    public function test_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('#callback', 'http://fechaduras/callback')
                ->type('#loginUsuario', '1111')
                ->press('Login')
                ->waitForText('Fechaduras')
                ->assertSee('Sair');
        });
    }
}
