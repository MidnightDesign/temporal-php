<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Spec\Internal\Calendar\CalendarFactory;
use Temporal\Spec\PlainDate;
use Temporal\Spec\PlainDateTime;
use Temporal\Spec\PlainMonthDay;
use Temporal\Spec\PlainYearMonth;

/**
 * Tests for Phase 4: Calendar field resolution (from/with).
 *
 * Verifies that calendarToIso(), calendarToIsoFromMonthCode(), and monthCodeToMonth()
 * correctly resolve non-ISO calendar fields to ISO fields, and that fromPropertyBag()
 * and with() work correctly for non-ISO calendars.
 */
final class CalendarFieldResolutionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // PlainDate::from() with non-ISO calendars
    // -------------------------------------------------------------------------

    public function testPlainDateFromHebrewPropertyBag(): void
    {
        $d = PlainDate::from(['year' => 5784, 'month' => 5, 'day' => 5, 'calendar' => 'hebrew']);

        self::assertSame('hebrew', $d->calendarId);
        self::assertSame(5784, $d->year);
        self::assertSame(5, $d->month);
        self::assertSame(5, $d->day);
    }

    public function testPlainDateFromGregoryPropertyBag(): void
    {
        $d = PlainDate::from(['year' => 2024, 'month' => 1, 'day' => 15, 'calendar' => 'gregory']);

        self::assertSame('gregory', $d->calendarId);
        self::assertSame(2024, $d->isoYear);
        self::assertSame(1, $d->isoMonth);
        self::assertSame(15, $d->isoDay);
    }

    public function testPlainDateFromBuddhistPropertyBag(): void
    {
        $d = PlainDate::from(['year' => 2567, 'month' => 1, 'day' => 15, 'calendar' => 'buddhist']);

        self::assertSame('buddhist', $d->calendarId);
        self::assertSame(2024, $d->isoYear);
    }

    public function testPlainDateFromPersianPropertyBag(): void
    {
        $d = PlainDate::from(['year' => 1403, 'month' => 1, 'day' => 1, 'calendar' => 'persian']);

        self::assertSame('persian', $d->calendarId);
        self::assertSame(1403, $d->year);
        self::assertSame(1, $d->month);
        self::assertSame(1, $d->day);
    }

    public function testPlainDateFromIslamicCivilPropertyBag(): void
    {
        $d = PlainDate::from(['year' => 1445, 'month' => 1, 'day' => 1, 'calendar' => 'islamic-civil']);

        self::assertSame('islamic-civil', $d->calendarId);
        self::assertSame(1445, $d->year);
    }

    public function testPlainDateFromCopticPropertyBag(): void
    {
        $d = PlainDate::from(['year' => 1740, 'month' => 1, 'day' => 1, 'calendar' => 'coptic']);

        self::assertSame('coptic', $d->calendarId);
        self::assertSame(1740, $d->year);
    }

    public function testPlainDateFromJapanesePropertyBag(): void
    {
        $d = PlainDate::from(['year' => 2024, 'month' => 1, 'day' => 15, 'calendar' => 'japanese']);

        self::assertSame('japanese', $d->calendarId);
        self::assertSame(2024, $d->isoYear);
    }

    // -------------------------------------------------------------------------
    // PlainDate::from() with monthCode
    // -------------------------------------------------------------------------

    public function testPlainDateFromHebrewMonthCode(): void
    {
        $d = PlainDate::from(['year' => 5784, 'monthCode' => 'M05', 'day' => 5, 'calendar' => 'hebrew']);

        self::assertSame('M05', $d->monthCode);
        self::assertSame(5, $d->month);
    }

    public function testPlainDateFromHebrewLeapMonthCode(): void
    {
        // 5784 is a Hebrew leap year: (7*5784+1)%19 = 2 < 7
        $d = PlainDate::from(['year' => 5784, 'monthCode' => 'M05L', 'day' => 5, 'calendar' => 'hebrew']);

        self::assertSame('M05L', $d->monthCode);
        self::assertSame(6, $d->month);
    }

    public function testPlainDateFromHebrewLeapMonthCodeThrowsInNonLeapYear(): void
    {
        // 5783: (7*5783+1)%19 = 14, not < 7, so not a leap year
        $this->expectException(InvalidArgumentException::class);
        PlainDate::from(['year' => 5783, 'monthCode' => 'M05L', 'day' => 5, 'calendar' => 'hebrew']);
    }

    public function testPlainDateFromCopticM13(): void
    {
        $d = PlainDate::from(['year' => 1740, 'monthCode' => 'M13', 'day' => 5, 'calendar' => 'coptic']);

        self::assertSame(13, $d->month);
        self::assertSame('M13', $d->monthCode);
    }

    // -------------------------------------------------------------------------
    // PlainDate roundtrip: ISO -> calendar -> property bag -> ISO
    // -------------------------------------------------------------------------

    /**
     * @dataProvider roundtripCalendarProvider
     */
    public function testPlainDateRoundtrip(string $calendarId): void
    {
        $iso = PlainDate::from('2024-06-15');
        $cal = $iso->withCalendar($calendarId);

        $back = PlainDate::from([
            'year' => $cal->year,
            'month' => $cal->month,
            'day' => $cal->day,
            'calendar' => $calendarId,
        ]);

        self::assertSame($iso->isoYear, $back->isoYear, "isoYear roundtrip for {$calendarId}");
        self::assertSame($iso->isoMonth, $back->isoMonth, "isoMonth roundtrip for {$calendarId}");
        self::assertSame($iso->isoDay, $back->isoDay, "isoDay roundtrip for {$calendarId}");
    }

    /**
     * @dataProvider roundtripCalendarProvider
     */
    public function testPlainDateRoundtripWithMonthCode(string $calendarId): void
    {
        $iso = PlainDate::from('2024-06-15');
        $cal = $iso->withCalendar($calendarId);

        $back = PlainDate::from([
            'year' => $cal->year,
            'monthCode' => $cal->monthCode,
            'day' => $cal->day,
            'calendar' => $calendarId,
        ]);

        self::assertSame($iso->isoYear, $back->isoYear, "isoYear roundtrip for {$calendarId}");
        self::assertSame($iso->isoMonth, $back->isoMonth, "isoMonth roundtrip for {$calendarId}");
        self::assertSame($iso->isoDay, $back->isoDay, "isoDay roundtrip for {$calendarId}");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function roundtripCalendarProvider(): iterable
    {
        $calendars = [
            'buddhist',
            'coptic',
            'ethiopic',
            'ethioaa',
            'gregory',
            'hebrew',
            'indian',
            'islamic-civil',
            'islamic-tbla',
            'islamic-umalqura',
            'japanese',
            'persian',
            'roc',
            'chinese',
            'dangi',
        ];
        foreach ($calendars as $cal) {
            yield $cal => [$cal];
        }
    }

    // -------------------------------------------------------------------------
    // PlainDate::with() for non-ISO calendars
    // -------------------------------------------------------------------------

    public function testPlainDateWithDayOnHebrewCalendar(): void
    {
        $d = PlainDate::from('2024-01-15')->withCalendar('hebrew');

        $d2 = $d->with(['day' => 10]);

        self::assertSame(10, $d2->day);
        self::assertSame($d->year, $d2->year);
        self::assertSame($d->month, $d2->month);
    }

    public function testPlainDateWithMonthOnHebrewCalendar(): void
    {
        $d = PlainDate::from('2024-01-15')->withCalendar('hebrew');

        $d2 = $d->with(['month' => 6]);

        self::assertSame(6, $d2->month);
        self::assertSame($d->year, $d2->year);
    }

    public function testPlainDateWithMonthCodeOnHebrewCalendar(): void
    {
        $d = PlainDate::from('2024-01-15')->withCalendar('hebrew');

        $d2 = $d->with(['monthCode' => 'M06']);

        self::assertSame('M06', $d2->monthCode);
    }

    public function testPlainDateWithYearOnGregoryCalendar(): void
    {
        $d = PlainDate::from('2024-06-15')->withCalendar('gregory');

        $d2 = $d->with(['year' => 2020]);

        self::assertSame(2020, $d2->year);
        self::assertSame($d->month, $d2->month);
        self::assertSame($d->day, $d2->day);
    }

    // -------------------------------------------------------------------------
    // Era resolution in from()
    // -------------------------------------------------------------------------

    public function testPlainDateFromJapaneseWithEra(): void
    {
        $d = PlainDate::from([
            'calendar' => 'japanese',
            'era' => 'ce',
            'eraYear' => 1800,
            'month' => 6,
            'day' => 15,
        ]);

        self::assertSame(1800, $d->year);
        self::assertSame('ce', $d->era);
        self::assertSame(1800, $d->eraYear);
    }

    public function testPlainDateFromJapaneseBce(): void
    {
        $d = PlainDate::from([
            'calendar' => 'japanese',
            'era' => 'bce',
            'eraYear' => 100,
            'month' => 1,
            'day' => 1,
        ]);

        self::assertSame(-99, $d->year);
        self::assertSame('bce', $d->era);
        self::assertSame(100, $d->eraYear);
    }

    public function testPlainDateFromInvalidEraThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlainDate::from([
            'year' => 2025,
            'month' => 1,
            'day' => 1,
            'era' => 'xyz',
            'eraYear' => 2025,
            'calendar' => 'japanese',
        ]);
    }

    public function testPlainDateFromChineseIgnoresEra(): void
    {
        // Era should be silently ignored for Chinese calendar
        $d = PlainDate::from([
            'year' => 2025,
            'month' => 1,
            'day' => 1,
            'era' => 'xyz',
            'eraYear' => 2025,
            'calendar' => 'chinese',
        ]);

        self::assertInstanceOf(PlainDate::class, $d); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    // -------------------------------------------------------------------------
    // Era property values
    // -------------------------------------------------------------------------

    public function testEraValuesForAllCalendars(): void
    {
        $d = PlainDate::from('2024-01-15');

        self::assertSame('ce', $d->withCalendar('gregory')->era);
        self::assertSame('be', $d->withCalendar('buddhist')->era);
        self::assertSame('roc', $d->withCalendar('roc')->era);
        self::assertSame('am', $d->withCalendar('coptic')->era);
        self::assertSame('am', $d->withCalendar('hebrew')->era);
        self::assertSame('shaka', $d->withCalendar('indian')->era);
        self::assertSame('ah', $d->withCalendar('islamic-civil')->era);
        self::assertSame('ap', $d->withCalendar('persian')->era);
        self::assertSame('aa', $d->withCalendar('ethioaa')->era);
    }

    // -------------------------------------------------------------------------
    // PlainDateTime::from() with non-ISO calendars
    // -------------------------------------------------------------------------

    public function testPlainDateTimeFromHebrewPropertyBag(): void
    {
        $dt = PlainDateTime::from([
            'year' => 5784,
            'month' => 5,
            'day' => 5,
            'hour' => 10,
            'calendar' => 'hebrew',
        ]);

        self::assertSame('hebrew', $dt->calendarId);
        self::assertSame(5784, $dt->year);
        self::assertSame(5, $dt->month);
        self::assertSame(5, $dt->day);
        self::assertSame(10, $dt->hour);
    }

    public function testPlainDateTimeFromGregoryPropertyBag(): void
    {
        $dt = PlainDateTime::from([
            'year' => 2024,
            'month' => 1,
            'day' => 15,
            'hour' => 12,
            'minute' => 30,
            'calendar' => 'gregory',
        ]);

        self::assertSame('gregory', $dt->calendarId);
        self::assertSame(2024, $dt->isoYear);
        self::assertSame(12, $dt->hour);
    }

    // -------------------------------------------------------------------------
    // PlainDateTime::with() for non-ISO calendars
    // -------------------------------------------------------------------------

    public function testPlainDateTimeWithDayOnPersianCalendar(): void
    {
        $dt = PlainDateTime::from('2024-01-15T10:30:00')->withCalendar('persian');

        $dt2 = $dt->with(['day' => 1]);

        self::assertSame(1, $dt2->day);
        self::assertSame($dt->year, $dt2->year);
        self::assertSame($dt->month, $dt2->month);
        self::assertSame(10, $dt2->hour);
        self::assertSame(30, $dt2->minute);
    }

    // -------------------------------------------------------------------------
    // PlainYearMonth::from() with non-ISO calendars
    // -------------------------------------------------------------------------

    public function testPlainYearMonthFromHebrewPropertyBag(): void
    {
        $ym = PlainYearMonth::from(['year' => 5784, 'month' => 5, 'calendar' => 'hebrew']);

        self::assertSame('hebrew', $ym->calendarId);
        self::assertSame(5784, $ym->year);
        self::assertSame(5, $ym->month);
    }

    public function testPlainYearMonthFromBuddhistPropertyBag(): void
    {
        $ym = PlainYearMonth::from(['year' => 2567, 'month' => 1, 'calendar' => 'buddhist']);

        self::assertSame('buddhist', $ym->calendarId);
        self::assertSame(2024, $ym->isoYear);
    }

    // -------------------------------------------------------------------------
    // PlainYearMonth::with() for non-ISO calendars
    // -------------------------------------------------------------------------

    public function testPlainYearMonthWithOnHebrewCalendar(): void
    {
        $ym = PlainYearMonth::from('2024-01-01[u-ca=hebrew]');

        $ym2 = $ym->with(['month' => 6]);

        self::assertSame(6, $ym2->month);
        self::assertSame($ym->year, $ym2->year);
    }

    // -------------------------------------------------------------------------
    // PlainMonthDay::from() with non-ISO calendars
    // -------------------------------------------------------------------------

    public function testPlainMonthDayFromHebrewPropertyBag(): void
    {
        $md = PlainMonthDay::from([
            'year' => 5784,
            'monthCode' => 'M05',
            'day' => 15,
            'calendar' => 'hebrew',
        ]);

        self::assertSame('hebrew', $md->calendarId);
        self::assertSame('M05', $md->monthCode);
    }

    // -------------------------------------------------------------------------
    // Overflow handling
    // -------------------------------------------------------------------------

    public function testPlainDateFromConstrainsOverflowingDay(): void
    {
        // Hebrew month may have fewer days than 30
        $d = PlainDate::from([
            'year' => 5784,
            'month' => 1,
            'day' => 50,
            'calendar' => 'hebrew',
        ]);

        // Day should be constrained to the maximum for that month
        self::assertLessThanOrEqual(30, $d->day);
    }

    public function testPlainDateFromRejectsOverflowingDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlainDate::from([
            'year' => 5784,
            'month' => 1,
            'day' => 50,
            'calendar' => 'hebrew',
        ], ['overflow' => 'reject']);
    }

    // -------------------------------------------------------------------------
    // monthCodeToMonth
    // -------------------------------------------------------------------------

    public function testMonthCodeToMonthForStandardCalendar(): void
    {
        $cal = CalendarFactory::get('gregory');

        self::assertSame(1, $cal->monthCodeToMonth('M01', 2024));
        self::assertSame(12, $cal->monthCodeToMonth('M12', 2024));
    }

    public function testMonthCodeToMonthForCoptic13(): void
    {
        $cal = CalendarFactory::get('coptic');

        self::assertSame(13, $cal->monthCodeToMonth('M13', 1740));
    }

    public function testMonthCodeToMonthForHebrewLeap(): void
    {
        $cal = CalendarFactory::get('hebrew');

        // 5784 is a leap year
        self::assertSame(6, $cal->monthCodeToMonth('M05L', 5784));
    }

    public function testMonthCodeToMonthForHebrewLeapThrowsInNonLeapYear(): void
    {
        $cal = CalendarFactory::get('hebrew');

        $this->expectException(InvalidArgumentException::class);
        $cal->monthCodeToMonth('M05L', 5783);
    }

    public function testMonthCodeToMonthInvalidCodeThrows(): void
    {
        $cal = CalendarFactory::get('gregory');

        $this->expectException(InvalidArgumentException::class);
        $cal->monthCodeToMonth('M13', 2024);
    }

    // -------------------------------------------------------------------------
    // resolveEra
    // -------------------------------------------------------------------------

    public function testResolveEraGregory(): void
    {
        $cal = CalendarFactory::get('gregory');

        self::assertSame(2024, $cal->resolveEra('ce', 2024));
        self::assertSame(-99, $cal->resolveEra('bce', 100));
    }

    public function testResolveEraJapanese(): void
    {
        $cal = CalendarFactory::get('japanese');

        self::assertSame(2024, $cal->resolveEra('reiwa', 6));
        self::assertSame(2019, $cal->resolveEra('heisei', 31));
        self::assertSame(1800, $cal->resolveEra('ce', 1800));
        self::assertSame(-99, $cal->resolveEra('bce', 100));
    }

    public function testResolveEraChineseReturnsNull(): void
    {
        $cal = CalendarFactory::get('chinese');

        self::assertNull($cal->resolveEra('anything', 2024));
    }

    public function testResolveEraInvalidThrows(): void
    {
        $cal = CalendarFactory::get('gregory');

        $this->expectException(InvalidArgumentException::class);
        $cal->resolveEra('xyz', 2024);
    }

    public function testResolveEraIsoCalendarReturnsNull(): void
    {
        $cal = CalendarFactory::get('iso8601');

        self::assertNull($cal->resolveEra('anything', 2024));
    }
}
