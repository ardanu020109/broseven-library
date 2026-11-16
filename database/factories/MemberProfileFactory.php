<?php

namespace Database\Factories;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MemberProfile>
 */
class MemberProfileFactory extends Factory
{
    public function definition(): array
    {
        $joined = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'user_id' => User::factory(),
            'member_number' => 'BS' . now()->format('y') . fake()->unique()->numerify('#####'),
            'joined_at' => $joined->format('Y-m-d'),
            'expired_at' => (clone $joined)->modify('+1 year')->format('Y-m-d'),
            'status' => MemberProfile::STATUS_ACTIVE,
            'card_token' => Str::random(64),
        ];
    }
}
