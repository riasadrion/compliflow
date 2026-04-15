<?php

namespace Database\Factories;

use App\Models\Crp;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrpFactory extends Factory
{
    protected $model = Crp::class;

    public function definition(): array
    {
        return [
            'name'      => fake()->company(),
            'email'     => fake()->companyEmail(),
            'address'   => fake()->address(),
            'phone'     => fake()->phoneNumber(),
            'vendor_id' => strtoupper(fake()->bothify('VND-####')),
            'is_active' => true,
        ];
    }
}
