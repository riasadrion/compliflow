<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Models\Client;
use App\Models\Crp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Crp $crp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crp  = Crp::factory()->create();
        $this->user = User::factory()->create([
            'crp_id'      => $this->crp->id,
            'mfa_enabled' => true,
        ]);

        // Bypass MFA middleware for these tests
        session([
            'mfa_verified_at'  => now()->timestamp,
            'mfa_verified_crp' => $this->crp->id,
        ]);

        $this->actingAs($this->user);
    }

    public function test_list_page_renders(): void
    {
        Livewire::test(ListClients::class)
            ->assertSuccessful();
    }

    public function test_only_shows_own_crp_clients(): void
    {
        $ownClient   = Client::factory()->create(['crp_id' => $this->crp->id]);
        $otherClient = Client::factory()->create(['crp_id' => Crp::factory()->create()->id]);

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$ownClient])
            ->assertCanNotSeeTableRecords([$otherClient]);
    }

    public function test_can_create_client(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'first_name'         => 'Jane',
                'last_name'          => 'Doe',
                'dob'                => '2005-03-15',
                'eligibility_status' => 'pending',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clients', [
            'crp_id' => $this->crp->id,
        ]);
    }

    public function test_create_requires_name_and_dob(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'first_name' => '',
                'last_name'  => '',
                'dob'        => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['first_name', 'last_name', 'dob']);
    }

    public function test_crp_id_auto_stamped_on_create(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'first_name'         => 'John',
                'last_name'          => 'Smith',
                'dob'                => '2004-06-01',
                'eligibility_status' => 'eligible',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::withoutCrpScope()->latest()->first();
        $this->assertEquals($this->crp->id, $client->crp_id);
    }

    public function test_can_edit_client(): void
    {
        $client = Client::factory()->create(['crp_id' => $this->crp->id]);

        Livewire::test(EditClient::class, ['record' => $client->id])
            ->fillForm(['eligibility_status' => 'eligible'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('eligible', $client->fresh()->eligibility_status);
    }

    public function test_documents_badge_reflects_received_count(): void
    {
        $noDocsClient  = Client::factory()->create(['crp_id' => $this->crp->id]);
        $allDocsClient = Client::factory()->withAllDocuments()->create(['crp_id' => $this->crp->id]);

        $this->assertCount(3, $noDocsClient->missing_documents);
        $this->assertTrue($allDocsClient->hasAllRequiredDocuments());
        $this->assertCount(0, $allDocsClient->missing_documents);
    }

    public function test_audit_log_written_on_create(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'first_name'         => 'Audit',
                'last_name'          => 'Test',
                'dob'                => '2003-01-01',
                'eligibility_status' => 'pending',
            ])
            ->call('create');

        $this->assertDatabaseHas('crp_audit_logs', [
            'crp_id'      => $this->crp->id,
            'user_id'     => $this->user->id,
            'action'      => 'client_created',
            'entity_type' => 'client',
        ]);
    }
}
