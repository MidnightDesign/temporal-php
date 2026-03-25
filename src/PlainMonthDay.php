<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

/**
 * A calendar month-day without a year, time, or time zone.
 *
 * Only the ISO 8601 calendar is supported. The referenceISOYear defaults to
 * 1972 (a leap year), which allows Feb 29 to be a valid PlainMonthDay.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-plainmonthday-objects
 */
final class PlainMonthDay implements Stringable
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
    public string $calendarId {
        get => 'iso8601';
    }

    /**
     * Month code in "M01"–"M12" format.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode {
        get => sprintf('M%02d', $this->isoMonth);
    }

    /**
     * Month number 1–12 (derived from monthCode).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $month {
        get => $this->isoMonth;
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * The ISO month number 1–12.
     *
     * @psalm-api
     */
    public readonly int $isoMonth;

    /**
     * The ISO day number 1–31.
     *
     * @psalm-api
     */
    public readonly int $day;

    /**
     * The reference ISO year; defaults to 1972 (a leap year).
     *
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public readonly int $referenceISOYear;

    /**
     * Constructs a PlainMonthDay.
     *
     * @param int|float $isoMonth        Month 1–12 (required).
     * @param int|float $isoDay          Day 1–31 (calendar-aware; required).
     * @param mixed     $calendar        Calendar ID string; only "iso8601" supported.
     * @param int|float $referenceISOYear Reference ISO year; defaults to 1972.
     *
     * @throws \TypeError             if calendar is not null/string.
     * @throws InvalidArgumentException if month/day/referenceISOYear are out of range, infinite,
     *                                  or the calendar is unsupported.
     */
    public function __construct(
        int|float $isoMonth,
        int|float $isoDay,
        mixed $calendar = null,
        int|float $referenceISOYear = 1972,
    ) {
        if ($calendar !== null) {
            if (!is_string($calendar)) {
                throw new \TypeError(sprintf(
                    'PlainMonthDay calendar must be a string; got %s.',
                    get_debug_type($calendar),
                ));
            }
            // Only bare calendar IDs (not ISO date strings) accepted in constructor.
            if (strtolower($calendar) !== 'iso8601') {
                throw new InvalidArgumentException("Unsupported calendar \"{$calendar}\": only iso8601 is supported.");
            }
        }
        if (!is_finite((float) $isoMonth) || !is_finite((float) $isoDay) || !is_finite((float) $referenceISOYear)) {
            throw new InvalidArgumentException(
                'Invalid PlainMonthDay: isoMonth, isoDay, and referenceISOYear must be finite numbers.',
            );
        }
        $this->isoMonth = (int) $isoMonth;
        $this->day = (int) $isoDay;
        $this->referenceISOYear = (int) $referenceISOYear;

        if ($this->isoMonth < 1 || $this->isoMonth > 12) {
            throw new InvalidArgumentException("Invalid PlainMonthDay: month {$this->isoMonth} is out of range 1–12.");
        }
        if ($this->day < 1) {
            throw new InvalidArgumentException("Invalid PlainMonthDay: day {$this->day} must be at least 1.");
        }

        // Validate day against the reference year's month.
        $daysInMonth = self::calcDaysInMonth($this->referenceISOYear, $this->isoMonth);
        if ($this->day > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainMonthDay: day {$this->day} exceeds {$daysInMonth} days in "
                . "{$this->referenceISOYear}-{$this->isoMonth}.",
            );
        }

        // TC39 range: the resulting date (referenceISOYear-month-day) must be within the
        // representable PlainDate range (Apr 19 −271821 … Sep 13 +275760).
        $epochDays = self::toJulianDay($this->referenceISOYear, $this->isoMonth, $this->day) - 2_440_588;
        if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
            throw new InvalidArgumentException(sprintf(
                'Invalid PlainMonthDay: %d-%d-%d is outside the representable range.',
                $this->referenceISOYear,
                $this->isoMonth,
                $this->day,
            ));
        }
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    /**
     * Creates a PlainMonthDay from another PlainMonthDay, an ISO 8601 string, or a
     * property-bag array with 'month'/'monthCode' and 'day' fields.
     *
     * @param mixed $item     PlainMonthDay, ISO 8601 month-day string, or property-bag array.
     * @param mixed $options  Options bag: ['overflow' => 'constrain'|'reject']
     * @throws InvalidArgumentException if the string is invalid or overflow option is invalid.
     * @throws \TypeError if the type cannot be interpreted as a PlainMonthDay.
     * @psalm-api
     */
    public static function from(mixed $item, mixed $options = null): self
    {
        // Validate overflow option before processing item (per spec ordering).
        $overflow = 'constrain';
        if ($options !== null) {
            if (!is_array($options)) {
                if (is_object($options)) {
                    $opts = (array) $options;
                    if (array_key_exists('overflow', $opts)) {
                        /** @var mixed $ov */
                        $ov = $opts['overflow'];
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
            } elseif (array_key_exists('overflow', $options)) {
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
            return new self($item->isoMonth, $item->day, 'iso8601', $item->referenceISOYear);
        }
        if (is_string($item)) {
            return self::fromString($item);
        }
        if (is_array($item)) {
            return self::fromPropertyBag($item, $overflow);
        }
        throw new \TypeError(sprintf(
            'PlainMonthDay::from() expects a PlainMonthDay, ISO 8601 string, or property-bag array; got %s.',
            get_debug_type($item),
        ));
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainMonthDay with the specified fields overridden.
     *
     * Recognized fields: 'month', 'monthCode', 'day'. The 'year' field is
     * accepted but only used for overflow validation (not for a range check).
     * The 'calendar' and 'timeZone' keys must not be present.
     *
     * @param mixed $fields   Property bag with fields to override (array or plain object).
     * @param mixed $options  Options bag: ['overflow' => 'constrain'|'reject']
     * @throws \TypeError             if $fields contains 'calendar' or 'timeZone'.
     * @throws \TypeError             if no recognized fields are present.
     * @throws InvalidArgumentException if the resulting month-day is invalid (overflow: reject).
     * @psalm-api
     */
    public function with(mixed $fields, mixed $options = null): self
    {
        // IsPartialTemporalObject checks: reject non-objects, Temporal objects, calendar/timeZone keys.
        if (!is_array($fields) && !is_object($fields)) {
            throw new \TypeError(sprintf(
                'PlainMonthDay::with() argument must be a plain object; got %s.',
                get_debug_type($fields),
            ));
        }

        // Reject Temporal objects (IsPartialTemporalObject step 2).
        if (
            $fields instanceof PlainDate
            || $fields instanceof PlainDateTime
            || $fields instanceof self
            || $fields instanceof PlainTime
            || $fields instanceof PlainYearMonth
            || $fields instanceof ZonedDateTime
            || $fields instanceof Instant
            || $fields instanceof Duration
        ) {
            throw new \TypeError('PlainMonthDay::with() argument must not be a Temporal object.');
        }

        if (is_object($fields)) {
            /** @var array<array-key, mixed> $bag */
            $bag = get_object_vars($fields);
        } else {
            $bag = $fields;
        }

        // IsPartialTemporalObject step 3: calendar key present → TypeError.
        if (array_key_exists('calendar', $bag)) {
            throw new \TypeError('PlainMonthDay::with() fields must not contain a calendar property.');
        }
        // IsPartialTemporalObject step 4: timeZone key present → TypeError.
        if (array_key_exists('timeZone', $bag)) {
            throw new \TypeError('PlainMonthDay::with() fields must not contain a timeZone property.');
        }

        // PrepareCalendarFields step 10: at least one recognized calendar field must be present
        // and not undefined (null in PHP).
        $hasYear = array_key_exists('year', $bag) && $bag['year'] !== null;
        $hasMonth = array_key_exists('month', $bag) && $bag['month'] !== null;
        $hasMonthCode = array_key_exists('monthCode', $bag) && $bag['monthCode'] !== null;
        $hasDay = array_key_exists('day', $bag) && $bag['day'] !== null;

        if (!$hasYear && !$hasMonth && !$hasMonthCode && !$hasDay) {
            throw new \TypeError('PlainMonthDay::with() requires at least one of: year, month, monthCode, day.');
        }

        // Validate overflow option.
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
            } elseif (is_object($options)) {
                $optsBag = (array) $options;
                if (array_key_exists('overflow', $optsBag)) {
                    /** @var mixed $ov */
                    $ov = $optsBag['overflow'];
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
        }

        // Start from current fields and override.
        $month = $this->isoMonth;
        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $mcStr = (string) $mc;
            if (preg_match('/^M(0[1-9]|1[0-2])$/', $mcStr) !== 1) {
                throw new InvalidArgumentException("Invalid monthCode for ISO calendar: \"{$mcStr}\".");
            }
            $month = (int) substr(string: $mcStr, offset: 1);
        }
        if ($hasMonth) {
            /** @var mixed $m */
            $m = $bag['month'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $m)) {
                throw new InvalidArgumentException('PlainMonthDay::with() month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = (int) $m;
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        $day = $this->day;
        if ($hasDay) {
            /** @var mixed $d */
            $d = $bag['day'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $d)) {
                throw new InvalidArgumentException('PlainMonthDay::with() day must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $day = (int) $d;
        }

        // The 'year' field is only used for overflow computation, not for a range check.
        $refYear = $this->referenceISOYear;
        if ($hasYear) {
            /** @var mixed $yr */
            $yr = $bag['year'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $yr)) {
                throw new InvalidArgumentException('PlainMonthDay::with() year must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $refYear = (int) $yr;
        }

        if ($month < 1) {
            throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }

        if ($overflow === 'constrain') {
            $month = min(12, $month);
            $maxDay = self::calcDaysInMonth($refYear, $month);
            $day = min($maxDay, $day);
        } else {
            // reject: validate against refYear's month
            if ($month > 12) {
                throw new InvalidArgumentException("Invalid month {$month}: must be in range 1–12.");
            }
            $maxDay = self::calcDaysInMonth($refYear, $month);
            if ($day > $maxDay) {
                throw new InvalidArgumentException(
                    "Invalid day {$day}: exceeds {$maxDay} days in month {$month} of year {$refYear}.",
                );
            }
        }

        // Always use 1972 as the new referenceISOYear unless the day exceeds 1972's days for that month.
        $newRefYear = 1972;
        $maxDayIn1972 = self::calcDaysInMonth(1972, $month);
        if ($day > $maxDayIn1972) {
            $newRefYear = $refYear;
        }

        return new self($month, $day, 'iso8601', $newRefYear);
    }

    /**
     * Returns true if this PlainMonthDay is the same as $other.
     *
     * Equality compares month, day, calendarId, AND referenceISOYear.
     *
     * @param mixed $other A PlainMonthDay, ISO 8601 month-day string, or property-bag array.
     * @psalm-api
     */
    public function equals(mixed $other): bool
    {
        $o = $other instanceof self ? $other : self::from($other);
        return (
            $this->isoMonth === $o->isoMonth
            && $this->day === $o->day
            && $this->referenceISOYear === $o->referenceISOYear
        );
    }

    /**
     * Returns a string representation.
     *
     * Format depends on calendarName option:
     *   auto/never → "MM-DD" (no year, no calendar)
     *   always     → "YYYY-MM-DD[u-ca=iso8601]"
     *   critical   → "YYYY-MM-DD[!u-ca=iso8601]"
     *
     * @param mixed $options Options bag: ['calendarName' => 'auto'|'always'|'never'|'critical']
     * @throws InvalidArgumentException for invalid calendarName values.
     * @psalm-api
     */
    public function toString(mixed $options = null): string
    {
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
            'auto', 'never' => sprintf('%02d-%02d', $this->isoMonth, $this->day),
            'always' => sprintf('%04d-%02d-%02d[u-ca=iso8601]', $this->referenceISOYear, $this->isoMonth, $this->day),
            'critical' => sprintf(
                '%04d-%02d-%02d[!u-ca=iso8601]',
                $this->referenceISOYear,
                $this->isoMonth,
                $this->day,
            ),
            default => throw new InvalidArgumentException("Invalid calendarName value: \"{$calendarName}\"."),
        };
    }

    /** @psalm-api */
    public function toJSON(): string
    {
        return $this->toString();
    }

    /**
     * @param mixed $locales  BCP 47 locale string or array (ignored in PHP).
     * @param mixed $options  Intl.DateTimeFormat options bag (ignored in PHP).
     * @psalm-suppress UnusedParam
     * @psalm-api
     */
    public function toLocaleString(mixed $locales = null, mixed $options = null): string
    {
        return $this->toString();
    }

    /**
     * Always throws TypeError — PlainMonthDay must not be used in arithmetic context.
     *
     * @throws \TypeError always.
     * @psalm-return never
     * @psalm-api
     */
    public function valueOf(): never
    {
        throw new \TypeError('PlainMonthDay objects are not orderable');
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Converts this PlainMonthDay to a PlainDate by supplying the year.
     *
     * The day is constrained to the valid range for that year's month (default overflow behaviour).
     *
     * @param array<array-key,mixed>|object|mixed $fields Must contain 'year' key.
     * @throws \TypeError             if $fields is not an object/array or 'year' is missing.
     * @throws InvalidArgumentException if the resulting date is invalid.
     * @psalm-api
     */
    public function toPlainDate(mixed $fields): PlainDate
    {
        if (is_array($fields)) {
            $bag = $fields;
        } elseif (is_object($fields)) {
            /** @var array<array-key, mixed> $bag */
            $bag = get_object_vars($fields);
        } else {
            throw new \TypeError(sprintf(
                'PlainMonthDay::toPlainDate() argument must be an object; got %s.',
                get_debug_type($fields),
            ));
        }

        if (!array_key_exists('year', $bag)) {
            throw new \TypeError('PlainMonthDay::toPlainDate() argument must have a year property.');
        }

        /** @var mixed $yearRaw */
        $yearRaw = $bag['year'];
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $yearRaw)) {
            throw new InvalidArgumentException('toPlainDate() year must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $year = (int) $yearRaw;

        // Constrain day to valid range for this year-month (default overflow behaviour per spec).
        $maxDay = self::calcDaysInMonth($year, $this->isoMonth);
        $day = min($this->day, $maxDay);

        return new PlainDate($year, $this->isoMonth, $day);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parses an ISO 8601 month-day string into a PlainMonthDay.
     *
     * Accepted formats:
     *   --MM-DD (canonical PlainMonthDay format, no year info → referenceISOYear=1972)
     *   MM-DD (compact form without -- prefix → referenceISOYear=1972)
     *   YYYY-MM-DD, ±YYYYYY-MM-DD (full date strings → referenceISOYear=1972, year from string dropped)
     *   YYYYMMDD, ±YYYYYYMMDD (compact date strings → referenceISOYear=1972)
     * Optional trailing time, offset (only when time is present), and bracket annotations.
     * Z (UTC designator) is never valid for PlainMonthDay.
     * UTC offsets without a time component are not valid.
     *
     * @throws InvalidArgumentException for invalid or out-of-range dates.
     */
    private static function fromString(string $s): self
    {
        if ($s === '') {
            throw new InvalidArgumentException('PlainMonthDay::from() received an empty string.');
        }
        // Reject non-ASCII minus sign (U+2212 = \xe2\x88\x92).
        if (str_contains($s, "\u{2212}")) {
            throw new InvalidArgumentException(
                "PlainMonthDay::from() cannot parse \"{$s}\": non-ASCII minus sign is not allowed.",
            );
        }
        // Reject more than 9 fractional-second digits.
        if (preg_match('/[.,]\d{10,}/', $s) === 1) {
            throw new InvalidArgumentException(
                "PlainMonthDay::from() cannot parse \"{$s}\": fractional seconds may have at most 9 digits.",
            );
        }

        // Try the --MM-DD or MM-DD format (canonical PlainMonthDay forms).
        // Both --MM-DD and MM-DD are accepted; optional time/offset/brackets may follow.
        // UTC offsets/Z without time are NOT valid.
        // Pattern captures: (1) month, (2) day, (3) hour, (4) min, (5) sec, (6) frac, (7) brackets
        // The double-dash prefix (--) is optional.
        // optional '--' prefix + MM-DD, optional T+time, optional offset, bracket annotations
        $monthDayPattern = '/^(?:--)?(\d{2})-(\d{2})(?:[Tt ](\d{2})(?::?(\d{2})(?::?(\d{2})([.,]\d+)?)?)?(?:[Zz]|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?)?((?:\[[^\]]*\])*)$/';

        /** @var list<string> $m */
        $m = [];
        if (preg_match($monthDayPattern, $s, $m) === 1) {
            $month = (int) $m[1];
            $day = (int) $m[2];

            // Validate time portion if present.
            if ($m[3] !== '') {
                $hour = (int) $m[3];
                if ($hour > 23) {
                    throw new InvalidArgumentException(
                        "PlainMonthDay::from() cannot parse \"{$s}\": hour {$hour} out of range.",
                    );
                }
                if ($m[4] !== '') {
                    $minute = (int) $m[4];
                    if ($minute > 59) {
                        throw new InvalidArgumentException(
                            "PlainMonthDay::from() cannot parse \"{$s}\": minute {$minute} out of range.",
                        );
                    }
                    if ($m[5] !== '') {
                        $second = (int) $m[5];
                        if ($second > 60) {
                            throw new InvalidArgumentException(
                                "PlainMonthDay::from() cannot parse \"{$s}\": second {$second} out of range.",
                            );
                        }
                    }
                }
                // Z is not valid for PlainMonthDay.
                // Determine the offset of the date part: MM-DD = 5 chars, --MM-DD = 7 chars.
                $dateLen = str_starts_with($s, '--') ? 7 : 5;
                $afterDate = substr(string: $s, offset: $dateLen);
                $bracketPos = strpos(haystack: $afterDate, needle: '[');
                $timeOffset = $bracketPos !== false
                    ? substr(string: $afterDate, offset: 0, length: $bracketPos)
                    : $afterDate;
                if (preg_match('/[Zz]/', $timeOffset) === 1) {
                    throw new InvalidArgumentException(
                        "PlainMonthDay::from() cannot parse \"{$s}\": Z (UTC) designator is not valid.",
                    );
                }
            } else {
                // No time — check for Z or offset immediately following the date portion.
                $dateLen = str_starts_with($s, '--') ? 7 : 5;
                $rest = substr(string: $s, offset: $dateLen);
                if ($rest !== '' && preg_match('/^[Zz]|^[+-]\d{2}/', $rest) === 1) {
                    throw new InvalidArgumentException(
                        "PlainMonthDay::from() cannot parse \"{$s}\": UTC offset without time is not valid.",
                    );
                }
            }

            self::validateAnnotations($m[7], $s);

            // Validate month and day.
            if ($month < 1 || $month > 12) {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": month {$month} out of range 1–12.",
                );
            }
            if ($day < 1) {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": day {$day} must be at least 1.",
                );
            }
            $maxDay = self::calcDaysInMonth(1972, $month);
            if ($day > $maxDay) {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": day {$day} exceeds {$maxDay} for month {$month}.",
                );
            }

            // --MM-DD or MM-DD form: referenceISOYear = 1972 (canonical default).
            return new self($month, $day, 'iso8601', 1972);
        }

        // Try full date string formats: YYYY-MM-DD, ±YYYYYY-MM-DD, YYYYMMDD, ±YYYYYYMMDD
        // Also handles MM-DD (without --) as a bare month-day string.
        // date: year + rest, optional T+time, optional offset, bracket annotations
        $datePattern = '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})(?:[Tt ](\d{2})(?::?(\d{2})(?::?(\d{2})([.,]\d+)?)?)?(?:[Zz]|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?)?((?:\[[^\]]*\])*)$/';

        /** @var list<string> $m */
        $m = [];
        if (preg_match($datePattern, $s, $m) !== 1) {
            throw new InvalidArgumentException(
                "PlainMonthDay::from() cannot parse \"{$s}\": invalid ISO 8601 date string.",
            );
        }

        [, $yearRaw, $dateRest] = $m;

        // Reject minus-zero extended year (-000000).
        if (preg_match('/^-0{6}$/', $yearRaw) === 1) {
            throw new InvalidArgumentException('Cannot use negative zero as extended year.');
        }

        // Extract month and day from the date rest.
        if (!str_starts_with($dateRest, '-')) {
            $month = (int) substr(string: $dateRest, offset: 0, length: 2);
            $day = (int) substr(string: $dateRest, offset: 2, length: 2);
        } else {
            $month = (int) substr(string: $dateRest, offset: 1, length: 2);
            $day = (int) substr(string: $dateRest, offset: 4, length: 2);
        }

        // Check for UTC offset without time — NOT valid for PlainMonthDay.
        if ($m[3] === '') {
            // No time component. Check if there's a Z or offset after the date portion.
            $dateLen = strlen($yearRaw) + strlen($dateRest);
            $rest = substr(string: $s, offset: $dateLen);
            if ($rest !== '' && preg_match('/^[Zz]|^[+-]\d{2}/', $rest) === 1) {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": UTC offset without time is not valid.",
                );
            }
        }

        // Validate the time portion if present.
        if ($m[3] !== '') {
            $hour = (int) $m[3];
            if ($hour > 23) {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": hour {$hour} out of range.",
                );
            }
            if ($m[4] !== '') {
                $minute = (int) $m[4];
                if ($minute > 59) {
                    throw new InvalidArgumentException(
                        "PlainMonthDay::from() cannot parse \"{$s}\": minute {$minute} out of range.",
                    );
                }
                if ($m[5] !== '') {
                    $second = (int) $m[5];
                    if ($second > 60) {
                        throw new InvalidArgumentException(
                            "PlainMonthDay::from() cannot parse \"{$s}\": second {$second} out of range.",
                        );
                    }
                }
            }

            // Reject UTC designator (Z) — not valid for PlainMonthDay.
            $afterDate = substr(string: $s, offset: strlen($yearRaw) + strlen($dateRest));
            $bracketPos = strpos(haystack: $afterDate, needle: '[');
            $timeOffset = $bracketPos !== false
                ? substr(string: $afterDate, offset: 0, length: $bracketPos)
                : $afterDate;
            if (preg_match('/[Zz]/', $timeOffset) === 1) {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": Z (UTC) designator is not valid.",
                );
            }
        }

        self::validateAnnotations($m[7], $s);

        // For full date strings, the year is NOT stored as referenceISOYear.
        // TC39 spec: always use 1972 as referenceISOYear for strings, regardless of the year in the string.
        return new self($month, $day, 'iso8601', 1972);
    }

    /**
     * Creates a PlainMonthDay from a property bag.
     *
     * Required fields: 'month'/'monthCode' AND 'day'.
     * Optional: 'year' (used for overflow validation only, determines leap year context).
     *
     * @param array<array-key,mixed> $bag
     * @throws \TypeError             if required fields are missing.
     * @throws InvalidArgumentException if field values are invalid.
     */
    private static function fromPropertyBag(array $bag, string $overflow): self
    {
        // Validate calendar if present.
        if (array_key_exists('calendar', $bag)) {
            /** @var mixed $calRaw */
            $calRaw = $bag['calendar'];
            if (!is_string($calRaw)) {
                throw new \TypeError(sprintf(
                    'PlainMonthDay::from() calendar must be a string; got %s.',
                    get_debug_type($calRaw),
                ));
            }
            // Reject minus-zero extended year in calendar strings.
            if (preg_match('/^-0{6}/', $calRaw) === 1) {
                throw new InvalidArgumentException(
                    "Cannot use negative zero as extended year in calendar string \"{$calRaw}\".",
                );
            }
            $calId = self::extractCalendarId($calRaw);
            if ($calId !== 'iso8601') {
                throw new InvalidArgumentException("Unsupported calendar \"{$calRaw}\": only iso8601 is supported.");
            }
        }

        $hasMonth = array_key_exists('month', $bag) && $bag['month'] !== null;
        $hasMonthCode = array_key_exists('monthCode', $bag) && $bag['monthCode'] !== null;
        $hasDay = array_key_exists('day', $bag) && $bag['day'] !== null;
        $hasYear = array_key_exists('year', $bag) && $bag['year'] !== null;

        // day is required.
        if (!$hasDay) {
            throw new \TypeError('PlainMonthDay::from() property bag must have a day property.');
        }

        // Either month or monthCode is required.
        if (!$hasMonth && !$hasMonthCode) {
            throw new \TypeError('PlainMonthDay::from() property bag must have a month or monthCode property.');
        }

        // Parse month.
        $month = 0;
        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $mcStr = (string) $mc;
            if (preg_match('/^M(0[1-9]|1[0-2])$/', $mcStr) !== 1) {
                throw new InvalidArgumentException("Invalid monthCode for ISO calendar: \"{$mcStr}\".");
            }
            $month = (int) substr(string: $mcStr, offset: 1);
        }
        if ($hasMonth) {
            /**
             * @var mixed $m
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            $m = $bag['month'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $m)) {
                throw new InvalidArgumentException('PlainMonthDay::from() month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = (int) $m;
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        /** @var mixed $dayRaw */
        $dayRaw = $bag['day'];
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $dayRaw)) {
            throw new InvalidArgumentException('PlainMonthDay::from() day must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $day = (int) $dayRaw;

        // Determine the year for overflow/validation.
        // If 'year' is provided, use it; otherwise default to 1972 (leap year).
        $year = 1972;
        if ($hasYear) {
            /** @var mixed $yr */
            $yr = $bag['year'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $yr)) {
                throw new InvalidArgumentException('PlainMonthDay::from() year must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $year = (int) $yr;
        }

        // month < 1 is always invalid.
        if ($month < 1) {
            throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
        }
        // day < 1 is always invalid.
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }

        if ($overflow === 'constrain') {
            $month = min(12, $month);
            $maxDay = self::calcDaysInMonth($year, $month);
            $day = min($maxDay, $day);
        } else {
            // reject
            if ($month > 12) {
                throw new InvalidArgumentException("Invalid month {$month}: must be in range 1–12.");
            }
            $maxDay = self::calcDaysInMonth($year, $month);
            if ($day > $maxDay) {
                throw new InvalidArgumentException(
                    "Invalid day {$day}: exceeds {$maxDay} days in month {$month} of year {$year}.",
                );
            }
        }

        // For the stored referenceISOYear: use 1972 if the day is valid for 1972,
        // otherwise use the provided year (for cases like Feb 29 constrained to 28 in common year).
        $refYear = 1972;
        $maxDayIn1972 = self::calcDaysInMonth(1972, $month);
        if ($day > $maxDayIn1972) {
            $refYear = $year;
        }

        return new self($month, $day, 'iso8601', $refYear);
    }

    /**
     * Validates bracket annotations in a PlainMonthDay string.
     *
     * For PlainMonthDay, non-ISO calendars in the calendar annotation are always invalid.
     * Time-zone annotations are accepted (but ignored).
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
     * Returns true if the given year is a leap year in the proleptic Gregorian calendar.
     */
    private static function isLeapYear(int $year): bool
    {
        return ($year % 4) === 0 && ($year % 100) !== 0 || ($year % 400) === 0;
    }

    /**
     * Returns the number of days in the given month of the given year.
     */
    private static function calcDaysInMonth(int $year, int $month): int
    {
        /** @var array<int, int> $days */
        static $days = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ($month === 2 && self::isLeapYear($year)) {
            return 29;
        }
        return $days[$month];
    }

    /**
     * Computes the Julian Day Number for a proleptic Gregorian date.
     * Uses the Richards (2013) algorithm with floor division (handles negative years correctly).
     */
    private static function toJulianDay(int $year, int $month, int $day): int
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
     * Extracts and validates the calendar ID from a calendar string.
     *
     * Accepts bare calendar IDs ("iso8601"), ISO date strings ("2020-01-01", "01-01"),
     * and strings with [u-ca=X] annotations. Returns the lowercase calendar ID.
     * Throws for unsupported calendars.
     */
    private static function extractCalendarId(string $cal): string
    {
        if (str_contains($cal, '[')) {
            if (preg_match('/\[!?u-ca=([^\]]+)\]/', $cal, $m) === 1) {
                return strtolower($m[1]);
            }
            // Bracket without u-ca → default iso8601.
            return 'iso8601';
        }
        // Detect date-like strings: starts with digits and has a dash within the first 7 chars.
        if (preg_match('/^\d/', $cal) === 1 && preg_match('/^\d{1,6}-/', $cal) === 1) {
            return 'iso8601';
        }
        // Plain calendar ID: ASCII-only lowercase.
        $lower = '';
        $len = strlen($cal);
        for ($i = 0; $i < $len; $i++) {
            $c = $cal[$i];
            $o = ord($c);
            $lower .= $o >= 0x41 && $o <= 0x5A ? chr($o + 32) : $c;
        }
        return $lower;
    }

    /** Floor division: rounds toward negative infinity (unlike intdiv which rounds toward zero). */
    private static function floorDiv(int $a, int $b): int
    {
        return (int) floor($a / $b);
    }
}
