<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\Spec\Duration;
use Temporal\Spec\PlainDate;
use Temporal\Spec\PlainDateTime;
use Temporal\Spec\PlainMonthDay;
use Temporal\Spec\PlainYearMonth;
use Temporal\Spec\ZonedDateTime;

/**
 * Verifies that all Temporal types accept known non-ISO calendar IDs
 * (ECMA-402 calendars) and reject unknown ones.
 */
final class NonIsoCalendarAcceptanceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // PlainDate
    // -------------------------------------------------------------------------

    public function testPlainDateConstructorAcceptsNonIsoCalendar(): void
    {
        $d = new PlainDate(2024, 1, 15, 'hebrew');

        self::assertSame('hebrew', $d->calendarId);
        self::assertSame(2024, $d->isoYear);
        self::assertSame(1, $d->isoMonth);
        self::assertSame(15, $d->isoDay);
    }

    public function testPlainDateConstructorRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown calendar');
        new PlainDate(2024, 1, 15, 'bogus');
    }

    public function testPlainDateFromStringExtractsCalendar(): void
    {
        $d = PlainDate::from('2024-01-15[u-ca=hebrew]');

        self::assertSame('hebrew', $d->calendarId);
        self::assertSame(2024, $d->isoYear);
    }

    public function testPlainDateFromStringRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown calendar');
        PlainDate::from('2024-01-15[u-ca=bogus]');
    }

    public function testPlainDateFromStringNoAnnotationDefaultsToIso(): void
    {
        $d = PlainDate::from('2024-01-15');

        self::assertSame('iso8601', $d->calendarId);
    }

    public function testPlainDateFromPropertyBagAcceptsNonIsoCalendar(): void
    {
        $d = PlainDate::from(['year' => 2024, 'month' => 1, 'day' => 15, 'calendar' => 'japanese']);

        self::assertSame('japanese', $d->calendarId);
    }

    public function testPlainDateFromPropertyBagRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlainDate::from(['year' => 2024, 'month' => 1, 'day' => 15, 'calendar' => 'bogus']);
    }

    public function testPlainDateWithCalendarAcceptsNonIso(): void
    {
        $d = PlainDate::from('2024-01-15');
        $d2 = $d->withCalendar('hebrew');

        self::assertSame('hebrew', $d2->calendarId);
        self::assertSame(2024, $d2->isoYear);
    }

    public function testPlainDateFromPreservesCalendarId(): void
    {
        $d1 = PlainDate::from('2024-01-15[u-ca=hebrew]');
        $d2 = PlainDate::from($d1);

        self::assertSame('hebrew', $d2->calendarId);
    }

    public function testPlainDateCalendarProjectedProperties(): void
    {
        $d = new PlainDate(2024, 1, 15, 'hebrew');

        // Hebrew year for Jan 15 2024 should be in the 5784 range
        self::assertSame(5784, $d->year);
        // monthCode should be valid
        self::assertNotEmpty($d->monthCode);
    }

    public function testPlainDateToStringIncludesNonIsoCalendar(): void
    {
        $d = new PlainDate(2024, 1, 15, 'hebrew');

        self::assertSame('2024-01-15[u-ca=hebrew]', $d->toString());
    }

    public function testPlainDateToStringOmitsIsoCalendar(): void
    {
        $d = new PlainDate(2024, 1, 15);

        self::assertSame('2024-01-15', $d->toString());
    }

    public function testPlainDateConstructorCanonicalizesCalendar(): void
    {
        $d = new PlainDate(2024, 1, 15, 'HEBREW');

        self::assertSame('hebrew', $d->calendarId);
    }

    public function testPlainDateConstructorResolvesAlias(): void
    {
        $d = new PlainDate(2024, 1, 15, 'islamicc');

        self::assertSame('islamic-civil', $d->calendarId);
    }

    // -------------------------------------------------------------------------
    // PlainDateTime
    // -------------------------------------------------------------------------

    public function testPlainDateTimeConstructorAcceptsNonIsoCalendar(): void
    {
        $dt = new PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'japanese');

        self::assertSame('japanese', $dt->calendarId);
    }

    public function testPlainDateTimeConstructorRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'bogus');
    }

    public function testPlainDateTimeFromStringExtractsCalendar(): void
    {
        $dt = PlainDateTime::from('2024-01-15T10:30:00[u-ca=buddhist]');

        self::assertSame('buddhist', $dt->calendarId);
    }

    public function testPlainDateTimeFromPropertyBagAcceptsCalendar(): void
    {
        $dt = PlainDateTime::from([
            'year' => 2024,
            'month' => 1,
            'day' => 15,
            'hour' => 10,
            'calendar' => 'gregory',
        ]);

        self::assertSame('gregory', $dt->calendarId);
    }

    public function testPlainDateTimeWithCalendarAcceptsNonIso(): void
    {
        $dt = PlainDateTime::from('2024-01-15T10:30:00');
        $dt2 = $dt->withCalendar('persian');

        self::assertSame('persian', $dt2->calendarId);
    }

    public function testPlainDateTimeFromPreservesCalendarId(): void
    {
        $dt1 = PlainDateTime::from('2024-01-15T10:30:00[u-ca=roc]');
        $dt2 = PlainDateTime::from($dt1);

        self::assertSame('roc', $dt2->calendarId);
    }

    // -------------------------------------------------------------------------
    // PlainMonthDay
    // -------------------------------------------------------------------------

    public function testPlainMonthDayConstructorAcceptsNonIsoCalendar(): void
    {
        $md = new PlainMonthDay(3, 15, 'coptic');

        self::assertSame('coptic', $md->calendarId);
    }

    public function testPlainMonthDayConstructorRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainMonthDay(3, 15, 'bogus');
    }

    public function testPlainMonthDayFromStringExtractsCalendar(): void
    {
        $md = PlainMonthDay::from('03-15[u-ca=chinese]');

        self::assertSame('chinese', $md->calendarId);
    }

    public function testPlainMonthDayFromPropertyBagAcceptsCalendar(): void
    {
        $md = PlainMonthDay::from(['month' => 3, 'day' => 15, 'calendar' => 'dangi']);

        self::assertSame('dangi', $md->calendarId);
    }

    public function testPlainMonthDayFromPreservesCalendarId(): void
    {
        $md1 = PlainMonthDay::from('03-15[u-ca=indian]');
        $md2 = PlainMonthDay::from($md1);

        self::assertSame('indian', $md2->calendarId);
    }

    // -------------------------------------------------------------------------
    // PlainYearMonth
    // -------------------------------------------------------------------------

    public function testPlainYearMonthConstructorAcceptsNonIsoCalendar(): void
    {
        $ym = new PlainYearMonth(2024, 6, 'ethiopic');

        self::assertSame('ethiopic', $ym->calendarId);
    }

    public function testPlainYearMonthConstructorRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainYearMonth(2024, 6, 'bogus');
    }

    public function testPlainYearMonthFromStringExtractsCalendar(): void
    {
        $ym = PlainYearMonth::from('2024-06[u-ca=islamic]');

        self::assertSame('islamic', $ym->calendarId);
    }

    public function testPlainYearMonthFromPropertyBagAcceptsCalendar(): void
    {
        $ym = PlainYearMonth::from(['year' => 2024, 'month' => 6, 'calendar' => 'persian']);

        self::assertSame('persian', $ym->calendarId);
    }

    public function testPlainYearMonthFromPreservesCalendarId(): void
    {
        $ym1 = PlainYearMonth::from('2024-06[u-ca=roc]');
        $ym2 = PlainYearMonth::from($ym1);

        self::assertSame('roc', $ym2->calendarId);
    }

    // -------------------------------------------------------------------------
    // ZonedDateTime
    // -------------------------------------------------------------------------

    public function testZonedDateTimeConstructorAcceptsNonIsoCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'gregory');

        self::assertSame('gregory', $zdt->calendarId);
    }

    public function testZonedDateTimeConstructorRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZonedDateTime(0, 'UTC', 'bogus');
    }

    public function testZonedDateTimeFromStringExtractsCalendar(): void
    {
        $zdt = ZonedDateTime::from('2024-01-15T10:30:00+00:00[UTC][u-ca=hebrew]');

        self::assertSame('hebrew', $zdt->calendarId);
    }

    public function testZonedDateTimeFromStringRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZonedDateTime::from('2024-01-15T10:30:00+00:00[UTC][u-ca=bogus]');
    }

    public function testZonedDateTimeWithCalendarAcceptsNonIso(): void
    {
        $zdt = ZonedDateTime::from('2024-01-15T10:30:00+00:00[UTC]');
        $zdt2 = $zdt->withCalendar('japanese');

        self::assertSame('japanese', $zdt2->calendarId);
    }

    public function testZonedDateTimeFromPropertyBagAcceptsCalendar(): void
    {
        $zdt = ZonedDateTime::from([
            'year' => 2024,
            'month' => 1,
            'day' => 15,
            'timeZone' => 'UTC',
            'calendar' => 'buddhist',
        ]);

        self::assertSame('buddhist', $zdt->calendarId);
    }

    // -------------------------------------------------------------------------
    // Duration relativeTo
    // -------------------------------------------------------------------------

    public function testDurationRelativeToStringAcceptsNonIsoCalendar(): void
    {
        $d = Duration::from('P1Y');

        // Should not throw
        $result = $d->total(['unit' => 'days', 'relativeTo' => '2024-01-15[u-ca=hebrew]']);

        self::assertEquals(366, $result);
    }

    public function testDurationRelativeToStringRejectsUnknownCalendar(): void
    {
        $d = Duration::from('P1Y');

        $this->expectException(InvalidArgumentException::class);
        $d->total(['unit' => 'days', 'relativeTo' => '2024-01-15[u-ca=bogus]']);
    }

    public function testDurationRelativeToPropertyBagAcceptsNonIsoCalendar(): void
    {
        $d = Duration::from('P1Y');

        // Should not throw
        $result = $d->total([
            'unit' => 'days',
            'relativeTo' => ['year' => 2024, 'month' => 1, 'day' => 15, 'calendar' => 'hebrew'],
        ]);

        self::assertEquals(366, $result);
    }

    public function testDurationRelativeToPropertyBagRejectsUnknownCalendar(): void
    {
        $d = Duration::from('P1Y');

        $this->expectException(InvalidArgumentException::class);
        $d->total([
            'unit' => 'days',
            'relativeTo' => ['year' => 2024, 'month' => 1, 'day' => 15, 'calendar' => 'bogus'],
        ]);
    }

    // -------------------------------------------------------------------------
    // CalendarMath::validateAnnotations
    // -------------------------------------------------------------------------

    public function testValidateAnnotationsReturnsCalendarId(): void
    {
        $result = \Temporal\Spec\Internal\CalendarMath::validateAnnotations(
            '[u-ca=hebrew]',
            'test',
        );

        self::assertSame('hebrew', $result);
    }

    public function testValidateAnnotationsReturnsNullWhenNoCalendar(): void
    {
        $result = \Temporal\Spec\Internal\CalendarMath::validateAnnotations(
            '[America/New_York]',
            'test',
        );

        self::assertNull($result);
    }

    public function testValidateAnnotationsReturnsNullForEmptySection(): void
    {
        $result = \Temporal\Spec\Internal\CalendarMath::validateAnnotations('', 'test');

        self::assertNull($result);
    }

    public function testValidateAnnotationsRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown calendar');
        \Temporal\Spec\Internal\CalendarMath::validateAnnotations(
            '[u-ca=bogus]',
            'test',
        );
    }

    public function testValidateAnnotationsSkipsCalendarCheckWhenDisabled(): void
    {
        // With checkCalendar=false, unknown calendars should be silently ignored
        $result = \Temporal\Spec\Internal\CalendarMath::validateAnnotations(
            '[u-ca=anything]',
            'test',
            false,
        );

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // extractCalendarFromString
    // -------------------------------------------------------------------------

    public function testExtractCalendarFromStringAcceptsKnownCalendar(): void
    {
        $result = ZonedDateTime::extractCalendarFromString('hebrew');

        self::assertSame('hebrew', $result);
    }

    public function testExtractCalendarFromStringAcceptsAnnotation(): void
    {
        $result = ZonedDateTime::extractCalendarFromString('2024-01-15[u-ca=japanese]');

        self::assertSame('japanese', $result);
    }

    public function testExtractCalendarFromStringRejectsUnknownCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZonedDateTime::extractCalendarFromString('nonsense');
    }

    // -------------------------------------------------------------------------
    // All known calendars accepted
    // -------------------------------------------------------------------------

    /**
     * @dataProvider knownCalendarProvider
     */
    public function testAllKnownCalendarsAcceptedByConstructor(string $calendarId): void
    {
        $d = new PlainDate(2024, 1, 15, $calendarId);

        self::assertSame($calendarId, $d->calendarId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function knownCalendarProvider(): iterable
    {
        $calendars = [
            'iso8601',
            'buddhist',
            'chinese',
            'coptic',
            'dangi',
            'ethioaa',
            'ethiopic',
            'gregory',
            'hebrew',
            'indian',
            'islamic',
            'islamic-civil',
            'islamic-rgsa',
            'islamic-tbla',
            'islamic-umalqura',
            'japanese',
            'persian',
            'roc',
        ];

        foreach ($calendars as $cal) {
            yield $cal => [$cal];
        }
    }
}
