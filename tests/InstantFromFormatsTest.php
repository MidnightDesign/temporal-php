<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Instant;

/**
 * Verifies that Instant::from() accepts all TC39-required ISO 8601 string formats.
 */
final class InstantFromFormatsTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function equivalentFormatProvider(): array
    {
        // Each case is [variant string, expected epochNanoseconds].
        // The reference instant is 1976-11-18T15:23:30Z = 217_178_610_000_000_000 ns.
        $ref = 217_178_610_000_000_000;

        return [
            'compact date' => ['19761118T15:23:30Z', $ref],
            'compact time' => ['1976-11-18T152330Z', $ref],
            'compact date and time' => ['19761118T152330Z', $ref],
            'short offset +HHMM' => ['1976-11-18T15:23:30+0000', $ref],
            'short offset +HH' => ['1976-11-18T15:23:30+00', $ref],
            'short offset with shift' => ['1976-11-18T20:53:30+0530', $ref],
            'space separator' => ['1976-11-18 15:23:30Z', $ref],
            'extended positive year' => ['+001976-11-18T15:23:30Z', $ref],
        ];
    }

    #[DataProvider('equivalentFormatProvider')]
    public function testEquivalentFormats(string $input, int $expectedNs): void
    {
        $instant = Instant::from($input);

        static::assertSame($expectedNs, $instant->epochNanoseconds);
    }

    public function testSecondsOptional(): void
    {
        // 1976-11-18T15:23Z must equal 1976-11-18T15:23:00Z
        $withSeconds = Instant::from('1976-11-18T15:23:00Z');
        $withoutSeconds = Instant::from('1976-11-18T15:23Z');

        static::assertSame($withSeconds->epochNanoseconds, $withoutSeconds->epochNanoseconds);
    }

    public function testCommaAsDecimalSeparator(): void
    {
        // Comma must behave identically to a period for the fractional part.
        $withPeriod = Instant::from('1976-11-18T15:23:30.12Z');
        $withComma = Instant::from('1976-11-18T15:23:30,12Z');

        static::assertSame($withPeriod->epochNanoseconds, $withComma->epochNanoseconds);
    }

    public function testNegativeYear(): void
    {
        // The format is accepted by the parser; year -9999 overflows the
        // nanosecond range (~1678–2262), so an InvalidArgumentException is expected.
        $this->expectException(InvalidArgumentException::class);
        Instant::from('-009999-11-18T15:23:30Z');
    }

    public function testMultipleAnnotationsIgnored(): void
    {
        $withTwo = Instant::from('2020-01-01T00:00:00Z[UTC][u-ca=hebrew]');
        $withNone = Instant::from('2020-01-01T00:00:00Z');

        static::assertSame($withNone->epochNanoseconds, $withTwo->epochNanoseconds);
    }

    public function testLeapSecondNormalized(): void
    {
        // 2016-12-31T23:59:60Z is a real leap second; it must normalize to 2017-01-01T00:00:00Z.
        $leapSecond = Instant::from('2016-12-31T23:59:60Z');
        $nextMinute = Instant::from('2017-01-01T00:00:00Z');

        static::assertSame($nextMinute->epochNanoseconds, $leapSecond->epochNanoseconds);
    }

    public function testCompactDateTimeWithOffsetAndFraction(): void
    {
        // Both compact date+time and a short ±HHMM offset together.
        $compact = Instant::from('19761118T152330.1+0000');
        $extended = Instant::from('1976-11-18T15:23:30.1Z');

        static::assertSame($extended->epochNanoseconds, $compact->epochNanoseconds);
    }

    public function testCompactDateWithJanuaryMonth(): void
    {
        // Compact date where month is 01: chars [0,1]='01', chars [2,3]='18'.
        // Verifies that substr(offset: 0, length: 2) is used for the month, not offset: 1.
        $compact = Instant::from('19760118T15:23:30Z');
        $extended = Instant::from('1976-01-18T15:23:30Z');

        static::assertSame($extended->epochNanoseconds, $compact->epochNanoseconds);
    }
}
