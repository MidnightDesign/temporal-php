<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Instant;

final class InstantStringTest extends TestCase
{
    public function testToStringUnixEpoch(): void
    {
        static::assertSame('1970-01-01T00:00:00Z', new Instant(0)->toString());
    }

    public function testToStringWithNanoseconds(): void
    {
        static::assertSame('1970-01-01T00:00:00.123456789Z', new Instant(123_456_789)->toString());
    }

    public function testToStringStripsTrailingFractionZeros(): void
    {
        static::assertSame('1970-01-01T00:00:00.5Z', new Instant(500_000_000)->toString());
    }

    public function testToStringMillisecondPrecision(): void
    {
        static::assertSame('1970-01-01T00:00:00.123Z', new Instant(123_000_000)->toString());
    }

    public function testToStringBeforeEpoch(): void
    {
        // -500,000,000 ns = -0.5 s → 1969-12-31T23:59:59.5Z
        static::assertSame('1969-12-31T23:59:59.5Z', new Instant(-500_000_000)->toString());
    }

    public function testToStringBeforeEpochWholeSecond(): void
    {
        static::assertSame('1969-12-31T23:59:59Z', new Instant(-1_000_000_000)->toString());
    }

    public function testMagicToString(): void
    {
        static::assertSame('1970-01-01T00:00:00Z', (string) new Instant(0));
    }

    public function testToJson(): void
    {
        static::assertSame('1970-01-01T00:00:00Z', new Instant(0)->toJSON());
    }

    #[DataProvider('roundTripProvider')]
    public function testFromToStringRoundTrip(string $isoString): void
    {
        static::assertSame($isoString, Instant::from($isoString)->toString());
    }

    /** @return array<string, array{string}> */
    public static function roundTripProvider(): array
    {
        return [
            'epoch' => ['1970-01-01T00:00:00Z'],
            'whole second' => ['2020-01-01T00:00:00Z'],
            'milliseconds' => ['2020-01-01T00:00:00.123Z'],
            'microseconds' => ['2020-01-01T00:00:00.123456Z'],
            'nanoseconds' => ['2020-01-01T00:00:00.123456789Z'],
            'sub-second strip' => ['2020-01-01T00:00:00.5Z'],
            'before epoch' => ['1969-12-31T23:59:59.5Z'],
        ];
    }
}
