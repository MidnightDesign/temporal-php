<?php

declare(strict_types=1);

namespace Temporal\Tests;

use Temporal\Duration;

final class DurationMutationTest extends TemporalTestCase
{
    public function testNegated(): void
    {
        $d = new Duration(years: 1, hours: 2);

        $n = $d->negated();

        static::assertSame(-1, $n->years);
        static::assertSame(-2, $n->hours);
    }

    public function testNegatedBlank(): void
    {
        $d = new Duration();

        static::assertTrue($d->negated()->blank);
    }

    public function testAbsFromNegative(): void
    {
        $d = new Duration(days: -3, minutes: -15);

        $a = $d->abs();

        static::assertSame(3, $a->days);
        static::assertSame(15, $a->minutes);
    }

    public function testAbsFromPositive(): void
    {
        $d = new Duration(years: 2);

        static::assertTrue($d->abs()->equals($d));
    }

    public function testAbsBlank(): void
    {
        $d = new Duration();

        static::assertTrue($d->abs()->blank);
    }

    public function testWithSingleField(): void
    {
        $d = new Duration(years: 1, months: 2);

        $updated = $d->with(['years' => 5]);

        static::assertSame(5, $updated->years);
        static::assertSame(2, $updated->months);
    }

    public function testWithMultipleFields(): void
    {
        $d = new Duration(years: 1, months: 2, days: 3);

        $updated = $d->with(['years' => 5, 'days' => 6]);

        $this->assertDurationIs(5, 2, 0, 6, 0, 0, 0, 0, 0, 0, $updated);
    }

    public function testEquals(): void
    {
        $a = new Duration(hours: 1, minutes: 30);
        $b = new Duration(hours: 1, minutes: 30);

        static::assertTrue($a->equals($b));
    }

    public function testNotEquals(): void
    {
        $a = new Duration(hours: 1);
        $b = new Duration(hours: 2);

        static::assertFalse($a->equals($b));
    }
}
