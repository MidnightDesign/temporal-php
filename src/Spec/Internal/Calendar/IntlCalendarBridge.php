<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal\Calendar;

use InvalidArgumentException;
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
        // TC39 requires proleptic Gregorian (no Julian cutover). ICU's Gregorian
        // calendar defaults to a 1582-10-15 cutover; setting the change date to
        // the minimum float value makes it fully proleptic.
        if ($cal instanceof \IntlGregorianCalendar) {
            $cal->setGregorianChange(PHP_FLOAT_MIN);
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

    /** Offset from ICU EXTENDED_YEAR to TC39 "related Gregorian year" for Chinese. */
    private const CHINESE_YEAR_OFFSET = 2637;

    /** Offset from ICU EXTENDED_YEAR to TC39 "related Gregorian year" for Dangi. */
    private const DANGI_YEAR_OFFSET = 2333;

    /** Offset from ICU EXTENDED_YEAR to TC39 signed year for ROC. */
    private const ROC_YEAR_OFFSET = 1911;

    public function year(int $isoYear, int $isoMonth, int $isoDay): int
    {
        // Gregorian-based calendars: compute directly from ISO year (proleptic, no ICU roundtrip).
        return match ($this->calendarId) {
            'gregory', 'japanese' => $isoYear,
            'buddhist' => $isoYear + 543,
            'roc' => $isoYear - self::ROC_YEAR_OFFSET,
            default => $this->yearFromIcu($isoYear, $isoMonth, $isoDay),
        };
    }

    private function yearFromIcu(int $isoYear, int $isoMonth, int $isoDay): int
    {
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        if (in_array($this->calendarId, ['coptic', 'ethiopic'], true)) {
            return $this->intlCal->get(self::FIELD_EXTENDED_YEAR);
        }
        if ($this->calendarId === 'chinese') {
            return $this->intlCal->get(self::FIELD_EXTENDED_YEAR) - self::CHINESE_YEAR_OFFSET;
        }
        if ($this->calendarId === 'dangi') {
            return $this->intlCal->get(self::FIELD_EXTENDED_YEAR) - self::DANGI_YEAR_OFFSET;
        }

        return $this->intlCal->get(\IntlCalendar::FIELD_YEAR);
    }

    public function month(int $isoYear, int $isoMonth, int $isoDay): int
    {
        // Gregorian-based calendars share ISO month structure.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            return $isoMonth;
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'hebrew' => $this->hebrewMonthOrdinal(),
            'chinese', 'dangi' => $this->chineseMonthOrdinal(),
            default => $this->intlCal->get(\IntlCalendar::FIELD_MONTH) + 1,
        };
    }

    public function day(int $isoYear, int $isoMonth, int $isoDay): int
    {
        // Gregorian-based calendars share ISO day structure.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            return $isoDay;
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->get(\IntlCalendar::FIELD_DAY_OF_MONTH);
    }

    public function era(int $isoYear, int $isoMonth, int $isoDay): ?string
    {
        return match ($this->calendarId) {
            'gregory' => $isoYear >= 1 ? 'ce' : 'bce',
            'japanese' => $this->japaneseEraFromIso($isoYear, $isoMonth, $isoDay),
            'buddhist' => 'be',
            'roc' => $isoYear >= 1912 ? 'roc' : 'broc',
            'coptic' => 'am',
            'ethiopic' => $this->intlCal->get(\IntlCalendar::FIELD_ERA) === 1 ? 'am' : 'aa',
            'ethioaa' => 'aa',
            'hebrew' => 'am',
            'indian' => 'shaka',
            'islamic', 'islamic-civil', 'islamic-rgsa', 'islamic-tbla', 'islamic-umalqura' =>
                $this->intlCal->get(\IntlCalendar::FIELD_YEAR) >= 1 ? 'ah' : 'bh',
            'persian' => 'ap',
            default => null,
        };
    }

    public function eraYear(int $isoYear, int $isoMonth, int $isoDay): ?int
    {
        // Gregorian-based calendars: compute directly from ISO year.
        if ($this->calendarId === 'gregory') {
            return $isoYear >= 1 ? $isoYear : 1 - $isoYear;
        }
        if ($this->calendarId === 'buddhist') {
            return $isoYear + 543;
        }
        if ($this->calendarId === 'roc') {
            return $isoYear >= 1912 ? $isoYear - self::ROC_YEAR_OFFSET : 1912 - $isoYear;
        }
        if ($this->calendarId === 'japanese') {
            return $this->japaneseEraYearFromIso($isoYear, $isoMonth, $isoDay);
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'coptic' => $this->intlCal->get(self::FIELD_EXTENDED_YEAR),
            'ethiopic' => $this->intlCal->get(\IntlCalendar::FIELD_ERA) === 1
                ? $this->intlCal->get(self::FIELD_EXTENDED_YEAR)
                : $this->intlCal->get(\IntlCalendar::FIELD_YEAR),
            'ethioaa' => $this->intlCal->get(\IntlCalendar::FIELD_YEAR),
            'hebrew', 'indian', 'persian' => $this->intlCal->get(\IntlCalendar::FIELD_YEAR),
            'islamic', 'islamic-civil', 'islamic-rgsa', 'islamic-tbla', 'islamic-umalqura' => (function () {
                $year = $this->intlCal->get(\IntlCalendar::FIELD_YEAR);
                return $year >= 1 ? $year : 1 - $year;
            })(),
            default => null,
        };
    }

    public function monthCode(int $isoYear, int $isoMonth, int $isoDay): string
    {
        // Gregorian-based calendars: month code matches ISO month.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            return sprintf('M%02d', $isoMonth);
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'hebrew' => $this->hebrewMonthCode(),
            'chinese', 'dangi' => $this->chineseMonthCode(),
            default => sprintf('M%02d', $this->intlCal->get(\IntlCalendar::FIELD_MONTH) + 1),
        };
    }

    public function dayOfYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        // Gregorian-based calendars: compute directly.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            return CalendarMath::isoDayOfYear($isoYear, $isoMonth, $isoDay);
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->get(\IntlCalendar::FIELD_DAY_OF_YEAR);
    }

    public function daysInMonth(int $isoYear, int $isoMonth, int $isoDay): int
    {
        // Gregorian-based calendars: compute directly.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            return CalendarMath::calcDaysInMonth($isoYear, $isoMonth);
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);
    }

    public function daysInYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        // Gregorian-based calendars: compute directly.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            return CalendarMath::isLeapYear($isoYear) ? 366 : 365;
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_YEAR);
    }

    public function monthsInYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        // Gregorian-based calendars always have 12 months.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            return 12;
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        return match ($this->calendarId) {
            'hebrew' => $this->isHebrewLeapYear() ? 13 : 12,
            'chinese', 'dangi' => $this->hasChineseLeapMonth() ? 13 : 12,
            default => $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_MONTH) + 1,
        };
    }

    public function inLeapYear(int $isoYear, int $isoMonth, int $isoDay): bool
    {
        if (in_array($this->calendarId, self::GREGORIAN_BASED, true)) {
            return CalendarMath::isLeapYear($isoYear);
        }

        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

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
        // For Gregorian-based calendars, pre-compute max day and handle overflow before
        // setting fields (JDN + set DAY_OF_MONTH would silently wrap to next month).
        $isoYear = match ($this->calendarId) {
            'gregory', 'japanese' => $calYear,
            'buddhist' => $calYear - 543,
            'roc' => $calYear + self::ROC_YEAR_OFFSET,
            default => null,
        };
        if ($isoYear !== null) {
            // Constrain month to 1-12 for Gregorian-based calendars.
            if ($calMonth > 12) {
                if ($overflow === 'reject') {
                    throw new InvalidArgumentException("Month {$calMonth} exceeds maximum 12 for this calendar year.");
                }
                $calMonth = 12;
            }
            $this->gregorianMaxDay = CalendarMath::calcDaysInMonth($isoYear, $calMonth);
            $clampedDay = min($calDay, $this->gregorianMaxDay);
            $jdn = CalendarMath::toJulianDay($isoYear, $calMonth, $clampedDay);
            $epochMs = ($jdn - 2_440_588) * self::MS_PER_DAY;
            $this->intlCal->setTime((float) $epochMs);
            return $this->resolveAndConstrain($calDay, $overflow);
        }

        // Non-Gregorian calendars: validate/constrain month range.
        if ($overflow === 'reject') {
            $this->setCalendarFields($calYear, 1, 1);
            $maxMonths = match ($this->calendarId) {
                'hebrew' => $this->isHebrewLeapYear() ? 13 : 12,
                'chinese', 'dangi' => $this->hasChineseLeapMonth() ? 13 : 12,
                default => $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_MONTH) + 1,
            };
            if ($calMonth > $maxMonths) {
                throw new InvalidArgumentException(
                    "Month {$calMonth} exceeds maximum {$maxMonths} for this calendar year.",
                );
            }
        } elseif ($calMonth > 12) {
            // For non-Gregorian constrain, check if month exceeds max.
            $this->setCalendarFields($calYear, 1, 1);
            $maxMonths = match ($this->calendarId) {
                'hebrew' => $this->isHebrewLeapYear() ? 13 : 12,
                'chinese', 'dangi' => $this->hasChineseLeapMonth() ? 13 : 12,
                default => $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_MONTH) + 1,
            };
            if ($calMonth > $maxMonths) {
                $calMonth = $maxMonths;
            }
        }
        $this->setCalendarFields($calYear, $calMonth, $calDay);
        return $this->resolveAndConstrain($calDay, $overflow);
    }

    public function calendarToIsoFromMonthCode(int $calYear, string $monthCode, int $calDay, string $overflow): array
    {
        $isLeapCode = str_ends_with($monthCode, 'L');

        // For Chinese/Dangi leap month codes, first verify the leap month exists
        // in this year using day 1 (to avoid day overflow changing the month).
        if ($isLeapCode && in_array($this->calendarId, ['chinese', 'dangi'], true)) {
            try {
                $this->setCalendarFieldsFromMonthCode($calYear, $monthCode, 1);
                $this->intlCal->get(\IntlCalendar::FIELD_MONTH);
                if ($this->intlCal->get(self::FIELD_IS_LEAP_MONTH) !== 1) {
                    throw new InvalidArgumentException(
                        "monthCode \"{$monthCode}\" does not exist in this calendar year.",
                    );
                }
            } catch (InvalidArgumentException $e) {
                if ($overflow === 'constrain') {
                    // Chinese/Dangi: MxxL → Mxx (the regular version of the same month).
                    $baseCode = substr($monthCode, 0, -1);
                    $this->setCalendarFieldsFromMonthCode($calYear, $baseCode, $calDay);
                    return $this->resolveAndConstrain($calDay, $overflow);
                }
                throw $e;
            }
        }

        try {
            $this->setCalendarFieldsFromMonthCode($calYear, $monthCode, $calDay);
        } catch (InvalidArgumentException $e) {
            // Leap month code in a year without that leap month: constrain.
            if ($overflow === 'constrain' && $isLeapCode) {
                if ($this->calendarId === 'hebrew' && $monthCode === 'M05L') {
                    // Hebrew M05L (Adar I) constrains to M06 (Adar), not M05 (Shevat).
                    $this->setCalendarFieldsFromMonthCode($calYear, 'M06', $calDay);
                } else {
                    $baseCode = substr($monthCode, 0, -1);
                    $this->setCalendarFieldsFromMonthCode($calYear, $baseCode, $calDay);
                }
            } else {
                throw $e;
            }
        }
        return $this->resolveAndConstrain($calDay, $overflow);
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
        $this->setIsoDate($isoYear, $isoMonth, $isoDay);

        // Capture the original calendar day before year/month addition, for 'reject' overflow.
        $originalCalDay = $this->intlCal->get(\IntlCalendar::FIELD_DAY_OF_MONTH);

        if ($years !== 0 || $months !== 0) {
            // Use field-level arithmetic: read calendar fields, add to year/month,
            // then resolve back. This avoids Julian cutover issues in ICU.
            $calYear = $this->calendarYear();
            $calMonth = $this->calendarMonth();

            // For calendars with leap months, year addition must preserve monthCode
            // (not ordinal position), because leap months shift ordinals between years.
            if ($years !== 0 && in_array($this->calendarId, ['chinese', 'dangi'], true)) {
                $icuMonth = $this->intlCal->get(\IntlCalendar::FIELD_MONTH);
                $isLeap = $this->intlCal->get(self::FIELD_IS_LEAP_MONTH);
                $calYear += $years;
                $calMonth = $this->chineseIcuMonthToOrdinal($calYear, $icuMonth, $isLeap, $overflow);
            } elseif ($years !== 0 && $this->calendarId === 'hebrew') {
                // Hebrew: preserve monthCode across year addition.
                // Read the current monthCode, add years, resolve monthCode in new year.
                $mc = $this->hebrewMonthCode();
                $calYear += $years;
                // Use monthCodeToMonth for the new year. If M05L and new year isn't
                // leap, constrain to M06 (Adar).
                $isNewLeap = (7 * $calYear + 1) % 19 < 7;
                if ($mc === 'M05L' && !$isNewLeap) {
                    if ($overflow === 'reject') {
                        throw new InvalidArgumentException(
                            "monthCode \"M05L\" does not exist in Hebrew year {$calYear}.",
                        );
                    }
                    $calMonth = $this->hebrewMonthCodeToMonth('M06', $calYear);
                } else {
                    $calMonth = $this->hebrewMonthCodeToMonth($mc, $calYear);
                }
            } else {
                $calYear += $years;
            }

            $calMonth += $months;

            // Handle month overflow/underflow.
            while ($calMonth < 1) {
                $calYear--;
                // Compute months in the year we're borrowing from.
                $this->setCalendarFields($calYear, 1, 1);
                $calMonth += $this->calendarMonthsInYear();
            }
            while (true) {
                $this->setCalendarFields($calYear, 1, 1);
                $monthsInYear = $this->calendarMonthsInYear();
                if ($calMonth <= $monthsInYear) {
                    break;
                }
                $calMonth -= $monthsInYear;
                $calYear++;
            }

            // Resolve new date with day constraining.
            $this->setCalendarFields($calYear, $calMonth, $originalCalDay);
            $newMaxDay = $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);

            if ($overflow === 'reject' && $originalCalDay > $newMaxDay) {
                throw new InvalidArgumentException(
                    "Day {$originalCalDay} exceeds maximum {$newMaxDay} for the resulting calendar month.",
                );
            }

            if ($originalCalDay > $newMaxDay) {
                $this->setCalendarFields($calYear, $calMonth, $newMaxDay);
            }
        }

        // Add weeks and days using JDN arithmetic (proleptic, no Julian cutover issue).
        $epochMs = $this->intlCal->getTime();
        $jdn = (int) floor($epochMs / self::MS_PER_DAY) + 2_440_588;
        $totalDays = ($weeks * 7) + $days;
        $jdn += $totalDays;

        return CalendarMath::fromJulianDay($jdn);
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
        // Day/week: pure JDN subtraction, calendar doesn't matter.
        if ($largestUnit === 'day' || $largestUnit === 'week') {
            $totalDays = CalendarMath::toJulianDay($isoY2, $isoM2, $isoD2)
                - CalendarMath::toJulianDay($isoY1, $isoM1, $isoD1);
            if ($largestUnit === 'week') {
                $weeks = intdiv($totalDays, 7);
                $days = $totalDays - ($weeks * 7);
                return [0, 0, $weeks, $days];
            }
            return [0, 0, 0, $totalDays];
        }

        // Year/month decomposition using TC39 DifferenceDate trial-and-increment
        // algorithm: starting from an initial estimate, adjust years and months
        // forward via dateAdd until adding one more would overshoot the end date.

        // Determine sign and normalize so start <= end.
        $jdn1 = CalendarMath::toJulianDay($isoY1, $isoM1, $isoD1);
        $jdn2 = CalendarMath::toJulianDay($isoY2, $isoM2, $isoD2);
        $sign = $jdn2 >= $jdn1 ? 1 : -1;

        if ($sign < 0) {
            [$isoY1, $isoM1, $isoD1, $isoY2, $isoM2, $isoD2] =
                [$isoY2, $isoM2, $isoD2, $isoY1, $isoM1, $isoD1];
            [$jdn1, $jdn2] = [$jdn2, $jdn1];
        }

        // Read calendar fields for initial estimates.
        $this->setIsoDate($isoY1, $isoM1, $isoD1);
        $calY1 = $this->calendarYear();
        $calM1 = $this->calendarMonth();

        $this->setIsoDate($isoY2, $isoM2, $isoD2);
        $calY2 = $this->calendarYear();
        $calM2 = $this->calendarMonth();

        $years = 0;
        $months = 0;

        if ($largestUnit === 'year') {
            // Initial guess for years, backed off by 1 to be safe.
            $years = max(0, $calY2 - $calY1 - 1);

            // Increment years until adding one more would overshoot.
            while (true) {
                [$tY, $tM, $tD] = $this->dateAdd(
                    $isoY1, $isoM1, $isoD1,
                    $years + 1, 0, 0, 0,
                    'constrain',
                );
                if (CalendarMath::toJulianDay($tY, $tM, $tD) > $jdn2) {
                    break;
                }
                $years++;
            }

            // Initial guess for months within the remaining partial year.
            if ($calY2 > $calY1 + $years) {
                // End is in a later calendar year than start+years.
                $this->setIsoDate($isoY1, $isoM1, $isoD1);
                $monthGuess = ($this->calendarMonthsInYear() - $calM1) + $calM2;
            } else {
                $monthGuess = $calM2 - $calM1;
            }
            $months = max(0, $monthGuess - 1);

            // Increment months until adding one more would overshoot.
            while (true) {
                [$tY, $tM, $tD] = $this->dateAdd(
                    $isoY1, $isoM1, $isoD1,
                    $years, $months + 1, 0, 0,
                    'constrain',
                );
                if (CalendarMath::toJulianDay($tY, $tM, $tD) > $jdn2) {
                    break;
                }
                $months++;
            }
        }

        if ($largestUnit === 'month') {
            // Initial guess: total months difference, backed off by 1.
            $totalMonthGuess = $this->totalMonthsInYears($calY1, $calY2 - $calY1, $isoY1, $isoM1, $isoD1)
                + ($calM2 - $calM1);
            $months = max(0, $totalMonthGuess - 1);

            // Increment months until adding one more would overshoot.
            while (true) {
                [$tY, $tM, $tD] = $this->dateAdd(
                    $isoY1, $isoM1, $isoD1,
                    0, $months + 1, 0, 0,
                    'constrain',
                );
                if (CalendarMath::toJulianDay($tY, $tM, $tD) > $jdn2) {
                    break;
                }
                $months++;
            }
        }

        // Compute remaining days from the intermediate date to end.
        [$intIsoY, $intIsoM, $intIsoD] = $this->dateAdd(
            $isoY1, $isoM1, $isoD1,
            $years, $months, 0, 0,
            'constrain',
        );
        $days = $jdn2 - CalendarMath::toJulianDay($intIsoY, $intIsoM, $intIsoD);

        return [$sign * $years, $sign * $months, 0, $sign * $days];
    }

    /**
     * Returns the calendar year using the same logic as year() but without re-calling setIsoDate.
     * Must call setIsoDate() first.
     */
    /**
     * Returns the calendar year from the currently-set IntlCalendar state.
     * For Gregorian-based calendars, convert via epoch to get the proleptic ISO year first.
     */
    private function calendarYear(): int
    {
        // For Gregorian-based calendars, derive from the epoch to avoid Julian cutover.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            $epochMs = $this->intlCal->getTime();
            $jdn = (int) floor($epochMs / self::MS_PER_DAY) + 2_440_588;
            [$isoY, , ] = CalendarMath::fromJulianDay($jdn);
            return match ($this->calendarId) {
                'gregory', 'japanese' => $isoY,
                'buddhist' => $isoY + 543,
                'roc' => $isoY - self::ROC_YEAR_OFFSET,
            };
        }
        if (in_array($this->calendarId, ['coptic', 'ethiopic'], true)) {
            return $this->intlCal->get(self::FIELD_EXTENDED_YEAR);
        }
        if ($this->calendarId === 'chinese') {
            return $this->intlCal->get(self::FIELD_EXTENDED_YEAR) - self::CHINESE_YEAR_OFFSET;
        }
        if ($this->calendarId === 'dangi') {
            return $this->intlCal->get(self::FIELD_EXTENDED_YEAR) - self::DANGI_YEAR_OFFSET;
        }
        return $this->intlCal->get(\IntlCalendar::FIELD_YEAR);
    }

    /**
     * Returns the calendar ordinal month using the same logic as month().
     * Must call setIsoDate() first.
     */
    /**
     * Returns the calendar ordinal month from the currently-set IntlCalendar state.
     */
    private function calendarMonth(): int
    {
        // Gregorian-based calendars: derive from epoch.
        if (in_array($this->calendarId, ['gregory', 'japanese', 'buddhist', 'roc'], true)) {
            $epochMs = $this->intlCal->getTime();
            $jdn = (int) floor($epochMs / self::MS_PER_DAY) + 2_440_588;
            [, $isoM, ] = CalendarMath::fromJulianDay($jdn);
            return $isoM;
        }
        return match ($this->calendarId) {
            'hebrew' => $this->hebrewMonthOrdinal(),
            'chinese', 'dangi' => $this->chineseMonthOrdinal(),
            default => $this->intlCal->get(\IntlCalendar::FIELD_MONTH) + 1,
        };
    }

    /**
     * Returns the months-in-year for the current calendar date.
     * Must call setIsoDate() first.
     */
    private function calendarMonthsInYear(): int
    {
        return match ($this->calendarId) {
            'hebrew' => $this->isHebrewLeapYear() ? 13 : 12,
            'chinese', 'dangi' => $this->hasChineseLeapMonth() ? 13 : 12,
            default => $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_MONTH) + 1,
        };
    }

    /**
     * Sums months-in-year for $count consecutive years starting from calYear.
     * Used to collapse years into months for largestUnit='month'.
     */
    private function totalMonthsInYears(int $calY1, int $years, int $isoY, int $isoM, int $isoD): int
    {
        if ($years === 0) {
            return 0;
        }
        $total = 0;
        // Walk forward one year at a time, accumulating months.
        $curIsoY = $isoY;
        $curIsoM = $isoM;
        $curIsoD = $isoD;
        for ($i = 0; $i < $years; $i++) {
            $this->setIsoDate($curIsoY, $curIsoM, $curIsoD);
            $total += $this->calendarMonthsInYear();
            // Advance by one calendar year.
            [$curIsoY, $curIsoM, $curIsoD] = $this->dateAdd(
                $curIsoY, $curIsoM, $curIsoD,
                1, 0, 0, 0,
                'constrain',
            );
        }
        return $total;
    }

    // -------------------------------------------------------------------------
    // Month code utilities
    // -------------------------------------------------------------------------

    public function monthCodeToMonth(string $monthCode, int $calYear): int
    {
        return match ($this->calendarId) {
            'hebrew' => $this->hebrewMonthCodeToMonth($monthCode, $calYear),
            'chinese', 'dangi' => $this->chineseMonthCodeToMonth($monthCode, $calYear),
            default => $this->defaultMonthCodeToMonth($monthCode),
        };
    }

    /**
     * monthCode → ordinal month for standard calendars.
     * Coptic/Ethiopic/Ethioaa allow M01-M13; others M01-M12.
     */
    private function defaultMonthCodeToMonth(string $monthCode): int
    {
        $maxMonth = in_array($this->calendarId, ['coptic', 'ethiopic', 'ethioaa'], true) ? 13 : 12;
        if (preg_match('/^M(\d{2})$/', $monthCode, $m) !== 1) {
            throw new InvalidArgumentException("Invalid monthCode \"{$monthCode}\" for calendar \"{$this->calendarId}\".");
        }
        $month = (int) $m[1];
        if ($month < 1 || $month > $maxMonth) {
            throw new InvalidArgumentException("monthCode \"{$monthCode}\" is out of range for calendar \"{$this->calendarId}\".");
        }
        return $month;
    }

    /**
     * Hebrew monthCode → ordinal month.
     *
     * M01-M05 → 1-5, M05L → 6 (leap only), M06-M12 → 6-12 (non-leap) or 7-13 (leap).
     */
    private function hebrewMonthCodeToMonth(string $monthCode, int $calYear): int
    {
        $isLeap = (7 * $calYear + 1) % 19 < 7;
        if ($monthCode === 'M05L') {
            if (!$isLeap) {
                throw new InvalidArgumentException("monthCode \"M05L\" is only valid in Hebrew leap years; year {$calYear} is not a leap year.");
            }
            return 6;
        }
        if (preg_match('/^M(\d{2})$/', $monthCode, $m) !== 1) {
            throw new InvalidArgumentException("Invalid monthCode \"{$monthCode}\" for hebrew calendar.");
        }
        $num = (int) $m[1];
        if ($num < 1 || $num > 12) {
            throw new InvalidArgumentException("monthCode \"{$monthCode}\" is out of range for hebrew calendar.");
        }
        if ($num <= 5) {
            return $num;
        }
        // M06-M12: in non-leap year → ordinal 6-12; in leap year → ordinal 7-13
        return $isLeap ? $num + 1 : $num;
    }

    /**
     * Chinese/Dangi monthCode → ordinal month.
     *
     * "M01"-"M12" are regular months; "MxxL" is a leap month following month xx.
     * The ordinal depends on which months precede it in the year (including any leap month).
     */
    private function chineseMonthCodeToMonth(string $monthCode, int $calYear): int
    {
        $isLeapCode = str_ends_with($monthCode, 'L');
        $baseCode = $isLeapCode ? substr($monthCode, 0, -1) : $monthCode;

        if (preg_match('/^M(\d{2})$/', $baseCode, $m) !== 1) {
            throw new InvalidArgumentException("Invalid monthCode \"{$monthCode}\" for calendar \"{$this->calendarId}\".");
        }
        $baseNum = (int) $m[1]; // 1-12
        if ($baseNum < 1 || $baseNum > 12) {
            throw new InvalidArgumentException("monthCode \"{$monthCode}\" is out of range for calendar \"{$this->calendarId}\".");
        }

        // Find the leap month in this year (if any) by scanning via ICU.
        $leapIcuMonth = $this->findChineseLeapMonthInYear($calYear);

        if ($isLeapCode) {
            // Leap month after base month xx. ICU month = baseNum - 1, IS_LEAP_MONTH = 1.
            // Ordinal = baseNum (for regular months before it) + 1 (for the leap month itself).
            // But if a leap month occurred before baseNum, we must account for it.
            // Actually for Chinese calendar, there is at most one leap month per year.
            // The ordinal of a leap month MxxL = baseNum + 1 (the regular month xx has ordinal baseNum,
            // then the leap month follows as baseNum+1).
            // But if the leap month is before another regular month, that shifts ordinals too.
            // Since this IS the leap month: ordinal = baseNum + 1.
            return $baseNum + 1;
        }

        // Regular month Mxx: ordinal = baseNum unless a leap month precedes it.
        // A leap month after ICU month $leapIcuMonth (0-indexed) means all regular months
        // with ICU month > leapIcuMonth have their ordinal incremented by 1.
        // baseNum maps to ICU month baseNum - 1.
        $ordinal = $baseNum;
        if ($leapIcuMonth >= 0 && ($baseNum - 1) > $leapIcuMonth) {
            $ordinal = $baseNum + 1;
        }
        return $ordinal;
    }

    /**
     * Finds the ICU month index (0-based) that has a leap month following it in the given
     * Chinese/Dangi calendar year, or -1 if none.
     */
    private function findChineseLeapMonthInYear(int $calYear): int
    {
        $savedTime = $this->intlCal->getTime();
        $icuYear = $calYear + ($this->calendarId === 'chinese' ? self::CHINESE_YEAR_OFFSET : self::DANGI_YEAR_OFFSET);

        $result = -1;
        for ($m = 0; $m <= 11; $m++) {
            $this->intlCal->clear();
            $this->intlCal->set(self::FIELD_EXTENDED_YEAR, $icuYear);
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $m);
            $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, 15);
            $this->intlCal->get(\IntlCalendar::FIELD_MONTH); // force resolution

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

    /**
     * Converts an ICU month + IS_LEAP_MONTH flag to an ordinal month in the
     * given Chinese/Dangi calendar year. If the source was a leap month that
     * does not exist in the target year, constrains to the non-leap version.
     */
    private function chineseIcuMonthToOrdinal(int $calYear, int $icuMonth, int $isLeap, string $overflow = 'constrain'): int
    {
        $leapIcuMonth = $this->findChineseLeapMonthInYear($calYear);

        if ($isLeap) {
            // Source was a leap month. If the target year has the same leap
            // month, return its ordinal. Otherwise constrain/reject.
            if ($leapIcuMonth === $icuMonth) {
                return $icuMonth + 2; // leap month ordinal = ICU month + 2
            }
            if ($overflow === 'reject') {
                $monthCode = sprintf('M%02dL', $icuMonth + 1);
                throw new InvalidArgumentException(
                    "monthCode \"{$monthCode}\" does not exist in this calendar year.",
                );
            }
            // Constrain: use the non-leap version of the same ICU month.
            $isLeap = 0;
        }

        // Regular month: ordinal = icuMonth + 1, shifted by +1 if a leap
        // month precedes it in this year.
        $ordinal = $icuMonth + 1;
        if ($leapIcuMonth >= 0 && $icuMonth > $leapIcuMonth) {
            $ordinal++;
        }
        return $ordinal;
    }

    // -------------------------------------------------------------------------
    // Era resolution
    // -------------------------------------------------------------------------

    /** Japanese era TC39 names to ICU era indices and start years. */
    private const JAPANESE_ERA_TO_START = [
        'reiwa' => 2019,
        'heisei' => 1989,
        'showa' => 1926,
        'taisho' => 1912,
        'meiji' => 1868,
    ];

    /**
     * Valid TC39 era strings per calendar (canonical forms only).
     *
     * @var array<string, list<string>>
     */
    private const VALID_ERAS = [
        'gregory' => ['ce', 'bce'],
        'japanese' => ['reiwa', 'heisei', 'showa', 'taisho', 'meiji', 'ce', 'bce'],
        'buddhist' => ['be'],
        'roc' => ['minguo', 'roc', 'before-roc', 'broc'],
        'coptic' => ['era0', 'era1', 'am'],
        'ethiopic' => ['era0', 'era1', 'am', 'aa'],
        'ethioaa' => ['era0', 'aa'],
        'hebrew' => ['am'],
        'indian' => ['shaka'],
        'islamic' => ['ah', 'bh'],
        'islamic-civil' => ['ah', 'bh'],
        'islamic-tbla' => ['ah', 'bh'],
        'islamic-umalqura' => ['ah', 'bh'],
        'persian' => ['ap'],
    ];

    /**
     * Era alias map: alternate/deprecated era names → canonical era name.
     *
     * @var array<string, string>
     */
    private const ERA_ALIASES = [
        'ad' => 'ce',
        'bc' => 'bce',
    ];

    public function resolveEra(string $era, int $eraYear): ?int
    {
        // Chinese/Dangi have no eras — signal to caller to ignore.
        if ($this->calendarId === 'chinese' || $this->calendarId === 'dangi') {
            return null;
        }

        // Canonicalize era aliases (e.g. 'ad' → 'ce', 'bc' → 'bce').
        $era = self::ERA_ALIASES[$era] ?? $era;

        $validEras = self::VALID_ERAS[$this->calendarId] ?? [];
        if (!in_array($era, $validEras, true)) {
            throw new InvalidArgumentException("Invalid era \"{$era}\" for calendar \"{$this->calendarId}\".");
        }

        return match ($this->calendarId) {
            'gregory' => $era === 'bce' ? 1 - $eraYear : $eraYear,
            'japanese' => $this->resolveJapaneseEra($era, $eraYear),
            'buddhist' => $eraYear,
            'roc' => ($era === 'before-roc' || $era === 'broc') ? 1 - $eraYear : $eraYear,
            'coptic' => ($era === 'era0') ? 1 - $eraYear : $eraYear,
            'ethiopic' => $this->resolveEthiopicEra($era, $eraYear),
            'ethioaa' => $eraYear,
            'hebrew' => $eraYear,
            'indian' => $eraYear,
            'persian' => $eraYear,
            default => $this->resolveIslamicEra($era, $eraYear),
        };
    }

    private function resolveJapaneseEra(string $era, int $eraYear): int
    {
        if ($era === 'bce') {
            return 1 - $eraYear;
        }
        if ($era === 'ce') {
            return $eraYear;
        }
        $startYear = self::JAPANESE_ERA_TO_START[$era]
            ?? throw new InvalidArgumentException("Unknown Japanese era \"{$era}\".");
        return $startYear + $eraYear - 1;
    }

    private function resolveEthiopicEra(string $era, int $eraYear): int
    {
        // 'aa' and 'era0' are the Amete Alem era; 'am' and 'era1' are Amete Mihret.
        // For ethiopic calendar, year property = FIELD_YEAR in the current era.
        // era0/aa: year = eraYear offset by 5500 from era1
        // era1/am: year = eraYear
        if ($era === 'era0' || $era === 'aa') {
            // Amete Alem year to Amete Mihret: year = eraYear - 5500
            return $eraYear - 5500;
        }
        return $eraYear;
    }

    private function resolveIslamicEra(string $era, int $eraYear): int
    {
        if ($era === 'bh') {
            return 1 - $eraYear;
        }
        return $eraYear;
    }

    // -------------------------------------------------------------------------
    // Internal: set the IntlCalendar from calendar-specific fields
    // -------------------------------------------------------------------------

    /**
     * Sets the IntlCalendar from calendar-specific year, ordinal month, and day.
     *
     * Maps TC39 ordinal month to the appropriate ICU month slot, which differs
     * for calendars with intercalary or leap months (Hebrew, Chinese/Dangi).
     */
    private function setCalendarFields(int $calYear, int $calMonth, int $calDay): void
    {
        $this->gregorianMaxDay = null;

        // For calendars using Gregorian months (same 1-12 months as ISO, just
        // a year offset), compute ISO date directly to avoid ICU's Julian cutover.
        // Set to day 1 of the target month, then set DAY_OF_MONTH to the requested day.
        // Day overflow is handled by resolveAndConstrain (for calendarToIso) or
        // is intentional (for dateAdd/dateUntil arithmetic).
        $isoYear = match ($this->calendarId) {
            'gregory', 'japanese' => $calYear,
            'buddhist' => $calYear - 543,
            'roc' => $calYear + self::ROC_YEAR_OFFSET,
            default => null,
        };
        if ($isoYear !== null) {
            $jdn = CalendarMath::toJulianDay($isoYear, $calMonth, 1);
            $epochMs = ($jdn - 2_440_588) * self::MS_PER_DAY;
            $this->intlCal->setTime((float) $epochMs);
            $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $calDay);
            return;
        }

        $this->intlCal->clear();

        if (in_array($this->calendarId, ['coptic', 'ethiopic'], true)) {
            $this->intlCal->set(self::FIELD_EXTENDED_YEAR, $calYear);
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $calMonth - 1);
        } elseif ($this->calendarId === 'hebrew') {
            $this->intlCal->set(\IntlCalendar::FIELD_YEAR, $calYear);
            $isLeap = (7 * $calYear + 1) % 19 < 7;
            $icuMonth = $this->hebrewOrdinalToIcuMonth($calMonth, $isLeap);
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $icuMonth);
        } elseif ($this->calendarId === 'chinese' || $this->calendarId === 'dangi') {
            $this->setChineseCalendarFromOrdinal($calYear, $calMonth, $calDay);
            return; // setChineseCalendarFromOrdinal sets DAY_OF_MONTH itself
        } else {
            $this->intlCal->set(\IntlCalendar::FIELD_YEAR, $calYear);
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $calMonth - 1);
        }

        $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $calDay);
    }

    /**
     * Sets the IntlCalendar from calendar-specific year, monthCode, and day.
     *
     * Converts the month code directly to ICU month slot, which avoids the
     * ordinal-to-ICU mapping needed by setCalendarFields().
     */
    /**
     * When the Gregorian shortcut is used, stores the pre-computed max day
     * for the target month (null for non-Gregorian calendars).
     */
    private ?int $gregorianMaxDay = null;

    private function setCalendarFieldsFromMonthCode(int $calYear, string $monthCode, int $calDay): void
    {
        // For Gregorian-based calendars, use direct ISO conversion to avoid Julian cutover.
        $isoYear = match ($this->calendarId) {
            'gregory', 'japanese' => $calYear,
            'buddhist' => $calYear - 543,
            'roc' => $calYear + self::ROC_YEAR_OFFSET,
            default => null,
        };
        if ($isoYear !== null) {
            $month = $this->defaultMonthCodeToMonth($monthCode);
            // Pre-compute the max day for this month for overflow handling in resolveAndConstrain.
            $this->gregorianMaxDay = CalendarMath::calcDaysInMonth($isoYear, $month);
            // Clamp the day to avoid JDN overflow into the next month.
            $clampedDay = min($calDay, $this->gregorianMaxDay);
            $jdn = CalendarMath::toJulianDay($isoYear, $month, $clampedDay);
            $epochMs = ($jdn - 2_440_588) * self::MS_PER_DAY;
            $this->intlCal->setTime((float) $epochMs);
            return;
        }
        $this->gregorianMaxDay = null;

        $this->intlCal->clear();

        if (in_array($this->calendarId, ['coptic', 'ethiopic'], true)) {
            $this->intlCal->set(self::FIELD_EXTENDED_YEAR, $calYear);
        } elseif ($this->calendarId === 'chinese') {
            $this->intlCal->set(self::FIELD_EXTENDED_YEAR, $calYear + self::CHINESE_YEAR_OFFSET);
        } elseif ($this->calendarId === 'dangi') {
            $this->intlCal->set(self::FIELD_EXTENDED_YEAR, $calYear + self::DANGI_YEAR_OFFSET);
        } else {
            $this->intlCal->set(\IntlCalendar::FIELD_YEAR, $calYear);
        }

        if ($this->calendarId === 'hebrew') {
            // Hebrew: M01-M05 → ICU 0-4, M05L → ICU 5, M06-M12 → ICU 6-12
            if ($monthCode === 'M05L') {
                $isLeap = (7 * $calYear + 1) % 19 < 7;
                if (!$isLeap) {
                    throw new InvalidArgumentException("monthCode \"M05L\" is only valid in Hebrew leap years; year {$calYear} is not a leap year.");
                }
                $this->intlCal->set(\IntlCalendar::FIELD_MONTH, 5);
            } else {
                if (preg_match('/^M(\d{2})$/', $monthCode, $m) !== 1) {
                    throw new InvalidArgumentException("Invalid monthCode \"{$monthCode}\" for hebrew calendar.");
                }
                $num = (int) $m[1];
                if ($num < 1 || $num > 12) {
                    throw new InvalidArgumentException("monthCode \"{$monthCode}\" is out of range for hebrew calendar.");
                }
                // M01-M05 → ICU 0-4, M06-M12 → ICU 6-12
                $icuMonth = $num <= 5 ? $num - 1 : $num;
                $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $icuMonth);
            }
        } elseif ($this->calendarId === 'chinese' || $this->calendarId === 'dangi') {
            // Chinese/Dangi: MxxL → ICU month xx-1 with IS_LEAP_MONTH=1
            $isLeapCode = str_ends_with($monthCode, 'L');
            $baseCode = $isLeapCode ? substr($monthCode, 0, -1) : $monthCode;
            if (preg_match('/^M(\d{2})$/', $baseCode, $m) !== 1) {
                throw new InvalidArgumentException("Invalid monthCode \"{$monthCode}\" for calendar \"{$this->calendarId}\".");
            }
            $baseNum = (int) $m[1];
            if ($baseNum < 1 || $baseNum > 12) {
                throw new InvalidArgumentException("monthCode \"{$monthCode}\" is out of range for calendar \"{$this->calendarId}\".");
            }
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $baseNum - 1);
            $this->intlCal->set(self::FIELD_IS_LEAP_MONTH, $isLeapCode ? 1 : 0);
        } else {
            // Standard calendars: M01-M12 → ICU 0-11; M13 → ICU 12 (coptic/ethiopic/ethioaa)
            $maxMonth = in_array($this->calendarId, ['coptic', 'ethiopic', 'ethioaa'], true) ? 13 : 12;
            if (preg_match('/^M(\d{2})$/', $monthCode, $m) !== 1) {
                throw new InvalidArgumentException("Invalid monthCode \"{$monthCode}\" for calendar \"{$this->calendarId}\".");
            }
            $num = (int) $m[1];
            if ($num < 1 || $num > $maxMonth) {
                throw new InvalidArgumentException("monthCode \"{$monthCode}\" is out of range for calendar \"{$this->calendarId}\".");
            }
            $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $num - 1);
        }

        $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $calDay);
    }

    /**
     * Reads back epoch ms from IntlCalendar, converts to ISO, and applies overflow handling.
     *
     * @return array{0: int, 1: int, 2: int} [isoYear, isoMonth, isoDay]
     */
    private function resolveAndConstrain(int $calDay, string $overflow): array
    {
        // Use pre-computed max day for Gregorian-based calendars (the JDN shortcut
        // clamps the day to avoid month overflow, so ICU's getActualMaximum would
        // report the wrong month's max if the day overflowed).
        $maxDay = $this->gregorianMaxDay
            ?? $this->intlCal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);

        if ($overflow === 'reject') {
            if ($calDay > $maxDay) {
                throw new InvalidArgumentException("Day {$calDay} exceeds maximum {$maxDay} for this calendar month.");
            }
        }
        // For Gregorian-based, the day was already clamped in setCalendarFieldsFromMonthCode.
        // For non-Gregorian with constrain, clamp via ICU.
        if ($overflow === 'constrain' && $this->gregorianMaxDay === null && $calDay > $maxDay) {
            $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $maxDay);
        }

        $epochMs = $this->intlCal->getTime();
        $jdn = (int) floor($epochMs / self::MS_PER_DAY) + 2_440_588;
        return CalendarMath::fromJulianDay($jdn);
    }

    /**
     * Maps TC39 ordinal month → ICU month slot for Hebrew calendar.
     *
     * Non-leap: TC39 1-5 → ICU 0-4, TC39 6-12 → ICU 6-12
     * Leap:     TC39 1-5 → ICU 0-4, TC39 6 → ICU 5, TC39 7-13 → ICU 6-12
     */
    private function hebrewOrdinalToIcuMonth(int $ordinal, bool $isLeap): int
    {
        if ($ordinal <= 5) {
            return $ordinal - 1;
        }
        if ($isLeap) {
            // ordinal 6 → ICU 5 (Adar I), ordinal 7-13 → ICU 6-12
            return $ordinal - 1;
        }
        // Non-leap: ordinal 6-12 → ICU 6-12
        return $ordinal;
    }

    /**
     * Sets Chinese/Dangi calendar fields from TC39 ordinal month.
     *
     * Finds which ICU month + leap combination matches the given ordinal for the year.
     */
    private function setChineseCalendarFromOrdinal(int $calYear, int $calMonth, int $calDay): void
    {
        // Find the leap month in this year (if any).
        $leapIcuMonth = $this->findChineseLeapMonthInYear($calYear);

        // Determine the target ICU month and IS_LEAP_MONTH flag.
        if ($leapIcuMonth < 0) {
            // No leap month: ordinal n → ICU month n-1, not leap.
            $icuMonth = $calMonth - 1;
            $isLeap = 0;
        } else {
            // The leap month follows ICU month $leapIcuMonth.
            // Regular months before and including the leap ICU month: ordinal = ICU month + 1.
            // The leap month itself: ordinal = leapIcuMonth + 2.
            // Regular months after the leap ICU month: ordinal = ICU month + 2.
            $leapOrdinal = $leapIcuMonth + 2; // ordinal of the leap month
            if ($calMonth <= $leapIcuMonth + 1) {
                // Before or at the regular month that has a leap copy.
                $icuMonth = $calMonth - 1;
                $isLeap = 0;
            } elseif ($calMonth === $leapOrdinal) {
                // This IS the leap month.
                $icuMonth = $leapIcuMonth;
                $isLeap = 1;
            } else {
                // After the leap month.
                $icuMonth = $calMonth - 2;
                $isLeap = 0;
            }
        }

        $icuYear = $calYear + ($this->calendarId === 'chinese' ? self::CHINESE_YEAR_OFFSET : self::DANGI_YEAR_OFFSET);

        $this->intlCal->clear();
        $this->intlCal->set(self::FIELD_EXTENDED_YEAR, $icuYear);
        $this->intlCal->set(\IntlCalendar::FIELD_MONTH, $icuMonth);
        $this->intlCal->set(self::FIELD_IS_LEAP_MONTH, $isLeap);
        $this->intlCal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $calDay);
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

    /** Japanese era start dates as [isoYear, isoMonth, isoDay]. */
    private const JAPANESE_ERA_STARTS = [
        'reiwa' => [2019, 5, 1],
        'heisei' => [1989, 1, 8],
        'showa' => [1926, 12, 25],
        'taisho' => [1912, 7, 30],
        'meiji' => [1868, 1, 25],
    ];

    /**
     * Returns the TC39 era string for a Japanese date from ISO fields (proleptic).
     */
    private function japaneseEraFromIso(int $isoYear, int $isoMonth, int $isoDay): string
    {
        foreach (self::JAPANESE_ERA_STARTS as $era => [$startY, $startM, $startD]) {
            if ($isoYear > $startY
                || ($isoYear === $startY && $isoMonth > $startM)
                || ($isoYear === $startY && $isoMonth === $startM && $isoDay >= $startD)) {
                return $era;
            }
        }
        return $isoYear >= 1 ? 'ce' : 'bce';
    }

    /**
     * Returns the TC39 eraYear for a Japanese date from ISO fields (proleptic).
     */
    private function japaneseEraYearFromIso(int $isoYear, int $isoMonth, int $isoDay): int
    {
        foreach (self::JAPANESE_ERA_STARTS as $era => [$startY, $startM, $startD]) {
            if ($isoYear > $startY
                || ($isoYear === $startY && $isoMonth > $startM)
                || ($isoYear === $startY && $isoMonth === $startM && $isoDay >= $startD)) {
                return $isoYear - $startY + 1;
            }
        }
        return $isoYear >= 1 ? $isoYear : 1 - $isoYear;
    }
}
