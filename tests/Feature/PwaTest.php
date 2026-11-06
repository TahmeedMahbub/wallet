<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_the_manifest_makes_wallet_installable_as_a_standalone_app(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('#f8f9fa', $manifest['theme_color']);
        $this->assertSame('#f8f9fa', $manifest['background_color']);

        $purposes = collect($manifest['icons'])->groupBy('sizes')->map(fn ($icons) => $icons->pluck('purpose')->all());
        $this->assertContains('any', $purposes['192x192']);
        $this->assertContains('any', $purposes['512x512']);
        $this->assertContains('maskable', $purposes['512x512']);

        foreach ($manifest['icons'] as $icon) {
            [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], $width.'x'.$height, $icon['src']);
        }
    }

    public function test_every_page_links_the_manifest_icons_and_status_bar_colour(): void
    {
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('<meta name="theme-color" content="#f8f9fa">', false)
            ->assertSee('<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">', false)
            ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
            ->assertSee('apple-mobile-web-app-status-bar-style" content="default"', false)
            ->assertSee('viewport-fit=cover', false);
    }

    public function test_every_splash_image_linked_in_the_page_exists_at_its_declared_size(): void
    {
        $this->withoutVite();

        preg_match_all('#href="/splash/splash-(\d+)x(\d+)\.png"#', $this->get('/login')->getContent(), $matches, PREG_SET_ORDER);

        $this->assertCount(8, $matches);

        foreach ($matches as [$path, $width, $height]) {
            $this->assertSame([(int) $width, (int) $height], array_slice(getimagesize(public_path(ltrim(substr($path, 6, -1), '/'))), 0, 2));
        }
    }

    public function test_the_service_worker_never_caches_pages_or_money_data(): void
    {
        $worker = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(public_path('sw.js')));

        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString("const STATIC_PREFIXES = ['/build/assets/', '/icons/'];", $worker);

        $this->assertStringNotContainsString('livewire', strtolower($worker));
        $this->assertStringNotContainsString('/export', $worker);
        $this->assertDoesNotMatchRegularExpression('#cache\.put\([^)]*navigate#', $worker);
        $this->assertSame(1, substr_count($worker, 'cache.put('), 'Only the static-asset path may write to the cache.');
    }

    public function test_the_offline_page_is_static_and_holds_no_user_data(): void
    {
        $page = (string) file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString("You're offline", $page);
        $this->assertStringNotContainsString('csrf', strtolower($page));
        $this->assertStringNotContainsString('<form', $page);
    }

    public function test_signed_in_pages_are_still_marked_not_cacheable(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $cacheControl = $this->actingAs($user)->get('/')->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }
}
