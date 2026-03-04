<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Duration;

final class DurationStringTest extends TestCase
{
    public function testBlank(): void
    {
        static::assertSame('PT0S', new Duration()->toString());
    }

    public function testSimple(): void
    {
        static::assertSame('P1Y', new Duration(years: 1)->toString());
    }

    public function testFull(): void
    {
        $d = new Duration(1, 2, 3, 4, 5, 6, 7);

        static::assertSame('P1Y2M3W4DT5H6M7S', $d->toString());
    }

    public function testNegative(): void
    {
        $d = new Duration(days: -1, hours: -2);

        static::assertSame('-P1DT2H', $d->toString());
    }

    public function testFractional(): void
    {
        $d = new Duration(seconds: 1, milliseconds: 500);

        static::assertSame('PT1.5S', $d->toString());
    }

    public function testSubSecondOnly(): void
    {
        $d = new Duration(nanoseconds: 1);

        static::assertSame('PT0.000000001S', $d->toString());
    }

    public function testHighPrecision(): void
    {
        $d = new Duration(seconds: 7, milliseconds: 8, microseconds: 9, nanoseconds: 1);

        static::assertSame('PT7.008009001S', $d->toString());
    }

    public function testToStringMinutesOnly(): void
    {
        // minutes-only duration: the || between minutes and seconds must not become &&
        static::assertSame('PT1M', new Duration(minutes: 1)->toString());
    }

    public function testLargeSubsecondCarry(): void
    {
        // 876_543 ms = 876 whole seconds + 543 ms; carry must produce PT876.543S
        static::assertSame('PT876.543S', new Duration(milliseconds: 876_543)->toString());
        // negative carry
        static::assertSame('-PT876.543S', new Duration(milliseconds: -876_543)->toString());
    }

    public function testToJSON(): void
    {
        $d = new Duration(hours: 2);

        static::assertSame($d->toString(), $d->toJSON());
    }

    /** @return list<array{string}> */
    public static function roundTripProvider(): array
    {
        return [
            ['PT0S'],
            ['P1Y'],
            ['P1Y2M3W4DT5H6M7S'],
            ['-P1DT2H'],
            ['PT1.5S'],
            ['PT0.000000001S'],
            ['PT7.008009001S'],
        ];
    }

    #[DataProvider('roundTripProvider')]
    public function testRoundTrip(string $iso): void
    {
        static::assertSame($iso, Duration::from($iso)->toString());
    }
}
