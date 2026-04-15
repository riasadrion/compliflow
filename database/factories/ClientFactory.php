<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Crp;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'crp_id'             => Crp::factory(),
            'first_name'         => fake()->firstName(),
            'last_name'          => fake()->lastName(),
            'dob'                => fake()->date('Y-m-d', '-16 years'),
            'ssn_last_four'      => (string) fake()->numberBetween(1000, 9999),
            'address'            => fake()->streetAddress(),
            'phone'              => fake()->phoneNumber(),
            'email'              => fake()->safeEmail(),
            'eligibility_status' => 'pending',
        ];
    }

    public function eligible(): static
    {
        return $this->state(['eligibility_status' => 'eligible']);
    }

    public function withAllDocuments(): static
    {
        return $this->state([
            'proof_of_disability_received_at' => now()->subDays(10),
            'iep_received_at'                 => now()->subDays(8),
            'consent_form_signed_at'          => now()->subDays(5),
        ]);
    }
}
