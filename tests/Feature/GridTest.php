<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Resolver;
use Facilo\AltLingo\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\User;

class GridTest extends TestCase
{
    /** T14: 10,000 images × 5 sites, filter Missing in en: correct count, under 1 s warm. */
    public function test_t14_grid_speed(): void
    {
        $sites = ['es', 'en', 'ca', 'es_mx', 'fr'];
        $statuses = [Resolver::DONE, Resolver::REVIEW, Resolver::FALLBACK, Resolver::MISSING, Resolver::DECORATIVE];
        $rows = [];
        $expected = 0;
        for ($i = 0; $i < 10000; $i++) {
            $name = sprintf('img%05d.jpg', $i);
            if ($i < 50) {
                $this->blank($name);
            }
            $s = [];
            foreach ($sites as $n => $site) {
                $s[$site] = $statuses[($i + $n * 3) % 5];
            }
            $expected += (int) ($s['en'] === Resolver::MISSING);
            $rows[$this->container.'::'.$name] = ['s' => $s, 'q' => $name];
        }
        Cache::forever('altlingo.coverage.'.$this->container, $rows);

        $user = User::make()->email('admin'.uniqid().'@altlingo.test')->makeSuper();
        $user->save();
        $url = '/cp/altlingo/rows?site=en&status=missing';
        $this->actingAs($user)->getJson($url)->assertOk();

        $start = microtime(true);
        $response = $this->actingAs($user)->getJson($url)->assertOk();
        $seconds = microtime(true) - $start;

        $this->assertSame(2000, $expected);
        $this->assertSame($expected, $response->json('meta.total'));
        $this->assertLessThan(1.0, $seconds, "Warm grid took {$seconds}s");
        file_put_contents(dirname(__DIR__, 3).'/proof/t14-grid-speed.txt', sprintf("T14 warm /cp/altlingo/rows, 10,000 rows x 5 sites, Missing in en: %d rows, %.3f s\n", $expected, $seconds));
    }
}
