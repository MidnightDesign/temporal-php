<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\PlainDateTime;
use Temporal\ZonedDateTime;

final class PlainDateTimeConversionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // toZonedDateTime
    // -------------------------------------------------------------------------

    public function testToZonedDateTimeUtc(): void
    {
        $dt = new PlainDateTime(2020, 1, 1, 0, 0);

        $zdt = $dt->toZonedDateTime('UTC');

        static::assertSame(1_577_836_800_000_000_000, $zdt->epochNanoseconds);
        static::assertSame('iso8601', $zdt->calendarId);
        static::assertSame('UTC', $zdt->timeZoneId);
    }

    public function testToZonedDateTimeWithOffset(): void
    {
        $dt = new PlainDateTime(2020, 1, 1, 0, 0);

        $zdt = $dt->toZonedDateTime('+03:30');

        static::assertSame('+03:30', $zdt->timeZoneId);
    }

    public function testToZonedDateTimeConstantOffsetSameResult(): void
    {
        $dt = new PlainDateTime(2019, 2, 16, 23, 45);

        foreach (['earlier', 'later', 'compatible', 'reject'] as $disambiguation) {
            $zdt = $dt->toZonedDateTime('+03:30', ['disambiguation' => $disambiguation]);
            static::assertSame(1_550_348_100_000_000_000, $zdt->epochNanoseconds, "disambiguation: {$disambiguation}");
        }
    }

    public function testToZonedDateTimeNullThrowsTypeError(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);

        $this->expectException(\TypeError::class);
        $dt->toZonedDateTime(null);
    }

    public function testToZonedDateTimeNumberThrowsTypeError(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);

        $this->expectException(\TypeError::class);
        $dt->toZonedDateTime(42);
    }

    public function testToZonedDateTimeEmptyStringThrows(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);

        $this->expectException(InvalidArgumentException::class);
        $dt->toZonedDateTime('');
    }

    public function testToZonedDateTimeBadOptionsTypeThrows(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);

        $this->expectException(\TypeError::class);
        $dt->toZonedDateTime('UTC', 'some string');
    }

    public function testToZonedDateTimeBadDisambiguationThrows(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);

        $this->expectException(InvalidArgumentException::class);
        $dt->toZonedDateTime('UTC', ['disambiguation' => 'bad']);
    }

    public function testToZonedDateTimeMinBoundaryThrows(): void
    {
        $dt = new PlainDateTime(-271821, 4, 19, 0, 0, 0, 0, 0, 1);

        $this->expectException(InvalidArgumentException::class);
        $dt->toZonedDateTime('UTC');
    }

    public function testToZonedDateTimeMaxBoundaryThrows(): void
    {
        $dt = new PlainDateTime(275760, 9, 13, 0, 0, 0, 0, 0, 1);

        $this->expectException(InvalidArgumentException::class);
        $dt->toZonedDateTime('UTC');
    }

    public function testToZonedDateTimeDatetimeStringAsTimezoneThrows(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);

        $this->expectException(InvalidArgumentException::class);
        $dt->toZonedDateTime('2021-08-19T17:30');
    }

    public function testToZonedDateTimeDatetimeStringWithOffsetAccepted(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);

        $zdt = $dt->toZonedDateTime('2021-08-19T17:30-07:00');

        static::assertSame('-07:00', $zdt->timeZoneId);
    }

    // -------------------------------------------------------------------------
    // withCalendar
    // -------------------------------------------------------------------------

    public function testWithCalendarReturnsNewInstance(): void
    {
        $pdt = new PlainDateTime(1976, 11, 18, 14, 30, 0);

        $result = $pdt->withCalendar('iso8601');

        static::assertNotSame($pdt, $result);
        static::assertSame(1976, $result->year);
        static::assertSame(11, $result->month);
        static::assertSame(18, $result->day);
        static::assertSame(14, $result->hour);
        static::assertSame(30, $result->minute);
        static::assertSame(0, $result->second);
        static::assertSame('iso8601', $result->calendarId);
    }

    public function testWithCalendarPreservesAllTimeFields(): void
    {
        $pdt = new PlainDateTime(2020, 3, 15, 10, 20, 30, 100, 200, 300);

        $result = $pdt->withCalendar('iso8601');

        static::assertSame(100, $result->millisecond);
        static::assertSame(200, $result->microsecond);
        static::assertSame(300, $result->nanosecond);
    }

    public function testWithCalendarAcceptsIsoString(): void
    {
        $pdt = new PlainDateTime(2020, 3, 15);

        $result = $pdt->withCalendar('2020-01-01[u-ca=iso8601]');

        static::assertSame('iso8601', $result->calendarId);
    }

    public function testWithCalendarNullThrowsTypeError(): void
    {
        $pdt = new PlainDateTime(2020, 3, 15);

        $this->expectException(\TypeError::class);
        $pdt->withCalendar(null);
    }

    public function testWithCalendarNumberThrowsTypeError(): void
    {
        $pdt = new PlainDateTime(2020, 3, 15);

        $this->expectException(\TypeError::class);
        $pdt->withCalendar(42);
    }

    public function testWithCalendarEmptyStringThrows(): void
    {
        $pdt = new PlainDateTime(2020, 3, 15);

        $this->expectException(InvalidArgumentException::class);
        $pdt->withCalendar('');
    }

    public function testWithCalendarUnsupportedThrows(): void
    {
        $pdt = new PlainDateTime(2020, 3, 15);

        $this->expectException(InvalidArgumentException::class);
        $pdt->withCalendar('japanese');
    }
}
