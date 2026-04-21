<?php

namespace Database\Factories;

use App\Models\Authorization;
use App\Models\Client;
use App\Models\Crp;
use App\Models\ServiceLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceLogFactory extends Factory
{
    protected $model = ServiceLog::class;

    public function definition(): array
    {
        return [
            'crp_id'          => Crp::factory(),
            'client_id'       => Client::factory(),
            'authorization_id'=> Authorization::factory(),
            'user_id'         => User::factory(),
            'form_type'       => fake()->randomElement(['963X', '964X', '122X']),
            'service_code'    => fake()->randomElement(['963X', '964X', '122X']),
            'service_date'    => now()->subDays(fake()->numberBetween(1, 5)),
            'start_time'      => '09:00:00',
            'end_time'        => '10:00:00',
            'units'           => 1,
            'report_status'   => 'draft',
            'notes'           => fake()->sentence(20), // >50 chars
            'custom_fields'   => [],
        ];
    }

    public function withSignature(): static
    {
        return $this->state(['custom_fields' => ['signature_present' => 'yes']]);
    }

    public function withPayroll(): static
    {
        return $this->state(fn (array $attrs) => [
            'custom_fields' => array_merge($attrs['custom_fields'] ?? [], ['payroll_data' => 'filled']),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(['report_status' => 'submitted']);
    }

    public function forForm(string $formType): static
    {
        return $this->state(['form_type' => $formType, 'service_code' => $formType]);
    }
}
