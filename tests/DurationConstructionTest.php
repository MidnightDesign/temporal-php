<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Duration;

final class DurationConstructionTest extends TestCase
{
    public function testDefaultConstructorAllZero(): void
    {
        $d = new Duration();

        static::assertSame(0, $d->years);
        static::assertSame(0, $d->months);
        static::assertSame(0, $d->weeks);
        static::assertSame(0, $d->days);
        static::assertSame(0, $d->hours);
        static::assertSame(0, $d->minutes);
        static::assertSame(0, $d->seconds);
        static::assertSame(0, $d->milliseconds);
        static::assertSame(0, $d->microseconds);
        static::assertSame(0, $d->nanoseconds);
    }

    public function testAllPositive(): void
    {
        $d = new Duration(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

        static::assertSame(1, $d->years);
        static::assertSame(2, $d->months);
        static::assertSame(3, $d->weeks);
        static::assertSame(4, $d->days);
        static::assertSame(5, $d->hours);
        static::assertSame(6, $d->minutes);
        static::assertSame(7, $d->seconds);
        static::assertSame(8, $d->milliseconds);
        static::assertSame(9, $d->microseconds);
        static::assertSame(10, $d->nanoseconds);
    }

    public function testAllNegative(): void
    {
        $d = new Duration(-1, -2, -3, -4, -5, -6, -7, -8, -9, -10);

        static::assertSame(-1, $d->years);
        static::assertSame(-5, $d->hours);
        static::assertSame(-10, $d->nanoseconds);
    }

    public function testSingleField(): void
    {
        $d = new Duration(days: 7);

        static::assertSame(7, $d->days);
        static::assertSame(0, $d->years);
    }

    public function testZeroAndPositiveAreValid(): void
    {
        $d = new Duration(years: 1, months: 0, days: 3);

        static::assertSame(1, $d->years);
        static::assertSame(0, $d->months);
        static::assertSame(3, $d->days);
    }

    public function testMixedSignsThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Duration(years: 1, days: -1);
    }
}
