<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Tests\TestCase;
use Statamic\Facades\Role;
use Statamic\Facades\User;

class PermissionTest extends TestCase
{
    private function editor(array $sites)
    {
        Role::make('alt_editor')->title('Editor')->permissions(array_merge([
            'access cp', "view {$this->container} assets", "edit {$this->container} assets", 'view altlingo', 'edit altlingo',
        ], array_map(fn ($s) => "access {$s} site", $sites)))->save();

        $user = User::make()->email('editor'.uniqid().'@altlingo.test')->assignRole('alt_editor');
        $user->save();

        return $user;
    }

    /** T13: an editor without ca access can't write ca, through the grid or the asset form. */
    public function test_t13_site_permission(): void
    {
        $asset = $this->image('a.png', 'Terraza', ['en' => 'Terrace']);
        $user = $this->editor(['es', 'en']);

        $this->actingAs($user)->postJson('/cp/altlingo/cell', ['id' => $asset->id(), 'site' => 'ca', 'text' => 'Terrassa'])->assertForbidden();
        $this->assertArrayNotHasKey('ca', $this->meta($asset)['alt_altlingo']);

        // Allowed site through the same endpoint works.
        $this->actingAs($user)->postJson('/cp/altlingo/cell', ['id' => $asset->id(), 'site' => 'en', 'text' => 'Sea terrace'])->assertOk();
        $this->assertSame('Sea terrace', $this->meta($asset)['alt_altlingo']['en']);

        // The asset form's value goes through the same check.
        $this->actingAs($user);
        $fresh = \Statamic\Facades\Asset::find($asset->id());
        $fresh->set('alt', ['__altlingo' => true, 'main' => 'Terraza', 'sites' => ['en' => 'Sea terrace', 'ca' => 'Terrassa']]);
        try {
            $fresh->save();
            $this->fail('Saving a ca text without ca access must be refused');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertArrayNotHasKey('ca', $this->meta($asset)['alt_altlingo']);
    }
}
