<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\BookingData;
use App\Data\GuestData;
use App\Data\RoomData;
use App\Data\RoomTypeData;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\PmsApiService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Data;

class SyncPmsBookingsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pms:sync-bookings {--since= : Only sync bookings updated after this date (YYYY-MM-DD)}';

    /**
     * @var string
     */
    protected $description = 'Sync hotel bookings from the external PMS API';

    /**
     * @throws \Throwable
     * @throws ConnectionException
     */
    public function handle(PmsApiService $pmsApi): int
    {
        $sinceOption = $this->option('since');
        $since = is_string($sinceOption) ? CarbonImmutable::parse($sinceOption) : null;

        $this->info('Fetching booking IDs from PMS API...');
        Log::info('PMS sync started', ['since' => $since?->toDateString()]);

        $bookingIds = $pmsApi->getBookingIds($since);
        $totalBookings = count($bookingIds);

        $this->info("Found {$totalBookings} bookings to sync.");

        if ($totalBookings === 0) {
            $this->info('Nothing to sync.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalBookings);
        $bar->start();

        /** @var array<BookingData> $bookingsData */
        $bookingsData = [];
        $roomIds = [];
        $roomTypeIds = [];
        $guestIds = [];
        $failedCount = 0;

        foreach ($bookingIds as $bookingId) {
            try {
                $booking = $pmsApi->getBooking($bookingId);
                $bookingsData[] = $booking;

                $roomIds[$booking->room_id] = true;
                $roomTypeIds[$booking->room_type_id] = true;

                foreach ($booking->guest_ids as $guestId) {
                    $guestIds[$guestId] = true;
                }
            } catch (\Throwable $e) {
                $failedCount++;
                Log::warning("Failed to fetch booking {$bookingId}", ['error' => $e->getMessage()]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Fetching related room types, rooms, and guests...');

        /** @var array<RoomTypeData> $roomTypesData */
        $roomTypesData = $this->fetchRelatedEntities(
            array_keys($roomTypeIds),
            fn (int $id) => $pmsApi->getRoomType($id),
            'room type'
        );

        /** @var array<RoomData> $roomsData */
        $roomsData = $this->fetchRelatedEntities(
            array_keys($roomIds),
            fn (int $id) => $pmsApi->getRoom($id),
            'room'
        );

        /** @var array<GuestData> $guestsData */
        $guestsData = $this->fetchRelatedEntities(
            array_keys($guestIds),
            fn (int $id) => $pmsApi->getGuest($id),
            'guest'
        );

        $this->info('Upserting records into database...');

        DB::transaction(function () use ($roomTypesData, $roomsData, $guestsData, $bookingsData) {
            foreach ($roomTypesData as $roomType) {
                RoomType::query()->updateOrCreate(
                    ['id' => $roomType->id],
                    [
                        'name' => $roomType->name,
                        'description' => $roomType->description,
                    ]
                );
            }

            foreach ($roomsData as $room) {
                Room::query()->updateOrCreate(
                    ['id' => $room->id],
                    [
                        'number' => $room->number,
                        'floor' => $room->floor,
                    ]
                );
            }

            foreach ($guestsData as $guest) {
                Guest::query()->updateOrCreate(
                    ['id' => $guest->id],
                    [
                        'first_name' => $guest->first_name,
                        'last_name' => $guest->last_name,
                        'email' => $guest->email,
                    ]
                );
            }

            foreach ($bookingsData as $booking) {
                $bookingModel = Booking::query()->updateOrCreate(
                    ['id' => $booking->id],
                    [
                        'external_id' => $booking->external_id,
                        'arrival_date' => $booking->arrival_date,
                        'departure_date' => $booking->departure_date,
                        'room_id' => $booking->room_id,
                        'room_type_id' => $booking->room_type_id,
                        'status' => $booking->status,
                        'notes' => $booking->notes,
                    ]
                );

                $bookingModel->guests()->sync($booking->guest_ids);
            }
        });

        $syncedCount = count($bookingsData);

        $this->info("Sync complete: {$syncedCount} bookings synced, {$failedCount} failed.");
        Log::info('PMS sync completed', ['synced' => $syncedCount, 'failed' => $failedCount]);

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @template T of Data
     *
     * @param  list<int>  $ids
     * @param  callable(int): T  $fetcher
     * @return list<T>
     */
    private function fetchRelatedEntities(array $ids, callable $fetcher, string $entityName): array
    {
        $data = [];

        foreach ($ids as $id) {
            try {
                $data[] = $fetcher($id);
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch {$entityName} {$id}", ['error' => $e->getMessage()]);
            }
        }

        return $data;
    }
}
