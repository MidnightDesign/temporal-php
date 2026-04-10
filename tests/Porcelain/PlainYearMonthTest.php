<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use Temporal\CalendarDisplay;
use Temporal\Duration;
use Temporal\PlainDate;
use Temporal\PlainYearMonth;
use Temporal\RoundingMode;
use Temporal\Unit;

final class PlainYearMonthTest extends TemporalTestCase
{
    // -------------------------------------------------------------------------
    // Constructor & readonly properties
    // -------------------------------------------------------------------------

    public function testConstructorSetsFields(): void
    {
        $ym = new PlainYearMonth(2020, 6);

        self::assertSame(2020, $ym->year);
        self::assertSame(6, $ym->month);
    }

    // -------------------------------------------------------------------------
    // Virtual properties
    // -------------------------------------------------------------------------

    public function testCalendarIdIsIso8601(): void
    {
        self::assertSame('iso8601', new PlainYearMonth(2020, 6)->calendarId);
    }

    public function testMonthCode(): void
    {
        self::assertSame('M01', new PlainYearMonth(2020, 1)->monthCode);
        self::assertSame('M06', new PlainYearMonth(2020, 6)->monthCode);
        self::assertSame('M12', new PlainYearMonth(2020, 12)->monthCode);
    }

    public function testDaysInMonth(): void
    {
        self::assertSame(31, new PlainYearMonth(2020, 1)->daysInMonth);
        self::assertSame(29, new PlainYearMonth(2020, 2)->daysInMonth);
        self::assertSame(28, new PlainYearMonth(2019, 2)->daysInMonth);
        self::assertSame(30, new PlainYearMonth(2020, 4)->daysInMonth);
    }

    public function testDaysInYear(): void
    {
        self::assertSame(366, new PlainYearMonth(2020, 1)->daysInYear);
        self::assertSame(365, new PlainYearMonth(2019, 1)->daysInYear);
    }

    public function testMonthsInYear(): void
    {
        self::assertSame(12, new PlainYearMonth(2020, 1)->monthsInYear);
    }

    public function testInLeapYear(): void
    {
        self::assertTrue(new PlainYearMonth(2020, 1)->inLeapYear);
        self::assertFalse(new PlainYearMonth(2019, 1)->inLeapYear);
    }

    // -------------------------------------------------------------------------
    // parse
    // -------------------------------------------------------------------------

    public function testParseBasic(): void
    {
        $ym = PlainYearMonth::parse('2020-06');

        self::assertSame(2020, $ym->year);
        self::assertSame(6, $ym->month);
    }

    public function testParseWithDay(): void
    {
        // Parsing a full date string should still yield the year-month
        $ym = PlainYearMonth::parse('2020-06-15');

        self::assertSame(2020, $ym->year);
        self::assertSame(6, $ym->month);
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlainYearMonth::parse('not-a-date');
    }

    public function testParseNegativeYear(): void
    {
        $ym = PlainYearMonth::parse('-001000-01');

        self::assertSame(-1000, $ym->year);
        self::assertSame(1, $ym->month);
    }

    // -------------------------------------------------------------------------
    // compare
    // -------------------------------------------------------------------------

    public function testCompareSame(): void
    {
        $a = new PlainYearMonth(2020, 6);
        $b = new PlainYearMonth(2020, 6);

        self::assertSame(0, PlainYearMonth::compare($a, $b));
    }

    public function testCompareEarlierVsLater(): void
    {
        $a = new PlainYearMonth(2020, 1);
        $b = new PlainYearMonth(2020, 12);

        self::assertLessThan(0, PlainYearMonth::compare($a, $b));
        self::assertGreaterThan(0, PlainYearMonth::compare($b, $a));
    }

    public function testCompareByYear(): void
    {
        $a = new PlainYearMonth(2019, 12);
        $b = new PlainYearMonth(2020, 1);

        self::assertLessThan(0, PlainYearMonth::compare($a, $b));
    }

    // -------------------------------------------------------------------------
    // with
    // -------------------------------------------------------------------------

    public function testWithYear(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $result = $ym->with(year: 2021);

        self::assertSame(2021, $result->year);
        self::assertSame(6, $result->month);
    }

    public function testWithMonth(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $result = $ym->with(month: 1);

        self::assertSame(2020, $result->year);
        self::assertSame(1, $result->month);
    }

    public function testWithReturnsNewInstance(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $result = $ym->with(year: 2021);

        self::assertNotSame($ym, $result);
        self::assertSame(2020, $ym->year);
    }

    // -------------------------------------------------------------------------
    // add / subtract
    // -------------------------------------------------------------------------

    public function testAddMonths(): void
    {
        $ym = new PlainYearMonth(2020, 10);
        $result = $ym->add(new Duration(months: 5));

        self::assertSame(2021, $result->year);
        self::assertSame(3, $result->month);
    }

    public function testAddYears(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $result = $ym->add(new Duration(years: 2));

        self::assertSame(2022, $result->year);
        self::assertSame(6, $result->month);
    }

    public function testAddYearsAndMonths(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $result = $ym->add(new Duration(years: 1, months: 8));

        self::assertSame(2022, $result->year);
        self::assertSame(2, $result->month);
    }

    public function testSubtractMonths(): void
    {
        $ym = new PlainYearMonth(2020, 3);
        $result = $ym->subtract(new Duration(months: 5));

        self::assertSame(2019, $result->year);
        self::assertSame(10, $result->month);
    }

    public function testSubtractYears(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $result = $ym->subtract(new Duration(years: 3));

        self::assertSame(2017, $result->year);
        self::assertSame(6, $result->month);
    }

    public function testAddDoesNotMutateOriginal(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $ym->add(new Duration(months: 1));

        self::assertSame(6, $ym->month);
    }

    // -------------------------------------------------------------------------
    // since / until
    // -------------------------------------------------------------------------

    public function testSinceMonths(): void
    {
        $a = new PlainYearMonth(2020, 1);
        $b = new PlainYearMonth(2020, 4);

        $dur = $b->since($a);

        self::assertSame(0, $dur->years);
        self::assertSame(3, $dur->months);
    }

    public function testUntilMonths(): void
    {
        $a = new PlainYearMonth(2020, 1);
        $b = new PlainYearMonth(2020, 4);

        $dur = $a->until($b);

        self::assertSame(0, $dur->years);
        self::assertSame(3, $dur->months);
    }

    public function testSinceWithLargestUnitYear(): void
    {
        $a = new PlainYearMonth(2018, 6);
        $b = new PlainYearMonth(2020, 9);

        $dur = $b->since($a, largestUnit: Unit::Year);

        self::assertSame(2, $dur->years);
        self::assertSame(3, $dur->months);
    }

    public function testUntilWithLargestUnitYear(): void
    {
        $a = new PlainYearMonth(2018, 6);
        $b = new PlainYearMonth(2020, 9);

        $dur = $a->until($b, largestUnit: Unit::Year);

        self::assertSame(2, $dur->years);
        self::assertSame(3, $dur->months);
    }

    public function testSinceNegative(): void
    {
        $a = new PlainYearMonth(2020, 6);
        $b = new PlainYearMonth(2020, 1);

        $dur = $b->since($a);

        self::assertSame(-5, $dur->months);
    }

    public function testUntilNegative(): void
    {
        $a = new PlainYearMonth(2020, 6);
        $b = new PlainYearMonth(2020, 1);

        $dur = $a->until($b);

        self::assertSame(-5, $dur->months);
    }

    public function testSinceWithSmallestUnitYear(): void
    {
        $a = new PlainYearMonth(2018, 6);
        $b = new PlainYearMonth(2020, 9);

        $dur = $b->since($a, smallestUnit: Unit::Year);

        self::assertSame(2, $dur->years);
        self::assertSame(0, $dur->months);
    }

    // -------------------------------------------------------------------------
    // equals
    // -------------------------------------------------------------------------

    public function testEqualsTrue(): void
    {
        $a = new PlainYearMonth(2020, 6);
        $b = new PlainYearMonth(2020, 6);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalse(): void
    {
        $a = new PlainYearMonth(2020, 6);
        $b = new PlainYearMonth(2020, 7);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsDifferentYear(): void
    {
        $a = new PlainYearMonth(2020, 6);
        $b = new PlainYearMonth(2021, 6);

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // toString
    // -------------------------------------------------------------------------

    public function testToStringDefault(): void
    {
        $ym = new PlainYearMonth(2020, 6);

        self::assertSame('2020-06', $ym->toString());
    }

    public function testToStringCalendarAlways(): void
    {
        $ym = new PlainYearMonth(2020, 6);

        self::assertSame('2020-06-01[u-ca=iso8601]', $ym->toString(CalendarDisplay::Always));
    }

    public function testToStringCalendarNever(): void
    {
        $ym = new PlainYearMonth(2020, 6);

        self::assertSame('2020-06', $ym->toString(CalendarDisplay::Never));
    }

    public function testToStringCalendarCritical(): void
    {
        $ym = new PlainYearMonth(2020, 6);

        self::assertSame('2020-06-01[!u-ca=iso8601]', $ym->toString(CalendarDisplay::Critical));
    }

    public function testToStringNegativeYear(): void
    {
        $ym = new PlainYearMonth(-1000, 1);

        self::assertSame('-001000-01', $ym->toString());
    }

    // -------------------------------------------------------------------------
    // toPlainDate
    // -------------------------------------------------------------------------

    public function testToPlainDate(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $date = $ym->toPlainDate(15);

        self::assertSame(2020, $date->year);
        self::assertSame(6, $date->month);
        self::assertSame(15, $date->day);
    }

    public function testToPlainDateFirstDay(): void
    {
        $ym = new PlainYearMonth(2020, 2);
        $date = $ym->toPlainDate(1);

        self::assertSame(2020, $date->year);
        self::assertSame(2, $date->month);
        self::assertSame(1, $date->day);
    }

    public function testToPlainDateLastDayLeapYear(): void
    {
        $ym = new PlainYearMonth(2020, 2);
        $date = $ym->toPlainDate(29);

        self::assertSame(29, $date->day);
    }

    // -------------------------------------------------------------------------
    // __toString / jsonSerialize
    // -------------------------------------------------------------------------

    public function testMagicToString(): void
    {
        $ym = new PlainYearMonth(2020, 6);

        self::assertSame('2020-06', (string) $ym);
    }

    public function testJsonSerialize(): void
    {
        $ym = new PlainYearMonth(2020, 6);

        self::assertSame('"2020-06"', json_encode($ym));
    }

    // -------------------------------------------------------------------------
    // toSpec / fromSpec
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecInstance(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $spec = $ym->toSpec();

        self::assertSame(2020, $spec->year);
        self::assertSame(6, $spec->month);
    }

    public function testFromSpecCreatesInstance(): void
    {
        $spec = new \Temporal\Spec\PlainYearMonth(2020, 6);
        $ym = PlainYearMonth::fromSpec($spec);

        self::assertSame(2020, $ym->year);
        self::assertSame(6, $ym->month);
    }

    public function testToSpecRoundTrip(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $restored = PlainYearMonth::fromSpec($ym->toSpec());

        self::assertTrue($ym->equals($restored));
    }

    // -------------------------------------------------------------------------
    // __debugInfo
    // -------------------------------------------------------------------------

    public function testDebugInfo(): void
    {
        $ym = new PlainYearMonth(2020, 6);
        $info = $ym->__debugInfo();

        self::assertSame(2020, $info['year']);
        self::assertSame(6, $info['month']);
        self::assertSame('iso8601', $info['calendarId']);
        self::assertSame('2020-06', $info['iso']);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: since() forwards all options
    // -------------------------------------------------------------------------

    public function testSinceForwardsLargestUnit(): void
    {
        $a = new PlainYearMonth(2018, 6);
        $b = new PlainYearMonth(2020, 9);
        $dur = $b->since($a, largestUnit: Unit::Month);

        self::assertSame(0, $dur->years);
        self::assertSame(27, $dur->months);
    }

    public function testSinceForwardsRoundingMode(): void
    {
        $a = new PlainYearMonth(2020, 1);
        $b = new PlainYearMonth(2020, 8);

        $trunc = $b->since($a, smallestUnit: Unit::Month, roundingIncrement: 3, roundingMode: RoundingMode::Trunc);
        $ceil = $b->since($a, smallestUnit: Unit::Month, roundingIncrement: 3, roundingMode: RoundingMode::Ceil);

        // 7 months truncated to nearest 3 = 6; ceiled to nearest 3 = 9
        self::assertSame(6, $trunc->months);
        self::assertSame(9, $ceil->months);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: until() forwards all options
    // -------------------------------------------------------------------------

    public function testUntilForwardsLargestUnit(): void
    {
        $a = new PlainYearMonth(2018, 6);
        $b = new PlainYearMonth(2020, 9);
        $dur = $a->until($b, largestUnit: Unit::Month);

        self::assertSame(0, $dur->years);
        self::assertSame(27, $dur->months);
    }

    public function testUntilForwardsSmallestUnit(): void
    {
        $a = new PlainYearMonth(2018, 6);
        $b = new PlainYearMonth(2020, 9);
        $dur = $a->until($b, smallestUnit: Unit::Year);

        self::assertSame(2, $dur->years);
        self::assertSame(0, $dur->months);
    }

    public function testUntilForwardsRoundingMode(): void
    {
        $a = new PlainYearMonth(2020, 1);
        $b = new PlainYearMonth(2020, 8);

        $trunc = $a->until($b, smallestUnit: Unit::Month, roundingIncrement: 3, roundingMode: RoundingMode::Trunc);
        $ceil = $a->until($b, smallestUnit: Unit::Month, roundingIncrement: 3, roundingMode: RoundingMode::Ceil);

        self::assertSame(6, $trunc->months);
        self::assertSame(9, $ceil->months);
    }
}
