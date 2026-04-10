<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use Temporal\Duration;
use Temporal\PlainTime;
use Temporal\RoundingMode;
use Temporal\Unit;

final class PlainTimeTest extends TemporalTestCase
{
    // -------------------------------------------------------------------------
    // Constructor & virtual properties
    // -------------------------------------------------------------------------

    public function testDefaultConstructorMidnight(): void
    {
        $t = new PlainTime();

        $this->assertPlainTimeIs(0, 0, 0, 0, 0, 0, $t);
    }

    public function testConstructorAllFields(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);

        $this->assertPlainTimeIs(13, 45, 30, 123, 456, 789, $t);
    }

    public function testConstructorPartialFields(): void
    {
        $t = new PlainTime(10, 30);

        $this->assertPlainTimeIs(10, 30, 0, 0, 0, 0, $t);
    }

    public function testConstructorNamedParameters(): void
    {
        $t = new PlainTime(hour: 8, second: 15);

        $this->assertPlainTimeIs(8, 0, 15, 0, 0, 0, $t);
    }

    // -------------------------------------------------------------------------
    // parse()
    // -------------------------------------------------------------------------

    public function testParseBasic(): void
    {
        $t = PlainTime::parse('13:45:30');

        $this->assertPlainTimeIs(13, 45, 30, 0, 0, 0, $t);
    }

    public function testParseWithFractionalSeconds(): void
    {
        $t = PlainTime::parse('13:45:30.123456789');

        $this->assertPlainTimeIs(13, 45, 30, 123, 456, 789, $t);
    }

    public function testParseWithMilliseconds(): void
    {
        $t = PlainTime::parse('09:30:00.500');

        $this->assertPlainTimeIs(9, 30, 0, 500, 0, 0, $t);
    }

    public function testParseMinimal(): void
    {
        $t = PlainTime::parse('08:00');

        $this->assertPlainTimeIs(8, 0, 0, 0, 0, 0, $t);
    }

    public function testParseWithLeadingT(): void
    {
        $t = PlainTime::parse('T12:30:00');

        $this->assertPlainTimeIs(12, 30, 0, 0, 0, 0, $t);
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PlainTime::parse('not-a-time');
    }

    public function testParseEmptyStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PlainTime::parse('');
    }

    // -------------------------------------------------------------------------
    // compare()
    // -------------------------------------------------------------------------

    public function testCompareEqual(): void
    {
        $a = new PlainTime(12, 30);
        $b = new PlainTime(12, 30);

        self::assertSame(0, PlainTime::compare($a, $b));
    }

    public function testCompareLess(): void
    {
        $a = new PlainTime(10, 0);
        $b = new PlainTime(12, 0);

        self::assertSame(-1, PlainTime::compare($a, $b));
    }

    public function testCompareGreater(): void
    {
        $a = new PlainTime(14, 0);
        $b = new PlainTime(12, 0);

        self::assertSame(1, PlainTime::compare($a, $b));
    }

    public function testCompareWithSubSeconds(): void
    {
        $a = new PlainTime(12, 0, 0, 0, 0, 1);
        $b = new PlainTime(12, 0, 0, 0, 0, 0);

        self::assertSame(1, PlainTime::compare($a, $b));
    }

    // -------------------------------------------------------------------------
    // with()
    // -------------------------------------------------------------------------

    public function testWithSingleField(): void
    {
        $t = new PlainTime(10, 20, 30);
        $updated = $t->with(hour: 15);

        $this->assertPlainTimeIs(15, 20, 30, 0, 0, 0, $updated);
    }

    public function testWithMultipleFields(): void
    {
        $t = new PlainTime(10, 20, 30, 100, 200, 300);
        $updated = $t->with(minute: 45, nanosecond: 999);

        $this->assertPlainTimeIs(10, 45, 30, 100, 200, 999, $updated);
    }

    public function testWithNoFieldsReturnsCopy(): void
    {
        $t = new PlainTime(10, 20, 30);
        $updated = $t->with();

        $this->assertPlainTimeIs(10, 20, 30, 0, 0, 0, $updated);
    }

    public function testWithDoesNotMutateOriginal(): void
    {
        $t = new PlainTime(10, 20, 30);
        $t->with(hour: 15);

        self::assertSame(10, $t->hour);
    }

    // -------------------------------------------------------------------------
    // add() / subtract()
    // -------------------------------------------------------------------------

    public function testAddHours(): void
    {
        $t = new PlainTime(10, 0);
        $result = $t->add(new Duration(hours: 3));

        $this->assertPlainTimeIs(13, 0, 0, 0, 0, 0, $result);
    }

    public function testAddWrapsAroundMidnight(): void
    {
        $t = new PlainTime(23, 0);
        $result = $t->add(new Duration(hours: 2));

        $this->assertPlainTimeIs(1, 0, 0, 0, 0, 0, $result);
    }

    public function testAddMinutesAndSeconds(): void
    {
        $t = new PlainTime(10, 30, 45);
        $result = $t->add(new Duration(minutes: 35, seconds: 20));

        $this->assertPlainTimeIs(11, 6, 5, 0, 0, 0, $result);
    }

    public function testAddSubSecondFields(): void
    {
        $t = new PlainTime(12, 0, 0);
        $result = $t->add(new Duration(milliseconds: 500, microseconds: 300, nanoseconds: 100));

        $this->assertPlainTimeIs(12, 0, 0, 500, 300, 100, $result);
    }

    public function testSubtractHours(): void
    {
        $t = new PlainTime(10, 0);
        $result = $t->subtract(new Duration(hours: 3));

        $this->assertPlainTimeIs(7, 0, 0, 0, 0, 0, $result);
    }

    public function testSubtractWrapsAroundMidnight(): void
    {
        $t = new PlainTime(1, 0);
        $result = $t->subtract(new Duration(hours: 3));

        $this->assertPlainTimeIs(22, 0, 0, 0, 0, 0, $result);
    }

    public function testAddDoesNotMutateOriginal(): void
    {
        $t = new PlainTime(10, 0);
        $t->add(new Duration(hours: 3));

        self::assertSame(10, $t->hour);
    }

    // -------------------------------------------------------------------------
    // round()
    // -------------------------------------------------------------------------

    public function testRoundToHour(): void
    {
        $t = new PlainTime(13, 45, 30);
        $rounded = $t->round(Unit::Hour);

        $this->assertPlainTimeIs(14, 0, 0, 0, 0, 0, $rounded);
    }

    public function testRoundToMinute(): void
    {
        $t = new PlainTime(13, 45, 30);
        $rounded = $t->round(Unit::Minute);

        $this->assertPlainTimeIs(13, 46, 0, 0, 0, 0, $rounded);
    }

    public function testRoundToSecond(): void
    {
        $t = new PlainTime(13, 45, 30, 500);
        $rounded = $t->round(Unit::Second);

        $this->assertPlainTimeIs(13, 45, 31, 0, 0, 0, $rounded);
    }

    public function testRoundWithTruncMode(): void
    {
        $t = new PlainTime(13, 45, 30, 999);
        $rounded = $t->round(Unit::Second, RoundingMode::Trunc);

        $this->assertPlainTimeIs(13, 45, 30, 0, 0, 0, $rounded);
    }

    public function testRoundToMillisecond(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 500);
        $rounded = $t->round(Unit::Millisecond);

        $this->assertPlainTimeIs(13, 45, 30, 124, 0, 0, $rounded);
    }

    public function testRoundToMicrosecond(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 500);
        $rounded = $t->round(Unit::Microsecond);

        $this->assertPlainTimeIs(13, 45, 30, 123, 457, 0, $rounded);
    }

    public function testRoundToNanosecond(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);
        $rounded = $t->round(Unit::Nanosecond);

        $this->assertPlainTimeIs(13, 45, 30, 123, 456, 789, $rounded);
    }

    public function testRoundWithIncrement(): void
    {
        $t = new PlainTime(13, 47);
        $rounded = $t->round(Unit::Minute, RoundingMode::HalfExpand, 15);

        $this->assertPlainTimeIs(13, 45, 0, 0, 0, 0, $rounded);
    }

    // -------------------------------------------------------------------------
    // since() / until()
    // -------------------------------------------------------------------------

    public function testUntilBasic(): void
    {
        $a = new PlainTime(10, 0);
        $b = new PlainTime(13, 30);
        $d = $a->until($b);

        self::assertSame(3, $d->hours);
        self::assertSame(30, $d->minutes);
    }

    public function testSinceBasic(): void
    {
        $a = new PlainTime(13, 30);
        $b = new PlainTime(10, 0);
        $d = $a->since($b);

        self::assertSame(3, $d->hours);
        self::assertSame(30, $d->minutes);
    }

    public function testUntilReturnsNegativeWhenOtherIsBefore(): void
    {
        $a = new PlainTime(13, 30);
        $b = new PlainTime(10, 0);
        $d = $a->until($b);

        self::assertSame(-3, $d->hours);
        self::assertSame(-30, $d->minutes);
    }

    public function testSinceWithLargestUnit(): void
    {
        $a = new PlainTime(13, 30, 45);
        $b = new PlainTime(10, 0, 0);
        $d = $a->since($b, largestUnit: Unit::Minute);

        self::assertSame(0, $d->hours);
        self::assertSame(210, $d->minutes);
        self::assertSame(45, $d->seconds);
    }

    public function testUntilWithSmallestUnit(): void
    {
        $a = new PlainTime(10, 0, 0);
        $b = new PlainTime(10, 30, 45);
        $d = $a->until($b, smallestUnit: Unit::Minute);

        self::assertSame(0, $d->hours);
        self::assertSame(30, $d->minutes);
        self::assertSame(0, $d->seconds);
    }

    public function testSinceWithSubSeconds(): void
    {
        $a = new PlainTime(10, 0, 1, 500, 0, 0);
        $b = new PlainTime(10, 0, 0, 0, 0, 0);
        $d = $a->since($b);

        self::assertSame(1, $d->seconds);
        self::assertSame(500, $d->milliseconds);
    }

    // -------------------------------------------------------------------------
    // equals()
    // -------------------------------------------------------------------------

    public function testEqualsTrue(): void
    {
        $a = new PlainTime(13, 45, 30, 123, 456, 789);
        $b = new PlainTime(13, 45, 30, 123, 456, 789);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalse(): void
    {
        $a = new PlainTime(13, 45, 30);
        $b = new PlainTime(13, 45, 31);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsMidnight(): void
    {
        $a = new PlainTime();
        $b = new PlainTime(0, 0, 0, 0, 0, 0);

        self::assertTrue($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // toString() / __toString() / jsonSerialize()
    // -------------------------------------------------------------------------

    public function testToStringDefault(): void
    {
        $t = new PlainTime(13, 45, 30);

        self::assertSame('13:45:30', $t->toString());
    }

    public function testToStringWithSubSeconds(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);

        self::assertSame('13:45:30.123456789', $t->toString());
    }

    public function testToStringAutoStripsTrailingZeros(): void
    {
        $t = new PlainTime(13, 45, 30, 100);

        self::assertSame('13:45:30.1', $t->toString());
    }

    public function testToStringMidnight(): void
    {
        $t = new PlainTime();

        self::assertSame('00:00:00', $t->toString());
    }

    public function testToStringWithFractionalSecondDigits(): void
    {
        $t = new PlainTime(13, 45, 30, 100);

        self::assertSame('13:45:30.100', $t->toString(fractionalSecondDigits: 3));
    }

    public function testToStringWithFractionalSecondDigitsZero(): void
    {
        $t = new PlainTime(13, 45, 30, 500);

        self::assertSame('13:45:30', $t->toString(fractionalSecondDigits: 0));
    }

    public function testToStringWithSmallestUnit(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);

        self::assertSame('13:45', $t->toString(smallestUnit: Unit::Minute));
    }

    public function testToStringWithRoundingMode(): void
    {
        $t = new PlainTime(13, 45, 30, 600);

        self::assertSame('13:45:31', $t->toString(fractionalSecondDigits: 0, roundingMode: RoundingMode::Ceil));
    }

    public function testMagicToString(): void
    {
        $t = new PlainTime(13, 45, 30);

        self::assertSame('13:45:30', (string) $t);
    }

    public function testJsonSerialize(): void
    {
        $t = new PlainTime(13, 45, 30);

        self::assertSame('"13:45:30"', json_encode($t));
    }

    public function testJsonSerializeWithSubSeconds(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);

        self::assertSame('"13:45:30.123456789"', json_encode($t));
    }

    // -------------------------------------------------------------------------
    // toSpec() / fromSpec()
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecPlainTime(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);
        $spec = $t->toSpec();

        self::assertSame(13, $spec->hour);
        self::assertSame(45, $spec->minute);
        self::assertSame(30, $spec->second);
        self::assertSame(123, $spec->millisecond);
        self::assertSame(456, $spec->microsecond);
        self::assertSame(789, $spec->nanosecond);
    }

    public function testFromSpecRoundTrip(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);
        $spec = $t->toSpec();
        $restored = PlainTime::fromSpec($spec);

        $this->assertPlainTimeIs(13, 45, 30, 123, 456, 789, $restored);
    }

    public function testFromSpecMidnight(): void
    {
        $spec = new \Temporal\Spec\PlainTime();
        $t = PlainTime::fromSpec($spec);

        $this->assertPlainTimeIs(0, 0, 0, 0, 0, 0, $t);
    }

    // -------------------------------------------------------------------------
    // __debugInfo()
    // -------------------------------------------------------------------------

    public function testDebugInfoContainsAllFields(): void
    {
        $t = new PlainTime(13, 45, 30, 123, 456, 789);
        $info = $t->__debugInfo();

        self::assertSame(13, $info['hour']);
        self::assertSame(45, $info['minute']);
        self::assertSame(30, $info['second']);
        self::assertSame(123, $info['millisecond']);
        self::assertSame(456, $info['microsecond']);
        self::assertSame(789, $info['nanosecond']);
    }

    public function testDebugInfoMidnight(): void
    {
        $t = new PlainTime();
        $info = $t->__debugInfo();

        self::assertSame(0, $info['hour']);
        self::assertSame(0, $info['minute']);
        self::assertSame(0, $info['second']);
        self::assertSame(0, $info['millisecond']);
        self::assertSame(0, $info['microsecond']);
        self::assertSame(0, $info['nanosecond']);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: since() forwards all options
    // -------------------------------------------------------------------------

    public function testSinceForwardsSmallestUnit(): void
    {
        $a = new PlainTime(10, 0, 0);
        $b = new PlainTime(10, 30, 45);
        $d = $b->since($a, smallestUnit: Unit::Minute);

        self::assertSame(0, $d->hours);
        self::assertSame(30, $d->minutes);
        self::assertSame(0, $d->seconds);
    }

    public function testSinceForwardsRoundingMode(): void
    {
        $a = new PlainTime(10, 0, 0);
        $b = new PlainTime(10, 0, 29);

        $trunc = $b->since($a, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc);
        $ceil = $b->since($a, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Ceil);

        self::assertSame(0, $trunc->minutes);
        self::assertSame(1, $ceil->minutes);
    }

    public function testSinceForwardsRoundingIncrement(): void
    {
        $a = new PlainTime(10, 0);
        $b = new PlainTime(10, 7);
        $d = $b->since($a, smallestUnit: Unit::Minute, roundingIncrement: 5);

        // 7 minutes truncated to nearest 5 = 5
        self::assertSame(5, $d->minutes);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: until() forwards all options
    // -------------------------------------------------------------------------

    public function testUntilForwardsLargestUnit(): void
    {
        $a = new PlainTime(10, 0, 0);
        $b = new PlainTime(13, 30, 45);
        $d = $a->until($b, largestUnit: Unit::Minute);

        self::assertSame(0, $d->hours);
        self::assertSame(210, $d->minutes);
        self::assertSame(45, $d->seconds);
    }

    public function testUntilForwardsSmallestUnit(): void
    {
        $a = new PlainTime(10, 0, 0);
        $b = new PlainTime(10, 30, 45);
        $d = $a->until($b, smallestUnit: Unit::Minute);

        self::assertSame(30, $d->minutes);
        self::assertSame(0, $d->seconds);
    }

    public function testUntilForwardsRoundingMode(): void
    {
        $a = new PlainTime(10, 0, 0);
        $b = new PlainTime(10, 0, 29);

        $trunc = $a->until($b, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc);
        $ceil = $a->until($b, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Ceil);

        self::assertSame(0, $trunc->minutes);
        self::assertSame(1, $ceil->minutes);
    }

    public function testUntilForwardsRoundingIncrement(): void
    {
        $a = new PlainTime(10, 0);
        $b = new PlainTime(10, 7);
        $d = $a->until($b, smallestUnit: Unit::Minute, roundingIncrement: 5);

        // 7 minutes truncated to nearest 5 = 5
        self::assertSame(5, $d->minutes);
    }
}
