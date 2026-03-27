<?php

declare(strict_types=1);

namespace Temporal\Spec;

use InvalidArgumentException;
use Stringable;
use Temporal\Spec\Internal\Calendar\CalendarFactory;
use Temporal\Spec\Internal\CalendarMath;
use Temporal\Spec\Internal\TemporalSerde;

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
    use TemporalSerde;

    // -------------------------------------------------------------------------
    // Virtual (get-only) properties
    // -------------------------------------------------------------------------

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode {
        get => CalendarFactory::get($this->calendarId)->monthCode($this->referenceISOYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $month {
        get => $this->calendarId === 'iso8601'
            ? $this->isoMonth
            : CalendarFactory::get($this->calendarId)->month($this->referenceISOYear, $this->isoMonth, $this->isoDay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty
     * @psalm-api
     */
    public int $day {
        get => $this->calendarId === 'iso8601'
            ? $this->isoDay
            : CalendarFactory::get($this->calendarId)->day($this->referenceISOYear, $this->isoMonth, $this->isoDay);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /** @psalm-api */
    public readonly string $calendarId;

    /**
     * The ISO month number 1–12.
     *
     * @psalm-api
     * @var int<1, 12>
     */
    public readonly int $isoMonth;

    /**
     * The ISO day number 1–31.
     *
     * @psalm-api
     * @var int<1, 31>
     */
    public readonly int $isoDay;

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
     * @param string|null $calendar        Calendar ID string; only "iso8601" supported.
     * @param int|float   $referenceISOYear Reference ISO year; defaults to 1972.
     *
     * @throws InvalidArgumentException if month/day/referenceISOYear are out of range, infinite,
     *                                  or the calendar is unsupported.
     */
    public function __construct(
        int|float $isoMonth,
        int|float $isoDay,
        ?string $calendar = null,
        int|float $referenceISOYear = 1972,
    ) {
        if ($calendar !== null) {
            $calendar = CalendarFactory::canonicalize($calendar);
        }
        $this->calendarId = $calendar ?? 'iso8601';
        if (!is_finite((float) $isoMonth) || !is_finite((float) $isoDay) || !is_finite((float) $referenceISOYear)) {
            throw new InvalidArgumentException(
                'Invalid PlainMonthDay: isoMonth, isoDay, and referenceISOYear must be finite numbers.',
            );
        }
        $monthInt = (int) $isoMonth;
        if ($monthInt < 1 || $monthInt > 12) {
            throw new InvalidArgumentException("Invalid PlainMonthDay: month {$monthInt} is out of range 1–12.");
        }
        $this->isoMonth = $monthInt;
        $dayInt = (int) $isoDay;
        if ($dayInt < 1) {
            throw new InvalidArgumentException("Invalid PlainMonthDay: day {$dayInt} must be at least 1.");
        }
        $this->referenceISOYear = (int) $referenceISOYear;

        // Validate day against the reference year's month.
        $daysInMonth = CalendarMath::calcDaysInMonth($this->referenceISOYear, $this->isoMonth);
        if ($dayInt > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainMonthDay: day {$dayInt} exceeds {$daysInMonth} days in "
                . "{$this->referenceISOYear}-{$this->isoMonth}.",
            );
        }
        /** @psalm-suppress InvalidPropertyAssignmentValue — $dayInt <= $daysInMonth <= 31 */
        $this->isoDay = $dayInt;

        // TC39 range: the resulting date (referenceISOYear-month-day) must be within the
        // representable PlainDate range (Apr 19 −271821 … Sep 13 +275760).
        $epochDays = CalendarMath::toJulianDay($this->referenceISOYear, $this->isoMonth, $this->isoDay) - 2_440_588;
        if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
            throw new InvalidArgumentException(sprintf(
                'Invalid PlainMonthDay: %d-%d-%d is outside the representable range.',
                $this->referenceISOYear,
                $this->isoMonth,
                $this->isoDay,
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
     * @param self|string|array<array-key, mixed>|object $item PlainMonthDay, ISO 8601 month-day string, or property-bag array.
     * @param array<array-key, mixed>|object|null $options Options bag: ['overflow' => 'constrain'|'reject']
     * @throws InvalidArgumentException if the string is invalid or overflow option is invalid.
     * @throws \TypeError if the type cannot be interpreted as a PlainMonthDay.
     * @psalm-api
     */
    public static function from(string|array|object $item, array|object|null $options = null): self
    {
        // Validate overflow option before processing item (per spec ordering).
        $overflow = 'constrain';
        if ($options !== null) {
            if (!is_array($options)) {
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
            return new self($item->isoMonth, $item->isoDay, $item->calendarId, $item->referenceISOYear);
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
     * @param array<array-key, mixed>|object $fields Property bag with fields to override.
     * @param array<array-key, mixed>|object|null $options Options bag: ['overflow' => 'constrain'|'reject']
     * @throws \TypeError             if $fields contains 'calendar' or 'timeZone'.
     * @throws \TypeError             if no recognized fields are present.
     * @throws InvalidArgumentException if the resulting month-day is invalid (overflow: reject).
     * @psalm-api
     */
    public function with(array|object $fields, array|object|null $options = null): self
    {
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

        $calendar = $this->calendarId !== 'iso8601'
            ? CalendarFactory::get($this->calendarId)
            : null;

        // Start from current fields (calendar-projected for non-ISO).
        $month = $calendar !== null ? $this->month : $this->isoMonth;
        $monthCode = null;
        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $monthCode = (string) $mc;
        }
        if ($hasMonth) {
            /** @var mixed $m */
            $m = $bag['month'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $m)) {
                throw new InvalidArgumentException('PlainMonthDay::with() month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $month = (int) $m;
        }

        $day = $calendar !== null ? $this->day : $this->isoDay;
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

        // Non-ISO calendar: resolve back to ISO via calendar protocol.
        if ($calendar !== null) {
            if ($hasMonthCode && $monthCode !== null) {
                $resolvedMonth = $calendar->monthCodeToMonth($monthCode, $refYear);
                if ($hasMonth && $month !== $resolvedMonth) {
                    throw new InvalidArgumentException('Conflicting month and monthCode fields.');
                }
            }
            if ($day < 1) {
                throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
            }
            if ($hasMonthCode && $monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($refYear, $monthCode, $day, $overflow);
            } else {
                if ($month < 1) {
                    throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
                }
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($refYear, $month, $day, $overflow);
            }
            return new self($isoM, $isoD, $this->calendarId, $isoY);
        }

        // ISO path: resolve monthCode.
        if ($hasMonthCode && $monthCode !== null) {
            $mcMonth = CalendarMath::monthCodeToMonth($monthCode);
            if ($hasMonth && $month !== $mcMonth) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $mcMonth;
        }

        if ($month < 1) {
            throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }

        if ($overflow === 'constrain') {
            /**
             * @var int<1, 12>
             * @psalm-suppress UnnecessaryVarAnnotation — Mago can't narrow min()
             */
            $month = min(12, $month);
            $maxDay = CalendarMath::calcDaysInMonth($refYear, $month);
            $day = min($maxDay, $day);
        } else {
            // reject: validate against refYear's month
            if ($month > 12) {
                throw new InvalidArgumentException("Invalid month {$month}: must be in range 1–12.");
            }
            $maxDay = CalendarMath::calcDaysInMonth($refYear, $month);
            if ($day > $maxDay) {
                throw new InvalidArgumentException(
                    "Invalid day {$day}: exceeds {$maxDay} days in month {$month} of year {$refYear}.",
                );
            }
        }

        // Always use 1972 as the new referenceISOYear unless the day exceeds 1972's days for that month.
        $newRefYear = 1972;
        /**
         * @var int<1, 12> $month
         * @psalm-suppress UnnecessaryVarAnnotation — Mago loses narrowing across if/else branches
         */
        $maxDayIn1972 = CalendarMath::calcDaysInMonth(1972, $month);
        if ($day > $maxDayIn1972) {
            $newRefYear = $refYear;
        }

        return new self($month, $day, $this->calendarId, $newRefYear);
    }

    /**
     * Returns true if this PlainMonthDay is the same as $other.
     *
     * Equality compares month, day, calendarId, AND referenceISOYear.
     *
     * @param self|string|array<array-key, mixed>|object $other A PlainMonthDay, ISO 8601 month-day string, or property-bag array.
     * @psalm-api
     */
    public function equals(string|array|object $other): bool
    {
        $o = $other instanceof self ? $other : self::from($other);
        return (
            $this->isoMonth === $o->isoMonth
            && $this->isoDay === $o->isoDay
            && $this->referenceISOYear === $o->referenceISOYear
            && $this->calendarId === $o->calendarId
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
     * @param array<array-key, mixed>|object|null $options Options bag: ['calendarName' => 'auto'|'always'|'never'|'critical']
     * @throws InvalidArgumentException for invalid calendarName values.
     * @psalm-api
     */
    #[\Override]
    public function toString(array|object|null $options = null): string
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
            'auto' => $this->calendarId !== 'iso8601'
                ? sprintf('%04d-%02d-%02d[u-ca=%s]', $this->referenceISOYear, $this->isoMonth, $this->isoDay, $this->calendarId)
                : sprintf('%02d-%02d', $this->isoMonth, $this->isoDay),
            'never' => sprintf('%02d-%02d', $this->isoMonth, $this->isoDay),
            'always' => sprintf('%04d-%02d-%02d[u-ca=%s]', $this->referenceISOYear, $this->isoMonth, $this->isoDay, $this->calendarId),
            'critical' => sprintf(
                '%04d-%02d-%02d[!u-ca=%s]',
                $this->referenceISOYear,
                $this->isoMonth,
                $this->isoDay,
                $this->calendarId,
            ),
            default => throw new InvalidArgumentException("Invalid calendarName value: \"{$calendarName}\"."),
        };
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

    /**
     * Converts this PlainMonthDay to a PlainDate by supplying the year.
     *
     * The day is constrained to the valid range for that year's month (default overflow behaviour).
     *
     * @param array<array-key, mixed>|object $fields Must contain 'year' key.
     * @throws \TypeError             if $fields is not an object/array or 'year' is missing.
     * @throws InvalidArgumentException if the resulting date is invalid.
     * @psalm-api
     */
    public function toPlainDate(array|object $fields): PlainDate
    {
        if (is_array($fields)) {
            $bag = $fields;
        } else {
            /** @var array<array-key, mixed> $bag */
            $bag = get_object_vars($fields);
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
        $maxDay = CalendarMath::calcDaysInMonth($year, $this->isoMonth);
        $day = min($this->isoDay, $maxDay);

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

            $calendarId = CalendarMath::validateAnnotations($m[7], $s);

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
            $maxDay = CalendarMath::calcDaysInMonth(1972, $month);
            if ($day > $maxDay) {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": day {$day} exceeds {$maxDay} for month {$month}.",
                );
            }

            // --MM-DD or MM-DD form: referenceISOYear = 1972 (canonical default).
            return new self($month, $day, $calendarId, 1972);
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

        $calendarId = CalendarMath::validateAnnotations($m[7], $s);

        // For full date strings, the year is NOT stored as referenceISOYear.
        // TC39 spec: always use 1972 as referenceISOYear for strings, regardless of the year in the string.
        return new self($month, $day, $calendarId, 1972);
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
        $calendarId = null;
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
            $calendarId = CalendarFactory::canonicalize(self::extractCalendarId($calRaw));
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
        $monthCode = null;
        $calendar = $calendarId !== null && $calendarId !== 'iso8601'
            ? CalendarFactory::get($calendarId)
            : null;

        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $monthCode = (string) $mc;
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
            $month = (int) $m;
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

        // For non-ISO calendars, resolve monthCode to month ordinal and then to ISO.
        if ($calendar !== null) {
            if ($hasMonthCode && $monthCode !== null) {
                $resolvedMonth = $calendar->monthCodeToMonth($monthCode, $year);
                if ($hasMonth && $month !== $resolvedMonth) {
                    throw new InvalidArgumentException('Conflicting month and monthCode fields.');
                }
            }
            if ($month < 1 && !$hasMonthCode) {
                throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
            }
            if ($day < 1) {
                throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
            }
            if ($hasMonthCode && $monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, $day, $overflow);
            } else {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, $day, $overflow);
            }
            return new self($isoM, $isoD, $calendarId, $isoY);
        }

        // ISO path: resolve monthCode to month number.
        if ($hasMonthCode && $monthCode !== null) {
            $mcMonth = CalendarMath::monthCodeToMonth($monthCode);
            if ($hasMonth && $month !== $mcMonth) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $mcMonth;
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
            /**
             * @var int<1, 12>
             * @psalm-suppress UnnecessaryVarAnnotation — Mago can't narrow min()
             */
            $month = min(12, $month);
            $maxDay = CalendarMath::calcDaysInMonth($year, $month);
            $day = min($maxDay, $day);
        } else {
            // reject
            if ($month > 12) {
                throw new InvalidArgumentException("Invalid month {$month}: must be in range 1–12.");
            }
            $maxDay = CalendarMath::calcDaysInMonth($year, $month);
            if ($day > $maxDay) {
                throw new InvalidArgumentException(
                    "Invalid day {$day}: exceeds {$maxDay} days in month {$month} of year {$year}.",
                );
            }
        }

        // For the stored referenceISOYear: use 1972 if the day is valid for 1972,
        // otherwise use the provided year (for cases like Feb 29 constrained to 28 in common year).
        $refYear = 1972;
        /**
         * @var int<1, 12> $month
         * @psalm-suppress UnnecessaryVarAnnotation — Mago loses narrowing across if/else branches
         */
        $maxDayIn1972 = CalendarMath::calcDaysInMonth(1972, $month);
        if ($day > $maxDayIn1972) {
            $refYear = $year;
        }

        return new self($month, $day, $calendarId, $refYear);
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
}
