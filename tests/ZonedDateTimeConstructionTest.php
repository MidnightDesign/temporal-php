<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\ZonedDateTime;

final class ZonedDateTimeConstructionTest extends TestCase
{
    public function testConstructorStoresEpochNanoseconds(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame(0, $zdt->epochNanoseconds);
    }

    public function testConstructorNormalizesUtcCaseInsensitive(): void
    {
        $zdt = new ZonedDateTime(0, 'utc');

        static::assertSame('UTC', $zdt->timeZoneId);
    }

    public function testConstructorAcceptsFixedOffset(): void
    {
        $zdt = new ZonedDateTime(0, '+05:30');

        static::assertSame('+05:30', $zdt->timeZoneId);
    }

    public function testConstructorNormalizesCompactOffset(): void
    {
        $zdt = new ZonedDateTime(0, '+0530');

        static::assertSame('+05:30', $zdt->timeZoneId);
    }

    public function testConstructorNormalizesHourOnlyOffset(): void
    {
        $zdt = new ZonedDateTime(0, '+05');

        static::assertSame('+05:00', $zdt->timeZoneId);
    }

    public function testConstructorRejectsEmptyTimezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(0, '');
    }

    public function testConstructorRejectsInvalidTimezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(0, 'Not/A/Timezone/That/Exists');
    }

    public function testConstructorRejectsNanFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(NAN, 'UTC');
    }

    public function testConstructorRejectsInfiniteFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(INF, 'UTC');
    }

    public function testConstructorRejectsFractionalFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(1.5, 'UTC');
    }

    public function testConstructorAcceptsIntegerFloat(): void
    {
        $zdt = new ZonedDateTime(1_000_000_000.0, 'UTC');

        static::assertSame(1_000_000_000, $zdt->epochNanoseconds);
    }

    public function testConstructorDefaultCalendarIsIso8601(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('iso8601', $zdt->calendarId);
    }

    public function testConstructorAcceptsIanaTimezone(): void
    {
        $zdt = new ZonedDateTime(0, 'America/New_York');

        static::assertSame('America/New_York', $zdt->timeZoneId);
    }
}
