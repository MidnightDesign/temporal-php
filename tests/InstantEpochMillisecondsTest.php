<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\Instant;

final class InstantEpochMillisecondsTest extends TestCase
{
    public function testEpochMilliseconds(): void
    {
        // 1,500,000 ns = 1.5 ms → floor = 1 ms
        $instant = new Instant(1_500_000);

        static::assertSame(1, $instant->epochMilliseconds);
    }

    public function testEpochMillisecondsExact(): void
    {
        // 2,000,000 ns = exactly 2 ms
        $instant = new Instant(2_000_000);

        static::assertSame(2, $instant->epochMilliseconds);
    }

    public function testEpochMillisecondsNegative(): void
    {
        // -1 ns → floor(-1 / 1_000_000) = -1 ms (floor of -0.000001)
        $instant = new Instant(-1);

        static::assertSame(-1, $instant->epochMilliseconds);
    }

    public function testEpochMillisecondsNegativeExact(): void
    {
        $instant = new Instant(-1_000_000);

        static::assertSame(-1, $instant->epochMilliseconds);
    }

    public function testEpochMillisecondsNegativeHalf(): void
    {
        // -1,500,000 ns = -1.5 ms → floor = -2 ms
        $instant = new Instant(-1_500_000);

        static::assertSame(-2, $instant->epochMilliseconds);
    }

    public function testEpochMillisecondsPositiveTimestampScale(): void
    {
        // 217_175_010_123_456_789 ns → floor = 217_175_010_123 ms (sub-ms digits truncated, not rounded)
        $instant = new Instant(217_175_010_123_456_789);

        static::assertSame(217_175_010_123, $instant->epochMilliseconds);
    }

    public function testEpochMillisecondsNegativeTimestampScale(): void
    {
        // -217_175_010_876_543_211 ns → floor = -217_175_010_877 ms (floor toward -∞, not truncate toward 0)
        $instant = new Instant(-217_175_010_876_543_211);

        static::assertSame(-217_175_010_877, $instant->epochMilliseconds);
    }
}
