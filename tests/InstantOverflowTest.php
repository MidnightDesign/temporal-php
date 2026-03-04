<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Temporal\Instant;

/**
 * Verifies that Instant::from() enforces the Temporal spec's epoch-nanosecond range.
 *
 * The spec allows dates from -271821-04-20T00:00:00Z (epoch second −8_640_000_000_000)
 * to +275760-09-13T00:00:00Z (epoch second +8_640_000_000_000). Outside that range
 * from() must throw InvalidArgumentException.
 *
 * Dates whose seconds fit within ±9_223_372_035 are accurately representable as
 * int64 nanoseconds (the threshold is 9_223_372_035 rather than 9_223_372_036 so
 * that 9_223_372_035 × 10⁹ + 999_999_999 stays below PHP_INT_MAX). The constants
 * below anchor the int64-safe boundary:
 *
 *   +9_223_372_035 s  →  2262-04-11T23:47:15Z  (within both spec and int64)
 *   -9_223_372_035 s  →  1677-09-21T00:12:45Z  (within both spec and int64)
 */
final class InstantOverflowTest extends TestCase
{
    // ── Int64-safe boundary ───────────────────────────────────────────────────

    public function testUpperBoundaryWithinRange(): void
    {
        // 9_223_372_035 seconds: within both the spec and PHP int64 range.
        $instant = Instant::from('2262-04-11T23:47:15Z');

        static::assertSame(9_223_372_035_000_000_000, $instant->epochNanoseconds);
    }

    public function testLowerBoundaryWithinRange(): void
    {
        // -9_223_372_035 seconds: within both the spec and PHP int64 range.
        $instant = Instant::from('1677-09-21T00:12:45Z');

        static::assertSame(-9_223_372_035_000_000_000, $instant->epochNanoseconds);
    }

    // ── Spec boundary (accepted but epochNanoseconds overflows PHP int64) ─────

    #[DoesNotPerformAssertions]
    public function testSpecUpperBoundaryAccepted(): void
    {
        // +275760-09-13T00:00:00Z is the spec maximum; from() must not throw
        // even though epochNanoseconds overflows PHP int64.
        Instant::from('+275760-09-13T00:00:00Z');

        // not throwing is the assertion
    }

    #[DoesNotPerformAssertions]
    public function testSpecLowerBoundaryAccepted(): void
    {
        // -271821-04-20T00:00:00Z is the spec minimum; from() must not throw.
        Instant::from('-271821-04-20T00:00:00Z');

        // not throwing is the assertion
    }

    // ── Out-of-spec-range ─────────────────────────────────────────────────────

    public function testUpperBoundaryOutOfRange(): void
    {
        // +275760-09-13T00:00:00.000000001Z is 1 ns beyond the spec maximum.
        $this->expectException(InvalidArgumentException::class);
        Instant::from('+275760-09-13T00:00:00.000000001Z');
    }

    public function testLowerBoundaryOutOfRange(): void
    {
        // -271821-04-19T23:59:59.999999999Z is 1 ns before the spec minimum.
        $this->expectException(InvalidArgumentException::class);
        Instant::from('-271821-04-19T23:59:59.999999999Z');
    }
}
