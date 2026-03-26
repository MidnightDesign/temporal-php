<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

/**
 * A wall-clock time without a date or time zone.
 *
 * Stores the time as total nanoseconds since midnight (00:00:00.000000000).
 * Valid range: 00:00:00.000000000 – 23:59:59.999999999.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-plaintime-objects
 */
final class PlainTime implements Stringable
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
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $hour {
        get => intdiv(num1: $this->ns, num2: self::NS_PER_HOUR);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $minute {
        get => intdiv(num1: $this->ns % self::NS_PER_HOUR, num2: self::NS_PER_MINUTE);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $second {
        get => intdiv(num1: $this->ns % self::NS_PER_MINUTE, num2: self::NS_PER_SECOND);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $millisecond {
        get => intdiv(num1: $this->ns % self::NS_PER_SECOND, num2: self::NS_PER_MS);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $microsecond {
        get => intdiv(num1: $this->ns % self::NS_PER_MS, num2: self::NS_PER_US);
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $nanosecond {
        get => $this->ns % self::NS_PER_US;
    }

    // -------------------------------------------------------------------------
    // Internal storage
    // -------------------------------------------------------------------------

    /** @psalm-suppress PropertyNotSetInConstructor — set unconditionally in constructor */
    private readonly int $ns;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param int|float $hour        0–23
     * @param int|float $minute      0–59
     * @param int|float $second      0–59
     * @param int|float $millisecond 0–999
     * @param int|float $microsecond 0–999
     * @param int|float $nanosecond  0–999
     * @throws InvalidArgumentException if any parameter is infinite or out of range.
     */
    public function __construct(
        int|float $hour = 0,
        int|float $minute = 0,
        int|float $second = 0,
        int|float $millisecond = 0,
        int|float $microsecond = 0,
        int|float $nanosecond = 0,
    ) {
        if (
            !is_finite((float) $hour)
            || !is_finite((float) $minute)
            || !is_finite((float) $second)
            || !is_finite((float) $millisecond)
            || !is_finite((float) $microsecond)
            || !is_finite((float) $nanosecond)
        ) {
            throw new InvalidArgumentException('Invalid PlainTime: all fields must be finite numbers.');
        }
        $h = (int) $hour;
        $min = (int) $minute;
        $sec = (int) $second;
        $ms = (int) $millisecond;
        $us = (int) $microsecond;
        $ns = (int) $nanosecond;

        self::validateFields($h, $min, $sec, $ms, $us, $ns);

        $this->ns =
            ($h * self::NS_PER_HOUR)
            + ($min * self::NS_PER_MINUTE)
            + ($sec * self::NS_PER_SECOND)
            + ($ms * self::NS_PER_MS)
            + ($us * self::NS_PER_US)
            + $ns;
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Creates a PlainTime from another PlainTime, a property-bag array, or an
     * ISO 8601 time string.
     *
     * String parsing rules:
     *   - Optional leading T/t prefix stripped.
     *   - HH:MM[:SS[.frac]] or HHMM[SS[.frac]] — fractional seconds up to 9 digits.
     *   - Full datetime strings (YYYY-MM-DDTHH:MM…): date portion is discarded.
     *   - UTC designator 'Z' in a time string is rejected (RangeError per spec).
     *   - Non-Z UTC offset (+HH:MM, −HH:MM etc.) after the time is ignored.
     *   - Bracket annotations validated (critical unknown, multiple TZ, etc.).
     *
     * Overflow option (default 'constrain'):
     *   - For strings: overflow is ignored; leap second 60 always normalizes to 59.
     *   - For property bags: 'constrain' clamps out-of-range values; 'reject' throws.
     *   - For PlainTime instances: overflow is ignored.
     *
     * @param self|string|array<array-key, mixed>|object $item PlainTime, ISO 8601 time string, or property-bag array.
     * @param array<array-key, mixed>|object|null $options Options bag or null; supports 'overflow' key.
     * @throws InvalidArgumentException if the string is invalid or any field is out of range.
     * @throws \TypeError if the type cannot be interpreted as a PlainTime.
     * @psalm-api
     */
    public static function from(string|array|object $item, array|object|null $options = null): self
    {
        if ($item instanceof self) {
            // Validate overflow option even though it's ignored for PlainTime instances.
            self::extractOverflow($options);
            return self::fromNs($item->ns);
        }
        if (is_string($item)) {
            // Overflow option is ignored for strings (per spec), but still validate it.
            self::extractOverflow($options);
            return self::fromString($item);
        }
        if (is_array($item)) {
            $overflow = self::extractOverflow($options);
            return self::fromPropertyBag($item, $overflow);
        }
        throw new \TypeError(sprintf(
            'PlainTime::from() expects a PlainTime, ISO 8601 time string, or property-bag array; got %s.',
            get_debug_type($item),
        ));
    }

    /**
     * Compares two PlainTime values.
     *
     * @param self|string|array<array-key, mixed>|object $one PlainTime or ISO 8601 time string.
     * @param self|string|array<array-key, mixed>|object $two PlainTime or ISO 8601 time string.
     * @return int -1, 0, or 1.
     * @psalm-api
     */
    public static function compare(string|array|object $one, string|array|object $two): int
    {
        $a = $one instanceof self ? $one : self::from($one);
        $b = $two instanceof self ? $two : self::from($two);
        return $a->ns <=> $b->ns;
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainTime with specified fields replaced.
     *
     * Only time fields (hour, minute, second, millisecond, microsecond, nanosecond)
     * are recognized; unrecognized keys are silently ignored.
     *
     * @param array<array-key, mixed> $fields
     * @param array<array-key, mixed>|object|null        $options Options bag or null; supports 'overflow' key.
     * @throws InvalidArgumentException if a field value is infinite or out of range.
     * @psalm-api
     */
    public function with(array $fields, array|object|null $options = null): self
    {
        $overflow = self::extractOverflow($options);

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
                throw new InvalidArgumentException('PlainTime::with() hour must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $h = (int) $v;
        }
        if (array_key_exists('minute', $fields)) {
            /** @var mixed $v */
            $v = $fields['minute'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainTime::with() minute must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $min = (int) $v;
        }
        if (array_key_exists('second', $fields)) {
            /** @var mixed $v */
            $v = $fields['second'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainTime::with() second must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $sec = (int) $v;
        }
        if (array_key_exists('millisecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['millisecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainTime::with() millisecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ms = (int) $v;
        }
        if (array_key_exists('microsecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['microsecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainTime::with() microsecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $us = (int) $v;
        }
        if (array_key_exists('nanosecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['nanosecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('PlainTime::with() nanosecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ns = (int) $v;
        }

        if ($overflow === 'constrain') {
            $h = max(0, min(23, $h));
            $min = max(0, min(59, $min));
            $sec = max(0, min(59, $sec));
            $ms = max(0, min(999, $ms));
            $us = max(0, min(999, $us));
            $ns = max(0, min(999, $ns));
        } else {
            self::validateFields($h, $min, $sec, $ms, $us, $ns);
        }

        return new self($h, $min, $sec, $ms, $us, $ns);
    }

    /**
     * Returns a new PlainTime advanced by the given duration.
     *
     * Calendar fields (years, months, weeks, days) are silently ignored — PlainTime
     * has no calendar context and only time fields are applied.
     *
     * @param Duration|string|array<array-key, mixed>|object $duration Duration, ISO 8601 duration string, or property-bag array.
     * @psalm-api
     */
    public function add(string|array|object $duration): self
    {
        $d = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addTimeFields(1, $d);
    }

    /**
     * Returns a new PlainTime moved back by the given duration.
     *
     * Calendar fields (years, months, weeks, days) are silently ignored.
     *
     * @param Duration|string|array<array-key,mixed>|object $duration Duration, ISO 8601 duration string, or property-bag array.
     * @psalm-api
     */
    public function subtract(string|array|object $duration): self
    {
        $d = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addTimeFields(-1, $d);
    }

    /**
     * Returns the Duration from this time to $other (other − this).
     *
     * The result is always positive (or zero) when $other > $this within the same day.
     * Options: largestUnit, smallestUnit, roundingMode, roundingIncrement.
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainTime or ISO 8601 time string.
     * @param array<array-key, mixed>|object|null $options Options array or null.
     * @throws InvalidArgumentException for invalid option values.
     * @psalm-api
     */
    public function until(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        $diffNs = $o->ns - $this->ns;
        return self::diffTime($diffNs, $options);
    }

    /**
     * Returns the Duration from $other to this time (this − other).
     *
     * Options: largestUnit, smallestUnit, roundingMode, roundingIncrement.
     *
     * @param self|string|array<array-key, mixed>|object $other   PlainTime or ISO 8601 time string.
     * @param array<array-key, mixed>|object|null $options Options array or null.
     * @throws InvalidArgumentException for invalid option values.
     * @psalm-api
     */
    public function since(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        $diffNs = $this->ns - $o->ns;
        return self::diffTime($diffNs, $options);
    }

    /**
     * Returns a new PlainTime rounded to the given unit and increment.
     *
     * @param string|array<array-key, mixed>|object $options string smallestUnit or array with keys:
     *   - smallestUnit (required): 'hour'|'minute'|'second'|'millisecond'|'microsecond'|'nanosecond'
     *   - roundingMode (default 'halfExpand'): 'trunc'|'floor'|'ceil'|'expand'|'halfExpand'|
     *                                          'halfTrunc'|'halfFloor'|'halfCeil'|'halfEven'
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
            throw new InvalidArgumentException('Temporal\\PlainTime::round() requires smallestUnit.');
        }
        if (!is_string($suRaw)) {
            throw new \TypeError('smallestUnit must be a string.');
        }

        // ns-per-unit → max increment (exclusive: must be strictly less than this)
        // The max is the number of units in the next-larger unit (for rounding to divide evenly).
        $unitMap = [
            'nanosecond' => [1, 1_000],
            'nanoseconds' => [1, 1_000],
            'microsecond' => [1_000, 1_000],
            'microseconds' => [1_000, 1_000],
            'millisecond' => [1_000_000, 1_000],
            'milliseconds' => [1_000_000, 1_000],
            'second' => [1_000_000_000, 60],
            'seconds' => [1_000_000_000, 60],
            'minute' => [60_000_000_000, 60],
            'minutes' => [60_000_000_000, 60],
            'hour' => [3_600_000_000_000, 24],
            'hours' => [3_600_000_000_000, 24],
        ];
        if (!array_key_exists($suRaw, $unitMap)) {
            throw new InvalidArgumentException("Invalid smallestUnit \"{$suRaw}\" for Temporal\\PlainTime::round().");
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
        // Increment must be strictly less than maxIncrement and must divide it evenly.
        if ($increment >= $maxIncrement || ($maxIncrement % $increment) !== 0) {
            throw new InvalidArgumentException("roundingIncrement {$increment} is invalid for unit \"{$suRaw}\".");
        }

        $nsIncrement = $nsPerUnit * $increment;
        // Round $this->ns (always non-negative) using the given mode.
        $rounded = self::roundPositiveNs($this->ns, $nsIncrement, $roundingMode);
        // Wrap modulo one day (rounded could reach exactly NS_PER_DAY).
        $rounded %= self::NS_PER_DAY;
        return self::fromNs($rounded);
    }

    /**
     * Returns true if this PlainTime represents the same time as $other.
     *
     * @param self|string|array<array-key, mixed>|object $other A PlainTime or ISO 8601 time string.
     * @psalm-api
     */
    public function equals(string|array|object $other): bool
    {
        $o = $other instanceof self ? $other : self::from($other);
        return $this->ns === $o->ns;
    }

    /**
     * Returns an ISO 8601 time string: HH:MM:SS[.fraction].
     *
     * Options (all optional):
     *   - fractionalSecondDigits: 'auto' (default) | 0–9
     *     'auto' strips trailing zeros from the sub-second part, but always includes seconds.
     *   - smallestUnit: 'minute'|'second'|'millisecond'|'microsecond'|'nanosecond'
     *     Overrides fractionalSecondDigits.
     *   - roundingMode: rounding mode (default 'trunc').
     *     When combined with smallestUnit/fractionalSecondDigits, rounds the time before output.
     *
     * @param array<array-key, mixed>|object|null $options null, an array of options, or any object (treated as empty options bag).
     * @throws InvalidArgumentException if options are invalid.
     * @throws \TypeError if $options is a non-null, non-array, non-object scalar.
     * @psalm-api
     */
    public function toString(array|object|null $options = null): string
    {
        if (is_object($options)) {
            $options = [];
        }

        // $digits: -2 = 'auto', -1 = minute format (no seconds), 0-9 = fixed digits.
        $digits = -2;
        $isMinute = false;
        $roundingMode = 'trunc'; // default: truncate

        if ($options !== null) {
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
                $su = (string) $options['smallestUnit'];
                [$digits, $isMinute] = match ($su) {
                    'minute', 'minutes' => [-1, true],
                    'second', 'seconds' => [0, false],
                    'millisecond', 'milliseconds' => [3, false],
                    'microsecond', 'microseconds' => [6, false],
                    'nanosecond', 'nanoseconds' => [9, false],
                    default => throw new InvalidArgumentException("Invalid smallestUnit \"{$su}\"."),
                };
            }

            // roundingMode option.
            if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
                /** @var mixed $rm */
                $rm = $options['roundingMode'];
                if (!is_string($rm)) {
                    throw new \TypeError('roundingMode must be a string.');
                }
                /** @var list<string> $validModes */
                static $validModes = [
                    'trunc',
                    'floor',
                    'ceil',
                    'expand',
                    'halfExpand',
                    'halfTrunc',
                    'halfFloor',
                    'halfCeil',
                    'halfEven',
                ];
                if (!in_array($rm, $validModes, strict: true)) {
                    throw new InvalidArgumentException("Invalid roundingMode \"{$rm}\".");
                }
                $roundingMode = $rm;
            }
        }

        // Determine nsIncrement from $digits: 10^(9-digits) nanoseconds.
        // digits=-2 ('auto'): nanosecond precision (no rounding changes display).
        // digits=-1 ('minute'): minute precision.
        // digits=0..9: second or sub-second precision.
        if ($digits === -2) {
            $nsIncrement = 1; // nanosecond precision; rounding=trunc is a no-op
        } elseif ($digits === -1) {
            $nsIncrement = self::NS_PER_MINUTE;
        } else {
            // exponent is 9 - $digits (0..9), so 10^0=1 to 10^9=1_000_000_000.
            $nsIncrement = (int) 10 ** (9 - $digits); // @phpstan-ignore cast.useless
        }

        // Round the nanoseconds (always non-negative).
        $nsToFormat = self::roundPositiveNs($this->ns, $nsIncrement, $roundingMode);
        // Wrap modulo one day (rounding could reach exactly NS_PER_DAY for ceil-like modes).
        $nsToFormat %= self::NS_PER_DAY;

        $h = intdiv(num1: $nsToFormat, num2: self::NS_PER_HOUR);
        $rem = $nsToFormat % self::NS_PER_HOUR;
        $min = intdiv(num1: $rem, num2: self::NS_PER_MINUTE);
        $rem = $rem % self::NS_PER_MINUTE;
        $sec = intdiv(num1: $rem, num2: self::NS_PER_SECOND);
        // Sub-second nanoseconds (0–999_999_999).
        $subNs = $rem % self::NS_PER_SECOND;

        if ($isMinute) {
            return sprintf('%02d:%02d', $h, $min);
        }

        $base = sprintf('%02d:%02d:%02d', $h, $min, $sec);

        if ($digits === -2) {
            // 'auto': omit sub-seconds when zero, otherwise strip trailing zeros.
            if ($subNs === 0) {
                return $base;
            }
            $fraction = rtrim(sprintf('%09d', $subNs), characters: '0');
            return "{$base}.{$fraction}";
        }

        if ($digits === 0) {
            return $base;
        }

        $fraction = substr(string: sprintf('%09d', $subNs), offset: 0, length: $digits);
        return "{$base}.{$fraction}";
    }

    /** @psalm-api */
    public function toJSON(): string
    {
        return $this->toString();
    }

    /**
     * Returns a locale-sensitive string for this PlainTime.
     *
     * PHP has no ICU Temporal support, so this falls back to toString().
     * The TC39 spec permits implementations to choose locale behavior.
     *
     * @param string|array<array-key, mixed>|null $locales BCP 47 locale string or array (ignored in PHP).
     * @param array<array-key, mixed>|object|null $options Intl.DateTimeFormat options bag (ignored in PHP).
     * @psalm-suppress UnusedParam
     * @psalm-api
     */
    public function toLocaleString(string|array|null $locales = null, array|object|null $options = null): string
    {
        return $this->toString();
    }

    /**
     * Always throws TypeError — PlainTime must not be used in arithmetic context.
     *
     * @throws \TypeError always.
     * @psalm-return never
     * @psalm-api
     */
    public function valueOf(): never
    {
        throw new \TypeError('Use Temporal.PlainTime.compare() to compare Temporal.PlainTime values.');
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
     * Internal factory: create a PlainTime from total nanoseconds since midnight.
     * $nsTotal must be in [0, NS_PER_DAY).
     */
    private static function fromNs(int $nsTotal): self
    {
        $h = intdiv(num1: $nsTotal, num2: self::NS_PER_HOUR);
        $rem = $nsTotal % self::NS_PER_HOUR;
        $min = intdiv(num1: $rem, num2: self::NS_PER_MINUTE);
        $rem = $rem % self::NS_PER_MINUTE;
        $sec = intdiv(num1: $rem, num2: self::NS_PER_SECOND);
        $rem = $rem % self::NS_PER_SECOND;
        $ms = intdiv(num1: $rem, num2: self::NS_PER_MS);
        $rem = $rem % self::NS_PER_MS;
        $us = intdiv(num1: $rem, num2: self::NS_PER_US);
        $ns = $rem % self::NS_PER_US;
        return new self($h, $min, $sec, $ms, $us, $ns);
    }

    /**
     * Extracts and validates the 'overflow' option from an options bag.
     *
     * Returns 'constrain' or 'reject'. Default is 'constrain'.
     * If $options is null, an empty array, or an object with no overflow key, returns 'constrain'.
     *
     * @param array<array-key, mixed>|object|null $options
     * @throws \TypeError if the overflow value is not a string (or null).
     * @throws InvalidArgumentException if the overflow value is an unrecognized string.
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
        if ($val === null) {
            return 'constrain';
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
     * Parses an ISO 8601 time string (or a full datetime string, discarding the date).
     *
     * Accepted time-only formats:
     *   HH:MM
     *   HH:MM:SS
     *   HH:MM:SS.fraction    (comma or dot; 1–9 digits)
     *   HHMM
     *   HHMMSS
     *   HHMMSS.fraction
     *   HH (hours only)
     *   THH:MM:SS...         (leading T/t stripped)
     *   T/tHHMMSS...         (leading T/t stripped, compact)
     *
     * Full datetime format (date + T + time + optional offset + optional annotations)
     * is also accepted; only the time portion is used.
     *
     * UTC designator 'Z' is REJECTED — strings with a bare 'Z' offset throw.
     *
     * @throws InvalidArgumentException for invalid strings or out-of-range fields.
     */
    private static function fromString(string $s): self
    {
        if ($s === '') {
            throw new InvalidArgumentException('PlainTime::from() received an empty string.');
        }
        // Reject non-ASCII minus sign (U+2212 = \xe2\x88\x92).
        if (str_contains($s, "\u{2212}")) {
            throw new InvalidArgumentException(
                "PlainTime::from() cannot parse \"{$s}\": non-ASCII minus sign is not allowed.",
            );
        }
        // Reject more than 9 fractional-second digits.
        if (preg_match('/[.,]\d{10,}/', $s) === 1) {
            throw new InvalidArgumentException(
                "PlainTime::from() cannot parse \"{$s}\": fractional seconds may have at most 9 digits.",
            );
        }

        // Try full datetime first (YYYY-MM-DDTHH:...) to extract the time portion.
        // Compact date variants: YYYYMMDD, ±YYYYYYMMDD.
        // After the time, an optional UTC offset is allowed — but NOT bare 'Z'.
        // Offset hours restricted to 00-23; minutes/seconds to 00-59.
        $offsetHH = '(?:[01]\d|2[0-3])';
        $offsetMM = '[0-5]\d';
        $offsetSS = '[0-5]\d';
        $offsetPattern = sprintf(
            '[+-]%s(?::%s(?::%s(?:[.,]\d+)?)?|%s(?:%s(?:[.,]\d+)?)?)?',
            $offsetHH,
            $offsetMM,
            $offsetSS,
            $offsetMM,
            $offsetSS,
        );

        $fullDatetimePattern = sprintf(
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})[T ](\d{2}):?(\d{2})(?::?(\d{2})([.,]\d+)?)?(?:Z|%s)?((?:\[[^\]]*\])*)$/i',
            $offsetPattern,
        );

        /** @var list<string> $m */
        $m = [];
        if (preg_match($fullDatetimePattern, $s, $m) === 1) {
            // Reject minus-zero extended year (-000000).
            if ($m[1] === '-000000') {
                throw new InvalidArgumentException(
                    "PlainTime::from() cannot parse \"{$s}\": -000000 year is not allowed.",
                );
            }
            $annotationSection = $m[7] !== '' ? $m[7] : '';
            // Reject if the string has a bare 'Z' UTC designator (before any bracket annotations).
            // A 'Z' immediately followed by '[', end-of-string, or whitespace in the non-annotation part.
            if (preg_match('/Z(?:\[|$)/i', $s) === 1) {
                throw new InvalidArgumentException(
                    "PlainTime::from() cannot parse \"{$s}\": UTC designator 'Z' is not allowed.",
                );
            }
            self::validateAnnotations($annotationSection, $s);

            $hourNum = (int) $m[3];
            $minNum = (int) $m[4];
            $secNum = $m[5] !== '' ? (int) $m[5] : 0;
            // Leap second 60 maps to 59.
            if ($secNum === 60) {
                $secNum = 59;
            }
            $fracRaw = $m[6] !== '' ? $m[6] : '';
            $subNs = $fracRaw !== '' ? self::parseFraction($fracRaw) : 0;

            self::validateFields($hourNum, $minNum, $secNum, 0, 0, 0);

            $totalNs =
                ($hourNum * self::NS_PER_HOUR)
                + ($minNum * self::NS_PER_MINUTE)
                + ($secNum * self::NS_PER_SECOND)
                + $subNs;

            return self::fromNs($totalNs);
        }

        // Try pure time string (with optional T prefix and optional offset/annotations).
        // Strip leading T/t.
        $timeStr = $s;
        if (str_starts_with($timeStr, 'T') || str_starts_with($timeStr, 't')) {
            $timeStr = substr(string: $timeStr, offset: 1);
        }

        // Pattern: HH:MM[:SS[.frac]][offset][annotations]  (colon-separated)
        //       or HHMM[SS[.frac]][offset][annotations]     (compact, no colons)
        //       or HH[offset][annotations]                  (hours only)
        // offset: NOT bare Z; [+-]HH variants only.
        // For pure time strings, Z is also rejected.

        // Check for bare Z before trying to match (reject first).
        // A 'Z' immediately after the time digits (before any bracket) is invalid.
        if (preg_match('/\d[Zz](\[|$)/', $timeStr) === 1 || preg_match('/\d[Zz]$/', $timeStr) === 1) {
            throw new InvalidArgumentException(
                "PlainTime::from() cannot parse \"{$s}\": UTC designator 'Z' is not allowed.",
            );
        }

        // Colon-separated format: HH:MM[:SS[.frac]][offset][annotations]
        $colonTimePattern = sprintf(
            '/^(\d{2}):(\d{2})(?::(\d{2})([.,]\d+)?)?(?:%s)?((?:\[[^\]]*\])*)$/i',
            $offsetPattern,
        );

        // Compact format: HHMMSS[.frac][offset][annotations] or HHMM[offset][annotations] or HH[offset][annotations]
        $compactTimePattern = sprintf('/^(\d{2})(\d{2})?(\d{2})?([.,]\d+)?(?:%s)?((?:\[[^\]]*\])*)$/i', $offsetPattern);

        /** @var list<string> $m2 */
        $m2 = [];
        if (preg_match($colonTimePattern, $timeStr, $m2) === 1) {
            $annotationSection = $m2[5] !== '' ? $m2[5] : '';
            self::validateAnnotations($annotationSection, $s);

            $hourNum = (int) $m2[1];
            $minNum = (int) $m2[2];
            $secNum = $m2[3] !== '' ? (int) $m2[3] : 0;
            if ($secNum === 60) {
                $secNum = 59;
            }
            $fracRaw = $m2[4] !== '' ? $m2[4] : '';
            $subNs = $fracRaw !== '' ? self::parseFraction($fracRaw) : 0;

            self::validateFields($hourNum, $minNum, $secNum, 0, 0, 0);

            $totalNs =
                ($hourNum * self::NS_PER_HOUR)
                + ($minNum * self::NS_PER_MINUTE)
                + ($secNum * self::NS_PER_SECOND)
                + $subNs;

            return self::fromNs($totalNs);
        }

        /** @var list<string> $m3 */
        $m3 = [];
        if (preg_match($compactTimePattern, $timeStr, $m3) === 1) {
            // Compact: m3[1]=HH, m3[2]=MM (or ''), m3[3]=SS (or ''), m3[4]=.frac (or ''), m3[5]=annotations
            $hourNum = (int) $m3[1];
            $minNum = $m3[2] !== '' ? (int) $m3[2] : 0;
            $secNum = $m3[3] !== '' ? (int) $m3[3] : 0;
            if ($secNum === 60) {
                $secNum = 59;
            }
            $fracRaw = $m3[4] !== '' ? $m3[4] : '';
            $subNs = $fracRaw !== '' ? self::parseFraction($fracRaw) : 0;
            $annotationSection = $m3[5] !== '' ? $m3[5] : '';
            self::validateAnnotations($annotationSection, $s);

            // If MM was not provided (hours-only), minNum stays 0; that's fine.
            // Disallow fractional seconds without HHMMSS (e.g., HH.frac or HHMM.frac is invalid).
            if ($m3[3] === '' && $fracRaw !== '') {
                throw new InvalidArgumentException(
                    "PlainTime::from() cannot parse \"{$s}\": invalid ISO 8601 time string.",
                );
            }

            self::validateFields($hourNum, $minNum, $secNum, 0, 0, 0);

            $totalNs =
                ($hourNum * self::NS_PER_HOUR)
                + ($minNum * self::NS_PER_MINUTE)
                + ($secNum * self::NS_PER_SECOND)
                + $subNs;

            return self::fromNs($totalNs);
        }

        throw new InvalidArgumentException("PlainTime::from() cannot parse \"{$s}\": invalid ISO 8601 time string.");
    }

    /**
     * Creates a PlainTime from a property-bag array.
     *
     * Required key: 'hour'. Optional: 'minute', 'second', 'millisecond', 'microsecond', 'nanosecond'.
     *
     * @param array<array-key, mixed> $bag
     * @param string                  $overflow 'constrain' or 'reject'
     * @throws \TypeError if required fields are missing or have wrong type.
     * @throws InvalidArgumentException if field values are out of range (with overflow='reject').
     */
    private static function fromPropertyBag(array $bag, string $overflow): self
    {
        if (!array_key_exists('hour', $bag)) {
            throw new \TypeError('PlainTime property bag must have an hour field.');
        }

        /** @var mixed $hourRaw */
        $hourRaw = $bag['hour'];
        if ($hourRaw === null) {
            throw new \TypeError('PlainTime property bag hour field must not be undefined.');
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $hourRaw)) {
            throw new InvalidArgumentException('PlainTime hour must be finite.');
        }
        /** @phpstan-ignore cast.int */
        $h = is_int($hourRaw) ? $hourRaw : (int) $hourRaw;

        $min = 0;
        if (array_key_exists('minute', $bag)) {
            /** @var mixed $minRaw */
            $minRaw = $bag['minute'];
            if ($minRaw === null) {
                throw new \TypeError('PlainTime property bag minute field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $minRaw)) {
                throw new InvalidArgumentException('PlainTime minute must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $min = is_int($minRaw) ? $minRaw : (int) $minRaw;
        }

        $sec = 0;
        if (array_key_exists('second', $bag)) {
            /** @var mixed $secRaw */
            $secRaw = $bag['second'];
            if ($secRaw === null) {
                throw new \TypeError('PlainTime property bag second field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $secRaw)) {
                throw new InvalidArgumentException('PlainTime second must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $sec = is_int($secRaw) ? $secRaw : (int) $secRaw;
        }

        $ms = 0;
        if (array_key_exists('millisecond', $bag)) {
            /** @var mixed $msRaw */
            $msRaw = $bag['millisecond'];
            if ($msRaw === null) {
                throw new \TypeError('PlainTime property bag millisecond field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $msRaw)) {
                throw new InvalidArgumentException('PlainTime millisecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ms = is_int($msRaw) ? $msRaw : (int) $msRaw;
        }

        $us = 0;
        if (array_key_exists('microsecond', $bag)) {
            /** @var mixed $usRaw */
            $usRaw = $bag['microsecond'];
            if ($usRaw === null) {
                throw new \TypeError('PlainTime property bag microsecond field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $usRaw)) {
                throw new InvalidArgumentException('PlainTime microsecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $us = is_int($usRaw) ? $usRaw : (int) $usRaw;
        }

        $ns = 0;
        if (array_key_exists('nanosecond', $bag)) {
            /** @var mixed $nsRaw */
            $nsRaw = $bag['nanosecond'];
            if ($nsRaw === null) {
                throw new \TypeError('PlainTime property bag nanosecond field must not be undefined.');
            }
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $nsRaw)) {
                throw new InvalidArgumentException('PlainTime nanosecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ns = is_int($nsRaw) ? $nsRaw : (int) $nsRaw;
        }

        if ($overflow === 'constrain') {
            $h = max(0, min(23, $h));
            $min = max(0, min(59, $min));
            $sec = max(0, min(59, $sec));
            $ms = max(0, min(999, $ms));
            $us = max(0, min(999, $us));
            $ns = max(0, min(999, $ns));
        } else {
            self::validateFields($h, $min, $sec, $ms, $us, $ns);
        }

        return new self($h, $min, $sec, $ms, $us, $ns);
    }

    /**
     * Validates all six time component values and throws if any are out of range.
     *
     * @phpstan-assert int<0, 23> $h
     * @phpstan-assert int<0, 59> $min
     * @phpstan-assert int<0, 59> $sec
     * @phpstan-assert int<0, 999> $ms
     * @phpstan-assert int<0, 999> $us
     * @phpstan-assert int<0, 999> $ns
     * @throws InvalidArgumentException if any field is out of its valid range.
     */
    private static function validateFields(int $h, int $min, int $sec, int $ms, int $us, int $ns): void
    {
        if ($h < 0 || $h > 23) {
            throw new InvalidArgumentException("Invalid PlainTime: hour {$h} is out of range 0–23.");
        }
        if ($min < 0 || $min > 59) {
            throw new InvalidArgumentException("Invalid PlainTime: minute {$min} is out of range 0–59.");
        }
        if ($sec < 0 || $sec > 59) {
            throw new InvalidArgumentException("Invalid PlainTime: second {$sec} is out of range 0–59.");
        }
        if ($ms < 0 || $ms > 999) {
            throw new InvalidArgumentException("Invalid PlainTime: millisecond {$ms} is out of range 0–999.");
        }
        if ($us < 0 || $us > 999) {
            throw new InvalidArgumentException("Invalid PlainTime: microsecond {$us} is out of range 0–999.");
        }
        if ($ns < 0 || $ns > 999) {
            throw new InvalidArgumentException("Invalid PlainTime: nanosecond {$ns} is out of range 0–999.");
        }
    }

    /**
     * Validates bracket annotations in a time string.
     *
     * Rejects: uppercase keys, critical unknown annotations, multiple TZ annotations,
     * and sub-minute offset inside TZ annotations.
     *
     * @throws InvalidArgumentException on any violation.
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
     * Parses fractional-second string (".123" or ",123456789") into nanoseconds.
     * Pads or truncates to exactly 9 digits.
     *
     * @return int<0, 999999999>
     */
    private static function parseFraction(string $fractionRaw): int
    {
        $digits = substr(string: $fractionRaw, offset: 1); // strip leading '.' or ','
        /** @var int<0, 999999999> — 9 decimal digits, range 000000000–999999999 */
        $ns = (int) str_pad(substr(string: $digits, offset: 0, length: 9), length: 9, pad_string: '0');
        return $ns;
    }

    /**
     * Adds (sign=+1) or subtracts (sign=-1) Duration time fields to this time,
     * wrapping around the day boundary.
     *
     * Calendar fields (years, months, weeks, days) are ignored per spec.
     * Each time component is reduced modulo its day-count before multiplication to
     * avoid int64 overflow with very large Duration field values.
     */
    private function addTimeFields(int $sign, Duration $d): self
    {
        // Reduce each field modulo its day-cycle count before multiplying, to prevent int64 overflow.
        // NS_PER_DAY / NS_PER_HOUR = 24; / NS_PER_MINUTE = 1440; / NS_PER_SECOND = 86400; etc.
        $hNs = ((int) $d->hours % 24) * self::NS_PER_HOUR;
        $minNs = ((int) $d->minutes % 1_440) * self::NS_PER_MINUTE;
        $secNs = ((int) $d->seconds % 86_400) * self::NS_PER_SECOND;
        $msNs = ((int) $d->milliseconds % 86_400_000) * self::NS_PER_MS;
        $usNs = ((int) $d->microseconds % 86_400_000_000) * self::NS_PER_US;
        $nsNs = (int) $d->nanoseconds % self::NS_PER_DAY;

        // Sum each reduced component (each is in (-NS_PER_DAY, NS_PER_DAY)).
        // Use modular addition step-by-step to stay within int64 range.
        $deltaNs = $hNs + $minNs + $secNs + $msNs + $usNs + $nsNs;

        $resultNs = $this->ns + ($sign * $deltaNs);

        // Wrap to [0, NS_PER_DAY) using true modulo (PHP's % can be negative).
        $resultNs = (($resultNs % self::NS_PER_DAY) + self::NS_PER_DAY) % self::NS_PER_DAY;

        return self::fromNs($resultNs);
    }

    /**
     * Core implementation for since() and until().
     *
     * Balances a nanosecond difference (possibly negative) into a Duration according
     * to largestUnit, then rounds to smallestUnit with the given mode and increment.
     *
     * @param array<array-key, mixed>|object|null $options Options array or null. Keys: largestUnit, smallestUnit, roundingMode, roundingIncrement.
     */
    private static function diffTime(int $diffNs, array|object|null $options): Duration
    {
        /** @var list<string> $validUnits */
        static $validUnits = [
            'auto',
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

        $largestUnit = 'hour'; // default per TC39 for PlainTime
        $smallestUnit = 'nanosecond'; // default: no rounding
        $roundingMode = 'trunc'; // default for since/until
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
                    $largestUnit = match ($lu) {
                        'auto', 'hours' => 'hour',
                        'minutes' => 'minute',
                        'seconds' => 'second',
                        'milliseconds' => 'millisecond',
                        'microseconds' => 'microsecond',
                        'nanoseconds' => 'nanosecond',
                        default => $lu,
                    };
                }
            }

            if (array_key_exists('smallestUnit', $opts)) {
                /** @var mixed $su */
                $su = $opts['smallestUnit'];
                if ($su !== null && !is_string($su)) {
                    throw new \TypeError('smallestUnit option must be a string.');
                }
                if (is_string($su)) {
                    // Valid smallestUnit values for PlainTime (no calendar units).
                    /** @var list<string> $validSmallest */
                    static $validSmallest = [
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
                    if (!in_array($su, $validSmallest, strict: true)) {
                        throw new InvalidArgumentException("Invalid smallestUnit value: \"{$su}\".");
                    }
                    $smallestUnit = match ($su) {
                        'hours' => 'hour',
                        'minutes' => 'minute',
                        'seconds' => 'second',
                        'milliseconds' => 'millisecond',
                        'microseconds' => 'microsecond',
                        'nanoseconds' => 'nanosecond',
                        default => $su,
                    };
                }
            }

            if (array_key_exists('roundingMode', $opts)) {
                /** @var mixed $rm */
                $rm = $opts['roundingMode'];
                if ($rm !== null && !is_string($rm)) {
                    throw new \TypeError('roundingMode option must be a string.');
                }
                if (is_string($rm)) {
                    /** @var list<string> $validModes */
                    static $validModes = [
                        'trunc',
                        'floor',
                        'ceil',
                        'expand',
                        'halfExpand',
                        'halfTrunc',
                        'halfFloor',
                        'halfCeil',
                        'halfEven',
                    ];
                    if (!in_array($rm, $validModes, strict: true)) {
                        throw new InvalidArgumentException("Invalid roundingMode value: \"{$rm}\".");
                    }
                    $roundingMode = $rm;
                }
            }

            if (array_key_exists('roundingIncrement', $opts)) {
                /** @var mixed $ri */
                $ri = $opts['roundingIncrement'];
                if ($ri !== null) {
                    $riFloat = (float) $ri; // @phpstan-ignore cast.double
                    if (!is_finite($riFloat) || $riFloat < 1) {
                        throw new InvalidArgumentException('roundingIncrement must be a finite positive number.');
                    }
                    $roundingIncrement = (int) $riFloat;
                    if ($roundingIncrement < 1) {
                        throw new InvalidArgumentException('roundingIncrement must be at least 1.');
                    }
                }
            }
        }

        // Unit rank: higher = larger unit.
        $unitRank = [
            'hour' => 6,
            'minute' => 5,
            'second' => 4,
            'millisecond' => 3,
            'microsecond' => 2,
            'nanosecond' => 1,
        ];

        $luRank = $unitRank[$largestUnit];
        $suRank = $unitRank[$smallestUnit];

        // largestUnit must be >= smallestUnit.
        if ($luRank < $suRank) {
            throw new InvalidArgumentException(
                "largestUnit \"{$largestUnit}\" must not be smaller than smallestUnit \"{$smallestUnit}\".",
            );
        }

        // Validate roundingIncrement: must evenly divide the next-unit count.
        $maxIncrements = [
            'hour' => 24,
            'minute' => 60,
            'second' => 60,
            'millisecond' => 1_000,
            'microsecond' => 1_000,
            'nanosecond' => 1_000,
        ];
        $maxIncrement = $maxIncrements[$smallestUnit] ?? 1_000_000_000;
        if ($roundingIncrement >= $maxIncrement || ($maxIncrement % $roundingIncrement) !== 0) {
            throw new InvalidArgumentException(
                "roundingIncrement {$roundingIncrement} is invalid for smallestUnit \"{$smallestUnit}\".",
            );
        }

        // ns-per-unit table for rounding.
        $nsPerUnit = [
            'hour' => self::NS_PER_HOUR,
            'minute' => self::NS_PER_MINUTE,
            'second' => self::NS_PER_SECOND,
            'millisecond' => self::NS_PER_MS,
            'microsecond' => self::NS_PER_US,
            'nanosecond' => 1,
        ];

        $sign = $diffNs >= 0 ? 1 : -1;
        $absNs = abs($diffNs);

        // Round diffNs to the nearest multiple of nsIncrement.
        // For floor/ceil/halfFloor/halfCeil, the direction depends on the sign of diffNs.
        $nsIncrement = ($nsPerUnit[$smallestUnit] ?? 1) * $roundingIncrement;
        $roundedAbsNs = self::roundSignedNs($diffNs, $nsIncrement, $roundingMode);
        // roundSignedNs returns a signed value; take abs for balancing, sign already captured.
        unset($absNs); // avoid accidental use
        $roundedAbsNs = abs($roundedAbsNs);

        // Balance the rounded absolute value up to largestUnit.
        $remaining = $roundedAbsNs;

        $hours = 0;
        $minutes = 0;
        $seconds = 0;
        $ms = 0;
        $us = 0;

        if ($luRank >= 6) {
            $hours = intdiv(num1: $remaining, num2: self::NS_PER_HOUR);
            $remaining = $remaining % self::NS_PER_HOUR;
        }
        if ($luRank >= 5) {
            $minutes = intdiv(num1: $remaining, num2: self::NS_PER_MINUTE);
            $remaining = $remaining % self::NS_PER_MINUTE;
        }
        if ($luRank >= 4) {
            $seconds = intdiv(num1: $remaining, num2: self::NS_PER_SECOND);
            $remaining = $remaining % self::NS_PER_SECOND;
        }
        if ($luRank >= 3) {
            $ms = intdiv(num1: $remaining, num2: self::NS_PER_MS);
            $remaining = $remaining % self::NS_PER_MS;
        }
        if ($luRank >= 2) {
            $us = intdiv(num1: $remaining, num2: self::NS_PER_US);
            $remaining = $remaining % self::NS_PER_US;
        }
        $ns = $remaining;

        return new Duration(
            hours: $sign * $hours,
            minutes: $sign * $minutes,
            seconds: $sign * $seconds,
            milliseconds: $sign * $ms,
            microseconds: $sign * $us,
            nanoseconds: $sign * $ns,
        );
    }

    /**
     * Rounds a non-negative nanosecond value to the nearest multiple of $increment
     * using the given rounding mode (standard positive-value rounding).
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
     * Rounds a signed nanosecond diff to the nearest multiple of $increment,
     * correctly handling directional modes (floor, ceil, halfFloor, halfCeil) for
     * negative values.
     *
     * Returns a signed result (may be negative).
     *
     * @throws InvalidArgumentException for unknown rounding modes.
     */
    private static function roundSignedNs(int $ns, int $increment, string $mode): int
    {
        // PHP's intdiv truncates toward zero.
        $q = intdiv(num1: $ns, num2: $increment);
        $rem = $ns - ($q * $increment); // same sign as $ns (or 0)
        $trunc = $q * $increment; // truncated toward zero
        $absRem = abs($rem);

        return match ($mode) {
            'trunc' => $trunc,
            'floor' => $rem < 0 ? $trunc - $increment : $trunc,
            'ceil' => $rem > 0 ? $trunc + $increment : $trunc,
            'expand' => $rem < 0 ? $trunc - $increment : ($rem > 0 ? $trunc + $increment : $trunc),
            // half modes: not at exact midpoint → same as expand/trunc; at midpoint → direction-dependent.
            'halfExpand' => ($absRem * 2) >= $increment
                ? ($ns >= 0 ? $trunc + $increment : $trunc - $increment)
                : $trunc,
            'halfTrunc' => ($absRem * 2) > $increment ? ($ns >= 0 ? $trunc + $increment : $trunc - $increment) : $trunc,
            'halfFloor' => ($absRem * 2) < $increment
                ? $trunc
                : (
                    ($absRem * 2)
                    > $increment
                        ? ($ns >= 0 ? $trunc + $increment : $trunc - $increment)
                        : ($ns >= 0 ? $trunc : $trunc - $increment)
                ), // tie: toward -∞
            'halfCeil' => ($absRem * 2) < $increment
                ? $trunc
                : (
                    ($absRem * 2)
                    > $increment
                        ? ($ns >= 0 ? $trunc + $increment : $trunc - $increment)
                        : ($ns >= 0 ? $trunc + $increment : $trunc)
                ), // tie: toward +∞
            'halfEven' => ($absRem * 2) < $increment
                ? $trunc
                : (
                    ($absRem * 2)
                    > $increment
                        ? ($ns >= 0 ? $trunc + $increment : $trunc - $increment)
                        : (($q % 2) === 0 ? $trunc : ($ns >= 0 ? $trunc + $increment : $trunc - $increment))
                ),
            default => throw new InvalidArgumentException("Invalid roundingMode \"{$mode}\"."),
        };
    }
}
