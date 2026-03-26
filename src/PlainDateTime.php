<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

/**
 * A calendar date combined with a wall-clock time, without a time zone.
 *
 * Only the ISO 8601 calendar is supported. The date range is identical to
 * PlainDate (Apr 19 −271821 … Sep 13 +275760); the time range is
 * 00:00:00.000000000 – 23:59:59.999999999.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-plaindatetime-objects
 */
final class PlainDateTime implements Stringable
{
    private const int NS_PER_HOUR = 3_600_000_000_000;
    private const int NS_PER_MINUTE = 60_000_000_000;
    private const int NS_PER_SECOND = 1_000_000_000;
    private const int NS_PER_MS = 1_000_000;
    private const int NS_PER_US = 1_000;
    private const int NS_PER_DAY = 86_400_000_000_000;

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
     * ISO 8601 day of week: 1 = Monday, 7 = Sunday.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $dayOfWeek {
        get => self::isoWeekday($this->year, $this->month, $this->day);
    }

    /**
     * Ordinal day of the year: 1–366.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $dayOfYear {
        get => self::calcDayOfYear($this->year, $this->month, $this->day);
    }

    /**
     * ISO 8601 week number: 1–53.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $weekOfYear {
        get => self::isoWeekInfo($this->year, $this->month, $this->day)['week'];
    }

    /**
     * ISO 8601 week-year (may differ from calendar year near year boundaries).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $yearOfWeek {
        get => self::isoWeekInfo($this->year, $this->month, $this->day)['year'];
    }

    /**
     * Number of days in this date's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInMonth {
        get => self::calcDaysInMonth($this->year, $this->month);
    }

    /**
     * Always 7 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
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
     * True if this date's year is a leap year.
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
     * @psalm-api
     * @var int<1, 31>
     */
    public readonly int $day;
    /**
     * @psalm-api
     * @var int<0, 23>
     */
    public readonly int $hour;
    /**
     * @psalm-api
     * @var int<0, 59>
     */
    public readonly int $minute;
    /**
     * @psalm-api
     * @var int<0, 59>
     */
    public readonly int $second;
    /**
     * @psalm-api
     * @var int<0, 999>
     */
    public readonly int $millisecond;
    /**
     * @psalm-api
     * @var int<0, 999>
     */
    public readonly int $microsecond;
    /**
     * @psalm-api
     * @var int<0, 999>
     */
    public readonly int $nanosecond;

    /**
     * @param int|float $year
     * @param int|float $month        1–12
     * @param int|float $day          1–daysInMonth
     * @param int|float $hour         0–23
     * @param int|float $minute       0–59
     * @param int|float $second       0–59
     * @param int|float $millisecond  0–999
     * @param int|float $microsecond  0–999
     * @param int|float $nanosecond   0–999
     * @throws InvalidArgumentException if any value is infinite, non-integer, or out of range.
     */
    public function __construct(
        int|float $year,
        int|float $month,
        int|float $day,
        int|float $hour = 0,
        int|float $minute = 0,
        int|float $second = 0,
        int|float $millisecond = 0,
        int|float $microsecond = 0,
        int|float $nanosecond = 0,
        mixed $calendar = null,
    ) {
        if ($calendar !== null) {
            if (!is_string($calendar)) {
                throw new \TypeError(sprintf(
                    'PlainDateTime calendar must be a string; got %s.',
                    get_debug_type($calendar),
                ));
            }
            // The constructor only accepts bare calendar IDs, not ISO date strings.
            // Use ASCII-only lowercase to reject non-ASCII chars like U+0130 (İ).
            if (strtolower($calendar) !== 'iso8601') {
                throw new InvalidArgumentException("Unsupported calendar \"{$calendar}\": only iso8601 is supported.");
            }
        }
        if (
            !is_finite((float) $year)
            || !is_finite((float) $month)
            || !is_finite((float) $day)
            || !is_finite((float) $hour)
            || !is_finite((float) $minute)
            || !is_finite((float) $second)
            || !is_finite((float) $millisecond)
            || !is_finite((float) $microsecond)
            || !is_finite((float) $nanosecond)
        ) {
            throw new InvalidArgumentException('Invalid PlainDateTime: all fields must be finite numbers.');
        }
        $this->year = (int) $year;
        $monthInt = (int) $month;
        if ($monthInt < 1 || $monthInt > 12) {
            throw new InvalidArgumentException("Invalid PlainDateTime: month {$monthInt} is out of range 1–12.");
        }
        $this->month = $monthInt;
        $dayInt = (int) $day;
        if ($dayInt < 1) {
            throw new InvalidArgumentException("Invalid PlainDateTime: day {$dayInt} must be at least 1.");
        }
        $daysInMonth = self::calcDaysInMonth($this->year, $this->month);
        if ($dayInt > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainDateTime: day {$dayInt} exceeds {$daysInMonth} for {$this->year}-{$this->month}.",
            );
        }
        /** @psalm-suppress InvalidPropertyAssignmentValue — $dayInt <= $daysInMonth <= 31 */
        $this->day = $dayInt;
        $hInt = (int) $hour;
        $minInt = (int) $minute;
        $secInt = (int) $second;
        $msInt = (int) $millisecond;
        $usInt = (int) $microsecond;
        $nsInt = (int) $nanosecond;
        self::validateTimeFields($hInt, $minInt, $secInt, $msInt, $usInt, $nsInt);
        // TC39 range: strictly after -271821-04-19T00:00:00 … up to +275760-09-13T23:59:59.999999999.
        // epochDays = days from Unix epoch (1970-01-01 = 0).
        // -271821-04-19 = epochDay -100_000_001; +275760-09-13 = epochDay 100_000_000.
        $epochDays = self::toJulianDay($this->year, $this->month, $this->day) - 2_440_588;
        if ($epochDays < -100_000_001 || $epochDays > 100_000_000) {
            throw new InvalidArgumentException(
                "Invalid PlainDateTime: {$this->year}-{$this->month}-{$this->day} is outside the representable range.",
            );
        }
        // Midnight (-271821-04-19 00:00:00.000000000) is itself outside the range.
        // The first valid instant is one nanosecond past midnight on that date.
        if (
            $epochDays === -100_000_001
            && $hInt === 0
            && $minInt === 0
            && $secInt === 0
            && $msInt === 0
            && $usInt === 0
            && $nsInt === 0
        ) {
            throw new InvalidArgumentException(
                'Invalid PlainDateTime: -271821-04-19T00:00:00 is outside the representable range (use T00:00:00.000000001 or later).',
            );
        }

        $this->hour = $hInt;
        $this->minute = $minInt;
        $this->second = $secInt;
        $this->millisecond = $msInt;
        $this->microsecond = $usInt;
        $this->nanosecond = $nsInt;
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Creates a PlainDateTime from another PlainDateTime, an ISO 8601 datetime string,
     * or a property-bag array.
     *
     * @param self|string|array<array-key, mixed>|object $item    PlainDateTime, ISO 8601 datetime string, or property-bag array.
     * @param array<array-key, mixed>|object|null $options Options bag or null; supports 'overflow' key.
     * @throws InvalidArgumentException if the string is invalid or any field is out of range.
     * @throws \TypeError if the type cannot be interpreted as a PlainDateTime.
     * @psalm-api
     */
    public static function from(string|array|object $item, array|object|null $options = null): self
    {
        // Validate overflow first so invalid overflow values always throw InvalidArgumentException,
        // regardless of the item type (even if item would otherwise cause TypeError).
        $overflow = self::extractOverflow($options);

        if ($item instanceof self) {
            return new self(
                $item->year,
                $item->month,
                $item->day,
                $item->hour,
                $item->minute,
                $item->second,
                $item->millisecond,
                $item->microsecond,
                $item->nanosecond,
            );
        }
        if (is_string($item)) {
            return self::fromString($item);
        }
        if (is_array($item)) {
            return self::fromPropertyBag($item, $overflow);
        }
        throw new \TypeError(sprintf(
            'PlainDateTime::from() expects a PlainDateTime, ISO 8601 datetime string, or property-bag array; got %s.',
            get_debug_type($item),
        ));
    }

    /**
     * Compares two PlainDateTimes chronologically.
     *
     * Returns -1, 0, or +1 (or a value with the same sign).
     *
     * @param self|string|array<array-key, mixed>|object $one PlainDateTime or ISO 8601 datetime string.
     * @param self|string|array<array-key, mixed>|object $two PlainDateTime or ISO 8601 datetime string.
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
        if ($a->day !== $b->day) {
            return $a->day <=> $b->day;
        }
        // Compare time fields: convert each to nanoseconds since midnight.
        $aNs = self::timeToNs($a->hour, $a->minute, $a->second, $a->millisecond, $a->microsecond, $a->nanosecond);
        $bNs = self::timeToNs($b->hour, $b->minute, $b->second, $b->millisecond, $b->microsecond, $b->nanosecond);
        return $aNs <=> $bNs;
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainDateTime with the specified fields overridden.
     *
     * Recognized date fields: year, month, monthCode, day.
     * Recognized time fields: hour, minute, second, millisecond, microsecond, nanosecond.
     * The 'calendar' and 'timeZone' keys must not be present.
     *
     * @param array<array-key,mixed> $fields   Property bag with fields to override.
     * @param array<array-key, mixed>|object|null       $options Options bag: ['overflow' => 'constrain'|'reject']
     * @throws \TypeError             if $fields contains 'calendar' or 'timeZone'.
     * @throws InvalidArgumentException if the resulting datetime is invalid (overflow: reject).
     * @psalm-api
     */
    public function with(array $fields, array|object|null $options = null): self
    {
        if (array_key_exists('calendar', $fields) || array_key_exists('timeZone', $fields)) {
            throw new \TypeError('PlainDateTime::with() fields must not contain a calendar or timeZone property.');
        }

        // At least one recognized property must be present.
        /** @var list<string> $recognized */
        static $recognized = [
            'year',
            'month',
            'monthCode',
            'day',
            'hour',
            'minute',
            'second',
            'millisecond',
            'microsecond',
            'nanosecond',
        ];
        $hasRecognized = false;
        foreach ($recognized as $key) {
            if (array_key_exists($key, $fields)) {
                $hasRecognized = true;
                break;
            }
        }
        if (!$hasRecognized) {
            throw new \TypeError('PlainDateTime::with() requires at least one recognized temporal property.');
        }

        $overflow = self::extractOverflow($options);

        // Merge date fields.
        $year = $this->year;
        if (array_key_exists('year', $fields)) {
            /** @var mixed $yr */
            $yr = $fields['year'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $yr)) {
                throw new InvalidArgumentException('PlainDateTime::with() year must be finite.');
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
                throw new InvalidArgumentException('PlainDateTime::with() month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = (int) $m;
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        $day = $this->day;
        if (array_key_exists('day', $fields)) {
            /** @var mixed $dy */
            $dy = $fields['day'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $dy)) {
                throw new InvalidArgumentException('PlainDateTime::with() day must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $day = (int) $dy;
        }

        // Merge time fields.
        $h = $this->hour;
        $min = $this->minute;
        $sec = $this->second;
        $ms = $this->millisecond;
        $us = $this->microsecond;
        $ns = $this->nanosecond;

        if (array_key_exists('hour', $fields)) {
            /** @var mixed $v */
            $v = $fields['hour'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainDateTime::with() hour must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $h = (int) $v;
        }
        if (array_key_exists('minute', $fields)) {
            /** @var mixed $v */
            $v = $fields['minute'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainDateTime::with() minute must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $min = (int) $v;
        }
        if (array_key_exists('second', $fields)) {
            /** @var mixed $v */
            $v = $fields['second'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainDateTime::with() second must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $sec = (int) $v;
        }
        if (array_key_exists('millisecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['millisecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainDateTime::with() millisecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ms = (int) $v;
        }
        if (array_key_exists('microsecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['microsecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainDateTime::with() microsecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $us = (int) $v;
        }
        if (array_key_exists('nanosecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['nanosecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainDateTime::with() nanosecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ns = (int) $v;
        }

        // month < 1 and day < 1 are always invalid (cannot constrain below minimum).
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
            $maxDay = self::calcDaysInMonth($year, $month);
            $day = min($maxDay, $day);
            $h = max(0, min(23, $h));
            $min = max(0, min(59, $min));
            $sec = max(0, min(59, $sec));
            $ms = max(0, min(999, $ms));
            $us = max(0, min(999, $us));
            $ns = max(0, min(999, $ns));
        }

        return new self($year, $month, $day, $h, $min, $sec, $ms, $us, $ns);
    }

    /**
     * Returns a new PlainDateTime with the given duration added.
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
     * Returns a new PlainDateTime with the given duration subtracted.
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
     * Returns the Duration from $other to this datetime (this − other).
     *
     * Default largestUnit is 'day' (matches TC39 PlainDateTime spec).
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainDateTime or ISO 8601 datetime string.
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function since(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        return self::diffDateTime($this, $o, $this, $options);
    }

    /**
     * Returns the Duration from this datetime to $other (other − this).
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainDateTime or ISO 8601 datetime string.
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     * @psalm-api
     */
    public function until(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        return self::diffDateTime($o, $this, $this, $options);
    }

    /**
     * Returns a new PlainDateTime rounded to the given unit and increment.
     *
     * @param string|array<array-key, mixed>|object $options string smallestUnit or array with keys:
     *   - smallestUnit (required): 'day'|'hour'|'minute'|'second'|'millisecond'|'microsecond'|'nanosecond'
     *   - roundingMode (default 'halfExpand')
     *   - roundingIncrement (default 1)
     * @throws \TypeError if options are not a string, array, or object.
     * @throws InvalidArgumentException for invalid option values.
     * @psalm-api
     */
    public function round(string|array|object $options): self
    {
        if (is_string($options)) {
            $options = ['smallestUnit' => $options];
        } elseif (is_object($options)) {
            $options = (array) $options;
        }

        /** @psalm-suppress MixedAssignment */
        $suRaw = $options['smallestUnit'] ?? null;
        if ($suRaw === null) {
            throw new InvalidArgumentException('Temporal\\PlainDateTime::round() requires smallestUnit.');
        }
        if (!is_string($suRaw)) {
            throw new \TypeError('smallestUnit must be a string.');
        }

        // ns-per-unit and max increment (exclusive) for each unit.
        // For 'day', max = 1 (only increment 1 is valid).
        $unitMap = [
            'day' => [self::NS_PER_DAY, 2], // only increment=1 is valid for day
            'days' => [self::NS_PER_DAY, 2],
            'hour' => [self::NS_PER_HOUR, 24],
            'hours' => [self::NS_PER_HOUR, 24],
            'minute' => [self::NS_PER_MINUTE, 60],
            'minutes' => [self::NS_PER_MINUTE, 60],
            'second' => [self::NS_PER_SECOND, 60],
            'seconds' => [self::NS_PER_SECOND, 60],
            'millisecond' => [self::NS_PER_MS, 1_000],
            'milliseconds' => [self::NS_PER_MS, 1_000],
            'microsecond' => [self::NS_PER_US, 1_000],
            'microseconds' => [self::NS_PER_US, 1_000],
            'nanosecond' => [1, 1_000],
            'nanoseconds' => [1, 1_000],
        ];
        if (!array_key_exists($suRaw, $unitMap)) {
            throw new InvalidArgumentException(
                "Invalid smallestUnit \"{$suRaw}\" for Temporal\\PlainDateTime::round().",
            );
        }
        [$nsPerUnit, $maxIncrement] = $unitMap[$suRaw];

        $roundingMode = 'halfExpand';
        if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
            /** @psalm-suppress MixedArgument */
            $roundingMode = (string) $options['roundingMode'];
        }

        $increment = 1;
        if (array_key_exists('roundingIncrement', $options) && $options['roundingIncrement'] !== null) {
            /** @psalm-suppress MixedArgument */
            $rawIncrement = (int) $options['roundingIncrement'];
            if ($rawIncrement < 1) {
                throw new InvalidArgumentException('roundingIncrement must be a positive integer.');
            }
            $increment = $rawIncrement;
        }
        // Increment must be strictly less than maxIncrement (for sub-day) and must divide it.
        // For 'day', increment must be exactly 1 (maxIncrement = 1).
        if ($increment >= $maxIncrement || ($maxIncrement % $increment) !== 0) {
            throw new InvalidArgumentException("roundingIncrement {$increment} is invalid for unit \"{$suRaw}\".");
        }

        // Total ns since epoch midnight: use Julian Day Number to count days.
        $jdn = self::toJulianDay($this->year, $this->month, $this->day);
        $timeNs = self::timeToNs(
            $this->hour,
            $this->minute,
            $this->second,
            $this->millisecond,
            $this->microsecond,
            $this->nanosecond,
        );

        // For day rounding, increment wraps in units of a full day relative to the
        // day boundary (midnight), so we simply round the time-of-day ns.
        $nsIncrement = $nsPerUnit * $increment;

        // Round time-of-day ns (always non-negative) using the given mode.
        $roundedTimeNs = self::roundPositiveNs($timeNs, $nsIncrement, $roundingMode);

        // Determine how many days of overflow result from rounding (0 or 1).
        $overflowDays = intdiv(num1: $roundedTimeNs, num2: self::NS_PER_DAY);
        $newTimeNs = $roundedTimeNs % self::NS_PER_DAY;

        $newJdn = $jdn + $overflowDays;

        // Range check.
        $minJdn = self::toJulianDay(-271821, 4, 19);
        $maxJdn = self::toJulianDay(275760, 9, 13);
        if ($newJdn < $minJdn || $newJdn > $maxJdn) {
            throw new InvalidArgumentException('PlainDateTime rounding result is outside the representable range.');
        }

        [$newYear, $newMonth, $newDay] = self::fromJulianDay($newJdn);

        $h = intdiv(num1: $newTimeNs, num2: self::NS_PER_HOUR);
        $rem = $newTimeNs % self::NS_PER_HOUR;
        $min = intdiv(num1: $rem, num2: self::NS_PER_MINUTE);
        $rem = $rem % self::NS_PER_MINUTE;
        $sec = intdiv(num1: $rem, num2: self::NS_PER_SECOND);
        $rem = $rem % self::NS_PER_SECOND;
        $ms = intdiv(num1: $rem, num2: self::NS_PER_MS);
        $rem = $rem % self::NS_PER_MS;
        $us = intdiv(num1: $rem, num2: self::NS_PER_US);
        $ns = $rem % self::NS_PER_US;

        return new self($newYear, $newMonth, $newDay, $h, $min, $sec, $ms, $us, $ns);
    }

    /**
     * Returns true if this PlainDateTime represents the same date and time as $other.
     *
     * @param self|string|array<array-key, mixed>|object $other A PlainDateTime or ISO 8601 datetime string.
     * @psalm-api
     */
    public function equals(string|array|object $other): bool
    {
        $o = $other instanceof self ? $other : self::from($other);
        return (
            $this->year === $o->year
            && $this->month === $o->month
            && $this->day === $o->day
            && $this->hour === $o->hour
            && $this->minute === $o->minute
            && $this->second === $o->second
            && $this->millisecond === $o->millisecond
            && $this->microsecond === $o->microsecond
            && $this->nanosecond === $o->nanosecond
        );
    }

    /**
     * Returns an ISO 8601 datetime string: YYYY-MM-DDTHH:MM:SS[.fraction][calendar?]
     *
     * Options:
     *   - calendarName: 'auto' (default) | 'always' | 'never' | 'critical'
     *   - fractionalSecondDigits: 'auto' (default) | 0–9
     *
     * @param array<array-key, mixed>|object|null $options null or array of options.
     * @throws InvalidArgumentException for invalid option values.
     * @psalm-api
     */
    public function toString(array|object|null $options = null): string
    {
        if (is_object($options)) {
            $options = (array) $options;
        }

        $calendarName = 'auto';
        $digits = -2; // -2 = 'auto'
        $isMinute = false;
        $roundMode = 'trunc';

        if ($options !== null) {
            if (array_key_exists('calendarName', $options)) {
                /** @psalm-suppress MixedAssignment */
                $cn = $options['calendarName'];
                if (!is_string($cn)) {
                    throw new \TypeError('calendarName option must be a string.');
                }
                $calendarName = $cn;
            }

            // fractionalSecondDigits: -2 = 'auto', 0-9 = fixed.
            if (array_key_exists('fractionalSecondDigits', $options)) {
                /** @psalm-suppress MixedAssignment */
                $fsd = $options['fractionalSecondDigits'];
                if ($fsd !== 'auto') {
                    if ($fsd === null || is_bool($fsd)) {
                        throw new InvalidArgumentException("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if (is_float($fsd)) {
                        if (is_nan($fsd) || is_infinite($fsd)) {
                            throw new InvalidArgumentException(
                                "fractionalSecondDigits must be 'auto' or a finite integer 0–9.",
                            );
                        }
                        $fsd = (int) floor($fsd);
                    } elseif (!is_int($fsd)) {
                        throw new InvalidArgumentException("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if ($fsd < 0 || $fsd > 9) {
                        throw new InvalidArgumentException(
                            "fractionalSecondDigits {$fsd} is out of range (must be 0–9).",
                        );
                    }
                    $digits = $fsd;
                }
            }

            // smallestUnit overrides fractionalSecondDigits.
            if (array_key_exists('smallestUnit', $options) && $options['smallestUnit'] !== null) {
                /** @var mixed $su */
                $su = $options['smallestUnit'];
                if (!is_string($su)) {
                    throw new \TypeError('smallestUnit must be a string.');
                }
                [$digits, $isMinute] = match ($su) {
                    'minute', 'minutes' => [-1, true],
                    'second', 'seconds' => [0, false],
                    'millisecond', 'milliseconds' => [3, false],
                    'microsecond', 'microseconds' => [6, false],
                    'nanosecond', 'nanoseconds' => [9, false],
                    default => throw new InvalidArgumentException("Invalid smallestUnit \"{$su}\"."),
                };
            }

            // roundingMode (default 'trunc' for toString).
            if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
                /** @var mixed $rm */
                $rm = $options['roundingMode'];
                if (!is_string($rm)) {
                    throw new \TypeError('roundingMode must be a string.');
                }
                $roundMode = $rm;
            }
        }

        // Compute rounding increment in nanoseconds.
        if ($isMinute) {
            $increment = 60_000_000_000;
        } elseif ($digits >= 0) {
            $increment = (int) 10 ** (9 - $digits); // @phpstan-ignore cast.useless
        } else {
            $increment = 1;
        }

        // Round time-of-day nanoseconds.
        $timeNs = self::timeToNs(
            $this->hour,
            $this->minute,
            $this->second,
            $this->millisecond,
            $this->microsecond,
            $this->nanosecond,
        );

        $roundedTimeNs = $increment === 1 ? $timeNs : self::roundPositiveNs($timeNs, $increment, $roundMode);

        // Determine overflow days from rounding (0 or 1).
        $overflowDays = intdiv(num1: $roundedTimeNs, num2: self::NS_PER_DAY);
        $newTimeNs = $roundedTimeNs % self::NS_PER_DAY;

        // Apply overflow days to date via Julian Day Number.
        $jdn = self::toJulianDay($this->year, $this->month, $this->day) + $overflowDays;

        // Range check the rounded result.
        $minJdn = self::toJulianDay(-271821, 4, 19);
        $maxJdn = self::toJulianDay(275760, 9, 13);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException('PlainDateTime rounding result is outside the representable range.');
        }
        // Midnight at the min boundary is outside the range.
        if ($jdn === $minJdn && $newTimeNs === 0) {
            throw new InvalidArgumentException('PlainDateTime rounding result is outside the representable range.');
        }

        [$year, $month, $day] = self::fromJulianDay($jdn);

        $hour = intdiv(num1: $newTimeNs, num2: self::NS_PER_HOUR);
        $rem = $newTimeNs % self::NS_PER_HOUR;
        $min = intdiv(num1: $rem, num2: self::NS_PER_MINUTE);
        $rem = $rem % self::NS_PER_MINUTE;
        $sec = intdiv(num1: $rem, num2: self::NS_PER_SECOND);
        $rem = $rem % self::NS_PER_SECOND;

        $subNs = $rem;

        // Format date part.
        if ($year < 0) {
            $yearStr = sprintf('-%06d', abs($year));
        } elseif ($year > 9999) {
            $yearStr = sprintf('+%06d', $year);
        } else {
            $yearStr = sprintf('%04d', $year);
        }
        $dateStr = sprintf('%s-%02d-%02d', $yearStr, $month, $day);

        // Format time part.
        if ($isMinute) {
            $timeStr = sprintf('%02d:%02d', $hour, $min);
        } elseif ($digits === -2) {
            // 'auto': strip trailing zeros; omit fraction entirely if zero.
            $timeBase = sprintf('%02d:%02d:%02d', $hour, $min, $sec);
            if ($subNs === 0) {
                $timeStr = $timeBase;
            } else {
                $fraction = rtrim(sprintf('%09d', $subNs), characters: '0');
                $timeStr = "{$timeBase}.{$fraction}";
            }
        } elseif ($digits === 0) {
            $timeStr = sprintf('%02d:%02d:%02d', $hour, $min, $sec);
        } else {
            $fraction = substr(string: sprintf('%09d', $subNs), offset: 0, length: $digits);
            $timeStr = sprintf('%02d:%02d:%02d.%s', $hour, $min, $sec, $fraction);
        }

        $base = "{$dateStr}T{$timeStr}";

        return match ($calendarName) {
            'auto', 'never' => $base,
            'always' => sprintf('%s[u-ca=iso8601]', $base),
            'critical' => sprintf('%s[!u-ca=iso8601]', $base),
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
     * Returns the date part as a PlainDate.
     *
     * @psalm-api
     */
    public function toPlainDate(): PlainDate
    {
        return new PlainDate($this->year, $this->month, $this->day);
    }

    /**
     * Returns the time part as a PlainTime.
     *
     * @psalm-api
     */
    public function toPlainTime(): PlainTime
    {
        return new PlainTime(
            $this->hour,
            $this->minute,
            $this->second,
            $this->millisecond,
            $this->microsecond,
            $this->nanosecond,
        );
    }

    /**
     * Returns a new PlainDateTime with the time part replaced by $time.
     *
     * When called with no argument, the time defaults to midnight (00:00:00).
     *
     * @param PlainTime|string|array<array-key, mixed>|object|int $time
     * @psalm-api
     */
    public function withPlainTime(string|array|object|int $time = PHP_INT_MIN): self
    {
        // PHP_INT_MIN sentinel distinguishes no-argument from explicit null.
        if ($time === PHP_INT_MIN) {
            // No argument provided: default to midnight.
            return new self($this->year, $this->month, $this->day);
        }
        if (is_int($time)) {
            throw new \TypeError(sprintf(
                'PlainDateTime::withPlainTime() expects a PlainTime, ISO 8601 time string, or property-bag array; got int (%d).',
                $time,
            ));
        }
        $t = $time instanceof PlainTime ? $time : PlainTime::from($time);
        return new self(
            $this->year,
            $this->month,
            $this->day,
            $t->hour,
            $t->minute,
            $t->second,
            $t->millisecond,
            $t->microsecond,
            $t->nanosecond,
        );
    }

    /**
     * Returns a ZonedDateTime by interpreting this date-time in the given timezone.
     *
     * @param array<array-key, mixed>|object|null $options Options bag; supports 'disambiguation' key.
     * @throws InvalidArgumentException if the timezone or disambiguation option is invalid,
     *                                  or the resulting instant is out of range.
     * @psalm-api
     */
    public function toZonedDateTime(string $timeZone, array|object|null $options = null): ZonedDateTime
    {
        // Validate options bag type.
        if ($options !== null) {
            if (is_object($options)) {
                $options = (array) $options;
            }
            // Validate disambiguation option if present.
            if (array_key_exists('disambiguation', $options)) {
                /** @var mixed $disamb */
                $disamb = $options['disambiguation'];
                if (
                    !is_string($disamb)
                    || !in_array($disamb, ['compatible', 'earlier', 'later', 'reject'], strict: true)
                ) {
                    throw new InvalidArgumentException(
                        'PlainDateTime::toZonedDateTime() disambiguation must be one of: compatible, earlier, later, reject.',
                    );
                }
            }
        }

        $normalTzId = ZonedDateTime::normalizeTimezoneId($timeZone);

        // Compute wall-clock seconds from epoch days + time-of-day (avoids DateTimeImmutable
        // year-formatting issues with extended years > 9999 or negative years).
        $epochDays = self::toJulianDay($this->year, $this->month, $this->day) - 2_440_588;
        $wallSec = ($epochDays * 86_400) + ($this->hour * 3600) + ($this->minute * 60) + $this->second;
        $epochSec = ZonedDateTime::wallSecToEpochSec($wallSec, $normalTzId);

        $subNs = ($this->millisecond * self::NS_PER_MS) + ($this->microsecond * self::NS_PER_US) + $this->nanosecond;

        // Instant range: |epochNs| ≤ 8_640_000_000_000_000_000_000 (i.e. ±8_640_000_000_000 seconds + 0 sub-ns).
        $absEpochSec = abs($epochSec);
        if ($absEpochSec > 8_640_000_000_000 || $absEpochSec === 8_640_000_000_000 && $subNs > 0) {
            throw new InvalidArgumentException(
                'PlainDateTime::toZonedDateTime() result is outside the representable Instant range.',
            );
        }
        $maxSecForNs = 9_223_372_035;
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            $epochNs = $epochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX;
        } else {
            $epochNs = ($epochSec * self::NS_PER_SECOND) + $subNs;
        }

        return new ZonedDateTime($epochNs, $normalTzId, 'iso8601');
    }

    /**
     * Returns a new PlainDateTime with the specified calendar.
     *
     * @throws InvalidArgumentException if the calendar is unsupported.
     * @psalm-api
     */
    public function withCalendar(string $calendar): self
    {
        ZonedDateTime::extractCalendarFromString($calendar);
        return new self(
            $this->year,
            $this->month,
            $this->day,
            $this->hour,
            $this->minute,
            $this->second,
            $this->millisecond,
            $this->microsecond,
            $this->nanosecond,
            'iso8601',
        );
    }

    /**
     * Always throws TypeError — PlainDateTime must not be used in arithmetic context.
     *
     * @throws \TypeError always.
     * @psalm-return never
     * @psalm-api
     */
    public function valueOf(): never
    {
        throw new \TypeError('PlainDateTime objects are not orderable');
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
     * Parses an ISO 8601 string into a PlainDateTime.
     *
     * Accepts:
     *   - Full datetime: date T time [offset?] [annotations?]
     *       Time formats: HH:MM[:SS[.frac]] (extended) or HHMM[SS[.frac]] (basic)
     *       Separator style must be consistent within time (no mixing).
     *       UTC offset (±HH:MM etc.) is accepted and ignored.
     *       UTC designator Z is rejected (PlainDateTime has no timezone).
     *   - Date-only: YYYY-MM-DD or ±YYYYYY-MM-DD [annotations?] — time defaults to 00:00:00.
     *   - Bracket annotations: validated per TC39 rules.
     *
     * @throws InvalidArgumentException for invalid or out-of-range values.
     */
    private static function fromString(string $s): self
    {
        if ($s === '') {
            throw new InvalidArgumentException('PlainDateTime::from() received an empty string.');
        }
        // Reject non-ASCII minus sign (U+2212).
        if (str_contains($s, "\u{2212}")) {
            throw new InvalidArgumentException(
                "PlainDateTime::from() cannot parse \"{$s}\": non-ASCII minus sign is not allowed.",
            );
        }
        // Reject more than 9 fractional-second digits.
        if (preg_match('/[.,]\d{10,}/', $s) === 1) {
            throw new InvalidArgumentException(
                "PlainDateTime::from() cannot parse \"{$s}\": fractional seconds may have at most 9 digits.",
            );
        }

        // UTC offset sub-pattern (Z excluded — captured separately).
        $offsetHH = '(?:[01]\d|2[0-3])';
        $offsetMM = '[0-5]\d';
        $offsetSS = '[0-5]\d';
        $offsetNonZ = sprintf(
            '[+-]%s(?::%s(?::%s(?:[.,]\d+)?)?|%s(?:%s(?:[.,]\d+)?)?)?',
            $offsetHH,
            $offsetMM,
            $offsetSS,
            $offsetMM,
            $offsetSS,
        );

        // Full datetime pattern (T/t/space separator required).
        // Time section: three mutually exclusive branches to enforce separator consistency:
        //   extended = HH:MM[:SS[.frac]]   (groups 3–6)
        //   basic    = HHMM[SS[.frac]]     (groups 7–10)
        //   hour-only = HH                 (group 13)
        // Group 11 captures a Z designator (which is then rejected).
        // Group 12 captures bracket annotations.
        // groups: 1=year, 2=dateRest, 3-6=ext time, 7-10=basic time, 13=hour-only, 11=Z, 12=annotations
        $dtPattern = sprintf(
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})[Tt ](?:(\d{2}):(\d{2})(?::(\d{2})([.,]\d+)?)?|(\d{2})(\d{2})(?:(\d{2})([.,]\d+)?)?|(\d{2}))(Z)?(?:%s)?((?:\[[^\]]*\])*)$/i',
            $offsetNonZ,
        );

        // Date-only pattern: YYYY-MM-DD or ±YYYYYY-MM-DD or YYYYMMDD, plus optional annotations.
        // Groups: 1=year, 2=dateRest, 3=annotations.
        $dateOnlyPattern = '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})((?:\[[^\]]*\])*)$/i';

        /** @var list<string> $m */
        $m = [];
        $hourNum = 0;
        $minNum = 0;
        $secNum = 0;
        $fracRaw = '';

        if (preg_match($dtPattern, $s, $m) === 1) {
            // UTC designator Z is not allowed for PlainDateTime.
            if (($m[12] ?? '') !== '') { // @phpstan-ignore nullCoalesce.offset
                throw new InvalidArgumentException(
                    "PlainDateTime::from() cannot parse \"{$s}\": UTC designator (Z) is not allowed.",
                );
            }
            $yearRaw = $m[1];
            $dateRest = $m[2];
            $annotations = $m[13] ?? ''; // @phpstan-ignore nullCoalesce.offset
            // Determine which time branch matched (extended uses group 3, basic uses group 7, hour-only uses group 11).
            if ($m[3] !== '') {
                // Extended format: HH:MM[:SS[.frac]]
                $hourNum = (int) $m[3];
                $minNum = (int) $m[4];
                $secNum = $m[5] !== '' ? (int) $m[5] : 0;
                $fracRaw = $m[6];
            } elseif ($m[7] !== '') {
                // Basic format: HHMM[SS[.frac]]
                $hourNum = (int) $m[7];
                $minNum = (int) $m[8];
                $secNum = $m[9] !== '' ? (int) $m[9] : 0;
                $fracRaw = $m[10];
            } else {
                // Hour-only format: HH
                $hourNum = (int) ($m[11] ?? '0'); // @phpstan-ignore nullCoalesce.offset
                $minNum = 0;
                $secNum = 0;
                $fracRaw = '';
            }
            // Leap second 60 → 59.
            if ($secNum === 60) {
                $secNum = 59;
            }
            // Validate time ranges.
            if ($hourNum > 23) {
                throw new InvalidArgumentException(
                    "PlainDateTime::from() cannot parse \"{$s}\": hour {$hourNum} out of range.",
                );
            }
            if ($minNum > 59) {
                throw new InvalidArgumentException(
                    "PlainDateTime::from() cannot parse \"{$s}\": minute {$minNum} out of range.",
                );
            }
            if ($secNum > 59) {
                throw new InvalidArgumentException(
                    "PlainDateTime::from() cannot parse \"{$s}\": second {$secNum} out of range.",
                );
            }
        } elseif (preg_match($dateOnlyPattern, $s, $m) === 1) {
            // Date-only string: time defaults to midnight (all zeros).
            $yearRaw = $m[1];
            $dateRest = $m[2];
            $annotations = $m[3];
        } else {
            throw new InvalidArgumentException(
                "PlainDateTime::from() cannot parse \"{$s}\": invalid ISO 8601 datetime string.",
            );
        }

        // Reject minus-zero extended year (-000000).
        if (preg_match('/^-0{6}$/', $yearRaw) === 1) {
            throw new InvalidArgumentException(
                "PlainDateTime::from() cannot parse \"{$s}\": cannot use negative zero as extended year.",
            );
        }

        // Parse date components.
        if (!str_starts_with($dateRest, '-')) {
            $month = (int) substr(string: $dateRest, offset: 0, length: 2);
            $day = (int) substr(string: $dateRest, offset: 2, length: 2);
        } else {
            $month = (int) substr(string: $dateRest, offset: 1, length: 2);
            $day = (int) substr(string: $dateRest, offset: 4, length: 2);
        }
        $year = (int) $yearRaw;

        // Validate bracket annotations.
        self::validateAnnotations($annotations, $s);

        // Decompose sub-second nanoseconds.
        $subNs = $fracRaw !== '' ? self::parseFraction($fracRaw) : 0;
        $ms = intdiv(num1: $subNs, num2: self::NS_PER_MS);
        $us = intdiv(num1: $subNs % self::NS_PER_MS, num2: self::NS_PER_US);
        $ns = $subNs % self::NS_PER_US;

        return new self($year, $month, $day, $hourNum, $minNum, $secNum, $ms, $us, $ns);
    }

    /**
     * Creates a PlainDateTime from a property-bag array.
     *
     * Required: year, (month or monthCode), day.
     * Optional: hour, minute, second, millisecond, microsecond, nanosecond.
     *
     * @param array<array-key,mixed> $bag
     * @param string                 $overflow 'constrain' (clamp) or 'reject' (throw on out-of-range).
     * @throws \TypeError if required fields are missing or have wrong type.
     * @throws InvalidArgumentException if the datetime is invalid.
     */
    private static function fromPropertyBag(array $bag, string $overflow = 'constrain'): self
    {
        // Validate calendar key if present (delegates to ZonedDateTime::extractCalendarFromString
        // which rejects minus-zero years, unsupported calendars, and empty strings).
        if (array_key_exists('calendar', $bag)) {
            /** @var mixed $cal */
            $cal = $bag['calendar'];
            if (!is_string($cal)) {
                throw new \TypeError(sprintf('PlainDateTime calendar must be a string; got %s.', get_debug_type($cal)));
            }
            ZonedDateTime::extractCalendarFromString($cal);
        }

        if (!array_key_exists('year', $bag)) {
            throw new \TypeError('PlainDateTime property bag must have a year field.');
        }
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('PlainDateTime property bag must have a month or monthCode field.');
        }
        if (!array_key_exists('day', $bag)) {
            throw new \TypeError('PlainDateTime property bag must have a day field.');
        }

        /** @var mixed $yearRaw */
        $yearRaw = $bag['year'];
        if ($yearRaw === null) {
            throw new \TypeError('PlainDateTime property bag year field must not be undefined.');
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $yearRaw)) {
            throw new InvalidArgumentException('PlainDateTime year must be finite.');
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
                throw new \TypeError('PlainDateTime property bag month field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $monthRaw)) {
                throw new InvalidArgumentException('PlainDateTime month must be finite.');
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
            throw new \TypeError('PlainDateTime property bag day field must not be undefined.');
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $dayRaw)) {
            throw new InvalidArgumentException('PlainDateTime day must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $day = is_int($dayRaw) ? $dayRaw : (int) $dayRaw;

        // Time fields default to 0 when absent.
        $h = self::extractIntField($bag, 'hour', 0, 'PlainDateTime');
        $min = self::extractIntField($bag, 'minute', 0, 'PlainDateTime');
        $sec = self::extractIntField($bag, 'second', 0, 'PlainDateTime');
        $ms = self::extractIntField($bag, 'millisecond', 0, 'PlainDateTime');
        $us = self::extractIntField($bag, 'microsecond', 0, 'PlainDateTime');
        $ns = self::extractIntField($bag, 'nanosecond', 0, 'PlainDateTime');

        if ($month < 1) {
            throw new InvalidArgumentException("Invalid PlainDateTime: month {$month} must be at least 1.");
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid PlainDateTime: day {$day} must be at least 1.");
        }

        if ($overflow === 'constrain') {
            /**
             * @var int<1, 12>
             * @psalm-suppress UnnecessaryVarAnnotation — Mago can't narrow min()
             */
            $month = min(12, $month);
            $maxDay = self::calcDaysInMonth($year, $month);
            $day = min($maxDay, $day);
            $h = max(0, min(23, $h));
            $min = max(0, min(59, $min));
            $sec = max(0, min(59, $sec));
            $ms = max(0, min(999, $ms));
            $us = max(0, min(999, $us));
            $ns = max(0, min(999, $ns));
        }

        return new self($year, $month, $day, $h, $min, $sec, $ms, $us, $ns);
    }

    /**
     * Extracts an optional int field from a property bag, returning $default if absent.
     *
     * @param array<array-key,mixed> $bag
     * @param non-empty-string $field
     * @throws \TypeError if the field is present but null.
     * @throws InvalidArgumentException if the value is non-finite.
     */
    private static function extractIntField(array $bag, string $field, int $default, string $className): int
    {
        if (!array_key_exists($field, $bag)) {
            return $default;
        }
        /** @var mixed $raw */
        $raw = $bag[$field];
        if ($raw === null) {
            throw new \TypeError("{$className} property bag {$field} field must not be undefined.");
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $raw)) {
            throw new InvalidArgumentException("{$className} {$field} must be finite.");
        }
        /** @phpstan-ignore cast.int */
        return is_int($raw) ? $raw : (int) $raw;
    }

    /**
     * Core implementation for since() and until().
     *
     * Computes $later − $earlier as a Duration.
     * since($other) passes (later=$this, earlier=$other).
     * until($other) passes (later=$other, earlier=$this).
     *
     * @param array<array-key, mixed>|object|null $options ['largestUnit' => ..., 'smallestUnit' => ..., 'roundingMode' => ..., 'roundingIncrement' => ...]
     */
    private static function diffDateTime(self $later, self $earlier, self $receiver, array|object|null $options): Duration
    {
        /** @var list<string> $validUnits */
        static $validUnits = [
            'auto',
            'day',
            'days',
            'week',
            'weeks',
            'month',
            'months',
            'year',
            'years',
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
        /** @var array<string, int> $unitRank */
        static $unitRank = [
            'year' => 9,
            'years' => 9,
            'month' => 8,
            'months' => 8,
            'week' => 7,
            'weeks' => 7,
            'day' => 6,
            'days' => 6,
            'auto' => 6,
            'hour' => 5,
            'hours' => 5,
            'minute' => 4,
            'minutes' => 4,
            'second' => 3,
            'seconds' => 3,
            'millisecond' => 2,
            'milliseconds' => 2,
            'microsecond' => 1,
            'microseconds' => 1,
            'nanosecond' => 0,
            'nanoseconds' => 0,
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

        $largestUnit = 'day'; // default per TC39 PlainDateTime spec
        $largestUnitExplicit = false;
        $smallestUnit = null;
        $roundingMode = 'trunc';
        $roundingIncrement = 1;

        if ($options !== null) {
            $opts = is_array($options) ? $options : (array) $options;

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

        if ($smallestUnit === null) {
            $smallestUnit = 'nanosecond';
        }

        // Normalize plural/auto to canonical singular.
        $normLargest = match ($largestUnit) {
            'years' => 'year',
            'months' => 'month',
            'weeks' => 'week',
            'days', 'auto' => 'day',
            'hours' => 'hour',
            'minutes' => 'minute',
            'seconds' => 'second',
            'milliseconds' => 'millisecond',
            'microseconds' => 'microsecond',
            'nanoseconds' => 'nanosecond',
            default => $largestUnit,
        };
        $normSmallest = match ($smallestUnit) {
            'years' => 'year',
            'months' => 'month',
            'weeks' => 'week',
            'days', 'auto' => 'day',
            'hours' => 'hour',
            'minutes' => 'minute',
            'seconds' => 'second',
            'milliseconds' => 'millisecond',
            'microseconds' => 'microsecond',
            'nanoseconds' => 'nanosecond',
            default => $smallestUnit,
        };

        $suRank = $unitRank[$normSmallest];
        $luRank = $unitRank[$normLargest];

        if ($suRank > $luRank) {
            if ($largestUnitExplicit) {
                throw new InvalidArgumentException(
                    "smallestUnit \"{$normSmallest}\" cannot be larger than largestUnit \"{$normLargest}\".",
                );
            }
            $normLargest = $normSmallest;
            $luRank = $suRank;
        }

        // Validate roundingIncrement for time units: must divide evenly into next higher unit.
        if ($roundingIncrement > 1) {
            /** @var array<string, int> $maxIncrementMap */
            static $maxIncrementMap = [
                'hour' => 24,
                'minute' => 60,
                'second' => 60,
                'millisecond' => 1000,
                'microsecond' => 1000,
                'nanosecond' => 1000,
            ];
            $maxInc = $maxIncrementMap[$normSmallest] ?? 0;
            if ($maxInc > 0 && ($roundingIncrement >= $maxInc || ($maxInc % $roundingIncrement) !== 0)) {
                throw new InvalidArgumentException(
                    "roundingIncrement {$roundingIncrement} does not divide evenly into the next highest unit for \"{$normSmallest}\".",
                );
            }
        }

        // Compute the raw date and time differences.
        $laterJdn = self::toJulianDay($later->year, $later->month, $later->day);
        $earlierJdn = self::toJulianDay($earlier->year, $earlier->month, $earlier->day);
        $laterNs = self::timeToNs(
            $later->hour,
            $later->minute,
            $later->second,
            $later->millisecond,
            $later->microsecond,
            $later->nanosecond,
        );
        $earlierNs = self::timeToNs(
            $earlier->hour,
            $earlier->minute,
            $earlier->second,
            $earlier->millisecond,
            $earlier->microsecond,
            $earlier->nanosecond,
        );

        $dateDiff = $laterJdn - $earlierJdn; // signed: positive if later > earlier (date-wise)
        $timeDiffNs = $laterNs - $earlierNs; // signed: may be negative

        // The overall sign is determined by the combined date+time diff.
        // To get a consistent sign: compute total ns from both components.
        // A positive diff means later > earlier.
        $sign = 0;
        if ($dateDiff > 0 || $dateDiff === 0 && $timeDiffNs > 0) {
            $sign = 1;
        } elseif ($dateDiff < 0 || $timeDiffNs < 0) {
            $sign = -1;
        }

        // Work in the positive direction; negate all output at the end.
        // Swap so that we always compute (positive later) - (positive earlier).
        if ($sign < 0) {
            [$later, $earlier] = [$earlier, $later];
            $laterJdn = self::toJulianDay($later->year, $later->month, $later->day);
            $earlierJdn = self::toJulianDay($earlier->year, $earlier->month, $earlier->day);
            $laterNs = self::timeToNs(
                $later->hour,
                $later->minute,
                $later->second,
                $later->millisecond,
                $later->microsecond,
                $later->nanosecond,
            );
            $earlierNs = self::timeToNs(
                $earlier->hour,
                $earlier->minute,
                $earlier->second,
                $earlier->millisecond,
                $earlier->microsecond,
                $earlier->nanosecond,
            );
            $dateDiff = $laterJdn - $earlierJdn;
            $timeDiffNs = $laterNs - $earlierNs;
        }

        // Borrow one day from the date component when the time part is negative.
        // After this, $timeDiffNs >= 0 and $dateDiff >= 0.
        if ($timeDiffNs < 0) {
            $dateDiff--;
            $timeDiffNs += self::NS_PER_DAY;
        }
        // Both $dateDiff and $timeDiffNs are now non-negative.

        $isCalendarLargest = $luRank >= 6; // day or above

        if ($isCalendarLargest) {
            $adjLaterJdn = $earlierJdn + $dateDiff;
            [$adjY2, $adjM2, $adjD2] = self::fromJulianDay($adjLaterJdn);
            $earlierY = $earlier->year;
            $earlierM = $earlier->month;
            $earlierD = $earlier->day;
            // The receiver is always $this. After a possible swap, determine whether
            // the receiver corresponds to the "later" date in the positive-direction diff.
            $receiverIsLater = $receiver === $later;

            if ($normLargest === 'day') {
                $days = $dateDiff;
                [$years, $months, $weeks] = [0, 0, 0];
            } elseif ($normLargest === 'week') {
                $weeks = intdiv(num1: $dateDiff, num2: 7);
                $days = $dateDiff - ($weeks * 7);
                [$years, $months] = [0, 0];
            } else {
                [$years, $months, $days] = self::calendarDiff(
                    $earlierY,
                    $earlierM,
                    $earlierD,
                    $adjY2,
                    $adjM2,
                    $adjD2,
                    $receiverIsLater,
                );
                $weeks = 0;
                // Convert years to months when largestUnit is 'month'.
                if ($normLargest === 'month') {
                    $months = ($years * 12) + $months;
                    $years = 0;
                }
            }

            $isSmallestCalendar = in_array($normSmallest, ['year', 'month', 'week', 'day'], strict: true);

            if ($isSmallestCalendar) {
                // Calendar-unit rounding: zero out time and round the calendar part.
                if ($normSmallest === 'year') {
                    $totalMonths = ($years * 12) + $months;
                    $roundedYears = self::roundCalendarYears(
                        $years,
                        $totalMonths,
                        $days,
                        $timeDiffNs,
                        $later,
                        $roundingIncrement,
                        $roundingMode,
                        $receiverIsLater,
                        $sign,
                    );
                    return new Duration(years: $sign * $roundedYears);
                }
                if ($normSmallest === 'month') {
                    $totalMonths = ($years * 12) + $months;
                    $roundedMonths = self::roundCalendarMonths(
                        $totalMonths,
                        $days,
                        $timeDiffNs,
                        $later,
                        $roundingIncrement,
                        $roundingMode,
                        $receiverIsLater,
                        $sign,
                    );
                    if ($normLargest === 'year') {
                        $roundedYears = intdiv(num1: $roundedMonths, num2: 12);
                        $roundedMonths = $roundedMonths - ($roundedYears * 12);
                        return new Duration(years: $sign * $roundedYears, months: $sign * $roundedMonths);
                    }
                    return new Duration(months: $sign * $roundedMonths);
                }
                if ($normSmallest === 'week') {
                    $totalDays = ($weeks * 7) + $days;
                    $weekIncrement = $roundingIncrement * 7;
                    $roundedDays = self::roundDaysWithTime(
                        $totalDays,
                        $timeDiffNs,
                        $weekIncrement,
                        $roundingMode,
                        $sign,
                    );
                    return new Duration(weeks: $sign * intdiv(num1: $roundedDays, num2: 7));
                }
                // normSmallest === 'day'
                $roundedDays = self::roundDaysWithTime($days, $timeDiffNs, $roundingIncrement, $roundingMode, $sign);
                if ($normLargest === 'day') {
                    return new Duration(days: $sign * $roundedDays);
                }
                if ($normLargest === 'week') {
                    $totalDays = ($weeks * 7) + $roundedDays;
                    $roundedWeeks = intdiv(num1: $totalDays, num2: 7);
                    $remDays = $totalDays - ($roundedWeeks * 7);
                    return new Duration(weeks: $sign * $roundedWeeks, days: $sign * $remDays);
                }
                return new Duration(years: $sign * $years, months: $sign * $months, days: $sign * $roundedDays);
            }

            // smallestUnit is a time unit but largestUnit is a calendar unit.
            $nsPerSmallest = match ($normSmallest) {
                'hour' => self::NS_PER_HOUR,
                'minute' => self::NS_PER_MINUTE,
                'second' => self::NS_PER_SECOND,
                'millisecond' => self::NS_PER_MS,
                'microsecond' => self::NS_PER_US,
                default => 1,
            };
            /** @psalm-var int<1, 1000> $roundingIncrement */
            $nsIncrement = $nsPerSmallest * $roundingIncrement;
            // For negative diffs, flip floor/ceil.
            $effTimeMode = $roundingMode;
            if ($sign < 0) {
                $effTimeMode = match ($roundingMode) {
                    'floor' => 'ceil',
                    'ceil' => 'floor',
                    'halfFloor' => 'halfCeil',
                    'halfCeil' => 'halfFloor',
                    default => $roundingMode,
                };
            }
            $absTimeNs = self::roundPositiveNs($timeDiffNs, $nsIncrement, $effTimeMode);

            // Handle day overflow from rounding time (e.g., 23:59 rounds up to 24:00).
            $overflowDays = intdiv(num1: $absTimeNs, num2: self::NS_PER_DAY);
            $absTimeNs = $absTimeNs % self::NS_PER_DAY;

            // When time overflow produces extra days, recompute the calendar diff
            // from the updated position to properly rebalance months/years.
            if ($overflowDays > 0 && $normLargest !== 'day' && $normLargest !== 'week') {
                $adjLaterJdn2 = $adjLaterJdn + $overflowDays;
                [$adjY3, $adjM3, $adjD3] = self::fromJulianDay($adjLaterJdn2);
                [$years, $months, $days] = self::calendarDiff(
                    $earlierY,
                    $earlierM,
                    $earlierD,
                    $adjY3,
                    $adjM3,
                    $adjD3,
                    $receiverIsLater,
                );
                if ($normLargest === 'month') {
                    $months = ($years * 12) + $months;
                    $years = 0;
                }
            } else {
                $days += $overflowDays;
            }

            $h = intdiv(num1: $absTimeNs, num2: self::NS_PER_HOUR);
            $rem = $absTimeNs % self::NS_PER_HOUR;
            $min = intdiv(num1: $rem, num2: self::NS_PER_MINUTE);
            $rem = $rem % self::NS_PER_MINUTE;
            $sec = intdiv(num1: $rem, num2: self::NS_PER_SECOND);
            $rem = $rem % self::NS_PER_SECOND;
            $ms = intdiv(num1: $rem, num2: self::NS_PER_MS);
            $rem = $rem % self::NS_PER_MS;
            $us = intdiv(num1: $rem, num2: self::NS_PER_US);
            $ns = $rem % self::NS_PER_US;

            return new Duration(
                years: $sign * $years,
                months: $sign * $months,
                weeks: $sign * $weeks,
                days: $sign * $days,
                hours: $sign * $h,
                minutes: $sign * $min,
                seconds: $sign * $sec,
                milliseconds: $sign * $ms,
                microseconds: $sign * $us,
                nanoseconds: $sign * $ns,
            );
        }

        // largestUnit is a time unit (hour or smaller): accumulate all days into ns.
        $totalAbsNs = ($dateDiff * self::NS_PER_DAY) + $timeDiffNs;

        $nsPerSmallest = match ($normSmallest) {
            'hour' => self::NS_PER_HOUR,
            'minute' => self::NS_PER_MINUTE,
            'second' => self::NS_PER_SECOND,
            'millisecond' => self::NS_PER_MS,
            'microsecond' => self::NS_PER_US,
            default => 1,
        };
        /** @psalm-var int<1, 1000> $roundingIncrement */
        $nsIncrement = $nsPerSmallest * $roundingIncrement;
        // For negative diffs, flip floor/ceil so they retain their directional meaning.
        $effectiveRoundMode = $roundingMode;
        if ($sign < 0) {
            $effectiveRoundMode = match ($roundingMode) {
                'floor' => 'ceil',
                'ceil' => 'floor',
                'halfFloor' => 'halfCeil',
                'halfCeil' => 'halfFloor',
                default => $roundingMode,
            };
        }
        $roundedAbsNs = self::roundPositiveNs($totalAbsNs, $nsIncrement, $effectiveRoundMode);

        // Decompose based on largest unit (no conversion to higher units).
        /** @var array<string, int> $timeUnitNs */
        static $timeUnitNs = [
            'hour' => 3_600_000_000_000,
            'minute' => 60_000_000_000,
            'second' => 1_000_000_000,
            'millisecond' => 1_000_000,
            'microsecond' => 1_000,
            'nanosecond' => 1,
        ];
        /** @var list<string> $timeUnitOrder */
        static $timeUnitOrder = ['hour', 'minute', 'second', 'millisecond', 'microsecond', 'nanosecond'];

        $rem = $roundedAbsNs;
        $h = 0;
        $min = 0;
        $sec = 0;
        $ms = 0;
        $us = 0;
        $ns = 0;
        $started = false;
        foreach ($timeUnitOrder as $unit) {
            if ($unit === $normLargest) {
                $started = true;
            }
            if (!$started) {
                continue;
            }
            $perUnit = $timeUnitNs[$unit];
            $val = intdiv(num1: $rem, num2: $perUnit);
            $rem = $rem % $perUnit;
            match ($unit) {
                'hour' => $h = $val,
                'minute' => $min = $val,
                'second' => $sec = $val,
                'millisecond' => $ms = $val,
                'microsecond' => $us = $val,
                'nanosecond' => $ns = $val,
                default => null,
            };
        }

        return new Duration(
            hours: $sign * $h,
            minutes: $sign * $min,
            seconds: $sign * $sec,
            milliseconds: $sign * $ms,
            microseconds: $sign * $us,
            nanoseconds: $sign * $ns,
        );
    }

    /**
     * Shared implementation for add() and subtract().
     *
     * Time units are balanced into nanoseconds, then converted to overflow days.
     * Day + calendar units are applied to the date part using PlainDate-style arithmetic.
     *
     * @param array<array-key, mixed>|object|null $options
     */
    private function addDuration(int $sign, Duration $dur, array|object|null $options): self
    {
        $overflow = self::extractOverflow($options);

        $years = $sign * (int) $dur->years;
        $months = $sign * (int) $dur->months;
        $days = $sign * (((int) $dur->weeks * 7) + (int) $dur->days);

        // Balance time units to nanoseconds, then extract whole days.
        $hours = $sign * (int) $dur->hours;
        $minutes = $sign * (int) $dur->minutes;
        $seconds = $sign * (int) $dur->seconds;
        $ms = $sign * (int) $dur->milliseconds;
        $us = $sign * (int) $dur->microseconds;
        $ns = $sign * (int) $dur->nanoseconds;

        // Balance time units using the same step-by-step carry approach as PlainDate,
        // to avoid int64 overflow with large Duration field values.
        // Each step extracts whole days and passes the remainder to the next smaller unit.

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

        // carry + nanoseconds → full days + remainder ns
        $totalNs = ($usRem * 1_000) + $ns;
        $nsDays = intdiv(num1: $totalNs, num2: 86_400_000_000_000);
        $nsRem = $totalNs % 86_400_000_000_000;

        $days += $hDays + $mDays + $sDays + $msDays + $usDays + $nsDays;

        // Reconstruct time-of-day from the accumulated remainders.
        // $nsRem is the total sub-day nanoseconds; it may be negative when the
        // duration is negative. Normalise to [0, NS_PER_DAY) using floor-div.
        $currentTimeNs = self::timeToNs(
            $this->hour,
            $this->minute,
            $this->second,
            $this->millisecond,
            $this->microsecond,
            $this->nanosecond,
        );
        $newTimeNs = $currentTimeNs + $nsRem;

        // Carry overflow days from the time component.
        if ($newTimeNs < 0) {
            $overflowDays = (int) floor($newTimeNs / self::NS_PER_DAY);
            $newTimeNs -= $overflowDays * self::NS_PER_DAY;
        } else {
            $overflowDays = intdiv(num1: $newTimeNs, num2: self::NS_PER_DAY);
            $newTimeNs = $newTimeNs % self::NS_PER_DAY;
        }

        $days += $overflowDays;

        // Apply years and months calendrically.
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

        // Clamp or reject day within new month.
        $newDay = $this->day;
        $maxDay = self::calcDaysInMonth($newYear, $newMonth);
        if ($newDay > $maxDay) {
            if ($overflow === 'constrain') {
                $newDay = $maxDay;
            } else {
                throw new InvalidArgumentException("Day {$newDay} is out of range for {$newYear}-{$newMonth}.");
            }
        }

        // Add days via Julian Day Number.
        $jdn = self::toJulianDay($newYear, $newMonth, $newDay) + $days;

        $minJdn = self::toJulianDay(-271821, 4, 19);
        $maxJdn = self::toJulianDay(275760, 9, 13);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException('PlainDateTime arithmetic result is outside the representable range.');
        }

        [$newYear, $newMonth, $newDay] = self::fromJulianDay($jdn);

        // Decompose new time.
        $h = intdiv(num1: $newTimeNs, num2: self::NS_PER_HOUR);
        $rem = $newTimeNs % self::NS_PER_HOUR;
        $min = intdiv(num1: $rem, num2: self::NS_PER_MINUTE);
        $rem = $rem % self::NS_PER_MINUTE;
        $sec = intdiv(num1: $rem, num2: self::NS_PER_SECOND);
        $rem = $rem % self::NS_PER_SECOND;
        $msR = intdiv(num1: $rem, num2: self::NS_PER_MS);
        $rem = $rem % self::NS_PER_MS;
        $usR = intdiv(num1: $rem, num2: self::NS_PER_US);
        $nsR = $rem % self::NS_PER_US;

        return new self($newYear, $newMonth, $newDay, $h, $min, $sec, $msR, $usR, $nsR);
    }

    /**
     * Validates bracket annotations in a PlainDateTime string.
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
                // Offset-style TZ annotation: reject sub-minute (seconds component).
                if (preg_match('/^[+-]/', $content) === 1) {
                    if (
                        preg_match('/^[+-]\d{2}:\d{2}:\d{2}/', $content) === 1
                        || preg_match('/^[+-]\d{2}:\d{2}[.,]/', $content) === 1
                    ) {
                        throw new InvalidArgumentException(
                            "Sub-minute UTC offset in time-zone annotation in \"{$original}\".",
                        );
                    }
                    if (preg_match('/^[+-]\d{2}(?!\d*:)\d{4,}/', $content) === 1) {
                        throw new InvalidArgumentException(
                            "Sub-minute UTC offset in time-zone annotation in \"{$original}\".",
                        );
                    }
                }
            }
        }
    }

    /**
     * Parses fractional-second string (".123456789" or ",123") into nanoseconds.
     * Pads or truncates to exactly 9 digits.
     */
    private static function parseFraction(string $fractionRaw): int
    {
        $digits = substr(string: $fractionRaw, offset: 1); // strip leading '.' or ','
        return (int) str_pad(substr(string: $digits, offset: 0, length: 9), length: 9, pad_string: '0');
    }

    /**
     * Validates all time fields and throws if any are out of range.
     *
     * @phpstan-assert int<0, 23> $h
     * @phpstan-assert int<0, 59> $min
     * @phpstan-assert int<0, 59> $sec
     * @phpstan-assert int<0, 999> $ms
     * @phpstan-assert int<0, 999> $us
     * @phpstan-assert int<0, 999> $ns
     * @throws InvalidArgumentException if any field is out of its valid range.
     */
    private static function validateTimeFields(int $h, int $min, int $sec, int $ms, int $us, int $ns): void
    {
        if ($h < 0 || $h > 23) {
            throw new InvalidArgumentException("Invalid PlainDateTime: hour {$h} is out of range 0–23.");
        }
        if ($min < 0 || $min > 59) {
            throw new InvalidArgumentException("Invalid PlainDateTime: minute {$min} is out of range 0–59.");
        }
        if ($sec < 0 || $sec > 59) {
            throw new InvalidArgumentException("Invalid PlainDateTime: second {$sec} is out of range 0–59.");
        }
        if ($ms < 0 || $ms > 999) {
            throw new InvalidArgumentException("Invalid PlainDateTime: millisecond {$ms} is out of range 0–999.");
        }
        if ($us < 0 || $us > 999) {
            throw new InvalidArgumentException("Invalid PlainDateTime: microsecond {$us} is out of range 0–999.");
        }
        if ($ns < 0 || $ns > 999) {
            throw new InvalidArgumentException("Invalid PlainDateTime: nanosecond {$ns} is out of range 0–999.");
        }
    }

    /**
     * Extracts and validates the 'overflow' option from an options bag.
     *
     * Returns 'constrain' or 'reject'. Default is 'constrain'.
     *
     * @param array<array-key, mixed>|object|null $options
     * @throws \TypeError if the overflow value is not a string.
     * @throws InvalidArgumentException if the overflow value is unrecognized.
     */
    private static function extractOverflow(array|object|null $options): string
    {
        if ($options === null) {
            return 'constrain';
        }
        if (is_object($options)) {
            $options = (array) $options;
        }
        if (!array_key_exists('overflow', $options)) {
            return 'constrain';
        }
        /** @var mixed $val */
        $val = $options['overflow'];
        if ($val === null || is_bool($val)) {
            throw new InvalidArgumentException("Invalid overflow value: must be 'constrain' or 'reject'.");
        }
        if (!is_string($val)) {
            throw new \TypeError('overflow option must be a string.');
        }
        if ($val !== 'constrain' && $val !== 'reject') {
            throw new InvalidArgumentException("Invalid overflow value \"{$val}\": must be 'constrain' or 'reject'.");
        }
        return $val;
    }

    /**
     * Converts time fields to total nanoseconds since midnight.
     */
    private static function timeToNs(int $h, int $min, int $sec, int $ms, int $us, int $ns): int
    {
        return (
            ($h * self::NS_PER_HOUR)
            + ($min * self::NS_PER_MINUTE)
            + ($sec * self::NS_PER_SECOND)
            + ($ms * self::NS_PER_MS)
            + ($us * self::NS_PER_US)
            + $ns
        );
    }

    /**
     * Calendar-aware year/month/day breakdown between two dates, as used by since()/until().
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

        $receiverIsY2AfterSwap = $receiverIsY2;

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

        if ($d2 < $d1) {
            if ($months > 0) {
                $months--;
            } else {
                $years--;
                $months = 11;
            }
        }

        if ($receiverIsY2AfterSwap) {
            $anchorMonth = $m2 - $months;
            $anchorYear = $y2 - $years;
            if ($anchorMonth <= 0) {
                $anchorYear--;
                $anchorMonth += 12;
            }
            $anchorMaxDay = self::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay = min($d2, $anchorMaxDay);
            $days = self::toJulianDay($anchorYear, $anchorMonth, $anchorDay) - self::toJulianDay($y1, $m1, $d1);
        } else {
            $anchorMonth = $m1 + $months;
            $anchorYear = $y1 + $years;
            if ($anchorMonth > 12) {
                $anchorYear++;
                $anchorMonth -= 12;
            }
            $anchorMaxDay = self::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay = min($d1, $anchorMaxDay);
            $days = self::toJulianDay($y2, $m2, $d2) - self::toJulianDay($anchorYear, $anchorMonth, $anchorDay);
        }

        return [$sign * $years, $sign * $months, $sign * $days];
    }

    /**
     * Rounds days (non-negative) plus remaining time-of-day nanoseconds using the given
     * rounding mode. The time ns acts as fractional progress toward the next day.
     */
    private static function roundDaysWithTime(int $days, int $timeNs, int $increment, string $mode, int $sign = 1): int
    {
        $progress = $timeNs > 0 ? (float) $timeNs / (float) self::NS_PER_DAY : 0.0;
        $roundUp = self::applyRoundingProgress($days, $progress, $increment, $mode, $sign);
        $q = intdiv(num1: $days, num2: $increment);
        return $roundUp ? ($q + 1) * $increment : $q * $increment;
    }

    /**
     * Calendar-aware rounding for months (NudgeToCalendarUnit, unit=months).
     *
     * Rounds $totalMonths (non-negative) + $remainingDays + $remainingTimeNs to the
     * nearest $increment months, anchored from the later date.
     *
     * @throws InvalidArgumentException if the rounded date is out of the valid ISO range.
     */
    private static function roundCalendarMonths(
        int $totalMonths,
        int $remainingDays,
        int $remainingTimeNs,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
        int $sign = 1,
    ): int {
        $dir = $receiverIsLater ? -1 : 1;

        // floor-count (rounded down to nearest multiple of increment).
        $floorCount = intdiv(num1: $totalMonths, num2: $increment) * $increment;

        $anchorJdn = self::addSignedMonths($receiver, $dir * $floorCount);
        $nextJdn = self::addSignedMonths($receiver, $dir * ($floorCount + $increment));

        $intervalDays = abs($nextJdn - $anchorJdn);

        // Total fractional progress: remaining days + remaining time as fraction of a day.
        $totalRemNs = ($remainingDays * self::NS_PER_DAY) + $remainingTimeNs;
        $progress = $intervalDays > 0 ? (float) $totalRemNs / ((float) $intervalDays * (float) self::NS_PER_DAY) : 0.0;

        $roundUp = self::applyRoundingProgress($totalMonths, $progress, $increment, $mode, $sign);

        $roundedAbsMonths = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate: the rounded result must not exceed the valid PlainDate range.
        self::addSignedMonths($receiver, $dir * $roundedAbsMonths);

        return $roundedAbsMonths;
    }

    /**
     * Calendar-aware rounding for years (NudgeToCalendarUnit, unit=years).
     *
     * @throws InvalidArgumentException if the rounded date is out of the valid ISO range.
     */
    private static function roundCalendarYears(
        int $years,
        int $totalMonths,
        int $remainingDays,
        int $remainingTimeNs,
        self $receiver,
        int $increment,
        string $mode,
        bool $receiverIsLater,
        int $sign = 1,
    ): int {
        $dir = $receiverIsLater ? -1 : 1;

        $floorCount = intdiv(num1: $years, num2: $increment) * $increment;

        // For year rounding, we go by year increments (12 months each).
        $anchorJdn = self::addSignedMonths($receiver, $dir * $floorCount * 12);
        $nextJdn = self::addSignedMonths($receiver, $dir * ($floorCount + $increment) * 12);

        $intervalDays = abs($nextJdn - $anchorJdn);

        // Compute the total distance from anchor (floorCount years) to actual position.
        $remMonths = $totalMonths - ($floorCount * 12);
        $monthsJdn = self::addSignedMonths($receiver, $dir * (($floorCount * 12) + $remMonths));
        $remDaysFromMonths = abs($monthsJdn - $anchorJdn);
        $totalRemNs = (($remDaysFromMonths + $remainingDays) * self::NS_PER_DAY) + $remainingTimeNs;
        $progress = $intervalDays > 0 ? (float) $totalRemNs / ((float) $intervalDays * (float) self::NS_PER_DAY) : 0.0;

        $roundUp = self::applyRoundingProgress($years, $progress, $increment, $mode, $sign);

        $roundedAbsYears = $roundUp ? $floorCount + $increment : $floorCount;

        // Validate range.
        self::addSignedMonths($receiver, $dir * $roundedAbsYears * 12);

        return $roundedAbsYears;
    }

    /**
     * Determines whether to round up based on fractional progress within an interval.
     *
     * For directed modes (floor/ceil), the direction is always positive since we
     * work with absolute values and apply sign at the end.
     *
     * @param int   $wholeUnits The number of whole units (for halfEven: determines evenness).
     * @param float $progress   Fractional progress in [0.0, 1.0).
     * @param int   $increment  The rounding increment.
     * @param string $mode      The rounding mode.
     * @return bool True if the value should be rounded up.
     */
    private static function applyRoundingProgress(
        int $wholeUnits,
        float $progress,
        int $increment,
        string $mode,
        int $sign = 1,
    ): bool {
        $q = intdiv(num1: $wholeUnits, num2: $increment);
        $unitRem = $wholeUnits - ($q * $increment);
        $hasFraction = $unitRem > 0 || $progress > 0.0;
        $halfPoint = (float) $increment / 2.0;
        $totalFrac = (float) $unitRem + $progress;

        // For negative diffs, flip floor/ceil so they retain their directional meaning.
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
            'ceil', 'expand' => $hasFraction,
            'halfExpand', 'halfCeil' => $totalFrac >= $halfPoint,
            'halfTrunc', 'halfFloor' => $totalFrac > $halfPoint,
            'halfEven' => $totalFrac > $halfPoint || $totalFrac === $halfPoint && ($q % 2) !== 0,
            default => false,
        };
    }

    /**
     * Adds $signedMonths months to $receiver's date and returns the resulting Julian Day Number.
     *
     * @throws InvalidArgumentException if the resulting date is outside the valid ISO range.
     */
    private static function addSignedMonths(self $receiver, int $signedMonths): int
    {
        $newMonth = $receiver->month + $signedMonths;
        $newYear = $receiver->year;

        if ($newMonth > 12) {
            $newYear += intdiv(num1: $newMonth - 1, num2: 12);
            $newMonth = (($newMonth - 1) % 12) + 1;
        } elseif ($newMonth < 1) {
            $newYear += intdiv(num1: $newMonth - 12, num2: 12);
            $newMonth = (((($newMonth - 1) % 12) + 12) % 12) + 1;
        }

        $maxDay = self::calcDaysInMonth($newYear, $newMonth);
        $newDay = min($receiver->day, $maxDay);

        $jdn = self::toJulianDay($newYear, $newMonth, $newDay);
        $minJdn = self::toJulianDay(-271821, 4, 19);
        $maxJdn = self::toJulianDay(275760, 9, 13);
        if ($jdn < $minJdn || $jdn > $maxJdn) {
            throw new InvalidArgumentException('Rounded PlainDateTime is outside the representable range.');
        }

        return $jdn;
    }

    /**
     * Rounds a non-negative nanosecond value to the nearest multiple of $increment.
     *
     * @throws InvalidArgumentException for unknown rounding modes.
     */
    private static function roundPositiveNs(int $ns, int $increment, string $mode): int
    {
        $q = intdiv(num1: $ns, num2: $increment);
        $rem = $ns - ($q * $increment);
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
            default => throw new InvalidArgumentException("Invalid roundingMode \"{$mode}\"."),
        };
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
     * Converts a Julian Day Number to a proleptic Gregorian calendar date.
     *
     * @return array{0: int, 1: int<1, 12>, 2: int<1, 31>} [year, month, day]
     */
    private static function fromJulianDay(int $jdn): array
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
        $dow =
            (
                $year + intdiv(num1: $year, num2: 4)
                - intdiv(num1: $year, num2: 100)
                + intdiv(num1: $year, num2: 400)
                + $t[$month - 1]
                + $day
            )
            % 7;
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
        $dow = self::isoWeekday($year, $month, $day);
        $ordinal = self::calcDayOfYear($year, $month, $day);

        $thursdayOrdinal = $ordinal + (4 - $dow);

        if ($thursdayOrdinal < 1) {
            $prevYear = $year - 1;
            $dec31Dow = self::isoWeekday($prevYear, 12, 31);
            $dec31Ord = self::isLeapYear($prevYear) ? 366 : 365;
            $prevWeek = intdiv(num1: $dec31Ord + (4 - $dec31Dow) - 1, num2: 7) + 1;
            return ['week' => $prevWeek, 'year' => $prevYear];
        }

        $yearDays = self::isLeapYear($year) ? 366 : 365;
        if ($thursdayOrdinal > $yearDays) {
            return ['week' => 1, 'year' => $year + 1];
        }

        $week = intdiv(num1: $thursdayOrdinal - 1, num2: 7) + 1;
        return ['week' => $week, 'year' => $year];
    }

    /**
     * Floor division: rounds towards negative infinity (unlike intdiv which truncates towards zero).
     */
    private static function floorDiv(int $a, int $b): int
    {
        return (int) floor($a / $b);
    }
}
