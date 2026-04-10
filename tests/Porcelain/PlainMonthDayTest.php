<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use Temporal\CalendarDisplay;
use Temporal\Overflow;
use Temporal\PlainDate;
use Temporal\PlainMonthDay;

final class PlainMonthDayTest extends TemporalTestCase
{
    // -------------------------------------------------------------------------
    // Constructor & readonly properties
    // -------------------------------------------------------------------------

    public function testConstructorSetsFields(): void
    {
        $md = new PlainMonthDay(12, 25);

        self::assertSame(12, $md->month);
        self::assertSame(25, $md->day);
    }

    public function testConstructorRejectsInvalidDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainMonthDay(2, 30);
    }

    public function testConstructorAcceptsFeb29(): void
    {
        $md = new PlainMonthDay(2, 29);

        self::assertSame(2, $md->month);
        self::assertSame(29, $md->day);
    }

    // -------------------------------------------------------------------------
    // Virtual properties
    // -------------------------------------------------------------------------

    public function testCalendarIdIsIso8601(): void
    {
        self::assertSame('iso8601', new PlainMonthDay(12, 25)->calendarId);
    }

    public function testMonthCode(): void
    {
        self::assertSame('M01', new PlainMonthDay(1, 1)->monthCode);
        self::assertSame('M06', new PlainMonthDay(6, 15)->monthCode);
        self::assertSame('M12', new PlainMonthDay(12, 31)->monthCode);
    }

    // -------------------------------------------------------------------------
    // parse
    // -------------------------------------------------------------------------

    public function testParseWithDoubleDashPrefix(): void
    {
        $md = PlainMonthDay::parse('--12-25');

        self::assertSame(12, $md->month);
        self::assertSame(25, $md->day);
    }

    public function testParseFullDate(): void
    {
        // Parsing a full date string should still extract the month-day
        $md = PlainMonthDay::parse('2020-06-15');

        self::assertSame(6, $md->month);
        self::assertSame(15, $md->day);
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlainMonthDay::parse('not-a-date');
    }

    public function testParseFeb29(): void
    {
        $md = PlainMonthDay::parse('--02-29');

        self::assertSame(2, $md->month);
        self::assertSame(29, $md->day);
    }

    // -------------------------------------------------------------------------
    // with
    // -------------------------------------------------------------------------

    public function testWithMonth(): void
    {
        $md = new PlainMonthDay(6, 15);
        $result = $md->with(month: 3);

        self::assertSame(3, $result->month);
        self::assertSame(15, $result->day);
    }

    public function testWithDay(): void
    {
        $md = new PlainMonthDay(6, 15);
        $result = $md->with(day: 1);

        self::assertSame(6, $result->month);
        self::assertSame(1, $result->day);
    }

    public function testWithConstrainsDay(): void
    {
        // Day 31 constrained to max for Feb (29)
        $md = new PlainMonthDay(1, 31);
        $result = $md->with(month: 2);

        self::assertSame(29, $result->day);
    }

    public function testWithRejectOverflow(): void
    {
        $md = new PlainMonthDay(1, 31);

        $this->expectException(InvalidArgumentException::class);
        $md->with(month: 2, overflow: Overflow::Reject);
    }

    public function testWithReturnsNewInstance(): void
    {
        $md = new PlainMonthDay(6, 15);
        $result = $md->with(month: 3);

        self::assertNotSame($md, $result);
        self::assertSame(6, $md->month);
    }

    // -------------------------------------------------------------------------
    // equals
    // -------------------------------------------------------------------------

    public function testEqualsTrue(): void
    {
        $a = new PlainMonthDay(12, 25);
        $b = new PlainMonthDay(12, 25);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalseDifferentMonth(): void
    {
        $a = new PlainMonthDay(12, 25);
        $b = new PlainMonthDay(11, 25);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsFalseDifferentDay(): void
    {
        $a = new PlainMonthDay(12, 25);
        $b = new PlainMonthDay(12, 26);

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // toString
    // -------------------------------------------------------------------------

    public function testToStringDefault(): void
    {
        $md = new PlainMonthDay(12, 25);

        self::assertSame('12-25', $md->toString());
    }

    public function testToStringCalendarAlways(): void
    {
        $md = new PlainMonthDay(12, 25);

        self::assertSame('1972-12-25[u-ca=iso8601]', $md->toString(CalendarDisplay::Always));
    }

    public function testToStringCalendarNever(): void
    {
        $md = new PlainMonthDay(12, 25);

        self::assertSame('12-25', $md->toString(CalendarDisplay::Never));
    }

    public function testToStringCalendarCritical(): void
    {
        $md = new PlainMonthDay(12, 25);

        self::assertSame('1972-12-25[!u-ca=iso8601]', $md->toString(CalendarDisplay::Critical));
    }

    public function testToStringSingleDigitMonth(): void
    {
        $md = new PlainMonthDay(1, 5);

        self::assertSame('01-05', $md->toString());
    }

    // -------------------------------------------------------------------------
    // toPlainDate
    // -------------------------------------------------------------------------

    public function testToPlainDate(): void
    {
        $md = new PlainMonthDay(12, 25);
        $date = $md->toPlainDate(2020);

        self::assertSame(2020, $date->year);
        self::assertSame(12, $date->month);
        self::assertSame(25, $date->day);
    }

    public function testToPlainDateFeb29LeapYear(): void
    {
        $md = new PlainMonthDay(2, 29);
        $date = $md->toPlainDate(2020);

        self::assertSame(2020, $date->year);
        self::assertSame(2, $date->month);
        self::assertSame(29, $date->day);
    }

    public function testToPlainDateFeb29NonLeapYear(): void
    {
        // Feb 29 combined with a non-leap year should constrain day to 28
        $md = new PlainMonthDay(2, 29);
        $date = $md->toPlainDate(2019);

        self::assertSame(2019, $date->year);
        self::assertSame(2, $date->month);
        self::assertSame(28, $date->day);
    }

    public function testToPlainDateDifferentYears(): void
    {
        $md = new PlainMonthDay(6, 15);

        $date2020 = $md->toPlainDate(2020);
        $date2025 = $md->toPlainDate(2025);

        self::assertSame(2020, $date2020->year);
        self::assertSame(2025, $date2025->year);
        self::assertSame(6, $date2020->month);
        self::assertSame(15, $date2020->day);
    }

    // -------------------------------------------------------------------------
    // __toString / jsonSerialize
    // -------------------------------------------------------------------------

    public function testMagicToString(): void
    {
        $md = new PlainMonthDay(12, 25);

        self::assertSame('12-25', (string) $md);
    }

    public function testJsonSerialize(): void
    {
        $md = new PlainMonthDay(12, 25);

        self::assertSame('"12-25"', json_encode($md));
    }

    // -------------------------------------------------------------------------
    // toSpec / fromSpec
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecInstance(): void
    {
        $md = new PlainMonthDay(12, 25);
        $spec = $md->toSpec();

        self::assertSame(12, $spec->isoMonth);
        self::assertSame(25, $spec->day);
    }

    public function testFromSpecCreatesInstance(): void
    {
        $spec = new \Temporal\Spec\PlainMonthDay(12, 25);
        $md = PlainMonthDay::fromSpec($spec);

        self::assertSame(12, $md->month);
        self::assertSame(25, $md->day);
    }

    public function testToSpecRoundTrip(): void
    {
        $md = new PlainMonthDay(12, 25);
        $restored = PlainMonthDay::fromSpec($md->toSpec());

        self::assertTrue($md->equals($restored));
    }

    // -------------------------------------------------------------------------
    // __debugInfo
    // -------------------------------------------------------------------------

    public function testDebugInfo(): void
    {
        $md = new PlainMonthDay(12, 25);
        $info = $md->__debugInfo();

        self::assertSame(12, $info['month']);
        self::assertSame(25, $info['day']);
        self::assertSame('iso8601', $info['calendarId']);
        self::assertSame('12-25', $info['iso']);
    }
}
