<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\CalendarDisplay;
use Temporal\Duration;
use Temporal\Overflow;
use Temporal\PlainDate;
use Temporal\RoundingMode;
use Temporal\Unit;

final class PlainDateTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor & readonly properties
    // -------------------------------------------------------------------------

    public function testConstructorSetsReadonlyProperties(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame(2020, $pd->year);
        self::assertSame(6, $pd->month);
        self::assertSame(15, $pd->day);
    }

    public function testConstructorRejectsInvalidDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainDate(2020, 2, 30);
    }

    // -------------------------------------------------------------------------
    // Virtual properties
    // -------------------------------------------------------------------------

    public function testCalendarIdIsIso8601(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame('iso8601', $pd->calendarId);
    }

    public function testDayOfWeek(): void
    {
        // 2020-06-15 is a Monday (1)
        self::assertSame(1, new PlainDate(2020, 6, 15)->dayOfWeek);
        // 2020-06-21 is a Sunday (7)
        self::assertSame(7, new PlainDate(2020, 6, 21)->dayOfWeek);
    }

    public function testDayOfYear(): void
    {
        // Jan 1 is day 1
        self::assertSame(1, new PlainDate(2020, 1, 1)->dayOfYear);
        // Dec 31 of a leap year is day 366
        self::assertSame(366, new PlainDate(2020, 12, 31)->dayOfYear);
    }

    public function testWeekOfYear(): void
    {
        // 2020-01-01 is in week 1
        self::assertSame(1, new PlainDate(2020, 1, 1)->weekOfYear);
    }

    public function testYearOfWeek(): void
    {
        // 2020-01-01 is in week-year 2020
        self::assertSame(2020, new PlainDate(2020, 1, 1)->yearOfWeek);
        // 2024-12-30 (Monday) is in ISO week 1 of 2025
        self::assertSame(2025, new PlainDate(2024, 12, 30)->yearOfWeek);
    }

    public function testDaysInMonth(): void
    {
        self::assertSame(31, new PlainDate(2020, 1, 1)->daysInMonth);
        self::assertSame(29, new PlainDate(2020, 2, 1)->daysInMonth);
        self::assertSame(28, new PlainDate(2019, 2, 1)->daysInMonth);
    }

    public function testDaysInYear(): void
    {
        self::assertSame(366, new PlainDate(2020, 1, 1)->daysInYear);
        self::assertSame(365, new PlainDate(2019, 1, 1)->daysInYear);
    }

    public function testDaysInWeek(): void
    {
        self::assertSame(7, new PlainDate(2020, 1, 1)->daysInWeek);
    }

    public function testMonthsInYear(): void
    {
        self::assertSame(12, new PlainDate(2020, 1, 1)->monthsInYear);
    }

    public function testMonthCode(): void
    {
        self::assertSame('M01', new PlainDate(2020, 1, 1)->monthCode);
        self::assertSame('M06', new PlainDate(2020, 6, 15)->monthCode);
        self::assertSame('M12', new PlainDate(2020, 12, 31)->monthCode);
    }

    public function testInLeapYear(): void
    {
        self::assertTrue(new PlainDate(2020, 1, 1)->inLeapYear);
        self::assertFalse(new PlainDate(2019, 1, 1)->inLeapYear);
    }

    // -------------------------------------------------------------------------
    // parse
    // -------------------------------------------------------------------------

    public function testParseBasicDate(): void
    {
        $pd = PlainDate::parse('2020-06-15');

        self::assertSame(2020, $pd->year);
        self::assertSame(6, $pd->month);
        self::assertSame(15, $pd->day);
    }

    public function testParseWithTime(): void
    {
        $pd = PlainDate::parse('2020-06-15T12:30:00');

        self::assertSame(2020, $pd->year);
        self::assertSame(6, $pd->month);
        self::assertSame(15, $pd->day);
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlainDate::parse('not-a-date');
    }

    public function testParseNegativeYear(): void
    {
        $pd = PlainDate::parse('-001000-01-01');

        self::assertSame(-1000, $pd->year);
        self::assertSame(1, $pd->month);
        self::assertSame(1, $pd->day);
    }

    public function testParseExtendedYear(): void
    {
        $pd = PlainDate::parse('+010000-01-01');

        self::assertSame(10000, $pd->year);
        self::assertSame(1, $pd->month);
        self::assertSame(1, $pd->day);
    }

    // -------------------------------------------------------------------------
    // compare
    // -------------------------------------------------------------------------

    public function testCompareSameDate(): void
    {
        $a = new PlainDate(2020, 6, 15);
        $b = new PlainDate(2020, 6, 15);

        self::assertSame(0, PlainDate::compare($a, $b));
    }

    public function testCompareEarlierVsLater(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 12, 31);

        self::assertLessThan(0, PlainDate::compare($a, $b));
        self::assertGreaterThan(0, PlainDate::compare($b, $a));
    }

    public function testCompareByYear(): void
    {
        $a = new PlainDate(2019, 12, 31);
        $b = new PlainDate(2020, 1, 1);

        self::assertLessThan(0, PlainDate::compare($a, $b));
    }

    // -------------------------------------------------------------------------
    // with
    // -------------------------------------------------------------------------

    public function testWithYear(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $result = $pd->with(year: 2021);

        self::assertSame(2021, $result->year);
        self::assertSame(6, $result->month);
        self::assertSame(15, $result->day);
    }

    public function testWithMonth(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $result = $pd->with(month: 1);

        self::assertSame(2020, $result->year);
        self::assertSame(1, $result->month);
        self::assertSame(15, $result->day);
    }

    public function testWithDay(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $result = $pd->with(day: 1);

        self::assertSame(2020, $result->year);
        self::assertSame(6, $result->month);
        self::assertSame(1, $result->day);
    }

    public function testWithConstrainsDay(): void
    {
        // Feb has 29 days in 2020; day 31 should constrain to 29.
        $pd = new PlainDate(2020, 1, 31);
        $result = $pd->with(month: 2);

        self::assertSame(29, $result->day);
    }

    public function testWithRejectOverflow(): void
    {
        $pd = new PlainDate(2020, 1, 31);

        $this->expectException(InvalidArgumentException::class);
        $pd->with(month: 2, overflow: Overflow::Reject);
    }

    public function testWithReturnsNewInstance(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $result = $pd->with(year: 2021);

        self::assertNotSame($pd, $result);
    }

    // -------------------------------------------------------------------------
    // add / subtract
    // -------------------------------------------------------------------------

    public function testAddDays(): void
    {
        $pd = new PlainDate(2020, 1, 1);
        $result = $pd->add(new Duration(days: 10));

        self::assertSame(2020, $result->year);
        self::assertSame(1, $result->month);
        self::assertSame(11, $result->day);
    }

    public function testAddMonths(): void
    {
        $pd = new PlainDate(2020, 1, 31);
        $result = $pd->add(new Duration(months: 1));

        self::assertSame(2020, $result->year);
        self::assertSame(2, $result->month);
        // Day constrained from 31 to 29 (Feb 2020 is leap)
        self::assertSame(29, $result->day);
    }

    public function testAddYears(): void
    {
        $pd = new PlainDate(2020, 2, 29);
        $result = $pd->add(new Duration(years: 1));

        // 2021 is not a leap year, so Feb 29 constrains to Feb 28
        self::assertSame(2021, $result->year);
        self::assertSame(2, $result->month);
        self::assertSame(28, $result->day);
    }

    public function testSubtractDays(): void
    {
        $pd = new PlainDate(2020, 1, 11);
        $result = $pd->subtract(new Duration(days: 10));

        self::assertSame(2020, $result->year);
        self::assertSame(1, $result->month);
        self::assertSame(1, $result->day);
    }

    public function testSubtractAcrossMonthBoundary(): void
    {
        $pd = new PlainDate(2020, 3, 1);
        $result = $pd->subtract(new Duration(days: 1));

        self::assertSame(2020, $result->year);
        self::assertSame(2, $result->month);
        self::assertSame(29, $result->day);
    }

    // -------------------------------------------------------------------------
    // since / until
    // -------------------------------------------------------------------------

    public function testSinceDays(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 1, 11);

        $dur = $b->since($a);

        self::assertSame(10, $dur->days);
    }

    public function testUntilDays(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 1, 11);

        $dur = $a->until($b);

        self::assertSame(10, $dur->days);
    }

    public function testSinceNegative(): void
    {
        $a = new PlainDate(2020, 1, 11);
        $b = new PlainDate(2020, 1, 1);

        $dur = $b->since($a);

        self::assertSame(-10, $dur->days);
    }

    public function testUntilNegative(): void
    {
        $a = new PlainDate(2020, 1, 11);
        $b = new PlainDate(2020, 1, 1);

        $dur = $a->until($b);

        self::assertSame(-10, $dur->days);
    }

    public function testSinceWithLargestUnitYear(): void
    {
        $a = new PlainDate(2018, 6, 15);
        $b = new PlainDate(2020, 9, 20);

        $dur = $b->since($a, largestUnit: Unit::Year);

        self::assertSame(2, $dur->years);
        self::assertSame(3, $dur->months);
        self::assertSame(5, $dur->days);
    }

    public function testUntilWithLargestUnitMonth(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 4, 1);

        $dur = $a->until($b, largestUnit: Unit::Month);

        self::assertSame(3, $dur->months);
        self::assertSame(0, $dur->days);
    }

    public function testSinceWithRoundingIncrement(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 1, 8);

        $dur = $b->since($a, roundingIncrement: 5);

        // 7 days rounded to nearest 5 with trunc = 5
        self::assertSame(5, $dur->days);
    }

    // -------------------------------------------------------------------------
    // equals
    // -------------------------------------------------------------------------

    public function testEqualsSameDate(): void
    {
        $a = new PlainDate(2020, 6, 15);
        $b = new PlainDate(2020, 6, 15);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsDifferentDate(): void
    {
        $a = new PlainDate(2020, 6, 15);
        $b = new PlainDate(2020, 6, 16);

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // toString
    // -------------------------------------------------------------------------

    public function testToStringDefault(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame('2020-06-15', $pd->toString());
    }

    public function testToStringCalendarAlways(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame('2020-06-15[u-ca=iso8601]', $pd->toString(CalendarDisplay::Always));
    }

    public function testToStringCalendarNever(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame('2020-06-15', $pd->toString(CalendarDisplay::Never));
    }

    public function testToStringCalendarCritical(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame('2020-06-15[!u-ca=iso8601]', $pd->toString(CalendarDisplay::Critical));
    }

    public function testToStringNegativeYear(): void
    {
        $pd = new PlainDate(-1000, 1, 1);

        self::assertSame('-001000-01-01', $pd->toString());
    }

    public function testToStringExtendedYear(): void
    {
        $pd = new PlainDate(10000, 1, 1);

        self::assertSame('+010000-01-01', $pd->toString());
    }

    // -------------------------------------------------------------------------
    // __toString / jsonSerialize
    // -------------------------------------------------------------------------

    public function testMagicToString(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame('2020-06-15', (string) $pd);
    }

    public function testJsonSerialize(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        self::assertSame('"2020-06-15"', json_encode($pd));
    }

    // -------------------------------------------------------------------------
    // toSpec / fromSpec
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecInstance(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $spec = $pd->toSpec();

        self::assertSame(2020, $spec->year);
        self::assertSame(6, $spec->month);
        self::assertSame(15, $spec->day);
    }

    public function testFromSpecCreatesInstance(): void
    {
        $spec = new \Temporal\Spec\PlainDate(2020, 6, 15);
        $pd = PlainDate::fromSpec($spec);

        self::assertSame(2020, $pd->year);
        self::assertSame(6, $pd->month);
        self::assertSame(15, $pd->day);
    }

    public function testToSpecRoundTrip(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $restored = PlainDate::fromSpec($pd->toSpec());

        self::assertTrue($pd->equals($restored));
    }

    // -------------------------------------------------------------------------
    // __debugInfo
    // -------------------------------------------------------------------------

    public function testDebugInfo(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $info = $pd->__debugInfo();

        self::assertSame(2020, $info['year']);
        self::assertSame(6, $info['month']);
        self::assertSame(15, $info['day']);
        self::assertSame('iso8601', $info['calendarId']);
        self::assertSame('2020-06-15', $info['iso']);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: add/subtract forward overflow option
    // -------------------------------------------------------------------------

    public function testAddForwardsOverflowReject(): void
    {
        $pd = new PlainDate(2020, 1, 31);
        $this->expectException(InvalidArgumentException::class);
        $pd->add(new Duration(months: 1), Overflow::Reject);
    }

    public function testSubtractForwardsOverflowReject(): void
    {
        $pd = new PlainDate(2020, 3, 31);
        $this->expectException(InvalidArgumentException::class);
        $pd->subtract(new Duration(months: 1), Overflow::Reject);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: since/until forward smallestUnit, roundingMode, roundingIncrement
    // -------------------------------------------------------------------------

    public function testSinceForwardsSmallestUnit(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 4, 15);
        $dur = $b->since($a, largestUnit: Unit::Month, smallestUnit: Unit::Month);

        self::assertSame(3, $dur->months);
        self::assertSame(0, $dur->days);
    }

    public function testSinceForwardsRoundingMode(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 1, 8);

        $trunc = $b->since($a, roundingIncrement: 5, roundingMode: RoundingMode::Trunc);
        $ceil = $b->since($a, roundingIncrement: 5, roundingMode: RoundingMode::Ceil);

        self::assertSame(5, $trunc->days);
        self::assertSame(10, $ceil->days);
    }

    public function testUntilForwardsSmallestUnit(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 4, 15);
        $dur = $a->until($b, largestUnit: Unit::Month, smallestUnit: Unit::Month);

        self::assertSame(3, $dur->months);
        self::assertSame(0, $dur->days);
    }

    public function testUntilForwardsRoundingMode(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 1, 8);

        $trunc = $a->until($b, roundingIncrement: 5, roundingMode: RoundingMode::Trunc);
        $ceil = $a->until($b, roundingIncrement: 5, roundingMode: RoundingMode::Ceil);

        self::assertSame(5, $trunc->days);
        self::assertSame(10, $ceil->days);
    }

    public function testUntilForwardsRoundingIncrement(): void
    {
        $a = new PlainDate(2020, 1, 1);
        $b = new PlainDate(2020, 1, 8);

        $dur = $a->until($b, roundingIncrement: 5);

        // 7 days truncated to nearest 5 = 5
        self::assertSame(5, $dur->days);
    }
}
