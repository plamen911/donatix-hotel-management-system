<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $arrivalDate = fake()->dateTimeBetween('now', '+6 months');

        return [
            'id' => fake()->unique()->randomNumber(5),
            'external_id' => (string) fake()->unique()->randomNumber(5),
            'arrival_date' => $arrivalDate,
            'departure_date' => fake()->dateTimeBetween($arrivalDate, (clone $arrivalDate)->modify('+14 days')),
            'room_id' => Room::factory(),
            'room_type_id' => RoomType::factory(),
            'status' => fake()->randomElement(['confirmed', 'checked_in', 'checked_out', 'cancelled']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
