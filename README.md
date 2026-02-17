# Donatix Hotel Management System

A Laravel application that syncs hotel booking data from an external Property Management System (PMS) API. It fetches bookings, rooms, room types, and guest information, storing everything locally for further processing.

## Tech Stack

- **PHP** 8.2+
- **Laravel** 12
- **SQLite** (default)
- **Spatie Laravel Data** for type-safe DTOs
- **PHPUnit** for testing
- **Tailwind CSS** 4

## Setup

```bash
git clone git@github.com:plamen911/donatix-hotel-management-system.git
cd donatix-hotel-management-system
composer setup
```

This will install dependencies, generate an app key, run migrations, and build frontend assets.

## Development

Start all services (server, queue, logs, Vite) concurrently:

```bash
composer run dev
```

## PMS Sync

Sync all bookings from the external PMS API:

```bash
php artisan pms:sync-bookings
```

Sync only bookings updated after a specific date:

```bash
php artisan pms:sync-bookings --since=2025-07-20
```

## Project Structure

```
app/
├── Console/Commands/
│   └── SyncPmsBookingsCommand.php   # Artisan command for PMS sync
├── Data/                            # Spatie Data DTOs
│   ├── BookingData.php
│   ├── GuestData.php
│   ├── RoomData.php
│   └── RoomTypeData.php
├── Models/
│   ├── Booking.php
│   ├── Guest.php
│   ├── Room.php
│   ├── RoomType.php
│   └── User.php
└── Services/
    └── PmsApiService.php            # PMS API client with rate limiting
```

## Testing

Run the full test suite:

```bash
php artisan test
```

Run a specific test:

```bash
php artisan test --filter=PmsApiServiceTest
```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
vendor/bin/pint
```

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
