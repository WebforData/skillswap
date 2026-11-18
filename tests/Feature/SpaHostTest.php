<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaHostTest extends TestCase
{
    public function test_spa_host_route_serves_html_with_react_root(): void
    {
        $response = $this->get('/app');

        $response->assertStatus(200);
        $response->assertSee('<div id="root"></div>', false);
        $response->assertSee('build/assets/main-', false);
        $response->assertSee('build/assets/app-', false);
    }

    public function test_spa_fallback_route_serves_html_for_web_urls(): void
    {
        $response = $this->get('/app/tableau-de-bord');

        $response->assertStatus(200);
        $response->assertSee('<div id="root"></div>', false);
    }

    public function test_api_routes_are_never_intercepted_by_spa_fallback(): void
    {
        // Une route API inexistante doit renvoyer du JSON 404, et non la vue HTML SPA
        $response = $this->getJson('/api/route-inexistante-pour-test');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Ressource non trouvée.',
        ]);
    }
}
