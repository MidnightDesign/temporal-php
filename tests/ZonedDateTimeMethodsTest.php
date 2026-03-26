<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use Temporal\PlainTime;
use Temporal\ZonedDateTime;

final class ZonedDateTimeMethodsTest extends TemporalTestCase
{
    public function testToInstantReturnsCorrectEpoch(): void
    {
        $epochNs = 1_000_000_000;
        $zdt = new ZonedDateTime($epochNs, 'UTC');

        $instant = $zdt->toInstant();

        static::assertSame($epochNs, $instant->epochNanoseconds);
    }

    public function testToPlainDate(): void
    {
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 12, minute: 0, second: 0, month: 3, day: 15, year: 2023);
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');

        $pd = $zdt->toPlainDate();

        static::assertSame(2023, $pd->year);
        static::assertSame(3, $pd->month);
        static::assertSame(15, $pd->day);
    }

    public function testToPlainTime(): void
    {
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 14, minute: 35, second: 22, month: 1, day: 1, year: 2020);
        $zdt = new ZonedDateTime(($epochSec * 1_000_000_000) + 500_000_000, 'UTC');

        $pt = $zdt->toPlainTime();

        static::assertSame(14, $pt->hour);
        static::assertSame(35, $pt->minute);
        static::assertSame(22, $pt->second);
        static::assertSame(500, $pt->millisecond);
    }

    public function testToPlainDateTime(): void
    {
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 8, minute: 45, second: 0, month: 6, day: 20, year: 2022);
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');

        $pdt = $zdt->toPlainDateTime();

        static::assertSame(2022, $pdt->year);
        static::assertSame(6, $pdt->month);
        static::assertSame(20, $pdt->day);
        static::assertSame(8, $pdt->hour);
        static::assertSame(45, $pdt->minute);
    }

    public function testWithTimeZoneChangesTimezone(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');
        $result = $zdt->withTimeZone('+05:30');

        static::assertSame(0, $result->epochNanoseconds);
        static::assertSame('+05:30', $result->timeZoneId);
    }

    public function testWithCalendarReturnsSameForIso8601(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');
        $result = $zdt->withCalendar('iso8601');

        static::assertSame('iso8601', $result->calendarId);
        static::assertSame(0, $result->epochNanoseconds);
    }

    public function testWithCalendarThrowsForUnsupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(0, 'UTC')->withCalendar('gregory');
    }

    public function testEqualsReturnsTrueForSameData(): void
    {
        $a = new ZonedDateTime(1000, 'UTC');
        $b = new ZonedDateTime(1000, 'UTC');

        static::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentEpoch(): void
    {
        $a = new ZonedDateTime(1000, 'UTC');
        $b = new ZonedDateTime(2000, 'UTC');

        static::assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentTimezone(): void
    {
        // Same epoch, different timezone → same instant but different ZDT
        $a = new ZonedDateTime(0, 'UTC');
        $b = new ZonedDateTime(0, '+05:30');

        static::assertFalse($a->equals($b));
    }

    public function testValueOfThrows(): void
    {
        $this->expectException(\TypeError::class);
        new ZonedDateTime(0, 'UTC')->valueOf();
    }

    public function testWithPlainTimeMidnight(): void
    {
        // Start from noon UTC
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 12, minute: 0, second: 0, month: 1, day: 1, year: 2020);
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');

        $result = $zdt->withPlainTime();

        static::assertSame(2020, $result->year);
        static::assertSame(1, $result->month);
        static::assertSame(1, $result->day);
        static::assertSame(0, $result->hour);
        static::assertSame(0, $result->minute);
        static::assertSame(0, $result->second);
    }

    public function testWithPlainTimeAcceptsPlainTimeObject(): void
    {
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 0, minute: 0, second: 0, month: 1, day: 1, year: 2020);
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');

        $pt = new PlainTime(14, 30, 0);
        $result = $zdt->withPlainTime($pt);

        static::assertSame(14, $result->hour);
        static::assertSame(30, $result->minute);
        static::assertSame(0, $result->second);
    }
}
