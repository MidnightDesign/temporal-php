<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

/**
 * A calendar date without a time or time zone.
 *
 * Only the ISO 8601 calendar is supported. Years must fit in the range
 * representable by PHP integers.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-plaindate-objects
 */
final class PlainDate implements Stringable
{
    // -------------------------------------------------------------------------
    // Virtual (get-only) properties
    // -------------------------------------------------------------------------

    /**
     * Always "iso8601" — the only supported calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $calendarId { get => 'iso8601'; }

    /**
     * Always undefined (null) for the ISO 8601 calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?string $era { get => null; }

    /**
     * Always undefined (null) for the ISO 8601 calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $eraYear { get => null; }

    /**
     * Month code in "M01"–"M12" format.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode { get => sprintf('M%02d', $this->month); }

    /**
     * ISO 8601 day of week: 1 = Monday, 7 = Sunday.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $dayOfWeek { get => self::isoWeekday($this->year, $this->month, $this->day); }

    /**
     * Ordinal day of the year: 1–366.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $dayOfYear { get => self::calcDayOfYear($this->year, $this->month, $this->day); }

    /**
     * ISO 8601 week number: 1–53.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $weekOfYear { get => self::isoWeekInfo($this->year, $this->month, $this->day)['week']; }

    /**
     * ISO 8601 week-year (may differ from calendar year near year boundaries).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $yearOfWeek { get => self::isoWeekInfo($this->year, $this->month, $this->day)['year']; }

    /**
     * Number of days in this date's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInMonth { get => self::calcDaysInMonth($this->year, $this->month); }

    /**
     * Always 7 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInWeek { get => 7; }

    /**
     * 365 or 366, depending on whether this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInYear { get => self::isLeapYear($this->year) ? 366 : 365; }

    /**
     * Always 12 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $monthsInYear { get => 12; }

    /**
     * True if this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public bool $inLeapYear { get => self::isLeapYear($this->year); }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @throws InvalidArgumentException if year/month/day form an invalid ISO date.
     */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: month {$month} is out of range 1–12.",
            );
        }
        if ($day < 1) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$day} must be at least 1.",
            );
        }
        $daysInMonth = self::calcDaysInMonth($year, $month);
        if ($day > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$day} exceeds {$daysInMonth} for {$year}-{$month}.",
            );
        }
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Creates a PlainDate from another PlainDate, an ISO 8601 string, or a
     * property-bag array with 'year', 'month'/'monthCode', and 'day' fields.
     *
     * @param mixed $item     PlainDate, ISO 8601 date string, or property-bag array.
     * @param mixed $options  Options bag: ['overflow' => 'constrain'|'reject']
     * @throws InvalidArgumentException if the string is invalid or overflow option is invalid.
     * @throws \TypeError if the type cannot be interpreted as a PlainDate.
     * @psalm-api
     */
    public static function from(mixed $item, mixed $options = null): self
    {
        // Validate and extract overflow option (must be done before processing item).
        $overflow = 'constrain';
        if ($options !== null) {
            if (is_array($options) && array_key_exists('overflow', $options)) {
                /** @var mixed $ov */
                $ov = $options['overflow'];
                if (!is_string($ov)) {
                    throw new \TypeError('overflow option must be a string.');
                }
                if ($ov !== 'constrain' && $ov !== 'reject') {
                    throw new InvalidArgumentException(
                        "Invalid overflow value: \"{$ov}\"; must be 'constrain' or 'reject'.",
                    );
                }
                $overflow = $ov;
            }
        }

        if ($item instanceof self) {
            return new self($item->year, $item->month, $item->day);
        }
        if (is_string($item)) {
            return self::fromString($item);
        }
        if (is_array($item)) {
            return self::fromPropertyBag($item, $overflow);
        }
        throw new \TypeError(
            'PlainDate::from() expects a PlainDate, ISO 8601 string, or property-bag array; got '
            . get_debug_type($item) . '.',
        );
    }

    /**
     * Compares two PlainDates chronologically.
     *
     * Returns -1, 0, or +1 (or a value with the same sign).
     *
     * @param self|string $one
     * @param self|string $two
     * @psalm-api
     */
    public static function compare(mixed $one, mixed $two): int
    {
        $a = $one instanceof self ? $one : self::from($one);
        $b = $two instanceof self ? $two : self::from($two);

        if ($a->year !== $b->year) {
            return $a->year <=> $b->year;
        }
        if ($a->month !== $b->month) {
            return $a->month <=> $b->month;
        }
        return $a->day <=> $b->day;
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainDate with the specified fields overridden.
     *
     * Only 'year', 'month', 'monthCode', and 'day' fields are recognized.
     * Time fields are silently ignored. The 'calendar' and 'timeZone' keys
     * must not be present.
     *
     * @param array<array-key,mixed> $fields   Property bag with fields to override.
     * @param mixed                  $options  Options bag: ['overflow' => 'constrain'|'reject']
     * @throws \TypeError             if $fields contains 'calendar' or 'timeZone'.
     * @throws InvalidArgumentException if the resulting date is invalid (overflow: reject).
     * @psalm-api
     */
    public function with(array $fields, mixed $options = null): self
    {
        if (array_key_exists('calendar', $fields) || array_key_exists('timeZone', $fields)) {
            throw new \TypeError(
                'PlainDate::with() fields must not contain a calendar or timeZone property.',
            );
        }

        // Validate and extract overflow option.
        $overflow = 'constrain';
        if ($options !== null && is_array($options) && array_key_exists('overflow', $options)) {
            /** @var mixed $ov */
            $ov = $options['overflow'];
            if (!is_string($ov)) {
                throw new \TypeError('overflow option must be a string.');
            }
            if ($ov !== 'constrain' && $ov !== 'reject') {
                throw new InvalidArgumentException(
                    "Invalid overflow value: \"{$ov}\"; must be 'constrain' or 'reject'.",
                );
            }
            $overflow = $ov;
        }

        // Merge: start from current fields and override with provided ones.
        $year  = array_key_exists('year', $fields)
            ? (int) $fields['year']
            : $this->year;
        $month = $this->month;
        if (array_key_exists('month', $fields)) {
            /** @var mixed $m */
            $m     = $fields['month'];
            /** @phpstan-ignore cast.int */
            $month = (int) $m;
        } elseif (array_key_exists('monthCode', $fields)) {
            /** @var mixed $mc */
            $mc    = $fields['monthCode'];
            /** @phpstan-ignore cast.string */
            $month = (int) substr(string: (string) $mc, offset: 1);
        }
        $day = array_key_exists('day', $fields)
            ? (int) $fields['day']
            : $this->day;

        if ($overflow === 'constrain') {
            $month = max(1, min(12, $month));
            $maxDay = self::calcDaysInMonth($year, $month);
            $day = max(1, min($maxDay, $day));
        }

        return new self($year, $month, $day);
    }

    /**
     * Returns a new PlainDate with the given duration added.
     *
     * Years and months are added calendrically (month overflow is carried into
     * year; day is clamped or rejected per `overflow` option). Weeks and days
     * are added using Julian Day Numbers so they work correctly across month and
     * year boundaries. Time units are accepted but silently ignored (PlainDate
     * has no time component).
     *
     * @param Duration|array<array-key,mixed>|string $duration
     * @param mixed                                  $options  ['overflow' => 'constrain'|'reject']
     * @psalm-api
     */
    public function add(mixed $duration, mixed $options = null): self
    {
        $dur = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addDuration(1, $dur, $options);
    }

    /**
     * Returns a new PlainDate with the given duration subtracted.
     *
     * @param Duration|array<array-key,mixed>|string $duration
     * @param mixed                                  $options  ['overflow' => 'constrain'|'reject']
     * @psalm-api
     */
    public function subtract(mixed $duration, mixed $options = null): self
    {
        $dur = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addDuration(-1, $dur, $options);
    }

    /**
     * Returns true if this PlainDate is the same date as $other.
     *
     * @param mixed $other A PlainDate or ISO 8601 date string.
     * @psalm-api
     */
    public function equals(mixed $other): bool
    {
        $o = $other instanceof self ? $other : self::from($other);
        return $this->year === $o->year
            && $this->month === $o->month
            && $this->day === $o->day;
    }

    /**
     * @param mixed $options Options bag: ['calendarName' => 'auto'|'always'|'never'|'critical']
     * @throws InvalidArgumentException for invalid calendarName values.
     * @psalm-api
     */
    public function toString(mixed $options = null): string
    {
        $base = sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);

        $calendarName = 'auto';
        if ($options !== null && is_array($options) && array_key_exists('calendarName', $options)) {
            /** @var mixed $cn */
            $cn = $options['calendarName'];
            if (!is_string($cn)) {
                throw new \TypeError('calendarName option must be a string.');
            }
            $calendarName = $cn;
        }

        return match ($calendarName) {
            'auto', 'never' => $base,
            'always'        => $base . '[u-ca=iso8601]',
            'critical'      => $base . '[!u-ca=iso8601]',
            default         => throw new InvalidArgumentException(
                "Invalid calendarName value: \"{$calendarName}\".",
            ),
        };
    }

    /** @psalm-api */
    public function toJSON(): string
    {
        return $this->toString();
    }

    /**
     * Always throws TypeError — PlainDate must not be used in arithmetic context.
     *
     * @throws \TypeError always.
     * @psalm-return never
     * @psalm-api
     */
    public function valueOf(): never
    {
        throw new \TypeError('PlainDate objects are not orderable');
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parses an ISO 8601 date string into a PlainDate.
     *
     * Accepted formats:
     *   YYYY-MM-DD, ±YYYYYY-MM-DD
     * Optional trailing annotations ([u-ca=iso8601]) and time parts are accepted
     * and ignored (the date portion is extracted).
     *
     * @throws InvalidArgumentException for invalid or out-of-range dates.
     */
    private static function fromString(string $s): self
    {
        if ($s === '') {
            throw new InvalidArgumentException('PlainDate::from() received an empty string.');
        }
        // Extract the date portion: extended year or 4-digit year, then -MM-DD.
        if (preg_match('/^([+\-]?\d{4,6})-(\d{2})-(\d{2})/', $s, $m) !== 1
            && preg_match('/^(\d{4})(\d{2})(\d{2})/', $s, $m) !== 1
        ) {
            throw new InvalidArgumentException(
                "PlainDate::from() cannot parse \"{$s}\": expected YYYY-MM-DD format.",
            );
        }
        // Reject minus-zero extended year (-000000).
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $s) === 1) {
            throw new InvalidArgumentException('Cannot use negative zero as extended year.');
        }

        $year  = (int) $m[1];
        $month = (int) $m[2];
        $day   = (int) $m[3];

        return new self($year, $month, $day);
    }

    /**
     * Creates a PlainDate from a property-bag array.
     *
     * @param array<array-key,mixed> $bag
     * @param string $overflow 'constrain' (clamp) or 'reject' (throw on out-of-range).
     * @throws \TypeError if required fields are missing.
     * @throws InvalidArgumentException if the date is invalid.
     */
    private static function fromPropertyBag(array $bag, string $overflow = 'constrain'): self
    {
        if (!array_key_exists('year', $bag)) {
            throw new \TypeError('PlainDate property bag must have a year field.');
        }
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('PlainDate property bag must have a month or monthCode field.');
        }
        if (!array_key_exists('day', $bag)) {
            throw new \TypeError('PlainDate property bag must have a day field.');
        }

        /** @var mixed $yearRaw */
        $yearRaw = $bag['year'];
        /** @phpstan-ignore cast.int */
        $year = is_int($yearRaw) ? $yearRaw : (int) $yearRaw;

        if (array_key_exists('month', $bag)) {
            /** @var mixed $monthRaw */
            $monthRaw = $bag['month'];
            /** @phpstan-ignore cast.int */
            $month = is_int($monthRaw) ? $monthRaw : (int) $monthRaw;
        } else {
            /** @var mixed $monthCodeRaw */
            $monthCodeRaw = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $mc = is_string($monthCodeRaw) ? $monthCodeRaw : (string) $monthCodeRaw;
            $month = (int) substr(string: $mc, offset: 1);
        }

        /** @var mixed $dayRaw */
        $dayRaw = $bag['day'];
        /** @phpstan-ignore cast.int */
        $day = is_int($dayRaw) ? $dayRaw : (int) $dayRaw;

        if ($overflow === 'constrain') {
            $month = max(1, min(12, $month));
            $maxDay = self::calcDaysInMonth($year, $month);
            $day = max(1, min($maxDay, $day));
        }

        return new self($year, $month, $day);
    }

    /**
     * Returns the number of days in the given ISO calendar month.
     */
    /**
     * Shared implementation for add() and subtract().
     *
     * @param mixed $options ['overflow' => 'constrain'|'reject']
     */
    private function addDuration(int $sign, Duration $dur, mixed $options): self
    {
        $overflow = 'constrain';
        if ($options !== null && is_array($options) && array_key_exists('overflow', $options)) {
            /** @var mixed $ov */
            $ov = $options['overflow'];
            if (!is_string($ov)) {
                throw new \TypeError('overflow option must be a string.');
            }
            if ($ov !== 'constrain' && $ov !== 'reject') {
                throw new InvalidArgumentException(
                    "Invalid overflow value: \"{$ov}\"; must be 'constrain' or 'reject'.",
                );
            }
            $overflow = $ov;
        }

        $years  = $sign * (int) $dur->years;
        $months = $sign * (int) $dur->months;
        $days   = $sign * ((int) $dur->weeks * 7 + (int) $dur->days);

        // Add years/months calendrically.
        $newYear  = $this->year + $years;
        $newMonth = $this->month + $months;

        // Normalize month into 1–12, carrying into year.
        if ($newMonth > 12) {
            $newYear  += intdiv(num1: $newMonth - 1, num2: 12);
            $newMonth  = (($newMonth - 1) % 12) + 1;
        } elseif ($newMonth < 1) {
            $newYear  += intdiv(num1: $newMonth - 12, num2: 12);
            $newMonth  = (($newMonth - 1) % 12 + 12) % 12 + 1;
        }

        // Clamp or reject day within new month.
        $newDay  = $this->day;
        $maxDay  = self::calcDaysInMonth($newYear, $newMonth);
        if ($newDay > $maxDay) {
            if ($overflow === 'constrain') {
                $newDay = $maxDay;
            } else {
                throw new InvalidArgumentException(
                    "Day {$newDay} is out of range for {$newYear}-{$newMonth}.",
                );
            }
        }

        // Add days via Julian Day Number to handle month/year boundaries.
        if ($days !== 0) {
            $jdn = self::toJulianDay($newYear, $newMonth, $newDay) + $days;
            [$newYear, $newMonth, $newDay] = self::fromJulianDay($jdn);
        }

        return new self($newYear, $newMonth, $newDay);
    }

    /**
     * Converts a proleptic Gregorian calendar date to a Julian Day Number.
     * Algorithm: Richards (2013).
     */
    private static function toJulianDay(int $year, int $month, int $day): int
    {
        $a = intdiv(num1: 14 - $month, num2: 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;
        return $day
            + intdiv(num1: 153 * $m + 2, num2: 5)
            + 365 * $y
            + intdiv(num1: $y, num2: 4)
            - intdiv(num1: $y, num2: 100)
            + intdiv(num1: $y, num2: 400)
            - 32_045;
    }

    /**
     * Converts a Julian Day Number to a proleptic Gregorian calendar date.
     *
     * @return array{0: int, 1: int, 2: int} [year, month, day]
     */
    private static function fromJulianDay(int $jdn): array
    {
        $a = $jdn + 32_044;
        $b = intdiv(num1: 4 * $a + 3, num2: 146_097);
        $c = $a - intdiv(num1: 146_097 * $b, num2: 4);
        $d = intdiv(num1: 4 * $c + 3, num2: 1_461);
        $e = $c - intdiv(num1: 1_461 * $d, num2: 4);
        $m = intdiv(num1: 5 * $e + 2, num2: 153);
        $day   = $e - intdiv(num1: 153 * $m + 2, num2: 5) + 1;
        $month = $m + 3 - 12 * intdiv(num1: $m, num2: 10);
        $year  = 100 * $b + $d - 4800 + intdiv(num1: $m, num2: 10);
        return [$year, $month, $day];
    }

    private static function calcDaysInMonth(int $year, int $month): int
    {
        return match ($month) {
            1, 3, 5, 7, 8, 10, 12 => 31,
            4, 6, 9, 11 => 30,
            2 => self::isLeapYear($year) ? 29 : 28,
            default => 0,
        };
    }

    private static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    /**
     * ISO 8601 day of week using Sakamoto's algorithm.
     *
     * Returns 1 = Monday … 7 = Sunday.
     */
    private static function isoWeekday(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $t */
        static $t = [0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4];
        if ($month < 3) {
            $year--;
        }
        $dow = ($year
            + intdiv(num1: $year, num2: 4)
            - intdiv(num1: $year, num2: 100)
            + intdiv(num1: $year, num2: 400)
            + $t[$month - 1]
            + $day) % 7;
        return $dow === 0 ? 7 : $dow;
    }

    /**
     * Ordinal day of the year (1 = January 1).
     */
    private static function calcDayOfYear(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $cumDays */
        static $cumDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $result = $cumDays[$month - 1] + $day;
        if ($month > 2 && self::isLeapYear($year)) {
            $result++;
        }
        return $result;
    }

    /**
     * Returns the ISO 8601 week number and week-year for the given date.
     *
     * @return array{week: int, year: int}
     * @psalm-suppress UnusedMethod — called from weekOfYear and yearOfWeek property hooks
     */
    private static function isoWeekInfo(int $year, int $month, int $day): array
    {
        $dow     = self::isoWeekday($year, $month, $day);
        $ordinal = self::calcDayOfYear($year, $month, $day);

        // Move to the Thursday of this ISO week; its ordinal determines the week number.
        $thursdayOrdinal = $ordinal + (4 - $dow);

        if ($thursdayOrdinal < 1) {
            // Thursday fell in the previous year → last week of that year.
            $prevYear    = $year - 1;
            $dec31Dow    = self::isoWeekday($prevYear, 12, 31);
            $dec31Ord    = self::isLeapYear($prevYear) ? 366 : 365;
            $prevWeek    = intdiv(num1: $dec31Ord + (4 - $dec31Dow) - 1, num2: 7) + 1;
            return ['week' => $prevWeek, 'year' => $prevYear];
        }

        $yearDays = self::isLeapYear($year) ? 366 : 365;
        if ($thursdayOrdinal > $yearDays) {
            // Thursday fell in the next year → week 1 of next year.
            return ['week' => 1, 'year' => $year + 1];
        }

        $week = intdiv(num1: $thursdayOrdinal - 1, num2: 7) + 1;
        return ['week' => $week, 'year' => $year];
    }
}
