<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

/**
 * A calendar year-month without a specific day, time, or time zone.
 *
 * Only the ISO 8601 calendar is supported.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-plainyearmonth-objects
 */
final class PlainYearMonth implements Stringable
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
     * Always undefined (null) for the ISO 8601 calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?string $era {
        get => null;
    }

    /**
     * Always undefined (null) for the ISO 8601 calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $eraYear {
        get => null;
    }

    /**
     * Month code in "M01"–"M12" format.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode {
        get => sprintf('M%02d', $this->month);
    }

    /**
     * Number of days in this year-month's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInMonth {
        get => self::calcDaysInMonth($this->year, $this->month);
    }

    /**
     * 365 or 366, depending on whether this year-month's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInYear {
        get => self::isLeapYear($this->year) ? 366 : 365;
    }

    /**
     * Always 12 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $monthsInYear {
        get => 12;
    }

    /**
     * True if this year-month's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public bool $inLeapYear {
        get => self::isLeapYear($this->year);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /** @psalm-api */
    public readonly int $year;
    /**
     * @psalm-api
     * @var int<1, 12>
     */
    public readonly int $month;

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
     * @param mixed     $calendar        Calendar ID string; only "iso8601" supported.
     * @param int|float $referenceISODay Reference ISO day, defaults to 1; must be a valid day for the month.
     *
     * @throws \TypeError             if calendar is not null/string.
     * @throws InvalidArgumentException if year/month/referenceISODay are out of range, infinite, or the calendar is unsupported.
     */
    public function __construct(
        int|float $year,
        int|float $month,
        mixed $calendar = null,
        int|float $referenceISODay = 1,
    ) {
        if ($calendar !== null) {
            if (!is_string($calendar)) {
                throw new \TypeError(sprintf(
                    'PlainYearMonth calendar must be a string; got %s.',
                    get_debug_type($calendar),
                ));
            }
            // Only bare calendar IDs (not ISO date strings) accepted in constructor.
            if (strtolower($calendar) !== 'iso8601') {
                throw new InvalidArgumentException("Unsupported calendar \"{$calendar}\": only iso8601 is supported.");
            }
        }
        if (!is_finite((float) $year) || !is_finite((float) $month) || !is_finite((float) $referenceISODay)) {
            throw new InvalidArgumentException(
                'Invalid PlainYearMonth: year, month, and referenceISODay must be finite numbers.',
            );
        }

        $this->year = (int) $year;
        $monthInt = (int) $month;
        if ($monthInt < 1 || $monthInt > 12) {
            throw new InvalidArgumentException("Invalid PlainYearMonth: month {$monthInt} is out of range 1–12.");
        }
        $this->month = $monthInt;
        $refDay = (int) $referenceISODay;

        // Validate referenceISODay is within the valid range for this year-month.
        $daysInMonth = self::calcDaysInMonth($this->year, $this->month);
        if ($refDay < 1 || $refDay > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainYearMonth: referenceISODay {$refDay} is out of range 1–{$daysInMonth}.",
            );
        }
        $this->referenceISODay = $refDay;

        // TC39 range check: §9.5.9 ISOYearMonthWithinLimits
        // Range: April −271821 … September +275760 (month-granular, not day-granular).
        if (!self::isoYearMonthWithinLimits($this->year, $this->month)) {
            throw new InvalidArgumentException(
                "Invalid PlainYearMonth: {$this->year}-{$this->month} is outside the representable range.",
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
            return new self($item->year, $item->month, 'iso8601', $item->referenceISODay);
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

        if ($a->year !== $b->year) {
            return $a->year <=> $b->year;
        }
        if ($a->month !== $b->month) {
            return $a->month <=> $b->month;
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
        ) {
            throw new \TypeError('PlainYearMonth::with() requires at least one of: year, month, monthCode.');
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

        // Start from current fields and override with provided ones.
        $year = $this->year;
        if (array_key_exists('year', $fields)) {
            /** @var mixed $yr */
            $yr = $fields['year'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $yr)) {
                throw new InvalidArgumentException('PlainYearMonth::with() year must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $year = (int) $yr;
        }

        $month = $this->month;
        $hasMonth = array_key_exists('month', $fields);
        $hasMonthCode = array_key_exists('monthCode', $fields);

        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $fields['monthCode'];
            /** @phpstan-ignore cast.string */
            $mcStr = (string) $mc;
            if (preg_match('/^M(0[1-9]|1[0-2])$/', $mcStr) !== 1) {
                throw new InvalidArgumentException("Invalid monthCode for ISO calendar: \"{$mcStr}\".");
            }
            $month = (int) substr(string: $mcStr, offset: 1);
        }
        if ($hasMonth) {
            /** @var mixed $m */
            $m = $fields['month'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $m)) {
                throw new InvalidArgumentException('PlainYearMonth::with() month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = (int) $m;
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
        return new self($year, $month, 'iso8601', 1);
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
        return self::diffYearMonth($this, $o, $this, $options);
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
        return self::diffYearMonth($o, $this, $this, $options);
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
        return $this->year === $o->year && $this->month === $o->month && $this->referenceISODay === $o->referenceISODay;
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
    public function toString(array|object|null $options = null): string
    {
        $yearStr = self::formatYear($this->year);
        $base = sprintf('%s-%02d', $yearStr, $this->month);

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
            'always' => sprintf('%s-%02d[u-ca=iso8601]', $base, $this->referenceISODay),
            'critical' => sprintf('%s-%02d[!u-ca=iso8601]', $base, $this->referenceISODay),
            default => throw new InvalidArgumentException("Invalid calendarName value: \"{$calendarName}\"."),
        };
    }

    /** @psalm-api */
    public function toJSON(): string
    {
        return $this->toString();
    }

    /**
     * @param string|array<array-key, mixed>|null $locales
     * @param array<array-key, mixed>|object|null $options
     * @psalm-api
     * @psalm-suppress UnusedParam
     */
    public function toLocaleString(string|array|null $locales = null, array|object|null $options = null): string
    {
        return $this->toString();
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

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
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
            /** @var array<array-key, mixed> $bag */
            $bag = get_object_vars($fields);
        }

        if (!array_key_exists('day', $bag)) {
            throw new \TypeError('PlainYearMonth::toPlainDate() argument must have a day property.');
        }

        /** @var mixed $dayRaw */
        $dayRaw = $bag['day'];
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $dayRaw)) {
            throw new InvalidArgumentException('toPlainDate() day must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $day = (int) $dayRaw;

        // Constrain day to valid range for this year-month.
        $maxDay = self::calcDaysInMonth($this->year, $this->month);
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }
        if ($day > $maxDay) {
            $day = $maxDay; // constrain (default overflow behaviour per spec)
        }

        return new PlainDate($this->year, $this->month, $day);
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

        // Validate bracket annotations.
        $annotationSection = $m[7];
        self::validateAnnotations($annotationSection, $s);

        // TC39 spec §9.2.4 ParseTemporalYearMonthString + §9.5.2 ToTemporalYearMonth:
        // For ISO 8601, the referenceISODay is always constrained to 1 regardless of
        // what day appeared in the input string. The day field in the string is used only
        // to validate the date (month/day combination must be valid), but the resulting
        // PlainYearMonth always has referenceISODay = 1.
        // See: https://tc39.es/proposal-temporal/#sec-temporal-totemporalyearmonth step 5.d

        // Validate month range before day validation.
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                sprintf('PlainYearMonth::from() cannot parse "%s": month %d out of range.', $s, $month),
            );
        }

        // Validate that $refDay is a valid day for this month (day from string).
        $daysInMonth = self::calcDaysInMonth($year, $month);
        if ($refDay < 1 || $refDay > $daysInMonth) {
            throw new InvalidArgumentException(
                "PlainYearMonth::from() cannot parse \"{$s}\": day {$refDay} is out of range for month {$month}.",
            );
        }

        // Always use referenceISODay = 1 for ISO calendar from() (per spec §9.5.2 step 5.d).
        return new self($year, $month, 'iso8601', 1);
    }

    /**
     * Validates bracket annotations in a PlainYearMonth string.
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
            $calId = self::extractCalendarId($cal);
            if ($calId !== 'iso8601') {
                throw new InvalidArgumentException("Unsupported calendar \"{$cal}\": only iso8601 is supported.");
            }
        }

        if (!array_key_exists('year', $bag)) {
            throw new \TypeError('PlainYearMonth property bag must have a year field.');
        }
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('PlainYearMonth property bag must have a month or monthCode field.');
        }

        /** @var mixed $yearRaw */
        $yearRaw = $bag['year'];
        if ($yearRaw === null) {
            throw new \TypeError('PlainYearMonth property bag year field must not be undefined.');
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $yearRaw)) {
            throw new InvalidArgumentException('PlainYearMonth year must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $year = is_int($yearRaw) ? $yearRaw : (int) $yearRaw;

        // Resolve month from monthCode or month field.
        $month = null;
        $hasMonth = array_key_exists('month', $bag);
        $hasMonthCode = array_key_exists('monthCode', $bag);

        if ($hasMonthCode) {
            /** @var mixed $monthCodeRaw */
            $monthCodeRaw = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $mc = is_string($monthCodeRaw) ? $monthCodeRaw : (string) $monthCodeRaw;
            if (preg_match('/^M(0[1-9]|1[0-2])$/', $mc) !== 1) {
                throw new InvalidArgumentException("Invalid monthCode for ISO calendar: \"{$mc}\".");
            }
            $month = (int) substr(string: $mc, offset: 1);
        }

        if ($hasMonth) {
            /** @var mixed $monthRaw */
            $monthRaw = $bag['month'];
            if ($monthRaw === null) {
                throw new \TypeError('PlainYearMonth property bag month field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $monthRaw)) {
                throw new InvalidArgumentException('PlainYearMonth month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = is_int($monthRaw) ? $monthRaw : (int) $monthRaw;
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

        if ($overflow === 'constrain') {
            $month = min(12, $month);
        }

        return new self($year, $month, 'iso8601', 1);
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
        $epochDays = self::toJulianDay($ym->year, $ym->month, 1) - 2_440_588;
        if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
            throw new InvalidArgumentException(
                "PlainYearMonth {$ym->year}-{$ym->month}: first day is outside the representable PlainDate range.",
            );
        }
    }

    /**
     * Core implementation for since() and until().
     *
     * $later and $earlier define the raw difference; $receiver is $this.
     *
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     */
    private static function diffYearMonth(self $later, self $earlier, self $receiver, array|object|null $options): Duration
    {
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
        /** @var list<string> $validModes */
        static $validModes = [
            'ceil',
            'floor',
            'expand',
            'trunc',
            'halfCeil',
            'halfFloor',
            'halfExpand',
            'halfTrunc',
            'halfEven',
        ];

        $largestUnit = 'year'; // default for PlainYearMonth per spec (auto = year)
        $largestUnitExplicit = false;
        $smallestUnit = null;
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
                    if (in_array($lu, $disallowedUnits, strict: true)) {
                        throw new InvalidArgumentException("Invalid largestUnit value: \"{$lu}\".");
                    }
                    /** @psalm-suppress RedundantCondition */
                    if (!in_array($lu, $validUnits, strict: true)) {
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
                    if (!is_int($ri) && !is_float($ri) && !is_string($ri) && !is_bool($ri)) {
                        throw new \TypeError('roundingIncrement must be numeric.');
                    }
                    $riFloat = (float) $ri;
                    if (is_nan($riFloat) || !is_finite($riFloat)) {
                        throw new InvalidArgumentException('roundingIncrement must be a finite number.');
                    }
                    $riInt = (int) $riFloat;
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
                    if (in_array($su, $disallowedUnits, strict: true)) {
                        throw new InvalidArgumentException("Invalid smallestUnit value: \"{$su}\".");
                    }
                    /** @psalm-suppress RedundantCondition */
                    if (!in_array($su, $validUnits, strict: true)) {
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

        // Short-circuit when both year-months are equal: diff is always zero.
        // This avoids the PlainDate range check for boundary year-months that would otherwise fail.
        if ($later->year === $earlier->year && $later->month === $earlier->month) {
            return new Duration();
        }

        // Validate that day=1 of each year-month is a valid PlainDate (TC39 §DifferenceTemporalPlainYearMonth step 8).
        self::validateYearMonthForDiff($later);
        self::validateYearMonthForDiff($earlier);

        // Compute raw year+month diff.
        $sign =
            $later->year > $earlier->year || $later->year === $earlier->year && $later->month >= $earlier->month
                ? 1
                : -1;

        $y1 = $earlier->year;
        $m1 = $earlier->month;
        $y2 = $later->year;
        $m2 = $later->month;

        if ($sign < 0) {
            [$y1, $m1, $y2, $m2] = [$y2, $m2, $y1, $m1];
        }

        $years = $y2 - $y1;
        $months = $m2 - $m1;
        if ($months < 0) {
            $years--;
            $months += 12;
        }

        $totalMonths = $sign * (($years * 12) + $months);
        $rawYears = $sign * $years;
        $rawMonths = $sign * $months;

        // The receiver is "later" when since() is called (receiver is $this = later arg).
        $receiverIsLater = $receiver->year === $later->year && $receiver->month === $later->month;

        if ($normLargest === 'month') {
            // All months; no years in output.
            if ($normSmallest === 'month') {
                if ($roundingIncrement === 1 && $roundingMode === 'trunc') {
                    return new Duration(months: $totalMonths);
                }
                // Round totalMonths by increment.
                $rounded = self::roundCalendarYearMonths(
                    $totalMonths,
                    $receiver,
                    $roundingIncrement,
                    $roundingMode,
                    $receiverIsLater,
                );
                return new Duration(months: $rounded);
            }
            // smallestUnit=year but largestUnit=month: impossible (year > month rank), should have been caught above.
            return new Duration(months: $totalMonths);
        }

        // normLargest === 'year'
        if ($normSmallest === 'year') {
            // Round years; discard months.
            if ($roundingIncrement === 1 && $roundingMode === 'trunc') {
                return new Duration(years: $rawYears);
            }
            $roundedYears = self::roundCalendarYearsYM(
                $rawYears,
                $rawMonths,
                $receiver,
                $roundingIncrement,
                $roundingMode,
                $receiverIsLater,
            );
            return new Duration(years: $roundedYears);
        }

        // normSmallest === 'month', normLargest === 'year': round the months component (not totalMonths).
        // The years component is kept; only the months sub-component is rounded.
        // TC39 NudgeToCalendarUnit: anchor = receiver moved by rawYears years, round rawMonths within that year.
        if ($roundingIncrement === 1 && $roundingMode === 'trunc') {
            return new Duration(years: $rawYears, months: $rawMonths);
        }
        [$ry, $rm] = self::roundCalendarMonthsWithinYear(
            $rawYears,
            $rawMonths,
            $receiver,
            $roundingIncrement,
            $roundingMode,
            $receiverIsLater,
        );
        return new Duration(years: $ry, months: $rm);
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
            [$ry, $rm] = self::addSignedMonthsYM($receiver->year, $receiver->month, $dir2 * $absMonths);
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
        [$anchorY, $anchorM] = self::addSignedMonthsYM($receiver->year, $receiver->month, $dir * $floorCount);
        [$nextY, $nextM] = self::addSignedMonthsYM(
            $receiver->year,
            $receiver->month,
            $dir * ($floorCount + $increment),
        );

        // Validate the next boundary is within the representable range (§NudgeToCalendarUnit step 8).
        if (!self::isoYearMonthWithinLimits($nextY, $nextM)) {
            throw new InvalidArgumentException('PlainYearMonth rounding result is outside the representable range.');
        }

        // Interval size in days (anchor → next boundary).
        $anchorJdn = self::toJulianDay($anchorY, $anchorM, 1);
        $nextJdn = self::toJulianDay($nextY, $nextM, 1);
        $intervalDays = abs($nextJdn - $anchorJdn);

        // Compute how far the remaining months reach within the interval (in days).
        [$remY, $remM] = self::addSignedMonthsYM($anchorY, $anchorM, $dir * $remainingMonths);
        $remJdn = self::toJulianDay($remY, $remM, 1);
        $remDays = abs($remJdn - $anchorJdn);

        $progress = $intervalDays > 0 ? $remDays / $intervalDays : 0.0;

        $roundUp = self::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbs = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate range.
        [$ry, $rm] = self::addSignedMonthsYM($receiver->year, $receiver->month, $dir * $roundedAbs);
        if (!self::isoYearMonthWithinLimits($ry, $rm)) {
            throw new InvalidArgumentException('PlainYearMonth arithmetic result is outside the representable range.');
        }

        return $sign * $roundedAbs;
    }

    /**
     * Calendar-aware rounding for years in a PlainYearMonth diff.
     *
     * @throws InvalidArgumentException if the rounded result is outside the valid range.
     */
    private static function roundCalendarYearsYM(
        int $years,
        int $months,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
    ): int {
        $sign = $years !== 0 ? ($years >= 0 ? 1 : -1) : ($months >= 0 ? 1 : -1);
        $absYears = abs($years);

        $floorCount = intdiv(num1: $absYears, num2: $increment) * $increment;

        $dir = $receiverIsLater ? -$sign : $sign;

        // Anchor at floorCount years from receiver.
        [$anchorY, $anchorM] = self::addSignedMonthsYM($receiver->year, $receiver->month, $dir * $floorCount * 12);
        [$nextY, $nextM] = self::addSignedMonthsYM(
            $receiver->year,
            $receiver->month,
            $dir * ($floorCount + $increment) * 12,
        );

        // Validate the next boundary is within the representable range.
        if (!self::isoYearMonthWithinLimits($nextY, $nextM)) {
            throw new InvalidArgumentException('PlainYearMonth rounding result is outside the representable range.');
        }

        $anchorJdn = self::toJulianDay($anchorY, $anchorM, 1);
        $nextJdn = self::toJulianDay($nextY, $nextM, 1);
        $intervalDays = abs($nextJdn - $anchorJdn);

        // Target: anchor + (total remaining months from anchor to target).
        // The target is at floorCount*12 + remaining_months from receiver,
        // i.e., the full abs diff (absYears*12 + absMonths) months from receiver.
        // From anchor (= receiver + dir*floorCount*12), the target is at dir*(absYears-floorCount)*12 + dir*absMonths.
        $absMonths = abs($months);
        $remMonthsFromAnchor = (($absYears - $floorCount) * 12) + $absMonths;
        [$subY, $subM] = self::addSignedMonthsYM($anchorY, $anchorM, $dir * $remMonthsFromAnchor);
        $subJdn = self::toJulianDay($subY, $subM, 1);
        $remDays = abs($subJdn - $anchorJdn);

        $progress = $intervalDays > 0 ? $remDays / $intervalDays : 0.0;
        $roundUp = self::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbs = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate range.
        [$ry, $rm] = self::addSignedMonthsYM($receiver->year, $receiver->month, $dir * $roundedAbs * 12);
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
        $sign = $rawYears !== 0 ? ($rawYears >= 0 ? 1 : -1) : ($rawMonths >= 0 ? 1 : -1);

        // Direction from receiver toward the other.
        $dir = $receiverIsLater ? -$sign : $sign;

        // Yearly anchor: receiver moved by |rawYears| years (the whole-year portion of the diff).
        [$yearAnchorY, $yearAnchorM] = self::addSignedMonthsYM(
            $receiver->year,
            $receiver->month,
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
        $monthAnchorJdn = self::toJulianDay($monthAnchorY, $monthAnchorM, 1);
        $nextJdn = self::toJulianDay($nextY, $nextM, 1);
        $intervalDays = abs($nextJdn - $monthAnchorJdn);

        $remainingMonths = $absMonths - $floorCount;
        [$remY, $remM] = self::addSignedMonthsYM($monthAnchorY, $monthAnchorM, $dir * $remainingMonths);
        $remJdn = self::toJulianDay($remY, $remM, 1);
        $remDays = abs($remJdn - $monthAnchorJdn);

        $progress = $intervalDays > 0 ? $remDays / $intervalDays : 0.0;
        $roundUp = self::applyRoundingProgress($progress, $mode, $sign);

        $roundedAbsMonths = $roundUp ? $nextCount : $floorCount;

        // Convert rounded abs months to a years+months result with carry.
        $carryYears = intdiv(num1: $roundedAbsMonths, num2: 12);
        $remainMonths = $roundedAbsMonths % 12;
        $roundedYears = $rawYears + ($sign * $carryYears);
        $roundedMonths = $sign * $remainMonths;

        // Validate range.
        $totalAbsMonths = (abs($rawYears) * 12) + $roundedAbsMonths;
        [$ry, $rm] = self::addSignedMonthsYM($receiver->year, $receiver->month, $dir * $totalAbsMonths);
        if (!self::isoYearMonthWithinLimits($ry, $rm)) {
            throw new InvalidArgumentException('PlainYearMonth arithmetic result is outside the representable range.');
        }

        return [$roundedYears, $roundedMonths];
    }

    /**
     * Determines whether to round up based on progress and rounding mode.
     */
    private static function applyRoundingProgress(float $progress, string $mode, int $sign): bool
    {
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
        return match ($effectiveMode) {
            'trunc', 'floor' => false,
            'ceil', 'expand' => $progress > 0.0,
            'halfExpand', 'halfCeil' => $progress >= 0.5,
            'halfTrunc', 'halfFloor' => $progress > 0.5,
            'halfEven' => $progress > 0.5,
            default => false,
        };
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

        $newYear = $this->year + $years;
        $newMonth = $this->month + $months;

        // Normalize month into 1–12, carrying into year.
        if ($newMonth > 12) {
            $newYear += intdiv(num1: $newMonth - 1, num2: 12);
            $newMonth = (($newMonth - 1) % 12) + 1;
        } elseif ($newMonth < 1) {
            $newYear += intdiv(num1: $newMonth - 12, num2: 12);
            $newMonth = (((($newMonth - 1) % 12) + 12) % 12) + 1;
        }

        // Range check using month-granular limit.
        if (!self::isoYearMonthWithinLimits($newYear, $newMonth)) {
            throw new InvalidArgumentException('PlainYearMonth arithmetic result is outside the representable range.');
        }

        return new self($newYear, $newMonth, 'iso8601', 1);
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
     * Converts a proleptic Gregorian calendar date to a Julian Day Number.
     * Algorithm: Richards (2013).
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
     * @param int<1, 12> $month
     * @return int<28, 31>
     */
    private static function calcDaysInMonth(int $year, int $month): int
    {
        return match ($month) {
            1, 3, 5, 7, 8, 10, 12 => 31,
            4, 6, 9, 11 => 30,
            2 => self::isLeapYear($year) ? 29 : 28,
        };
    }

    private static function isLeapYear(int $year): bool
    {
        return ($year % 4) === 0 && ($year % 100) !== 0 || ($year % 400) === 0;
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

    /**
     * Floor division: rounds towards negative infinity.
     */
    private static function floorDiv(int $a, int $b): int
    {
        return (int) floor($a / $b);
    }
}
