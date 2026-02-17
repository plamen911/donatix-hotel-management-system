<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class GuestData extends Data
{
    public function __construct(
        public int $id,
        public string $first_name,
        public string $last_name,
        public string $email,
    ) {}
}
