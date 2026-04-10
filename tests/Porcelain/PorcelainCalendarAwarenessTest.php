<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use PHPUnit\Framework\TestCase;
use Temporal\CalendarDisplay;
use Temporal\Duration;
use Temporal\PlainDate;
use Temporal\PlainDateTime;
use Temporal\PlainMonthDay;
use Temporal\PlainYearMonth;
use Temporal\Unit;
use Temporal\ZonedDateTime;

/**
 * Tests that the porcelain layer correctly preserves and exposes calendar IDs
 * and that fromSpec() round-trips non-ISO calendars without corruption.
 */
final class PorcelainCalendarAwarenessTest extends TestCase
{
    // =========================================================================
    // PlainDate
    // =========================================================================

    public function testPlainDateConstructorAcceptsCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        self::assertSame('hebrew', $pd->calendarId);
    }

    public function testPlainDateConstructorDefaultsToIso(): void
    {
        $pd = new PlainDate(2024, 1, 15);

        self::assertSame('iso8601', $pd->calendarId);
    }

    public function testPlainDateYearMonthDayAreCalendarProjected(): void
    {
        // 2024-01-15 ISO corresponds to Hebrew year 5784
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        self::assertSame(5784, $pd->year);
        self::assertNotSame(2024, $pd->year);
    }

    public function testPlainDateIsoYearMonthDayRoundTrip(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        $spec = $pd->toSpec();
        self::assertSame(2024, $spec->isoYear);
        self::assertSame(1, $spec->isoMonth);
        self::assertSame(15, $spec->isoDay);
        self::assertSame('hebrew', $spec->calendarId);
    }

    public function testPlainDateFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainDate(2024, 1, 15, 'hebrew');
        $pd = PlainDate::fromSpec($spec);

        self::assertSame('hebrew', $pd->calendarId);
        // The porcelain should round-trip ISO fields faithfully
        self::assertSame(2024, $pd->toSpec()->isoYear);
        self::assertSame(1, $pd->toSpec()->isoMonth);
        self::assertSame(15, $pd->toSpec()->isoDay);
    }

    public function testPlainDateFromSpecDoesNotCorruptNonIsoCalendar(): void
    {
        // Create a spec PlainDate with hebrew calendar
        $spec1 = new \Temporal\Spec\PlainDate(2024, 1, 15, 'hebrew');
        $pd = PlainDate::fromSpec($spec1);

        // Round-trip: fromSpec -> toSpec -> fromSpec
        $spec2 = $pd->toSpec();
        $pd2 = PlainDate::fromSpec($spec2);

        // All ISO fields must be identical after round-trip
        self::assertSame($spec1->isoYear, $pd2->toSpec()->isoYear);
        self::assertSame($spec1->isoMonth, $pd2->toSpec()->isoMonth);
        self::assertSame($spec1->isoDay, $pd2->toSpec()->isoDay);
        self::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateParsePreservesCalendar(): void
    {
        $pd = PlainDate::parse('2024-01-15[u-ca=hebrew]');

        self::assertSame('hebrew', $pd->calendarId);
        self::assertSame(5784, $pd->year);
    }

    public function testPlainDateToStringIncludesNonIsoCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        self::assertStringContainsString('[u-ca=hebrew]', $pd->toString());
    }

    public function testPlainDateWithPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        // $pd->day is the Hebrew day. Use with() to change the calendar day.
        $pd2 = $pd->with(day: 1);

        self::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateAddPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pd2 = $pd->add(new Duration(days: 10));

        self::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateSubtractPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pd2 = $pd->subtract(new Duration(days: 10));

        self::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateToPlainDateTimePreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pdt = $pd->toPlainDateTime();

        self::assertSame('hebrew', $pdt->calendarId);
    }

    public function testPlainDateToPlainYearMonthPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pym = $pd->toPlainYearMonth();

        self::assertSame('hebrew', $pym->calendarId);
    }

    public function testPlainDateToPlainMonthDayPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pmd = $pd->toPlainMonthDay();

        self::assertSame('hebrew', $pmd->calendarId);
    }

    // =========================================================================
    // PlainDateTime
    // =========================================================================

    public function testPlainDateTimeConstructorAcceptsCalendar(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'japanese');

        self::assertSame('japanese', $pdt->calendarId);
    }

    public function testPlainDateTimeConstructorDefaultsToIso(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15);

        self::assertSame('iso8601', $pdt->calendarId);
    }

    public function testPlainDateTimeYearMonthDayAreCalendarProjected(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'hebrew');

        self::assertSame(5784, $pdt->year);
    }

    public function testPlainDateTimeTimeFieldsDelegateCorrectly(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 30, 45, 100, 200, 300, 'hebrew');

        self::assertSame(10, $pdt->hour);
        self::assertSame(30, $pdt->minute);
        self::assertSame(45, $pdt->second);
        self::assertSame(100, $pdt->millisecond);
        self::assertSame(200, $pdt->microsecond);
        self::assertSame(300, $pdt->nanosecond);
    }

    public function testPlainDateTimeFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'japanese');
        $pdt = PlainDateTime::fromSpec($spec);

        self::assertSame('japanese', $pdt->calendarId);
        self::assertSame(2024, $pdt->toSpec()->isoYear);
        self::assertSame(1, $pdt->toSpec()->isoMonth);
        self::assertSame(15, $pdt->toSpec()->isoDay);
    }

    public function testPlainDateTimeFromSpecDoesNotCorruptNonIso(): void
    {
        $spec1 = new \Temporal\Spec\PlainDateTime(2024, 1, 15, 12, 0, 0, 0, 0, 0, 'persian');
        $pdt = PlainDateTime::fromSpec($spec1);

        // Round-trip
        $spec2 = $pdt->toSpec();
        $pdt2 = PlainDateTime::fromSpec($spec2);

        self::assertSame($spec1->isoYear, $pdt2->toSpec()->isoYear);
        self::assertSame($spec1->isoMonth, $pdt2->toSpec()->isoMonth);
        self::assertSame($spec1->isoDay, $pdt2->toSpec()->isoDay);
        self::assertSame('persian', $pdt2->calendarId);
    }

    public function testPlainDateTimeParsePreservesCalendar(): void
    {
        $pdt = PlainDateTime::parse('2024-01-15T10:30:00[u-ca=buddhist]');

        self::assertSame('buddhist', $pdt->calendarId);
    }

    public function testPlainDateTimeAddPreservesCalendar(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 0, 0, 0, 0, 0, 'gregory');
        $pdt2 = $pdt->add(new Duration(days: 5));

        self::assertSame('gregory', $pdt2->calendarId);
    }

    public function testPlainDateTimeToPlainDatePreservesCalendar(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 0, 0, 0, 0, 0, 'hebrew');
        $pd = $pdt->toPlainDate();

        self::assertSame('hebrew', $pd->calendarId);
    }

    // =========================================================================
    // PlainYearMonth
    // =========================================================================

    public function testPlainYearMonthConstructorAcceptsCalendar(): void
    {
        $pym = new PlainYearMonth(2024, 6, 'buddhist');

        self::assertSame('buddhist', $pym->calendarId);
    }

    public function testPlainYearMonthConstructorDefaultsToIso(): void
    {
        $pym = new PlainYearMonth(2024, 6);

        self::assertSame('iso8601', $pym->calendarId);
    }

    public function testPlainYearMonthYearMonthAreCalendarProjected(): void
    {
        // Buddhist year = ISO year + 543
        $pym = new PlainYearMonth(2024, 6, 'buddhist');

        self::assertSame(2567, $pym->year);
    }

    public function testPlainYearMonthFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainYearMonth(2024, 6, 'buddhist');
        $pym = PlainYearMonth::fromSpec($spec);

        self::assertSame('buddhist', $pym->calendarId);
        self::assertSame(2024, $pym->toSpec()->isoYear);
        self::assertSame(6, $pym->toSpec()->isoMonth);
    }

    public function testPlainYearMonthFromSpecPreservesReferenceDay(): void
    {
        $spec = new \Temporal\Spec\PlainYearMonth(2024, 3, 'iso8601', 15);
        $pym = PlainYearMonth::fromSpec($spec);

        self::assertSame(15, $pym->toSpec()->referenceISODay);
    }

    public function testPlainYearMonthFromSpecDoesNotCorruptNonIso(): void
    {
        $spec1 = new \Temporal\Spec\PlainYearMonth(2024, 6, 'gregory');
        $pym = PlainYearMonth::fromSpec($spec1);
        $spec2 = $pym->toSpec();
        $pym2 = PlainYearMonth::fromSpec($spec2);

        self::assertSame($spec1->isoYear, $pym2->toSpec()->isoYear);
        self::assertSame($spec1->isoMonth, $pym2->toSpec()->isoMonth);
        self::assertSame('gregory', $pym2->calendarId);
    }

    public function testPlainYearMonthParsePreservesCalendar(): void
    {
        $pym = PlainYearMonth::parse('2024-06-01[u-ca=japanese]');

        self::assertSame('japanese', $pym->calendarId);
    }

    public function testPlainYearMonthAddPreservesCalendar(): void
    {
        $pym = new PlainYearMonth(2024, 6, 'buddhist');
        $pym2 = $pym->add(new Duration(months: 2));

        self::assertSame('buddhist', $pym2->calendarId);
    }

    // =========================================================================
    // PlainMonthDay
    // =========================================================================

    public function testPlainMonthDayConstructorAcceptsCalendar(): void
    {
        $pmd = new PlainMonthDay(3, 15, 'coptic');

        self::assertSame('coptic', $pmd->calendarId);
    }

    public function testPlainMonthDayConstructorDefaultsToIso(): void
    {
        $pmd = new PlainMonthDay(3, 15);

        self::assertSame('iso8601', $pmd->calendarId);
    }

    public function testPlainMonthDayFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainMonthDay(3, 15, 'coptic');
        $pmd = PlainMonthDay::fromSpec($spec);

        self::assertSame('coptic', $pmd->calendarId);
        self::assertSame(3, $pmd->toSpec()->isoMonth);
        self::assertSame(15, $pmd->toSpec()->isoDay);
    }

    public function testPlainMonthDayFromSpecPreservesReferenceYear(): void
    {
        $spec = new \Temporal\Spec\PlainMonthDay(6, 15, 'iso8601', 2000);
        $pmd = PlainMonthDay::fromSpec($spec);

        self::assertSame(2000, $pmd->toSpec()->referenceISOYear);
    }

    public function testPlainMonthDayFromSpecDoesNotCorruptNonIso(): void
    {
        $spec1 = new \Temporal\Spec\PlainMonthDay(3, 15, 'indian');
        $pmd = PlainMonthDay::fromSpec($spec1);
        $spec2 = $pmd->toSpec();
        $pmd2 = PlainMonthDay::fromSpec($spec2);

        self::assertSame($spec1->isoMonth, $pmd2->toSpec()->isoMonth);
        self::assertSame($spec1->isoDay, $pmd2->toSpec()->isoDay);
        self::assertSame('indian', $pmd2->calendarId);
    }

    public function testPlainMonthDayParsePreservesCalendar(): void
    {
        $pmd = PlainMonthDay::parse('1972-03-15[u-ca=chinese]');

        self::assertSame('chinese', $pmd->calendarId);
    }

    // =========================================================================
    // ZonedDateTime
    // =========================================================================

    public function testZonedDateTimeConstructorAcceptsCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'hebrew');

        self::assertSame('hebrew', $zdt->calendarId);
    }

    public function testZonedDateTimeConstructorDefaultsToIso(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        self::assertSame('iso8601', $zdt->calendarId);
    }

    public function testZonedDateTimeFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\ZonedDateTime(0, 'UTC', 'gregory');
        $zdt = ZonedDateTime::fromSpec($spec);

        self::assertSame('gregory', $zdt->calendarId);
    }

    public function testZonedDateTimeFromSpecDoesNotCorruptCalendar(): void
    {
        $spec1 = new \Temporal\Spec\ZonedDateTime(0, 'UTC', 'japanese');
        $zdt = ZonedDateTime::fromSpec($spec1);
        $spec2 = $zdt->toSpec();
        $zdt2 = ZonedDateTime::fromSpec($spec2);

        self::assertSame($spec1->epochNanoseconds, $zdt2->epochNanoseconds);
        self::assertSame('japanese', $zdt2->calendarId);
    }

    public function testZonedDateTimeParsePreservesCalendar(): void
    {
        $zdt = ZonedDateTime::parse('2024-01-15T10:30:00+00:00[UTC][u-ca=hebrew]');

        self::assertSame('hebrew', $zdt->calendarId);
    }

    public function testZonedDateTimeAddPreservesCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'buddhist');
        $zdt2 = $zdt->add(new Duration(days: 5));

        self::assertSame('buddhist', $zdt2->calendarId);
    }

    public function testZonedDateTimeToPlainDatePreservesCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'hebrew');
        $pd = $zdt->toPlainDate();

        self::assertSame('hebrew', $pd->calendarId);
    }

    public function testZonedDateTimeToPlainDateTimePreservesCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'japanese');
        $pdt = $zdt->toPlainDateTime();

        self::assertSame('japanese', $pdt->calendarId);
    }

    // =========================================================================
    // Duration relativeTo with non-ISO calendar
    // =========================================================================

    public function testDurationCompareWithNonIsoRelativeTo(): void
    {
        $d1 = new Duration(months: 1);
        $d2 = new Duration(days: 30);
        $rt = new PlainDate(2024, 1, 15, 'hebrew');

        // Should not throw; should compare correctly. The actual ordering depends
        // on the Hebrew calendar month length at the reference date; we only
        // require a valid comparison result (-1, 0, or 1).
        $result = Duration::compare($d1, $d2, $rt);

        self::assertContains($result, [-1, 0, 1]);
    }

    public function testDurationTotalWithNonIsoRelativeTo(): void
    {
        $d = new Duration(years: 1);
        $rt = new PlainDate(2024, 1, 15, 'hebrew');

        // 2024 is a leap year, so 1 year from Jan 15 should be 366 days
        $result = $d->total(Unit::Day, $rt);

        self::assertEquals(366, $result);
    }

    public function testDurationRoundWithNonIsoRelativeTo(): void
    {
        $d = new Duration(months: 15);
        $rt = new PlainDate(2024, 1, 15, 'buddhist');

        $rounded = $d->round(smallestUnit: Unit::Year, relativeTo: $rt);

        self::assertSame(1, $rounded->years);
    }

    // =========================================================================
    // ISO calendar backward compatibility
    // =========================================================================

    public function testPlainDateIsoBackwardCompatibility(): void
    {
        $pd = new PlainDate(2024, 6, 15);

        // Same values for ISO calendar
        self::assertSame(2024, $pd->year);
        self::assertSame(6, $pd->month);
        self::assertSame(15, $pd->day);
        self::assertSame('iso8601', $pd->calendarId);
    }

    public function testPlainDateTimeIsoBackwardCompatibility(): void
    {
        $pdt = new PlainDateTime(2024, 6, 15, 10, 30, 45, 100, 200, 300);

        self::assertSame(2024, $pdt->year);
        self::assertSame(6, $pdt->month);
        self::assertSame(15, $pdt->day);
        self::assertSame(10, $pdt->hour);
        self::assertSame(30, $pdt->minute);
        self::assertSame(45, $pdt->second);
        self::assertSame(100, $pdt->millisecond);
        self::assertSame(200, $pdt->microsecond);
        self::assertSame(300, $pdt->nanosecond);
        self::assertSame('iso8601', $pdt->calendarId);
    }

    public function testPlainYearMonthIsoBackwardCompatibility(): void
    {
        $pym = new PlainYearMonth(2024, 6);

        self::assertSame(2024, $pym->year);
        self::assertSame(6, $pym->month);
        self::assertSame('iso8601', $pym->calendarId);
    }

    public function testPlainMonthDayIsoBackwardCompatibility(): void
    {
        $pmd = new PlainMonthDay(3, 15);

        self::assertSame(3, $pmd->month);
        self::assertSame(15, $pmd->day);
        self::assertSame('iso8601', $pmd->calendarId);
    }

    public function testZonedDateTimeIsoBackwardCompatibility(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        self::assertSame('iso8601', $zdt->calendarId);
        self::assertSame(0, $zdt->epochNanoseconds);
    }

    // =========================================================================
    // Cross-type conversions preserve calendar
    // =========================================================================

    public function testPlainDateToZonedDateTimePreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'buddhist');
        $zdt = $pd->toZonedDateTime('UTC');

        self::assertSame('buddhist', $zdt->calendarId);
    }

    // =========================================================================
    // ext-intl composer.json requirement
    // =========================================================================

    public function testIntlExtensionIsLoaded(): void
    {
        self::assertTrue(extension_loaded('intl'), 'ext-intl must be loaded');
    }
}
