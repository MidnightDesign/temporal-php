<?php

declare(strict_types=1);

namespace Temporal\Spec;

use InvalidArgumentException;
use Stringable;
use Temporal\Spec\Internal\Calendar\CalendarFactory;
use Temporal\Spec\Internal\CalendarMath;
use Temporal\Spec\Internal\TemporalSerde;

/**
 * A calendar year-month without a specific day, time, or time zone.
 *
 * Only the ISO 8601 calendar is supported.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-plainyearmonth-objects
 */
final class PlainYearMonth implements Stringable
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
        get => CalendarFactory::get($this->calendarId)->era($this->isoYear, $this->isoMonth, $this->referenceISODay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $eraYear {
        get => CalendarFactory::get($this->calendarId)->eraYear(
            $this->isoYear,
            $this->isoMonth,
            $this->referenceISODay,
        );
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode {
        get => CalendarFactory::get($this->calendarId)->monthCode(
            $this->isoYear,
            $this->isoMonth,
            $this->referenceISODay,
        );
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty
     * @psalm-api
     */
    public int $year {
        get => $this->calendarId === 'iso8601'
            ? $this->isoYear
            : CalendarFactory::get($this->calendarId)->year($this->isoYear, $this->isoMonth, $this->referenceISODay);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property
     * @psalm-suppress PossiblyUnusedProperty
     * @psalm-api
     */
    public int $month {
        get => $this->calendarId === 'iso8601'
            ? $this->isoMonth
            : CalendarFactory::get($this->calendarId)->month($this->isoYear, $this->isoMonth, $this->referenceISODay);
    }

    /**
     * Number of days in this year-month's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInMonth {
        get => CalendarFactory::get($this->calendarId)->daysInMonth(
            $this->isoYear,
            $this->isoMonth,
            $this->referenceISODay,
        );
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInYear {
        get => CalendarFactory::get($this->calendarId)->daysInYear(
            $this->isoYear,
            $this->isoMonth,
            $this->referenceISODay,
        );
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $monthsInYear {
        get => CalendarFactory::get($this->calendarId)->monthsInYear(
            $this->isoYear,
            $this->isoMonth,
            $this->referenceISODay,
        );
    }

    /**
     * True if this year-month's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public bool $inLeapYear {
        get => CalendarFactory::get($this->calendarId)->inLeapYear(
            $this->isoYear,
            $this->isoMonth,
            $this->referenceISODay,
        );
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
     * The reference ISO day — a valid day for this year-month, defaults to 1.
     * Used internally for computing arithmetic anchors and in toString(calendarName:"always").
     *
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public readonly int $referenceISODay;

    /**
     * Constructs a PlainYearMonth.
     *
     * @param int|float $year            Calendar year (required).
     * @param int|float $month           Calendar month 1–12 (required).
     * @param string|null $calendar        Calendar ID string; only "iso8601" supported.
     * @param int|float   $referenceISODay Reference ISO day, defaults to 1; must be a valid day for the month.
     *
     * @throws InvalidArgumentException if year/month/referenceISODay are out of range, infinite, or the calendar is unsupported.
     */
    public function __construct(
        int|float $year,
        int|float $month,
        ?string $calendar = null,
        int|float $referenceISODay = 1,
    ) {
        if ($calendar !== null) {
            $calendar = CalendarFactory::canonicalize($calendar);
        }
        $this->calendarId = $calendar ?? 'iso8601';
        if (!is_finite((float) $year) || !is_finite((float) $month) || !is_finite((float) $referenceISODay)) {
            throw new InvalidArgumentException(
                'Invalid PlainYearMonth: year, month, and referenceISODay must be finite numbers.',
            );
        }

        $this->isoYear = (int) $year;
        $monthInt = (int) $month;
        if ($monthInt < 1 || $monthInt > 12) {
            throw new InvalidArgumentException("Invalid PlainYearMonth: month {$monthInt} is out of range 1–12.");
        }
        $this->isoMonth = $monthInt;
        $refDay = (int) $referenceISODay;

        // Validate referenceISODay is within the valid range for this year-month.
        $daysInMonth = CalendarMath::calcDaysInMonth($this->isoYear, $this->isoMonth);
        if ($refDay < 1 || $refDay > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainYearMonth: referenceISODay {$refDay} is out of range 1–{$daysInMonth}.",
            );
        }
        $this->referenceISODay = $refDay;

        // TC39 range check: §9.5.9 ISOYearMonthWithinLimits
        // Range: April −271821 … September +275760 (month-granular, not day-granular).
        if (!self::isoYearMonthWithinLimits($this->isoYear, $this->isoMonth)) {
            throw new InvalidArgumentException(
                "Invalid PlainYearMonth: {$this->isoYear}-{$this->isoMonth} is outside the representable range.",
            );
        }
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Creates a PlainYearMonth from another PlainYearMonth, an ISO 8601 string, or a
     * property-bag array with 'year' and 'month'/'monthCode' fields.
     *
     * @param self|string|array<array-key, mixed>|object $item     PlainYearMonth, ISO 8601 year-month string, or property-bag array.
     * @param array<array-key, mixed>|object|null $options Options bag: ['overflow' => 'constrain'|'reject']
     * @throws InvalidArgumentException if the string is invalid or overflow option is invalid.
     * @throws \TypeError if the type cannot be interpreted as a PlainYearMonth.
     * @psalm-api
     */
    public static function from(string|array|object $item, array|object|null $options = null): self
    {
        // Validate overflow option before processing item (per spec ordering).
        $overflow = 'constrain';
        if ($options !== null) {
            if (!is_array($options)) {
                $opts = get_object_vars($options);
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
            return new self($item->isoYear, $item->isoMonth, $item->calendarId, $item->referenceISODay);
        }
        if (is_string($item)) {
            return self::fromString($item);
        }
        if (is_array($item)) {
            return self::fromPropertyBag($item, $overflow);
        }
        throw new \TypeError(sprintf(
            'PlainYearMonth::from() expects a PlainYearMonth, ISO 8601 string, or property-bag array; got %s.',
            get_debug_type($item),
        ));
    }

    /**
     * Compares two PlainYearMonths chronologically.
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
        return $a->referenceISODay <=> $b->referenceISODay;
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainYearMonth with the specified fields overridden.
     *
     * Only 'year', 'month', and 'monthCode' fields are recognized.
     * The 'calendar' and 'timeZone' keys must not be present.
     *
     * @param array<array-key,mixed> $fields   Property bag with fields to override.
     * @param array<array-key, mixed>|object|null       $options Options bag: ['overflow' => 'constrain'|'reject']
     * @throws \TypeError             if $fields contains 'calendar' or 'timeZone'.
     * @throws InvalidArgumentException if the resulting year-month is invalid.
     * @psalm-api
     */
    public function with(array $fields, array|object|null $options = null): self
    {
        if (array_key_exists('calendar', $fields) || array_key_exists('timeZone', $fields)) {
            throw new \TypeError('PlainYearMonth::with() fields must not contain a calendar or timeZone property.');
        }

        // At least one recognized field must be present.
        if (
            !array_key_exists('year', $fields)
            && !array_key_exists('month', $fields)
            && !array_key_exists('monthCode', $fields)
            && !array_key_exists('era', $fields)
            && !array_key_exists('eraYear', $fields)
        ) {
            throw new \TypeError(
                'PlainYearMonth::with() requires at least one of: year, month, monthCode, era, eraYear.',
            );
        }

        // Validate overflow option.
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

        // Non-ISO calendar: delegate to dedicated handler.
        if ($this->calendarId !== 'iso8601') {
            return $this->withNonIso($fields, $overflow, CalendarFactory::get($this->calendarId));
        }

        // ISO path.
        $year = $this->isoYear;
        if (array_key_exists('year', $fields)) {
            $year = CalendarMath::toFiniteInt($fields['year'], 'PlainYearMonth::with() year');
        }

        $month = $this->isoMonth;
        $hasMonth = array_key_exists('month', $fields);
        $hasMonthCode = array_key_exists('monthCode', $fields);

        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $fields['monthCode'];
            if (!is_string($mc)) {
                throw new \TypeError('monthCode must be a string.');
            }
            $month = CalendarMath::monthCodeToMonth($mc);
        }
        if ($hasMonth) {
            $newMonth = CalendarMath::toFiniteInt($fields['month'], 'PlainYearMonth::with() month');
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        if ($month < 1) {
            throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
        }

        if ($overflow === 'constrain') {
            $month = min(12, $month);
        }

        // referenceISODay stays 1 after with() — TC39 spec §9.5.6 RegulateISOYearMonth.
        return new self($year, $month, $this->calendarId, 1);
    }

    /**
     * Non-ISO calendar path for with(), mirroring PlainDate::withNonIso().
     *
     * @param array<array-key,mixed> $fields
     * @param Internal\Calendar\CalendarProtocol $calendar
     */
    private function withNonIso(array $fields, string $overflow, Internal\Calendar\CalendarProtocol $calendar): self
    {
        $hasYear = array_key_exists('year', $fields);
        $hasEra = array_key_exists('era', $fields);
        $hasEraYear = array_key_exists('eraYear', $fields);
        $hasMonth = array_key_exists('month', $fields);
        $hasMonthCode = array_key_exists('monthCode', $fields);

        // Chinese/Dangi have no eras — providing era or eraYear is always a TypeError.
        if (($hasEra || $hasEraYear) && in_array($calendar->id(), ['chinese', 'dangi'], strict: true)) {
            throw new \TypeError('eraYear and era are invalid for this calendar.');
        }

        // TC39: era without eraYear (or vice versa) is TypeError when year is not also provided.
        if ($hasEra && !$hasEraYear && !$hasYear) {
            throw new \TypeError('era provided without eraYear in with() fields.');
        }
        if ($hasEraYear && !$hasEra && !$hasYear) {
            throw new \TypeError('eraYear provided without era in with() fields.');
        }

        // Resolve year: era+eraYear takes precedence over the current year if both provided.
        // When $hasYear is false, $hasEra implies $hasEraYear (and vice versa) due to checks above.
        $year = $this->year;
        if ($hasYear) {
            $year = CalendarMath::toFiniteInt($fields['year'], 'PlainYearMonth::with() year');
        } elseif ($hasEra) {
            /** @var mixed $eraRaw */
            $eraRaw = $fields['era'];
            /** @var mixed $eraYearRaw */
            $eraYearRaw = $fields['eraYear'];
            if (is_string($eraRaw) && $eraYearRaw !== null) {
                $eraYearInt = CalendarMath::toFiniteInt($eraYearRaw, 'eraYear');
                $resolved = $calendar->resolveEra($eraRaw, $eraYearInt);
                if ($resolved !== null) {
                    $year = $resolved;
                }
            }
        }

        // Resolve monthCode/month with mutual exclusion.
        // When neither is provided, default to current monthCode (not ordinal month).
        $monthCode = null;
        $month = null;
        $useMonthCode = false;

        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $fields['monthCode'];
            if (!is_string($mc)) {
                throw new \TypeError('monthCode must be a string.');
            }
            $monthCode = $mc;
            $useMonthCode = true;
        }
        if ($hasMonth) {
            $month = CalendarMath::toFiniteInt($fields['month'], 'PlainYearMonth::with() month');
            // Validate month/monthCode conflict.
            if ($hasMonthCode) {
                /** @var string $monthCode */
                $monthFromCode = $calendar->monthCodeToMonth($monthCode, $year);
                if ($month !== $monthFromCode) {
                    throw new InvalidArgumentException('Conflicting month and monthCode fields.');
                }
            }
            $useMonthCode = false; // explicit month takes precedence
        }
        if (!$hasMonth && !$hasMonthCode) {
            // Default: preserve current monthCode.
            $monthCode = $this->monthCode;
            $useMonthCode = true;
        }

        if ($useMonthCode && $monthCode !== null) {
            [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, 1, $overflow);
        } else {
            /** @var int $month */
            if ($month < 1) {
                throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
            }
            [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, 1, $overflow);
        }

        return new self($isoY, $isoM, $this->calendarId, $isoD);
    }

    /**
     * Returns a new PlainYearMonth with the given duration added.
     *
     * Only years and months are relevant; weeks and days are rejected.
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
     * Returns a new PlainYearMonth with the given duration subtracted.
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
     * Returns the Duration from $other to this year-month (this − other).
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainYearMonth or ISO 8601 year-month string.
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => 'year'|'month', 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function since(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        if ($this->calendarId !== $o->calendarId) {
            throw new InvalidArgumentException(
                "Cannot compute since() between different calendars: \"{$this->calendarId}\" and \"{$o->calendarId}\".",
            );
        }
        return self::diffYearMonth($this, $o, 'since', $options);
    }

    /**
     * Returns the Duration from this year-month to $other (other − this).
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainYearMonth or ISO 8601 year-month string.
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => 'year'|'month', 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function until(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        if ($this->calendarId !== $o->calendarId) {
            throw new InvalidArgumentException(
                "Cannot compute until() between different calendars: \"{$this->calendarId}\" and \"{$o->calendarId}\".",
            );
        }
        return self::diffYearMonth($this, $o, 'until', $options);
    }

    /**
     * Returns true if this PlainYearMonth is the same as $other.
     *
     * @param self|string|array<array-key, mixed>|object $other A PlainYearMonth or ISO 8601 year-month string.
     * @psalm-api
     */
    public function equals(string|array|object $other): bool
    {
        $o = $other instanceof self ? $other : self::from($other);
        return (
            $this->isoYear === $o->isoYear
            && $this->isoMonth === $o->isoMonth
            && $this->referenceISODay === $o->referenceISODay
            && $this->calendarId === $o->calendarId
        );
    }

    /**
     * Returns a string representation.
     *
     * Format: YYYY-MM (with ±YYYYYY for years outside 0–9999).
     * With calendarName="always" or "critical": YYYY-MM-DD[...] using referenceISODay.
     *
     * @param array<array-key, mixed>|object|null $options Options bag: ['calendarName' => 'auto'|'always'|'never'|'critical']
     * @throws InvalidArgumentException for invalid calendarName values.
     * @psalm-api
     */
    #[\Override]
    public function toString(array|object|null $options = null): string
    {
        $yearStr = self::formatYear($this->isoYear);
        $base = sprintf('%s-%02d', $yearStr, $this->isoMonth);

        $calendarName = 'auto';
        if ($options !== null && is_array($options) && array_key_exists('calendarName', $options)) {
            /** @var mixed $cn */
            $cn = $options['calendarName'];
            if (!is_string($cn)) {
                throw new \TypeError('calendarName option must be a string.');
            }
            $calendarName = $cn;
        }

        // When calendar annotation is shown, always include the referenceDay.
        $isNonIso = $this->calendarId !== 'iso8601';
        $baseWithDay = sprintf('%s-%02d', $base, $this->referenceISODay);

        return match ($calendarName) {
            'auto' => $isNonIso ? sprintf('%s[u-ca=%s]', $baseWithDay, $this->calendarId) : $base,
            'never' => $isNonIso ? $baseWithDay : $base,
            'always' => sprintf('%s[u-ca=%s]', $baseWithDay, $this->calendarId),
            'critical' => sprintf('%s[!u-ca=%s]', $baseWithDay, $this->calendarId),
            default => throw new InvalidArgumentException("Invalid calendarName value: \"{$calendarName}\"."),
        };
    }

    /**
     * Always throws TypeError — PlainYearMonth must not be used in arithmetic context.
     *
     * @throws \TypeError always.
     * @psalm-return never
     * @psalm-api
     */
    public function valueOf(): never
    {
        throw new \TypeError('PlainYearMonth objects are not orderable');
    }

    /**
     * Converts this PlainYearMonth to a PlainDate by supplying the day.
     *
     * @param array<array-key, mixed>|object $fields Must contain 'day' key.
     * @throws \TypeError             if $fields is not an object/array or 'day' is missing.
     * @throws InvalidArgumentException if the resulting date is invalid.
     * @psalm-api
     */
    public function toPlainDate(array|object $fields): PlainDate
    {
        if (is_array($fields)) {
            $bag = $fields;
        } else {
            $bag = get_object_vars($fields);
        }

        if (!array_key_exists('day', $bag)) {
            throw new \TypeError('PlainYearMonth::toPlainDate() argument must have a day property.');
        }

        $day = CalendarMath::toFiniteInt($bag['day'], 'toPlainDate() day');

        // Constrain day to valid range for this year-month.
        $maxDay = CalendarMath::calcDaysInMonth($this->isoYear, $this->isoMonth);
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }
        if ($day > $maxDay) {
            $day = $maxDay; // constrain (default overflow behaviour per spec)
        }

        return new PlainDate($this->isoYear, $this->isoMonth, $day);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parses an ISO 8601 year-month string into a PlainYearMonth.
     *
     * Accepted formats:
     *   YYYY-MM, ±YYYYYY-MM (extended), YYYYMM (compact), ±YYYYYYMM
     *   YYYY-MM-DD, ±YYYYYY-MM-DD — date strings (extract year+month; referenceISODay = day)
     *   YYYYMMDD, ±YYYYYYMMDD
     * Optional trailing time, offset, and bracket annotations are accepted;
     * only the date portion is used. Z (UTC designator) and fractional minutes are not valid.
     *
     * @throws InvalidArgumentException for invalid or out-of-range dates.
     */
    private static function fromString(string $s): self
    {
        if ($s === '') {
            throw new InvalidArgumentException('PlainYearMonth::from() received an empty string.');
        }
        // Reject non-ASCII minus sign (U+2212 = \xe2\x88\x92).
        if (str_contains($s, "\u{2212}")) {
            throw new InvalidArgumentException(
                "PlainYearMonth::from() cannot parse \"{$s}\": non-ASCII minus sign is not allowed.",
            );
        }
        // Reject more than 9 fractional-second digits.
        if (preg_match('/[.,]\d{10,}/', $s) === 1) {
            throw new InvalidArgumentException(
                "PlainYearMonth::from() cannot parse \"{$s}\": fractional seconds may have at most 9 digits.",
            );
        }

        // Full regex for a PlainYearMonth string.
        // Date part: YYYY-MM | ±YYYYYY-MM (year-month only — NO day)
        //         OR: YYYY-MM-DD | ±YYYYYY-MM-DD (full date)
        //         OR: YYYYMMDD | YYYYMM | ±YYYYYYMMDD | ±YYYYYY MM (compact)
        // Optional time: T + HH[:MM[:SS[frac]]]
        // Optional non-Z offset: ±HH[:MM[:SS[.frac]]] or ±HHMM...
        // Z is never valid for PlainYearMonth
        // Bracket annotations are allowed
        // Groups: 1=year, 2=month[-day], 3=HH, 4=MM, 5=SS, 6=frac, 7=annotations
        $pattern = '/^([+-]\d{6}|\d{4})(-\d{2}(?:-\d{2})?|\d{2}(?:\d{2})?)(?:[Tt ](\d{2})(?::?(\d{2})(?::?(\d{2})([.,]\d+)?)?)?(?:[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?)?((?:\[[^\]]*\])*)$/';

        /** @var list<string> $m */
        $m = [];
        if (preg_match($pattern, $s, $m) !== 1) {
            throw new InvalidArgumentException(
                "PlainYearMonth::from() cannot parse \"{$s}\": invalid ISO 8601 year-month string.",
            );
        }

        $yearRaw = $m[1];
        $dateRest = $m[2];

        // Reject minus-zero extended year (-000000).
        if (preg_match('/^-0{6}$/', $yearRaw) === 1) {
            throw new InvalidArgumentException('Cannot use negative zero as extended year.');
        }

        $year = (int) $yearRaw;
        $refDay = 1;

        if (str_starts_with($dateRest, '-')) {
            // Extended/normal date: -MM or -MM-DD
            $month = (int) substr(string: $dateRest, offset: 1, length: 2);
            if (strlen($dateRest) >= 6) {
                // -MM-DD
                $refDay = (int) substr(string: $dateRest, offset: 4, length: 2);
            }
        } else {
            // Compact: MM or MMDD
            $month = (int) substr(string: $dateRest, offset: 0, length: 2);
            if (strlen($dateRest) === 4) {
                $refDay = (int) substr(string: $dateRest, offset: 2, length: 2);
            }
        }

        // Validate time portion if present.
        if ($m[3] !== '') {
            $hour = (int) $m[3];
            if ($hour > 23) {
                throw new InvalidArgumentException(
                    "PlainYearMonth::from() cannot parse \"{$s}\": hour {$hour} out of range.",
                );
            }
            if ($m[4] !== '') {
                $minute = (int) $m[4];
                if ($minute > 59) {
                    throw new InvalidArgumentException(
                        "PlainYearMonth::from() cannot parse \"{$s}\": minute {$minute} out of range.",
                    );
                }
                if ($m[5] !== '') {
                    $second = (int) $m[5];
                    if ($second > 60) {
                        throw new InvalidArgumentException(
                            "PlainYearMonth::from() cannot parse \"{$s}\": second {$second} out of range.",
                        );
                    }
                }
            }
        }

        // Validate bracket annotations and extract calendar ID.
        $annotationSection = $m[7];
        $calendarId = CalendarMath::validateAnnotations($annotationSection, $s);

        // Per TC39 spec: year-month form (no day) with non-ISO calendar is invalid,
        // because a day is required to resolve the reference ISO day.
        $hasDay = str_starts_with($dateRest, '-') ? strlen($dateRest) >= 6 : strlen($dateRest) === 4;
        if (!$hasDay && $calendarId !== null && $calendarId !== 'iso8601') {
            throw new InvalidArgumentException(
                "PlainYearMonth::from() cannot parse \"{$s}\": year-month form requires a full date (YYYY-MM-DD) with non-ISO calendar \"{$calendarId}\".",
            );
        }

        // TC39 spec §9.2.4 ParseTemporalYearMonthString + §9.5.2 ToTemporalYearMonth:
        // For ISO 8601, the referenceISODay is always constrained to 1 regardless of
        // what day appeared in the input string. The day field in the string is used only
        // to validate the date (month/day combination must be valid), but the resulting
        // PlainYearMonth always has referenceISODay = 1.
        // See: https://tc39.es/proposal-temporal/#sec-temporal-totemporalyearmonth step 5.d

        // Validate month range before day validation.
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(sprintf(
                'PlainYearMonth::from() cannot parse "%s": month %d out of range.',
                $s,
                $month,
            ));
        }

        // Validate that $refDay is a valid day for this month (day from string).
        $daysInMonth = CalendarMath::calcDaysInMonth($year, $month);
        if ($refDay < 1 || $refDay > $daysInMonth) {
            throw new InvalidArgumentException(
                "PlainYearMonth::from() cannot parse \"{$s}\": day {$refDay} is out of range for month {$month}.",
            );
        }

        // For ISO calendar: referenceISODay = 1 (per spec §9.5.2 step 5.d).
        // For non-ISO calendars: preserve the referenceISODay from the string.
        $effectiveRefDay = $calendarId !== null && $calendarId !== 'iso8601' ? $refDay : 1;
        return new self($year, $month, $calendarId, $effectiveRefDay);
    }

    /**
     * Creates a PlainYearMonth from a property-bag array.
     *
     * @param array<array-key,mixed> $bag
     * @param string $overflow 'constrain' or 'reject'
     * @throws \TypeError if required fields are missing or have wrong type.
     * @throws InvalidArgumentException if the year-month is invalid.
     */
    private static function fromPropertyBag(array $bag, string $overflow = 'constrain'): self
    {
        // Validate calendar key if present.
        $calendarId = null;
        if (array_key_exists('calendar', $bag)) {
            /** @var mixed $cal */
            $cal = $bag['calendar'];
            if (!is_string($cal)) {
                throw new \TypeError(sprintf(
                    'PlainYearMonth calendar must be a string; got %s.',
                    get_debug_type($cal),
                ));
            }
            if (preg_match('/^-0{6}/', $cal) === 1) {
                throw new InvalidArgumentException(
                    "Cannot use negative zero as extended year in calendar string \"{$cal}\".",
                );
            }
            $calendarId = CalendarFactory::canonicalize(self::extractCalendarId($cal));
        }

        $hasEra = array_key_exists('era', $bag);
        $hasEraYear = array_key_exists('eraYear', $bag);
        $hasEraAndEraYear = $hasEra && $hasEraYear;

        if ($hasEra !== $hasEraYear) {
            throw new \TypeError('PlainYearMonth property bag must have both era and eraYear, or neither.');
        }

        $calendarSupportsEras =
            $calendarId !== null && $calendarId !== 'iso8601' && !in_array($calendarId, ['chinese', 'dangi'], strict: true);

        if (!array_key_exists('year', $bag) && (!$hasEraAndEraYear || !$calendarSupportsEras)) {
            throw new \TypeError('PlainYearMonth property bag must have a year field.');
        }
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('PlainYearMonth property bag must have a month or monthCode field.');
        }

        $calendar = $calendarId !== null && $calendarId !== 'iso8601' ? CalendarFactory::get($calendarId) : null;

        // Extract year from the bag, or resolve from era + eraYear.
        $year = 0;
        if (array_key_exists('year', $bag)) {
            /** @var mixed $yearRaw */
            $yearRaw = $bag['year'];
            if ($yearRaw === null) {
                throw new \TypeError('PlainYearMonth property bag year field must not be undefined.');
            }
            $year = CalendarMath::toFiniteInt($yearRaw, 'PlainYearMonth year');
        }

        // Resolve era + eraYear if present (overrides year for era-based calendars).
        if ($calendar !== null && array_key_exists('era', $bag) && array_key_exists('eraYear', $bag)) {
            /** @var mixed $eraRaw */
            $eraRaw = $bag['era'];
            /** @var mixed $eraYearRaw */
            $eraYearRaw = $bag['eraYear'];
            if (is_string($eraRaw) && $eraYearRaw !== null) {
                $eraYearInt = CalendarMath::toFiniteInt($eraYearRaw, 'PlainYearMonth eraYear');
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
            if (!is_string($monthCodeRaw)) {
                throw new \TypeError('PlainYearMonth monthCode must be a string.');
            }
            $monthCode = $monthCodeRaw;
            $month = $calendar !== null
                ? $calendar->monthCodeToMonth($monthCode, $year)
                : CalendarMath::monthCodeToMonth($monthCode);
        }

        if ($hasMonth) {
            /** @var mixed $monthRaw */
            $monthRaw = $bag['month'] ?? null;
            if ($monthRaw === null) {
                throw new \TypeError('PlainYearMonth property bag month field must not be undefined.');
            }
            $newMonth = CalendarMath::toFiniteInt($monthRaw, 'PlainYearMonth month');
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        /** @var int $month */

        // month < 1 is always invalid (cannot constrain below minimum of 1).
        if ($month < 1) {
            throw new InvalidArgumentException("Invalid PlainYearMonth: month {$month} must be at least 1.");
        }

        // Non-ISO calendar: resolve calendar fields to ISO via the calendar protocol.
        // Use day=1 as the reference day for year-month resolution.
        if ($calendar !== null) {
            if ($monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, 1, $overflow);
            } else {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, 1, $overflow);
            }
            return new self($isoY, $isoM, $calendarId, $isoD);
        }

        if ($overflow === 'constrain') {
            $month = min(12, $month);
        }

        return new self($year, $month, $calendarId, 1);
    }

    /**
     * Validates that a PlainYearMonth's first day (day=1) is a valid PlainDate.
     *
     * The since()/until() operations use day=1 of each year-month as the anchor date,
     * so both must be valid PlainDates (not just valid PlainYearMonths).
     *
     * @throws InvalidArgumentException if the first day of the month is outside the PlainDate range.
     */
    private static function validateYearMonthForDiff(self $ym): void
    {
        $epochDays = CalendarMath::toJulianDay($ym->isoYear, $ym->isoMonth, 1) - 2_440_588;
        if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
            throw new InvalidArgumentException(
                "PlainYearMonth {$ym->isoYear}-{$ym->isoMonth}: first day is outside the representable PlainDate range.",
            );
        }
    }

    /**
     * Core implementation for since() and until().
     *
     * TC39 CalendarDateUntil is always called as (temporalDate, other). For
     * "since", the final result is negated.
     *
     * @param string $operation 'since' or 'until'
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     */
    private static function diffYearMonth(
        self $temporalDate,
        self $other,
        string $operation,
        array|object|null $options,
    ): Duration {
        /** @var list<string> $validUnits */
        static $validUnits = ['auto', 'month', 'months', 'year', 'years'];
        /** @var list<string> $disallowedUnits */
        static $disallowedUnits = [
            'week',
            'weeks',
            'day',
            'days',
            'hour',
            'hours',
            'minute',
            'minutes',
            'second',
            'seconds',
            'millisecond',
            'milliseconds',
            'microsecond',
            'microseconds',
            'nanosecond',
            'nanoseconds',
        ];

        $largestUnit = 'year'; // default for PlainYearMonth per spec (auto = year)
        $largestUnitExplicit = false;
        $smallestUnit = null;
        $roundingMode = 'trunc';
        $roundingIncrement = 1;

        if ($options !== null) {
            $opts = is_array($options) ? $options : get_object_vars($options);

            // largestUnit
            if (array_key_exists('largestUnit', $opts)) {
                /** @var mixed $lu */
                $lu = $opts['largestUnit'];
                if ($lu !== null && !is_string($lu)) {
                    throw new \TypeError('largestUnit option must be a string.');
                }
                if (is_string($lu)) {
                    if (
                        in_array($lu, $disallowedUnits, strict: true)
                        || !in_array($lu, $validUnits, strict: true)
                    ) {
                        throw new InvalidArgumentException("Invalid largestUnit value: \"{$lu}\".");
                    }
                    $largestUnit = $lu;
                    $largestUnitExplicit = true;
                }
            }

            // roundingIncrement
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
                    if (
                        in_array($su, $disallowedUnits, strict: true)
                        || !in_array($su, $validUnits, strict: true)
                    ) {
                        throw new InvalidArgumentException("Invalid smallestUnit value: \"{$su}\".");
                    }
                    $smallestUnit = $su;
                }
            }
        }

        // Default smallestUnit is 'month' for PlainYearMonth.
        if ($smallestUnit === null) {
            $smallestUnit = 'month';
        }

        // Normalize to canonical forms. 'auto' for PlainYearMonth = 'year'.
        $normLargest = match ($largestUnit) {
            'months' => 'month',
            'auto', 'years' => 'year',
            default => $largestUnit,
        };
        $normSmallest = match ($smallestUnit) {
            'months', 'auto' => 'month',
            'years' => 'year',
            default => $smallestUnit,
        };

        /** @var array<string, int> $unitRank */
        static $unitRank = ['year' => 2, 'years' => 2, 'month' => 1, 'months' => 1, 'auto' => 1];

        $suRank = $unitRank[$smallestUnit];
        $luRank = $unitRank[$largestUnit];

        if ($suRank > $luRank) {
            if ($largestUnitExplicit) {
                throw new InvalidArgumentException(
                    "smallestUnit \"{$smallestUnit}\" cannot be larger than largestUnit \"{$largestUnit}\".",
                );
            }
            $normLargest = $normSmallest;
        }

        // Short-circuit when both year-months represent the same calendar month.
        // Compare all three ISO fields because non-ISO calendars can map different
        // calendar months to the same ISO year/month (distinguished by referenceISODay).
        if (
            $temporalDate->isoYear === $other->isoYear
            && $temporalDate->isoMonth === $other->isoMonth
            && $temporalDate->referenceISODay === $other->referenceISODay
        ) {
            return new Duration();
        }

        // Validate that day=1 of each year-month is a valid PlainDate (TC39 §DifferenceTemporalPlainYearMonth step 8).
        self::validateYearMonthForDiff($temporalDate);
        self::validateYearMonthForDiff($other);

        // TC39 CalendarDateUntil(temporalDate, other) — always in (this, other) order.
        // The sign and leap-month asymmetry are handled inside dateUntil.
        $calId = $temporalDate->calendarId;
        $cal = CalendarFactory::get($calId);
        [$rawYears, $rawMonths] = $cal->dateUntil(
            $temporalDate->isoYear,
            $temporalDate->isoMonth,
            $temporalDate->referenceISODay,
            $other->isoYear,
            $other->isoMonth,
            $other->referenceISODay,
            'year',
        );
        // For totalMonths, use dateUntil with largestUnit='month' for correct result
        // in calendars with variable months-per-year (e.g. Hebrew 13-month years).
        [, $totalMonths] = $cal->dateUntil(
            $temporalDate->isoYear,
            $temporalDate->isoMonth,
            $temporalDate->referenceISODay,
            $other->isoYear,
            $other->isoMonth,
            $other->referenceISODay,
            'month',
        );

        // TC39: for "since", GetDifferenceSettings negates the rounding mode.
        $sinceSign = $operation === 'since' ? -1 : 1;
        if ($operation === 'since') {
            $roundingMode = self::negateRoundingMode($roundingMode);
        }

        if ($normLargest === 'month') {
            if ($normSmallest === 'month') {
                if ($roundingIncrement === 1 && $roundingMode === 'trunc') {
                    return new Duration(months: $sinceSign * $totalMonths);
                }
                $rounded = self::roundCalendarYearMonths(
                    $totalMonths,
                    $temporalDate,
                    $roundingIncrement,
                    $roundingMode,
                    false,
                );
                return new Duration(months: $sinceSign * $rounded);
            }
            return new Duration(months: $sinceSign * $totalMonths);
        }

        // normLargest === 'year'
        if ($normSmallest === 'year') {
            if ($roundingIncrement === 1 && $roundingMode === 'trunc') {
                return new Duration(years: $sinceSign * $rawYears);
            }
            $roundedYears = self::roundCalendarYearsYM(
                $rawYears,
                $rawMonths,
                $temporalDate,
                $roundingIncrement,
                $roundingMode,
                false,
            );
            return new Duration(years: $sinceSign * $roundedYears);
        }

        // normSmallest === 'month', normLargest === 'year'
        if ($roundingIncrement === 1 && $roundingMode === 'trunc') {
            return new Duration(years: $sinceSign * $rawYears, months: $sinceSign * $rawMonths);
        }
        [$ry, $rm] = self::roundCalendarMonthsWithinYear(
            $rawYears,
            $rawMonths,
            $temporalDate,
            $roundingIncrement,
            $roundingMode,
            false,
        );
        return new Duration(years: $sinceSign * $ry, months: $sinceSign * $rm);
    }

    /**
     * Calendar-aware rounding for totalMonths in a PlainYearMonth diff.
     *
     * Progress is computed as the fraction of remaining months within the increment bucket,
     * measured in days (since months have varying lengths).
     *
     * @throws InvalidArgumentException if the rounded result is outside the valid range.
     */
    private static function roundCalendarYearMonths(
        int $totalMonths,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
    ): int {
        $sign = $totalMonths >= 0 ? 1 : -1;
        $absMonths = abs($totalMonths);

        $floorCount = intdiv(num1: $absMonths, num2: $increment) * $increment;
        $remainingMonths = $absMonths - $floorCount;

        // No rounding needed when mode is trunc/floor (always rounds down).
        if ($increment === 1) {
            $sign2 = $totalMonths >= 0 ? 1 : -1;
            // Validate range.
            $dir2 = $receiverIsLater ? -$sign2 : $sign2;
            [$ry, $rm] = self::addSignedMonthsYM($receiver->isoYear, $receiver->isoMonth, $dir2 * $absMonths);
            if (!self::isoYearMonthWithinLimits($ry, $rm)) {
                throw new InvalidArgumentException(
                    'PlainYearMonth arithmetic result is outside the representable range.',
                );
            }
            return $totalMonths;
        }

        // Anchor: receiver going toward "other" by floorCount months.
        $dir = $receiverIsLater ? -$sign : $sign;

        // Compute anchor and next boundary as year-month.
        [$anchorY, $anchorM] = self::addSignedMonthsYM($receiver->isoYear, $receiver->isoMonth, $dir * $floorCount);
        [$nextY, $nextM] = self::addSignedMonthsYM(
            $receiver->isoYear,
            $receiver->isoMonth,
            $dir * ($floorCount + $increment),
        );

        // Validate the next boundary is within the representable range (§NudgeToCalendarUnit step 8).
        if (!self::isoYearMonthWithinLimits($nextY, $nextM)) {
            throw new InvalidArgumentException('PlainYearMonth rounding result is outside the representable range.');
        }

        // Interval size in days (anchor → next boundary).
        $anchorJdn = CalendarMath::toJulianDay($anchorY, $anchorM, 1);
        $nextJdn = CalendarMath::toJulianDay($nextY, $nextM, 1);
        $intervalDays = abs($nextJdn - $anchorJdn);

        // Compute how far the remaining months reach within the interval (in days).
        [$remY, $remM] = self::addSignedMonthsYM($anchorY, $anchorM, $dir * $remainingMonths);
        $remJdn = CalendarMath::toJulianDay($remY, $remM, 1);
        $remDays = abs($remJdn - $anchorJdn);

        $progress = $intervalDays > 0 ? $remDays / $intervalDays : 0.0;

        $roundUp = CalendarMath::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbs = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate range.
        [$ry, $rm] = self::addSignedMonthsYM($receiver->isoYear, $receiver->isoMonth, $dir * $roundedAbs);
        if (!self::isoYearMonthWithinLimits($ry, $rm)) {
            throw new InvalidArgumentException('PlainYearMonth arithmetic result is outside the representable range.');
        }

        return $sign * $roundedAbs;
    }

    /**
     * Inverts a rounding mode for negative diffs.
     */
    private static function negateRoundingMode(string $mode): string
    {
        return match ($mode) {
            'floor' => 'ceil',
            'ceil' => 'floor',
            'halfFloor' => 'halfCeil',
            'halfCeil' => 'halfFloor',
            default => $mode,
        };
    }

    private static function roundCalendarYearsYM(
        int $years,
        int $months,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
    ): int {
        if ($years !== 0) {
            $sign = $years >= 0 ? 1 : -1;
        } else {
            $sign = $months >= 0 ? 1 : -1;
        }
        $absYears = abs($years);

        $floorCount = intdiv(num1: $absYears, num2: $increment) * $increment;

        $dir = $receiverIsLater ? -$sign : $sign;

        // Anchor at floorCount years from receiver.
        [$anchorY, $anchorM] = self::addSignedMonthsYM(
            $receiver->isoYear,
            $receiver->isoMonth,
            $dir * $floorCount * 12,
        );
        [$nextY, $nextM] = self::addSignedMonthsYM(
            $receiver->isoYear,
            $receiver->isoMonth,
            $dir * ($floorCount + $increment) * 12,
        );

        // Validate the next boundary is within the representable range.
        if (!self::isoYearMonthWithinLimits($nextY, $nextM)) {
            throw new InvalidArgumentException('PlainYearMonth rounding result is outside the representable range.');
        }

        $anchorJdn = CalendarMath::toJulianDay($anchorY, $anchorM, 1);
        $nextJdn = CalendarMath::toJulianDay($nextY, $nextM, 1);
        $intervalDays = abs($nextJdn - $anchorJdn);

        // Target: anchor + (total remaining months from anchor to target).
        // The target is at floorCount*12 + remaining_months from receiver,
        // i.e., the full abs diff (absYears*12 + absMonths) months from receiver.
        // From anchor (= receiver + dir*floorCount*12), the target is at dir*(absYears-floorCount)*12 + dir*absMonths.
        $absMonths = abs($months);
        $remMonthsFromAnchor = (($absYears - $floorCount) * 12) + $absMonths;
        [$subY, $subM] = self::addSignedMonthsYM($anchorY, $anchorM, $dir * $remMonthsFromAnchor);
        $subJdn = CalendarMath::toJulianDay($subY, $subM, 1);
        $remDays = abs($subJdn - $anchorJdn);

        $progress = $intervalDays > 0 ? $remDays / $intervalDays : 0.0;
        $roundUp = CalendarMath::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbs = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate range.
        [$ry, $rm] = self::addSignedMonthsYM($receiver->isoYear, $receiver->isoMonth, $dir * $roundedAbs * 12);
        if (!self::isoYearMonthWithinLimits($ry, $rm)) {
            throw new InvalidArgumentException('PlainYearMonth arithmetic result is outside the representable range.');
        }

        return $sign * $roundedAbs;
    }

    /**
     * Rounds the months component within the {years, months} diff representation.
     *
     * The years component is unchanged; only the months sub-component is rounded.
     * The anchor is receiver moved by |rawYears| years (toward the other); rounding
     * is done within the bucket of size `increment` months from that anchor.
     * If rounding up would push months to >= 12, the carry propagates into years.
     *
     * @return array{0: int, 1: int} [roundedYears, roundedMonths]
     * @throws InvalidArgumentException if the rounded result is outside the valid range.
     */
    private static function roundCalendarMonthsWithinYear(
        int $rawYears,
        int $rawMonths,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
    ): array {
        if ($rawYears !== 0) {
            $sign = $rawYears >= 0 ? 1 : -1;
        } else {
            $sign = $rawMonths >= 0 ? 1 : -1;
        }

        // Direction from receiver toward the other.
        $dir = $receiverIsLater ? -$sign : $sign;

        // Yearly anchor: receiver moved by |rawYears| years (the whole-year portion of the diff).
        [$yearAnchorY, $yearAnchorM] = self::addSignedMonthsYM(
            $receiver->isoYear,
            $receiver->isoMonth,
            $dir * abs($rawYears) * 12,
        );

        $absMonths = abs($rawMonths);
        $floorCount = intdiv(num1: $absMonths, num2: $increment) * $increment;
        $nextCount = $floorCount + $increment;

        // Month anchor: yearAnchor moved by floorCount months (= lower boundary of the rounding bucket).
        [$monthAnchorY, $monthAnchorM] = self::addSignedMonthsYM($yearAnchorY, $yearAnchorM, $dir * $floorCount);
        [$nextY, $nextM] = self::addSignedMonthsYM($yearAnchorY, $yearAnchorM, $dir * $nextCount);

        // Validate that the next boundary is representable.
        if (!self::isoYearMonthWithinLimits($nextY, $nextM)) {
            throw new InvalidArgumentException('PlainYearMonth rounding result is outside the representable range.');
        }

        // Calendar-aware progress: measure remaining months in days from the month anchor.
        $monthAnchorJdn = CalendarMath::toJulianDay($monthAnchorY, $monthAnchorM, 1);
        $nextJdn = CalendarMath::toJulianDay($nextY, $nextM, 1);
        $intervalDays = abs($nextJdn - $monthAnchorJdn);

        $remainingMonths = $absMonths - $floorCount;
        [$remY, $remM] = self::addSignedMonthsYM($monthAnchorY, $monthAnchorM, $dir * $remainingMonths);
        $remJdn = CalendarMath::toJulianDay($remY, $remM, 1);
        $remDays = abs($remJdn - $monthAnchorJdn);

        $progress = $intervalDays > 0 ? $remDays / $intervalDays : 0.0;
        $roundUp = CalendarMath::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbsMonths = $roundUp ? $nextCount : $floorCount;

        // Convert rounded abs months to a years+months result with carry.
        $carryYears = intdiv(num1: $roundedAbsMonths, num2: 12);
        $remainMonths = $roundedAbsMonths % 12;
        $roundedYears = $rawYears + ($sign * $carryYears);
        $roundedMonths = $sign * $remainMonths;

        // Validate range.
        $totalAbsMonths = (abs($rawYears) * 12) + $roundedAbsMonths;
        [$ry, $rm] = self::addSignedMonthsYM($receiver->isoYear, $receiver->isoMonth, $dir * $totalAbsMonths);
        if (!self::isoYearMonthWithinLimits($ry, $rm)) {
            throw new InvalidArgumentException('PlainYearMonth arithmetic result is outside the representable range.');
        }

        return [$roundedYears, $roundedMonths];
    }

    /**
     * Shared implementation for add() and subtract().
     *
     * @param array<array-key, mixed>|object|null $options
     */
    private function addDuration(int $sign, Duration $dur, array|object|null $options): self
    {
        // Validate overflow option. Per TC39 spec §9.5.7:
        // GetOption calls ToString(value) first, then validates the string.
        // So non-string values get coerced to string, and the resulting string is validated.
        // This means null → "null" → RangeError, true → "true" → RangeError, etc.
        // The overflow value itself is not consulted for arithmetic (PlainYearMonth has no day field to constrain).
        if ($options !== null && is_array($options) && array_key_exists('overflow', $options)) {
            /** @var mixed $ov */
            $ov = $options['overflow'];
            if ($ov === null || is_bool($ov) || is_int($ov) || is_float($ov)) {
                // Coerce to string per spec, then fail validation.
                $ovStr = (string) $ov;
                throw new InvalidArgumentException(
                    "Invalid overflow value: \"{$ovStr}\"; must be 'constrain' or 'reject'.",
                );
            }
            if (!is_string($ov)) {
                throw new \TypeError('overflow option must be a string.');
            }
            if ($ov !== 'constrain' && $ov !== 'reject') {
                throw new InvalidArgumentException(
                    "Invalid overflow value: \"{$ov}\"; must be 'constrain' or 'reject'.",
                );
            }
        }

        // TC39 spec §9.5.7 step 8: The intermediate PlainDate created from {year, month, day=1}
        // must be within the valid PlainDate range (ISODateWithinLimits check via CreateTemporalDate).
        self::validateYearMonthForDiff($this);

        // PlainYearMonth.add/subtract: sub-month units (weeks, days, hours, etc.) are forbidden.
        // TC39 spec: §9.5.7 AddDurationToOrSubtractDurationFromPlainYearMonth step 4.
        // Any non-zero week, day, or sub-day field causes a RangeError.
        if (
            (int) $dur->weeks !== 0
            || (int) $dur->days !== 0
            || (int) $dur->hours !== 0
            || (int) $dur->minutes !== 0
            || (int) $dur->seconds !== 0
            || (int) $dur->milliseconds !== 0
            || (int) $dur->microseconds !== 0
            || (int) $dur->nanoseconds !== 0
        ) {
            throw new InvalidArgumentException(
                'PlainYearMonth::add()/subtract() does not support sub-month units (weeks, days, hours, etc.).',
            );
        }

        $years = $sign * (int) $dur->years;
        $months = $sign * (int) $dur->months;

        // Extract the actual overflow value for the calendar protocol.
        $overflow = 'constrain';
        if ($options !== null && is_array($options) && array_key_exists('overflow', $options)) {
            /** @var mixed $ovVal */
            $ovVal = $options['overflow'];
            if (is_string($ovVal) && ($ovVal === 'constrain' || $ovVal === 'reject')) {
                $overflow = $ovVal;
            }
        }

        // Delegate to the calendar protocol for date arithmetic.
        $cal = CalendarFactory::get($this->calendarId);
        [$newIsoY, $newIsoM, $newIsoD] = $cal->dateAdd(
            $this->isoYear,
            $this->isoMonth,
            $this->referenceISODay,
            $years,
            $months,
            0,
            0,
            $overflow,
        );

        // Range check using month-granular limit.
        if (!self::isoYearMonthWithinLimits($newIsoY, $newIsoM)) {
            throw new InvalidArgumentException('PlainYearMonth arithmetic result is outside the representable range.');
        }

        return new self($newIsoY, $newIsoM, $this->calendarId, $newIsoD);
    }

    /**
     * Adds $signedMonths months to [year, month] and returns [$newYear, $newMonth].
     *
     * @return array{0: int, 1: int}
     */
    private static function addSignedMonthsYM(int $year, int $month, int $signedMonths): array
    {
        $m = $month + $signedMonths;
        $y = $year;

        if ($m > 12) {
            $y += intdiv(num1: $m - 1, num2: 12);
            $m = (($m - 1) % 12) + 1;
        } elseif ($m < 1) {
            $y += intdiv(num1: $m - 12, num2: 12);
            $m = (((($m - 1) % 12) + 12) % 12) + 1;
        }

        return [$y, $m];
    }

    /**
     * Formats a year as a 4-digit or ±6-digit string per TC39 spec.
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
     * Extracts the calendar ID from a calendar string (same logic as PlainDate).
     */
    private static function extractCalendarId(string $cal): string
    {
        if (str_contains($cal, '[')) {
            if (preg_match('/\[!?u-ca=([^\]]+)\]/', $cal, $m) === 1) {
                return strtolower($m[1]);
            }
            return 'iso8601';
        }
        if (preg_match('/^\d/', $cal) === 1 && preg_match('/^\d{1,6}-/', $cal) === 1) {
            return 'iso8601';
        }
        // ASCII-only lowercase.
        $lower = '';
        $len = strlen($cal);
        for ($i = 0; $i < $len; $i++) {
            $c = $cal[$i];
            $o = ord($c);
            $lower .= $o >= 0x41 && $o <= 0x5A ? chr($o + 32) : $c;
        }
        return $lower;
    }

    /**
     * TC39 §9.5.9 ISOYearMonthWithinLimits.
     *
     * Valid range: April −271821 … September +275760.
     */
    private static function isoYearMonthWithinLimits(int $year, int $month): bool
    {
        if ($year < -271_821 || $year > 275_760) {
            return false;
        }
        if ($year === -271_821 && $month < 4) {
            return false;
        }
        if ($year === 275_760 && $month > 9) {
            return false;
        }
        return true;
    }

    #[\Override]
    protected function localeDefaultComponents(): string
    {
        return 'yearmonth';
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
        // Use referenceISODay to ensure the timestamp falls within the correct
        // calendar month for non-ISO calendars.
        $dt = new \DateTime(
            sprintf('%04d-%02d-%02d 00:00:00', $this->isoYear, $this->isoMonth, $this->referenceISODay),
            new \DateTimeZone('UTC'),
        );
        return $dt->getTimestamp();
    }
}
