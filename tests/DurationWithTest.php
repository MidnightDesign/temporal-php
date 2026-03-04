<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\Duration;

final class DurationWithTest extends TestCase
{
    public function testWithMonths(): void
    {
        $d = new Duration(months: 2);

        static::assertSame(5, $d->with(['months' => 5])->months);
    }

    public function testWithWeeks(): void
    {
        $d = new Duration(weeks: 2);

        static::assertSame(5, $d->with(['weeks' => 5])->weeks);
    }

    public function testWithHours(): void
    {
        $d = new Duration(hours: 2);

        static::assertSame(5, $d->with(['hours' => 5])->hours);
    }

    public function testWithMinutes(): void
    {
        $d = new Duration(minutes: 2);

        static::assertSame(5, $d->with(['minutes' => 5])->minutes);
    }

    public function testWithSeconds(): void
    {
        $d = new Duration(seconds: 2);

        static::assertSame(5, $d->with(['seconds' => 5])->seconds);
    }

    public function testWithMilliseconds(): void
    {
        $d = new Duration(milliseconds: 2);

        static::assertSame(5, $d->with(['milliseconds' => 5])->milliseconds);
    }

    public function testWithMicroseconds(): void
    {
        $d = new Duration(microseconds: 2);

        static::assertSame(5, $d->with(['microseconds' => 5])->microseconds);
    }

    public function testWithNanoseconds(): void
    {
        $d = new Duration(nanoseconds: 2);

        static::assertSame(5, $d->with(['nanoseconds' => 5])->nanoseconds);
    }
}
