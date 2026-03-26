<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use Temporal\Duration;

final class DurationFromTest extends TemporalTestCase
{
    public function testSimple(): void
    {
        $d = Duration::from('P1Y');

        static::assertSame(1, $d->years);
        static::assertSame(0, $d->months);
    }

    public function testFull(): void
    {
        $d = Duration::from('P1Y2M3W4DT5H6M7S');

        $this->assertDurationIs(1, 2, 3, 4, 5, 6, 7, 0, 0, 0, $d);
    }

    public function testNegative(): void
    {
        $d = Duration::from('-P1DT2H');

        static::assertSame(-1, $d->days);
        static::assertSame(-2, $d->hours);
        static::assertSame(0, $d->years);
    }

    public function testTimeOnly(): void
    {
        $d = Duration::from('PT30M');

        static::assertSame(30, $d->minutes);
        static::assertSame(0, $d->hours);
        static::assertSame(0, $d->days);
    }

    public function testFractionalSeconds(): void
    {
        $d = Duration::from('PT1.5S');

        static::assertSame(1, $d->seconds);
        static::assertSame(500, $d->milliseconds);
        static::assertSame(0, $d->microseconds);
        static::assertSame(0, $d->nanoseconds);
    }

    public function testCommaFraction(): void
    {
        $d = Duration::from('PT1,5S');

        static::assertSame(1, $d->seconds);
        static::assertSame(500, $d->milliseconds);
    }

    public function testHighPrecisionFraction(): void
    {
        $d = Duration::from('P1Y2M3W4DT5H6M7.008009001S');

        $this->assertDurationIs(1, 2, 3, 4, 5, 6, 7, 8, 9, 1, $d);
    }

    public function testZeroComponent(): void
    {
        $d = Duration::from('P0Y');

        static::assertTrue($d->blank);
        static::assertSame(0, $d->years);
    }

    public function testInvalidNoComponents(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::from('P');
    }

    public function testInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::from('not-a-duration');
    }
}
