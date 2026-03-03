<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Instant;

final class InstantFromTest extends TestCase
{
    public function testFromUnixEpoch(): void
    {
        $instant = Instant::from('1970-01-01T00:00:00Z');

        static::assertSame(0, $instant->epochNanoseconds);
    }

    public function testFromWithPositiveOffset(): void
    {
        // 2020-01-01T00:00:00+05:30 == 2019-12-31T18:30:00Z
        $instant = Instant::from('2020-01-01T00:00:00+05:30');
        $expected = Instant::from('2019-12-31T18:30:00Z');

        static::assertTrue($instant->equals($expected));
    }

    public function testFromWithNanoseconds(): void
    {
        $instant = Instant::from('1970-01-01T00:00:00.000000001Z');

        static::assertSame(1, $instant->epochNanoseconds);
    }

    public function testFromWithSubSecondPrecision(): void
    {
        $instant = Instant::from('1970-01-01T00:00:00.123456789Z');

        static::assertSame(123_456_789, $instant->epochNanoseconds);
    }

    public function testFromShortFraction(): void
    {
        // '.5' should be treated as 500,000,000 ns (padded to 9 digits)
        $instant = Instant::from('1970-01-01T00:00:00.5Z');

        static::assertSame(500_000_000, $instant->epochNanoseconds);
    }

    public function testFromIgnoresIanaAnnotation(): void
    {
        $withAnnotation = Instant::from('2020-06-01T12:00:00Z[America/New_York]');
        $withoutAnnotation = Instant::from('2020-06-01T12:00:00Z');

        static::assertTrue($withAnnotation->equals($withoutAnnotation));
    }

    public function testFractionTruncatedBeyondNanoseconds(): void
    {
        // Digits beyond the 9th are discarded (truncation, not rounding).
        $instant = Instant::from('1970-01-01T00:00:00.1234567899Z'); // 10 digits

        static::assertSame(123_456_789, $instant->epochNanoseconds);
    }

    public function testFromThrowsWithoutOffset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Instant::from('2020-01-01T00:00:00');
    }

    public function testFromThrowsOnInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Instant::from('not-a-date');
    }
}
