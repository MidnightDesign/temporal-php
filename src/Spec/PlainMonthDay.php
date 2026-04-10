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
        get => CalendarFactory::get($this->calendarId)->monthCode(
            $this->referenceISOYear,
            $this->isoMonth,
            $this->isoDay,
        );
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
        // Temporal objects with calendar fields: extract as a property bag
        // per TC39 ToTemporalMonthDay step that calls CalendarFields.
        if ($item instanceof PlainDate || $item instanceof PlainDateTime) {
            $bag = [
                'year' => $item->year,
                'month' => $item->month,
                'monthCode' => $item->monthCode,
                'day' => $item->day,
                'calendar' => $item->calendarId,
            ];
            return self::fromPropertyBag($bag, $overflow);
        }
        if ($item instanceof ZonedDateTime) {
            $bag = [
                'year' => $item->year,
                'month' => $item->month,
                'monthCode' => $item->monthCode,
                'day' => $item->day,
                'calendar' => $item->calendarId,
            ];
            return self::fromPropertyBag($bag, $overflow);
        }
        if (is_array($item)) {
            return self::fromPropertyBag($item, $overflow);
        }
        // is_object check covers remaining object types not matched above.
        return self::fromPropertyBag((array) $item, $overflow);
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

        $calendar = $this->calendarId !== 'iso8601' ? CalendarFactory::get($this->calendarId) : null;

        // Non-ISO calendar path.
        if ($calendar !== null) {
            // For non-ISO calendars, month without monthCode requires year.
            if ($hasMonth && !$hasMonthCode && !$hasYear) {
                throw new \TypeError(
                    'PlainMonthDay::with() non-ISO calendar requires year when only month is provided.',
                );
            }

            // Resolve monthCode: use provided, or default to current.
            $monthCode = null;
            $useMonthCode = false;
            if ($hasMonthCode) {
                $mc = $bag['monthCode'];
                $monthCode = is_string($mc) ? $mc : (string) $mc;
                $useMonthCode = true;
            }

            $month = null;
            if ($hasMonth) {
                $month = CalendarMath::toFiniteInt($bag['month'], 'PlainMonthDay::with() month');
                $useMonthCode = false;
            }
            if (!$hasMonth && !$hasMonthCode) {
                // Default: preserve current monthCode.
                $monthCode = $this->monthCode;
                $useMonthCode = true;
            }

            $day = $this->day;
            if ($hasDay) {
                $day = CalendarMath::toFiniteInt($bag['day'], 'PlainMonthDay::with() day');
            }

            if ($day < 1) {
                throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
            }

            // Resolve year for validation context.
            $calYear = null;
            if ($hasYear) {
                $calYear = CalendarMath::toFiniteInt($bag['year'], 'PlainMonthDay::with() year');
            }

            if ($calYear !== null) {
                // Validate month/monthCode conflict with year context.
                if ($useMonthCode && $hasMonth) {
                    assert($monthCode !== null);
                    /** @var int $month */
                    $resolvedMonth = $calendar->monthCodeToMonth($monthCode, $calYear);
                    if ($month !== $resolvedMonth) {
                        throw new InvalidArgumentException('Conflicting month and monthCode fields.');
                    }
                }

                if ($useMonthCode && $monthCode !== null) {
                    [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode(
                        $calYear,
                        $monthCode,
                        $day,
                        $overflow,
                    );
                } else {
                    /** @var int $month */
                    if ($month < 1) {
                        throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
                    }
                    [$isoY, $isoM, $isoD] = $calendar->calendarToIso($calYear, $month, $day, $overflow);
                }

                // Read back resolved monthCode+day after overflow processing.
                $resolvedMonthCode = $calendar->monthCode($isoY, $isoM, $isoD);
                $resolvedDay = $calendar->day($isoY, $isoM, $isoD);
                return self::resolveNonIsoReferenceYear($calendar, $this->calendarId, $resolvedMonthCode, $resolvedDay);
            }

            // No year: use monthCode path with reference year resolution.
            if ($useMonthCode && $monthCode !== null) {
                return self::resolveNonIsoReferenceYear($calendar, $this->calendarId, $monthCode, $day);
            }

            // Should not reach here — month without year was rejected above.
            throw new \TypeError('PlainMonthDay::with() non-ISO calendar requires year or monthCode.');
        }

        // ISO path: start from current fields.
        $month = $this->isoMonth;
        $monthCode = null;
        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            if (!is_string($mc)) {
                throw new \TypeError('monthCode must be a string.');
            }
            $monthCode = $mc;
        }
        if ($hasMonth) {
            $month = CalendarMath::toFiniteInt($bag['month'], 'PlainMonthDay::with() month');
        }

        $day = $this->isoDay;
        if ($hasDay) {
            $day = CalendarMath::toFiniteInt($bag['day'], 'PlainMonthDay::with() day');
        }

        $refYear = $this->referenceISOYear;
        if ($hasYear) {
            $refYear = CalendarMath::toFiniteInt($bag['year'], 'PlainMonthDay::with() year');
        }

        // Resolve monthCode.
        if ($hasMonthCode) {
            /** @var string $monthCode */
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
     * For ISO 8601 calendar:
     *   auto/never → "MM-DD"
     *   always     → "YYYY-MM-DD[u-ca=iso8601]"
     *   critical   → "YYYY-MM-DD[!u-ca=iso8601]"
     *
     * For non-ISO calendars:
     *   auto       → "YYYY-MM-DD[u-ca=<id>]"
     *   never      → "YYYY-MM-DD" (year shown, but no annotation)
     *   always     → "YYYY-MM-DD[u-ca=<id>]"
     *   critical   → "YYYY-MM-DD[!u-ca=<id>]"
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

        $isNonIso = $this->calendarId !== 'iso8601';
        $yearStr = self::formatYear($this->referenceISOYear);
        $dateStr = sprintf('%s-%02d-%02d', $yearStr, $this->isoMonth, $this->isoDay);

        return match ($calendarName) {
            'auto' => $isNonIso
                ? sprintf('%s[u-ca=%s]', $dateStr, $this->calendarId)
                : sprintf('%02d-%02d', $this->isoMonth, $this->isoDay),
            'never' => $isNonIso ? $dateStr : sprintf('%02d-%02d', $this->isoMonth, $this->isoDay),
            'always' => sprintf('%s[u-ca=%s]', $dateStr, $this->calendarId),
            'critical' => sprintf('%s[!u-ca=%s]', $dateStr, $this->calendarId),
            default => throw new InvalidArgumentException("Invalid calendarName value: \"{$calendarName}\"."),
        };
    }

    /**
     * Formats a year for ISO 8601 string output.
     *
     * Years 0-9999 use 4-digit format, years outside that range use 6-digit
     * with a sign prefix (+ or -).
     */
    private static function formatYear(int $year): string
    {
        if ($year < 0) {
            return sprintf('-%06d', abs($year));
        }
        if ($year > 9999) {
            return sprintf('+%06d', $year);
        }
        return sprintf('%04d', $year);
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

        $calendar = $this->calendarId !== 'iso8601' ? CalendarFactory::get($this->calendarId) : null;

        $hasYear = array_key_exists('year', $bag);
        $hasEra = array_key_exists('era', $bag);
        $hasEraYear = array_key_exists('eraYear', $bag);

        if (!$hasYear && !($hasEra && $hasEraYear && $calendar !== null)) {
            throw new \TypeError('PlainMonthDay::toPlainDate() argument must have a year property.');
        }

        $year = null;
        if ($hasYear) {
            $year = CalendarMath::toFiniteInt($bag['year'], 'toPlainDate() year');
        }

        // Resolve era + eraYear for non-ISO calendars.
        if ($calendar !== null && $hasEra && $hasEraYear) {
            /** @var mixed $eraRaw */
            $eraRaw = $bag['era'];
            /** @var mixed $eraYearRaw */
            $eraYearRaw = $bag['eraYear'];
            if (is_string($eraRaw) && $eraYearRaw !== null) {
                $eraYearInt = CalendarMath::toFiniteInt($eraYearRaw, 'toPlainDate() eraYear');
                $resolved = $calendar->resolveEra($eraRaw, $eraYearInt);
                if ($resolved !== null) {
                    $year = $resolved;
                }
            }
        }

        if ($year === null) {
            throw new \TypeError('PlainMonthDay::toPlainDate() could not resolve a year.');
        }

        // Non-ISO calendar: combine the calendar year with this PlainMonthDay's stored monthCode+day.
        if ($calendar !== null) {
            $monthCode = $this->monthCode;
            $day = $this->day;
            [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, $day, 'constrain');
            return new PlainDate($isoY, $isoM, $isoD, $this->calendarId);
        }

        // ISO path: constrain day to valid range for this year-month.
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

            // Per TC39 spec: month-day form (no year) with non-ISO calendar is invalid,
            // because a year is required to resolve the reference ISO year.
            if ($calendarId !== null && $calendarId !== 'iso8601') {
                throw new InvalidArgumentException(
                    "PlainMonthDay::from() cannot parse \"{$s}\": month-day form requires a full date (YYYY-MM-DD) with non-ISO calendar \"{$calendarId}\".",
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
        $isoYear = (int) $yearRaw;

        // For non-ISO calendars, project the ISO date through the calendar and find reference year.
        if ($calendarId !== null && $calendarId !== 'iso8601') {
            $cal = CalendarFactory::get($calendarId);
            $mc = $cal->monthCode($isoYear, $month, $day);
            $d = $cal->day($isoYear, $month, $day);
            return self::resolveNonIsoReferenceYear($cal, $calendarId, $mc, $d);
        }

        // ISO calendar: always use 1972 as referenceISOYear for strings.
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
        $hasEra = array_key_exists('era', $bag);
        $hasEraYear = array_key_exists('eraYear', $bag);

        $calendar = $calendarId !== null && $calendarId !== 'iso8601' ? CalendarFactory::get($calendarId) : null;

        // For non-ISO calendars: validate era/eraYear completeness and calendar compatibility.
        if ($calendar !== null) {
            if (($hasEra || $hasEraYear) && in_array($calendar->id(), ['chinese', 'dangi'], true)) {
                throw new \TypeError('eraYear and era are invalid for this calendar.');
            }
            if ($hasEra && !$hasEraYear) {
                throw new \TypeError('era provided without eraYear.');
            }
            if ($hasEraYear && !$hasEra) {
                throw new \TypeError('eraYear provided without era.');
            }
        }

        $hasEraAndEraYear = $hasEra && $hasEraYear;
        $hasYearLike = $hasYear || $calendar !== null && $hasEraAndEraYear;

        // For non-ISO calendars, year is required when using month (without monthCode).
        if ($calendar !== null) {
            if (!$hasMonthCode && !$hasYearLike) {
                throw new \TypeError(
                    'PlainMonthDay::from() non-ISO calendar requires year when monthCode is not provided.',
                );
            }
            // When both month and monthCode are given, year is needed to resolve conflicts.
            if ($hasMonth && $hasMonthCode && !$hasYearLike) {
                throw new \TypeError(
                    'PlainMonthDay::from() non-ISO calendar requires year when both month and monthCode are provided.',
                );
            }
        }

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

        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            if (!is_string($mc)) {
                throw new \TypeError('PlainMonthDay monthCode must be a string.');
            }
            $monthCode = $mc;
        }
        if ($hasMonth) {
            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            $month = CalendarMath::toFiniteInt($bag['month'], 'PlainMonthDay::from() month');
        }

        $day = CalendarMath::toFiniteInt($bag['day'], 'PlainMonthDay::from() day');

        // Determine the year for overflow/validation.
        $year = null;
        if ($hasYear) {
            $year = CalendarMath::toFiniteInt($bag['year'], 'PlainMonthDay::from() year');
        }

        // Resolve era + eraYear if present (overrides year for era-based calendars).
        if ($calendar !== null && $hasEraAndEraYear) {
            /** @var mixed $eraRaw */
            $eraRaw = $bag['era'];
            /** @var mixed $eraYearRaw */
            $eraYearRaw = $bag['eraYear'];
            if (is_string($eraRaw) && $eraYearRaw !== null) {
                $eraYearInt = CalendarMath::toFiniteInt($eraYearRaw, 'PlainMonthDay::from() eraYear');
                $resolved = $calendar->resolveEra($eraRaw, $eraYearInt);
                if ($resolved !== null) {
                    if ($year !== null && $year !== $resolved) {
                        throw new InvalidArgumentException(
                            "Conflicting year ({$year}) and era+eraYear (resolved to {$resolved}).",
                        );
                    }
                    $year = $resolved;
                }
            }
        }

        // For non-ISO calendars, delegate to the non-ISO path with reference year resolution.
        if ($calendar !== null) {
            return self::fromPropertyBagNonIso(
                $calendar,
                $calendarId,
                $hasMonth,
                $month,
                $hasMonthCode,
                $monthCode,
                $day,
                $year,
                $overflow,
            );
        }

        // ISO path: default year to 1972 if not provided.
        if ($year === null) {
            $year = 1972;
        }

        // ISO path: resolve monthCode to month number.
        if ($hasMonthCode) {
            /** @var string $monthCode — guaranteed non-null when $hasMonthCode is true */
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
         * @psalm-suppress UnnecessaryVarAnnotation — Mago loses narrowing across if/else branches
         */
        $maxDayIn1972 = CalendarMath::calcDaysInMonth(1972, $month);
        if ($day > $maxDayIn1972) {
            $refYear = $year;
        }

        return new self($month, $day, $calendarId, $refYear);
    }

    /**
     * Handles the non-ISO calendar path for fromPropertyBag.
     *
     * Resolves calendar fields, validates/constrains using the user's year (if given),
     * then finds the reference ISO year: the latest ISO year at or before 1972
     * where the resolved monthCode+day exists in the calendar.
     *
     * @param Internal\Calendar\CalendarProtocol $calendar
     */
    private static function fromPropertyBagNonIso(
        Internal\Calendar\CalendarProtocol $calendar,
        ?string $calendarId,
        bool $hasMonth,
        int $month,
        bool $hasMonthCode,
        ?string $monthCode,
        int $day,
        ?int $year,
        string $overflow,
    ): self {
        // When year is provided: validate/constrain the date, then derive the final monthCode+day.
        if ($year !== null) {
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

            // Resolve to ISO using the user-provided year to validate/constrain.
            if ($hasMonthCode && $monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, $day, $overflow);
            } else {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, $day, $overflow);
            }

            // Validate the resolved ISO date is within the representable range.
            $epochDays = CalendarMath::toJulianDay($isoY, $isoM, $isoD) - 2_440_588;
            if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
                throw new InvalidArgumentException(sprintf(
                    'Calendar year %d produces ISO date %d-%d-%d which is outside the representable range.',
                    $year,
                    $isoY,
                    $isoM,
                    $isoD,
                ));
            }

            // Read back the resolved calendar monthCode and day (after overflow processing).
            $resolvedMonthCode = $calendar->monthCode($isoY, $isoM, $isoD);
            $resolvedDay = $calendar->day($isoY, $isoM, $isoD);

            // Find the reference ISO year for this monthCode+day.
            return self::resolveNonIsoReferenceYear(
                $calendar,
                $calendarId,
                $resolvedMonthCode,
                $resolvedDay,
                $overflow,
            );
        }

        // No year: only monthCode path is allowed (validated above).
        if ($monthCode === null) {
            throw new \TypeError(
                'PlainMonthDay::from() non-ISO calendar requires year when monthCode is not provided.',
            );
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }

        return self::resolveNonIsoReferenceYear($calendar, $calendarId, $monthCode, $day, $overflow);
    }

    /**
     * Finds the latest ISO year at or before 1972 where the given calendar
     * monthCode+day exists, and returns a PlainMonthDay with that reference year.
     *
     * When overflow is 'constrain' and the exact day doesn't exist in any searched year,
     * the day is clamped to the maximum for that month across all searched years (so that
     * e.g. Coptic M13 day 7 constrains to 6 using a leap year, not 5 from a common year).
     * When overflow is 'reject', throws if the day exceeds every searched year's maximum.
     *
     * Searches backward from 1972 for up to 100 years to handle lunisolar
     * calendars (19-year Metonic cycle) and Islamic calendars (30-year cycle).
     *
     * @param Internal\Calendar\CalendarProtocol $calendar
     */
    private static function resolveNonIsoReferenceYear(
        Internal\Calendar\CalendarProtocol $calendar,
        ?string $calendarId,
        string $monthCode,
        int $day,
        string $overflow = 'constrain',
    ): self {
        // Phase 1: Try to find an exact match (the day fits without constraining).
        /** @var array{0: int, 1: int, 2: int}|null $bestMatch */
        $bestMatch = null;
        /** @var array<int, true> $triedCalYears */
        $triedCalYears = [];
        // Collect all candidate calendar years and their constrained days for phase 2.
        /** @var list<array{calYear: int, isoY: int, isoM: int, isoD: int, day: int}> $constrainedCandidates */
        $constrainedCandidates = [];

        for ($isoYear = 1972; $isoYear >= 1872; $isoYear--) {
            $calYearStart = $calendar->year($isoYear, 1, 1);
            $calYearEnd = $calendar->year($isoYear, 12, 31);

            $candidates = array_unique([$calYearEnd, $calYearStart]);
            rsort($candidates);

            foreach ($candidates as $tryCalYear) {
                if (isset($triedCalYears[$tryCalYear])) {
                    continue;
                }
                $triedCalYears[$tryCalYear] = true;

                // Try exact match first.
                try {
                    [$resIsoY, $resIsoM, $resIsoD] = $calendar->calendarToIsoFromMonthCode(
                        $tryCalYear,
                        $monthCode,
                        $day,
                        'reject',
                    );
                    if ($resIsoY > 1972) {
                        continue;
                    }
                    $rtMonthCode = $calendar->monthCode($resIsoY, $resIsoM, $resIsoD);
                    $rtDay = $calendar->day($resIsoY, $resIsoM, $resIsoD);
                    if ($rtMonthCode === $monthCode && $rtDay === $day) {
                        if ($bestMatch === null || $resIsoY > $bestMatch[0]) {
                            $bestMatch = [$resIsoY, $resIsoM, $resIsoD];
                        }
                    }
                } catch (InvalidArgumentException) {
                    // Exact day doesn't fit. Record the constrained result for phase 2.
                    if ($overflow === 'constrain') {
                        try {
                            [$resIsoY, $resIsoM, $resIsoD] = $calendar->calendarToIsoFromMonthCode(
                                $tryCalYear,
                                $monthCode,
                                $day,
                                'constrain',
                            );
                            if ($resIsoY <= 1972) {
                                $rtMonthCode = $calendar->monthCode($resIsoY, $resIsoM, $resIsoD);
                                if ($rtMonthCode === $monthCode) {
                                    $rtDay = $calendar->day($resIsoY, $resIsoM, $resIsoD);
                                    $constrainedCandidates[] = [
                                        'calYear' => $tryCalYear,
                                        'isoY' => $resIsoY,
                                        'isoM' => $resIsoM,
                                        'isoD' => $resIsoD,
                                        'day' => $rtDay,
                                    ];
                                }
                            }
                        } catch (InvalidArgumentException) {
                            // monthCode itself doesn't exist; keep searching.
                        }
                    }
                }
            }

            // If we found an exact match, return immediately.
            if ($bestMatch !== null) {
                return new self($bestMatch[1], $bestMatch[2], $calendarId, $bestMatch[0]);
            }
        }

        // Phase 2: No exact match. For constrain, find the candidate with the largest
        // constrained day, then resolve that day's reference year.
        if ($overflow === 'constrain' && $constrainedCandidates !== []) {
            // Find the maximum constrained day.
            $maxConstrainedDay = 0;
            foreach ($constrainedCandidates as $c) {
                if ($c['day'] > $maxConstrainedDay) {
                    $maxConstrainedDay = $c['day'];
                }
            }
            // Now find the reference year for the constrained monthCode+day.
            return self::resolveNonIsoReferenceYear($calendar, $calendarId, $monthCode, $maxConstrainedDay, 'reject');
        }

        // With 'reject' overflow, if no exact match was found, throw.
        if ($overflow === 'reject') {
            throw new InvalidArgumentException(
                "monthCode \"{$monthCode}\" with day {$day} does not exist in this calendar.",
            );
        }

        // Fallback: should not normally be reached for supported calendars.
        $calYear = $calendar->year(1972, 7, 1);
        [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($calYear, $monthCode, $day, 'constrain');
        return new self($isoM, $isoD, $calendarId, $isoY);
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

    #[\Override]
    protected function localeDefaultComponents(): string
    {
        return 'monthday';
    }

    #[\Override]
    protected function localeIsDateOnly(): bool
    {
        return true;
    }

    #[\Override]
    protected function localeIsTimeOnly(): bool
    {
        return false;
    }

    #[\Override]
    protected function toLocaleTimestamp(): int
    {
        $dt = new \DateTime(
            sprintf('%04d-%02d-%02d 00:00:00', $this->referenceISOYear, $this->isoMonth, $this->isoDay),
            new \DateTimeZone('UTC'),
        );
        return $dt->getTimestamp();
    }
}
