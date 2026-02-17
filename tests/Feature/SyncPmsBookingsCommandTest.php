<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncPmsBookingsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakePmsResponses(): void
    {
        Http::fake([
            '*/api/bookings' => Http::response([
                'data' => [1001],
            ]),
            '*/api/bookings/1001' => Http::response([
                'id' => 1001,
                'external_id' => 'EXT-1001',
                'arrival_date' => '2025-08-01',
                'departure_date' => '2025-08-05',
                'room_id' => 10,
                'room_type_id' => 1,
                'guest_ids' => [100, 101],
                'status' => 'confirmed',
                'notes' => 'Late check-in',
            ]),
            '*/api/room-types/1' => Http::response([
                'id' => 1,
                'name' => 'Deluxe Suite',
                'description' => 'A deluxe suite with sea view',
            ]),
            '*/api/rooms/10' => Http::response([
                'id' => 10,
                'number' => '101',
                'floor' => 1,
            ]),
            '*/api/guests/100' => Http::response([
                'id' => 100,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ]),
            '*/api/guests/101' => Http::response([
                'id' => 101,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
            ]),
        ]);
    }

    public function test_it_syncs_bookings_from_pms_api(): void
    {
        $this->fakePmsResponses();

        $this->artisan('pms:sync-bookings')
            ->assertSuccessful();

        $this->assertDatabaseHas('room_types', [
            'id' => 1,
            'name' => 'Deluxe Suite',
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => 10,
            'number' => '101',
            'floor' => 1,
        ]);

        $this->assertDatabaseHas('guests', [
            'id' => 100,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertDatabaseHas('guests', [
            'id' => 101,
            'first_name' => 'Jane',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => 1001,
            'external_id' => 'EXT-1001',
            'status' => 'confirmed',
            'notes' => 'Late check-in',
        ]);

        $booking = Booking::query()->find(1001);
        $this->assertCount(2, $booking->guests);
        $this->assertTrue($booking->guests->contains(100));
        $this->assertTrue($booking->guests->contains(101));
    }

    public function test_it_handles_empty_booking_list(): void
    {
        Http::fake([
            '*/api/bookings' => Http::response(['data' => []]),
        ]);

        $this->artisan('pms:sync-bookings')
            ->assertSuccessful();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_it_passes_since_option_as_query_parameter(): void
    {
        Http::fake([
            '*/api/bookings*' => Http::response(['data' => []]),
        ]);

        $this->artisan('pms:sync-bookings', ['--since' => '2025-07-20'])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'updated_at.gt=2025-07-20');
        });
    }

    public function test_it_is_idempotent(): void
    {
        $this->fakePmsResponses();

        $this->artisan('pms:sync-bookings')->assertSuccessful();
        $this->artisan('pms:sync-bookings')->assertSuccessful();

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('rooms', 1);
        $this->assertDatabaseCount('room_types', 1);
        $this->assertDatabaseCount('guests', 2);

        $booking = Booking::query()->find(1001);
        $this->assertCount(2, $booking->guests);
    }

    public function test_it_updates_existing_records_on_resync(): void
    {
        RoomType::query()->create(['id' => 1, 'name' => 'Deluxe Suite', 'description' => 'Original']);
        Room::query()->create(['id' => 10, 'number' => '101', 'floor' => 1]);
        Guest::query()->create(['id' => 100, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com']);
        Guest::query()->create(['id' => 101, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com']);
        $booking = Booking::query()->create([
            'id' => 1001, 'external_id' => 'EXT-1001', 'arrival_date' => '2025-08-01',
            'departure_date' => '2025-08-05', 'room_id' => 10, 'room_type_id' => 1,
            'status' => 'confirmed', 'notes' => 'Late check-in',
        ]);
        $booking->guests()->sync([100, 101]);

        Http::fake([
            '*/api/bookings' => Http::response(['data' => [1001]]),
            '*/api/bookings/1001' => Http::response([
                'id' => 1001, 'external_id' => 'EXT-1001', 'arrival_date' => '2025-08-02',
                'departure_date' => '2025-08-06', 'room_id' => 10, 'room_type_id' => 1,
                'guest_ids' => [100], 'status' => 'checked_in', 'notes' => null,
            ]),
            '*/api/room-types/1' => Http::response([
                'id' => 1, 'name' => 'Deluxe Suite Updated', 'description' => null,
            ]),
            '*/api/rooms/10' => Http::response([
                'id' => 10, 'number' => '101', 'floor' => 1,
            ]),
            '*/api/guests/100' => Http::response([
                'id' => 100, 'first_name' => 'John', 'last_name' => 'Doe',
                'email' => 'john.updated@example.com',
            ]),
        ]);

        $this->artisan('pms:sync-bookings')->assertSuccessful();

        $updatedBooking = Booking::query()->find(1001);
        $this->assertEquals('checked_in', $updatedBooking->status);
        $this->assertEquals('2025-08-02', $updatedBooking->arrival_date->toDateString());

        $this->assertDatabaseHas('room_types', [
            'id' => 1,
            'name' => 'Deluxe Suite Updated',
        ]);

        $this->assertDatabaseHas('guests', [
            'id' => 100,
            'email' => 'john.updated@example.com',
        ]);

        $this->assertCount(1, $updatedBooking->guests);
    }

    public function test_it_continues_on_individual_booking_failure(): void
    {
        Http::fake([
            '*/api/bookings' => Http::response(['data' => [1001, 1002]]),
            '*/api/bookings/1001' => Http::response('Server Error', 500),
            '*/api/bookings/1002' => Http::response([
                'id' => 1002,
                'external_id' => 'EXT-1002',
                'arrival_date' => '2025-09-01',
                'departure_date' => '2025-09-03',
                'room_id' => 20,
                'room_type_id' => 2,
                'guest_ids' => [200],
                'status' => 'confirmed',
                'notes' => null,
            ]),
            '*/api/room-types/2' => Http::response([
                'id' => 2,
                'name' => 'Standard',
                'description' => null,
            ]),
            '*/api/rooms/20' => Http::response([
                'id' => 20,
                'number' => '201',
                'floor' => 2,
            ]),
            '*/api/guests/200' => Http::response([
                'id' => 200,
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'email' => 'bob@example.com',
            ]),
        ]);

        $this->artisan('pms:sync-bookings')
            ->assertFailed();

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['id' => 1002]);
    }
}
