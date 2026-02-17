<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\BookingData;
use App\Data\GuestData;
use App\Data\RoomData;
use App\Data\RoomTypeData;
use App\Services\PmsApiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PmsApiServiceTest extends TestCase
{
    public function test_get_booking_ids_returns_array_of_ids(): void
    {
        Http::fake([
            '*/api/bookings' => Http::response(['data' => [1001, 1002, 1003]]),
        ]);

        $service = new PmsApiService;
        $ids = $service->getBookingIds();

        $this->assertEquals([1001, 1002, 1003], $ids);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/bookings')
                && ! str_contains($request->url(), 'updated_at');
        });
    }

    public function test_get_booking_ids_passes_updated_after_filter(): void
    {
        Http::fake([
            '*/api/bookings*' => Http::response(['data' => [1001]]),
        ]);

        $service = new PmsApiService;
        $service->getBookingIds(CarbonImmutable::parse('2025-07-20'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'updated_at.gt=2025-07-20');
        });
    }

    public function test_get_booking_returns_booking_data(): void
    {
        Http::fake([
            '*/api/bookings/1001' => Http::response([
                'id' => 1001,
                'external_id' => 'EXT-1001',
                'arrival_date' => '2025-08-01',
                'departure_date' => '2025-08-05',
                'room_id' => 10,
                'room_type_id' => 1,
                'guest_ids' => [100],
                'status' => 'confirmed',
                'notes' => null,
            ]),
        ]);

        $service = new PmsApiService;
        $result = $service->getBooking(1001);

        $this->assertInstanceOf(BookingData::class, $result);
        $this->assertSame(1001, $result->id);
        $this->assertSame('EXT-1001', $result->external_id);
        $this->assertSame('2025-08-01', $result->arrival_date);
        $this->assertSame('2025-08-05', $result->departure_date);
        $this->assertSame(10, $result->room_id);
        $this->assertSame(1, $result->room_type_id);
        $this->assertSame([100], $result->guest_ids);
        $this->assertSame('confirmed', $result->status);
        $this->assertNull($result->notes);
    }

    public function test_get_room_returns_room_data(): void
    {
        Http::fake([
            '*/api/rooms/10' => Http::response(['id' => 10, 'number' => '101', 'floor' => 1]),
        ]);

        $service = new PmsApiService;
        $result = $service->getRoom(10);

        $this->assertInstanceOf(RoomData::class, $result);
        $this->assertSame(10, $result->id);
        $this->assertSame('101', $result->number);
        $this->assertSame(1, $result->floor);
    }

    public function test_get_room_type_returns_room_type_data(): void
    {
        Http::fake([
            '*/api/room-types/1' => Http::response(['id' => 1, 'name' => 'Deluxe', 'description' => 'A deluxe room']),
        ]);

        $service = new PmsApiService;
        $result = $service->getRoomType(1);

        $this->assertInstanceOf(RoomTypeData::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('Deluxe', $result->name);
        $this->assertSame('A deluxe room', $result->description);
    }

    public function test_get_guest_returns_guest_data(): void
    {
        Http::fake([
            '*/api/guests/100' => Http::response([
                'id' => 100,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ]),
        ]);

        $service = new PmsApiService;
        $result = $service->getGuest(100);

        $this->assertInstanceOf(GuestData::class, $result);
        $this->assertSame(100, $result->id);
        $this->assertSame('John', $result->first_name);
        $this->assertSame('Doe', $result->last_name);
        $this->assertSame('john@example.com', $result->email);
    }

    public function test_it_throws_on_server_error(): void
    {
        Http::fake([
            '*/api/bookings' => Http::response('Server Error', 500),
        ]);

        $this->expectException(RequestException::class);

        $service = new PmsApiService;
        $service->getBookingIds();
    }
}
