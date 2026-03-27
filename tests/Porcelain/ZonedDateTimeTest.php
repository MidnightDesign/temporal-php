<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use Temporal\CalendarDisplay;
use Temporal\Disambiguation;
use Temporal\Duration;
use Temporal\Instant;
use Temporal\OffsetDisplay;
use Temporal\OffsetOption;
use Temporal\Overflow;
use Temporal\PlainDate;
use Temporal\PlainDateTime;
use Temporal\PlainTime;
use Temporal\RoundingMode;

use Temporal\TimeZoneDisplay;
use Temporal\TransitionDirection;
use Temporal\Unit;
use Temporal\ZonedDateTime;

final class ZonedDateTimeTest extends TemporalTestCase
{
    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorUtc(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        self::assertSame(0, $zdt->epochNanoseconds);
        self::assertSame('UTC', $zdt->timeZoneId);
    }

    public function testConstructorWithIanaTimeZone(): void
    {
        $ns = 1_577_836_800_000_000_000; // 2020-01-01T00:00:00Z
        $zdt = new ZonedDateTime($ns, 'America/New_York');

        self::assertSame($ns, $zdt->epochNanoseconds);
        self::assertSame('America/New_York', $zdt->timeZoneId);
    }

    public function testConstructorWithFixedOffset(): void
    {
        $zdt = new ZonedDateTime(0, '+05:30');

        self::assertSame(0, $zdt->epochNanoseconds);
        self::assertSame('+05:30', $zdt->timeZoneId);
    }

    public function testConstructorInvalidTimeZoneThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ZonedDateTime(0, 'Invalid/Zone');
    }

    // -------------------------------------------------------------------------
    // Virtual properties - epoch
    // -------------------------------------------------------------------------

    public function testEpochNanoseconds(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $zdt = new ZonedDateTime($ns, 'UTC');

        self::assertSame($ns, $zdt->epochNanoseconds);
    }

    public function testEpochMilliseconds(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $zdt = new ZonedDateTime($ns, 'UTC');

        self::assertSame(1_577_836_800_123, $zdt->epochMilliseconds);
    }

    // -------------------------------------------------------------------------
    // Virtual properties - identity
    // -------------------------------------------------------------------------

    public function testTimeZoneId(): void
    {
        $zdt = new ZonedDateTime(0, 'Europe/Berlin');

        self::assertSame('Europe/Berlin', $zdt->timeZoneId);
    }

    public function testCalendarId(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        self::assertSame('iso8601', $zdt->calendarId);
    }

    // -------------------------------------------------------------------------
    // Virtual properties - date/time components
    // -------------------------------------------------------------------------

    public function testDateTimeComponentsUtc(): void
    {
        // 2020-06-15T13:45:30.123456789Z
        $zdt = ZonedDateTime::parse('2020-06-15T13:45:30.123456789+00:00[UTC]');

        self::assertSame(2020, $zdt->year);
        self::assertSame(6, $zdt->month);
        self::assertSame(15, $zdt->day);
        self::assertSame(13, $zdt->hour);
        self::assertSame(45, $zdt->minute);
        self::assertSame(30, $zdt->second);
        self::assertSame(123, $zdt->millisecond);
        self::assertSame(456, $zdt->microsecond);
        self::assertSame(789, $zdt->nanosecond);
    }

    public function testDateTimeComponentsWithOffset(): void
    {
        // 2020-01-01T00:00:00Z in Asia/Kolkata is 2020-01-01T05:30:00+05:30
        $zdt = new ZonedDateTime(1_577_836_800_000_000_000, 'Asia/Kolkata');

        self::assertSame(2020, $zdt->year);
        self::assertSame(1, $zdt->month);
        self::assertSame(1, $zdt->day);
        self::assertSame(5, $zdt->hour);
        self::assertSame(30, $zdt->minute);
    }

    // -------------------------------------------------------------------------
    // Virtual properties - offset
    // -------------------------------------------------------------------------

    public function testOffsetUtc(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        self::assertSame('+00:00', $zdt->offset);
    }

    public function testOffsetKolkata(): void
    {
        $zdt = new ZonedDateTime(0, 'Asia/Kolkata');

        self::assertSame('+05:30', $zdt->offset);
    }

    public function testOffsetNanoseconds(): void
    {
        $zdt = new ZonedDateTime(0, 'Asia/Kolkata');

        // +05:30 = 5*3600 + 30*60 = 19800 seconds = 19800_000_000_000 ns
        self::assertSame(19800_000_000_000, $zdt->offsetNanoseconds);
    }

    // -------------------------------------------------------------------------
    // Virtual properties - calendar
    // -------------------------------------------------------------------------

    public function testMonthCode(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T00:00:00+00:00[UTC]');

        self::assertSame('M06', $zdt->monthCode);
    }

    public function testDayOfWeek(): void
    {
        // 2020-06-15 is a Monday (1)
        $zdt = ZonedDateTime::parse('2020-06-15T00:00:00+00:00[UTC]');

        self::assertSame(1, $zdt->dayOfWeek);
    }

    public function testDayOfYear(): void
    {
        // Jan 1 is day 1
        $zdt = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');

        self::assertSame(1, $zdt->dayOfYear);
    }

    public function testWeekOfYear(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');

        self::assertSame(1, $zdt->weekOfYear);
    }

    public function testYearOfWeek(): void
    {
        // 2024-12-30 (Monday) is in ISO week 1 of 2025
        $zdt = ZonedDateTime::parse('2024-12-30T00:00:00+00:00[UTC]');

        self::assertSame(2025, $zdt->yearOfWeek);
    }

    public function testDaysInMonth(): void
    {
        $zdt = ZonedDateTime::parse('2020-02-15T00:00:00+00:00[UTC]');

        self::assertSame(29, $zdt->daysInMonth); // 2020 is leap year
    }

    public function testDaysInYear(): void
    {
        $zdtLeap = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $zdtNormal = ZonedDateTime::parse('2019-01-01T00:00:00+00:00[UTC]');

        self::assertSame(366, $zdtLeap->daysInYear);
        self::assertSame(365, $zdtNormal->daysInYear);
    }

    public function testInLeapYear(): void
    {
        $zdtLeap = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $zdtNormal = ZonedDateTime::parse('2019-01-01T00:00:00+00:00[UTC]');

        self::assertTrue($zdtLeap->inLeapYear);
        self::assertFalse($zdtNormal->inLeapYear);
    }

    public function testHoursInDay(): void
    {
        // A normal day has 24 hours
        $zdt = ZonedDateTime::parse('2020-06-15T00:00:00+00:00[UTC]');

        self::assertSame(24, $zdt->hoursInDay);
    }

    // -------------------------------------------------------------------------
    // parse()
    // -------------------------------------------------------------------------

    public function testParseBasic(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        self::assertSame(2020, $zdt->year);
        self::assertSame(1, $zdt->month);
        self::assertSame(1, $zdt->day);
        self::assertSame(12, $zdt->hour);
        self::assertSame('UTC', $zdt->timeZoneId);
    }

    public function testParseWithIanaTimezone(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+05:30[Asia/Kolkata]');

        self::assertSame('Asia/Kolkata', $zdt->timeZoneId);
        self::assertSame(12, $zdt->hour);
        self::assertSame('+05:30', $zdt->offset);
    }

    public function testParseWithFractionalSeconds(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00.123456789+00:00[UTC]');

        self::assertSame(123, $zdt->millisecond);
        self::assertSame(456, $zdt->microsecond);
        self::assertSame(789, $zdt->nanosecond);
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ZonedDateTime::parse('not-a-zoned-datetime');
    }

    public function testParseWithDisambiguationEarlier(): void
    {
        // During fall-back (Nov 1, 2020 in US/Eastern), 1:30 AM exists twice.
        // "earlier" should pick the EDT (summer) occurrence.
        $zdt = ZonedDateTime::parse(
            '2020-11-01T01:30:00-04:00[America/New_York]',
            disambiguation: Disambiguation::Earlier,
            offset: OffsetOption::Ignore,
        );

        self::assertSame(1, $zdt->hour);
        self::assertSame(30, $zdt->minute);
        self::assertSame('-04:00', $zdt->offset);
    }

    public function testParseWithDisambiguationCompatible(): void
    {
        // "compatible" should also resolve the ambiguous time
        $zdt = ZonedDateTime::parse(
            '2020-11-01T01:30:00-04:00[America/New_York]',
            disambiguation: Disambiguation::Compatible,
            offset: OffsetOption::Ignore,
        );

        self::assertSame(1, $zdt->hour);
        self::assertSame(30, $zdt->minute);
    }

    public function testParseWithOffsetOptionUse(): void
    {
        $zdt = ZonedDateTime::parse(
            '2020-01-01T12:00:00+05:30[Asia/Kolkata]',
            offset: OffsetOption::Use,
        );

        self::assertSame(12, $zdt->hour);
        self::assertSame('+05:30', $zdt->offset);
    }

    public function testParseWithOffsetOptionRejectValid(): void
    {
        // Offset matches the timezone -- should succeed
        $zdt = ZonedDateTime::parse(
            '2020-01-01T12:00:00+05:30[Asia/Kolkata]',
            offset: OffsetOption::Reject,
        );

        self::assertSame(12, $zdt->hour);
    }

    public function testParseWithOffsetOptionRejectInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Offset does not match the timezone
        ZonedDateTime::parse(
            '2020-01-01T12:00:00+00:00[Asia/Kolkata]',
            offset: OffsetOption::Reject,
        );
    }

    // -------------------------------------------------------------------------
    // compare()
    // -------------------------------------------------------------------------

    public function testCompareEqual(): void
    {
        $a = new ZonedDateTime(1_000_000_000, 'UTC');
        $b = new ZonedDateTime(1_000_000_000, 'UTC');

        self::assertSame(0, ZonedDateTime::compare($a, $b));
    }

    public function testCompareLess(): void
    {
        $a = new ZonedDateTime(1_000_000_000, 'UTC');
        $b = new ZonedDateTime(2_000_000_000, 'UTC');

        self::assertSame(-1, ZonedDateTime::compare($a, $b));
    }

    public function testCompareGreater(): void
    {
        $a = new ZonedDateTime(2_000_000_000, 'UTC');
        $b = new ZonedDateTime(1_000_000_000, 'UTC');

        self::assertSame(1, ZonedDateTime::compare($a, $b));
    }

    public function testCompareDifferentTimeZonesSameInstant(): void
    {
        $ns = 1_577_836_800_000_000_000;
        $a = new ZonedDateTime($ns, 'UTC');
        $b = new ZonedDateTime($ns, 'Asia/Kolkata');

        self::assertSame(0, ZonedDateTime::compare($a, $b));
    }

    // -------------------------------------------------------------------------
    // equals()
    // -------------------------------------------------------------------------

    public function testEqualsTrue(): void
    {
        $a = new ZonedDateTime(1_577_836_800_000_000_000, 'UTC');
        $b = new ZonedDateTime(1_577_836_800_000_000_000, 'UTC');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalseDifferentNs(): void
    {
        $a = new ZonedDateTime(1_577_836_800_000_000_000, 'UTC');
        $b = new ZonedDateTime(1_577_836_800_000_000_001, 'UTC');

        self::assertFalse($a->equals($b));
    }

    public function testEqualsFalseDifferentTimeZone(): void
    {
        $ns = 1_577_836_800_000_000_000;
        $a = new ZonedDateTime($ns, 'UTC');
        $b = new ZonedDateTime($ns, 'Asia/Kolkata');

        // equals checks instant + timezone + calendar, different timezone => false
        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // with()
    // -------------------------------------------------------------------------

    public function testWithYear(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->with(year: 2021);

        self::assertSame(2021, $result->year);
        self::assertSame(6, $result->month);
        self::assertSame(15, $result->day);
        self::assertSame(12, $result->hour);
    }

    public function testWithMonth(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->with(month: 3);

        self::assertSame(2020, $result->year);
        self::assertSame(3, $result->month);
        self::assertSame(15, $result->day);
    }

    public function testWithDay(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->with(day: 20);

        self::assertSame(20, $result->day);
    }

    public function testWithHour(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->with(hour: 18);

        self::assertSame(18, $result->hour);
    }

    public function testWithMinute(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->with(minute: 45);

        self::assertSame(45, $result->minute);
    }

    public function testWithConstrainsDay(): void
    {
        // Jan 31 -> month 2 with constrain should give Feb 29 (2020 is leap)
        $zdt = ZonedDateTime::parse('2020-01-31T12:00:00+00:00[UTC]');
        $result = $zdt->with(month: 2, overflow: Overflow::Constrain);

        self::assertSame(29, $result->day);
    }

    public function testWithRejectOverflow(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-31T12:00:00+00:00[UTC]');

        $this->expectException(InvalidArgumentException::class);

        $zdt->with(month: 2, overflow: Overflow::Reject);
    }

    public function testWithDoesNotMutateOriginal(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $zdt->with(year: 2021);

        self::assertSame(2020, $zdt->year);
    }

    // -------------------------------------------------------------------------
    // add() / subtract()
    // -------------------------------------------------------------------------

    public function testAddHours(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $result = $zdt->add(new Duration(hours: 2));

        self::assertSame(2, $result->hour);
    }

    public function testAddDays(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');
        $result = $zdt->add(new Duration(days: 10));

        self::assertSame(11, $result->day);
        self::assertSame(12, $result->hour);
    }

    public function testAddMonths(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-31T12:00:00+00:00[UTC]');
        $result = $zdt->add(new Duration(months: 1));

        // Feb 2020 has 29 days, so Jan 31 + 1 month constrains to Feb 29
        self::assertSame(2, $result->month);
        self::assertSame(29, $result->day);
    }

    public function testAddYears(): void
    {
        $zdt = ZonedDateTime::parse('2020-02-29T12:00:00+00:00[UTC]');
        $result = $zdt->add(new Duration(years: 1));

        // 2021 is not a leap year, so Feb 29 constrains to Feb 28
        self::assertSame(2021, $result->year);
        self::assertSame(2, $result->month);
        self::assertSame(28, $result->day);
    }

    public function testAddDoesNotMutateOriginal(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $zdt->add(new Duration(hours: 1));

        self::assertSame(0, $zdt->hour);
    }

    public function testSubtractHours(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');
        $result = $zdt->subtract(new Duration(hours: 3));

        self::assertSame(9, $result->hour);
    }

    public function testSubtractDays(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-11T12:00:00+00:00[UTC]');
        $result = $zdt->subtract(new Duration(days: 10));

        self::assertSame(1, $result->day);
    }

    public function testSubtractAcrossMonthBoundary(): void
    {
        $zdt = ZonedDateTime::parse('2020-03-01T12:00:00+00:00[UTC]');
        $result = $zdt->subtract(new Duration(days: 1));

        self::assertSame(2, $result->month);
        self::assertSame(29, $result->day); // 2020 is leap year
    }

    // -------------------------------------------------------------------------
    // round()
    // -------------------------------------------------------------------------

    public function testRoundToHour(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T13:45:30+00:00[UTC]');
        $result = $zdt->round(Unit::Hour);

        self::assertSame(14, $result->hour);
        self::assertSame(0, $result->minute);
        self::assertSame(0, $result->second);
    }

    public function testRoundToMinute(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T13:45:30+00:00[UTC]');
        $result = $zdt->round(Unit::Minute);

        self::assertSame(46, $result->minute);
        self::assertSame(0, $result->second);
    }

    public function testRoundToSecondTrunc(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T13:45:30.999+00:00[UTC]');
        $result = $zdt->round(Unit::Second, RoundingMode::Trunc);

        self::assertSame(30, $result->second);
        self::assertSame(0, $result->millisecond);
    }

    public function testRoundWithIncrement(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T13:47:00+00:00[UTC]');
        $result = $zdt->round(Unit::Minute, RoundingMode::HalfExpand, 15);

        self::assertSame(45, $result->minute);
    }

    // -------------------------------------------------------------------------
    // since() / until()
    // -------------------------------------------------------------------------

    public function testSinceBasic(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T02:30:00+00:00[UTC]');
        $d = $b->since($a);

        self::assertSame(2, $d->hours);
        self::assertSame(30, $d->minutes);
    }

    public function testSinceNegative(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T02:30:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $d = $b->since($a);

        self::assertSame(-2, $d->hours);
        self::assertSame(-30, $d->minutes);
    }

    public function testSinceWithLargestUnit(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-04-01T00:00:00+00:00[UTC]');
        $d = $b->since($a, largestUnit: Unit::Month);

        self::assertSame(3, $d->months);
    }

    public function testSinceWithSmallestUnit(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T01:30:45+00:00[UTC]');
        $d = $b->since($a, smallestUnit: Unit::Minute);

        self::assertSame(1, $d->hours);
        self::assertSame(30, $d->minutes);
        self::assertSame(0, $d->seconds);
    }

    public function testUntilBasic(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T03:00:00+00:00[UTC]');
        $d = $a->until($b);

        self::assertSame(3, $d->hours);
    }

    public function testUntilNegative(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T03:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $d = $a->until($b);

        self::assertSame(-3, $d->hours);
    }

    public function testUntilWithLargestUnit(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2021-06-15T00:00:00+00:00[UTC]');
        $d = $a->until($b, largestUnit: Unit::Year);

        self::assertSame(1, $d->years);
        self::assertSame(5, $d->months);
        self::assertSame(14, $d->days);
    }

    // -------------------------------------------------------------------------
    // Conversion methods
    // -------------------------------------------------------------------------

    public function testToInstant(): void
    {
        $ns = 1_577_836_800_000_000_000;
        $zdt = new ZonedDateTime($ns, 'America/New_York');
        $instant = $zdt->toInstant();

        self::assertSame($ns, $instant->epochNanoseconds);
    }

    public function testToPlainDate(): void
    {
        // 2020-01-01T00:00:00Z in Kolkata is 2020-01-01T05:30:00+05:30
        $zdt = new ZonedDateTime(1_577_836_800_000_000_000, 'Asia/Kolkata');
        $pd = $zdt->toPlainDate();

        self::assertSame(2020, $pd->year);
        self::assertSame(1, $pd->month);
        self::assertSame(1, $pd->day);
    }

    public function testToPlainTime(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T13:45:30.123456789+00:00[UTC]');
        $pt = $zdt->toPlainTime();

        $this->assertPlainTimeIs(13, 45, 30, 123, 456, 789, $pt);
    }

    public function testToPlainDateTime(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T13:45:30+00:00[UTC]');
        $pdt = $zdt->toPlainDateTime();

        self::assertSame(2020, $pdt->year);
        self::assertSame(6, $pdt->month);
        self::assertSame(15, $pdt->day);
        self::assertSame(13, $pdt->hour);
        self::assertSame(45, $pdt->minute);
        self::assertSame(30, $pdt->second);
    }

    // -------------------------------------------------------------------------
    // withTimeZone()
    // -------------------------------------------------------------------------

    public function testWithTimeZone(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $result = $zdt->withTimeZone('America/New_York');

        // Same instant, different time zone display
        self::assertSame($zdt->epochNanoseconds, $result->epochNanoseconds);
        self::assertSame('America/New_York', $result->timeZoneId);
        // In winter, EST is UTC-5
        self::assertSame(19, $result->hour);
        self::assertSame(12, $result->month); // Dec 31 in NY
    }

    public function testWithTimeZonePreservesEpochNanoseconds(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $zdt = new ZonedDateTime($ns, 'UTC');
        $result = $zdt->withTimeZone('Europe/Berlin');

        self::assertSame($ns, $result->epochNanoseconds);
    }

    // -------------------------------------------------------------------------
    // withPlainTime()
    // -------------------------------------------------------------------------

    public function testWithPlainTimeReplacesTime(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->withPlainTime(new PlainTime(18, 30, 45));

        self::assertSame(2020, $result->year);
        self::assertSame(6, $result->month);
        self::assertSame(15, $result->day);
        self::assertSame(18, $result->hour);
        self::assertSame(30, $result->minute);
        self::assertSame(45, $result->second);
    }

    public function testWithPlainTimeNullSetsMidnight(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->withPlainTime();

        self::assertSame(0, $result->hour);
        self::assertSame(0, $result->minute);
        self::assertSame(0, $result->second);
        self::assertSame(15, $result->day);
    }

    // -------------------------------------------------------------------------
    // startOfDay()
    // -------------------------------------------------------------------------

    public function testStartOfDay(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T13:45:30+00:00[UTC]');
        $sod = $zdt->startOfDay();

        self::assertSame(2020, $sod->year);
        self::assertSame(6, $sod->month);
        self::assertSame(15, $sod->day);
        self::assertSame(0, $sod->hour);
        self::assertSame(0, $sod->minute);
        self::assertSame(0, $sod->second);
        self::assertSame('UTC', $sod->timeZoneId);
    }

    public function testStartOfDayPreservesDate(): void
    {
        $zdt = ZonedDateTime::parse('2020-12-31T23:59:59+00:00[UTC]');
        $sod = $zdt->startOfDay();

        self::assertSame(2020, $sod->year);
        self::assertSame(12, $sod->month);
        self::assertSame(31, $sod->day);
    }

    // -------------------------------------------------------------------------
    // getTimeZoneTransition()
    // -------------------------------------------------------------------------

    public function testGetTimeZoneTransitionNext(): void
    {
        // US Eastern has DST transitions. The method returns the next transition
        // point after the current instant.
        $zdt = ZonedDateTime::parse('2020-01-01T00:00:00-05:00[America/New_York]');
        $next = $zdt->getTimeZoneTransition(TransitionDirection::Next);

        self::assertNotNull($next);
        self::assertSame('America/New_York', $next->timeZoneId);
    }

    public function testGetTimeZoneTransitionPrevious(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-01T00:00:00-04:00[America/New_York]');
        $prev = $zdt->getTimeZoneTransition(TransitionDirection::Previous);

        self::assertNotNull($prev);
        // Previous transition from June 2020 should be March 2020 (spring forward)
        self::assertSame(2020, $prev->year);
        self::assertSame(3, $prev->month);
    }

    public function testGetTimeZoneTransitionFixedOffsetReturnsNull(): void
    {
        $zdt = new ZonedDateTime(0, '+05:30');

        self::assertNull($zdt->getTimeZoneTransition(TransitionDirection::Next));
    }

    public function testGetTimeZoneTransitionUtcReturnsNull(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        self::assertNull($zdt->getTimeZoneTransition(TransitionDirection::Next));
    }

    // -------------------------------------------------------------------------
    // toString() with enum params
    // -------------------------------------------------------------------------

    public function testToStringDefault(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        self::assertSame('2020-01-01T12:00:00+00:00[UTC]', $zdt->toString());
    }

    public function testToStringWithFractionalSecondDigits(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00.100+00:00[UTC]');

        self::assertSame(
            '2020-01-01T12:00:00.100+00:00[UTC]',
            $zdt->toString(fractionalSecondDigits: 3),
        );
    }

    public function testToStringWithFractionalSecondDigitsZero(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00.500+00:00[UTC]');

        self::assertSame(
            '2020-01-01T12:00:00+00:00[UTC]',
            $zdt->toString(fractionalSecondDigits: 0),
        );
    }

    public function testToStringWithSmallestUnit(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:30:45+00:00[UTC]');

        // smallestUnit: minute produces HH:MM format (no seconds)
        self::assertSame(
            '2020-01-01T12:30+00:00[UTC]',
            $zdt->toString(smallestUnit: Unit::Minute),
        );
    }

    public function testToStringWithRoundingMode(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00.600+00:00[UTC]');

        self::assertSame(
            '2020-01-01T12:00:01+00:00[UTC]',
            $zdt->toString(fractionalSecondDigits: 0, roundingMode: RoundingMode::Ceil),
        );
    }

    public function testToStringOffsetDisplayNever(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        $str = $zdt->toString(offset: OffsetDisplay::Never);

        self::assertStringNotContainsString('+00:00', $str);
        self::assertStringContainsString('[UTC]', $str);
    }

    public function testToStringTimeZoneDisplayNever(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        $str = $zdt->toString(timeZoneName: TimeZoneDisplay::Never);

        self::assertStringNotContainsString('[UTC]', $str);
        self::assertStringContainsString('+00:00', $str);
    }

    public function testToStringTimeZoneDisplayCritical(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        $str = $zdt->toString(timeZoneName: TimeZoneDisplay::Critical);

        self::assertStringContainsString('[!UTC]', $str);
    }

    public function testToStringCalendarDisplayAlways(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        $str = $zdt->toString(calendarName: CalendarDisplay::Always);

        self::assertStringContainsString('[u-ca=iso8601]', $str);
    }

    public function testToStringCalendarDisplayNever(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        $str = $zdt->toString(calendarName: CalendarDisplay::Never);

        self::assertStringNotContainsString('u-ca=', $str);
    }

    public function testToStringCalendarDisplayCritical(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        $str = $zdt->toString(calendarName: CalendarDisplay::Critical);

        self::assertStringContainsString('[!u-ca=iso8601]', $str);
    }

    // -------------------------------------------------------------------------
    // __toString() / jsonSerialize()
    // -------------------------------------------------------------------------

    public function testMagicToString(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        self::assertSame('2020-01-01T12:00:00+00:00[UTC]', (string) $zdt);
    }

    public function testJsonSerialize(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T12:00:00+00:00[UTC]');

        self::assertSame('"2020-01-01T12:00:00+00:00[UTC]"', json_encode($zdt));
    }

    // -------------------------------------------------------------------------
    // toSpec() / fromSpec()
    // -------------------------------------------------------------------------

    public function testToSpecReturnsSpecZonedDateTime(): void
    {
        $ns = 1_577_836_800_000_000_000;
        $zdt = new ZonedDateTime($ns, 'UTC');
        $spec = $zdt->toSpec();

        self::assertSame($ns, $spec->epochNanoseconds);
        self::assertSame('UTC', $spec->timeZoneId);
    }

    public function testFromSpecRoundTrip(): void
    {
        $ns = 1_577_836_800_123_456_789;
        $zdt = new ZonedDateTime($ns, 'America/New_York');
        $restored = ZonedDateTime::fromSpec($zdt->toSpec());

        self::assertTrue($zdt->equals($restored));
    }

    public function testFromSpecCreatesCorrectInstance(): void
    {
        $spec = new \Temporal\Spec\ZonedDateTime(0, 'Europe/Berlin');
        $zdt = ZonedDateTime::fromSpec($spec);

        self::assertSame(0, $zdt->epochNanoseconds);
        self::assertSame('Europe/Berlin', $zdt->timeZoneId);
    }

    // -------------------------------------------------------------------------
    // __debugInfo()
    // -------------------------------------------------------------------------

    public function testDebugInfoContainsExpectedKeys(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T13:45:30.123456789+00:00[UTC]');
        $info = $zdt->__debugInfo();

        self::assertArrayHasKey('epochNanoseconds', $info);
        self::assertArrayHasKey('timeZoneId', $info);
        self::assertArrayHasKey('string', $info);
        self::assertArrayHasKey('year', $info);
        self::assertArrayHasKey('month', $info);
        self::assertArrayHasKey('day', $info);
        self::assertArrayHasKey('hour', $info);
        self::assertArrayHasKey('minute', $info);
        self::assertArrayHasKey('second', $info);
        self::assertArrayHasKey('millisecond', $info);
        self::assertArrayHasKey('microsecond', $info);
        self::assertArrayHasKey('nanosecond', $info);
        self::assertArrayHasKey('offset', $info);

        self::assertSame(2020, $info['year']);
        self::assertSame(6, $info['month']);
        self::assertSame(15, $info['day']);
        self::assertSame(13, $info['hour']);
        self::assertSame('UTC', $info['timeZoneId']);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: parse() forwards disambiguation option
    // -------------------------------------------------------------------------

    public function testParseForwardsDisambiguation(): void
    {
        // The disambiguation option is forwarded; we verify it doesn't throw
        // and produces a valid result when set to a non-default value.
        $zdt = ZonedDateTime::parse(
            '2020-06-15T12:00:00+00:00[UTC]',
            disambiguation: Disambiguation::Later,
        );

        self::assertSame(12, $zdt->hour);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: parse() forwards offset option
    // -------------------------------------------------------------------------

    public function testParseForwardsOffsetOptionIgnore(): void
    {
        // The offset +01:00 doesn't match UTC, but "ignore" ignores offset
        $zdt = ZonedDateTime::parse(
            '2020-01-01T12:00:00+01:00[UTC]',
            offset: OffsetOption::Ignore,
        );

        self::assertSame(12, $zdt->hour);
        self::assertSame('+00:00', $zdt->offset);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: with() forwards disambiguation option
    // -------------------------------------------------------------------------

    public function testWithForwardsDisambiguationEarlier(): void
    {
        // Set up a ZonedDateTime and then change hour to an ambiguous time
        $zdt = ZonedDateTime::parse('2020-11-01T00:00:00-04:00[America/New_York]');
        $result = $zdt->with(hour: 1, minute: 30, disambiguation: Disambiguation::Earlier);

        self::assertSame(1, $result->hour);
        self::assertSame(30, $result->minute);
        self::assertSame('-04:00', $result->offset);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: with() forwards offset option
    // -------------------------------------------------------------------------

    public function testWithForwardsOffsetOptionPrefer(): void
    {
        $zdt = ZonedDateTime::parse('2020-06-15T12:00:00+00:00[UTC]');
        $result = $zdt->with(hour: 14, offsetOption: OffsetOption::Prefer);

        self::assertSame(14, $result->hour);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: add/subtract forward overflow option
    // -------------------------------------------------------------------------

    public function testAddForwardsOverflowReject(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-31T12:00:00+00:00[UTC]');
        $this->expectException(InvalidArgumentException::class);
        $zdt->add(new Duration(months: 1), Overflow::Reject);
    }

    public function testSubtractForwardsOverflowReject(): void
    {
        $zdt = ZonedDateTime::parse('2020-03-31T12:00:00+00:00[UTC]');
        $this->expectException(InvalidArgumentException::class);
        $zdt->subtract(new Duration(months: 1), Overflow::Reject);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: since() forwards all options
    // -------------------------------------------------------------------------

    public function testSinceForwardsSmallestUnit(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T01:30:45+00:00[UTC]');
        $d = $b->since($a, smallestUnit: Unit::Minute);

        self::assertSame(1, $d->hours);
        self::assertSame(30, $d->minutes);
        self::assertSame(0, $d->seconds);
    }

    public function testSinceForwardsRoundingMode(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T00:00:29+00:00[UTC]');

        $trunc = $b->since($a, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc);
        $ceil = $b->since($a, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Ceil);

        self::assertSame(0, $trunc->minutes);
        self::assertSame(1, $ceil->minutes);
    }

    public function testSinceForwardsRoundingIncrement(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T00:07:00+00:00[UTC]');
        $d = $b->since($a, smallestUnit: Unit::Minute, roundingIncrement: 5);

        // 7 minutes truncated to nearest 5 = 5
        self::assertSame(5, $d->minutes);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: until() forwards all options
    // -------------------------------------------------------------------------

    public function testUntilForwardsSmallestUnit(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T01:30:45+00:00[UTC]');
        $d = $a->until($b, smallestUnit: Unit::Minute);

        self::assertSame(1, $d->hours);
        self::assertSame(30, $d->minutes);
        self::assertSame(0, $d->seconds);
    }

    public function testUntilForwardsRoundingMode(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T00:00:29+00:00[UTC]');

        $trunc = $a->until($b, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Trunc);
        $ceil = $a->until($b, smallestUnit: Unit::Minute, roundingMode: RoundingMode::Ceil);

        self::assertSame(0, $trunc->minutes);
        self::assertSame(1, $ceil->minutes);
    }

    public function testUntilForwardsRoundingIncrement(): void
    {
        $a = ZonedDateTime::parse('2020-01-01T00:00:00+00:00[UTC]');
        $b = ZonedDateTime::parse('2020-01-01T00:07:00+00:00[UTC]');
        $d = $a->until($b, smallestUnit: Unit::Minute, roundingIncrement: 5);

        // 7 minutes truncated to nearest 5 = 5
        self::assertSame(5, $d->minutes);
    }

    // -------------------------------------------------------------------------
    // Mutation coverage: round() forwards roundingIncrement
    // -------------------------------------------------------------------------

    public function testRoundForwardsRoundingIncrement(): void
    {
        $zdt = ZonedDateTime::parse('2020-01-01T13:07:00+00:00[UTC]');
        $result = $zdt->round(Unit::Minute, RoundingMode::Trunc, 5);

        self::assertSame(5, $result->minute);
    }

    public function testRoundDefaultRoundingIncrementIsOne(): void
    {
        // With default roundingIncrement=1 and Trunc, 13:07:29 rounds to 13:07:00
        // With roundingIncrement=2, it would round to 13:06:00
        $zdt = ZonedDateTime::parse('2020-01-01T13:07:29+00:00[UTC]');
        $result = $zdt->round(Unit::Minute, RoundingMode::Trunc);

        self::assertSame(7, $result->minute);
    }

}
