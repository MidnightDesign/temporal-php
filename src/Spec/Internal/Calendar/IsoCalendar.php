<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal\Calendar;

use InvalidArgumentException;
use Temporal\Spec\Internal\CalendarMath;

/**
 * ISO 8601 calendar implementation.
 *
 * For ISO, calendar fields are identical to the stored ISO fields. This class
 * consolidates all ISO-specific calendar logic previously scattered across
 * CalendarMath and the Temporal types.
 *
 * @internal
 */
final class IsoCalendar implements CalendarProtocol
{
    public function id(): string
    {
        return 'iso8601';
    }

    // -------------------------------------------------------------------------
    // ISO -> Calendar field projection (identity for ISO)
    // -------------------------------------------------------------------------

    public function year(int $isoYear, int $isoMonth, int $isoDay): int
    {
        return $isoYear;
    }

    public function month(int $isoYear, int $isoMonth, int $isoDay): int
    {
        return $isoMonth;
    }

    public function day(int $isoYear, int $isoMonth, int $isoDay): int
    {
        return $isoDay;
    }

    public function era(int $isoYear, int $isoMonth, int $isoDay): ?string
    {
        return null;
    }

    public function eraYear(int $isoYear, int $isoMonth, int $isoDay): ?int
    {
        return null;
    }

    public function monthCode(int $isoYear, int $isoMonth, int $isoDay): string
    {
        return sprintf('M%02d', $isoMonth);
    }

    public function dayOfYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        return CalendarMath::calcDayOfYear($isoYear, $isoMonth, $isoDay);
    }

    public function daysInMonth(int $isoYear, int $isoMonth, int $isoDay): int
    {
        return CalendarMath::calcDaysInMonth($isoYear, $isoMonth);
    }

    public function daysInYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        return CalendarMath::isLeapYear($isoYear) ? 366 : 365;
    }

    public function monthsInYear(int $isoYear, int $isoMonth, int $isoDay): int
    {
        return 12;
    }

    public function inLeapYear(int $isoYear, int $isoMonth, int $isoDay): bool
    {
        return CalendarMath::isLeapYear($isoYear);
    }

    // -------------------------------------------------------------------------
    // Calendar -> ISO field resolution (identity for ISO)
    // -------------------------------------------------------------------------

    public function calendarToIso(int $calYear, int $calMonth, int $calDay, string $overflow): array
    {
        return self::regulateIsoDate($calYear, $calMonth, $calDay, $overflow);
    }

    public function calendarToIsoFromMonthCode(int $calYear, string $monthCode, int $calDay, string $overflow): array
    {
        $month = CalendarMath::monthCodeToMonth($monthCode);

        return self::regulateIsoDate($calYear, $month, $calDay, $overflow);
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
        $newYear = $isoYear + $years;
        $newMonth = $isoMonth + $months;

        // Normalize month into 1-12, carrying into year.
        if ($newMonth > 12) {
            $newYear += intdiv(num1: $newMonth - 1, num2: 12);
            $newMonth = (($newMonth - 1) % 12) + 1;
        } elseif ($newMonth < 1) {
            $newYear += intdiv(num1: $newMonth - 12, num2: 12);
            $newMonth = (((($newMonth - 1) % 12) + 12) % 12) + 1;
        }

        // Clamp or reject day within new month.
        $newDay = $isoDay;
        $maxDay = CalendarMath::calcDaysInMonth($newYear, $newMonth);
        if ($newDay > $maxDay) {
            if ($overflow === 'constrain') {
                $newDay = $maxDay;
            } else {
                throw new InvalidArgumentException("Day {$newDay} is out of range for {$newYear}-{$newMonth}.");
            }
        }

        // Add weeks and days via Julian Day Number.
        $totalDays = ($weeks * 7) + $days;
        if ($totalDays !== 0) {
            $jdn = CalendarMath::toJulianDay($newYear, $newMonth, $newDay) + $totalDays;
            [$newYear, $newMonth, $newDay] = CalendarMath::fromJulianDay($jdn);
        }

        return [$newYear, $newMonth, $newDay];
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

        // Year/month decomposition.
        $sign = $isoY2 > $isoY1
            || ($isoY2 === $isoY1 && ($isoM2 > $isoM1 || ($isoM2 === $isoM1 && $isoD2 >= $isoD1)))
            ? 1 : -1;

        if ($sign < 0) {
            [$isoY1, $isoM1, $isoD1, $isoY2, $isoM2, $isoD2] =
                [$isoY2, $isoM2, $isoD2, $isoY1, $isoM1, $isoD1];
        }

        $years = $isoY2 - $isoY1;
        $months = $isoM2 - $isoM1;

        if ($months < 0) {
            $years--;
            $months += 12;
        }

        if ($isoD2 < $isoD1) {
            if ($months > 0) {
                $months--;
            } else {
                $years--;
                $months = 11;
            }
        }

        if ($largestUnit === 'month') {
            $months += $years * 12;
            $years = 0;
        }

        // Compute remaining days: anchor forward from date1 by years+months.
        $anchorMonth = $isoM1 + $months;
        $anchorYear = $isoY1 + $years;
        if ($anchorMonth > 12) {
            $anchorYear++;
            $anchorMonth -= 12;
        }
        $anchorMaxDay = CalendarMath::calcDaysInMonth($anchorYear, $anchorMonth);
        $anchorDay = min($isoD1, $anchorMaxDay);
        $days = CalendarMath::toJulianDay($isoY2, $isoM2, $isoD2)
            - CalendarMath::toJulianDay($anchorYear, $anchorMonth, $anchorDay);

        return [$sign * $years, $sign * $months, 0, $sign * $days];
    }

    // -------------------------------------------------------------------------
    // Month code utilities
    // -------------------------------------------------------------------------

    public function monthCodeToMonth(string $monthCode, int $calYear): int
    {
        return CalendarMath::monthCodeToMonth($monthCode);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Validates and optionally constrains an ISO date.
     *
     * @return array{0: int, 1: int, 2: int} [isoYear, isoMonth, isoDay]
     */
    private static function regulateIsoDate(int $year, int $month, int $day, string $overflow): array
    {
        if ($overflow === 'constrain') {
            $month = max(1, min(12, $month));
            $day = max(1, min(CalendarMath::calcDaysInMonth($year, $month), $day));
        } else {
            if ($month < 1 || $month > 12) {
                throw new InvalidArgumentException("Invalid PlainDate: month {$month} is out of range 1-12.");
            }
            $maxDay = CalendarMath::calcDaysInMonth($year, $month);
            if ($day < 1 || $day > $maxDay) {
                throw new InvalidArgumentException("Invalid PlainDate: day {$day} is out of range for {$year}-{$month}.");
            }
        }

        return [$year, $month, $day];
    }
}
