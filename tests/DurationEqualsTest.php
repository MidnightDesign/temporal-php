<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\Duration;

final class DurationEqualsTest extends TestCase
{
    public function testNotEqualsYears(): void
    {
        static::assertFalse(new Duration(years: 1)->equals(new Duration(years: 2)));
    }

    public function testNotEqualsMonths(): void
    {
        static::assertFalse(new Duration(months: 1)->equals(new Duration(months: 2)));
    }

    public function testNotEqualsWeeks(): void
    {
        static::assertFalse(new Duration(weeks: 1)->equals(new Duration(weeks: 2)));
    }

    public function testNotEqualsDays(): void
    {
        static::assertFalse(new Duration(days: 1)->equals(new Duration(days: 2)));
    }
}
