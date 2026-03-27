<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal\Calendar;

use InvalidArgumentException;
use Temporal\Exception\NotYetImplementedException;
use Temporal\Spec\Internal\CalendarMath;

/**
 * Non-ISO calendar implementation backed by PHP's IntlCalendar (ICU).
 *
 * Converts between ISO 8601 fields and calendar-specific fields using ICU's
 * calendar support. The conversion path is:
 *   ISO fields -> JDN -> epoch ms -> IntlCalendar -> calendar fields
 *
 * @internal
 */
final class IntlCalendarBridge implements CalendarProtocol
{
    /** Milliseconds per day. */
    private const int MS_PER_DAY = 86_400_000;

    /** ICU field ID for EXTENDED_YEAR (not defined as a PHP constant). */
    private const int FIELD_EXTENDED_YEAR = 19;

    /** ICU field ID for IS_LEAP_MONTH. */
    private const int FIELD_IS_LEAP_MONTH = 22;

    /**
     * Map from TC39 calendar ID to ICU calendar type.
     *
     * @var array<string, string>
     */
    private const CALENDAR_TO_ICU = [
        'buddhist' => 'buddhist',
        'chinese' => 'chinese',
        'coptic' => 'coptic',
        'dangi' => 'dangi',
        'ethioaa' => 'ethiopic-amete-alem',
        'ethiopic' => 'ethiopic',
        'gregory' => 'gregorian',
        'hebrew' => 'hebrew',
        'indian' => 'indian',
        'islamic' => 'islamic',
        'islamic-civil' => 'islamic-civil',
        'islamic-rgsa' => 'islamic-rgsa',
        'islamic-tbla' => 'islamic-tbla',
        'islamic-umalqura' => 'islamic-umalqura',
        'japanese' => 'japanese',
        'persian' => 'persian',
        'roc' => 'roc',
    ];

    /**
     * Japanese ICU era numbers to TC39 era strings.
     *
     * @var array<int, string>
     */
    private const JAPANESE_ERAS = [
        236 => 'reiwa',
        235 => 'heisei',
        234 => 'showa',
        233 => 'taisho',
        232 => 'meiji',
    ];

    /** Calendars that use Gregorian solar year (ISO leap year rule). */
    private const GREGORIAN_BASED = ['gregory', 'buddhist', 'roc', 'japanese', 'indian'];

    private readonly \IntlCalendar $intlCal;

    public function __construct(
        private readonly string $calendarId,
    ) {
        $icuType = self::CALENDAR_TO_ICU[$calendarId]
            ?? throw new InvalidArgumentException("No ICU mapping for calendar \"{$calendarId}\".");
        $cal = \IntlCalendar::createInstance('UTC', '@calendar=' . $icuType);
        if ($cal === null) {
            throw new \RuntimeException("Failed to create IntlCalendar for \"{$icuType}\".");
        }
        $this->intlCal = $cal;
    }

    public function id(): string
    {
        return $this->calendarId;
    }

    // -------------------------------------------------------------------------
    // ISO -> Calendar field projection
    // -------------------------------------------------------------------------

    public function year(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        // Gregory uses signed year (EXTENDED_YEAR): negative for BCE.
        // Chinese/Dangi use EXTENDED_YEAR as the "related Gregorian year" proxy.
        if ($this->calendarId === 'gregory') {
            return $this->intlCal->get(self::FIELD_EXTENDED_YEAR);
        }

        return $this->intlCal->get(\IntlCalendar::FIELD_YEAR);
    }

    public function month(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'hebrew' => $this->hebrewMonthOrdinal(),
            'chinese', 'dangi' => $this->chineseMonthOrdinal(),
            default => $this->intlCal->get(\IntlCalendar::FIELD_MONTH) + 1,
        };
    }

    public function day(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->get(\IntlCalendar::FIELD_DAY_OF_MONTH);
    }

    public function era(int $isoYear, int $isoMonth, int $isoDay): ?string
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'gregory' => $this->intlCal->get(\IntlCalendar::FIELD_ERA) === 1 ? 'ce' : 'bce',
            'japanese' => $this->japaneseEra(),
            'buddhist' => 'be',
            'roc' => $this->intlCal->get(\IntlCalendar::FIELD_ERA) === 1 ? 'minguo' : 'before-roc',
            'coptic', 'ethiopic' => $this->intlCal->get(\IntlCalendar::FIELD_ERA) === 1 ? 'era1' : 'era0',
            'ethioaa' => 'era0',
            default => null,
        };
    }

    public function eraYear(int $isoYear, int $isoMonth, int $isoDay): ?int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'gregory' => $this->intlCal->get(\IntlCalendar::FIELD_YEAR),
            'japanese', 'buddhist', 'roc' => $this->intlCal->get(\IntlCalendar::FIELD_YEAR),
            'coptic', 'ethiopic', 'ethioaa' => $this->intlCal->get(\IntlCalendar::FIELD_YEAR),
            default => null,
        };
    }

    public function monthCode(int $isoYear, int $isoMonth, int $isoDay): string
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'hebrew' => $this->hebrewMonthCode(),
            'chinese', 'dangi' => $this->chineseMonthCode(),
            default => sprintf('M%02d', $this->intlCal->get(\IntlCalendar::FIELD_MONTH) + 1),
        };
    }

    public function dayOfYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->get(\IntlCalendar::FIELD_DAY_OF_YEAR);
    }

    public function daysInMonth(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);
    }

    public function daysInYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_YEAR);
    }

    public function monthsInYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'hebrew' => $this->isHebrewLeapYear() ? 13 : 12,
            'chinese', 'dangi' => $this->hasChineseLeapMonth() ? 13 : 12,
            default => $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_MONTH) + 1,
        };
    }

    public function inLeapYear(int $isoYear, int $isoMonth, int $isoDay): bool
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        if (in_array($this->calendarId, self::GREGORIAN_BASED, true)) {
            return CalendarMath::isLeapYear($isoYear);
        }

        return match ($this->calendarId) {
            'hebrew' => $this->isHebrewLeapYear(),
            'chinese', 'dangi' => $this->hasChineseLeapMonth(),
            'coptic', 'ethiopic', 'ethioaa' =>
                $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_YEAR) > 365,
            'persian' =>
                $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_YEAR) > 365,
            default => // Islamic variants: leap year has 355 days, non-leap 354
                $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_YEAR) > 354,
        };
    }

    // -------------------------------------------------------------------------
    // Calendar -> ISO field resolution
    // -------------------------------------------------------------------------

    public function calendarToIso(int $calYear, int $calMonth, int $calDay, string $overflow): array
    {
        // Stub — Phase 4 will implement.
        throw new NotYetImplementedException('IntlCalendarBridge::calendarToIso()');
    }

    public function calendarToIsoFromMonthCode(int $calYear, string $monthCode, int $calDay, string $overflow): array
    {
        // Stub — Phase 4 will implement.
        throw new NotYetImplementedException('IntlCalendarBridge::calendarToIsoFromMonthCode()');
    }

    // -------------------------------------------------------------------------
    // Calendar-aware arithmetic
    // -------------------------------------------------------------------------

    public function dateAdd(
        int $isoYear,
        int $isoMonth,
        int $isoDay,
        int $years,
        int $months,
        int $weeks,
        int $days,
        string $overflow,
    ): array {
        // Stub — Phase 5 will implement.
        throw new NotYetImplementedException('IntlCalendarBridge::dateAdd()');
    }

    public function dateUntil(
        int $isoY1,
        int $isoM1,
        int $isoD1,
        int $isoY2,
        int $isoM2,
        int $isoD2,
        string $largestUnit,
    ): array {
        // Stub — Phase 5 will implement.
        throw new NotYetImplementedException('IntlCalendarBridge::dateUntil()');
    }

    // -------------------------------------------------------------------------
    // Month code utilities
    // -------------------------------------------------------------------------

    public function monthCodeToMonth(string $monthCode, int $calYear): int
    {
        // Stub — Phase 4 will implement.
        throw new NotYetImplementedException('IntlCalendarBridge::monthCodeToMonth()');
    }

    // -------------------------------------------------------------------------
    // Internal: set the IntlCalendar to an ISO date
    // -------------------------------------------------------------------------

    private function setIsoDate(int $isoYear, int $isoMonth, int $isoDay): void
    {
        $jdn = CalendarMath::toJulianDay($isoYear, $isoMonth, $isoDay);
        $epochMs = ($jdn - 2_440_588) * self::MS_PER_DAY;
        $this->intlCal->setTime((float) $epochMs);
    }

    // -------------------------------------------------------------------------
    // Hebrew calendar helpers
    // -------------------------------------------------------------------------

    /**
     * Whether the current Hebrew year is a leap year (has 13 months).
     * Must call setIsoDate() first.
     */
    private function isHebrewLeapYear(): bool
    {
        $year = $this->intlCal->get(\IntlCalendar::FIELD_YEAR);

        return (7 * $year + 1) % 19 < 7;
    }

    /**
     * Computes the TC39 ordinal month for Hebrew calendar.
     * Must call setIsoDate() first.
     *
     * In ICU, Hebrew months are 0-indexed with slot 5 = Adar I (leap only).
     * Non-leap: ICU 0-4 → TC39 1-5, ICU 6-12 → TC39 6-12
     * Leap:     ICU 0-4 → TC39 1-5, ICU 5 → TC39 6, ICU 6-12 → TC39 7-13
     */
    private function hebrewMonthOrdinal(): int
    {
        $icuMonth = $this->intlCal->get(\IntlCalendar::FIELD_MONTH);
        $isLeap = $this->isHebrewLeapYear();

        if ($icuMonth <= 4) {
            return $icuMonth + 1;
        }
        if ($isLeap) {
            // Slot 5 = Adar I (month 6), slots 6-12 = months 7-13.
            return $icuMonth + 1;
        }
        // Non-leap: slot 5 doesn't exist, slots 6-12 = months 6-12.
        return $icuMonth;
    }

    /**
     * Computes the TC39 month code for Hebrew calendar.
     * Must call setIsoDate() first.
     *
     * ICU months 0-4 → M01-M05, slot 5 (leap) → M05L, slots 6-12 → M06-M12.
     */
    private function hebrewMonthCode(): string
    {
        $icuMonth = $this->intlCal->get(\IntlCalendar::FIELD_MONTH);

        if ($icuMonth <= 4) {
            return sprintf('M%02d', $icuMonth + 1);
        }
        if ($icuMonth === 5) {
            // Adar I — only appears in leap years.
            return 'M05L';
        }
        // ICU months 6-12 → M06-M12
        return sprintf('M%02d', $icuMonth);
    }

    // -------------------------------------------------------------------------
    // Chinese/Dangi calendar helpers
    // -------------------------------------------------------------------------

    /**
     * Whether the current Chinese/Dangi year has a leap month.
     * Must call setIsoDate() first.
     */
    private function hasChineseLeapMonth(): bool
    {
        $savedTime = $this->intlCal->getTime();
        $year = $this->intlCal->get(\IntlCalendar::FIELD_YEAR);
        $era = $this->intlCal->get(\IntlCalendar::FIELD_ERA);

        $found = false;
        // Scan all months in the year checking IS_LEAP_MONTH.
        for ($m = 0; $m <= 11; $m++) {
            $this->intlCal->clear();
            $this->intlCal->set(\IntlCalendar::FIELD_ERA, $era);
            $this->intlCal->set(\IntlCalendar::FIELD_YEAR, $year);
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $m);
            $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, 15); // mid-month for safety
            // Force resolution.
            $this->intlCal->get(\IntlCalendar::FIELD_MONTH);

            // Now advance to the end of this month + 1 day to check if a leap month follows.
            $daysInMonth = $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);
            $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $daysInMonth);
            $this->intlCal->add(\IntlCalendar::FIELD_DAY_OF_MONTH, 1);
            if ($this->intlCal->get(self::FIELD_IS_LEAP_MONTH) === 1) {
                $found = true;
                break;
            }
        }

        $this->intlCal->setTime($savedTime);

        return $found;
    }

    /**
     * Computes the TC39 ordinal month for Chinese/Dangi calendar.
     * Must call setIsoDate() first.
     *
     * Chinese/Dangi months are 0-indexed in ICU. A leap month has the same
     * MONTH value as the preceding month but IS_LEAP_MONTH=1. The TC39 ordinal
     * counts months sequentially (including the leap month).
     */
    private function chineseMonthOrdinal(): int
    {
        $icuMonth = $this->intlCal->get(\IntlCalendar::FIELD_MONTH);
        $isLeap = $this->intlCal->get(self::FIELD_IS_LEAP_MONTH);

        // Base ordinal (no leap month consideration).
        $ordinal = $icuMonth + 1;

        if ($isLeap) {
            // This IS a leap month — it follows the regular month with the same index.
            return $ordinal + 1;
        }

        // Check if a leap month occurred before the current month in this year.
        $leapBefore = $this->findChineseLeapMonthBefore($icuMonth);
        if ($leapBefore >= 0) {
            return $ordinal + 1;
        }

        return $ordinal;
    }

    /**
     * Computes the TC39 month code for Chinese/Dangi calendar.
     * Must call setIsoDate() first.
     *
     * Regular months: M01-M12. Leap months: MxxL (where xx = the regular month number).
     */
    private function chineseMonthCode(): string
    {
        $icuMonth = $this->intlCal->get(\IntlCalendar::FIELD_MONTH);
        $isLeap = $this->intlCal->get(self::FIELD_IS_LEAP_MONTH);

        $code = sprintf('M%02d', $icuMonth + 1);

        return $isLeap ? $code . 'L' : $code;
    }

    /**
     * Finds the leap month index (0-based) occurring before month $beforeMonth in the current year.
     * Returns -1 if none found. Must call setIsoDate() first.
     */
    private function findChineseLeapMonthBefore(int $beforeMonth): int
    {
        if ($beforeMonth === 0) {
            return -1;
        }

        $savedTime = $this->intlCal->getTime();
        $year = $this->intlCal->get(\IntlCalendar::FIELD_YEAR);
        $era = $this->intlCal->get(\IntlCalendar::FIELD_ERA);

        $result = -1;
        for ($m = 0; $m < $beforeMonth; $m++) {
            $this->intlCal->clear();
            $this->intlCal->set(\IntlCalendar::FIELD_ERA, $era);
            $this->intlCal->set(\IntlCalendar::FIELD_YEAR, $year);
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $m);
            $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, 15);

            // Advance to last day of month + 1 day to enter next month.
            $daysInMonth = $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);
            $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $daysInMonth);
            $this->intlCal->add(\IntlCalendar::FIELD_DAY_OF_MONTH, 1);

            if ($this->intlCal->get(self::FIELD_IS_LEAP_MONTH) === 1) {
                $result = $m;
                break;
            }
        }

        $this->intlCal->setTime($savedTime);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Japanese era helper
    // -------------------------------------------------------------------------

    /**
     * Returns the TC39 era string for the current Japanese calendar date.
     * Must call setIsoDate() first.
     */
    private function japaneseEra(): string
    {
        $icuEra = $this->intlCal->get(\IntlCalendar::FIELD_ERA);

        return self::JAPANESE_ERAS[$icuEra] ?? 'ce';
    }
}
