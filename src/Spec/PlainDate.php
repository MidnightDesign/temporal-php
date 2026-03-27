<?php

declare(strict_types=1);

namespace Temporal\Spec;

use InvalidArgumentException;
use Stringable;
use Temporal\Spec\Internal\Calendar\CalendarFactory;
use Temporal\Spec\Internal\CalendarMath;
use Temporal\Spec\Internal\TemporalSerde;

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
    use TemporalSerde;

    // -------------------------------------------------------------------------
    // Virtual (get-only) properties
    // -------------------------------------------------------------------------

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?string $era {
        get => CalendarFactory::get($this->calendarId)->era($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $eraYear {
        get => CalendarFactory::get($this->calendarId)->eraYear($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode {
        get => CalendarFactory::get($this->calendarId)->monthCode($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty
     * @psalm-api
     */
    public int $year {
        get => $this->calendarId === 'iso8601'
            ? $this->isoYear
            : CalendarFactory::get($this->calendarId)->year($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty
     * @psalm-api
     */
    public int $month {
        get => $this->calendarId === 'iso8601'
            ? $this->isoMonth
            : CalendarFactory::get($this->calendarId)->month($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty
     * @psalm-api
     */
    public int $day {
        get => $this->calendarId === 'iso8601'
            ? $this->isoDay
            : CalendarFactory::get($this->calendarId)->day($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * ISO 8601 day of week: 1 = Monday, 7 = Sunday.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<1, 7>
     */
    public int $dayOfWeek {
        get => CalendarMath::isoWeekday($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * Ordinal day of the year: 1–366.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<1, 366>
     */
    public int $dayOfYear {
        get => CalendarMath::calcDayOfYear($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * ISO 8601 week number: 1–53.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<1, 53>
     */
    public int $weekOfYear {
        get => CalendarMath::isoWeekInfo($this->isoYear, $this->isoMonth, $this->isoDay)['week'];
    }

    /**
     * ISO 8601 week-year (may differ from calendar year near year boundaries).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $yearOfWeek {
        get => CalendarMath::isoWeekInfo($this->isoYear, $this->isoMonth, $this->isoDay)['year'];
    }

    /**
     * Number of days in this date's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<28, 31>
     */
    public int $daysInMonth {
        get => CalendarFactory::get($this->calendarId)->daysInMonth($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * Always 7 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<7, 7>
     */
    public int $daysInWeek {
        get => 7;
    }

    /**
     * 365 or 366, depending on whether this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<365, 366>
     */
    public int $daysInYear {
        get => CalendarFactory::get($this->calendarId)->daysInYear($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * Always 12 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<12, 12>
     */
    public int $monthsInYear {
        get => CalendarFactory::get($this->calendarId)->monthsInYear($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * True if this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public bool $inLeapYear {
        get => CalendarFactory::get($this->calendarId)->inLeapYear($this->isoYear, $this->isoMonth, $this->isoDay);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /** @psalm-api */
    public readonly string $calendarId;
    /** @psalm-api */
    public readonly int $isoYear;
    /**
     * @psalm-api
     * @var int<1, 12>
     */
    public readonly int $isoMonth;
    /**
     * @psalm-api
     * @var int<1, 31>
     */
    public readonly int $isoDay;

    /**
     * @throws InvalidArgumentException if year/month/day form an invalid ISO date or are infinite.
     * @throws InvalidArgumentException if calendar is provided and is not "iso8601" (case-insensitive, ASCII-only).
     */
    public function __construct(int|float $year, int|float $month, int|float $day, ?string $calendar = null)
    {
        if ($calendar !== null) {
            $calendar = CalendarFactory::canonicalize($calendar);
        }
        $this->calendarId = $calendar ?? 'iso8601';
        if (!is_finite((float) $year) || !is_finite((float) $month) || !is_finite((float) $day)) {
            throw new InvalidArgumentException('Invalid PlainDate: year, month, and day must be finite numbers.');
        }
        $this->isoYear = (int) $year;
        $monthInt = (int) $month;
        if ($monthInt < 1 || $monthInt > 12) {
            throw new InvalidArgumentException("Invalid PlainDate: month {$monthInt} is out of range 1–12.");
        }
        $this->isoMonth = $monthInt;
        $dayInt = (int) $day;
        if ($dayInt < 1) {
            throw new InvalidArgumentException("Invalid PlainDate: day {$dayInt} must be at least 1.");
        }
        $daysInMonth = CalendarMath::calcDaysInMonth($this->isoYear, $this->isoMonth);
        if ($dayInt > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$dayInt} exceeds {$daysInMonth} for {$this->isoYear}-{$this->isoMonth}.",
            );
        }
        /** @psalm-suppress InvalidPropertyAssignmentValue — $dayInt <= $daysInMonth <= 31 */
        $this->isoDay = $dayInt;
        // TC39 range: Apr 19 −271821 … Sep 13 +275760.
        $epochDays = CalendarMath::toJulianDay($this->isoYear, $this->isoMonth, $this->isoDay) - 2_440_588;
        if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: {$this->isoYear}-{$this->isoMonth}-{$this->isoDay} is outside the representable range.",
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
     * @param self|string|array<array-key, mixed>|object $item     PlainDate, ISO 8601 date string, or property-bag array.
     * @param array<array-key, mixed>|object|null $options Options bag: ['overflow' => 'constrain'|'reject']
     * @throws InvalidArgumentException if the string is invalid or overflow option is invalid.
     * @throws \TypeError if the type cannot be interpreted as a PlainDate.
     * @psalm-api
     */
    public static function from(string|array|object $item, array|object|null $options = null): self
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
            return new self($item->isoYear, $item->isoMonth, $item->isoDay, $item->calendarId);
        }
        if (is_string($item)) {
            return self::fromString($item);
        }
        if (is_array($item)) {
            return self::fromPropertyBag($item, $overflow);
        }
        throw new \TypeError(sprintf(
            'PlainDate::from() expects a PlainDate, ISO 8601 string, or property-bag array; got %s.',
            get_debug_type($item),
        ));
    }

    /**
     * Compares two PlainDates chronologically.
     *
     * Returns -1, 0, or +1 (or a value with the same sign).
     *
     * @param self|string|array<array-key, mixed>|object $one
     * @param self|string|array<array-key, mixed>|object $two
     * @psalm-api
     */
    public static function compare(string|array|object $one, string|array|object $two): int
    {
        $a = $one instanceof self ? $one : self::from($one);
        $b = $two instanceof self ? $two : self::from($two);

        if ($a->isoYear !== $b->isoYear) {
            return $a->isoYear <=> $b->isoYear;
        }
        if ($a->isoMonth !== $b->isoMonth) {
            return $a->isoMonth <=> $b->isoMonth;
        }
        return $a->isoDay <=> $b->isoDay;
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
     * @param array<array-key, mixed>|object|null       $options Options bag: ['overflow' => 'constrain'|'reject']
     * @throws \TypeError             if $fields contains 'calendar' or 'timeZone'.
     * @throws InvalidArgumentException if the resulting date is invalid (overflow: reject).
     * @psalm-api
     */
    public function with(array $fields, array|object|null $options = null): self
    {
        if (array_key_exists('calendar', $fields) || array_key_exists('timeZone', $fields)) {
            throw new \TypeError('PlainDate::with() fields must not contain a calendar or timeZone property.');
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

        $calendar = $this->calendarId !== 'iso8601'
            ? CalendarFactory::get($this->calendarId)
            : null;

        // Merge: start from current fields (calendar-projected for non-ISO, ISO for ISO).
        $year = $calendar !== null ? $this->year : $this->isoYear;
        if (array_key_exists('year', $fields)) {
            /** @var mixed $yr */
            $yr = $fields['year'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $yr)) {
                throw new InvalidArgumentException('PlainDate::with() year must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $year = (int) $yr;
        }

        $month = $calendar !== null ? $this->month : $this->isoMonth;
        $monthCode = null;
        $hasMonth = array_key_exists('month', $fields);
        $hasMonthCode = array_key_exists('monthCode', $fields);
        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $fields['monthCode'];
            /** @phpstan-ignore cast.string */
            $monthCode = (string) $mc;
            $month = $calendar !== null
                ? $calendar->monthCodeToMonth($monthCode, $year)
                : CalendarMath::monthCodeToMonth($monthCode);
        }
        if ($hasMonth) {
            /** @var mixed $m */
            $m = $fields['month'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $m)) {
                throw new InvalidArgumentException('PlainDate::with() month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = (int) $m;
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        $day = $calendar !== null ? $this->day : $this->isoDay;
        if (array_key_exists('day', $fields)) {
            /** @var mixed $dy */
            $dy = $fields['day'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $dy)) {
                throw new InvalidArgumentException('PlainDate::with() day must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $day = (int) $dy;
        }

        // month < 1 and day < 1 are always invalid (cannot constrain below minimum).
        if ($month < 1) {
            throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }

        // Non-ISO calendar: resolve back to ISO via calendar protocol.
        if ($calendar !== null) {
            if ($hasMonthCode && $monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, $day, $overflow);
            } else {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, $day, $overflow);
            }
            return new self($isoY, $isoM, $isoD, $this->calendarId);
        }

        if ($overflow === 'constrain') {
            /**
             * @var int<1, 12>
             * @psalm-suppress UnnecessaryVarAnnotation — Mago can't narrow min()
             */
            $month = min(12, $month);
            $maxDay = CalendarMath::calcDaysInMonth($year, $month);
            $day = min($maxDay, $day);
        }

        return new self($year, $month, $day, $this->calendarId);
    }

    /**
     * Returns a new PlainDate with the given duration added.
     *
     * Years and months are added calendrically (month overflow is carried into
     * year; day is clamped or rejected per `overflow` option). Weeks and days
     * are added using Julian Day Numbers so they work correctly across month and
     * year boundaries. Time units are balanced into days before applying.
     *
     * @param Duration|string|array<array-key, mixed>|object $duration
     * @param array<array-key, mixed>|object|null                        $options ['overflow' => 'constrain'|'reject']
     * @psalm-api
     */
    public function add(string|array|object $duration, array|object|null $options = null): self
    {
        $dur = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addDuration(1, $dur, $options);
    }

    /**
     * Returns a new PlainDate with the given duration subtracted.
     *
     * @param Duration|string|array<array-key, mixed>|object $duration
     * @param array<array-key, mixed>|object|null                        $options ['overflow' => 'constrain'|'reject']
     * @psalm-api
     */
    public function subtract(string|array|object $duration, array|object|null $options = null): self
    {
        $dur = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addDuration(-1, $dur, $options);
    }

    /**
     * Returns the Duration from $other to this date (this − other).
     *
     * Supports largestUnit, smallestUnit, roundingMode, and roundingIncrement options.
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainDate or ISO 8601 date string.
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function since(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        return self::diffDate($this, $o, $this, $options);
    }

    /**
     * Returns the Duration from this date to $other (other − this).
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainDate or ISO 8601 date string.
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function until(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        return self::diffDate($o, $this, $this, $options);
    }

    /**
     * Returns true if this PlainDate is the same date as $other.
     *
     * @param self|string|array<array-key, mixed>|object $other A PlainDate or ISO 8601 date string.
     * @psalm-api
     */
    public function equals(string|array|object $other): bool
    {
        $o = $other instanceof self ? $other : self::from($other);
        return $this->isoYear === $o->isoYear
            && $this->isoMonth === $o->isoMonth
            && $this->isoDay === $o->isoDay
            && $this->calendarId === $o->calendarId;
    }

    /**
     * @param array<array-key, mixed>|object|null $options Options bag: ['calendarName' => 'auto'|'always'|'never'|'critical']
     * @throws InvalidArgumentException for invalid calendarName values.
     * @psalm-api
     */
    #[\Override]
    public function toString(array|object|null $options = null): string
    {
        // TC39: years 0–9999 → 4 digits; years outside → ±YYYYYY (6 digits with sign prefix).
        if ($this->isoYear < 0) {
            $yearStr = sprintf('-%06d', abs($this->isoYear));
        } elseif ($this->isoYear > 9999) {
            $yearStr = sprintf('+%06d', $this->isoYear);
        } else {
            $yearStr = sprintf('%04d', $this->isoYear);
        }
        $base = sprintf('%s-%02d-%02d', $yearStr, $this->isoMonth, $this->isoDay);

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
            'auto' => $this->calendarId !== 'iso8601'
                ? sprintf('%s[u-ca=%s]', $base, $this->calendarId)
                : $base,
            'never' => $base,
            'always' => sprintf('%s[u-ca=%s]', $base, $this->calendarId),
            'critical' => sprintf('%s[!u-ca=%s]', $base, $this->calendarId),
            default => throw new InvalidArgumentException("Invalid calendarName value: \"{$calendarName}\"."),
        };
    }

    /**
     * Combines this date with a time to produce a PlainDateTime.
     *
     * If no argument is given, midnight (00:00:00) is used.
     * Accepts a PlainTime, a time string, or a property-bag array.
     *
     * @param PlainTime|string|array<array-key, mixed>|object|null $time PlainTime, string, array, or null for midnight.
     * @throws \TypeError if $time is an invalid type (number, boolean, etc.).
     * @throws InvalidArgumentException if the string is invalid or the result is out of range.
     * @psalm-api
     */
    public function toPlainDateTime(string|array|object|null $time = null): PlainDateTime
    {
        if (func_num_args() === 0) {
            return new PlainDateTime($this->isoYear, $this->isoMonth, $this->isoDay, calendar: $this->calendarId);
        }
        if ($time === null) {
            throw new \TypeError(
                'PlainDate::toPlainDateTime() argument must be a PlainTime, string, or property bag; null given.',
            );
        }
        $t = $time instanceof PlainTime ? $time : PlainTime::from($time);
        return new PlainDateTime(
            $this->isoYear,
            $this->isoMonth,
            $this->isoDay,
            $t->hour,
            $t->minute,
            $t->second,
            $t->millisecond,
            $t->microsecond,
            $t->nanosecond,
            $this->calendarId,
        );
    }

    /**
     * Converts this date to a ZonedDateTime in the given timezone.
     *
     * Accepts a timezone string or an array with 'timeZone' and optional 'plainTime' keys.
     *
     * @param string|array<array-key, mixed> $item Timezone string or property bag with 'timeZone' (and optional 'plainTime').
     * @throws InvalidArgumentException if the timezone is invalid or the result is out of range.
     * @psalm-api
     */
    public function toZonedDateTime(string|array $item): ZonedDateTime
    {
        if (is_string($item)) {
            // String argument = timezone ID; combine with midnight.
            $tzId = ZonedDateTime::normalizeTimezoneId($item);
            return $this->createZdt($tzId, 0, 0, 0, 0, 0, 0);
        }
        // Property bag: must have 'timeZone' key.
        if (!array_key_exists('timeZone', $item)) {
            throw new \TypeError('PlainDate::toZonedDateTime() property bag must have a timeZone property.');
        }
        /** @var mixed $tzRaw */
        $tzRaw = $item['timeZone'];
        if (!is_string($tzRaw)) {
            throw new \TypeError(sprintf(
                'PlainDate::toZonedDateTime() timeZone must be a string; got %s.',
                get_debug_type($tzRaw),
            ));
        }
        $tzId = ZonedDateTime::normalizeTimezoneId($tzRaw);

        // Optional plainTime: if the key is present, pass through PlainTime::from()
        // (null throws TypeError, matching JS behavior where null !== undefined).
        $h = $m = $s = $ms = $us = $ns = 0;
        if (array_key_exists('plainTime', $item)) {
            /** @var mixed $ptRaw */
            $ptRaw = $item['plainTime'];
            if ($ptRaw === null) {
                throw new \TypeError('PlainDate::toZonedDateTime() plainTime must not be null.');
            }
            if (!is_string($ptRaw) && !is_array($ptRaw) && !is_object($ptRaw)) {
                throw new \TypeError(sprintf(
                    'PlainDate::toZonedDateTime() plainTime must be a PlainTime, string, or property bag; got %s.',
                    get_debug_type($ptRaw),
                ));
            }
            $t = $ptRaw instanceof PlainTime ? $ptRaw : PlainTime::from($ptRaw);
            $h = $t->hour;
            $m = $t->minute;
            $s = $t->second;
            $ms = $t->millisecond;
            $us = $t->microsecond;
            $ns = $t->nanosecond;
        }
        return $this->createZdt($tzId, $h, $m, $s, $ms, $us, $ns);
    }

    /**
     * Returns a PlainYearMonth from this date's year and month.
     *
     * @psalm-api
     */
    public function toPlainYearMonth(): PlainYearMonth
    {
        return new PlainYearMonth($this->isoYear, $this->isoMonth, $this->calendarId);
    }

    /**
     * Returns a PlainMonthDay from this date's month and day.
     *
     * @psalm-api
     */
    public function toPlainMonthDay(): PlainMonthDay
    {
        return new PlainMonthDay($this->isoMonth, $this->isoDay, $this->calendarId);
    }

    /**
     * Returns a new PlainDate with the specified calendar.
     *
     * Accepts a bare calendar ID or an ISO date string from which the calendar is extracted.
     *
     * @throws InvalidArgumentException if the calendar is unsupported.
     * @psalm-api
     */
    public function withCalendar(string $calendar): self
    {
        $calId = ZonedDateTime::extractCalendarFromString($calendar);
        return new self($this->isoYear, $this->isoMonth, $this->isoDay, $calId);
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private const int NS_PER_MILLISECOND = 1_000_000;
    private const int NS_PER_MICROSECOND = 1_000;

    /**
     * Creates a ZonedDateTime from this date combined with the given time fields and timezone.
     *
     * Computes epoch seconds from Julian Day Numbers to handle extreme years correctly.
     *
     * @throws InvalidArgumentException if the resulting epoch nanoseconds are out of range.
     */
    private function createZdt(string $tzId, int $h, int $m, int $s, int $ms, int $us, int $ns): ZonedDateTime
    {
        // Compute wall-clock seconds from epoch days + time-of-day (avoids DateTimeImmutable
        // year-formatting issues with extended years > 9999 or negative years).
        $epochDays = CalendarMath::toJulianDay($this->isoYear, $this->isoMonth, $this->isoDay) - 2_440_588;
        $wallSec = ($epochDays * 86_400) + ($h * 3600) + ($m * 60) + $s;
        $epochSec = ZonedDateTime::wallSecToEpochSec($wallSec, $tzId);

        $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;

        return ZonedDateTime::createFromEpochParts($epochSec, $subNs, $tzId, $this->calendarId);
    }

    /**
     * Parses an ISO 8601 date string into a PlainDate.
     *
     * Accepted formats:
     *   YYYY-MM-DD, ±YYYYYY-MM-DD, YYYYMMDD, ±YYYYYYMMDD
     * Optional trailing time, offset, and bracket annotations are accepted;
     * only the date portion is used. Z (UTC designator) is not valid for PlainDate.
     *
     * @throws InvalidArgumentException for invalid or out-of-range dates.
     */
    private static function fromString(string $s): self
    {
        if ($s === '') {
            throw new InvalidArgumentException('PlainDate::from() received an empty string.');
        }
        // Reject non-ASCII minus sign (U+2212 = \xe2\x88\x92).
        if (str_contains($s, "\u{2212}")) {
            throw new InvalidArgumentException(
                "PlainDate::from() cannot parse \"{$s}\": non-ASCII minus sign is not allowed.",
            );
        }
        // Reject more than 9 fractional-second digits anywhere (time part or offset fraction).
        if (preg_match('/[.,]\d{10,}/', $s) === 1) {
            throw new InvalidArgumentException(
                "PlainDate::from() cannot parse \"{$s}\": fractional seconds may have at most 9 digits.",
            );
        }

        // Full anchored regex for a PlainDate string.
        // Date part: YYYY-MM-DD | ±YYYYYY-MM-DD | YYYYMMDD | ±YYYYYYMMDD
        // Optional time: T + HH[:MM[:SS[frac]]]  (fraction only after SS)
        // Optional non-Z offset (only when time is present): ±HH[:MM[:SS[frac]]]
        // Optional bracket annotations
        // Z (UTC designator) is NEVER valid for PlainDate.
        // date: year + rest, optional T+HH:MM:SS.frac, optional offset, bracket annotations
        $pattern = '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})(?:[Tt ](\d{2})(?::?(\d{2})(?::?(\d{2})([.,]\d+)?)?)?(?:[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?)?((?:\[[^\]]*\])*)$/';

        /** @var list<string> $m */
        $m = [];
        if (preg_match($pattern, $s, $m) !== 1) {
            throw new InvalidArgumentException(
                "PlainDate::from() cannot parse \"{$s}\": invalid ISO 8601 date string.",
            );
        }

        [, $yearRaw, $dateRest] = $m;

        // Reject minus-zero extended year (-000000).
        if (preg_match('/^-0{6}$/', $yearRaw) === 1) {
            throw new InvalidArgumentException('Cannot use negative zero as extended year.');
        }

        // Compact date rest (MMDD) → extract components.
        if (!str_starts_with($dateRest, '-')) {
            $month = (int) substr(string: $dateRest, offset: 0, length: 2);
            $day = (int) substr(string: $dateRest, offset: 2, length: 2);
        } else {
            $month = (int) substr(string: $dateRest, offset: 1, length: 2);
            $day = (int) substr(string: $dateRest, offset: 4, length: 2);
        }
        $year = (int) $yearRaw;

        // Validate the time portion if present (groups 3-6 from the regex).
        // Hour must be 0-23, minute 0-59, second 0-60 (60 = leap second → mapped).
        // Groups are always present in the match array (as empty strings when not matched).
        if ($m[3] !== '') {
            $hour = (int) $m[3];
            if ($hour > 23) {
                throw new InvalidArgumentException(
                    "PlainDate::from() cannot parse \"{$s}\": hour {$hour} out of range.",
                );
            }
            if ($m[4] !== '') {
                $minute = (int) $m[4];
                if ($minute > 59) {
                    throw new InvalidArgumentException(
                        "PlainDate::from() cannot parse \"{$s}\": minute {$minute} out of range.",
                    );
                }
                if ($m[5] !== '') {
                    $second = (int) $m[5];
                    if ($second > 60) {
                        throw new InvalidArgumentException(
                            "PlainDate::from() cannot parse \"{$s}\": second {$second} out of range.",
                        );
                    }
                }
            }
        }

        // Validate bracket annotations and extract calendar ID.
        $annotationSection = $m[7];
        $calendarId = CalendarMath::validateAnnotations($annotationSection, $s);

        return new self($year, $month, $day, $calendarId);
    }

    /**
     * Creates a PlainDate from a property-bag array.
     *
     * @param array<array-key,mixed> $bag
     * @param string $overflow 'constrain' (clamp) or 'reject' (throw on out-of-range).
     * @throws \TypeError if required fields are missing or have wrong type.
     * @throws InvalidArgumentException if the date is invalid.
     */
    private static function fromPropertyBag(array $bag, string $overflow = 'constrain'): self
    {
        // Validate calendar key if present.
        $calendarId = null;
        if (array_key_exists('calendar', $bag)) {
            /** @var mixed $cal */
            $cal = $bag['calendar'];
            if (!is_string($cal)) {
                throw new \TypeError(sprintf('PlainDate calendar must be a string; got %s.', get_debug_type($cal)));
            }
            // Reject minus-zero extended year in date-like calendar strings.
            if (preg_match('/^-0{6}/', $cal) === 1) {
                throw new InvalidArgumentException(
                    "Cannot use negative zero as extended year in calendar string \"{$cal}\".",
                );
            }
            $calendarId = CalendarFactory::canonicalize(self::extractCalendarId($cal));
        }

        $hasEraAndEraYear = array_key_exists('era', $bag) && array_key_exists('eraYear', $bag);
        if (!array_key_exists('year', $bag) && !$hasEraAndEraYear) {
            throw new \TypeError('PlainDate property bag must have a year field.');
        }
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('PlainDate property bag must have a month or monthCode field.');
        }
        if (!array_key_exists('day', $bag)) {
            throw new \TypeError('PlainDate property bag must have a day field.');
        }

        $calendar = $calendarId !== null && $calendarId !== 'iso8601'
            ? CalendarFactory::get($calendarId)
            : null;

        // Extract year from the bag, or resolve from era + eraYear.
        $year = 0;
        if (array_key_exists('year', $bag)) {
            /** @var mixed $yearRaw */
            $yearRaw = $bag['year'];
            if ($yearRaw === null) {
                throw new \TypeError('PlainDate property bag year field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $yearRaw)) {
                throw new InvalidArgumentException('PlainDate year must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $year = is_int($yearRaw) ? $yearRaw : (int) $yearRaw;
        }

        // Resolve era + eraYear if present (overrides year for era-based calendars).
        if ($calendar !== null && array_key_exists('era', $bag) && array_key_exists('eraYear', $bag)) {
            /** @var mixed $eraRaw */
            $eraRaw = $bag['era'];
            /** @var mixed $eraYearRaw */
            $eraYearRaw = $bag['eraYear'];
            if (is_string($eraRaw) && $eraYearRaw !== null) {
                /** @phpstan-ignore cast.int */
                $eraYearInt = is_int($eraYearRaw) ? $eraYearRaw : (int) $eraYearRaw;
                $resolved = $calendar->resolveEra($eraRaw, $eraYearInt);
                if ($resolved !== null) {
                    $year = $resolved;
                }
            }
        }

        // Resolve month from monthCode or month field.
        $month = null;
        $monthCode = null;
        $hasMonth = array_key_exists('month', $bag);
        $hasMonthCode = array_key_exists('monthCode', $bag);

        if ($hasMonthCode) {
            /** @var mixed $monthCodeRaw */
            $monthCodeRaw = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $monthCode = is_string($monthCodeRaw) ? $monthCodeRaw : (string) $monthCodeRaw;
            $month = $calendar !== null
                ? $calendar->monthCodeToMonth($monthCode, $year)
                : CalendarMath::monthCodeToMonth($monthCode);
        }

        if ($hasMonth) {
            /** @var mixed $monthRaw */
            $monthRaw = $bag['month'];
            if ($monthRaw === null) {
                throw new \TypeError('PlainDate property bag month field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $monthRaw)) {
                throw new InvalidArgumentException('PlainDate month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = is_int($monthRaw) ? $monthRaw : (int) $monthRaw;
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        /** @var int $month */

        /** @var mixed $dayRaw */
        $dayRaw = $bag['day'];
        if ($dayRaw === null) {
            throw new \TypeError('PlainDate property bag day field must not be undefined.');
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $dayRaw)) {
            throw new InvalidArgumentException('PlainDate day must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $day = is_int($dayRaw) ? $dayRaw : (int) $dayRaw;

        // month < 1 and day < 1 are always invalid (cannot constrain below minimum of 1).
        if ($month < 1) {
            throw new InvalidArgumentException("Invalid PlainDate: month {$month} must be at least 1.");
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid PlainDate: day {$day} must be at least 1.");
        }

        // Non-ISO calendar: resolve calendar fields to ISO via the calendar protocol.
        if ($calendar !== null) {
            if ($hasMonthCode && $monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, $day, $overflow);
            } else {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, $day, $overflow);
            }
            return new self($isoY, $isoM, $isoD, $calendarId);
        }

        if ($overflow === 'constrain') {
            /**
             * @var int<1, 12>
             * @psalm-suppress UnnecessaryVarAnnotation — Mago can't narrow min()
             */
            $month = min(12, $month);
            $maxDay = CalendarMath::calcDaysInMonth($year, $month);
            $day = min($maxDay, $day);
        }

        return new self($year, $month, $day, $calendarId);
    }

    /**
     * Core implementation for since() and until().
     *
     * $later and $earlier define the raw difference; $receiver is $this (used as
     * the anchor for calendar-aware rounding, per the TC39 NudgeToCalendarUnit spec).
     *
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     */
    private static function diffDate(self $later, self $earlier, self $receiver, array|object|null $options): Duration
    {
        /** @var list<string> $validUnits */
        static $validUnits = ['auto', 'day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years'];
        /** @var array<string, int> $unitRank */
        static $unitRank = [
            'year' => 4,
            'years' => 4,
            'month' => 3,
            'months' => 3,
            'week' => 2,
            'weeks' => 2,
            'day' => 1,
            'days' => 1,
            'auto' => 1,
        ];

        $largestUnit = 'day';
        $largestUnitExplicit = false; // whether largestUnit was explicitly specified
        $smallestUnit = null; // null = not specified
        $roundingMode = 'trunc';
        $roundingIncrement = 1;

        if ($options !== null) {
            $opts = is_array($options) ? $options : (array) $options;

            // largestUnit
            if (array_key_exists('largestUnit', $opts)) {
                /** @var mixed $lu */
                $lu = $opts['largestUnit'];
                if ($lu !== null && !is_string($lu)) {
                    throw new \TypeError('largestUnit option must be a string.');
                }
                if (is_string($lu)) {
                    if (!in_array($lu, $validUnits, strict: true)) {
                        throw new InvalidArgumentException("Invalid largestUnit value: \"{$lu}\".");
                    }
                    $largestUnit = $lu;
                    $largestUnitExplicit = true;
                }
            }

            // roundingIncrement (parsed early so validation order matches spec)
            if (array_key_exists('roundingIncrement', $opts)) {
                /** @var mixed $ri */
                $ri = $opts['roundingIncrement'];
                if ($ri !== null) {
                    $roundingIncrement = CalendarMath::validateRoundingIncrement($ri);
                }
            }

            // roundingMode
            if (array_key_exists('roundingMode', $opts)) {
                /** @var mixed $rm */
                $rm = $opts['roundingMode'];
                if ($rm !== null && !is_string($rm)) {
                    throw new \TypeError('roundingMode option must be a string.');
                }
                if (is_string($rm)) {
                    if (!in_array($rm, CalendarMath::ROUNDING_MODES, strict: true)) {
                        throw new InvalidArgumentException("Invalid roundingMode value: \"{$rm}\".");
                    }
                    $roundingMode = $rm;
                }
            }

            // smallestUnit
            if (array_key_exists('smallestUnit', $opts)) {
                /** @var mixed $su */
                $su = $opts['smallestUnit'];
                if ($su !== null && !is_string($su)) {
                    throw new \TypeError('smallestUnit option must be a string.');
                }
                if (is_string($su)) {
                    if (!in_array($su, $validUnits, strict: true)) {
                        throw new InvalidArgumentException("Invalid smallestUnit value: \"{$su}\".");
                    }
                    $smallestUnit = $su;
                }
            }
        }

        // Default smallestUnit is 'day' (per TC39 spec for PlainDate).
        if ($smallestUnit === null) {
            $smallestUnit = 'day';
        }

        $suRank = $unitRank[$smallestUnit];
        $luRank = $unitRank[$largestUnit];

        if ($suRank > $luRank) {
            if ($largestUnitExplicit) {
                // Both explicitly set and smallestUnit > largestUnit: throw per spec.
                throw new InvalidArgumentException(
                    "smallestUnit \"{$smallestUnit}\" cannot be larger than largestUnit \"{$largestUnit}\".",
                );
            }
            // Only smallestUnit was explicitly set; bump largestUnit up to match.
            $largestUnit = $smallestUnit;
        }

        // Normalize to canonical singular forms.
        $normLargest = match ($largestUnit) {
            'days', 'auto' => 'day',
            'weeks' => 'week',
            'months' => 'month',
            'years' => 'year',
            default => $largestUnit,
        };
        $normSmallest = match ($smallestUnit) {
            'days', 'auto' => 'day',
            'weeks' => 'week',
            'months' => 'month',
            'years' => 'year',
            default => $smallestUnit,
        };

        $laterJdn = CalendarMath::toJulianDay($later->isoYear, $later->isoMonth, $later->isoDay);
        $earlierJdn = CalendarMath::toJulianDay($earlier->isoYear, $earlier->isoMonth, $earlier->isoDay);
        $totalDays = $laterJdn - $earlierJdn;

        // ---- Compute raw diff and apply rounding ----
        //
        // The structure follows: determine the raw diff (using largest unit to decompose),
        // then round at the smallest unit level.

        // Weeks and days: purely mathematical (no calendar-awareness for months/years).
        if ($normLargest === 'day') {
            // Both smallest and largest are 'day': round days directly.
            return new Duration(days: self::roundDays($totalDays, $roundingIncrement, $roundingMode));
        }

        if ($normLargest === 'week') {
            // Decompose totalDays into weeks + remaining days, then round at $normSmallest.
            if ($normSmallest === 'week') {
                $weekIncrement = $roundingIncrement * 7;
                $roundedDays = self::roundDays($totalDays, $weekIncrement, $roundingMode);
                return new Duration(weeks: intdiv(num1: $roundedDays, num2: 7));
            }
            // smallestUnit=day: round days within the week decomposition.
            $rawWeeks = intdiv(num1: $totalDays, num2: 7);
            $rawDays = $totalDays - ($rawWeeks * 7);
            $roundedDays = self::roundDays($rawDays, $roundingIncrement, $roundingMode);
            return new Duration(weeks: $rawWeeks, days: $roundedDays);
        }

        // Calendar units (months/years): compute via calendar protocol.
        // Pass whether the receiver corresponds to the y2 (later) argument so
        // calendarDiff knows from which end to anchor the day-remainder calculation.
        $receiverIsLater =
            $receiver->isoYear === $later->isoYear && $receiver->isoMonth === $later->isoMonth && $receiver->isoDay === $later->isoDay;
        $calendarId = $earlier->calendarId;
        if ($calendarId !== 'iso8601') {
            $cal = CalendarFactory::get($calendarId);
            [$years, $months, , $days] = $cal->dateUntil(
                $earlier->isoYear, $earlier->isoMonth, $earlier->isoDay,
                $later->isoYear, $later->isoMonth, $later->isoDay,
                $normLargest,
            );
        } else {
            [$years, $months, $days] = self::calendarDiff(
                $earlier->isoYear,
                $earlier->isoMonth,
                $earlier->isoDay,
                $later->isoYear,
                $later->isoMonth,
                $later->isoDay,
                $receiverIsLater,
            );
        }

        if ($normLargest === 'month') {
            $totalMonths = ($years * 12) + $months;
            if ($normSmallest === 'month') {
                // Round months; discard remaining days.
                return new Duration(months: self::roundCalendarMonths(
                    $totalMonths,
                    $days,
                    $receiver,
                    $roundingIncrement,
                    $roundingMode,
                    $receiverIsLater,
                ));
            }
            // smallestUnit=day: round remaining days (usually increment=1 = no-op).
            $roundedDays = self::roundDays($days, $roundingIncrement, $roundingMode);
            return new Duration(months: $totalMonths, days: $roundedDays);
        }

        // normLargest === 'year'
        if ($normSmallest === 'year') {
            // Round years; discard months and remaining days.
            $totalMonths = ($years * 12) + $months;
            return new Duration(years: self::roundCalendarYears(
                $years,
                $totalMonths,
                $days,
                $receiver,
                $roundingIncrement,
                $roundingMode,
                $receiverIsLater,
            ));
        }
        if ($normSmallest === 'month') {
            // Round months (collapse years into months), then reconvert to years+months.
            $totalMonths = ($years * 12) + $months;
            $roundedMonths = self::roundCalendarMonths(
                $totalMonths,
                $days,
                $receiver,
                $roundingIncrement,
                $roundingMode,
                $receiverIsLater,
            );
            $roundedYears = intdiv(num1: $roundedMonths, num2: 12);
            $roundedMonths = $roundedMonths - ($roundedYears * 12);
            return new Duration(years: $roundedYears, months: $roundedMonths);
        }
        // smallestUnit=day (or week, but week < month so that would have been caught earlier):
        // Return years + months + rounded days.
        $roundedDays = self::roundDays($days, $roundingIncrement, $roundingMode);
        return new Duration(years: $years, months: $months, days: $roundedDays);
    }

    /**
     * Rounds totalDays (possibly negative) to the nearest multiple of $increment
     * using the given rounding mode.
     *
     * For directed modes (floor/ceil, halfFloor/halfCeil), the mode is negated
     * for negative values to maintain correct directional semantics.
     */
    private static function roundDays(int $totalDays, int $increment, string $mode): int
    {
        if ($increment === 1 && $mode === 'trunc') {
            return $totalDays;
        }
        $sign = $totalDays >= 0 ? 1 : -1;
        $absDays = abs($totalDays);
        $effectiveMode = $mode;
        if ($sign < 0) {
            $effectiveMode = match ($mode) {
                'floor' => 'ceil',
                'ceil' => 'floor',
                'halfFloor' => 'halfCeil',
                'halfCeil' => 'halfFloor',
                default => $mode,
            };
        }
        return $sign * self::roundPositive($absDays, $increment, $effectiveMode);
    }

    /**
     * Rounds $absValue (non-negative) to nearest multiple of $increment with the given mode.
     *
     * Mode must be 'asIfPositive' (i.e. floor/trunc = round down, ceil/expand = round up).
     *
     * @return int rounded absolute value (multiple of $increment)
     */
    private static function roundPositive(int $absValue, int $increment, string $mode): int
    {
        $q = intdiv(num1: $absValue, num2: $increment);
        $rem = $absValue - ($q * $increment);
        $r1 = $q * $increment; // floor multiple
        $r2 = $r1 + $increment; // ceil multiple
        return match ($mode) {
            'trunc', 'floor' => $r1,
            'ceil', 'expand' => $rem === 0 ? $r1 : $r2,
            'halfExpand', 'halfCeil' => ($rem * 2) >= $increment ? $r2 : $r1,
            'halfTrunc', 'halfFloor' => ($rem * 2) > $increment ? $r2 : $r1,
            'halfEven' => ($rem * 2) < $increment
                ? $r1
                : (($rem * 2) > $increment ? $r2 : (($q % 2) === 0 ? $r1 : $r2)),
            default => $r1,
        };
    }

    /**
     * Calendar-aware rounding for months (NudgeToCalendarUnit, unit=months).
     *
     * Rounds $totalMonths (signed) + $remainingDays to the nearest $increment months,
     * anchored from the receiver date (per TC39 spec).
     *
     * $receiverIsLater: true when the receiver is the LATER of the two dates (since()
     * semantics), false when it is the EARLIER (until() semantics).  This controls the
     * direction in which the anchor is computed from the receiver.
     *
     * @throws InvalidArgumentException if the rounded date is out of the valid ISO range.
     */
    private static function roundCalendarMonths(
        int $totalMonths,
        int $remainingDays,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
    ): int {
        $sign = $totalMonths >= 0 ? 1 : -1;
        if ($totalMonths === 0 && $remainingDays !== 0) {
            $sign = $remainingDays >= 0 ? 1 : -1;
        }
        $absTotalMonths = abs($totalMonths);
        $absRemDays = abs($remainingDays);

        // floor-count (rounded down to nearest multiple of increment).
        $floorCount = intdiv(num1: $absTotalMonths, num2: $increment) * $increment;

        // Anchor: receiver going toward "other" by floorCount months.
        // When receiver is the later date (since): go backward → receiver − sign*floorCount months.
        // When receiver is the earlier date (until): go forward → receiver + sign*floorCount months.
        // Equivalently, the direction multiplier is -sign for since and +sign for until,
        // which simplifies to: direction = receiverIsLater ? -1 : 1.
        $dir = $receiverIsLater ? -$sign : $sign;
        $anchorJdn = self::addSignedMonths($receiver, $dir * $floorCount);

        // Next boundary: one increment further in the same direction.
        $nextJdn = self::addSignedMonths($receiver, $dir * ($floorCount + $increment));

        // Interval size in days (absolute value of the interval).
        $intervalDays = abs($nextJdn - $anchorJdn);

        // Remaining distance from anchor toward target = |remainingDays| from calendarDiff.
        $progress = $intervalDays > 0 ? $absRemDays / $intervalDays : 0.0;

        // Apply rounding (for negative diffs, flip floor/ceil per spec §11.5.12).
        $roundUp = CalendarMath::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbsMonths = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate: the rounded result must not exceed the valid PlainDate range.
        self::addSignedMonths($receiver, $dir * $roundedAbsMonths); // throws if out of range

        return $sign * $roundedAbsMonths;
    }

    /**
     * Calendar-aware rounding for years (NudgeToCalendarUnit, unit=years).
     *
     * $receiverIsLater: true when the receiver is the LATER of the two dates (since()
     * semantics), false when it is the EARLIER (until() semantics).
     *
     * @throws InvalidArgumentException if the rounded date is out of the valid ISO range.
     */
    private static function roundCalendarYears(
        int $years,
        int $totalMonths,
        int $remainingDays,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
    ): int {
        $sign = $years !== 0
            ? ($years >= 0 ? 1 : -1)
            : ($totalMonths !== 0 ? ($totalMonths >= 0 ? 1 : -1) : ($remainingDays >= 0 ? 1 : -1));
        $absYears = abs($years);

        $floorCount = intdiv(num1: $absYears, num2: $increment) * $increment;

        // Anchor: receiver going toward "other" by floorCount years.
        // When receiver is later (since): go backward → -sign direction.
        // When receiver is earlier (until): go forward → +sign direction.
        $dir = $receiverIsLater ? -$sign : $sign;
        $anchorJdn = self::addSignedYears($receiver, $dir * $floorCount);
        $nextJdn = self::addSignedYears($receiver, $dir * ($floorCount + $increment));

        $intervalDays = abs($nextJdn - $anchorJdn);

        // Compute the target JDN: from anchor, go further in the same direction
        // (toward next boundary) by the remaining months+days.
        $absRemMonths = abs($totalMonths) - ($floorCount * 12);
        $subAnchorJdn = self::addSignedMonths(self::fromJulianDayStatic($anchorJdn, $receiver->calendarId), $dir * $absRemMonths);
        $targetJdn = $subAnchorJdn + ($dir * abs($remainingDays));
        $absRemDistance = abs($targetJdn - $anchorJdn);

        $progress = $intervalDays > 0 ? $absRemDistance / $intervalDays : 0.0;
        $roundUp = CalendarMath::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbsYears = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate: the rounded result must not exceed the valid PlainDate range.
        self::addSignedYears($receiver, $dir * $roundedAbsYears); // throws if out of range

        return $sign * $roundedAbsYears;
    }

    /**
     * Adds $signedMonths months to $date with constrain overflow.
     *
     * Returns the Julian Day Number of the resulting date.
     *
     * @throws InvalidArgumentException if the resulting date is outside the valid ISO range.
     */
    private static function addSignedMonths(self $date, int $signedMonths): int
    {
        $cal = CalendarFactory::get($date->calendarId);
        [$y, $m, $d] = $cal->dateAdd(
            $date->isoYear, $date->isoMonth, $date->isoDay,
            0, $signedMonths, 0, 0,
            'constrain',
        );

        $minJdn = CalendarMath::toJulianDay(-271821, 4, 19);
        $maxJdn = CalendarMath::toJulianDay(275760, 9, 13);
        $jdn = CalendarMath::toJulianDay($y, $m, $d);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException('PlainDate rounding result is outside the representable range.');
        }
        return $jdn;
    }

    /**
     * Adds $signedYears years to $date with constrain overflow.
     *
     * Returns the Julian Day Number of the resulting date.
     *
     * @throws InvalidArgumentException if the resulting date is outside the valid ISO range.
     */
    private static function addSignedYears(self $date, int $signedYears): int
    {
        $cal = CalendarFactory::get($date->calendarId);
        [$y, $m, $d] = $cal->dateAdd(
            $date->isoYear, $date->isoMonth, $date->isoDay,
            $signedYears, 0, 0, 0,
            'constrain',
        );

        $minJdn = CalendarMath::toJulianDay(-271821, 4, 19);
        $maxJdn = CalendarMath::toJulianDay(275760, 9, 13);
        $jdn = CalendarMath::toJulianDay($y, $m, $d);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException('PlainDate rounding result is outside the representable range.');
        }
        return $jdn;
    }

    /**
     * Constructs a PlainDate from a Julian Day Number (used internally for rounding).
     */
    private static function fromJulianDayStatic(int $jdn, string $calendarId = 'iso8601'): self
    {
        [$y, $m, $d] = CalendarMath::fromJulianDay($jdn);
        return new self($y, $m, $d, $calendarId);
    }

    /**
     * Returns [years, months, remainingDays] between two dates.
     *
     * $receiverIsY2: true when the caller's receiver corresponds to y2 (the later
     * argument), false when it corresponds to y1 (the earlier argument).  The anchor
     * for the day-remainder calculation is always derived from the RECEIVER's date so
     * that since() and until() produce receiver-relative results as required by the spec.
     *
     * @param int<1, 12> $m1
     * @param int<1, 12> $m2
     * @return array{0: int, 1: int, 2: int}
     */
    private static function calendarDiff(
        int $y1,
        int $m1,
        int $d1,
        int $y2,
        int $m2,
        int $d2,
        bool $receiverIsY2 = true,
    ): array {
        $sign = $y2 > $y1 || $y2 === $y1 && ($m2 > $m1 || $m2 === $m1 && $d2 >= $d1) ? 1 : -1;

        // Track whether the receiver ends up as y1 or y2 after a potential swap.
        // A swap happens when sign < 0, which flips the receiver position.
        $receiverIsY2AfterSwap = $receiverIsY2;

        // Work in the positive direction; negate result if sign is negative.
        if ($sign < 0) {
            [$y1, $m1, $d1, $y2, $m2, $d2] = [$y2, $m2, $d2, $y1, $m1, $d1];
            $receiverIsY2AfterSwap = !$receiverIsY2;
        }

        $years = $y2 - $y1;
        $months = $m2 - $m1;

        if ($months < 0) {
            $years--;
            $months += 12;
        }

        // Borrow one month if d2 hasn't reached the start day (d1).
        // Compare d2 against the ORIGINAL d1 (not clamped to maxDay) to correctly
        // handle leap-day cases (e.g. Feb 29 2020 → Feb 28 2021: d2=28 < d1=29, borrow).
        if ($d2 < $d1) {
            if ($months > 0) {
                $months--;
            } else {
                $years--;
                $months = 11;
            }
        }

        // Compute anchor and remaining days from the RECEIVER's perspective.
        // receiverIsY2AfterSwap=true  → receiver=y2 → anchor = y2 − months (backward from receiver)
        // receiverIsY2AfterSwap=false → receiver=y1 → anchor = y1 + months (forward from receiver)
        if ($receiverIsY2AfterSwap) {
            // Anchor from y2 (receiver) going backward.
            $anchorMonth = $m2 - $months;
            $anchorYear = $y2 - $years;
            if ($anchorMonth <= 0) {
                $anchorYear--;
                $anchorMonth += 12;
            }
            $anchorMaxDay = CalendarMath::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay = min($d2, $anchorMaxDay);
            $days =
                CalendarMath::toJulianDay($anchorYear, $anchorMonth, $anchorDay)
                - CalendarMath::toJulianDay($y1, $m1, $d1);
        } else {
            // Anchor from y1 (receiver) going forward.
            $anchorMonth = $m1 + $months;
            $anchorYear = $y1 + $years;
            if ($anchorMonth > 12) {
                $anchorYear++;
                $anchorMonth -= 12;
            }
            $anchorMaxDay = CalendarMath::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay = min($d1, $anchorMaxDay);
            $days =
                CalendarMath::toJulianDay($y2, $m2, $d2)
                - CalendarMath::toJulianDay($anchorYear, $anchorMonth, $anchorDay);
        }

        return [$sign * $years, $sign * $months, $sign * $days];
    }

    /**
     * Shared implementation for add() and subtract().
     *
     * Sub-day time units (hours, minutes, seconds, milliseconds, microseconds,
     * nanoseconds) are balanced into whole days before applying day arithmetic.
     *
     * @param array<array-key, mixed>|object|null $options
     */
    private function addDuration(int $sign, Duration $dur, array|object|null $options): self
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

        $years = $sign * (int) $dur->years;
        $months = $sign * (int) $dur->months;
        $days = $sign * (((int) $dur->weeks * 7) + (int) $dur->days);

        // Balance sub-day time units (hours → days, etc.) using cascade arithmetic.
        // Each step: extract full days, carry remainder to the next smaller unit.
        $hours = $sign * (int) $dur->hours;
        $minutes = $sign * (int) $dur->minutes;
        $seconds = $sign * (int) $dur->seconds;
        $ms = $sign * (int) $dur->milliseconds;
        $us = $sign * (int) $dur->microseconds;
        $ns = $sign * (int) $dur->nanoseconds;

        // hours → full days + remainder hours
        $hDays = intdiv(num1: $hours, num2: 24);
        $hRem = $hours % 24;

        // carry + minutes → full days + remainder minutes
        $totalMin = ($hRem * 60) + $minutes;
        $mDays = intdiv(num1: $totalMin, num2: 1_440);
        $mRem = $totalMin % 1_440;

        // carry + seconds → full days + remainder seconds
        $totalSec = ($mRem * 60) + $seconds;
        $sDays = intdiv(num1: $totalSec, num2: 86_400);
        $sRem = $totalSec % 86_400;

        // carry + milliseconds → full days + remainder ms
        $totalMs = ($sRem * 1_000) + $ms;
        $msDays = intdiv(num1: $totalMs, num2: 86_400_000);
        $msRem = $totalMs % 86_400_000;

        // carry + microseconds → full days + remainder μs
        $totalUs = ($msRem * 1_000) + $us;
        $usDays = intdiv(num1: $totalUs, num2: 86_400_000_000);
        $usRem = $totalUs % 86_400_000_000;

        // carry + nanoseconds → full days
        $totalNs = ($usRem * 1_000) + $ns;
        $nsDays = intdiv(num1: $totalNs, num2: 86_400_000_000_000);

        $days += $hDays + $mDays + $sDays + $msDays + $usDays + $nsDays;

        // Delegate to the calendar protocol for date arithmetic.
        $cal = CalendarFactory::get($this->calendarId);
        [$newYear, $newMonth, $newDay] = $cal->dateAdd(
            $this->isoYear, $this->isoMonth, $this->isoDay,
            $years, $months, 0, $days,
            $overflow,
        );

        // Arithmetic that crosses the valid PlainDate range always throws, regardless of overflow.
        $minJdn = CalendarMath::toJulianDay(-271821, 4, 19);
        $maxJdn = CalendarMath::toJulianDay(275760, 9, 13);
        $jdn = CalendarMath::toJulianDay($newYear, $newMonth, $newDay);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException('PlainDate arithmetic result is outside the representable range.');
        }

        return new self($newYear, $newMonth, $newDay, $this->calendarId);
    }

    /**
     * Extracts the calendar ID from a calendar string.
     *
     * The calendar field in a property bag may be either a plain ID (e.g. 'iso8601')
     * or an ISO 8601 date/datetime string carrying a [u-ca=...] annotation. In the
     * latter case the annotation's value is the calendar ID; when absent, 'iso8601'
     * is the default.
     *
     * Only ASCII-lowercase comparison is used for case-folding. Calendar IDs that
     * contain non-ASCII characters that would be lowercased differently by Unicode
     * case-folding (e.g. U+0130 İ) are not lowercased and will not match 'iso8601'.
     */
    private static function extractCalendarId(string $cal): string
    {
        // A string that looks like an ISO date/datetime (e.g. "2020-01-01", "01-01",
        // "2020-01", "2016-12-31T23:59:60") is an ISO date string used as a
        // calendar field. Extract the [u-ca=...] annotation if present; otherwise
        // the implicit calendar is iso8601.
        //
        // Valid date-string forms (per TC39 spec, must START with digits and have
        // date structure):
        //   YYYY-MM  YYYY-MM-DD  YYYY-MM-DDTHH...  MM-DD
        // These all START with digits and contain a '-' within the first 7 chars.
        if (str_contains($cal, '[')) {
            if (preg_match('/\[!?u-ca=([^\]]+)\]/', $cal, $m) === 1) {
                return strtolower($m[1]);
            }
            // Bracket without u-ca → default iso8601.
            return 'iso8601';
        }
        // Detect date-like strings: must start with ASCII digits and have a dash
        // within the first 7 chars (to distinguish "2020-01-01" from "iso8601").
        if (preg_match('/^\d/', $cal) === 1 && preg_match('/^\d{1,6}-/', $cal) === 1) {
            // ISO date string with no bracket → implicit iso8601.
            return 'iso8601';
        }
        // A plain calendar ID: lowercase for case-insensitive comparison.
        // Use ASCII-only lowercase to reject non-ASCII characters like U+0130 (İ).
        $lower = '';
        $len = strlen($cal);
        for ($i = 0; $i < $len; $i++) {
            $c = $cal[$i];
            $o = ord($c);
            // Only lowercase ASCII A-Z; leave all other bytes unchanged.
            $lower .= $o >= 0x41 && $o <= 0x5A ? chr($o + 32) : $c;
        }
        return $lower;
    }
}
