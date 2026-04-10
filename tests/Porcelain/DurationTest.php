<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Temporal\Duration;
use Temporal\PlainDate;
use Temporal\RoundingMode;
use Temporal\Unit;

final class DurationTest extends TemporalTestCase
{
    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testDefaultConstructorAllZero(): void
    {
        $d = new Duration();

        $this->assertDurationIs(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, $d);
    }

    public function testAllPositive(): void
    {
        $d = new Duration(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

        $this->assertDurationIs(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, $d);
    }

    public function testAllNegative(): void
    {
        $d = new Duration(-1, -2, -3, -4, -5, -6, -7, -8, -9, -10);

        self::assertSame(-1, $d->years);
        self::assertSame(-5, $d->hours);
        self::assertSame(-10, $d->nanoseconds);
    }

    public function testSingleField(): void
    {
        $d = new Duration(days: 7);

        self::assertSame(7, $d->days);
        self::assertSame(0, $d->years);
    }

    public function testMixedSignsThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Duration(years: 1, days: -1);
    }

    // -------------------------------------------------------------------------
    // Virtual properties: sign and blank
    // -------------------------------------------------------------------------

    public function testSignPositive(): void
    {
        self::assertSame(1, new Duration(years: 1)->sign);
    }

    public function testSignNegative(): void
    {
        self::assertSame(-1, new Duration(hours: -3)->sign);
    }

    public function testSignZero(): void
    {
        self::assertSame(0, new Duration()->sign);
    }

    public function testBlankTrue(): void
    {
        self::assertTrue(new Duration()->blank);
    }

    public function testBlankFalse(): void
    {
        self::assertFalse(new Duration(nanoseconds: 1)->blank);
    }

    // -------------------------------------------------------------------------
    // parse()
    // -------------------------------------------------------------------------

    public function testParseSimple(): void
    {
        $d = Duration::parse('P1Y2M3DT4H5M6S');

        $this->assertDurationIs(1, 2, 0, 3, 4, 5, 6, 0, 0, 0, $d);
    }

    public function testParseNegative(): void
    {
        $d = Duration::parse('-P1DT2H');

        self::assertSame(-1, $d->days);
        self::assertSame(-2, $d->hours);
    }

    public function testParseFractionalSeconds(): void
    {
        $d = Duration::parse('PT1.5S');

        self::assertSame(1, $d->seconds);
        self::assertSame(500, $d->milliseconds);
    }

    public function testParseInvalidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::parse('not-a-duration');
    }

    /** @return list<array{string}> */
    public static function roundTripProvider(): array
    {
        return [
            ['PT0S'],
            ['P1Y'],
            ['P1Y2M3W4DT5H6M7S'],
            ['-P1DT2H'],
            ['PT1.5S'],
            ['PT0.000000001S'],
            ['PT7.008009001S'],
        ];
    }

    #[DataProvider('roundTripProvider')]
    public function testParseRoundTrip(string $iso): void
    {
        self::assertSame($iso, Duration::parse($iso)->toString());
    }

    // -------------------------------------------------------------------------
    // compare()
    // -------------------------------------------------------------------------

    public function testCompareEqual(): void
    {
        $a = new Duration(hours: 1);
        $b = new Duration(hours: 1);

        self::assertSame(0, Duration::compare($a, $b));
    }

    public function testCompareLess(): void
    {
        $a = new Duration(hours: 1);
        $b = new Duration(hours: 2);

        self::assertSame(-1, Duration::compare($a, $b));
    }

    public function testCompareGreater(): void
    {
        $a = new Duration(hours: 2);
        $b = new Duration(hours: 1);

        self::assertSame(1, Duration::compare($a, $b));
    }

    public function testCompareWithCalendarUnitsRequiresRelativeTo(): void
    {
        $a = new Duration(months: 1);
        $b = new Duration(days: 30);

        $this->expectException(InvalidArgumentException::class);

        Duration::compare($a, $b);
    }

    public function testCompareWithRelativeTo(): void
    {
        $a = new Duration(months: 1);
        $b = new Duration(days: 31);
        $relativeTo = new PlainDate(2024, 1, 1);

        // January has 31 days, so P1M from Jan 1 = 31 days = P31D => equal
        self::assertSame(0, Duration::compare($a, $b, $relativeTo));
    }

    // -------------------------------------------------------------------------
    // negated()
    // -------------------------------------------------------------------------

    public function testNegated(): void
    {
        $d = new Duration(years: 1, hours: 2);

        $n = $d->negated();

        self::assertSame(-1, $n->years);
        self::assertSame(-2, $n->hours);
    }

    public function testNegatedBlank(): void
    {
        self::assertTrue(new Duration()->negated()->blank);
    }

    public function testNegatedReturnsNewInstance(): void
    {
        $d = new Duration(days: 3);
        $n = $d->negated();

        self::assertSame(3, $d->days);
        self::assertSame(-3, $n->days);
    }

    // -------------------------------------------------------------------------
    // abs()
    // -------------------------------------------------------------------------

    public function testAbsFromNegative(): void
    {
        $d = new Duration(days: -3, minutes: -15);
        $a = $d->abs();

        self::assertSame(3, $a->days);
        self::assertSame(15, $a->minutes);
    }

    public function testAbsFromPositive(): void
    {
        $d = new Duration(years: 2);

        self::assertTrue($d->abs()->equals($d));
    }

    public function testAbsBlank(): void
    {
        self::assertTrue(new Duration()->abs()->blank);
    }

    // -------------------------------------------------------------------------
    // equals()
    // -------------------------------------------------------------------------

    public function testEqualsTrue(): void
    {
        $a = new Duration(hours: 1, minutes: 30);
        $b = new Duration(hours: 1, minutes: 30);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalse(): void
    {
        $a = new Duration(hours: 1);
        $b = new Duration(hours: 2);

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // with()
    // -------------------------------------------------------------------------

    public function testWithSingleField(): void
    {
        $d = new Duration(years: 1, months: 2);

        $updated = $d->with(years: 5);

        self::assertSame(5, $updated->years);
        self::assertSame(2, $updated->months);
    }

    public function testWithMultipleFields(): void
    {
        $d = new Duration(years: 1, months: 2, days: 3);

        $updated = $d->with(years: 5, days: 6);

        $this->assertDurationIs(5, 2, 0, 6, 0, 0, 0, 0, 0, 0, $updated);
    }

    public function testWithNoFieldsThrows(): void
    {
        $d = new Duration(years: 1);

        $this->expectException(\TypeError::class);

        $d->with();
    }

    public function testWithDoesNotMutateOriginal(): void
    {
        $d = new Duration(years: 1, months: 2);

        $d->with(years: 5);

        self::assertSame(1, $d->years);
    }

    // -------------------------------------------------------------------------
    // add() / subtract()
    // -------------------------------------------------------------------------

    public function testAdd(): void
    {
        $a = new Duration(hours: 1, minutes: 30);
        $b = new Duration(hours: 2, minutes: 45);

        $result = $a->add($b);

        self::assertSame(4, $result->hours);
        self::assertSame(15, $result->minutes);
    }

    public function testAddWithCalendarFieldsThrows(): void
    {
        $a = new Duration(months: 1);
        $b = new Duration(days: 5);

        $this->expectException(InvalidArgumentException::class);

        $a->add($b);
    }

    public function testSubtract(): void
    {
        $a = new Duration(hours: 3);
        $b = new Duration(hours: 1);

        $result = $a->subtract($b);

        self::assertSame(2, $result->hours);
    }

    public function testSubtractResultingInNegative(): void
    {
        $a = new Duration(hours: 1);
        $b = new Duration(hours: 3);

        $result = $a->subtract($b);

        self::assertSame(-2, $result->hours);
    }

    // -------------------------------------------------------------------------
    // round()
    // -------------------------------------------------------------------------

    public function testRoundSmallestUnit(): void
    {
        $d = new Duration(hours: 1, minutes: 30, seconds: 45);

        $rounded = $d->round(smallestUnit: Unit::Minute);

        self::assertSame(1, $rounded->hours);
        self::assertSame(31, $rounded->minutes);
        self::assertSame(0, $rounded->seconds);
    }

    public function testRoundLargestUnit(): void
    {
        $d = new Duration(seconds: 3661);

        $rounded = $d->round(largestUnit: Unit::Hour, smallestUnit: Unit::Second);

        self::assertSame(1, $rounded->hours);
        self::assertSame(1, $rounded->minutes);
        self::assertSame(1, $rounded->seconds);
    }

    public function testRoundWithTruncMode(): void
    {
        $d = new Duration(hours: 1, minutes: 30, seconds: 29);

        $rounded = $d->round(smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc);

        self::assertSame(1, $rounded->hours);
        self::assertSame(30, $rounded->minutes);
    }

    public function testRoundRequiresAtLeastOneUnit(): void
    {
        $d = new Duration(hours: 1);

        $this->expectException(InvalidArgumentException::class);

        $d->round();
    }

    // -------------------------------------------------------------------------
    // total()
    // -------------------------------------------------------------------------

    public function testTotalHours(): void
    {
        $d = new Duration(hours: 1, minutes: 30);

        self::assertSame(1.5, $d->total(Unit::Hour));
    }

    public function testTotalMinutes(): void
    {
        $d = new Duration(hours: 2);

        self::assertSame(120, $d->total(Unit::Minute));
    }

    public function testTotalSeconds(): void
    {
        $d = new Duration(minutes: 1, seconds: 30);

        self::assertSame(90, $d->total(Unit::Second));
    }

    public function testTotalWithCalendarUnitRequiresRelativeTo(): void
    {
        $d = new Duration(months: 1);

        $this->expectException(InvalidArgumentException::class);

        $d->total(Unit::Day);
    }

    public function testTotalNanoseconds(): void
    {
        $d = new Duration(milliseconds: 1, microseconds: 500);

        self::assertSame(1_500_000, $d->total(Unit::Nanosecond));
    }

    // -------------------------------------------------------------------------
    // toString() / __toString() / jsonSerialize()
    // -------------------------------------------------------------------------

    public function testToStringBlank(): void
    {
        self::assertSame('PT0S', new Duration()->toString());
    }

    public function testToStringSimple(): void
    {
        self::assertSame('P1Y', new Duration(years: 1)->toString());
    }

    public function testToStringFull(): void
    {
        $d = new Duration(1, 2, 3, 4, 5, 6, 7);

        self::assertSame('P1Y2M3W4DT5H6M7S', $d->toString());
    }

    public function testToStringNegative(): void
    {
        self::assertSame('-P1DT2H', new Duration(days: -1, hours: -2)->toString());
    }

    public function testToStringWithFractionalSecondDigits(): void
    {
        $d = new Duration(seconds: 1, milliseconds: 500);

        self::assertSame('PT1.500000000S', $d->toString(fractionalSecondDigits: 9));
    }

    public function testToStringWithSmallestUnit(): void
    {
        $d = new Duration(seconds: 1, milliseconds: 500, microseconds: 300);

        self::assertSame('PT1.500S', $d->toString(smallestUnit: Unit::Millisecond));
    }

    public function testMagicToString(): void
    {
        $d = new Duration(hours: 2);

        self::assertSame('PT2H', (string) $d);
    }

    public function testJsonSerialize(): void
    {
        $d = new Duration(hours: 2);

        self::assertSame('"PT2H"', json_encode($d));
    }

    // -------------------------------------------------------------------------
    // toSpec() / fromSpec()
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecDuration(): void
    {
        $d = new Duration(years: 1, months: 2, days: 3);
        $spec = $d->toSpec();

        self::assertSame(1, $spec->years);
        self::assertSame(2, $spec->months);
        self::assertSame(3, $spec->days);
    }

    public function testFromSpecRoundTrip(): void
    {
        $d = new Duration(
            years: 1,
            months: 2,
            weeks: 3,
            days: 4,
            hours: 5,
            minutes: 6,
            seconds: 7,
            milliseconds: 8,
            microseconds: 9,
            nanoseconds: 10,
        );
        $spec = $d->toSpec();
        $restored = Duration::fromSpec($spec);

        $this->assertDurationIs(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, $restored);
    }

    // -------------------------------------------------------------------------
    // __debugInfo()
    // -------------------------------------------------------------------------

    public function testDebugInfoContainsStringAndFields(): void
    {
        $d = new Duration(hours: 2, minutes: 30);
        $info = $d->__debugInfo();

        self::assertSame('PT2H30M', $info['string']);
        self::assertSame(2, $info['hours']);
        self::assertSame(30, $info['minutes']);
        self::assertSame(0, $info['years']);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: round() forwards roundingMode
    // -------------------------------------------------------------------------

    public function testRoundForwardsRoundingMode(): void
    {
        // 1h 30m 29s: HalfExpand rounds to 1h 30m, Ceil rounds to 1h 31m
        $d = new Duration(hours: 1, minutes: 30, seconds: 29);

        $halfExpand = $d->round(smallestUnit: Unit::Minute, roundingMode: RoundingMode::HalfExpand);
        $ceil = $d->round(smallestUnit: Unit::Minute, roundingMode: RoundingMode::Ceil);

        self::assertSame(30, $halfExpand->minutes);
        self::assertSame(31, $ceil->minutes);
    }

    public function testRoundForwardsRoundingIncrement(): void
    {
        $d = new Duration(hours: 1, minutes: 7);

        $rounded = $d->round(smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc, roundingIncrement: 5);

        self::assertSame(5, $rounded->minutes);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: toString() forwards roundingMode
    // -------------------------------------------------------------------------

    public function testToStringForwardsRoundingMode(): void
    {
        // 1.6s with fractionalSecondDigits: 0 and Ceil => 2s, Trunc => 1s
        $d = new Duration(seconds: 1, milliseconds: 600);

        $ceil = $d->toString(fractionalSecondDigits: 0, roundingMode: RoundingMode::Ceil);
        $trunc = $d->toString(fractionalSecondDigits: 0, roundingMode: RoundingMode::Trunc);

        self::assertSame('PT2S', $ceil);
        self::assertSame('PT1S', $trunc);
    }
}
