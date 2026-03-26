<?php

declare(strict_types=1);

namespace Temporal\Internal;

/** @internal */
final class CalendarMath
{
    /**
     * Floor division: rounds towards negative infinity (unlike intdiv which truncates towards zero).
     *
     * Required for correct Julian Day Number conversions with negative years.
     */
    public static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        $r = $a - ($q * $b);
        return $r < 0 ? $q - 1 : $q;
    }

    public static function isLeapYear(int $year): bool
    {
        return ($year % 4) === 0 && ($year % 100) !== 0 || ($year % 400) === 0;
    }

    /**
     * @param int<1, 12> $month
     * @return int<28, 31>
     */
    public static function calcDaysInMonth(int $year, int $month): int
    {
        return match ($month) {
            1, 3, 5, 7, 8, 10, 12 => 31,
            4, 6, 9, 11 => 30,
            2 => self::isLeapYear($year) ? 29 : 28,
        };
    }

    /**
     * ISO 8601 day of week using Sakamoto's algorithm.
     *
     * Returns 1 = Monday … 7 = Sunday.
     *
     * @return int<1, 7>
     */
    public static function isoWeekday(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $t */
        static $t = [0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4];
        if ($month < 3) {
            $year--;
        }
        $dow =
            (
                $year + intdiv(num1: $year, num2: 4)
                - intdiv(num1: $year, num2: 100)
                + intdiv(num1: $year, num2: 400)
                + $t[$month - 1]
                + $day
            )
            % 7;
        /** @var int<1, 7> Sakamoto maps 0→7, rest 1–6 unchanged */
        $result = $dow === 0 ? 7 : $dow;
        return $result;
    }

    /**
     * Ordinal day of the year (1 = January 1).
     *
     * @return int<1, 366>
     */
    public static function calcDayOfYear(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $cumDays */
        static $cumDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $result = $cumDays[$month - 1] + $day;
        if ($month > 2 && self::isLeapYear($year)) {
            $result++;
        }
        /** @var int<1, 366> $result — max 335 + 31 = 366 (Dec 31 in leap year) */
        $ordinal = $result;
        return $ordinal;
    }

    /**
     * Returns the ISO 8601 week number and week-year for the given date.
     *
     * @return array{week: int<1, 53>, year: int}
     * @psalm-suppress UnusedMethod — called from weekOfYear and yearOfWeek property hooks
     */
    public static function isoWeekInfo(int $year, int $month, int $day): array
    {
        $dow = self::isoWeekday($year, $month, $day);
        $ordinal = self::calcDayOfYear($year, $month, $day);

        // Move to the Thursday of this ISO week; its ordinal determines the week number.
        $thursdayOrdinal = $ordinal + (4 - $dow);

        if ($thursdayOrdinal < 1) {
            // Thursday fell in the previous year → last week of that year.
            $prevYear = $year - 1;
            $dec31Dow = self::isoWeekday($prevYear, 12, 31);
            $dec31Ord = self::isLeapYear($prevYear) ? 366 : 365;
            /** @var int<1, 53> ISO week of previous year's Dec 31 */
            $prevWeek = intdiv(num1: $dec31Ord + (4 - $dec31Dow) - 1, num2: 7) + 1;
            return ['week' => $prevWeek, 'year' => $prevYear];
        }

        $yearDays = self::isLeapYear($year) ? 366 : 365;
        if ($thursdayOrdinal > $yearDays) {
            // Thursday fell in the next year → week 1 of next year.
            return ['week' => 1, 'year' => $year + 1];
        }

        /** @var int<1, 53> thursdayOrdinal 1–366 maps to week 1–53 */
        $week = intdiv(num1: $thursdayOrdinal - 1, num2: 7) + 1;
        return ['week' => $week, 'year' => $year];
    }

    /**
     * Converts a proleptic Gregorian calendar date to a Julian Day Number.
     * Algorithm: Richards (2013).
     */
    public static function toJulianDay(int $year, int $month, int $day): int
    {
        $a = intdiv(num1: 14 - $month, num2: 12);
        $y = $year + 4800 - $a;
        $m = $month + (12 * $a) - 3;
        return (
            $day
            + intdiv(num1: (153 * $m) + 2, num2: 5)
            + (365 * $y)
            + self::floorDiv($y, 4)
            - self::floorDiv($y, 100)
            + self::floorDiv($y, 400)
            - 32_045
        );
    }

    /**
     * Converts a Julian Day Number to a proleptic Gregorian calendar date.
     *
     * @return array{0: int, 1: int<1, 12>, 2: int<1, 31>} [year, month, day]
     */
    public static function fromJulianDay(int $jdn): array
    {
        $a = $jdn + 32_044;
        $b = self::floorDiv((4 * $a) + 3, 146_097);
        $c = $a - self::floorDiv(146_097 * $b, 4);
        $d = self::floorDiv((4 * $c) + 3, 1_461);
        $e = $c - self::floorDiv(1_461 * $d, 4);
        $m = self::floorDiv((5 * $e) + 2, 153);
        /** @var int<1, 31> Richards algorithm guarantees day is 1–31 */
        $day = $e - intdiv(num1: (153 * $m) + 2, num2: 5) + 1;
        /** @var int<1, 12> Richards algorithm guarantees month is 1–12 */
        $month = $m + 3 - (12 * intdiv(num1: $m, num2: 10));
        $year = (100 * $b) + $d - 4800 + intdiv(num1: $m, num2: 10);
        return [$year, $month, $day];
    }
}
