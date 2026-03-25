<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\ZonedDateTime;

final class ZonedDateTimeLocalComponentsTest extends TestCase
{
    public function testUtcLocalComponents(): void
    {
        // 2020-01-15T12:30:45.123456789Z
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 12, minute: 30, second: 45, month: 1, day: 15, year: 2020);
        $subNs = 123_456_789;
        $epochNs = ($epochSec * 1_000_000_000) + $subNs;

        $zdt = new ZonedDateTime($epochNs, 'UTC');

        static::assertSame(2020, $zdt->year);
        static::assertSame(1, $zdt->month);
        static::assertSame(15, $zdt->day);
        static::assertSame(12, $zdt->hour);
        static::assertSame(30, $zdt->minute);
        static::assertSame(45, $zdt->second);
        static::assertSame(123, $zdt->millisecond);
        static::assertSame(456, $zdt->microsecond);
        static::assertSame(789, $zdt->nanosecond);
        static::assertSame('+00:00', $zdt->offset);
        static::assertSame(0, $zdt->offsetNanoseconds);
    }

    public function testPositiveFixedOffsetLocalComponents(): void
    {
        // Epoch 0 in +05:30 is 1970-01-01T05:30:00+05:30
        $zdt = new ZonedDateTime(0, '+05:30');

        static::assertSame(1970, $zdt->year);
        static::assertSame(1, $zdt->month);
        static::assertSame(1, $zdt->day);
        static::assertSame(5, $zdt->hour);
        static::assertSame(30, $zdt->minute);
        static::assertSame(0, $zdt->second);
        static::assertSame('+05:30', $zdt->offset);
        static::assertSame(19_800_000_000_000, $zdt->offsetNanoseconds);
    }

    public function testNegativeFixedOffsetLocalComponents(): void
    {
        // Epoch 0 in -05:00 is 1969-12-31T19:00:00-05:00
        $zdt = new ZonedDateTime(0, '-05:00');

        static::assertSame(1969, $zdt->year);
        static::assertSame(12, $zdt->month);
        static::assertSame(31, $zdt->day);
        static::assertSame(19, $zdt->hour);
        static::assertSame(0, $zdt->minute);
        static::assertSame('-05:00', $zdt->offset);
    }

    public function testEpochMilliseconds(): void
    {
        // 1_000_000_000 ns = 1_000 ms
        $zdt = new ZonedDateTime(1_000_000_000, 'UTC');

        static::assertSame(1_000, $zdt->epochMilliseconds);
    }

    public function testEpochMillisecondsNegative(): void
    {
        // -1_000_000 ns = floor(-1_000_000 / 1_000_000) = -1 ms
        $zdt = new ZonedDateTime(-1_000_000, 'UTC');

        static::assertSame(-1, $zdt->epochMilliseconds);
    }

    public function testCalendarProperties(): void
    {
        // 2024-02-29T00:00:00Z (2024 is a leap year)
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 0, minute: 0, second: 0, month: 2, day: 29, year: 2024);
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');

        static::assertSame('M02', $zdt->monthCode);
        static::assertSame(29, $zdt->daysInMonth); // Feb has 29 in leap year
        static::assertSame(7, $zdt->daysInWeek);
        static::assertSame(366, $zdt->daysInYear);
        static::assertSame(12, $zdt->monthsInYear);
        static::assertTrue($zdt->inLeapYear);
        static::assertNull($zdt->era);
        static::assertNull($zdt->eraYear);
        static::assertSame(24, $zdt->hoursInDay);
        static::assertSame('iso8601', $zdt->calendarId);
    }

    public function testDayOfWeek(): void
    {
        // 2020-01-01 is a Wednesday (3)
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 0, minute: 0, second: 0, month: 1, day: 1, year: 2020);
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');

        static::assertSame(3, $zdt->dayOfWeek);
    }

    public function testDayOfYear(): void
    {
        // Feb 1 is day 32.
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 0, minute: 0, second: 0, month: 2, day: 1, year: 2020);
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');

        static::assertSame(32, $zdt->dayOfYear);
    }
}
