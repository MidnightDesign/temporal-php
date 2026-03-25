<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\ZonedDateTime;

final class ZonedDateTimeStringTest extends TestCase
{
    public function testToStringProducesExpectedFormat(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('1970-01-01T00:00:00+00:00[UTC]', $zdt->toString());
    }

    public function testToStringWithFractionalSeconds(): void
    {
        $epochNs = (0 * 1_000_000_000) + 123_456_789;
        $zdt = new ZonedDateTime($epochNs, 'UTC');

        static::assertSame('1970-01-01T00:00:00.123456789+00:00[UTC]', $zdt->toString());
    }

    public function testToStringAutoStripsTrailingZeros(): void
    {
        // 0.100000000 → 0.1
        $epochNs = 100_000_000;
        $zdt = new ZonedDateTime($epochNs, 'UTC');

        static::assertSame('1970-01-01T00:00:00.1+00:00[UTC]', $zdt->toString());
    }

    public function testToStringFixedFractionalDigits(): void
    {
        $epochNs = 123_456_789;
        $zdt = new ZonedDateTime($epochNs, 'UTC');

        static::assertSame('1970-01-01T00:00:00.123+00:00[UTC]', $zdt->toString(['fractionalSecondDigits' => 3]));
    }

    public function testToStringFractionalDigitsZero(): void
    {
        $epochNs = 123_456_789;
        $zdt = new ZonedDateTime($epochNs, 'UTC');

        static::assertSame('1970-01-01T00:00:00+00:00[UTC]', $zdt->toString(['fractionalSecondDigits' => 0]));
    }

    public function testToStringOffsetNever(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('1970-01-01T00:00:00[UTC]', $zdt->toString(['offset' => 'never']));
    }

    public function testToStringTimeZoneNameNever(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('1970-01-01T00:00:00+00:00', $zdt->toString(['timeZoneName' => 'never']));
    }

    public function testToStringTimeZoneNameCritical(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('1970-01-01T00:00:00+00:00[!UTC]', $zdt->toString(['timeZoneName' => 'critical']));
    }

    public function testToStringCalendarNameAlways(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('1970-01-01T00:00:00+00:00[UTC][u-ca=iso8601]', $zdt->toString([
            'calendarName' => 'always',
        ]));
    }

    public function testToStringCalendarNameNever(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        // iso8601 is default, so 'never' omits it (same as 'auto' for iso8601)
        static::assertSame('1970-01-01T00:00:00+00:00[UTC]', $zdt->toString(['calendarName' => 'never']));
    }

    public function testToStringCalendarNameCritical(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('1970-01-01T00:00:00+00:00[UTC][!u-ca=iso8601]', $zdt->toString([
            'calendarName' => 'critical',
        ]));
    }

    public function testToStringWithPositiveOffset(): void
    {
        $zdt = new ZonedDateTime(0, '+05:30');

        $str = $zdt->toString();
        static::assertStringContainsString('+05:30', $str);
        static::assertStringContainsString('[+05:30]', $str);
    }

    public function testToStringYear1(): void
    {
        // 0001-01-01T00:00:00Z is before Unix epoch
        // PHP_INT_MIN sentinel represents a very distant past date; use a representable year
        // -1970 years * 365.25 days * 86400 sec ≈ -62_167_219_200 sec; too large.
        // Use year 1677 (representable in int64): approximately -9_223_372_036 seconds.
        // Use a simpler approach: year 1900.
        /** @psalm-suppress RedundantCast — PHPStan types mktime() as int|false */
        $epochSec = (int) mktime(hour: 0, minute: 0, second: 0, month: 1, day: 1, year: 1900); // typically -2_208_988_800
        $zdt = new ZonedDateTime($epochSec * 1_000_000_000, 'UTC');
        $str = $zdt->toString();
        static::assertStringStartsWith('1900-01-01', $str);
    }

    public function testToJsonDelegatesToToString(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame($zdt->toString(), $zdt->toJSON());
    }

    public function testCastToStringDelegatesToToString(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame($zdt->toString(), (string) $zdt);
    }

    public function testToStringOffsetOptionWrongTypeThrows(): void
    {
        $this->expectException(\TypeError::class);
        new ZonedDateTime(0, 'UTC')->toString(['offset' => 42]);
    }

    public function testToStringInvalidOffsetOptionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(0, 'UTC')->toString(['offset' => 'invalid']);
    }

    public function testToStringInvalidCalendarNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(0, 'UTC')->toString(['calendarName' => 'bad']);
    }
}
