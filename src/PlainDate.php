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

    /** @psalm-api */
    public readonly int $year;
    /** @psalm-api */
    public readonly int $month;
    /** @psalm-api */
    public readonly int $day;

    /**
     * @throws InvalidArgumentException if year/month/day form an invalid ISO date or are infinite.
     * @throws InvalidArgumentException if calendar is provided and is not "iso8601" (case-insensitive, ASCII-only).
     */
    public function __construct(int|float $year, int|float $month, int|float $day, mixed $calendar = null)
    {
        if ($calendar !== null) {
            if (!is_string($calendar)) {
                throw new \TypeError(
                    'PlainDate calendar must be a string; got ' . get_debug_type($calendar) . '.',
                );
            }
            // The constructor only accepts bare calendar IDs, not ISO date strings.
            // Use ASCII-only lowercase to reject non-ASCII chars like U+0130 (İ).
            if (strtolower($calendar) !== 'iso8601') {
                throw new InvalidArgumentException(
                    "Unsupported calendar \"{$calendar}\": only iso8601 is supported.",
                );
            }
        }
        if (!is_finite((float) $year) || !is_finite((float) $month) || !is_finite((float) $day)) {
            throw new InvalidArgumentException(
                'Invalid PlainDate: year, month, and day must be finite numbers.',
            );
        }
        $this->year  = (int) $year;
        $this->month = (int) $month;
        $this->day   = (int) $day;
        if ($this->month < 1 || $this->month > 12) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: month {$this->month} is out of range 1–12.",
            );
        }
        if ($this->day < 1) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$this->day} must be at least 1.",
            );
        }
        $daysInMonth = self::calcDaysInMonth($this->year, $this->month);
        if ($this->day > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$this->day} exceeds {$daysInMonth} for {$this->year}-{$this->month}.",
            );
        }
        // TC39 range: Apr 19 −271821 … Sep 13 +275760.
        $epochDays = self::toJulianDay($this->year, $this->month, $this->day) - 2_440_588;
        if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: {$this->year}-{$this->month}-{$this->day} is outside the representable range.",
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
        $year = $this->year;
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

        $month        = $this->month;
        $hasMonth     = array_key_exists('month', $fields);
        $hasMonthCode = array_key_exists('monthCode', $fields);
        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $fields['monthCode'];
            /** @phpstan-ignore cast.string */
            $mcStr = (string) $mc;
            // monthCode must be M01–M12 in the ISO calendar.
            if (preg_match('/^M(0[1-9]|1[0-2])$/', $mcStr) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid monthCode for ISO calendar: \"{$mcStr}\".",
                );
            }
            $month = (int) substr(string: $mcStr, offset: 1);
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
                throw new InvalidArgumentException(
                    'Conflicting month and monthCode fields.',
                );
            }
            $month = $newMonth;
        }

        $day = $this->day;
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
            throw new InvalidArgumentException(
                "Invalid month {$month}: must be at least 1.",
            );
        }
        if ($day < 1) {
            throw new InvalidArgumentException(
                "Invalid day {$day}: must be at least 1.",
            );
        }

        if ($overflow === 'constrain') {
            $month  = min(12, $month);
            $maxDay = self::calcDaysInMonth($year, $month);
            $day    = min($maxDay, $day);
        }

        return new self($year, $month, $day);
    }

    /**
     * Returns a new PlainDate with the given duration added.
     *
     * Years and months are added calendrically (month overflow is carried into
     * year; day is clamped or rejected per `overflow` option). Weeks and days
     * are added using Julian Day Numbers so they work correctly across month and
     * year boundaries. Time units are balanced into days before applying.
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
     * Returns the Duration from $other to this date (this − other).
     *
     * Supports largestUnit, smallestUnit, roundingMode, and roundingIncrement options.
     *
     * @param mixed $other   PlainDate or ISO 8601 date string.
     * @param mixed $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function since(mixed $other, mixed $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        return self::diffDate($this, $o, $this, $options);
    }

    /**
     * Returns the Duration from this date to $other (other − this).
     *
     * @param mixed $other   PlainDate or ISO 8601 date string.
     * @param mixed $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function until(mixed $other, mixed $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        return self::diffDate($o, $this, $this, $options);
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
        // TC39: years 0–9999 → 4 digits; years outside → ±YYYYYY (6 digits with sign prefix).
        if ($this->year < 0) {
            $yearStr = sprintf('-%06d', abs($this->year));
        } elseif ($this->year > 9999) {
            $yearStr = sprintf('+%06d', $this->year);
        } else {
            $yearStr = sprintf('%04d', $this->year);
        }
        $base = sprintf('%s-%02d-%02d', $yearStr, $this->month, $this->day);

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
        $pattern =
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})'   // date: year + rest
            . '(?:[Tt ](\d{2})'                             // optional T + HH
            . '(?::?(\d{2})'                                // optional :MM
            . '(?::?(\d{2})([.,]\d+)?)?)?'                  // optional :SS[.frac]
            . '(?:[+-]\d{2}'                                // optional offset ±HH
            . '(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?'           //   ±HH:MM[:SS[.frac]]
            . '|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?)?'         //   or ±HHMM[SS[.frac]]
            . '((?:\[[^\]]*\])*)'                           // bracket annotations
            . '$/';

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
            $day   = (int) substr(string: $dateRest, offset: 2, length: 2);
        } else {
            $month = (int) substr(string: $dateRest, offset: 1, length: 2);
            $day   = (int) substr(string: $dateRest, offset: 4, length: 2);
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

        // Validate bracket annotations (rejects: uppercase keys, critical unknown, multiple TZ, etc.)
        $annotationSection = $m[7];
        self::validateAnnotations($annotationSection, $s);

        return new self($year, $month, $day);
    }

    /**
     * Validates bracket annotations in a PlainDate string.
     *
     * Additionally validates that any calendar (u-ca) annotation has value 'iso8601'.
     */
    private static function validateAnnotations(string $section, string $original): void
    {
        if ($section === '') {
            return;
        }

        $tzCount = 0;
        $calCount = 0;
        $calHasCritical = false;

        preg_match_all('/\[(!?)([^\]]*)\]/', $section, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            [, $bang, $content] = $match;
            $critical = $bang === '!';

            if (str_contains($content, '=')) {
                [$key] = explode(separator: '=', string: $content, limit: 2);

                if ($key !== strtolower($key)) {
                    throw new InvalidArgumentException(
                        "Invalid annotation key \"{$key}\" in \"{$original}\": annotation keys must be lowercase.",
                    );
                }

                if ($key === 'u-ca') {
                    // Validate calendar value on the first occurrence.
                    if ($calCount === 0) {
                        $calValue = substr(string: $content, offset: strlen($key) + 1);
                        if (strtolower($calValue) !== 'iso8601') {
                            throw new InvalidArgumentException(
                                "Unsupported calendar \"{$calValue}\" in \"{$original}\": only iso8601 is supported.",
                            );
                        }
                    }
                    ++$calCount;
                    if ($critical) {
                        $calHasCritical = true;
                    }
                    if ($calCount > 1 && $calHasCritical) {
                        throw new InvalidArgumentException(
                            "Multiple calendar annotations with critical flag in \"{$original}\".",
                        );
                    }
                } else {
                    if ($critical) {
                        throw new InvalidArgumentException(
                            "Critical unknown annotation \"[!{$content}]\" in \"{$original}\".",
                        );
                    }
                }
            } else {
                ++$tzCount;
                if ($tzCount > 1) {
                    throw new InvalidArgumentException("Multiple time-zone annotations in \"{$original}\".");
                }
            }
        }
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
        if (array_key_exists('calendar', $bag)) {
            /** @var mixed $cal */
            $cal = $bag['calendar'];
            if (!is_string($cal)) {
                throw new \TypeError(
                    'PlainDate calendar must be a string; got ' . get_debug_type($cal) . '.',
                );
            }
            // Reject minus-zero extended year in date-like calendar strings.
            if (preg_match('/^-0{6}/', $cal) === 1) {
                throw new InvalidArgumentException(
                    "Cannot use negative zero as extended year in calendar string \"{$cal}\".",
                );
            }
            $calId = self::extractCalendarId($cal);
            if ($calId !== 'iso8601') {
                throw new InvalidArgumentException(
                    "Unsupported calendar \"{$cal}\": only iso8601 is supported.",
                );
            }
        }

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
        if ($yearRaw === null) {
            throw new \TypeError('PlainDate property bag year field must not be undefined.');
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $yearRaw)) {
            throw new InvalidArgumentException('PlainDate year must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $year = is_int($yearRaw) ? $yearRaw : (int) $yearRaw;

        // Resolve month from monthCode or month field.
        $month = null;
        $hasMonth     = array_key_exists('month', $bag);
        $hasMonthCode = array_key_exists('monthCode', $bag);

        if ($hasMonthCode) {
            /** @var mixed $monthCodeRaw */
            $monthCodeRaw = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $mc = is_string($monthCodeRaw) ? $monthCodeRaw : (string) $monthCodeRaw;
            // monthCode must match M01–M12 exactly (no suffix like L).
            if (preg_match('/^M(0[1-9]|1[0-2])$/', $mc) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid monthCode for ISO calendar: \"{$mc}\".",
                );
            }
            $month = (int) substr(string: $mc, offset: 1);
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
            throw new InvalidArgumentException(
                "Invalid PlainDate: month {$month} must be at least 1.",
            );
        }
        if ($day < 1) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$day} must be at least 1.",
            );
        }

        if ($overflow === 'constrain') {
            $month  = min(12, $month);
            $maxDay = self::calcDaysInMonth($year, $month);
            $day    = min($maxDay, $day);
        }

        return new self($year, $month, $day);
    }

    /**
     * Core implementation for since() and until().
     *
     * $later and $earlier define the raw difference; $receiver is $this (used as
     * the anchor for calendar-aware rounding, per the TC39 NudgeToCalendarUnit spec).
     *
     * @param mixed $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     */
    private static function diffDate(self $later, self $earlier, self $receiver, mixed $options): Duration
    {
        /** @var list<string> $validUnits */
        static $validUnits = ['auto', 'day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years'];
        /** @var array<string, int> $unitRank */
        static $unitRank = ['year' => 4, 'years' => 4, 'month' => 3, 'months' => 3,
            'week' => 2, 'weeks' => 2, 'day' => 1, 'days' => 1, 'auto' => 1];
        /** @var list<string> $validModes */
        static $validModes = ['ceil', 'floor', 'expand', 'trunc',
            'halfCeil', 'halfFloor', 'halfExpand', 'halfTrunc', 'halfEven'];

        $largestUnit        = 'day';
        $largestUnitExplicit = false;  // whether largestUnit was explicitly specified
        $smallestUnit       = null;    // null = not specified
        $roundingMode       = 'trunc';
        $roundingIncrement  = 1;

        if ($options !== null && (is_array($options) || is_object($options))) {
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
                    if (!is_int($ri) && !is_float($ri) && !is_string($ri) && !is_bool($ri)) {
                        throw new \TypeError('roundingIncrement must be numeric.');
                    }
                    $riFloat = (float) $ri;
                    if (is_nan($riFloat) || !is_finite($riFloat)) {
                        throw new InvalidArgumentException('roundingIncrement must be a finite number.');
                    }
                    $riInt = (int) $riFloat; // truncate toward zero per spec
                    if ($riInt < 1 || $riInt > 1_000_000_000) {
                        throw new InvalidArgumentException(
                            "roundingIncrement {$riInt} is out of range; must be 1–1000000000.",
                        );
                    }
                    $roundingIncrement = $riInt;
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
                    if (!in_array($rm, $validModes, strict: true)) {
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

        $suRank = $unitRank[$smallestUnit] ?? 1;
        $luRank = $unitRank[$largestUnit] ?? 1;

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
        $normLargest  = match ($largestUnit)  { 'days', 'auto' => 'day', 'weeks' => 'week',
            'months' => 'month', 'years' => 'year', default => $largestUnit };
        $normSmallest = match ($smallestUnit) { 'days', 'auto' => 'day', 'weeks' => 'week',
            'months' => 'month', 'years' => 'year', default => $smallestUnit };

        $laterJdn   = self::toJulianDay($later->year, $later->month, $later->day);
        $earlierJdn = self::toJulianDay($earlier->year, $earlier->month, $earlier->day);
        $totalDays  = $laterJdn - $earlierJdn;

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
                $roundedDays   = self::roundDays($totalDays, $weekIncrement, $roundingMode);
                return new Duration(weeks: intdiv(num1: $roundedDays, num2: 7));
            }
            // smallestUnit=day: round days within the week decomposition.
            $rawWeeks = intdiv(num1: $totalDays, num2: 7);
            $rawDays  = $totalDays - $rawWeeks * 7;
            $roundedDays = self::roundDays($rawDays, $roundingIncrement, $roundingMode);
            return new Duration(weeks: $rawWeeks, days: $roundedDays);
        }

        // Calendar units (months/years): compute via calendarDiff.
        // Pass whether the receiver corresponds to the y2 (later) argument so
        // calendarDiff knows from which end to anchor the day-remainder calculation.
        $receiverIsLater = ($receiver->year === $later->year
            && $receiver->month === $later->month
            && $receiver->day === $later->day);
        [$years, $months, $days] = self::calendarDiff(
            $earlier->year, $earlier->month, $earlier->day,
            $later->year, $later->month, $later->day,
            $receiverIsLater,
        );

        if ($normLargest === 'month') {
            $totalMonths = $years * 12 + $months;
            if ($normSmallest === 'month') {
                // Round months; discard remaining days.
                return new Duration(months: self::roundCalendarMonths(
                    $totalMonths, $days, $receiver, $roundingIncrement, $roundingMode, $receiverIsLater,
                ));
            }
            // smallestUnit=day: round remaining days (usually increment=1 = no-op).
            $roundedDays = self::roundDays($days, $roundingIncrement, $roundingMode);
            return new Duration(months: $totalMonths, days: $roundedDays);
        }

        // normLargest === 'year'
        if ($normSmallest === 'year') {
            // Round years; discard months and remaining days.
            $totalMonths = $years * 12 + $months;
            return new Duration(years: self::roundCalendarYears(
                $years, $totalMonths, $days, $receiver, $roundingIncrement, $roundingMode, $receiverIsLater,
            ));
        }
        if ($normSmallest === 'month') {
            // Round months (collapse years into months), then reconvert to years+months.
            $totalMonths   = $years * 12 + $months;
            $roundedMonths = self::roundCalendarMonths(
                $totalMonths, $days, $receiver, $roundingIncrement, $roundingMode, $receiverIsLater,
            );
            $roundedYears  = intdiv(num1: $roundedMonths, num2: 12);
            $roundedMonths = $roundedMonths - $roundedYears * 12;
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
        $sign    = $totalDays >= 0 ? 1 : -1;
        $absDays = abs($totalDays);
        $effectiveMode = $mode;
        if ($sign < 0) {
            $effectiveMode = match ($mode) {
                'floor'     => 'ceil',
                'ceil'      => 'floor',
                'halfFloor' => 'halfCeil',
                'halfCeil'  => 'halfFloor',
                default     => $mode,
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
        $q   = intdiv(num1: $absValue, num2: $increment);
        $rem = $absValue - $q * $increment;
        $r1  = $q * $increment;       // floor multiple
        $r2  = $r1 + $increment;      // ceil multiple
        return match ($mode) {
            'trunc', 'floor'    => $r1,
            'ceil', 'expand'    => $rem === 0 ? $r1 : $r2,
            'halfExpand', 'halfCeil' => ($rem * 2) >= $increment ? $r2 : $r1,
            'halfTrunc', 'halfFloor' => ($rem * 2) > $increment ? $r2 : $r1,
            'halfEven' => ($rem * 2) < $increment ? $r1
                : (($rem * 2) > $increment ? $r2
                    : ($q % 2 === 0 ? $r1 : $r2)),
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
        $absRemDays     = abs($remainingDays);

        // floor-count (rounded down to nearest multiple of increment).
        $floorCount = (intdiv(num1: $absTotalMonths, num2: $increment)) * $increment;

        // Anchor: receiver going toward "other" by floorCount months.
        // When receiver is the later date (since): go backward → receiver − sign*floorCount months.
        // When receiver is the earlier date (until): go forward → receiver + sign*floorCount months.
        // Equivalently, the direction multiplier is -sign for since and +sign for until,
        // which simplifies to: direction = receiverIsLater ? -1 : 1.
        $dir = $receiverIsLater ? -$sign : $sign;
        $anchorJdn = self::addSignedMonths($receiver, $dir * $floorCount);

        // Next boundary: one increment further in the same direction.
        $nextJdn   = self::addSignedMonths($receiver, $dir * ($floorCount + $increment));

        // Interval size in days (absolute value of the interval).
        $intervalDays = abs($nextJdn - $anchorJdn);

        // Remaining distance from anchor toward target = |remainingDays| from calendarDiff.
        $progress = $intervalDays > 0 ? ($absRemDays / $intervalDays) : 0.0;

        // Apply rounding (for negative diffs, flip floor/ceil per spec §11.5.12).
        $roundUp = self::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbsMonths = $roundUp
            ? $floorCount + $increment
            : $floorCount;

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
        $sign = $years !== 0 ? ($years >= 0 ? 1 : -1)
            : ($totalMonths !== 0 ? ($totalMonths >= 0 ? 1 : -1)
                : ($remainingDays >= 0 ? 1 : -1));
        $absYears = abs($years);

        $floorCount = (intdiv(num1: $absYears, num2: $increment)) * $increment;

        // Anchor: receiver going toward "other" by floorCount years.
        // When receiver is later (since): go backward → -sign direction.
        // When receiver is earlier (until): go forward → +sign direction.
        $dir       = $receiverIsLater ? -$sign : $sign;
        $anchorJdn = self::addSignedYears($receiver, $dir * $floorCount);
        $nextJdn   = self::addSignedYears($receiver, $dir * ($floorCount + $increment));

        $intervalDays = abs($nextJdn - $anchorJdn);

        // Compute the target JDN: from anchor, go further in the same direction
        // (toward next boundary) by the remaining months+days.
        $absRemMonths = abs($totalMonths) - $floorCount * 12;
        $subAnchorJdn = self::addSignedMonths(
            self::fromJulianDayStatic($anchorJdn),
            $dir * $absRemMonths,
        );
        $targetJdn      = $subAnchorJdn + ($dir * abs($remainingDays));
        $absRemDistance = abs($targetJdn - $anchorJdn);

        $progress = $intervalDays > 0 ? ($absRemDistance / $intervalDays) : 0.0;
        $roundUp  = self::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbsYears = $roundUp
            ? $floorCount + $increment
            : $floorCount;

        // Validate: the rounded result must not exceed the valid PlainDate range.
        self::addSignedYears($receiver, $dir * $roundedAbsYears); // throws if out of range

        return $sign * $roundedAbsYears;
    }

    /**
     * Determines whether to round up based on progress (fraction in [0,1]) and rounding mode.
     *
     * For directed modes (floor/ceil, halfFloor/halfCeil), the sign is used to flip the mode
     * so that floor always rounds toward -∞ and ceil always toward +∞.
     */
    private static function applyRoundingProgress(float $progress, string $mode, int $sign): bool
    {
        // For negative diffs, flip floor/ceil so they retain their directional meaning.
        $effectiveMode = $mode;
        if ($sign < 0) {
            $effectiveMode = match ($mode) {
                'floor'     => 'ceil',
                'ceil'      => 'floor',
                'halfFloor' => 'halfCeil',
                'halfCeil'  => 'halfFloor',
                default     => $mode,
            };
        }
        return match ($effectiveMode) {
            'trunc', 'floor'         => false,
            'ceil', 'expand'         => $progress > 0.0,
            'halfExpand', 'halfCeil' => $progress >= 0.5,
            'halfTrunc', 'halfFloor' => $progress > 0.5,
            'halfEven'               => $progress > 0.5,  // at exactly 0.5, leave at floor (calendar units differ from numeric even/odd)
            default                  => false,
        };
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
        $y = $date->year;
        $m = $date->month + $signedMonths;
        $d = $date->day;

        if ($m > 12) {
            $y += intdiv(num1: $m - 1, num2: 12);
            $m  = (($m - 1) % 12) + 1;
        } elseif ($m < 1) {
            $y += intdiv(num1: $m - 12, num2: 12);
            $m  = (($m - 1) % 12 + 12) % 12 + 1;
        }

        $maxDay = self::calcDaysInMonth($y, $m);
        if ($d > $maxDay) {
            $d = $maxDay; // constrain
        }

        $minJdn = self::toJulianDay(-271821, 4, 19);
        $maxJdn = self::toJulianDay(275760, 9, 13);
        $jdn    = self::toJulianDay($y, $m, $d);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException(
                'PlainDate rounding result is outside the representable range.',
            );
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
        return self::addSignedMonths($date, $signedYears * 12);
    }

    /**
     * Constructs a PlainDate from a Julian Day Number (used internally for rounding).
     */
    private static function fromJulianDayStatic(int $jdn): self
    {
        [$y, $m, $d] = self::fromJulianDay($jdn);
        return new self($y, $m, $d);
    }

    /**
     * Returns [years, months, remainingDays] between two dates.
     *
     * $receiverIsY2: true when the caller's receiver corresponds to y2 (the later
     * argument), false when it corresponds to y1 (the earlier argument).  The anchor
     * for the day-remainder calculation is always derived from the RECEIVER's date so
     * that since() and until() produce receiver-relative results as required by the spec.
     *
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
        $sign = ($y2 > $y1 || ($y2 === $y1 && ($m2 > $m1 || ($m2 === $m1 && $d2 >= $d1)))) ? 1 : -1;

        // Track whether the receiver ends up as y1 or y2 after a potential swap.
        // A swap happens when sign < 0, which flips the receiver position.
        $receiverIsY2AfterSwap = $receiverIsY2;

        // Work in the positive direction; negate result if sign is negative.
        if ($sign < 0) {
            [$y1, $m1, $d1, $y2, $m2, $d2] = [$y2, $m2, $d2, $y1, $m1, $d1];
            $receiverIsY2AfterSwap = !$receiverIsY2;
        }

        $years  = $y2 - $y1;
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
            $anchorYear  = $y2 - $years;
            if ($anchorMonth <= 0) {
                $anchorYear--;
                $anchorMonth += 12;
            }
            $anchorMaxDay = self::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay    = min($d2, $anchorMaxDay);
            $days = self::toJulianDay($anchorYear, $anchorMonth, $anchorDay)
                - self::toJulianDay($y1, $m1, $d1);
        } else {
            // Anchor from y1 (receiver) going forward.
            $anchorMonth = $m1 + $months;
            $anchorYear  = $y1 + $years;
            if ($anchorMonth > 12) {
                $anchorYear++;
                $anchorMonth -= 12;
            }
            $anchorMaxDay = self::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay    = min($d1, $anchorMaxDay);
            $days = self::toJulianDay($y2, $m2, $d2)
                - self::toJulianDay($anchorYear, $anchorMonth, $anchorDay);
        }

        return [$sign * $years, $sign * $months, $sign * $days];
    }

    /**
     * Shared implementation for add() and subtract().
     *
     * Sub-day time units (hours, minutes, seconds, milliseconds, microseconds,
     * nanoseconds) are balanced into whole days before applying day arithmetic.
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

        // Balance sub-day time units (hours → days, etc.) using cascade arithmetic.
        // Each step: extract full days, carry remainder to the next smaller unit.
        $hours   = $sign * (int) $dur->hours;
        $minutes = $sign * (int) $dur->minutes;
        $seconds = $sign * (int) $dur->seconds;
        $ms      = $sign * (int) $dur->milliseconds;
        $us      = $sign * (int) $dur->microseconds;
        $ns      = $sign * (int) $dur->nanoseconds;

        // hours → full days + remainder hours
        $hDays  = intdiv(num1: $hours, num2: 24);
        $hRem   = $hours % 24;

        // carry + minutes → full days + remainder minutes
        $totalMin = $hRem * 60 + $minutes;
        $mDays    = intdiv(num1: $totalMin, num2: 1_440);
        $mRem     = $totalMin % 1_440;

        // carry + seconds → full days + remainder seconds
        $totalSec = $mRem * 60 + $seconds;
        $sDays    = intdiv(num1: $totalSec, num2: 86_400);
        $sRem     = $totalSec % 86_400;

        // carry + milliseconds → full days + remainder ms
        $totalMs = $sRem * 1_000 + $ms;
        $msDays  = intdiv(num1: $totalMs, num2: 86_400_000);
        $msRem   = $totalMs % 86_400_000;

        // carry + microseconds → full days + remainder μs
        $totalUs = $msRem * 1_000 + $us;
        $usDays  = intdiv(num1: $totalUs, num2: 86_400_000_000);
        $usRem   = $totalUs % 86_400_000_000;

        // carry + nanoseconds → full days
        $totalNs = $usRem * 1_000 + $ns;
        $nsDays  = intdiv(num1: $totalNs, num2: 86_400_000_000_000);

        $days += $hDays + $mDays + $sDays + $msDays + $usDays + $nsDays;

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
        $jdn = self::toJulianDay($newYear, $newMonth, $newDay) + $days;

        // Arithmetic that crosses the valid PlainDate range always throws, regardless of overflow.
        $minJdn = self::toJulianDay(-271821, 4, 19);
        $maxJdn = self::toJulianDay(275760, 9, 13);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException(
                'PlainDate arithmetic result is outside the representable range.',
            );
        }

        [$newYear, $newMonth, $newDay] = self::fromJulianDay($jdn);

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
            + self::floorDiv($y, 4)
            - self::floorDiv($y, 100)
            + self::floorDiv($y, 400)
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
        $b = self::floorDiv(4 * $a + 3, 146_097);
        $c = $a - self::floorDiv(146_097 * $b, 4);
        $d = self::floorDiv(4 * $c + 3, 1_461);
        $e = $c - self::floorDiv(1_461 * $d, 4);
        $m = self::floorDiv(5 * $e + 2, 153);
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
        $len   = strlen($cal);
        for ($i = 0; $i < $len; $i++) {
            $c = $cal[$i];
            $o = ord($c);
            // Only lowercase ASCII A-Z; leave all other bytes unchanged.
            $lower .= ($o >= 0x41 && $o <= 0x5A) ? chr($o + 32) : $c;
        }
        return $lower;
    }

    /**
     * Floor division: rounds towards negative infinity (unlike intdiv which truncates towards zero).
     *
     * Required for correct Julian Day Number conversions with negative years.
     */
    private static function floorDiv(int $a, int $b): int
    {
        return (int) floor($a / $b);
    }
}
