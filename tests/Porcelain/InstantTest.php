<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use Temporal\Duration;
use Temporal\Instant;
use Temporal\RoundingMode;

use Temporal\Unit;
use Temporal\ZonedDateTime;

final class InstantTest extends TemporalTestCase
{
    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorWithZero(): void
    {
        $i = new Instant(0);

        self::assertSame(0, $i->epochNanoseconds);
    }

    public function testConstructorWithPositiveValue(): void
    {
        // 2020-01-01T00:00:00Z in nanoseconds
        $ns = 1_577_836_800_000_000_000;
        $i = new Instant($ns);

        self::assertSame($ns, $i->epochNanoseconds);
    }

    public function testConstructorWithNegativeValue(): void
    {
        $ns = -1_000_000_000;
        $i = new Instant($ns);

        self::assertSame($ns, $i->epochNanoseconds);
    }

    // -------------------------------------------------------------------------
    // Virtual properties
    // -------------------------------------------------------------------------

    public function testEpochNanoseconds(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $i = new Instant($ns);

        self::assertSame($ns, $i->epochNanoseconds);
    }

    public function testEpochMilliseconds(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $i = new Instant($ns);

        self::assertSame(1_577_836_800_123, $i->epochMilliseconds);
    }

    public function testEpochMillisecondsZero(): void
    {
        $i = new Instant(0);

        self::assertSame(0, $i->epochMilliseconds);
    }

    public function testEpochMillisecondsNegative(): void
    {
        // -1.5 seconds => floor(-1_500_000_000 / 1_000_000) = -1500
        $i = new Instant(-1_500_000_000);

        self::assertSame(-1500, $i->epochMilliseconds);
    }

    // -------------------------------------------------------------------------
    // parse()
    // -------------------------------------------------------------------------

    public function testParseUtcZ(): void
    {
        $i = Instant::parse('1970-01-01T00:00:00Z');

        self::assertSame(0, $i->epochNanoseconds);
    }

    public function testParseWithOffset(): void
    {
        // 2020-01-01T00:00:00+00:00 = epoch 1577836800 seconds
        $i = Instant::parse('2020-01-01T00:00:00+00:00');

        self::assertSame(1_577_836_800_000_000_000, $i->epochNanoseconds);
    }

    public function testParseWithFractionalSeconds(): void
    {
        $i = Instant::parse('1970-01-01T00:00:01.500Z');

        self::assertSame(1_500_000_000, $i->epochNanoseconds);
    }

    public function testParseWithNanoseconds(): void
    {
        $i = Instant::parse('1970-01-01T00:00:00.123456789Z');

        self::assertSame(123_456_789, $i->epochNanoseconds);
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Instant::parse('not-an-instant');
    }

    public function testParseEmptyStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Instant::parse('');
    }

    // -------------------------------------------------------------------------
    // Static factories
    // -------------------------------------------------------------------------

    public function testFromEpochMilliseconds(): void
    {
        $i = Instant::fromEpochMilliseconds(1_577_836_800_000);

        self::assertSame(1_577_836_800_000_000_000, $i->epochNanoseconds);
        self::assertSame(1_577_836_800_000, $i->epochMilliseconds);
    }

    public function testFromEpochMillisecondsZero(): void
    {
        $i = Instant::fromEpochMilliseconds(0);

        self::assertSame(0, $i->epochNanoseconds);
    }

    public function testFromEpochMillisecondsNegative(): void
    {
        $i = Instant::fromEpochMilliseconds(-1000);

        self::assertSame(-1_000_000_000, $i->epochNanoseconds);
    }

    public function testFromEpochNanoseconds(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $i = Instant::fromEpochNanoseconds($ns);

        self::assertSame($ns, $i->epochNanoseconds);
    }

    public function testFromEpochNanosecondsZero(): void
    {
        $i = Instant::fromEpochNanoseconds(0);

        self::assertSame(0, $i->epochNanoseconds);
    }

    // -------------------------------------------------------------------------
    // compare()
    // -------------------------------------------------------------------------

    public function testCompareEqual(): void
    {
        $a = new Instant(1_000_000_000);
        $b = new Instant(1_000_000_000);

        self::assertSame(0, Instant::compare($a, $b));
    }

    public function testCompareLess(): void
    {
        $a = new Instant(1_000_000_000);
        $b = new Instant(2_000_000_000);

        self::assertSame(-1, Instant::compare($a, $b));
    }

    public function testCompareGreater(): void
    {
        $a = new Instant(2_000_000_000);
        $b = new Instant(1_000_000_000);

        self::assertSame(1, Instant::compare($a, $b));
    }

    // -------------------------------------------------------------------------
    // equals()
    // -------------------------------------------------------------------------

    public function testEqualsTrue(): void
    {
        $a = new Instant(1_577_836_800_000_000_000);
        $b = new Instant(1_577_836_800_000_000_000);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalse(): void
    {
        $a = new Instant(1_577_836_800_000_000_000);
        $b = new Instant(1_577_836_800_000_000_001);

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // add() / subtract()
    // -------------------------------------------------------------------------

    public function testAddHours(): void
    {
        $i = new Instant(0);
        $result = $i->add(new Duration(hours: 1));

        self::assertSame(3_600_000_000_000, $result->epochNanoseconds);
    }

    public function testAddMinutesAndSeconds(): void
    {
        $i = new Instant(0);
        $result = $i->add(new Duration(minutes: 30, seconds: 15));

        $expected = (30 * 60 + 15) * 1_000_000_000;
        self::assertSame($expected, $result->epochNanoseconds);
    }

    public function testAddNanoseconds(): void
    {
        $i = new Instant(0);
        $result = $i->add(new Duration(nanoseconds: 500));

        self::assertSame(500, $result->epochNanoseconds);
    }

    public function testAddDoesNotMutateOriginal(): void
    {
        $i = new Instant(0);
        $i->add(new Duration(hours: 1));

        self::assertSame(0, $i->epochNanoseconds);
    }

    public function testAddWithCalendarFieldsThrows(): void
    {
        $i = new Instant(0);

        $this->expectException(InvalidArgumentException::class);

        $i->add(new Duration(days: 1));
    }

    public function testSubtractHours(): void
    {
        $i = new Instant(7_200_000_000_000);
        $result = $i->subtract(new Duration(hours: 1));

        self::assertSame(3_600_000_000_000, $result->epochNanoseconds);
    }

    public function testSubtractToNegative(): void
    {
        $i = new Instant(0);
        $result = $i->subtract(new Duration(seconds: 1));

        self::assertSame(-1_000_000_000, $result->epochNanoseconds);
    }

    public function testSubtractWithCalendarFieldsThrows(): void
    {
        $i = new Instant(0);

        $this->expectException(InvalidArgumentException::class);

        $i->subtract(new Duration(months: 1));
    }

    // -------------------------------------------------------------------------
    // round()
    // -------------------------------------------------------------------------

    public function testRoundToSecond(): void
    {
        // 1.6 seconds -> round halfExpand -> 2 seconds
        $i = new Instant(1_600_000_000);
        $result = $i->round(Unit::Second);

        self::assertSame(2_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundToSecondTrunc(): void
    {
        // 1.6 seconds -> trunc -> 1 second
        $i = new Instant(1_600_000_000);
        $result = $i->round(Unit::Second, RoundingMode::Trunc);

        self::assertSame(1_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundToMinute(): void
    {
        // 90 seconds -> round halfExpand -> 2 minutes
        $i = new Instant(90_000_000_000);
        $result = $i->round(Unit::Minute);

        self::assertSame(120_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundToMillisecond(): void
    {
        $i = new Instant(1_500_500);
        $result = $i->round(Unit::Millisecond);

        self::assertSame(2_000_000, $result->epochNanoseconds);
    }

    public function testRoundWithIncrement(): void
    {
        // 7 seconds rounded to increment of 5 with halfExpand = 5 seconds
        $i = new Instant(7_000_000_000);
        $result = $i->round(Unit::Second, RoundingMode::HalfExpand, 5);

        self::assertSame(5_000_000_000, $result->epochNanoseconds);
    }

    public function testRoundCeil(): void
    {
        // 1.001 seconds -> ceil to second -> 2 seconds
        $i = new Instant(1_001_000_000);
        $result = $i->round(Unit::Second, RoundingMode::Ceil);

        self::assertSame(2_000_000_000, $result->epochNanoseconds);
    }

    // -------------------------------------------------------------------------
    // since() / until()
    // -------------------------------------------------------------------------

    public function testSinceBasic(): void
    {
        $a = new Instant(0);
        $b = new Instant(3_600_000_000_000);
        $d = $b->since($a);

        self::assertSame(3600, $d->seconds);
    }

    public function testSinceNegative(): void
    {
        $a = new Instant(3_600_000_000_000);
        $b = new Instant(0);
        $d = $b->since($a);

        self::assertSame(-3600, $d->seconds);
    }

    public function testSinceWithLargestUnit(): void
    {
        $a = new Instant(0);
        $b = new Instant(7_200_000_000_000);
        $d = $b->since($a, largestUnit: Unit::Hour);

        self::assertSame(2, $d->hours);
        self::assertSame(0, $d->seconds);
    }

    public function testSinceWithSmallestUnit(): void
    {
        $a = new Instant(0);
        $b = new Instant(1_500_000_000);
        $d = $b->since($a, smallestUnit: Unit::Second);

        self::assertSame(1, $d->seconds);
        self::assertSame(0, $d->nanoseconds);
    }

    public function testSinceWithRoundingMode(): void
    {
        $a = new Instant(0);
        $b = new Instant(1_600_000_000);
        $d = $b->since($a, smallestUnit: Unit::Second, roundingMode: RoundingMode::Ceil);

        self::assertSame(2, $d->seconds);
    }

    public function testUntilBasic(): void
    {
        $a = new Instant(0);
        $b = new Instant(3_600_000_000_000);
        $d = $a->until($b);

        self::assertSame(3600, $d->seconds);
    }

    public function testUntilNegative(): void
    {
        $a = new Instant(3_600_000_000_000);
        $b = new Instant(0);
        $d = $a->until($b);

        self::assertSame(-3600, $d->seconds);
    }

    public function testUntilWithLargestUnit(): void
    {
        $a = new Instant(0);
        $b = new Instant(7_200_000_000_000);
        $d = $a->until($b, largestUnit: Unit::Hour);

        self::assertSame(2, $d->hours);
    }

    // -------------------------------------------------------------------------
    // toZonedDateTime()
    // -------------------------------------------------------------------------

    public function testToZonedDateTimeUtc(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00Z');
        $zdt = $i->toZonedDateTime('UTC');

        self::assertSame('UTC', $zdt->timeZoneId);
        self::assertSame(2020, $zdt->year);
        self::assertSame(1, $zdt->month);
        self::assertSame(1, $zdt->day);
        self::assertSame(0, $zdt->hour);
    }

    public function testToZonedDateTimeWithPositiveOffset(): void
    {
        // 2020-01-01T00:00:00Z displayed in +05:30 is 2020-01-01T05:30:00
        $i = Instant::parse('2020-01-01T00:00:00Z');
        $zdt = $i->toZonedDateTime('+05:30');

        self::assertSame('+05:30', $zdt->timeZoneId);
        self::assertSame(5, $zdt->hour);
        self::assertSame(30, $zdt->minute);
    }

    public function testToZonedDateTimeWithNegativeOffset(): void
    {
        // 2020-01-01T00:00:00Z displayed in -05:00 is 2019-12-31T19:00:00
        $i = Instant::parse('2020-01-01T00:00:00Z');
        $zdt = $i->toZonedDateTime('-05:00');

        self::assertSame('-05:00', $zdt->timeZoneId);
        self::assertSame(19, $zdt->hour);
        self::assertSame(31, $zdt->day);
    }

    public function testToZonedDateTimePreservesEpochNanoseconds(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $i = new Instant($ns);
        $zdt = $i->toZonedDateTime('UTC');

        self::assertSame($ns, $zdt->epochNanoseconds);
    }

    // -------------------------------------------------------------------------
    // toString()
    // -------------------------------------------------------------------------

    public function testToStringDefault(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00Z');

        self::assertSame('2020-01-01T00:00:00Z', $i->toString());
    }

    public function testToStringWithFractionalSecondDigits(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00.100Z');

        self::assertSame('2020-01-01T00:00:00.100Z', $i->toString(fractionalSecondDigits: 3));
    }

    public function testToStringWithFractionalSecondDigitsZero(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00.500Z');

        self::assertSame('2020-01-01T00:00:00Z', $i->toString(fractionalSecondDigits: 0));
    }

    public function testToStringWithSmallestUnit(): void
    {
        $i = Instant::parse('2020-01-01T00:00:30.123Z');

        // smallestUnit: minute produces HH:MM format (no seconds)
        self::assertSame('2020-01-01T00:00Z', $i->toString(smallestUnit: Unit::Minute));
    }

    public function testToStringWithRoundingMode(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00.600Z');

        self::assertSame('2020-01-01T00:00:01Z', $i->toString(
            fractionalSecondDigits: 0,
            roundingMode: RoundingMode::Ceil,
        ));
    }

    public function testToStringWithTimeZone(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00Z');

        self::assertSame('2020-01-01T05:30:00+05:30', $i->toString(timeZone: '+05:30'));
    }

    // -------------------------------------------------------------------------
    // __toString() / jsonSerialize()
    // -------------------------------------------------------------------------

    public function testMagicToString(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00Z');

        self::assertSame('2020-01-01T00:00:00Z', (string) $i);
    }

    public function testJsonSerialize(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00Z');

        self::assertSame('"2020-01-01T00:00:00Z"', json_encode($i));
    }

    // -------------------------------------------------------------------------
    // toSpec() / fromSpec()
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecInstant(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $i = new Instant($ns);
        $spec = $i->toSpec();

        self::assertSame($ns, $spec->epochNanoseconds);
    }

    public function testFromSpecRoundTrip(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $i = new Instant($ns);
        $restored = Instant::fromSpec($i->toSpec());

        self::assertTrue($i->equals($restored));
    }

    public function testFromSpecCreatesCorrectInstance(): void
    {
        $spec = new \Temporal\Spec\Instant(42_000_000_000);
        $i = Instant::fromSpec($spec);

        self::assertSame(42_000_000_000, $i->epochNanoseconds);
    }

    // -------------------------------------------------------------------------
    // __debugInfo()
    // -------------------------------------------------------------------------

    public function testDebugInfoContainsExpectedKeys(): void
    {
        $i = Instant::parse('2020-01-01T00:00:00Z');
        $info = $i->__debugInfo();

        self::assertArrayHasKey('epochNanoseconds', $info);
        self::assertArrayHasKey('epochMilliseconds', $info);
        self::assertArrayHasKey('string', $info);
        self::assertSame(1_577_836_800_000_000_000, $info['epochNanoseconds']);
        self::assertSame(1_577_836_800_000, $info['epochMilliseconds']);
        self::assertSame('2020-01-01T00:00:00Z', $info['string']);
    }

    // -------------------------------------------------------------------------
    // parse() round-trip
    // -------------------------------------------------------------------------

    public function testParseRoundTrip(): void
    {
        $iso = '2020-06-15T12:30:45.123456789Z';
        $i = Instant::parse($iso);

        self::assertSame($iso, $i->toString(fractionalSecondDigits: 9));
    }

    public function testParseRoundTripDefault(): void
    {
        $iso = '2020-01-01T00:00:00Z';
        $i = Instant::parse($iso);

        self::assertSame($iso, (string) $i);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: until() forwards all options
    // -------------------------------------------------------------------------

    public function testUntilForwardsSmallestUnit(): void
    {
        $a = new Instant(0);
        $b = new Instant(1_500_000_000); // 1.5s
        $d = $a->until($b, smallestUnit: Unit::Second);

        self::assertSame(1, $d->seconds);
        self::assertSame(0, $d->nanoseconds);
    }

    public function testUntilForwardsRoundingMode(): void
    {
        $a = new Instant(0);
        $b = new Instant(1_600_000_000); // 1.6s
        $d = $a->until($b, smallestUnit: Unit::Second, roundingMode: RoundingMode::Ceil);

        self::assertSame(2, $d->seconds);
    }

    public function testUntilForwardsRoundingIncrement(): void
    {
        $a = new Instant(0);
        $b = new Instant(7_000_000_000); // 7s
        $d = $a->until($b, smallestUnit: Unit::Second, roundingIncrement: 5);

        // 7 seconds truncated to increment of 5 = 5
        self::assertSame(5, $d->seconds);
    }

    public function testSinceForwardsRoundingIncrement(): void
    {
        $a = new Instant(0);
        $b = new Instant(7_000_000_000); // 7s
        $d = $b->since($a, smallestUnit: Unit::Second, roundingIncrement: 5);

        self::assertSame(5, $d->seconds);
    }
}
