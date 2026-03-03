<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Instant;

/**
 * Verifies that Instant::from() enforces the nanosecond-range overflow guard.
 *
 * The guard rejects timestamps where |seconds| > 9_223_372_036, since multiplying
 * by 1_000_000_000 would overflow a 64-bit integer.  The exact boundary dates were
 * computed from the Unix epoch:
 *
 *   Upper limit:  9_223_372_036 s  →  2262-04-11T23:47:16Z  (within range)
 *   Above limit:  9_223_372_037 s  →  2262-04-11T23:47:17Z  (out of range)
 *   Lower limit: -9_223_372_036 s  →  1677-09-21T00:12:44Z  (within range)
 *   Below limit: -9_223_372_037 s  →  1677-09-21T00:12:43Z  (out of range)
 */
final class InstantOverflowTest extends TestCase
{
    public function testUpperBoundaryWithinRange(): void
    {
        // 9_223_372_036 seconds: exactly at the limit, must NOT throw.
        $instant = Instant::from('2262-04-11T23:47:16Z');

        static::assertSame(9_223_372_036_000_000_000, $instant->epochNanoseconds);
    }

    public function testUpperBoundaryOutOfRange(): void
    {
        // 9_223_372_037 seconds: one second past the limit, must throw.
        $this->expectException(InvalidArgumentException::class);
        Instant::from('2262-04-11T23:47:17Z');
    }

    public function testLowerBoundaryWithinRange(): void
    {
        // -9_223_372_036 seconds: exactly at the limit, must NOT throw.
        $instant = Instant::from('1677-09-21T00:12:44Z');

        static::assertSame(-9_223_372_036_000_000_000, $instant->epochNanoseconds);
    }

    public function testLowerBoundaryOutOfRange(): void
    {
        // -9_223_372_037 seconds: one second past the limit, must throw.
        $this->expectException(InvalidArgumentException::class);
        Instant::from('1677-09-21T00:12:43Z');
    }
}
