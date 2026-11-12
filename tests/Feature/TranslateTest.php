<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Store\MetaStore;
use Facilo\AltLingo\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Statamic\Facades\User;

class TranslateTest extends TestCase
{
    public const KEY = 'secret-test-key-4f9a:fx';

    private int $calls = 0;

    private array $responses = [];

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    protected function tearDown(): void
    {
        putenv('ALTLINGO_DEEPL_KEY');
        unset($_ENV['ALTLINGO_DEEPL_KEY'], $_SERVER['ALTLINGO_DEEPL_KEY']);
        parent::tearDown();
    }

    private function withKey(): void
    {
        putenv('ALTLINGO_DEEPL_KEY='.self::KEY);
        $_ENV['ALTLINGO_DEEPL_KEY'] = $_SERVER['ALTLINGO_DEEPL_KEY'] = self::KEY;
    }

    /** DeepL without Catalan; translate call 7 gets a 429 once. */
    private function fakeDeepL(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('DeepL-Auth-Key '.self::KEY, $request->header('Authorization')[0]);
            if (str_contains($request->url(), '/v2/languages')) {
                return Http::response([['language' => 'EN-US'], ['language' => 'ES'], ['language' => 'DE']]);
            }
            $this->calls++;
            if ($this->calls === 7) {
                return Http::response(['message' => 'Too many requests'], 429);
            }

            return Http::response(['translations' => [['text' => $request['target_lang'].': '.$request['text'][0]]]]);
        });
    }

    private function admin()
    {
        $user = User::make()->email('admin'.uniqid().'@altlingo.test')->makeSuper();
        $user->save();

        return $user;
    }

    private function translateRun(array $ids, array $sites): array
    {
        $start = $this->postJson('/cp/altlingo/translate', ['ids' => $ids, 'sites' => $sites])->assertOk();
        $this->responses[] = $start->getContent();
        $run = $start->json('run');

        for ($i = 0; $i < 20; $i++) {
            $res = $this->postJson('/cp/altlingo/translate/batch', ['run' => $run])->assertOk();
            $this->responses[] = $res->getContent();
            if ($res->json('finished')) {
                return $res->json();
            }
        }
        $this->fail('Translation run did not finish');
    }

    /** T16: 30 rows × 2 languages with a 429 on call 7: 60 cells filled and To review. */
    public function test_t16_translate_bulk_with_rate_limit(): void
    {
        $this->withKey();
        $this->fakeDeepL();
        $ids = [];
        for ($i = 0; $i < 30; $i++) {
            $ids[] = $this->image("t{$i}.png", "Foto $i")->id();
        }

        $summary = $this->actingAs($this->admin())->translateRun($ids, ['en', 'es_mx']);

        $this->assertSame(60, $summary['ok']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(61, $this->calls);
        foreach ($ids as $n => $id) {
            $asset = \Statamic\Facades\Asset::find($id);
            $this->assertSame(['en' => "EN-US: Foto $n", 'es_mx' => "ES: Foto $n"], $this->texts($asset));
            $this->assertEqualsCanonicalizing(['en', 'es_mx'], $this->altlingo()->record($this->fresh($asset))->review);
            $this->assertSame("Foto $n", $this->fresh($asset)->get('alt'));
        }
        $this->assertSame(0, $summary['totals']['en']['percent'], 'To review is not done');
    }

    /** T17: Catalan with DeepL is skipped with a clear message, the rest proceeds. */
    public function test_t17_unsupported_language(): void
    {
        $this->withKey();
        $this->fakeDeepL();
        $id = $this->image('c.png', 'Terraza')->id();

        $summary = $this->actingAs($this->admin())->translateRun([$id], ['ca', 'en']);

        $this->assertSame(1, $summary['ok']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame("DeepL doesn't support Catalan. Try the AI option.", $summary['errors'][0]['message']);
        $this->assertSame(['en' => 'EN-US: Terraza'], $this->texts(\Statamic\Facades\Asset::find($id)));
    }

    /** T18: no key, no Translate button, and the "Add a key" line shows. */
    public function test_t18_no_key(): void
    {
        $asset = $this->image('n.png', 'Terraza');
        $this->actingAs($this->admin());

        $page = $this->get('/cp/altlingo')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/&quot;translator&quot;:false|"translator":false/', $page);

        $field = (new \Facilo\AltLingo\Fieldtypes\AltLingo)->setField(new \Statamic\Fields\Field('alt', ['type' => 'altlingo']));
        $this->assertFalse($field->preload()['canTranslate']);

        $this->postJson('/cp/altlingo/translate/image/'.base64_encode($asset->id()), ['site' => 'en'])->assertStatus(422);
    }

    /** The image editor's Translate button fills one tab. */
    public function test_translate_one_tab_from_editor(): void
    {
        $this->withKey();
        $this->fakeDeepL();
        $asset = $this->image('o.png', 'Terraza');

        $this->actingAs($this->admin())->postJson('/cp/altlingo/translate/image/'.base64_encode($asset->id()), ['site' => 'en'])
            ->assertOk()->assertJson(['text' => 'EN-US: Terraza']);
    }

    /** T19: after a run, the key is in no log, YAML file or JSON response. */
    public function test_t19_key_never_leaks(): void
    {
        $this->test_t16_translate_bulk_with_rate_limit();

        $haystacks = $this->responses;
        foreach (File::allFiles(storage_path('logs')) as $file) {
            $haystacks[] = $file->getContents();
        }
        foreach (File::allFiles(storage_path('altlingo-tests/'.$this->container), true) as $file) {
            $haystacks[] = $file->getContents();
        }
        foreach (File::allFiles(base_path('content')) as $file) {
            $haystacks[] = $file->getContents();
        }
        $haystacks[] = $this->get('/cp/altlingo')->getContent();

        $this->assertGreaterThan(30, count($haystacks));
        foreach ($haystacks as $text) {
            $this->assertStringNotContainsString('secret-test-key', $text);
        }
    }
}
