<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Duration;

final class DurationFromFieldsTest extends TestCase
{
    public function testMonthsOnly(): void
    {
        $d = Duration::from('P6M');

        static::assertSame(6, $d->months);
        static::assertSame(0, $d->years);
    }

    public function testWeeksOnly(): void
    {
        $d = Duration::from('P2W');

        static::assertSame(2, $d->weeks);
        static::assertSame(0, $d->months);
    }

    public function testDaysOnly(): void
    {
        $d = Duration::from('P4D');

        static::assertSame(4, $d->days);
        static::assertSame(0, $d->weeks);
    }

    public function testHoursOnly(): void
    {
        $d = Duration::from('PT2H');

        static::assertSame(2, $d->hours);
        static::assertSame(0, $d->days);
    }

    public function testMinutesOnly(): void
    {
        $d = Duration::from('PT7M');

        static::assertSame(7, $d->minutes);
        static::assertSame(0, $d->hours);
    }

    public function testSecondsOnly(): void
    {
        $d = Duration::from('PT7S');

        static::assertSame(7, $d->seconds);
        static::assertSame(0, $d->minutes);
    }

    public function testFractionSevenDigits(): void
    {
        // 7 fractional digits: str_pad must pad to 9, not 8
        $d = Duration::from('PT0.1234567S');

        static::assertSame(0, $d->seconds);
        static::assertSame(123, $d->milliseconds);
        static::assertSame(456, $d->microseconds);
        static::assertSame(700, $d->nanoseconds);
    }

    public function testFractionOneNano(): void
    {
        // nanoseconds offset must be 6, not 7
        $d = Duration::from('PT0.0000001S');

        static::assertSame(0, $d->milliseconds);
        static::assertSame(0, $d->microseconds);
        static::assertSame(100, $d->nanoseconds);
    }

    public function testFractionExactly9DigitsValid(): void
    {
        // TC39: seconds fraction must have 1–9 digits; exactly 9 must be accepted.
        $d = Duration::from('PT0.000000001S');

        static::assertSame(1, $d->nanoseconds);
    }

    public function testFraction10DigitsInvalid(): void
    {
        // TC39: seconds fraction must have at most 9 digits; 10 digits is invalid.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('seconds fraction must have at most 9 digits');

        Duration::from('PT0.0000000001S');
    }

    public function testInvalidFormatExactMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected ISO 8601 duration.');

        Duration::from('not-a-duration');
    }
}
