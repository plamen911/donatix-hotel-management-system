<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\BookingData;
use App\Data\GuestData;
use App\Data\RoomData;
use App\Data\RoomTypeData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PmsApiService
{
    private float $lastRequestTime = 0;

    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::baseUrl(config('pms.base_url'))
            ->acceptJson()
            ->throw();
    }

    /**
     * @return list<int>
     * @throws ConnectionException
     */
    public function getBookingIds(?CarbonImmutable $updatedAfter = null): array
    {
        $query = $updatedAfter ? ['updated_at.gt' => $updatedAfter->toDateString()] : [];

        return $this->request('/api/bookings', $query)['data'] ?? [];
    }

    /**
     * @throws ConnectionException
     */
    public function getBooking(int $id): BookingData
    {
        return BookingData::from($this->request("/api/bookings/{$id}"));
    }

    /**
     * @throws ConnectionException
     */
    public function getRoom(int $id): RoomData
    {
        return RoomData::from($this->request("/api/rooms/{$id}"));
    }

    /**
     * @throws ConnectionException
     */
    public function getRoomType(int $id): RoomTypeData
    {
        return RoomTypeData::from($this->request("/api/room-types/{$id}"));
    }

    /**
     * @throws ConnectionException
     */
    public function getGuest(int $id): GuestData
    {
        return GuestData::from($this->request("/api/guests/{$id}"));
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     * @throws ConnectionException
     */
    private function request(string $endpoint, array $query = []): array
    {
        $this->rateLimit();

        return $this->client->get($endpoint, $query)->json();
    }

    private function rateLimit(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRequestTime;
        $minInterval = 0.5; // 2 requests per second

        if ($elapsed < $minInterval) {
            usleep((int) (($minInterval - $elapsed) * 1_000_000));
        }

        $this->lastRequestTime = microtime(true);
    }
}
