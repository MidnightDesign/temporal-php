<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use PHPUnit\Framework\TestCase;
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

        static::assertSame('hebrew', $pd->calendarId);
    }

    public function testPlainDateConstructorDefaultsToIso(): void
    {
        $pd = new PlainDate(2024, 1, 15);

        static::assertSame('iso8601', $pd->calendarId);
    }

    public function testPlainDateYearMonthDayAreCalendarProjected(): void
    {
        // 2024-01-15 ISO corresponds to Hebrew year 5784
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        static::assertSame(5784, $pd->year);
        static::assertNotSame(2024, $pd->year);
    }

    public function testPlainDateIsoYearMonthDayRoundTrip(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        $spec = $pd->toSpec();
        static::assertSame(2024, $spec->isoYear);
        static::assertSame(1, $spec->isoMonth);
        static::assertSame(15, $spec->isoDay);
        static::assertSame('hebrew', $spec->calendarId);
    }

    public function testPlainDateFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainDate(2024, 1, 15, 'hebrew');
        $pd = PlainDate::fromSpec($spec);

        static::assertSame('hebrew', $pd->calendarId);
        // The porcelain should round-trip ISO fields faithfully
        static::assertSame(2024, $pd->toSpec()->isoYear);
        static::assertSame(1, $pd->toSpec()->isoMonth);
        static::assertSame(15, $pd->toSpec()->isoDay);
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
        static::assertSame($spec1->isoYear, $pd2->toSpec()->isoYear);
        static::assertSame($spec1->isoMonth, $pd2->toSpec()->isoMonth);
        static::assertSame($spec1->isoDay, $pd2->toSpec()->isoDay);
        static::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateParsePreservesCalendar(): void
    {
        $pd = PlainDate::parse('2024-01-15[u-ca=hebrew]');

        static::assertSame('hebrew', $pd->calendarId);
        static::assertSame(5784, $pd->year);
    }

    public function testPlainDateToStringIncludesNonIsoCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        static::assertStringContainsString('[u-ca=hebrew]', $pd->toString());
    }

    public function testPlainDateWithPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');

        // $pd->day is the Hebrew day. Use with() to change the calendar day.
        $pd2 = $pd->with(day: 1);

        static::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateAddPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pd2 = $pd->add(new Duration(days: 10));

        static::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateSubtractPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pd2 = $pd->subtract(new Duration(days: 10));

        static::assertSame('hebrew', $pd2->calendarId);
    }

    public function testPlainDateToPlainDateTimePreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pdt = $pd->toPlainDateTime();

        static::assertSame('hebrew', $pdt->calendarId);
    }

    public function testPlainDateToPlainYearMonthPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pym = $pd->toPlainYearMonth();

        static::assertSame('hebrew', $pym->calendarId);
    }

    public function testPlainDateToPlainMonthDayPreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'hebrew');
        $pmd = $pd->toPlainMonthDay();

        static::assertSame('hebrew', $pmd->calendarId);
    }

    // =========================================================================
    // PlainDateTime
    // =========================================================================

    public function testPlainDateTimeConstructorAcceptsCalendar(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'japanese');

        static::assertSame('japanese', $pdt->calendarId);
    }

    public function testPlainDateTimeConstructorDefaultsToIso(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15);

        static::assertSame('iso8601', $pdt->calendarId);
    }

    public function testPlainDateTimeYearMonthDayAreCalendarProjected(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'hebrew');

        static::assertSame(5784, $pdt->year);
    }

    public function testPlainDateTimeTimeFieldsDelegateCorrectly(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 30, 45, 100, 200, 300, 'hebrew');

        static::assertSame(10, $pdt->hour);
        static::assertSame(30, $pdt->minute);
        static::assertSame(45, $pdt->second);
        static::assertSame(100, $pdt->millisecond);
        static::assertSame(200, $pdt->microsecond);
        static::assertSame(300, $pdt->nanosecond);
    }

    public function testPlainDateTimeFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainDateTime(2024, 1, 15, 10, 30, 0, 0, 0, 0, 'japanese');
        $pdt = PlainDateTime::fromSpec($spec);

        static::assertSame('japanese', $pdt->calendarId);
        static::assertSame(2024, $pdt->toSpec()->isoYear);
        static::assertSame(1, $pdt->toSpec()->isoMonth);
        static::assertSame(15, $pdt->toSpec()->isoDay);
    }

    public function testPlainDateTimeFromSpecDoesNotCorruptNonIso(): void
    {
        $spec1 = new \Temporal\Spec\PlainDateTime(2024, 1, 15, 12, 0, 0, 0, 0, 0, 'persian');
        $pdt = PlainDateTime::fromSpec($spec1);

        // Round-trip
        $spec2 = $pdt->toSpec();
        $pdt2 = PlainDateTime::fromSpec($spec2);

        static::assertSame($spec1->isoYear, $pdt2->toSpec()->isoYear);
        static::assertSame($spec1->isoMonth, $pdt2->toSpec()->isoMonth);
        static::assertSame($spec1->isoDay, $pdt2->toSpec()->isoDay);
        static::assertSame('persian', $pdt2->calendarId);
    }

    public function testPlainDateTimeParsePreservesCalendar(): void
    {
        $pdt = PlainDateTime::parse('2024-01-15T10:30:00[u-ca=buddhist]');

        static::assertSame('buddhist', $pdt->calendarId);
    }

    public function testPlainDateTimeAddPreservesCalendar(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 0, 0, 0, 0, 0, 'gregory');
        $pdt2 = $pdt->add(new Duration(days: 5));

        static::assertSame('gregory', $pdt2->calendarId);
    }

    public function testPlainDateTimeToPlainDatePreservesCalendar(): void
    {
        $pdt = new PlainDateTime(2024, 1, 15, 10, 0, 0, 0, 0, 0, 'hebrew');
        $pd = $pdt->toPlainDate();

        static::assertSame('hebrew', $pd->calendarId);
    }

    // =========================================================================
    // PlainYearMonth
    // =========================================================================

    public function testPlainYearMonthConstructorAcceptsCalendar(): void
    {
        $pym = new PlainYearMonth(2024, 6, 'buddhist');

        static::assertSame('buddhist', $pym->calendarId);
    }

    public function testPlainYearMonthConstructorDefaultsToIso(): void
    {
        $pym = new PlainYearMonth(2024, 6);

        static::assertSame('iso8601', $pym->calendarId);
    }

    public function testPlainYearMonthYearMonthAreCalendarProjected(): void
    {
        // Buddhist year = ISO year + 543
        $pym = new PlainYearMonth(2024, 6, 'buddhist');

        static::assertSame(2567, $pym->year);
    }

    public function testPlainYearMonthFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainYearMonth(2024, 6, 'buddhist');
        $pym = PlainYearMonth::fromSpec($spec);

        static::assertSame('buddhist', $pym->calendarId);
        static::assertSame(2024, $pym->toSpec()->isoYear);
        static::assertSame(6, $pym->toSpec()->isoMonth);
    }

    public function testPlainYearMonthFromSpecPreservesReferenceDay(): void
    {
        $spec = new \Temporal\Spec\PlainYearMonth(2024, 3, 'iso8601', 15);
        $pym = PlainYearMonth::fromSpec($spec);

        static::assertSame(15, $pym->toSpec()->referenceISODay);
    }

    public function testPlainYearMonthFromSpecDoesNotCorruptNonIso(): void
    {
        $spec1 = new \Temporal\Spec\PlainYearMonth(2024, 6, 'gregory');
        $pym = PlainYearMonth::fromSpec($spec1);
        $spec2 = $pym->toSpec();
        $pym2 = PlainYearMonth::fromSpec($spec2);

        static::assertSame($spec1->isoYear, $pym2->toSpec()->isoYear);
        static::assertSame($spec1->isoMonth, $pym2->toSpec()->isoMonth);
        static::assertSame('gregory', $pym2->calendarId);
    }

    public function testPlainYearMonthParsePreservesCalendar(): void
    {
        $pym = PlainYearMonth::parse('2024-06-01[u-ca=japanese]');

        static::assertSame('japanese', $pym->calendarId);
    }

    public function testPlainYearMonthAddPreservesCalendar(): void
    {
        $pym = new PlainYearMonth(2024, 6, 'buddhist');
        $pym2 = $pym->add(new Duration(months: 2));

        static::assertSame('buddhist', $pym2->calendarId);
    }

    // =========================================================================
    // PlainMonthDay
    // =========================================================================

    public function testPlainMonthDayConstructorAcceptsCalendar(): void
    {
        $pmd = new PlainMonthDay(3, 15, 'coptic');

        static::assertSame('coptic', $pmd->calendarId);
    }

    public function testPlainMonthDayConstructorDefaultsToIso(): void
    {
        $pmd = new PlainMonthDay(3, 15);

        static::assertSame('iso8601', $pmd->calendarId);
    }

    public function testPlainMonthDayFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\PlainMonthDay(3, 15, 'coptic');
        $pmd = PlainMonthDay::fromSpec($spec);

        static::assertSame('coptic', $pmd->calendarId);
        static::assertSame(3, $pmd->toSpec()->isoMonth);
        static::assertSame(15, $pmd->toSpec()->isoDay);
    }

    public function testPlainMonthDayFromSpecPreservesReferenceYear(): void
    {
        $spec = new \Temporal\Spec\PlainMonthDay(6, 15, 'iso8601', 2000);
        $pmd = PlainMonthDay::fromSpec($spec);

        static::assertSame(2000, $pmd->toSpec()->referenceISOYear);
    }

    public function testPlainMonthDayFromSpecDoesNotCorruptNonIso(): void
    {
        $spec1 = new \Temporal\Spec\PlainMonthDay(3, 15, 'indian');
        $pmd = PlainMonthDay::fromSpec($spec1);
        $spec2 = $pmd->toSpec();
        $pmd2 = PlainMonthDay::fromSpec($spec2);

        static::assertSame($spec1->isoMonth, $pmd2->toSpec()->isoMonth);
        static::assertSame($spec1->isoDay, $pmd2->toSpec()->isoDay);
        static::assertSame('indian', $pmd2->calendarId);
    }

    public function testPlainMonthDayParsePreservesCalendar(): void
    {
        $pmd = PlainMonthDay::parse('1972-03-15[u-ca=chinese]');

        static::assertSame('chinese', $pmd->calendarId);
    }

    // =========================================================================
    // ZonedDateTime
    // =========================================================================

    public function testZonedDateTimeConstructorAcceptsCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'hebrew');

        static::assertSame('hebrew', $zdt->calendarId);
    }

    public function testZonedDateTimeConstructorDefaultsToIso(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('iso8601', $zdt->calendarId);
    }

    public function testZonedDateTimeFromSpecPreservesCalendar(): void
    {
        $spec = new \Temporal\Spec\ZonedDateTime(0, 'UTC', 'gregory');
        $zdt = ZonedDateTime::fromSpec($spec);

        static::assertSame('gregory', $zdt->calendarId);
    }

    public function testZonedDateTimeFromSpecDoesNotCorruptCalendar(): void
    {
        $spec1 = new \Temporal\Spec\ZonedDateTime(0, 'UTC', 'japanese');
        $zdt = ZonedDateTime::fromSpec($spec1);
        $spec2 = $zdt->toSpec();
        $zdt2 = ZonedDateTime::fromSpec($spec2);

        static::assertSame($spec1->epochNanoseconds, $zdt2->epochNanoseconds);
        static::assertSame('japanese', $zdt2->calendarId);
    }

    public function testZonedDateTimeParsePreservesCalendar(): void
    {
        $zdt = ZonedDateTime::parse('2024-01-15T10:30:00+00:00[UTC][u-ca=hebrew]');

        static::assertSame('hebrew', $zdt->calendarId);
    }

    public function testZonedDateTimeAddPreservesCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'buddhist');
        $zdt2 = $zdt->add(new Duration(days: 5));

        static::assertSame('buddhist', $zdt2->calendarId);
    }

    public function testZonedDateTimeToPlainDatePreservesCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'hebrew');
        $pd = $zdt->toPlainDate();

        static::assertSame('hebrew', $pd->calendarId);
    }

    public function testZonedDateTimeToPlainDateTimePreservesCalendar(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC', 'japanese');
        $pdt = $zdt->toPlainDateTime();

        static::assertSame('japanese', $pdt->calendarId);
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

        static::assertContains($result, [-1, 0, 1]);
    }

    public function testDurationTotalWithNonIsoRelativeTo(): void
    {
        $d = new Duration(years: 1);
        $rt = new PlainDate(2024, 1, 15, 'hebrew');

        // 2024 is a leap year, so 1 year from Jan 15 should be 366 days
        $result = $d->total(Unit::Day, $rt);

        static::assertSame(366.0, (float) $result);
    }

    public function testDurationRoundWithNonIsoRelativeTo(): void
    {
        $d = new Duration(months: 15);
        $rt = new PlainDate(2024, 1, 15, 'buddhist');

        $rounded = $d->round(smallestUnit: Unit::Year, relativeTo: $rt);

        static::assertSame(1, $rounded->years);
    }

    // =========================================================================
    // ISO calendar backward compatibility
    // =========================================================================

    public function testPlainDateIsoBackwardCompatibility(): void
    {
        $pd = new PlainDate(2024, 6, 15);

        // Same values for ISO calendar
        static::assertSame(2024, $pd->year);
        static::assertSame(6, $pd->month);
        static::assertSame(15, $pd->day);
        static::assertSame('iso8601', $pd->calendarId);
    }

    public function testPlainDateTimeIsoBackwardCompatibility(): void
    {
        $pdt = new PlainDateTime(2024, 6, 15, 10, 30, 45, 100, 200, 300);

        static::assertSame(2024, $pdt->year);
        static::assertSame(6, $pdt->month);
        static::assertSame(15, $pdt->day);
        static::assertSame(10, $pdt->hour);
        static::assertSame(30, $pdt->minute);
        static::assertSame(45, $pdt->second);
        static::assertSame(100, $pdt->millisecond);
        static::assertSame(200, $pdt->microsecond);
        static::assertSame(300, $pdt->nanosecond);
        static::assertSame('iso8601', $pdt->calendarId);
    }

    public function testPlainYearMonthIsoBackwardCompatibility(): void
    {
        $pym = new PlainYearMonth(2024, 6);

        static::assertSame(2024, $pym->year);
        static::assertSame(6, $pym->month);
        static::assertSame('iso8601', $pym->calendarId);
    }

    public function testPlainMonthDayIsoBackwardCompatibility(): void
    {
        $pmd = new PlainMonthDay(3, 15);

        static::assertSame(3, $pmd->month);
        static::assertSame(15, $pmd->day);
        static::assertSame('iso8601', $pmd->calendarId);
    }

    public function testZonedDateTimeIsoBackwardCompatibility(): void
    {
        $zdt = new ZonedDateTime(0, 'UTC');

        static::assertSame('iso8601', $zdt->calendarId);
        static::assertSame(0, $zdt->epochNanoseconds);
    }

    // =========================================================================
    // Cross-type conversions preserve calendar
    // =========================================================================

    public function testPlainDateToZonedDateTimePreservesCalendar(): void
    {
        $pd = new PlainDate(2024, 1, 15, 'buddhist');
        $zdt = $pd->toZonedDateTime('UTC');

        static::assertSame('buddhist', $zdt->calendarId);
    }

    // =========================================================================
    // ext-intl composer.json requirement
    // =========================================================================

    public function testIntlExtensionIsLoaded(): void
    {
        static::assertTrue(extension_loaded('intl'), 'ext-intl must be loaded');
    }
}
