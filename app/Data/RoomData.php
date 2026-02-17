<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class RoomData extends Data
{
    public function __construct(
        public int $id,
        public string $number,
        public int $floor,
    ) {}
}
