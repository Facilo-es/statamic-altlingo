<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Statamic\Facades\User;

class CsvTest extends TestCase
{
    private $admin;

    private function admin()
    {
        if ($this->admin) {
            return $this->admin;
        }
        $user = User::make()->email('admin'.uniqid().'@altlingo.test')->makeSuper();
        $user->save();

        return $this->admin = $user;
    }

    private function export(): array
    {
        $csv = $this->actingAs($this->admin())->get('/cp/altlingo/export')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(preg_split('/\r?\n/', substr($csv, 3))));

        return array_map(fn ($l) => str_getcsv($l, ',', '"', ''), $lines);
    }

    private function upload(array $rows, string $name = 'alt.csv')
    {
        $csv = implode("\n", array_map(function ($row) {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, $row, escape: '');
            rewind($out);

            return rtrim(stream_get_contents($out), "\n");
        }, $rows));

        return $this->actingAs($this->admin())->post('/cp/altlingo/import/preview', [
            'file' => UploadedFile::fake()->createWithContent($name, $csv),
        ], ['Accept' => 'application/json']);
    }

    /** T20: export, edit 3 cells, import. */
    public function test_round_trip(): void
    {
        $a = $this->image('a.png', 'Terraza', ['en' => 'Terrace']);
        $b = $this->image('b.png', 'Sala');
        $this->image('c.png', 'Cocina', ['ca' => 'Cuina']);

        $rows = $this->export();
        $header = $rows[0];
        $this->assertSame(['id', 'container', 'path', 'image_url', 'es', 'en', 'ca', 'es_mx', 'decorative'], $header);
        $this->assertCount(4, $rows);

        $col = array_flip($header);
        foreach ($rows as $i => $row) {
            if ($row[$col['id']] === $a->id()) {
                $rows[$i][$col['en']] = 'Sunny terrace';
            }
            if ($row[$col['id']] === $b->id()) {
                $rows[$i][$col['en']] = 'Living room';
                $rows[$i][$col['ca']] = 'Sala d\'estar';
            }
            if ($row[$col['path']] === 'c.png') {
                $rows[$i][$col['ca']] = '';
            }
        }

        $preview = $this->upload($rows)->assertOk()->json();
        $this->assertCount(3, $preview['changes']);
        $this->assertSame(1, $preview['changed']);
        $this->assertSame(2, $preview['new']);
        $this->assertSame([], $preview['errors']);

        $this->actingAs($this->admin())->postJson('/cp/altlingo/import/apply', ['token' => $preview['token']])
            ->assertOk()->assertJson(['written' => 3]);

        $this->assertSame('Sunny terrace', $this->altlingo()->resolve($this->fresh($a), 'en'));
        $this->assertSame('Living room', $this->altlingo()->resolve($this->fresh($b), 'en'));
        $this->assertSame('Sala d\'estar', $this->altlingo()->resolve($this->fresh($b), 'ca'));
        $this->assertSame('Cuina', $this->altlingo()->resolve(\Statamic\Facades\Asset::find($this->container.'::c.png'), 'ca'), 'An empty cell erases nothing');

        // The token works once, and only for the user who uploaded it.
        $this->actingAs($this->admin())->postJson('/cp/altlingo/import/apply', ['token' => $preview['token']])->assertNotFound();
    }

    /** T21: a formula is exported with a leading quote, and comes back as the same text. */
    public function test_csv_injection(): void
    {
        $text = '=HYPERLINK("http://evil.test","x")';
        $asset = $this->image('f.png', $text);

        $rows = $this->export();
        $col = array_flip($rows[0]);
        $this->assertSame("'".$text, $rows[1][$col['es']]);
        foreach (['+1', '-1', '@SUM(A1)', "\tx"] as $value) {
            $this->assertSame("'".$value, \Facilo\AltLingo\Http\Controllers\CsvController::safe($value));
        }
        $this->assertSame('Terraza - vista', \Facilo\AltLingo\Http\Controllers\CsvController::safe('Terraza - vista'));

        $preview = $this->upload($rows)->assertOk()->json();
        $this->assertCount(0, $preview['changes'], 'Re-importing the export changes nothing');
        $this->assertSame($text, $this->altlingo()->resolve($this->fresh($asset), 'es'));
    }

    /** T22: unknown id reported and skipped, a 6 MB file refused with a message. */
    public function test_bad_rows_and_big_file(): void
    {
        $this->image('g.png', 'Jardín');
        $preview = $this->upload([
            ['id', 'en'],
            ['nope::missing.png', 'Ghost'],
            [$this->container.'::g.png', 'Garden'],
        ])->assertOk()->json();

        $this->assertSame(1, $preview['unmatched']);
        $this->assertSame(['Line 2: no image with this id'], $preview['errors']);
        $this->assertCount(1, $preview['changes']);

        $big = UploadedFile::fake()->create('big.csv', 6 * 1024, 'text/csv');
        $this->actingAs($this->admin())->post('/cp/altlingo/import/preview', ['file' => $big], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertSee('bigger than 5 MB');

        $this->upload([['name', 'en'], ['x', 'y']])->assertStatus(422)->assertSee('id column');
    }
}
