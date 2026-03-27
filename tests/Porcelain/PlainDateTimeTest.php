<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use Temporal\CalendarDisplay;
use Temporal\Duration;
use Temporal\Overflow;
use Temporal\PlainDate;
use Temporal\PlainDateTime;
use Temporal\PlainTime;
use Temporal\RoundingMode;

use Temporal\Unit;

final class PlainDateTimeTest extends TemporalTestCase
{
    // -------------------------------------------------------------------------
    // Constructor & readonly properties
    // -------------------------------------------------------------------------

    public function testConstructorSetsAllFields(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);

        self::assertSame(2020, $dt->year);
        self::assertSame(6, $dt->month);
        self::assertSame(15, $dt->day);
        self::assertSame(13, $dt->hour);
        self::assertSame(45, $dt->minute);
        self::assertSame(30, $dt->second);
        self::assertSame(123, $dt->millisecond);
        self::assertSame(456, $dt->microsecond);
        self::assertSame(789, $dt->nanosecond);
    }

    public function testConstructorDefaultsTimeToMidnight(): void
    {
        $dt = new PlainDateTime(2020, 6, 15);

        self::assertSame(0, $dt->hour);
        self::assertSame(0, $dt->minute);
        self::assertSame(0, $dt->second);
        self::assertSame(0, $dt->millisecond);
        self::assertSame(0, $dt->microsecond);
        self::assertSame(0, $dt->nanosecond);
    }

    public function testConstructorPartialTimeFields(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 10, 30);

        self::assertSame(10, $dt->hour);
        self::assertSame(30, $dt->minute);
        self::assertSame(0, $dt->second);
    }

    public function testConstructorRejectsInvalidDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainDateTime(2020, 2, 30);
    }

    // -------------------------------------------------------------------------
    // Virtual properties
    // -------------------------------------------------------------------------

    public function testCalendarIdIsIso8601(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);

        self::assertSame('iso8601', $dt->calendarId);
    }

    public function testMonthCode(): void
    {
        self::assertSame('M01', (new PlainDateTime(2020, 1, 1))->monthCode);
        self::assertSame('M06', (new PlainDateTime(2020, 6, 15))->monthCode);
        self::assertSame('M12', (new PlainDateTime(2020, 12, 31))->monthCode);
    }

    public function testDayOfWeek(): void
    {
        // 2020-06-15 is a Monday (1)
        self::assertSame(1, (new PlainDateTime(2020, 6, 15))->dayOfWeek);
        // 2020-06-21 is a Sunday (7)
        self::assertSame(7, (new PlainDateTime(2020, 6, 21))->dayOfWeek);
    }

    public function testDayOfYear(): void
    {
        self::assertSame(1, (new PlainDateTime(2020, 1, 1))->dayOfYear);
        // Dec 31 of a leap year is day 366
        self::assertSame(366, (new PlainDateTime(2020, 12, 31))->dayOfYear);
    }

    public function testWeekOfYear(): void
    {
        self::assertSame(1, (new PlainDateTime(2020, 1, 1))->weekOfYear);
    }

    public function testYearOfWeek(): void
    {
        self::assertSame(2020, (new PlainDateTime(2020, 1, 1))->yearOfWeek);
        // 2024-12-30 (Monday) is in ISO week 1 of 2025
        self::assertSame(2025, (new PlainDateTime(2024, 12, 30))->yearOfWeek);
    }

    public function testDaysInMonth(): void
    {
        self::assertSame(31, (new PlainDateTime(2020, 1, 1))->daysInMonth);
        self::assertSame(29, (new PlainDateTime(2020, 2, 1))->daysInMonth);
        self::assertSame(28, (new PlainDateTime(2019, 2, 1))->daysInMonth);
    }

    public function testDaysInYear(): void
    {
        self::assertSame(366, (new PlainDateTime(2020, 1, 1))->daysInYear);
        self::assertSame(365, (new PlainDateTime(2019, 1, 1))->daysInYear);
    }

    public function testInLeapYear(): void
    {
        self::assertTrue((new PlainDateTime(2020, 1, 1))->inLeapYear);
        self::assertFalse((new PlainDateTime(2019, 1, 1))->inLeapYear);
    }

    // -------------------------------------------------------------------------
    // parse
    // -------------------------------------------------------------------------

    public function testParseBasicDateTime(): void
    {
        $dt = PlainDateTime::parse('2020-06-15T13:45:30');

        self::assertSame(2020, $dt->year);
        self::assertSame(6, $dt->month);
        self::assertSame(15, $dt->day);
        self::assertSame(13, $dt->hour);
        self::assertSame(45, $dt->minute);
        self::assertSame(30, $dt->second);
    }

    public function testParseWithFractionalSeconds(): void
    {
        $dt = PlainDateTime::parse('2020-06-15T13:45:30.123456789');

        self::assertSame(123, $dt->millisecond);
        self::assertSame(456, $dt->microsecond);
        self::assertSame(789, $dt->nanosecond);
    }

    public function testParseDateOnly(): void
    {
        $dt = PlainDateTime::parse('2020-06-15');

        self::assertSame(2020, $dt->year);
        self::assertSame(6, $dt->month);
        self::assertSame(15, $dt->day);
        self::assertSame(0, $dt->hour);
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlainDateTime::parse('not-a-datetime');
    }

    public function testParseNegativeYear(): void
    {
        $dt = PlainDateTime::parse('-001000-01-01T00:00');

        self::assertSame(-1000, $dt->year);
    }

    public function testParseExtendedYear(): void
    {
        $dt = PlainDateTime::parse('+010000-01-01T00:00');

        self::assertSame(10000, $dt->year);
    }

    // -------------------------------------------------------------------------
    // compare
    // -------------------------------------------------------------------------

    public function testCompareSame(): void
    {
        $a = new PlainDateTime(2020, 6, 15, 12, 0);
        $b = new PlainDateTime(2020, 6, 15, 12, 0);

        self::assertSame(0, PlainDateTime::compare($a, $b));
    }

    public function testCompareEarlierVsLater(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 0, 0);
        $b = new PlainDateTime(2020, 12, 31, 23, 59);

        self::assertLessThan(0, PlainDateTime::compare($a, $b));
        self::assertGreaterThan(0, PlainDateTime::compare($b, $a));
    }

    public function testCompareByTime(): void
    {
        $a = new PlainDateTime(2020, 6, 15, 10, 0);
        $b = new PlainDateTime(2020, 6, 15, 14, 0);

        self::assertLessThan(0, PlainDateTime::compare($a, $b));
    }

    public function testCompareBySubSeconds(): void
    {
        $a = new PlainDateTime(2020, 6, 15, 12, 0, 0, 0, 0, 0);
        $b = new PlainDateTime(2020, 6, 15, 12, 0, 0, 0, 0, 1);

        self::assertLessThan(0, PlainDateTime::compare($a, $b));
    }

    // -------------------------------------------------------------------------
    // with
    // -------------------------------------------------------------------------

    public function testWithYear(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 30);
        $result = $dt->with(year: 2021);

        self::assertSame(2021, $result->year);
        self::assertSame(6, $result->month);
        self::assertSame(15, $result->day);
        self::assertSame(12, $result->hour);
        self::assertSame(30, $result->minute);
    }

    public function testWithMonth(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);
        $result = $dt->with(month: 1);

        self::assertSame(1, $result->month);
        self::assertSame(15, $result->day);
    }

    public function testWithDay(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);
        $result = $dt->with(day: 1);

        self::assertSame(1, $result->day);
    }

    public function testWithHour(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);
        $result = $dt->with(hour: 23);

        self::assertSame(23, $result->hour);
    }

    public function testWithMinute(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);
        $result = $dt->with(minute: 59);

        self::assertSame(59, $result->minute);
    }

    public function testWithConstrainsDay(): void
    {
        // Jan 31 -> Feb: day constrained to 29 in 2020 (leap year)
        $dt = new PlainDateTime(2020, 1, 31, 12, 0);
        $result = $dt->with(month: 2);

        self::assertSame(29, $result->day);
    }

    public function testWithRejectOverflow(): void
    {
        $dt = new PlainDateTime(2020, 1, 31, 12, 0);

        $this->expectException(InvalidArgumentException::class);
        $dt->with(month: 2, overflow: Overflow::Reject);
    }

    public function testWithReturnsNewInstance(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);
        $result = $dt->with(year: 2021);

        self::assertNotSame($dt, $result);
        self::assertSame(2020, $dt->year);
    }

    public function testWithMultipleFields(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 30, 45);
        $result = $dt->with(year: 2021, month: 3, hour: 8, second: 0);

        self::assertSame(2021, $result->year);
        self::assertSame(3, $result->month);
        self::assertSame(15, $result->day);
        self::assertSame(8, $result->hour);
        self::assertSame(30, $result->minute);
        self::assertSame(0, $result->second);
    }

    // -------------------------------------------------------------------------
    // add / subtract
    // -------------------------------------------------------------------------

    public function testAddDays(): void
    {
        $dt = new PlainDateTime(2020, 1, 1, 12, 0);
        $result = $dt->add(new Duration(days: 10));

        self::assertSame(2020, $result->year);
        self::assertSame(1, $result->month);
        self::assertSame(11, $result->day);
        self::assertSame(12, $result->hour);
    }

    public function testAddMonths(): void
    {
        $dt = new PlainDateTime(2020, 1, 31, 12, 0);
        $result = $dt->add(new Duration(months: 1));

        self::assertSame(2, $result->month);
        // Day constrained from 31 to 29 (Feb 2020 is leap)
        self::assertSame(29, $result->day);
    }

    public function testAddHours(): void
    {
        $dt = new PlainDateTime(2020, 1, 1, 23, 0);
        $result = $dt->add(new Duration(hours: 2));

        self::assertSame(2, $result->day);
        self::assertSame(1, $result->hour);
    }

    public function testAddMixed(): void
    {
        $dt = new PlainDateTime(2020, 1, 1, 10, 30);
        $result = $dt->add(new Duration(years: 1, months: 2, days: 3, hours: 4, minutes: 5));

        self::assertSame(2021, $result->year);
        self::assertSame(3, $result->month);
        self::assertSame(4, $result->day);
        self::assertSame(14, $result->hour);
        self::assertSame(35, $result->minute);
    }

    public function testSubtractDays(): void
    {
        $dt = new PlainDateTime(2020, 1, 11, 12, 0);
        $result = $dt->subtract(new Duration(days: 10));

        self::assertSame(1, $result->day);
        self::assertSame(12, $result->hour);
    }

    public function testSubtractAcrossDayBoundary(): void
    {
        $dt = new PlainDateTime(2020, 3, 1, 2, 0);
        $result = $dt->subtract(new Duration(hours: 3));

        self::assertSame(2, $result->month);
        self::assertSame(29, $result->day);
        self::assertSame(23, $result->hour);
    }

    public function testAddDoesNotMutateOriginal(): void
    {
        $dt = new PlainDateTime(2020, 1, 1, 12, 0);
        $dt->add(new Duration(days: 10));

        self::assertSame(1, $dt->day);
    }

    // -------------------------------------------------------------------------
    // since / until
    // -------------------------------------------------------------------------

    public function testSinceDays(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 0, 0);
        $b = new PlainDateTime(2020, 1, 11, 0, 0);

        $dur = $b->since($a);

        self::assertSame(10, $dur->days);
    }

    public function testUntilDays(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 0, 0);
        $b = new PlainDateTime(2020, 1, 11, 0, 0);

        $dur = $a->until($b);

        self::assertSame(10, $dur->days);
    }

    public function testSinceWithTimeComponent(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 10, 0);
        $b = new PlainDateTime(2020, 1, 1, 14, 30);

        $dur = $b->since($a);

        self::assertSame(0, $dur->days);
        self::assertSame(4, $dur->hours);
        self::assertSame(30, $dur->minutes);
    }

    public function testUntilNegative(): void
    {
        $a = new PlainDateTime(2020, 1, 11, 0, 0);
        $b = new PlainDateTime(2020, 1, 1, 0, 0);

        $dur = $a->until($b);

        self::assertSame(-10, $dur->days);
    }

    public function testSinceWithLargestUnitYear(): void
    {
        $a = new PlainDateTime(2018, 6, 15, 10, 0);
        $b = new PlainDateTime(2020, 9, 20, 14, 30);

        $dur = $b->since($a, largestUnit: Unit::Year);

        self::assertSame(2, $dur->years);
        self::assertSame(3, $dur->months);
        self::assertSame(5, $dur->days);
        self::assertSame(4, $dur->hours);
        self::assertSame(30, $dur->minutes);
    }

    public function testUntilWithSmallestUnit(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 10, 0, 0);
        $b = new PlainDateTime(2020, 1, 1, 10, 30, 45);

        $dur = $a->until($b, smallestUnit: Unit::Minute);

        self::assertSame(30, $dur->minutes);
        self::assertSame(0, $dur->seconds);
    }

    public function testUntilWithRoundingIncrement(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 0, 0);
        $b = new PlainDateTime(2020, 1, 8, 0, 0);

        $dur = $a->until($b, smallestUnit: Unit::Day, roundingIncrement: 5);

        // 7 days rounded to nearest 5 with trunc = 5
        self::assertSame(5, $dur->days);
    }

    // -------------------------------------------------------------------------
    // round
    // -------------------------------------------------------------------------

    public function testRoundToHour(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);
        $rounded = $dt->round(Unit::Hour);

        self::assertSame(14, $rounded->hour);
        self::assertSame(0, $rounded->minute);
        self::assertSame(0, $rounded->second);
    }

    public function testRoundToMinute(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);
        $rounded = $dt->round(Unit::Minute);

        self::assertSame(13, $rounded->hour);
        self::assertSame(46, $rounded->minute);
        self::assertSame(0, $rounded->second);
    }

    public function testRoundToSecond(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 600);
        $rounded = $dt->round(Unit::Second);

        self::assertSame(31, $rounded->second);
        self::assertSame(0, $rounded->millisecond);
    }

    public function testRoundWithTruncMode(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 59, 999);
        $rounded = $dt->round(Unit::Second, RoundingMode::Trunc);

        self::assertSame(59, $rounded->second);
        self::assertSame(0, $rounded->millisecond);
    }

    public function testRoundWithIncrement(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 47);
        $rounded = $dt->round(Unit::Minute, RoundingMode::HalfExpand, 15);

        self::assertSame(13, $rounded->hour);
        self::assertSame(45, $rounded->minute);
    }

    public function testRoundToDay(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 0);
        $rounded = $dt->round(Unit::Day);

        self::assertSame(16, $rounded->day);
        self::assertSame(0, $rounded->hour);
    }

    // -------------------------------------------------------------------------
    // equals
    // -------------------------------------------------------------------------

    public function testEqualsTrue(): void
    {
        $a = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);
        $b = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalse(): void
    {
        $a = new PlainDateTime(2020, 6, 15, 13, 45, 30);
        $b = new PlainDateTime(2020, 6, 15, 13, 45, 31);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsDifferentDate(): void
    {
        $a = new PlainDateTime(2020, 6, 15, 12, 0);
        $b = new PlainDateTime(2020, 6, 16, 12, 0);

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // toString
    // -------------------------------------------------------------------------

    public function testToStringDefault(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);

        self::assertSame('2020-06-15T13:45:30', $dt->toString());
    }

    public function testToStringWithSubSeconds(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);

        self::assertSame('2020-06-15T13:45:30.123456789', $dt->toString());
    }

    public function testToStringCalendarAlways(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);

        self::assertSame(
            '2020-06-15T12:00:00[u-ca=iso8601]',
            $dt->toString(CalendarDisplay::Always),
        );
    }

    public function testToStringCalendarNever(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);

        self::assertSame('2020-06-15T12:00:00', $dt->toString(CalendarDisplay::Never));
    }

    public function testToStringCalendarCritical(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0);

        self::assertSame(
            '2020-06-15T12:00:00[!u-ca=iso8601]',
            $dt->toString(CalendarDisplay::Critical),
        );
    }

    public function testToStringWithFractionalSecondDigits(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 100);

        self::assertSame('2020-06-15T13:45:30.100', $dt->toString(fractionalSecondDigits: 3));
    }

    public function testToStringWithFractionalSecondDigitsZero(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 500);

        self::assertSame('2020-06-15T13:45:30', $dt->toString(fractionalSecondDigits: 0));
    }

    public function testToStringWithSmallestUnit(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123);

        self::assertSame('2020-06-15T13:45', $dt->toString(smallestUnit: Unit::Minute));
    }

    public function testToStringWithRoundingMode(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 600);

        self::assertSame('2020-06-15T13:45:31', $dt->toString(
            fractionalSecondDigits: 0,
            roundingMode: RoundingMode::Ceil,
        ));
    }

    public function testToStringNegativeYear(): void
    {
        $dt = new PlainDateTime(-1000, 1, 1, 0, 0);

        self::assertSame('-001000-01-01T00:00:00', $dt->toString());
    }

    public function testToStringExtendedYear(): void
    {
        $dt = new PlainDateTime(10000, 1, 1, 0, 0);

        self::assertSame('+010000-01-01T00:00:00', $dt->toString());
    }

    // -------------------------------------------------------------------------
    // __toString / jsonSerialize
    // -------------------------------------------------------------------------

    public function testMagicToString(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);

        self::assertSame('2020-06-15T13:45:30', (string) $dt);
    }

    public function testJsonSerialize(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);

        self::assertSame('"2020-06-15T13:45:30"', json_encode($dt));
    }

    public function testJsonSerializeWithSubSeconds(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);

        self::assertSame('"2020-06-15T13:45:30.123456789"', json_encode($dt));
    }

    // -------------------------------------------------------------------------
    // Conversion methods
    // -------------------------------------------------------------------------

    public function testToPlainDate(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);
        $date = $dt->toPlainDate();

        self::assertSame(2020, $date->year);
        self::assertSame(6, $date->month);
        self::assertSame(15, $date->day);
    }

    public function testToPlainTime(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);
        $time = $dt->toPlainTime();

        self::assertSame(13, $time->hour);
        self::assertSame(45, $time->minute);
        self::assertSame(30, $time->second);
        self::assertSame(123, $time->millisecond);
        self::assertSame(456, $time->microsecond);
        self::assertSame(789, $time->nanosecond);
    }

    public function testWithPlainTimeReplacesTime(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);
        $result = $dt->withPlainTime(new PlainTime(8, 15));

        self::assertSame(2020, $result->year);
        self::assertSame(6, $result->month);
        self::assertSame(15, $result->day);
        self::assertSame(8, $result->hour);
        self::assertSame(15, $result->minute);
        self::assertSame(0, $result->second);
    }

    public function testWithPlainTimeNullResetsMidnight(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30);
        $result = $dt->withPlainTime();

        self::assertSame(2020, $result->year);
        self::assertSame(6, $result->month);
        self::assertSame(15, $result->day);
        self::assertSame(0, $result->hour);
        self::assertSame(0, $result->minute);
        self::assertSame(0, $result->second);
    }

    // -------------------------------------------------------------------------
    // toSpec / fromSpec
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecInstance(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);
        $spec = $dt->toSpec();

        self::assertSame(2020, $spec->year);
        self::assertSame(6, $spec->month);
        self::assertSame(15, $spec->day);
        self::assertSame(13, $spec->hour);
        self::assertSame(45, $spec->minute);
        self::assertSame(30, $spec->second);
        self::assertSame(123, $spec->millisecond);
        self::assertSame(456, $spec->microsecond);
        self::assertSame(789, $spec->nanosecond);
    }

    public function testFromSpecCreatesInstance(): void
    {
        $spec = new \Temporal\Spec\PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);
        $dt = PlainDateTime::fromSpec($spec);

        self::assertSame(2020, $dt->year);
        self::assertSame(6, $dt->month);
        self::assertSame(15, $dt->day);
        self::assertSame(13, $dt->hour);
        self::assertSame(45, $dt->minute);
        self::assertSame(30, $dt->second);
        self::assertSame(123, $dt->millisecond);
        self::assertSame(456, $dt->microsecond);
        self::assertSame(789, $dt->nanosecond);
    }

    public function testToSpecRoundTrip(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);
        $restored = PlainDateTime::fromSpec($dt->toSpec());

        self::assertTrue($dt->equals($restored));
    }

    // -------------------------------------------------------------------------
    // __debugInfo
    // -------------------------------------------------------------------------

    public function testDebugInfo(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 13, 45, 30, 123, 456, 789);
        $info = $dt->__debugInfo();

        self::assertSame(2020, $info['year']);
        self::assertSame(6, $info['month']);
        self::assertSame(15, $info['day']);
        self::assertSame(13, $info['hour']);
        self::assertSame(45, $info['minute']);
        self::assertSame(30, $info['second']);
        self::assertSame(123, $info['millisecond']);
        self::assertSame(456, $info['microsecond']);
        self::assertSame(789, $info['nanosecond']);
        self::assertSame('iso8601', $info['calendarId']);
        self::assertSame('2020-06-15T13:45:30.123456789', $info['iso']);
    }

    public function testDebugInfoMidnight(): void
    {
        $dt = new PlainDateTime(2020, 1, 1);
        $info = $dt->__debugInfo();

        self::assertSame(0, $info['hour']);
        self::assertSame(0, $info['minute']);
        self::assertSame(0, $info['second']);
        self::assertSame('2020-01-01T00:00:00', $info['iso']);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: with() forwards millisecond, microsecond, nanosecond
    // -------------------------------------------------------------------------

    public function testWithMillisecond(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0, 0, 0, 0, 0);
        $result = $dt->with(millisecond: 500);

        self::assertSame(500, $result->millisecond);
        self::assertSame(0, $result->microsecond);
        self::assertSame(0, $result->nanosecond);
    }

    public function testWithMicrosecond(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0, 0, 0, 0, 0);
        $result = $dt->with(microsecond: 300);

        self::assertSame(0, $result->millisecond);
        self::assertSame(300, $result->microsecond);
        self::assertSame(0, $result->nanosecond);
    }

    public function testWithNanosecond(): void
    {
        $dt = new PlainDateTime(2020, 6, 15, 12, 0, 0, 0, 0, 0);
        $result = $dt->with(nanosecond: 100);

        self::assertSame(0, $result->millisecond);
        self::assertSame(0, $result->microsecond);
        self::assertSame(100, $result->nanosecond);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: add/subtract forward overflow option
    // -------------------------------------------------------------------------

    public function testAddForwardsOverflowReject(): void
    {
        $dt = new PlainDateTime(2020, 1, 31, 12, 0);
        $this->expectException(InvalidArgumentException::class);
        $dt->add(new Duration(months: 1), Overflow::Reject);
    }

    public function testSubtractForwardsOverflowReject(): void
    {
        $dt = new PlainDateTime(2020, 3, 31, 12, 0);
        $this->expectException(InvalidArgumentException::class);
        $dt->subtract(new Duration(months: 1), Overflow::Reject);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: since() forwards all options
    // -------------------------------------------------------------------------

    public function testSinceForwardsSmallestUnit(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 10, 0, 0);
        $b = new PlainDateTime(2020, 1, 1, 10, 30, 45);
        $dur = $b->since($a, smallestUnit: Unit::Minute);

        self::assertSame(30, $dur->minutes);
        self::assertSame(0, $dur->seconds);
    }

    public function testSinceForwardsRoundingMode(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 10, 0, 0);
        $b = new PlainDateTime(2020, 1, 1, 10, 0, 29);

        $trunc = $b->since($a, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc);
        $ceil = $b->since($a, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Ceil);

        self::assertSame(0, $trunc->minutes);
        self::assertSame(1, $ceil->minutes);
    }

    public function testSinceForwardsRoundingIncrement(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 0, 0);
        $b = new PlainDateTime(2020, 1, 8, 0, 0);
        $dur = $b->since($a, smallestUnit: Unit::Day, roundingIncrement: 5);

        self::assertSame(5, $dur->days);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: until() forwards all options
    // -------------------------------------------------------------------------

    public function testUntilForwardsLargestUnit(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 0, 0);
        $b = new PlainDateTime(2020, 4, 1, 0, 0);
        $dur = $a->until($b, largestUnit: Unit::Month);

        self::assertSame(3, $dur->months);
        self::assertSame(0, $dur->days);
    }

    public function testUntilForwardsSmallestUnit(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 10, 0, 0);
        $b = new PlainDateTime(2020, 1, 1, 10, 30, 45);
        $dur = $a->until($b, smallestUnit: Unit::Minute);

        self::assertSame(30, $dur->minutes);
        self::assertSame(0, $dur->seconds);
    }

    public function testUntilForwardsRoundingMode(): void
    {
        $a = new PlainDateTime(2020, 1, 1, 10, 0, 0);
        $b = new PlainDateTime(2020, 1, 1, 10, 0, 29);

        $trunc = $a->until($b, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc);
        $ceil = $a->until($b, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Ceil);

        self::assertSame(0, $trunc->minutes);
        self::assertSame(1, $ceil->minutes);
    }
}
