<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\ZonedDateTime;

final class ZonedDateTimeFromTest extends TestCase
{
    public function testFromZonedDateTimeReturnsCopy(): void
    {
        $original = new ZonedDateTime(0, 'UTC');
        $copy     = ZonedDateTime::from($original);

        static::assertSame($original->epochNanoseconds, $copy->epochNanoseconds);
        static::assertSame($original->timeZoneId, $copy->timeZoneId);
    }

    public function testFromStringWithZOffset(): void
    {
        $zdt = ZonedDateTime::from('2020-01-15T12:30:00Z[UTC]');

        static::assertSame(2020, $zdt->year);
        static::assertSame(1, $zdt->month);
        static::assertSame(15, $zdt->day);
        static::assertSame(12, $zdt->hour);
        static::assertSame(30, $zdt->minute);
        static::assertSame(0, $zdt->second);
        static::assertSame('UTC', $zdt->timeZoneId);
    }

    public function testFromStringWithFixedOffset(): void
    {
        $zdt = ZonedDateTime::from('2020-01-15T12:30:00+05:30[+05:30]');

        static::assertSame(2020, $zdt->year);
        static::assertSame(12, $zdt->hour);
        static::assertSame(30, $zdt->minute);
        static::assertSame('+05:30', $zdt->timeZoneId);
    }

    public function testFromStringWithFractionalSeconds(): void
    {
        $zdt = ZonedDateTime::from('2020-01-15T12:30:45.123456789Z[UTC]');

        static::assertSame(45, $zdt->second);
        static::assertSame(123, $zdt->millisecond);
        static::assertSame(456, $zdt->microsecond);
        static::assertSame(789, $zdt->nanosecond);
    }

    public function testFromStringRequiresBracketAnnotation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZonedDateTime::from('2020-01-15T12:30:00Z');
    }

    public function testFromStringRejectsTooManyFractionalDigits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZonedDateTime::from('2020-01-15T12:30:00.1234567890Z[UTC]');
    }

    public function testFromStringRejectsMinusZeroYear(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZonedDateTime::from('-000000-01-01T00:00:00Z[UTC]');
    }

    public function testFromStringRejectsInvalidMonth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZonedDateTime::from('2020-13-01T00:00:00Z[UTC]');
    }

    public function testFromStringRejectsInvalidDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZonedDateTime::from('2020-02-30T00:00:00Z[UTC]');
    }

    public function testFromStringLeapSecond(): void
    {
        $zdt = ZonedDateTime::from('2016-12-31T23:59:60Z[UTC]');

        // Leap second maps to :59.000000000 (same as Instant::from() — no sub-second offset)
        static::assertSame(59, $zdt->second);
        static::assertSame(0, $zdt->millisecond);
        static::assertSame(0, $zdt->microsecond);
        static::assertSame(0, $zdt->nanosecond);
    }

    public function testFromStringRejectsMismatchedOffset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // UTC timezone but +05:30 inline offset → mismatch
        ZonedDateTime::from('2020-01-15T12:30:00+05:30[UTC]');
    }

    public function testFromThrowsTypeErrorForInt(): void
    {
        $this->expectException(\TypeError::class);
        ZonedDateTime::from(42);
    }

    public function testCompareReturnsMinusOneWhenFirstIsEarlier(): void
    {
        $a = new ZonedDateTime(0, 'UTC');
        $b = new ZonedDateTime(1_000_000_000, 'UTC');

        static::assertSame(-1, ZonedDateTime::compare($a, $b));
    }

    public function testCompareReturnsZeroWhenEqual(): void
    {
        $a = new ZonedDateTime(500, 'UTC');
        $b = new ZonedDateTime(500, '+00:00');

        static::assertSame(0, ZonedDateTime::compare($a, $b));
    }

    public function testCompareReturnsOneWhenFirstIsLater(): void
    {
        $a = new ZonedDateTime(2_000_000_000, 'UTC');
        $b = new ZonedDateTime(1_000_000_000, 'UTC');

        static::assertSame(1, ZonedDateTime::compare($a, $b));
    }
}
