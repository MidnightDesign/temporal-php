<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Duration;
use Temporal\Instant;

final class InstantAddSubtractTest extends TestCase
{
    public function testAddSecondsForwardInTime(): void
    {
        $base = new Instant(0);
        $result = $base->add(new Duration(seconds: 5));

        static::assertSame(5_000_000_000, $result->epochNanoseconds);
    }

    public function testAddNanoseconds(): void
    {
        $base = new Instant(1_000_000_000);
        $result = $base->add(new Duration(nanoseconds: 500));

        static::assertSame(1_000_000_500, $result->epochNanoseconds);
    }

    public function testAddAcceptsString(): void
    {
        $base = new Instant(0);
        $result = $base->add('PT1H');

        static::assertSame(3_600_000_000_000, $result->epochNanoseconds);
    }

    public function testAddAcceptsArray(): void
    {
        $base = new Instant(0);
        $result = $base->add(['minutes' => 2]);

        static::assertSame(120_000_000_000, $result->epochNanoseconds);
    }

    public function testAddThrowsForCalendarDays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Instant(0)->add(new Duration(days: 1));
    }

    public function testAddThrowsForCalendarYears(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Instant(0)->add(new Duration(years: 1));
    }

    public function testSubtractSecondsBackwardInTime(): void
    {
        $base = new Instant(5_000_000_000);
        $result = $base->subtract(new Duration(seconds: 3));

        static::assertSame(2_000_000_000, $result->epochNanoseconds);
    }

    public function testSubtractAcceptsString(): void
    {
        $base = new Instant(3_600_000_000_000);
        $result = $base->subtract('PT1H');

        static::assertSame(0, $result->epochNanoseconds);
    }

    public function testSubtractThrowsForCalendarWeeks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Instant(0)->subtract(new Duration(weeks: 1));
    }

    public function testAddOutOfSpecRangeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Start at max spec boundary and add more.
        $max = Instant::from('+275760-09-13T23:59:59.999999999Z');
        $max->add(new Duration(nanoseconds: 1));
    }
}
