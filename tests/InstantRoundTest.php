<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Instant;
use TypeError;

final class InstantRoundTest extends TestCase
{
    public function testRoundToSecondHalfExpand(): void
    {
        // 1.5 seconds → rounds up to 2 seconds with halfExpand
        $base = new Instant(1_500_000_000);
        $result = $base->round('second');

        static::assertSame(2_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundToSecondRoundsDown(): void
    {
        // 1.4 seconds → rounds to 1 second
        $base = new Instant(1_400_000_000);
        $result = $base->round(['smallestUnit' => 'second']);

        static::assertSame(1_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundToMillisecond(): void
    {
        $base = new Instant(1_000_500_000); // 1.0005 seconds
        $result = $base->round('millisecond');

        static::assertSame(1_001_000_000, $result->epochNanoseconds);
    }

    public function testRoundToMinuteHalfExpand(): void
    {
        // 30 seconds → rounds up to 1 minute
        $base = new Instant(30_000_000_000);
        $result = $base->round('minute');

        static::assertSame(60_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundWithRoundingModeFloor(): void
    {
        // 1.9 seconds with floor → 1 second
        $base = new Instant(1_900_000_000);
        $result = $base->round(['smallestUnit' => 'second', 'roundingMode' => 'floor']);

        static::assertSame(1_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundMissingSmallestUnitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Instant(0))->round([]);
    }

    public function testRoundInvalidSmallestUnitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Instant(0))->round('day');
    }

    public function testRoundNonStringNonArrayThrows(): void
    {
        $this->expectException(TypeError::class);
        (new Instant(0))->round(42);
    }

    public function testRoundInvalidIncrementThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Instant(0))->round(['smallestUnit' => 'nanosecond', 'roundingIncrement' => 7]);
    }

    public function testRoundNanosecondIsNoOp(): void
    {
        $base = new Instant(123_456_789);
        $result = $base->round('nanosecond');

        static::assertSame(123_456_789, $result->epochNanoseconds);
    }
}
