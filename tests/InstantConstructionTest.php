<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\Instant;

final class InstantConstructionTest extends TestCase
{
    public function testConstructorExposesEpochNanoseconds(): void
    {
        $instant = new Instant(1_000_000_000);

        static::assertSame(1_000_000_000, $instant->epochNanoseconds);
    }

    public function testConstructorZero(): void
    {
        $instant = new Instant(0);

        static::assertSame(0, $instant->epochNanoseconds);
    }

    public function testConstructorNegative(): void
    {
        $instant = new Instant(-1);

        static::assertSame(-1, $instant->epochNanoseconds);
    }

    public function testFromEpochNanoseconds(): void
    {
        $instant = Instant::fromEpochNanoseconds(1_234_567_890_000_000_000);

        static::assertSame(1_234_567_890_000_000_000, $instant->epochNanoseconds);
    }

    public function testFromEpochMilliseconds(): void
    {
        $instant = Instant::fromEpochMilliseconds(1_000);

        // 1,000 ms × 1,000,000 ns/ms = 1,000,000,000 ns (= 1 second)
        static::assertSame(1_000_000_000, $instant->epochNanoseconds);
    }

    public function testFromEpochMillisecondsNegative(): void
    {
        $instant = Instant::fromEpochMilliseconds(-500);

        static::assertSame(-500_000_000, $instant->epochNanoseconds);
    }
}
