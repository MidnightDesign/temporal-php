<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\Instant;

final class InstantCompareTest extends TestCase
{
    public function testEqualsTrue(): void
    {
        $a = new Instant(42);
        $b = new Instant(42);

        static::assertTrue($a->equals($b));
    }

    public function testEqualsFalse(): void
    {
        $a = new Instant(42);
        $b = new Instant(43);

        static::assertFalse($a->equals($b));
    }

    public function testCompareLessThan(): void
    {
        $earlier = new Instant(1);
        $later = new Instant(2);

        static::assertSame(-1, Instant::compare($earlier, $later));
    }

    public function testCompareEqual(): void
    {
        $a = new Instant(5);
        $b = new Instant(5);

        static::assertSame(0, Instant::compare($a, $b));
    }

    public function testCompareGreaterThan(): void
    {
        $earlier = new Instant(1);
        $later = new Instant(2);

        static::assertSame(1, Instant::compare($later, $earlier));
    }
}
