<?php

namespace Database\Factories;

use App\Models\Authorization;
use App\Models\Client;
use App\Models\Crp;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorizationFactory extends Factory
{
    protected $model = Authorization::class;

    public function definition(): array
    {
        $authorized = fake()->numberBetween(10, 100);

        return [
            'crp_id'               => Crp::factory(),
            'client_id'            => Client::factory(),
            'authorization_number' => strtoupper(fake()->bothify('AUTH-######')),
            'service_code'         => fake()->randomElement(['963X', '964X', '122X']),
            'service_type'         => fake()->randomElement(['Pre-ETS', 'Job Coaching', 'Benefits Counseling']),
            'start_date'           => now()->subMonths(2),
            'end_date'             => now()->addMonths(4),
            'authorized_units'     => $authorized,
            'units_used'           => fake()->numberBetween(0, $authorized),
            'vrc_name'             => fake()->name(),
            'vrc_email'            => fake()->safeEmail(),
            'district_office'      => fake()->city() . ' District',
            'status'               => 'active',
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'end_date' => now()->subDays(10),
            'status'   => 'expired',
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attrs) => [
            'units_used' => $attrs['authorized_units'],
            'status'     => 'exhausted',
        ]);
    }
}
