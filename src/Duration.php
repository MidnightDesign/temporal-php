<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

/**
 * A span of time expressed as 10 calendar and clock fields.
 *
 * All non-zero fields must share the same sign. Calendar fields (years,
 * months, weeks) cannot be converted to nanoseconds without a reference date,
 * so no internal nanosecond total is maintained.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-duration-objects
 */
final class Duration implements Stringable
{
    /**
     * Returns 1 if any field is positive, -1 if any field is negative, 0 if all are zero.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $sign {
        get {
            foreach ($this->fields() as $v) {
                if ($v !== 0) {
                    return $v > 0 ? 1 : -1;
                }
            }
            return 0;
        }
    }

    /**
     * True when all fields are zero.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public bool $blank {
        get => $this->sign === 0;
    }

    /**
     * @throws InvalidArgumentException when fields are out of range or non-zero fields do not all share the same sign.
     */
    public function __construct(
        public readonly int|float $years = 0,
        public readonly int|float $months = 0,
        public readonly int|float $weeks = 0,
        public readonly int|float $days = 0,
        public readonly int|float $hours = 0,
        public readonly int|float $minutes = 0,
        public readonly int|float $seconds = 0,
        public readonly int|float $milliseconds = 0,
        public readonly int|float $microseconds = 0,
        public readonly int|float $nanoseconds = 0,
    ) {
        // TC39 §7.5.10 IsValidDuration — calendar fields capped at 2^32.
        /** @infection-ignore-all GreaterThanOrEqual |x| >= 2^32 vs > 2^32-1 are identical for integers */
        if (
            abs($this->years) >= 4_294_967_296
            || abs($this->months) >= 4_294_967_296
            || abs($this->weeks) >= 4_294_967_296
        ) {
            throw new InvalidArgumentException(
                'Duration years, months, and weeks must each be less than 2^32 in absolute value.',
            );
        }

        // TC39 §7.5.11 IsValidDuration: the combined total of days + time fields must not
        // exceed MaxTimeDuration = 2^53 × 10^9 - 1 nanoseconds.
        //
        // Strategy:
        //   A. Reject if the seconds field alone exceeds MAX_SAFE_INT.
        //   B. When all fields are integers (the common case): use exact integer carry
        //      arithmetic to compute the effective full-seconds total, then check it.
        //      This correctly handles large-ms/µs/ns values that would round to exactly
        //      2^53 in float64 (e.g. seconds=8_998_192_055_486_252 + ms=MAX_SAFE_INT).
        //   C. When any field is a float: use the float total check (with >) which works
        //      for values that are clearly above or below the limit.

        /** @infection-ignore-all */
        $secI = is_int($this->seconds) ? $this->seconds : (int) $this->seconds;
        if ($secI > 9_007_199_254_740_991 || $secI < -9_007_199_254_740_991) {
            throw new InvalidArgumentException('Duration time fields exceed the maximum representable range.');
        }

        if (
            is_int($this->days)
            && is_int($this->hours)
            && is_int($this->minutes)
            && is_int($this->seconds)
            && is_int($this->milliseconds)
            && is_int($this->microseconds)
            && is_int($this->nanoseconds)
        ) {
            // All-integer path: propagate carry ns → µs → ms → s → check full total.
            $carryNs = intdiv($this->nanoseconds, 1_000);
            $usEff = $this->microseconds + $carryNs;
            $carryUs = intdiv($usEff, 1_000);
            $msEff = $this->milliseconds + $carryUs;
            $carryMs = intdiv($msEff, 1_000);
            $sEff = $secI + $carryMs;
            $intSecFull = ($this->days * 86_400) + ($this->hours * 3_600) + ($this->minutes * 60) + $sEff;
            if ($intSecFull > 9_007_199_254_740_991 || $intSecFull < -9_007_199_254_740_991) {
                throw new InvalidArgumentException('Duration time fields exceed the maximum representable range.');
            }
            // At the exact boundary (effective seconds == MAX_SAFE_INT), the remaining
            // sub-second nanoseconds must be < 1 s to stay within MaxTimeDuration.
            if (abs($intSecFull) === 9_007_199_254_740_991) {
                $remNs = $this->nanoseconds - ($carryNs * 1_000);
                $remUs = $usEff - ($carryUs * 1_000);
                $remMs = $msEff - ($carryMs * 1_000);
                $remSubNs = ($remMs * 1_000_000) + ($remUs * 1_000) + $remNs;
                if (abs($remSubNs) >= 1_000_000_000) {
                    throw new InvalidArgumentException('Duration time fields exceed the maximum representable range.');
                }
            }
        } else {
            // Float path: any field is a float (large µs/ns may exceed PHP int64).
            // Use > (not >=) because valid-max durations also round to 2^53 in float64.
            $MAX_SAFE_F = 9_007_199_254_740_992.0; // 2^53 exactly as float64
            $subNs =
                ((float) $this->milliseconds * 1_000_000.0)
                + ((float) $this->microseconds * 1_000.0)
                + (float) $this->nanoseconds;
            $totalSec =
                ((float) $this->days * 86_400.0)
                + ((float) $this->hours * 3_600.0)
                + ((float) $this->minutes * 60.0)
                + (float) $this->seconds
                + ($subNs / 1_000_000_000.0);
            if (abs($totalSec) > $MAX_SAFE_F) {
                throw new InvalidArgumentException('Duration time fields exceed the maximum representable range.');
            }
        }

        $positive = null;
        foreach ($this->fields() as $v) {
            if ($v == 0) {
                continue;
            }
            /** @infection-ignore-all GreaterThan > 0 ≡ >= 0 when $v is guaranteed non-zero (guarded above) */
            $isPositive = $v > 0;
            if ($positive === null) {
                $positive = $isPositive;
                continue;
            }
            if ($positive !== $isPositive) {
                throw new InvalidArgumentException('All non-zero Duration fields must have the same sign.');
            }
        }
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    /**
     * Creates a Duration from an existing Duration, a property-bag array, or an ISO 8601 string.
     *
     * Property-bag example: ['years' => 1, 'hours' => 2]
     * String examples: 'P1Y', 'PT30M', '-P1DT2H', 'PT1.5S', 'PT1,5S', 'PT1.03125H'
     *
     * @param mixed $item Duration, array property bag, or ISO 8601 duration string.
     * @throws InvalidArgumentException if the value cannot be interpreted as a Duration.
     * @throws \TypeError if the type is not Duration, array, or string.
     */
    public static function from(mixed $item): self
    {
        if ($item instanceof self) {
            // TC39 requires a new instance, not the same reference.
            return new self(
                $item->years,
                $item->months,
                $item->weeks,
                $item->days,
                $item->hours,
                $item->minutes,
                $item->seconds,
                $item->milliseconds,
                $item->microseconds,
                $item->nanoseconds,
            );
        }
        if (is_array($item)) {
            return self::parseDurationLike($item);
        }
        if (is_string($item)) {
            return self::fromString($item);
        }
        throw new \TypeError(
            'Duration::from() expects a Duration, ISO 8601 string, or property-bag array; got '
            . get_debug_type($item)
            . '.',
        );
    }

    /**
     * Parses an ISO 8601 duration string.
     *
     * Supported examples:
     *   'P1Y', 'PT30M', '-P1DT2H', 'PT1.5S', 'PT1,5S', 'PT1.03125H', 'P1Y2M3W4DT5H6M7.008009001S'
     *
     * The overall sign prefix (+ or -) applies to all components. Individual
     * component signs are not supported (e.g. 'P-1Y' is invalid per TC39).
     * A decimal fraction may appear only on the last present component (ISO 8601 §5.5.3.5).
     *
     * @throws InvalidArgumentException if the string is not a valid ISO 8601 duration.
     */
    private static function fromString(string $text): self
    {
        /*
         * Regex groups:
         *   1  — overall sign (+ / - / empty)
         *   2  — years     3  — months    4  — weeks    5  — days
         *   6  — hours     7  — hours fraction digits
         *   8  — minutes   9  — minutes fraction digits
         *  10  — seconds  11  — seconds fraction digits
         *
         * The (?=\d) lookahead after T prevents 'P1YT' from matching.
         */
        $pattern =
            '/^([+-])?P'
            . '(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?'
            . '(?:T(?=\d)(?:(\d+)(?:[.,](\d+))?H)?(?:(\d+)(?:[.,](\d+))?M)?(?:(\d+)(?:[.,](\d+))?S)?)?'
            . '$/i';

        // PCRE2 omits optional group captures from the array when their outer
        // optional group never participated.
        /** @var array<string> $m */
        $m = [];
        if (preg_match($pattern, $text, $m) !== 1) {
            throw new InvalidArgumentException("Invalid Duration string \"{$text}\": expected ISO 8601 duration.");
        }

        $hoursFrac = $m[7] ?? '';
        $minutesStr = $m[8] ?? '';
        $minutesFrac = $m[9] ?? '';
        $secondsStr = $m[10] ?? '';
        $secondsFrac = $m[11] ?? '';

        // TC39: seconds fraction must have at most 9 digits.
        if (strlen($secondsFrac) > 9) {
            throw new InvalidArgumentException(
                "Invalid Duration string \"{$text}\": seconds fraction must have at most 9 digits.",
            );
        }

        // ISO 8601: a decimal fraction may appear only on the last present component.
        if ($hoursFrac !== '' && ($minutesStr !== '' || $secondsStr !== '')) {
            throw new InvalidArgumentException(
                "Invalid Duration string \"{$text}\": fraction only allowed on the last component.",
            );
        }
        if ($minutesFrac !== '' && $secondsStr !== '') {
            throw new InvalidArgumentException(
                "Invalid Duration string \"{$text}\": fraction only allowed on the last component.",
            );
        }

        /** @var array<string> $allGroups */
        $allGroups = [
            $m[2] ?? '',
            $m[3] ?? '',
            $m[4] ?? '',
            $m[5] ?? '',
            $m[6] ?? '',
            $hoursFrac,
            $minutesStr,
            $minutesFrac,
            $secondsStr,
            $secondsFrac,
        ];
        if (implode('', $allGroups) === '') {
            throw new InvalidArgumentException("Invalid Duration string \"{$text}\": at least one field is required.");
        }

        // (int)'' === 0, so absent/empty groups naturally become 0.
        // Guard against very large digit strings: PHP's (int) cast silently returns 0 for strings
        // that overflow int64 (e.g. "9"×1000), so check float64 first.
        $safeInt = static function (string $digits): int {
            if ($digits === '') {
                return 0;
            }
            $f = (float) $digits;
            if (!is_finite($f)) {
                throw new InvalidArgumentException('Duration field value is too large (overflows to Infinity).');
            }
            return (int) $digits;
        };

        $years = $safeInt($m[2] ?? '');
        $months = $safeInt($m[3] ?? '');
        $weeks = $safeInt($m[4] ?? '');
        $days = $safeInt($m[5] ?? '');
        $hours = $safeInt($m[6] ?? '');
        $minutes = $safeInt($minutesStr);
        $seconds = $safeInt($secondsStr);

        $milliseconds = 0;
        $microseconds = 0;
        $nanoseconds = 0;

        if ($hoursFrac !== '') {
            // Distribute fractional hours (1 H = 3 600 000 000 000 ns) into smaller units.
            [$dm, $ds, $dms, $dus, $dns] = self::distributeFracNs($hoursFrac, 3_600_000_000_000);
            $minutes += $dm;
            $seconds += $ds;
            $milliseconds = $dms;
            $microseconds = $dus;
            $nanoseconds = $dns;
        } elseif ($minutesFrac !== '') {
            // Distribute fractional minutes (1 M = 60 000 000 000 ns) into smaller units.
            [, $ds, $dms, $dus, $dns] = self::distributeFracNs($minutesFrac, 60_000_000_000);
            $seconds += $ds;
            $milliseconds = $dms;
            $microseconds = $dus;
            $nanoseconds = $dns;
        } elseif ($secondsFrac !== '') {
            /** @infection-ignore-all IncrementInteger length 9→10 is equivalent: str_pad only appends chars, positions 0–8 are identical in both padded strings */
            $frac = str_pad($secondsFrac, length: 9, pad_string: '0');
            $milliseconds = (int) substr($frac, offset: 0, length: 3);
            $microseconds = (int) substr($frac, offset: 3, length: 3);
            $nanoseconds = (int) substr($frac, offset: 6, length: 3);
        }

        /** @infection-ignore-all EqualIdentical === vs == is equivalent for two string operands */
        if (($m[1] ?? '') === '-') {
            return new self(
                -$years,
                -$months,
                -$weeks,
                -$days,
                -$hours,
                -$minutes,
                -$seconds,
                -$milliseconds,
                -$microseconds,
                -$nanoseconds,
            );
        }

        return new self(
            $years,
            $months,
            $weeks,
            $days,
            $hours,
            $minutes,
            $seconds,
            $milliseconds,
            $microseconds,
            $nanoseconds,
        );
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a Duration with all fields negated.
     */
    public function negated(): self
    {
        return new self(
            -$this->years,
            -$this->months,
            -$this->weeks,
            -$this->days,
            -$this->hours,
            -$this->minutes,
            -$this->seconds,
            -$this->milliseconds,
            -$this->microseconds,
            -$this->nanoseconds,
        );
    }

    /**
     * Returns a Duration with all fields made positive.
     */
    public function abs(): self
    {
        return new self(
            abs($this->years),
            abs($this->months),
            abs($this->weeks),
            abs($this->days),
            abs($this->hours),
            abs($this->minutes),
            abs($this->seconds),
            abs($this->milliseconds),
            abs($this->microseconds),
            abs($this->nanoseconds),
        );
    }

    /**
     * Returns true when both Durations have identical field values.
     */
    public function equals(self $other): bool
    {
        return (
            $this->years === $other->years
            && $this->months === $other->months
            && $this->weeks === $other->weeks
            && $this->days === $other->days
            && $this->hours === $other->hours
            && $this->minutes === $other->minutes
            && $this->seconds === $other->seconds
            && $this->milliseconds === $other->milliseconds
            && $this->microseconds === $other->microseconds
            && $this->nanoseconds === $other->nanoseconds
        );
    }

    /**
     * Returns a Duration with the specified fields replaced.
     *
     * @throws \TypeError if $fields is not an array or has no recognized plural Duration field.
     */
    public function with(mixed $fields): self
    {
        if (!is_array($fields)) {
            throw new \TypeError('Duration::with() expects a property-bag array; got ' . get_debug_type($fields) . '.');
        }

        // TC39 ToTemporalPartialDurationRecord: at least one recognized plural field required.
        static $PLURAL_FIELDS = [
            'years',
            'months',
            'weeks',
            'days',
            'hours',
            'minutes',
            'seconds',
            'milliseconds',
            'microseconds',
            'nanoseconds',
        ];
        $hasAny = false;
        foreach ($PLURAL_FIELDS as $f) {
            if (array_key_exists($f, $fields)) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) {
            throw new \TypeError(
                'Duration::with() property bag must contain at least one recognized Duration field '
                . '(years, months, weeks, days, hours, minutes, seconds, milliseconds, microseconds, nanoseconds).',
            );
        }

        return new self(
            $fields['years'] ?? $this->years,
            $fields['months'] ?? $this->months,
            $fields['weeks'] ?? $this->weeks,
            $fields['days'] ?? $this->days,
            $fields['hours'] ?? $this->hours,
            $fields['minutes'] ?? $this->minutes,
            $fields['seconds'] ?? $this->seconds,
            $fields['milliseconds'] ?? $this->milliseconds,
            $fields['microseconds'] ?? $this->microseconds,
            $fields['nanoseconds'] ?? $this->nanoseconds,
        );
    }

    /**
     * Returns an ISO 8601 duration string, with optional rounding/precision options.
     *
     * Options (all optional):
     *   - fractionalSecondDigits: 'auto' (default) | 0–9 | non-integer (floored)
     *   - smallestUnit: 'second[s]'|'millisecond[s]'|'microsecond[s]'|'nanosecond[s]' (overrides fractionalSecondDigits)
     *   - roundingMode: 'trunc' (default) | 'floor' | 'ceil' | 'expand' | 'halfExpand' | 'halfTrunc' | 'halfFloor' | 'halfCeil' | 'halfEven'
     *
     * @param mixed $options null or array of options
     * @throws InvalidArgumentException if options are invalid or rounding causes overflow.
     * @throws \TypeError if $options is not null and not an array.
     */
    public function toString(mixed $options = null): string
    {
        // $digits: null = auto, 0–9 = exact digit count.
        $digits = null;
        $roundingMode = 'trunc';

        if ($options !== null) {
            // TC39: any object (including functions) is a valid options bag; non-recognised
            // properties are silently ignored.  Only throw for non-array, non-object values.
            if (!is_array($options) && !is_object($options)) {
                throw new \TypeError(
                    'Duration::toString() options must be an array or object; got ' . get_debug_type($options) . '.',
                );
            }
            if (!is_array($options)) {
                // Treat non-array objects (e.g. Closures) as empty options bags → all defaults.
                $options = [];
            }

            // fractionalSecondDigits
            if (array_key_exists('fractionalSecondDigits', $options)) {
                $fsd = $options['fractionalSecondDigits'];
                if ($fsd === 'auto') {
                    $digits = null; // keep auto
                } else {
                    if (is_float($fsd)) {
                        if (is_nan($fsd) || is_infinite($fsd)) {
                            throw new InvalidArgumentException(
                                "fractionalSecondDigits must be 'auto' or a finite integer 0–9.",
                            );
                        }
                        $fsd = (int) floor($fsd); // floor (not truncate) for non-integers
                    } elseif (!is_int($fsd)) {
                        throw new InvalidArgumentException("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if ($fsd < 0 || $fsd > 9) {
                        throw new InvalidArgumentException(
                            "fractionalSecondDigits must be between 0 and 9, got {$fsd}.",
                        );
                    }
                    $digits = $fsd;
                }
            }

            // smallestUnit overrides fractionalSecondDigits
            if (array_key_exists('smallestUnit', $options) && $options['smallestUnit'] !== null) {
                $su = (string) $options['smallestUnit'];
                $digits = match ($su) {
                    'second', 'seconds' => 0,
                    'millisecond', 'milliseconds' => 3,
                    'microsecond', 'microseconds' => 6,
                    'nanosecond', 'nanoseconds' => 9,
                    default => throw new InvalidArgumentException(
                        "Invalid smallestUnit \"{$su}\": must be second(s), millisecond(s), microsecond(s), or nanosecond(s).",
                    ),
                };
            }

            // roundingMode
            if (array_key_exists('roundingMode', $options)) {
                $rm = $options['roundingMode'];
                $roundingMode = $rm === null ? 'trunc' : (string) $rm;
            }
        }

        // Early return for blank duration in auto mode.
        if ($this->blank && $digits === null) {
            return 'PT0S';
        }

        $sign = $this->sign;
        $prefix = $sign === -1 ? '-' : '';
        $abs = $this->abs();

        // Compute whole seconds and sub-second nanoseconds from the abs() fields.
        // Use fmod() for the remainder so that large float values (> PHP_INT_MAX) are handled
        // correctly — PHP's % operator converts floats to int via truncation, which overflows
        // for values like 4.5e21 µs. fmod() follows IEEE 754 and gives the exact remainder.
        // Carry = (v - fmod(v, divisor)) / divisor avoids the rounding-up error from v/divisor.
        $remMs = (int) fmod((float) $abs->milliseconds, 1_000.0);
        $carryMs = (int) (((float) $abs->milliseconds - (float) $remMs) / 1_000.0);
        $remUs = (int) fmod((float) $abs->microseconds, 1_000_000.0);
        $carryUs = (int) (((float) $abs->microseconds - (float) $remUs) / 1_000_000.0);
        $remNs = (int) fmod((float) $abs->nanoseconds, 1_000_000_000.0);
        $carryNs = (int) (((float) $abs->nanoseconds - (float) $remNs) / 1_000_000_000.0);

        $subNs = ($remMs * 1_000_000) + ($remUs * 1_000) + $remNs;
        $totalSeconds = (int) $abs->seconds + $carryMs + $carryUs + $carryNs + (int) ($subNs / 1_000_000_000);
        $subNs = (int) ($subNs % 1_000_000_000);

        // Apply rounding and format the fractional seconds string.
        $frac = '';
        if ($digits === null) {
            // auto: retain only significant digits.
            $frac = $subNs !== 0 ? '.' . rtrim(sprintf('%09d', $subNs), characters: '0') : '';
        } else {
            // Exact digit count with rounding.
            [$roundedFrac, $carrySecond] = self::roundSubSecond($subNs, $digits, $roundingMode, $sign);
            $totalSeconds += $carrySecond;

            // Range check: rounding might push totalSeconds beyond TC39's limit (2^53).
            $MAX_SAFE_F = 9_007_199_254_740_992.0;
            $totalSec =
                ((float) $abs->days * 86_400.0)
                + ((float) $abs->hours * 3_600.0)
                + ((float) $abs->minutes * 60.0)
                + (float) $totalSeconds;
            if ($totalSec >= $MAX_SAFE_F) {
                throw new InvalidArgumentException(
                    'Duration total seconds exceed the maximum representable range after rounding.',
                );
            }

            $frac = $digits === 0 ? '' : '.' . sprintf('%0' . $digits . 'd', $roundedFrac);
        }

        $s = $prefix . 'P';

        if ($abs->years !== 0) {
            $s .= $abs->years . 'Y';
        }
        if ($abs->months !== 0) {
            $s .= $abs->months . 'M';
        }
        if ($abs->weeks !== 0) {
            $s .= $abs->weeks . 'W';
        }
        if ($abs->days !== 0) {
            $s .= $abs->days . 'D';
        }

        // With a fixed digit count we always emit the time component (even if zero).
        $hasTime = $digits !== null || $abs->hours !== 0 || $abs->minutes !== 0 || $totalSeconds !== 0 || $subNs !== 0;

        if ($hasTime) {
            $s .= 'T';
            if ($abs->hours !== 0) {
                $s .= $abs->hours . 'H';
            }
            if ($abs->minutes !== 0) {
                $s .= $abs->minutes . 'M';
            }
            // In fixed-digit mode always emit seconds; in auto mode emit only when non-zero.
            if ($digits !== null || $totalSeconds !== 0 || $subNs !== 0) {
                $s .= $totalSeconds . $frac . 'S';
            }
        }

        return $s;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    public function toJSON(): string
    {
        return $this->toString();
    }

    /**
     * Throws TypeError to prevent numeric coercion.
     *
     * @throws \TypeError always.
     * @return never
     */
    public function valueOf(): never
    {
        throw new \TypeError('Use Temporal.Duration.compare() to compare Duration values.');
    }

    /**
     * Returns the total of this duration as a number in the given unit.
     *
     * Returns an int when the result is a whole number, float otherwise.
     *
     * Only works for time-only durations (years/months/weeks = 0) and time units
     * (not years/months/weeks without relativeTo). A relativeTo option in an array
     * bag is inspected; invalid bags throw TypeError, absent required options throw
     * InvalidArgumentException.
     *
     * @param mixed $totalOf Unit string or options bag with 'unit' key.
     * @return int|float
     * @throws InvalidArgumentException if the unit is invalid or unavailable without relativeTo.
     * @throws \TypeError if $totalOf is not a string or array, or if relativeTo is an invalid bag.
     */
    public function total(mixed $totalOf): int|float
    {
        if (!is_string($totalOf) && !is_array($totalOf)) {
            throw new InvalidArgumentException('total() expects a unit string or an options bag array.');
        }

        $unit = is_array($totalOf) ? (string) ($totalOf['unit'] ?? '') : $totalOf;
        $unit = self::normalizeUnit($unit);

        if ($unit === 'years' || $unit === 'months' || $unit === 'weeks') {
            if (!is_array($totalOf) || !array_key_exists('relativeTo', $totalOf)) {
                throw new InvalidArgumentException("total() with unit \"{$unit}\" requires a relativeTo option.");
            }
            $rt = $totalOf['relativeTo'];
            // String relativeTo: parse as ISO PlainDate string.
            if (is_string($rt)) {
                $rt = $this->parseRelativeToString($rt);
            }
            if (!is_array($rt)) {
                throw new \TypeError('relativeTo must be a string or property bag.');
            }
            // Both 'month' and 'monthCode' are valid month specifiers per TC39.
            $hasYear = array_key_exists('year', $rt);
            $hasMonth = array_key_exists('month', $rt) || array_key_exists('monthCode', $rt);
            $hasDay = array_key_exists('day', $rt);
            if (!$hasYear || !$hasMonth || !$hasDay) {
                throw new \TypeError('relativeTo property bag must have year, month/monthCode, and day fields.');
            }
            // Validate calendar: only ISO 8601 is supported.
            if (array_key_exists('calendar', $rt)) {
                $cal = strtolower((string) $rt['calendar']);
                if ($cal !== 'iso8601') {
                    throw new InvalidArgumentException(
                        "Unsupported calendar \"{$rt['calendar']}\"; only iso8601 is supported.",
                    );
                }
            }
            return $this->totalCalendar($unit, $rt);
        }

        if ($this->years !== 0 || $this->months !== 0 || $this->weeks !== 0) {
            throw new InvalidArgumentException(
                'total() on a duration with years, months, or weeks requires a relativeTo option.',
            );
        }

        // Compute in seconds. Combine sub-second fields into nanoseconds first, then divide
        // by 1e9 once — this avoids accumulated float64 rounding error from separate divisions
        // (e.g. 2ms/1000 + 31µs/1e6 gives 0.0020310000000000003 instead of 0.002031).
        $subNs =
            ((float) $this->milliseconds * 1_000_000.0)
            + ((float) $this->microseconds * 1_000.0)
            + (float) $this->nanoseconds;
        $totalSec =
            ((float) $this->days * 86_400.0)
            + ((float) $this->hours * 3_600.0)
            + ((float) $this->minutes * 60.0)
            + (float) $this->seconds
            + ($subNs / 1_000_000_000.0);

        $result = match ($unit) {
            'days' => $totalSec / 86_400.0,
            'hours' => $totalSec / 3_600.0,
            'minutes' => $totalSec / 60.0,
            'seconds' => $totalSec,
            'milliseconds' => $totalSec * 1_000.0,
            'microseconds' => $totalSec * 1_000_000.0,
            'nanoseconds' => $totalSec * 1_000_000_000.0,
            default => throw new InvalidArgumentException("Unhandled unit: \"{$unit}\"."),
        };

        // Return int when the result is a whole number (matches JS behavior where
        // e.g. 24 hours total('hours') is 24, not 24.0).
        return self::toIntIfWhole($result);
    }

    /**
     * Parses a relativeTo ISO date string into a property bag.
     * Rejects invalid formats, fractional minutes/hours, unknown calendars, and
     * sub-minute bracket offsets (TC39 §10.6.7 ToTemporalDate).
     *
     * @return array<string,int> Bag with 'year', 'month', 'day' keys.
     * @throws InvalidArgumentException for invalid or unsupported strings.
     */
    private function parseRelativeToString(string $s): array
    {
        if ($s === '') {
            throw new InvalidArgumentException('relativeTo string must not be empty.');
        }
        // Fractional hours: T12.5 or fractional minutes: T12:34.5 are not allowed.
        if (preg_match('/T\d{2}\.\d/', $s) === 1 || preg_match('/T\d{2}:\d{2}\.\d{1,3}(?:Z|[+\-\[]|$)/i', $s) === 1) {
            throw new InvalidArgumentException('relativeTo string must not have fractional hours or minutes.');
        }
        // Validate calendar annotation.
        if (preg_match('/\[u-ca=([^\]]+)\]/', $s, $calMatch) === 1) {
            if (strtolower($calMatch[1]) !== 'iso8601') {
                throw new InvalidArgumentException(
                    "Unsupported calendar \"{$calMatch[1]}\"; only iso8601 is supported.",
                );
            }
        }
        // Validate bracket annotation: sub-minute offsets like [-00:44:30] are invalid.
        if (
            preg_match('/\[([^\]]+)\]/', $s, $bracketMatch) === 1
            && !str_starts_with($bracketMatch[1], 'u-ca=')
            && preg_match('/^[+\-]\d{2}:\d{2}:\d{2}/', $bracketMatch[1]) === 1
        ) {
            throw new InvalidArgumentException(
                'relativeTo string must not have sub-minute offset in bracket annotation.',
            );
        }
        // Extract date part: ±YYYY-MM-DD or YYYYMMDD.
        if (
            preg_match('/^([+\-]?\d{4,6})-(\d{2})-(\d{2})/', $s, $dateMatch) !== 1
            && preg_match('/^(\d{4})(\d{2})(\d{2})/', $s, $dateMatch) !== 1
        ) {
            throw new InvalidArgumentException("Invalid relativeTo date string \"{$s}\".");
        }
        return ['year' => (int) $dateMatch[1], 'month' => (int) $dateMatch[2], 'day' => (int) $dateMatch[3]];
    }

    /**
     * Implements total() for calendar units (years/months/weeks) given an ISO PlainDate
     * relativeTo bag. Unknown keys in the bag are silently ignored per TC39.
     *
     * @param array<array-key,mixed> $relativeTo Validated plain-date property bag.
     */
    private function totalCalendar(string $unit, array $relativeTo): int|float
    {
        $year = (int) $relativeTo['year'];
        $month = array_key_exists('month', $relativeTo)
            ? (int) $relativeTo['month']
            : (int) substr(string: (string) $relativeTo['monthCode'], offset: 1);
        $day = (int) $relativeTo['day'];

        $tz = new \DateTimeZone('UTC');
        $start = new \DateTimeImmutable('now', $tz)
            ->setDate($year, $month, $day)
            ->setTime(0, 0, 0);

        // Move start forward by this duration's calendar fields.
        if ((int) $this->years !== 0 || (int) $this->months !== 0 || (int) $this->weeks !== 0) {
            $ay = abs((int) $this->years);
            $am = abs((int) $this->months);
            $aw = abs((int) $this->weeks);
            $di = new \DateInterval("P{$ay}Y{$am}M{$aw}W");
            $start = $this->sign >= 0 ? $start->add($di) : $start->sub($di);
        }

        // Convert remaining fields to whole days + sub-day nanoseconds.
        $nsPerDay = 86_400_000_000_000;
        $totalNs =
            ((int) $this->days * $nsPerDay)
            + ((int) $this->hours * 3_600_000_000_000)
            + ((int) $this->minutes * 60_000_000_000)
            + ((int) $this->seconds * 1_000_000_000)
            + ((int) $this->milliseconds * 1_000_000)
            + ((int) $this->microseconds * 1_000)
            + (int) $this->nanoseconds;
        $wholeDays = intdiv($totalNs, $nsPerDay);
        $fracNs = $totalNs % $nsPerDay;

        return match ($unit) {
            'months' => $this->totalCalendarMonths($start, $wholeDays, $fracNs, $nsPerDay),
            'weeks' => self::toIntIfWhole(((float) $wholeDays + ($fracNs / (float) $nsPerDay)) / 7.0),
            'years' => $this->totalCalendarYears($start, $wholeDays, $fracNs, $nsPerDay),
            default => throw new InvalidArgumentException("Unhandled calendar unit: \"{$unit}\"."),
        };
    }

    /**
     * Counts fractional months from $start spanning $wholeDays days + $fracNs nanoseconds.
     * Implements TC39 RoundDuration for unit = "months".
     */
    private function totalCalendarMonths(
        \DateTimeImmutable $start,
        int $wholeDays,
        int $fracNs,
        int $nsPerDay,
    ): int|float {
        $absWholeDays = abs($wholeDays);
        $dir = $wholeDays >= 0 ? '+' : '-';
        $sign = $wholeDays >= 0 ? 1 : -1;
        $end = $start->modify("{$dir}{$absWholeDays} days");

        $months = 0;
        $current = $start;
        while (true) {
            $next = $current->modify("{$dir}1 month");
            if ($sign > 0 ? $next > $end : $next < $end) {
                break;
            }
            $months++;
            $current = $next;
        }

        $remainingDays = (int) $current->diff($end)->days;
        $daysInNextMonth = (int) $current->diff($current->modify("{$dir}1 month"))->days;
        $result =
            (float) ($months * $sign)
            + ((float) ($sign * $remainingDays) / (float) $daysInNextMonth)
            + ((float) ($sign * $fracNs) / ((float) $nsPerDay * (float) $daysInNextMonth));

        return self::toIntIfWhole($result);
    }

    /**
     * Counts fractional years from $start spanning $wholeDays days + $fracNs nanoseconds.
     * Implements TC39 RoundDuration for unit = "years".
     */
    private function totalCalendarYears(
        \DateTimeImmutable $start,
        int $wholeDays,
        int $fracNs,
        int $nsPerDay,
    ): int|float {
        $absWholeDays = abs($wholeDays);
        $dir = $wholeDays >= 0 ? '+' : '-';
        $sign = $wholeDays >= 0 ? 1 : -1;
        $end = $start->modify("{$dir}{$absWholeDays} days");

        $years = 0;
        $current = $start;
        while (true) {
            $next = $current->modify("{$dir}1 year");
            if ($sign > 0 ? $next > $end : $next < $end) {
                break;
            }
            $years++;
            $current = $next;
        }

        $remainingDays = (int) $current->diff($end)->days;
        $daysInNextYear = (int) $current->diff($current->modify("{$dir}1 year"))->days;
        $result =
            (float) ($years * $sign)
            + ((float) ($sign * $remainingDays) / (float) $daysInNextYear)
            + ((float) ($sign * $fracNs) / ((float) $nsPerDay * (float) $daysInNextYear));

        return self::toIntIfWhole($result);
    }

    private static function toIntIfWhole(float $result): int|float
    {
        return fmod($result, 1.0) === 0.0 ? (int) $result : $result;
    }

    /**
     * Returns the sum of this duration and another.
     *
     * Both durations must be free of calendar fields (years/months/weeks). The
     * result is balanced: sub-second carries are propagated upward to the largest
     * unit present in either operand. Uses integer arithmetic for exact results.
     *
     * @param mixed $other Duration, ISO 8601 string, or property-bag array.
     * @throws InvalidArgumentException if either duration has calendar fields or the result is out of range.
     * @throws \TypeError if $other is not a Duration, string, or array.
     */
    public function add(mixed $other): self
    {
        $other = self::from($other);

        if (
            $this->years !== 0
            || $this->months !== 0
            || $this->weeks !== 0
            || $other->years !== 0
            || $other->months !== 0
            || $other->weeks !== 0
        ) {
            throw new InvalidArgumentException('add() with years, months, or weeks requires a relativeTo option.');
        }

        // Determine the largest unit present in either duration.
        $rank = 0;
        if ($this->days !== 0 || $other->days !== 0) {
            $rank = 6;
        } elseif ($this->hours !== 0 || $other->hours !== 0) {
            $rank = 5;
        } elseif ($this->minutes !== 0 || $other->minutes !== 0) {
            $rank = 4;
        } elseif ($this->seconds !== 0 || $other->seconds !== 0) {
            $rank = 3;
        } elseif ($this->milliseconds !== 0 || $other->milliseconds !== 0) {
            $rank = 2;
        } elseif ($this->microseconds !== 0 || $other->microseconds !== 0) {
            $rank = 1;
        }

        // Sum each field. PHP promotes int+int to float on overflow; tdivmod handles both.
        $d = $this->days + $other->days;
        $h = $this->hours + $other->hours;
        $min = $this->minutes + $other->minutes;
        $s = $this->seconds + $other->seconds;
        $ms = $this->milliseconds + $other->milliseconds;
        $us = $this->microseconds + $other->microseconds;
        $ns = $this->nanoseconds + $other->nanoseconds;

        return self::balanceTimeFields($d, $h, $min, $s, $ms, $us, $ns, $rank);
    }

    /**
     * Returns the difference of this duration and another (equivalent to adding the negation).
     *
     * @param mixed $other Duration, ISO 8601 string, or property-bag array.
     * @throws InvalidArgumentException if either duration has calendar fields.
     * @throws \TypeError if $other is not a Duration, string, or array.
     */
    public function subtract(mixed $other): self
    {
        return $this->add(self::from($other)->negated());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns all 10 field values in declaration order.
     *
     * @return list<int|float>
     */
    private function fields(): array
    {
        return [
            $this->years,
            $this->months,
            $this->weeks,
            $this->days,
            $this->hours,
            $this->minutes,
            $this->seconds,
            $this->milliseconds,
            $this->microseconds,
            $this->nanoseconds,
        ];
    }

    /**
     * Distributes a decimal fraction of a time unit into smaller units.
     *
     * Uses float64 arithmetic (same precision as JS) to match TC39 test262 expected values.
     *
     * @param string $fracDigits Fractional digits without the decimal point (e.g. "5" for 0.5 hours)
     * @param int    $nsPerUnit  Nanoseconds per whole unit (3_600_000_000_000 for hours, 60_000_000_000 for minutes)
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int} [extra_minutes, seconds, milliseconds, microseconds, nanoseconds]
     */
    private static function distributeFracNs(string $fracDigits, int $nsPerUnit): array
    {
        $totalFracNs = (int) round((float) ('0.' . $fracDigits) * (float) $nsPerUnit);

        $dm = intdiv(num1: $totalFracNs, num2: 60_000_000_000);
        $rem = $totalFracNs % 60_000_000_000;
        $ds = intdiv(num1: $rem, num2: 1_000_000_000);
        $rem = $rem % 1_000_000_000;
        $dms = intdiv(num1: $rem, num2: 1_000_000);
        $rem = $rem % 1_000_000;
        $dus = intdiv(num1: $rem, num2: 1_000);
        $dns = $rem % 1_000;

        return [$dm, $ds, $dms, $dus, $dns];
    }

    /**
     * Parses a property-bag array into a Duration.
     *
     * TC39 ToTemporalPartialDurationRecord semantics:
     *  - At least one recognized plural field required (TypeError if none).
     *  - Each provided numeric value must be a finite, integer-valued number
     *    (InvalidArgumentException if not).
     *
     * @param array<string, mixed> $item
     */
    private static function parseDurationLike(array $item): self
    {
        static $PLURAL_FIELDS = [
            'years',
            'months',
            'weeks',
            'days',
            'hours',
            'minutes',
            'seconds',
            'milliseconds',
            'microseconds',
            'nanoseconds',
        ];

        $hasAny = false;
        foreach ($PLURAL_FIELDS as $f) {
            if (array_key_exists($f, $item)) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) {
            throw new \TypeError(
                'Duration property bag must contain at least one recognized field '
                . '(years, months, weeks, days, hours, minutes, seconds, milliseconds, microseconds, nanoseconds).',
            );
        }

        // Validate and extract each field.
        $values = [];
        foreach ($PLURAL_FIELDS as $field) {
            $v = $item[$field] ?? 0;
            if (is_float($v)) {
                if (is_nan($v) || is_infinite($v)) {
                    throw new InvalidArgumentException("Duration field \"{$field}\" must be a finite integer.");
                }
                if (fmod($v, 1.0) !== 0.0) {
                    throw new InvalidArgumentException(
                        "Duration field \"{$field}\" must be an integer, got non-integer {$v}.",
                    );
                }
            }
            // Keep large floats (> PHP_INT_MAX) as float; cast the rest to int.
            // Values within int64 range are cast for exact integer semantics.
            $values[] = is_float($v) && abs($v) >= (float) PHP_INT_MAX ? $v : (int) $v;
        }

        return new self(...$values);
    }

    /**
     * Normalises a singular or plural Temporal unit name to its canonical plural form.
     *
     * @throws InvalidArgumentException for unknown unit names.
     */
    private static function normalizeUnit(string $unit): string
    {
        return match ($unit) {
            'year', 'years' => 'years',
            'month', 'months' => 'months',
            'week', 'weeks' => 'weeks',
            'day', 'days' => 'days',
            'hour', 'hours' => 'hours',
            'minute', 'minutes' => 'minutes',
            'second', 'seconds' => 'seconds',
            'millisecond', 'milliseconds' => 'milliseconds',
            'microsecond', 'microseconds' => 'microseconds',
            'nanosecond', 'nanoseconds' => 'nanoseconds',
            default => throw new InvalidArgumentException("Unknown duration unit: \"{$unit}\"."),
        };
    }

    /**
     * Truncating integer division with remainder (modulo), handling both int and float.
     *
     * For int inputs uses PHP's intdiv/%. For float inputs (which arise when PHP
     * auto-promotes int+int overflow to float) uses (int) cast for truncation.
     * When the quotient exceeds PHP_INT_MAX (e.g. ns ≈ 1e25 / 1000 ≈ 1e22), returns
     * a float quotient — the range check in balanceTimeFields() will catch it.
     *
     * When |n| > PHP_INT_MAX but the quotient fits in int64, float64 division may
     * round the quotient incorrectly. In that case we use exact decimal long-division
     * via sprintf('%.0f'), which gives the exact integer string for large floats.
     *
     * @return array{0: int|float, 1: int} [quotient, remainder]
     */
    private static function tdivmod(int|float $n, int $divisor): array
    {
        if (is_int($n)) {
            return [intdiv($n, $divisor), $n % $divisor];
        }
        // Float path.
        $fq = $n / (float) $divisor;
        $floatMax = (float) PHP_INT_MAX;
        // Guard against int overflow when the quotient exceeds int64 range.
        if (abs($fq) >= $floatMax) {
            // Return float quotient; the remainder can still be extracted via fmod.
            return [$fq, (int) fmod($n, (float) $divisor)];
        }
        // When |n| itself exceeds int64 range, (int)($n/$divisor) can round the
        // quotient incorrectly (float64 loses ~19 decimal digits of precision).
        // Use exact string-based long-division: sprintf('%.0f') gives the exact
        // decimal representation of integer-valued floats.
        if (abs($n) >= $floatMax) {
            $sign = $n < 0.0 ? -1 : 1;
            $absStr = sprintf('%.0f', abs($n));
            $q = 0;
            $rem = 0;
            $len = strlen($absStr);
            for ($i = 0; $i < $len; $i++) {
                $rem = ($rem * 10) + (int) $absStr[$i];
                $q = ($q * 10) + intdiv($rem, $divisor);
                $rem %= $divisor;
            }
            return [$sign * $q, $sign * $rem];
        }
        $q = (int) $fq;
        $r = (int) round($n - ((float) $q * (float) $divisor));
        return [$q, $r];
    }

    /**
     * Balances a set of time field sums.
     *
     * Uses bottom-up integer carry (ns → µs → ms → s → min → h → days, stopping at `$rank`).
     * When the result has mixed signs (a cross-field borrow that integer carry cannot resolve),
     * falls back to float totalNs + top-down truncating distribution (TC39 BalanceTimeDuration).
     * Applies float64 rounding ((int)(float)) to each result field to match JS Number storage.
     *
     * @param int|float $d   Sum of days fields.
     * @param int|float $h   Sum of hours fields.
     * @param int|float $min Sum of minutes fields.
     * @param int|float $s   Sum of seconds fields.
     * @param int|float $ms  Sum of milliseconds fields.
     * @param int|float $us  Sum of microseconds fields.
     * @param int|float $ns  Sum of nanoseconds fields.
     * @param int       $rank  Largest unit rank (6=days, 5=hours, 4=minutes, 3=seconds, 2=ms, 1=µs, 0=ns).
     */
    private static function balanceTimeFields(
        int|float $d,
        int|float $h,
        int|float $min,
        int|float $s,
        int|float $ms,
        int|float $us,
        int|float $ns,
        int $rank,
    ): self {
        // Save originals for float fallback (used when integer carry leaves mixed signs).
        [$d0, $h0, $min0, $s0, $ms0, $us0, $ns0] = [$d, $h, $min, $s, $ms, $us, $ns];

        // Bottom-up integer carry.
        [$carryUs, $ns] = self::tdivmod($ns, 1_000);
        $us += $carryUs;
        if ($rank >= 2) {
            [$carryMs, $us] = self::tdivmod($us, 1_000);
            $ms += $carryMs;
        }
        if ($rank >= 3) {
            [$carryS, $ms] = self::tdivmod($ms, 1_000);
            $s += $carryS;
        }
        if ($rank >= 4) {
            [$carryMin, $s] = self::tdivmod($s, 60);
            $min += $carryMin;
        }
        if ($rank >= 5) {
            [$carryH, $min] = self::tdivmod($min, 60);
            $h += $carryH;
        }
        if ($rank >= 6) {
            [$carryD, $h] = self::tdivmod($h, 24);
            $d += $carryD;
        }

        // Detect mixed signs after integer carry.  Cross-field borrows (e.g. h=-1, min=+1)
        // are not resolved by bottom-up carry; the float path handles them correctly.
        $hasPos = $hasNeg = false;
        foreach ([$d, $h, $min, $s, $ms, $us, $ns] as $fv) {
            if ($fv > 0) {
                $hasPos = true;
            } elseif ($fv < 0) {
                $hasNeg = true;
            }
            if ($hasPos && $hasNeg) {
                break;
            }
        }

        $MAX_SAFE_F = 9_007_199_254_740_992.0;

        if ($hasPos && $hasNeg) {
            // Float totalNs + top-down truncating distribution (TC39 BalanceTimeDuration).
            if ($rank >= 6) {
                // Include days in the total.
                $totalNs =
                    ((float) $d0 * 86_400_000_000_000.0)
                    + ((float) $h0 * 3_600_000_000_000.0)
                    + ((float) $min0 * 60_000_000_000.0)
                    + ((float) $s0 * 1_000_000_000.0)
                    + ((float) $ms0 * 1_000_000.0)
                    + ((float) $us0 * 1_000.0)
                    + (float) $ns0;
                $d = (int) ($totalNs / 86_400_000_000_000.0);
                $totalNs -= (float) $d * 86_400_000_000_000.0;
            } else {
                $d = $d0; // Days unchanged when rank < 6.
                $totalNs =
                    ((float) $h0 * 3_600_000_000_000.0)
                    + ((float) $min0 * 60_000_000_000.0)
                    + ((float) $s0 * 1_000_000_000.0)
                    + ((float) $ms0 * 1_000_000.0)
                    + ((float) $us0 * 1_000.0)
                    + (float) $ns0;
            }

            $h = (int) ($totalNs / 3_600_000_000_000.0);
            $totalNs -= (float) $h * 3_600_000_000_000.0;
            $min = (int) ($totalNs / 60_000_000_000.0);
            $totalNs -= (float) $min * 60_000_000_000.0;
            $s = (int) ($totalNs / 1_000_000_000.0);
            $totalNs -= (float) $s * 1_000_000_000.0;
            $ms = (int) ($totalNs / 1_000_000.0);
            $totalNs -= (float) $ms * 1_000_000.0;
            $us = (int) ($totalNs / 1_000.0);
            $totalNs -= (float) $us * 1_000.0;
            $ns = (int) $totalNs;
        } else {
            // Same-sign path: apply float64 rounding to match JS Number field storage.
            // JS stores all numbers as float64; integer operations > 2^53 lose precision.
            // We must simulate this by converting int→float64→int even for PHP ints,
            // so that e.g. (9007199254740991 + 9007199254740990) = 18014398509481980 (float64)
            // rather than 18014398509481981 (exact PHP int).
            // Guard against overflow: values that don't fit in int64 remain as float.
            $floatMax = (float) PHP_INT_MAX;
            $roundF64 = static function (int|float $v) use ($floatMax): int|float {
                $fv = (float) $v;
                return abs($fv) < $floatMax ? (int) $fv : $fv;
            };
            $d = $roundF64($d);
            $h = $roundF64($h);
            $min = $roundF64($min);
            $s = $roundF64($s);
            $ms = $roundF64($ms);
            $us = $roundF64($us);
            $ns = $roundF64($ns);
        }

        // TC39 range check: total seconds must not exceed MAX_SAFE_INT.
        $totalSec = ((float) $d * 86_400.0) + ((float) $h * 3_600.0) + ((float) $min * 60.0) + (float) $s;
        if ($rank < 3) {
            $totalSec += ((float) $ms / 1_000.0) + ((float) $us / 1_000_000.0) + ((float) $ns / 1_000_000_000.0);
        }
        if (abs($totalSec) >= $MAX_SAFE_F) {
            throw new InvalidArgumentException('Duration time fields exceed the maximum representable range.');
        }

        return new self(0, 0, 0, (int) $d, (int) $h, (int) $min, (int) $s, (int) $ms, (int) $us, (int) $ns);
    }

    /**
     * Rounds/truncates sub-second nanoseconds to the given number of decimal digits.
     *
     * For negative durations the rounding direction is inverted (floor ↔ expand).
     *
     * @param int    $subNs       Sub-second nanoseconds (0–999_999_999).
     * @param int    $digits      Number of fractional seconds digits (0–9).
     * @param string $roundingMode TC39 rounding mode name.
     * @param int    $sign        Duration sign (1 or -1; 0 treated as 1).
     * @return array{0: int, 1: int} [roundedFrac, carrySecond]
     *   $roundedFrac: the integer to format as $digits decimal digits (0 when $digits=0).
     *   $carrySecond: 0 or 1, to add to the whole-seconds total.
     */
    private static function roundSubSecond(int $subNs, int $digits, string $roundingMode, int $sign): array
    {
        if ($digits === 0) {
            $carry = self::applyRounding($subNs, 1_000_000_000, $roundingMode, 0, $sign);
            return [0, $carry];
        }

        $unitNs = (int) round(10 ** (9 - $digits));
        $quotient = intdiv(num1: $subNs, num2: $unitNs);
        $remainder = $subNs % $unitNs;
        $carry = self::applyRounding($remainder, $unitNs, $roundingMode, $quotient, $sign);
        $rounded = $quotient + $carry;

        $maxFrac = (int) round(10 ** $digits);
        if ($rounded >= $maxFrac) {
            return [0, 1]; // overflow into next second
        }
        return [$rounded, 0];
    }

    /**
     * Determines the increment (0 or 1) to add to the quotient when rounding.
     *
     * @param int    $remainder   Fractional part (0 ≤ remainder < $unitNs).
     * @param int    $unitNs      Size of the rounding unit in nanoseconds.
     * @param string $mode        TC39 rounding mode.
     * @param int    $quotient    Truncated quotient (used by halfEven).
     * @param int    $sign        Duration sign (1 or -1).
     */
    private static function applyRounding(int $remainder, int $unitNs, string $mode, int $quotient, int $sign): int
    {
        if ($remainder === 0) {
            return 0;
        }
        $positive = $sign >= 0;
        return match ($mode) {
            // Toward zero
            'trunc', 'truncate' => 0,
            // Floor = toward -∞: expand for negative, trunc for positive.
            'floor' => $positive ? 0 : 1,
            // Ceil = toward +∞: expand for positive, trunc for negative.
            'ceil', 'ceiling' => $positive ? 1 : 0,
            // Always away from zero.
            'expand' => 1,
            // Half away from zero (standard rounding).
            'halfExpand' => ($remainder * 2) >= $unitNs ? 1 : 0,
            // Half toward zero.
            'halfTrunc' => ($remainder * 2) > $unitNs ? 1 : 0,
            // Half toward -∞.
            'halfFloor' => $positive ? (($remainder * 2) > $unitNs ? 1 : 0) : (($remainder * 2) >= $unitNs ? 1 : 0),
            // Half toward +∞.
            'halfCeil' => $positive ? (($remainder * 2) >= $unitNs ? 1 : 0) : (($remainder * 2) > $unitNs ? 1 : 0),
            // Half to even.
            'halfEven' => self::halfEvenRound($remainder, $unitNs, $quotient),
            default => throw new InvalidArgumentException("Unknown rounding mode \"{$mode}\"."),
        };
    }

    /**
     * Half-to-even (banker's rounding) helper.
     *
     * @param int $remainder 0 ≤ remainder < $unitNs.
     * @param int $unitNs    Size of the rounding unit.
     * @param int $quotient  Truncated quotient (to check parity).
     */
    private static function halfEvenRound(int $remainder, int $unitNs, int $quotient): int
    {
        $double = $remainder * 2;
        if ($double < $unitNs) {
            return 0;
        }
        if ($double > $unitNs) {
            return 1;
        }
        // Exactly half — round to even.
        return ($quotient % 2) !== 0 ? 1 : 0;
    }
}
