<?php

declare(strict_types=1);

namespace App\Enums;

use MoonShine\Support\Enums\Color;

enum ScanStatus: string
{
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function color(): Color
    {
        return match ($this) {
            self::RUNNING => Color::BLUE,
            self::SUCCESS => Color::GREEN,
            self::FAILED => Color::RED,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'Running',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
        };
    }

    public static function colorFor(?string $status): Color
    {
        return self::tryFrom((string) $status)?->color() ?? Color::GRAY;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(static fn (self $status): array => [$status->value, $status->label()], self::cases()),
            1,
            0,
        );
    }
}
