<?php

namespace Tests\Feature;

use App\Models\IndexedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Guest / Client / Administrator permissions, soft deletes and restores. */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(): IndexedFile
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('indexing/prices/sample.csv', 'a,b');

        return IndexedFile::create([
            'title' => 'Sample', 'category' => 'prices', 'item' => 'Palay',
            'period_type' => 'yearly', 'period_value' => '2025', 'tags' => [],
            'original_name' => 'sample.csv', 'path' => 'indexing/prices/sample.csv',
            'extension' => 'csv', 'mime' => 'text/csv', 'size' => 3,
        ]);
    }

    public function test_guest_can_view_the_list_but_not_download_or_change_anything(): void
    {
        $file = $this->makeFile();

        $this->get('/')->assertOk();
        $this->getJson('/files')->assertOk()->assertJsonPath('total', 1);
        $this->get(route('files.download', $file))->assertRedirect(route('login'));
        $this->postJson('/files/download-selected', ['ids' => [$file->id]])->assertUnauthorized();
        $this->putJson("/files/{$file->id}", [])->assertUnauthorized();
        $this->deleteJson("/files/{$file->id}")->assertUnauthorized();
        $this->getJson('/accounts')->assertUnauthorized();
        $this->getJson('/files?trashed=1')->assertForbidden();
    }

    public function test_client_can_download_but_not_manage(): void
    {
        $file = $this->makeFile();
        $client = User::factory()->create();

        $this->actingAs($client)->get(route('files.download', $file))->assertOk();
        $this->actingAs($client)->putJson("/files/{$file->id}", [])->assertForbidden();
        $this->actingAs($client)->deleteJson("/files/{$file->id}")->assertForbidden();
        $this->actingAs($client)->getJson('/accounts')->assertForbidden();
        $this->actingAs($client)->getJson('/files?trashed=1')->assertForbidden();
    }

    public function test_administrator_can_edit_file_information(): void
    {
        $file = $this->makeFile();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson("/files/{$file->id}", [
            'title' => 'Renamed title', 'description' => 'Notes', 'category' => 'imports', 'item' => 'Rice',
            'period_type' => 'monthly', 'period_value' => 'March', 'region' => null,
            'tags' => ['Rice', 'Imports'], 'file_name' => 'new-name.csv',
        ])->assertOk();

        $file->refresh();
        $this->assertSame('Renamed title', $file->title);
        $this->assertSame('imports', $file->category);
        $this->assertSame(['rice', 'imports'], $file->tags);
        $this->assertSame('new-name.csv', $file->original_name);
    }

    public function test_administrator_can_delete_and_restore_a_file(): void
    {
        $file = $this->makeFile();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->deleteJson("/files/{$file->id}")->assertOk();

        $this->assertSoftDeleted('indexed_files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing($file->path); // no longer reachable through public/storage
        Storage::disk('local')->assertExists($file->path);
        $this->getJson('/files')->assertJsonPath('total', 0);
        $this->actingAs($admin)->getJson('/files?trashed=1')->assertJsonPath('total', 1);
        $this->actingAs($admin)->get(route('files.download', $file))->assertNotFound();

        $this->actingAs($admin)->postJson("/files/{$file->id}/restore")->assertOk();

        $this->assertNotSoftDeleted('indexed_files', ['id' => $file->id]);
        Storage::disk('public')->assertExists($file->path);
        $this->getJson('/files')->assertJsonPath('total', 1);
    }

    public function test_login_logout_and_deleted_accounts(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson('/accounts', [
            'name' => 'Maria Client', 'username' => 'Maria', 'password' => 'secret123', 'role' => 'client',
        ])->assertCreated();

        auth()->logout();

        $this->post('/login', ['username' => 'wrong', 'password' => 'nope'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/login', ['username' => 'maria', 'password' => 'secret123'])->assertRedirect();
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();

        // Delete the account: it can no longer log in. Restore it: it can again.
        $maria = User::where('username', 'maria')->firstOrFail();
        $this->actingAs($admin)->deleteJson("/accounts/{$maria->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $maria->id]);

        auth()->logout();
        $this->post('/login', ['username' => 'maria', 'password' => 'secret123'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->actingAs($admin)->postJson("/accounts/{$maria->id}/restore")->assertOk();
        auth()->logout();
        $this->post('/login', ['username' => 'maria', 'password' => 'secret123'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_administrator_cannot_delete_or_demote_themselves(): void
    {
        $admin = User::factory()->administrator()->create(['username' => 'boss']);

        $this->actingAs($admin)->deleteJson("/accounts/{$admin->id}")->assertStatus(422);

        $this->actingAs($admin)->putJson("/accounts/{$admin->id}", [
            'name' => $admin->name, 'username' => 'boss', 'role' => 'client',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }
}
