<?php

namespace Tests\Feature;

use App\Filament\Resources\AuthorizationResource\Pages\CreateAuthorization;
use App\Filament\Resources\AuthorizationResource\Pages\EditAuthorization;
use App\Filament\Resources\AuthorizationResource\Pages\ListAuthorizations;
use App\Models\Authorization;
use App\Models\Client;
use App\Models\Crp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Crp $crp;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crp    = Crp::factory()->create();
        $this->client = Client::factory()->create(['crp_id' => $this->crp->id]);
        $this->user   = User::factory()->create([
            'crp_id'      => $this->crp->id,
            'mfa_enabled' => true,
        ]);

        session([
            'mfa_verified_at'  => now()->timestamp,
            'mfa_verified_crp' => $this->crp->id,
        ]);

        $this->actingAs($this->user);
    }

    public function test_list_page_renders(): void
    {
        Livewire::test(ListAuthorizations::class)
            ->assertSuccessful();
    }

    public function test_only_shows_own_crp_authorizations(): void
    {
        $ownAuth   = Authorization::factory()->create([
            'crp_id'    => $this->crp->id,
            'client_id' => $this->client->id,
        ]);
        $otherCrp  = Crp::factory()->create();
        $otherAuth = Authorization::factory()->create([
            'crp_id'    => $otherCrp->id,
            'client_id' => Client::factory()->create(['crp_id' => $otherCrp->id])->id,
        ]);

        Livewire::test(ListAuthorizations::class)
            ->assertCanSeeTableRecords([$ownAuth])
            ->assertCanNotSeeTableRecords([$otherAuth]);
    }

    public function test_can_create_authorization(): void
    {
        Livewire::test(CreateAuthorization::class)
            ->fillForm([
                'client_id'            => $this->client->id,
                'authorization_number' => 'AUTH-001',
                'service_code'         => '963X',
                'service_type'         => 'Pre-ETS',
                'start_date'           => now()->subMonth()->toDateString(),
                'end_date'             => now()->addMonths(5)->toDateString(),
                'authorized_units'     => 40,
                'units_used'           => 0,
                'status'               => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('authorizations', [
            'authorization_number' => 'AUTH-001',
            'crp_id'               => $this->crp->id,
        ]);
    }

    public function test_create_requires_required_fields(): void
    {
        Livewire::test(CreateAuthorization::class)
            ->fillForm([
                'client_id'            => null,
                'authorization_number' => '',
                'service_code'         => '',
                'service_type'         => '',
                'start_date'           => null,
                'end_date'             => null,
                'authorized_units'     => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'client_id', 'authorization_number', 'service_code',
                'service_type', 'start_date', 'end_date', 'authorized_units',
            ]);
    }

    public function test_crp_id_auto_stamped_on_create(): void
    {
        Livewire::test(CreateAuthorization::class)
            ->fillForm([
                'client_id'            => $this->client->id,
                'authorization_number' => 'AUTH-STAMP',
                'service_code'         => '964X',
                'service_type'         => 'Job Coaching',
                'start_date'           => now()->subMonth()->toDateString(),
                'end_date'             => now()->addMonths(3)->toDateString(),
                'authorized_units'     => 20,
                'status'               => 'active',
            ])
            ->call('create');

        $auth = Authorization::withoutCrpScope()->where('authorization_number', 'AUTH-STAMP')->first();
        $this->assertEquals($this->crp->id, $auth->crp_id);
    }

    public function test_units_remaining_computed_correctly(): void
    {
        $auth = Authorization::factory()->create([
            'crp_id'           => $this->crp->id,
            'client_id'        => $this->client->id,
            'authorized_units' => 50,
            'units_used'       => 18,
        ]);

        $this->assertEquals(32, $auth->units_remaining);
        $this->assertEquals(36.0, $auth->units_percent_used);
    }

    public function test_units_remaining_never_negative(): void
    {
        $auth = Authorization::factory()->create([
            'crp_id'           => $this->crp->id,
            'client_id'        => $this->client->id,
            'authorized_units' => 10,
            'units_used'       => 15, // over-used
        ]);

        $this->assertEquals(0, $auth->units_remaining);
    }

    public function test_can_edit_authorization(): void
    {
        $auth = Authorization::factory()->create([
            'crp_id'    => $this->crp->id,
            'client_id' => $this->client->id,
            'status'    => 'active',
        ]);

        Livewire::test(EditAuthorization::class, ['record' => $auth->id])
            ->fillForm(['status' => 'expired'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('expired', $auth->fresh()->status);
    }

    public function test_audit_log_written_on_create(): void
    {
        Livewire::test(CreateAuthorization::class)
            ->fillForm([
                'client_id'            => $this->client->id,
                'authorization_number' => 'AUTH-AUDIT',
                'service_code'         => '122X',
                'service_type'         => 'Benefits Counseling',
                'start_date'           => now()->subMonth()->toDateString(),
                'end_date'             => now()->addMonths(3)->toDateString(),
                'authorized_units'     => 30,
                'status'               => 'pending',
            ])
            ->call('create');

        $this->assertDatabaseHas('crp_audit_logs', [
            'crp_id'      => $this->crp->id,
            'user_id'     => $this->user->id,
            'action'      => 'authorization_created',
            'entity_type' => 'authorization',
        ]);
    }
}
