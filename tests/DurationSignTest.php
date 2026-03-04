<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\Duration;

final class DurationSignTest extends TestCase
{
    public function testSignPositive(): void
    {
        $d = new Duration(years: 1);

        static::assertSame(1, $d->sign);
    }

    public function testSignNegative(): void
    {
        $d = new Duration(hours: -3);

        static::assertSame(-1, $d->sign);
    }

    public function testSignZero(): void
    {
        $d = new Duration();

        static::assertSame(0, $d->sign);
    }

    public function testBlankTrue(): void
    {
        $d = new Duration();

        static::assertTrue($d->blank);
    }

    public function testBlankFalse(): void
    {
        $d = new Duration(nanoseconds: 1);

        static::assertFalse($d->blank);
    }
}
