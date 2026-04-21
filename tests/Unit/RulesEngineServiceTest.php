<?php

namespace Tests\Unit;

use App\Models\Crp;
use App\Models\Client;
use App\Models\ServiceLog;
use App\Services\RulesEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulesEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private RulesEngineService $engine;
    private Crp $crp;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(RulesEngineService::class);
        $this->crp    = Crp::factory()->create();
        $this->client = Client::factory()->create(['crp_id' => $this->crp->id]);
    }

    public function test_ready_when_no_unsubmitted_logs(): void
    {
        $result = $this->engine->evaluate($this->client->id);

        $this->assertEquals(RulesEngineService::STATUS_READY, $result['status']);
        $this->assertEmpty($result['flags']);
    }

    public function test_ready_when_all_submitted(): void
    {
        ServiceLog::factory()->submitted()->create([
            'crp_id'    => $this->crp->id,
            'client_id' => $this->client->id,
        ]);

        $result = $this->engine->evaluate($this->client->id);

        $this->assertEquals(RulesEngineService::STATUS_READY, $result['status']);
    }

    public function test_ready_when_122x_has_auth_no_signature_needed(): void
    {
        ServiceLog::factory()->forForm('122X')->create([
            'crp_id'        => $this->crp->id,
            'client_id'     => $this->client->id,
            'custom_fields' => [],
        ]);

        $result = $this->engine->evaluate($this->client->id);

        // 122X only needs auth (no signature/payroll flags)
        $this->assertEquals(RulesEngineService::STATUS_READY, $result['status']);
    }

    public function test_blocked_when_963x_missing_signature(): void
    {
        ServiceLog::factory()->forForm('963X')->create([
            'crp_id'        => $this->crp->id,
            'client_id'     => $this->client->id,
            'custom_fields' => [],
        ]);

        $result = $this->engine->evaluate($this->client->id);

        $this->assertEquals(RulesEngineService::STATUS_BLOCKED, $result['status']);
        $flags = array_column($result['flags'], 'flag');
        $this->assertContains('missing_signature', $flags);
    }

    public function test_blocked_when_964x_missing_signature_and_payroll(): void
    {
        ServiceLog::factory()->forForm('964X')->create([
            'crp_id'        => $this->crp->id,
            'client_id'     => $this->client->id,
            'custom_fields' => [],
        ]);

        $result = $this->engine->evaluate($this->client->id);

        $this->assertEquals(RulesEngineService::STATUS_BLOCKED, $result['status']);
        $flags = array_column($result['flags'], 'flag');
        $this->assertContains('missing_signature', $flags);
        $this->assertContains('missing_payroll', $flags);
    }

    public function test_blocked_when_964x_has_signature_but_missing_payroll(): void
    {
        ServiceLog::factory()->forForm('964X')->withSignature()->create([
            'crp_id'    => $this->crp->id,
            'client_id' => $this->client->id,
        ]);

        $result = $this->engine->evaluate($this->client->id);

        $this->assertEquals(RulesEngineService::STATUS_BLOCKED, $result['status']);
        $flags = array_column($result['flags'], 'flag');
        $this->assertContains('missing_payroll', $flags);
        $this->assertNotContains('missing_signature', $flags);
    }

    public function test_ready_when_963x_has_signature(): void
    {
        ServiceLog::factory()->forForm('963X')->withSignature()->create([
            'crp_id'    => $this->crp->id,
            'client_id' => $this->client->id,
        ]);

        $result = $this->engine->evaluate($this->client->id);

        $this->assertEquals(RulesEngineService::STATUS_READY, $result['status']);
    }

    public function test_ready_when_964x_has_signature_and_payroll(): void
    {
        ServiceLog::factory()->forForm('964X')->withSignature()->withPayroll()->create([
            'crp_id'    => $this->crp->id,
            'client_id' => $this->client->id,
        ]);

        $result = $this->engine->evaluate($this->client->id);

        $this->assertEquals(RulesEngineService::STATUS_READY, $result['status']);
    }

    public function test_122x_does_not_require_signature_or_payroll(): void
    {
        ServiceLog::factory()->forForm('122X')->create([
            'crp_id'        => $this->crp->id,
            'client_id'     => $this->client->id,
            'custom_fields' => [],
        ]);

        $result = $this->engine->evaluate($this->client->id);

        $flags = array_column($result['flags'], 'flag');
        $this->assertNotContains('missing_signature', $flags);
        $this->assertNotContains('missing_payroll', $flags);
    }

    public function test_evaluate_all_returns_results_for_all_clients(): void
    {
        $client2 = Client::factory()->create(['crp_id' => $this->crp->id]);

        // client1 has a clean 122X log
        ServiceLog::factory()->forForm('122X')->create([
            'crp_id'    => $this->crp->id,
            'client_id' => $this->client->id,
        ]);

        // client2 has a 963X log missing signature
        ServiceLog::factory()->forForm('963X')->create([
            'crp_id'        => $this->crp->id,
            'client_id'     => $client2->id,
            'custom_fields' => [],
        ]);

        $results = $this->engine->evaluateAll($this->crp->id);

        $this->assertCount(2, $results);

        $statuses = $results->pluck('status', 'client_id');
        $this->assertEquals(RulesEngineService::STATUS_READY, $statuses[$this->client->id]);
        $this->assertEquals(RulesEngineService::STATUS_BLOCKED, $statuses[$client2->id]);
    }
}
