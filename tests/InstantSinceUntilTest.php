<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Instant;
use TypeError;

final class InstantSinceUntilTest extends TestCase
{
    public function testSinceReturnsPositiveDurationWhenLater(): void
    {
        $earlier = new Instant(0);
        $later = new Instant(5_000_000_000); // 5 seconds later

        $d = $later->since($earlier);

        static::assertSame(5, $d->seconds);
        static::assertSame(0, $d->minutes);
        static::assertSame(0, $d->hours);
    }

    public function testSinceReturnsNegativeDurationWhenEarlier(): void
    {
        $earlier = new Instant(0);
        $later = new Instant(5_000_000_000);

        $d = $earlier->since($later);

        static::assertSame(-5, $d->seconds);
    }

    public function testUntilReturnsPositiveDurationWhenOtherIsLater(): void
    {
        $start = new Instant(0);
        $end = new Instant(3_600_000_000_000); // 1 hour

        $d = $start->until($end);

        static::assertSame(3_600, $d->seconds);
    }

    public function testUntilWithLargestUnitHour(): void
    {
        $start = new Instant(0);
        $end = new Instant(3_600_000_000_000);

        $d = $start->until($end, ['largestUnit' => 'hour']);

        static::assertSame(1, $d->hours);
        static::assertSame(0, $d->minutes);
        static::assertSame(0, $d->seconds);
    }

    public function testSinceAcceptsStringArgument(): void
    {
        $later = new Instant(1_000_000_000);

        $d = $later->since('1970-01-01T00:00:00Z');

        static::assertSame(1, $d->seconds);
    }

    public function testUntilAcceptsStringArgument(): void
    {
        $start = new Instant(0);

        $d = $start->until('1970-01-01T00:00:01Z');

        static::assertSame(1, $d->seconds);
    }

    public function testSinceWithSmallestUnitMillisecond(): void
    {
        $earlier = new Instant(0);
        $later = new Instant(1_500_000_000); // 1.5 s

        $d = $later->since($earlier, ['smallestUnit' => 'millisecond']);

        static::assertSame(1, $d->seconds);
        static::assertSame(500, $d->milliseconds);
    }

    public function testUntilInvalidLargestUnitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Instant(0)->until(new Instant(1), ['largestUnit' => 'day']);
    }

    public function testSinceInvalidOptionsTypeThrows(): void
    {
        $this->expectException(TypeError::class);
        new Instant(0)->since(new Instant(1), 'bad-options');
    }

    public function testUntilSmallestLargerThanLargestThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Instant(0)->until(new Instant(1), [
            'largestUnit' => 'second',
            'smallestUnit' => 'hour',
        ]);
    }
}
