<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use PHPUnit\Framework\TestCase;
use Temporal\Duration;
use Temporal\PlainTime;
use Temporal\Tests\Porcelain\Constraint\DurationEquals;
use Temporal\Tests\Porcelain\Constraint\PlainTimeEquals;

abstract class TemporalTestCase extends TestCase
{
    public const int YEAR_MIN = -271821;
    public const int YEAR_MAX = 275760;

    protected function assertPlainTimeIs(
        int $h,
        int $min,
        int $sec,
        int $ms,
        int $us,
        int $ns,
        PlainTime $time,
        string $message = '',
    ): void {
        self::assertThat($time, new PlainTimeEquals($h, $min, $sec, $ms, $us, $ns), $message);
    }

    protected function assertDurationIs(
        int $years,
        int $months,
        int $weeks,
        int $days,
        int $hours,
        int $minutes,
        int $seconds,
        int $ms,
        int $us,
        int $ns,
        Duration $d,
        string $message = '',
    ): void {
        self::assertThat(
            $d,
            new DurationEquals($years, $months, $weeks, $days, $hours, $minutes, $seconds, $ms, $us, $ns),
            $message,
        );
    }
}
