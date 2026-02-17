<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class BookingData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $arrival_date,
        public string $departure_date,
        public int $room_id,
        public int $room_type_id,
        /** @var list<int> */
        public array $guest_ids,
        public string $status,
        public ?string $notes,
    ) {}
}
