<?php

declare(strict_types=1);

namespace Temporal\Spec;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;
use Temporal\Spec\Internal\CalendarMath;

/**
 * A fixed point in time with nanosecond precision.
 *
 * Stores the number of nanoseconds since the Unix epoch (1970-01-01T00:00:00Z)
 * as a 64-bit integer, giving a practical range of approximately 1677–2262.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-instant-objects
 */
final class Instant implements Stringable
{
    private const int NS_PER_SECOND = 1_000_000_000;
    private const int NS_PER_MILLISECOND = 1_000_000;

    /**
     * Milliseconds since the Unix epoch (floor-divided from nanoseconds).
     *
     * Unlike the JS spec, which returns a Number, PHP returns int since a
     * 64-bit integer has sufficient range for all practical timestamps.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $epochMilliseconds {
        get => CalendarMath::floorDiv($this->epochNanoseconds, self::NS_PER_MILLISECOND);
    }

    /** @psalm-suppress PropertyNotSetInConstructor — set unconditionally in constructor */
    public readonly int $epochNanoseconds;

    /**
     * @param int|float $epochNanoseconds Nanoseconds since the Unix epoch.
     *        Must be a finite integer value within the PHP int64 range.
     *        Finite float values representing out-of-range integers throw InvalidArgumentException.
     * @throws InvalidArgumentException if a float value is not a finite integer or exceeds int64.
     */
    public function __construct(int|float $epochNanoseconds)
    {
        if (is_float($epochNanoseconds)) {
            if (!is_finite($epochNanoseconds) || floor($epochNanoseconds) !== $epochNanoseconds) {
                throw new InvalidArgumentException('epochNanoseconds must be a finite integer value.');
            }
            // (float) PHP_INT_MAX rounds up to 2^63 > PHP_INT_MAX due to float64 precision.
            // Any float larger than 9.2e18 is outside int64 range and thus outside the spec range.
            if ($epochNanoseconds > (float) PHP_INT_MAX || $epochNanoseconds < (float) PHP_INT_MIN) {
                throw new InvalidArgumentException('epochNanoseconds value exceeds the PHP int64 range.');
            }
            $epochNanoseconds = (int) $epochNanoseconds;
        }
        $this->epochNanoseconds = $epochNanoseconds;
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    /**
     * Parses an ISO 8601 / RFC 3339 date-time string that includes a UTC offset.
     *
     * Supported formats (non-exhaustive):
     *   '2020-01-01T00:00:00Z'
     *   '2020-01-01T00:00:00+05:30'
     *   '2020-01-01T00:00:00.123456789Z'
     *   '2020-01-01T15:23Z'                                    (seconds optional)
     *   '1976-11-18T15:23:30,12Z'                              (comma as decimal separator)
     *   '19761118T152330Z'                                     (compact date + compact time)
     *   '1976-11-18T15:23:30+0530'                             (short offset ±HHMM)
     *   '1976-11-18T15:23:30+00'                               (short offset ±HH)
     *   '+001976-11-18T15:23:30Z'                              (extended positive year)
     *   '-009999-11-18T15:23:30Z'                              (negative year)
     *   '+0019761118T15:23:30Z'                                (extended year + compact date)
     *   '2020-01-01T00:00:00Z[UTC][u-ca=iso8601]'              (multiple annotations ignored)
     *   '2016-12-31T23:59:60Z'                                 (leap second → last ns of :59)
     *   '1976-11-18T15:23:30.123456789-00:00:00.1'             (sub-minute offset)
     *   '-271821-04-20T00:00Z'                                 (spec minimum)
     *   '+275760-09-13T23:59:59.999999999+23:59:59.999999999'  (spec maximum)
     *
     * @throws InvalidArgumentException if the string cannot be parsed, has no UTC offset,
     *                                  or represents a timestamp outside the nanosecond range.
     */
    public static function from(string|object $item): self
    {
        if ($item instanceof self) {
            return new self($item->epochNanoseconds);
        }
        if (!is_string($item)) {
            throw new InvalidArgumentException(sprintf(
                'Temporal.Instant.from() requires an Instant or string, got %s.',
                get_debug_type($item),
            ));
        }
        $text = $item;
        // Reject more than 9 fractional-second digits (time part or offset fraction).
        // TC39 spec: strings with 10+ fractional digits are invalid (test262 argument-string-too-many-decimals).
        if (preg_match('/[.,]\d{10,}/', $text) === 1) {
            throw new InvalidArgumentException(
                "Invalid Instant string \"{$text}\": fractional seconds may have at most 9 digits.",
            );
        }
        /*
         * Regex groups:
         *   1 — year (±YYYYYY or YYYY)
         *   2 — date rest (-MM-DD or MMDD)
         *   3 — hour (HH)
         *   4 — minute (MM, optional — bare hour form '1976-11-18T15Z' is valid)
         *   5 — second (SS, optional)
         *   6 — time fraction ([.,]\d+, optional)
         *   7 — offset (full form including sub-minute)
         *
         * Offset alternatives (no mixed separators):
         *   Z
         *   ±HH
         *   ±HH:MM | ±HH:MM:SS | ±HH:MM:SS[.,]frac  (colon-separated)
         *   ±HHMM  | ±HHMMSS  | ±HHMMSS[.,]frac     (no separators)
         */
        $pattern = '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})[T ](\d{2})(?::?(\d{2})(?::?(\d{2}))?)?([.,]\d+)?(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)((?:\[[^\]]*\])*)$/i';

        /** @var list<string> $m */
        $m = [];
        if (preg_match($pattern, $text, $m) !== 1) {
            throw new InvalidArgumentException(
                "Invalid Instant string \"{$text}\": expected ISO 8601 with a UTC offset.",
            );
        }

        [, $yearRaw, $dateRest, $hour, $min, $sec, $fractionRaw, $offsetRaw, $annotationSection] = $m;

        // Normalise compact date (MMDD) → extended form (-MM-DD) so that both
        // PHP's DateTimeImmutable and our component extraction work uniformly.
        if (!str_starts_with($dateRest, '-')) {
            $dateRest = sprintf(
                '-%s-%s',
                substr(string: $dateRest, offset: 0, length: 2),
                substr(string: $dateRest, offset: 2, length: 2),
            );
        }

        // Extract and validate date/time components.
        $yearNum = (int) $yearRaw;
        // Reject -000000 (minus-zero year is invalid per spec).
        if ($yearNum === 0 && str_starts_with($yearRaw, '-')) {
            throw new InvalidArgumentException(
                "Invalid Instant string \"{$text}\": year -000000 (negative zero) is not valid.",
            );
        }
        $monthNum = (int) substr(string: $dateRest, offset: 1, length: 2);
        $dayNum = (int) substr(string: $dateRest, offset: 4, length: 2);
        $hourNum = (int) $hour;
        $minNum = (int) $min;
        $secNum = $sec !== '' ? (int) $sec : 0;

        if ($monthNum < 1 || $monthNum > 12) {
            throw new InvalidArgumentException("Invalid Instant string \"{$text}\": month out of range.");
        }
        $maxDay = CalendarMath::calcDaysInMonth($yearNum, $monthNum);
        if ($dayNum < 1 || $dayNum > $maxDay) {
            throw new InvalidArgumentException("Invalid Instant string \"{$text}\": day out of range.");
        }
        if ($hourNum > 23) {
            throw new InvalidArgumentException("Invalid Instant string \"{$text}\": hour out of range.");
        }
        if ($minNum > 59) {
            throw new InvalidArgumentException("Invalid Instant string \"{$text}\": minute out of range.");
        }

        // Leap second: 60 is valid and maps to the last nanosecond of :59 (spec §8.5.6).
        $sec60 = $secNum === 60;
        $normalSec = $sec60 ? 59 : $secNum;
        if (!$sec60 && $secNum > 59) {
            throw new InvalidArgumentException("Invalid Instant string \"{$text}\": second out of range.");
        }

        // Parse the offset to [sign, absSec, fracNs].  The offset is applied
        // manually so that sub-minute precision is handled correctly.
        [$offsetSign, $offsetAbsSec, $offsetFracNs] = self::parseOffset($offsetRaw, $text);

        CalendarMath::validateAnnotations($annotationSection, $text, false);

        // Build a UTC-only DateTimeImmutable (always +00:00) so that PHP does
        // not apply any offset itself. Use validated numeric values to avoid
        // malformed strings (e.g. empty $min when only the hour was given).
        // Extended year strings like '+001976-11-18T15:23:30+00:00' are handled
        // natively by PHP's flexible DateTime parser.
        try {
            $dt = new DateTimeImmutable(sprintf(
                '%s%sT%02d:%02d:%02d+00:00',
                $yearRaw,
                $dateRest,
                $hourNum,
                $minNum,
                $normalSec,
            ));
        } catch (\Exception) {
            throw new InvalidArgumentException("Could not parse \"{$text}\".");
        }

        // $localSec: Unix seconds for the local date/time as if it were UTC.
        $localSec = $dt->getTimestamp();
        $localSubNs = $fractionRaw !== '' ? self::parseFraction($fractionRaw) : 0;

        // UTC epoch seconds = local seconds − offset seconds.
        // We avoid multiplying large second values by 10^9 (which would overflow
        // int64) by carrying the nanosecond arithmetic separately.
        $utcEpochSec = $localSec - ($offsetSign * $offsetAbsSec);
        $baseNs = $localSubNs - ($offsetSign * $offsetFracNs);

        // Propagate carry from the nanosecond component into whole seconds.
        if ($baseNs < 0) {
            --$utcEpochSec;
            $baseNs += self::NS_PER_SECOND;
        } elseif ($baseNs >= self::NS_PER_SECOND) {
            ++$utcEpochSec;
            $baseNs -= self::NS_PER_SECOND;
        }

        // Spec range: epoch nanoseconds ∈ [-8_640_000_000_000×10⁹, +8_640_000_000_000×10⁹].
        // Checked at second granularity; at the boundary second, any non-zero
        // sub-second component puts the instant out of range.
        $maxSec = 8_640_000_000_000;
        if ($utcEpochSec < -$maxSec || $utcEpochSec > $maxSec || $utcEpochSec === $maxSec && $baseNs > 0) {
            throw new InvalidArgumentException(
                "Instant string \"{$text}\" is outside the representable nanosecond range.",
            );
        }

        // For dates far from the Unix epoch (years roughly outside 1678–2262),
        // utcEpochSec × NS_PER_SECOND would overflow PHP's int64. We guard with
        // a hardcoded threshold of 9_223_372_035 — one less than
        // intdiv(PHP_INT_MAX, NS_PER_SECOND) = 9_223_372_036 — so that the full
        // product plus the maximum baseNs (999_999_999) stays within int64
        // (9_223_372_035 × 10⁹ + 999_999_999 = 9_223_372_035_999_999_999 < PHP_INT_MAX).
        // Dates beyond the threshold use a saturated sentinel so that from() does
        // not throw for spec-valid but int64-unrepresentable instants.
        $maxSecForNs = 9_223_372_035;
        if ($utcEpochSec > $maxSecForNs || $utcEpochSec < -$maxSecForNs) {
            $epochNs = $utcEpochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX;
        } else {
            $epochNs = ($utcEpochSec * self::NS_PER_SECOND) + $baseNs;
        }

        return new self($epochNs);
    }

    /**
     * Creates an Instant from a Unix timestamp in milliseconds.
     *
     * @param int|float|null $epochMilliseconds Milliseconds since the Unix epoch.
     *        Must be a finite integer value within ±8_640_000_000_000_000.
     * @throws InvalidArgumentException if the value is not a finite integer or is out of range.
     */
    public static function fromEpochMilliseconds(int|float|null $epochMilliseconds = null): self
    {
        if ($epochMilliseconds === null) {
            throw new InvalidArgumentException('epochMilliseconds must be provided.');
        }
        if (is_float($epochMilliseconds)) {
            if (!is_finite($epochMilliseconds) || floor($epochMilliseconds) !== $epochMilliseconds) {
                throw new InvalidArgumentException(
                    "epochMilliseconds must be a finite integer value, got {$epochMilliseconds}.",
                );
            }
            $epochMilliseconds = (int) $epochMilliseconds;
        }
        $limit = 8_640_000_000_000_000;
        if ($epochMilliseconds < -$limit || $epochMilliseconds > $limit) {
            throw new InvalidArgumentException(
                "epochMilliseconds {$epochMilliseconds} is outside the valid range of ±{$limit}.",
            );
        }
        // Guard against int64 overflow when multiplying ms × 10^6 to get nanoseconds.
        // Threshold: floor(PHP_INT_MAX / NS_PER_MILLISECOND) = 9_223_372_036_854
        $threshold = 9_223_372_036_854;
        if ($epochMilliseconds > $threshold || $epochMilliseconds < -$threshold) {
            return new self($epochMilliseconds < 0 ? PHP_INT_MIN : PHP_INT_MAX);
        }
        return new self($epochMilliseconds * self::NS_PER_MILLISECOND);
    }

    /**
     * Creates an Instant from a Unix timestamp in nanoseconds.
     */
    public static function fromEpochNanoseconds(int $epochNanoseconds): self
    {
        return new self($epochNanoseconds);
    }

    /**
     * Compares two Instants chronologically.
     *
     * Accepts either an Instant instance or a string parseable by {@see from()}.
     *
     * @return int -1, 0, or 1.
     */
    public static function compare(string|object $one, string|object $two): int
    {
        $a = $one instanceof self ? $one : self::coerceToInstant($one);
        $b = $two instanceof self ? $two : self::coerceToInstant($two);
        return $a->epochNanoseconds <=> $b->epochNanoseconds;
    }

    /**
     * Coerces a non-Instant value to an Instant by parsing it as an ISO string.
     * Throws TypeError for primitive non-strings (null, bool, int, float).
     * Throws InvalidArgumentException for objects/arrays (can't replicate JS toString coercion).
     *
     * @throws \TypeError for primitive non-string values.
     * @throws InvalidArgumentException for objects/arrays or invalid strings.
     */
    private static function coerceToInstant(string|object $arg): self
    {
        if (is_string($arg)) {
            return self::from($arg);
        }
        throw new InvalidArgumentException('Temporal\\Instant argument must be a Temporal\\Instant or an ISO string.');
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns true when both Instants represent the same point in time.
     *
     * Accepts either an Instant instance or a string parseable by {@see from()}.
     * Any other type throws InvalidArgumentException (matches JS coercion failure behaviour).
     *
     * @throws InvalidArgumentException if $other is not an Instant or a valid ISO string.
     */
    public function equals(string|object $other): bool
    {
        return (
            $this->epochNanoseconds
            === ($other instanceof self ? $other : self::coerceToInstant($other))->epochNanoseconds
        );
    }

    /**
     * Throws TypeError unconditionally to prevent numeric comparison.
     *
     * Mirrors JS Temporal.Instant.prototype.valueOf() which always throws TypeError.
     *
     * @psalm-api used by test262 scripts
     * @throws \TypeError always
     * @return never
     */
    public function valueOf(): never
    {
        throw new \TypeError(
            'Use Temporal\\Instant::compare() or epochNanoseconds to compare Temporal\\Instant values.',
        );
    }

    /**
     * Returns an ISO 8601 string in UTC, with optional rounding and precision options.
     *
     * Options (all optional):
     *   - fractionalSecondDigits: 'auto' (default, strip trailing zeros) | 0–9 (fixed digit count)
     *     Floats are floored. Null, booleans, NaN, ±Inf, or out-of-range values throw.
     *   - smallestUnit: 'minute'|'second'|'millisecond'|'microsecond'|'nanosecond' (overrides digits)
     *   - roundingMode: 'trunc' (default)|'floor'|'ceil'|'expand'|'halfExpand'|
     *                   'halfTrunc'|'halfFloor'|'halfCeil'|'halfEven'
     *     Null roundingMode defaults to 'trunc'.
     *
     * Uses RoundNumberToIncrementAsIfPositive (spec §8.3.13): rounding is always applied
     * using the unsigned mode for a positive sign, regardless of the actual sign of the epoch.
     *
     * @param array<array-key, mixed>|object|null $options
     * @throws InvalidArgumentException if options are invalid.
     */
    public function toString(array|object|null $options = null): string
    {
        // TC39: any object (including closures) is a valid options bag treated as empty.
        if (is_object($options)) {
            $options = [];
        }

        // $digits: -2 = 'auto' (strip trailing zeros), -1 = minute format, 0-9 = fixed.
        $digits = -2;
        $roundMode = 'trunc';
        $isMinute = false;
        $increment = 1;

        if ($options !== null) {
            // fractionalSecondDigits
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

            // smallestUnit overrides fractionalSecondDigits
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

            // roundingMode (null → default 'trunc')
            if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
                $roundMode = (string) $options['roundingMode'];
            }

            // timeZone: must be a string; non-string (including null) → TypeError.
            if (array_key_exists('timeZone', $options)) {
                /** @var mixed $tzVal */
                $tzVal = $options['timeZone'];
                if (!is_string($tzVal)) {
                    throw new \TypeError('timeZone must be a string.');
                }
                self::validateTimeZoneString($tzVal);
            }
        }

        // Resolve timezone offset (null = UTC / 'Z' suffix).
        $tzOffsetMinutes = null;
        if ($options !== null && array_key_exists('timeZone', $options)) {
            $tzStr = (string) $options['timeZone'];
            $tzOffsetMinutes = self::resolveTimeZoneOffsetMinutes($tzStr);
        }

        // Determine the rounding increment in nanoseconds.
        if ($isMinute) {
            $increment = 60_000_000_000;
        } elseif ($digits >= 0) {
            // exponent (9 - $digits) is always 0-9, so ** always returns int here.
            $increment = (int) 10 ** (9 - $digits); // @phpstan-ignore cast.useless
        }
        // For 'auto' ($digits === -2), increment stays 1 (no rounding).

        // Round using RoundNumberToIncrementAsIfPositive.
        $ns = $increment === 1
            ? $this->epochNanoseconds
            : self::roundAsIfPositive($this->epochNanoseconds, $increment, $roundMode);

        // Extract whole UTC seconds and sub-second nanoseconds.
        $secs = CalendarMath::floorDiv($ns, self::NS_PER_SECOND);
        $subNs = $ns - ($secs * self::NS_PER_SECOND); // always 0–999_999_999

        // Apply timezone offset to get local datetime.
        $localSecs = $tzOffsetMinutes !== null ? $secs + ($tzOffsetMinutes * 60) : $secs;
        $dt = new DateTimeImmutable(sprintf('@%d', $localSecs))->setTimezone(new DateTimeZone('UTC'));

        // Build the UTC-offset suffix: 'Z' or ±HH:MM.
        if ($tzOffsetMinutes === null) {
            $tzSuffix = 'Z';
        } else {
            $absMin = abs($tzOffsetMinutes);
            $tzH = intdiv(num1: $absMin, num2: 60);
            $tzM = $absMin % 60;
            $tzSign = $tzOffsetMinutes < 0 ? '-' : '+';
            $tzSuffix = sprintf('%s%02d:%02d', $tzSign, $tzH, $tzM);
        }

        if ($isMinute) {
            return $dt->format('Y-m-d\TH:i') . $tzSuffix;
        }

        $base = $dt->format('Y-m-d\TH:i:s');

        if ($digits === -2) {
            // 'auto': strip trailing zeros.
            if ($subNs === 0) {
                return $base . $tzSuffix;
            }
            $fraction = rtrim(sprintf('%09d', $subNs), characters: '0');
            return "{$base}.{$fraction}{$tzSuffix}";
        }

        if ($digits === 0) {
            return $base . $tzSuffix;
        }

        $fraction = substr(sprintf('%09d', $subNs), offset: 0, length: $digits);
        return "{$base}.{$fraction}{$tzSuffix}";
    }

    /**
     * Validates a timezone identifier string for the toString() timeZone option.
     *
     * Rules (from TC39 Temporal spec):
     *   - Minus-zero extended year (-000000) → reject.
     *   - Bracket annotation offset with seconds (e.g. [+23:59:60]) → reject.
     *   - Pure UTC-offset strings (start with ±HH): must be ±HH:MM or ±HHMM (no seconds).
     *   - Datetime strings (contain T): must have Z, an offset, or a bracket annotation;
     *     an inline offset must not include a seconds component.
     *
     * @throws InvalidArgumentException for invalid timezone strings.
     */
    private static function validateTimeZoneString(string $tz): void
    {
        // Reject empty string.
        if ($tz === '') {
            throw new InvalidArgumentException('Invalid timeZone "": empty string is not a valid timezone identifier.');
        }
        // Reject minus-zero extended year.
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $tz) === 1) {
            throw new InvalidArgumentException("Invalid timeZone \"{$tz}\": minus-zero year.");
        }
        // Reject bracket annotation with a seconds component (e.g. [+23:59:60]).
        if (preg_match('/\[([^\]]+)\]/', $tz, $bm) === 1) {
            if (preg_match('/^[+\-]\d{2}:\d{2}:\d{2}/', $bm[1]) === 1) {
                throw new InvalidArgumentException(
                    "Invalid timeZone \"{$tz}\": sub-minute seconds in bracket annotation.",
                );
            }
        }
        // Pure UTC-offset strings (no T date/time part): must be ±HH:MM or ±HHMM.
        if (preg_match('/^[+\-]\d{2}/', $tz) === 1 && !str_contains($tz, 'T') && !str_contains($tz, 't')) {
            if (preg_match('/^[+\-]\d{2}:\d{2}(?:$|[^:\d])/', $tz) !== 1 && preg_match('/^[+\-]\d{4}$/', $tz) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid timeZone \"{$tz}\": offset contains seconds or is in an invalid format.",
                );
            }
            return;
        }
        // Datetime strings: must have Z, an offset, or a bracket annotation.
        if (preg_match('/\d{4,}-\d{2}-\d{2}[Tt]|\d{8}[Tt]/', $tz) === 1) {
            if (preg_match('/T\d{2}:?\d{2}(?::?\d{2})?(?:\.\d+)?(?:Z|[+\-]|\[)/i', $tz) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid timeZone \"{$tz}\": bare datetime without Z, offset, or bracket.",
                );
            }
            // Inline offset must not include a seconds component (e.g. -07:00:01).
            if (preg_match('/[+\-]\d{2}:\d{2}:\d{2}(?!\])/i', $tz) === 1) {
                throw new InvalidArgumentException(
                    "Invalid timeZone \"{$tz}\": inline offset contains a seconds component.",
                );
            }
        }
    }

    /**
     * Extracts the UTC offset in minutes from a validated timezone string.
     *
     * Priority: bracket annotation > inline offset/Z.
     * Returns 0 for 'UTC' or 'Z', the offset minutes for ±HH:MM strings,
     * and the bracket annotation offset for datetime strings with [±HH:MM] or [UTC].
     */
    private static function resolveTimeZoneOffsetMinutes(string $tz): int
    {
        // 'UTC' (case-insensitive)
        if (strtoupper($tz) === 'UTC') {
            return 0;
        }
        // Pure UTC-offset strings: ±HH:MM or ±HHMM
        if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $tz, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            return $sign * (((int) $m[2] * 60) + (int) $m[3]);
        }
        if (preg_match('/^([+\-])(\d{2})(\d{2})$/', $tz, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            return $sign * (((int) $m[2] * 60) + (int) $m[3]);
        }
        // Datetime strings: bracket annotation takes precedence.
        if (preg_match('/\[([^\]]+)\]/', $tz, $bm) === 1) {
            $bracket = $bm[1];
            if (strtoupper($bracket) === 'UTC') {
                return 0;
            }
            if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $bracket, $om) === 1) {
                $sign = $om[1] === '+' ? 1 : -1;
                return $sign * (((int) $om[2] * 60) + (int) $om[3]);
            }
        }
        // Datetime strings without bracket: use inline offset or Z.
        if (preg_match('/[Tt].*?(Z|([+\-])(\d{2}):(\d{2}))/i', $tz, $om) === 1) {
            if ($om[1] === 'Z' || $om[1] === 'z') {
                return 0;
            }
            /** @var array{non-falsy-string, non-falsy-string, '+'|'-', non-falsy-string, non-falsy-string} $om */
            $sign = $om[2] === '+' ? 1 : -1;
            return $sign * (((int) $om[3] * 60) + (int) $om[4]);
        }
        return 0;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /** @psalm-suppress UnusedParam toJSON ignores its argument per TC39 spec */
    public function toJSON(mixed $options = null): string
    {
        return $this->toString();
    }

    /**
     * Returns a locale-sensitive string for this Instant using IntlDateFormatter.
     *
     * Supports a subset of Intl.DateTimeFormat options:
     *   - dateStyle: "full" | "long" | "medium" | "short"
     *   - timeStyle: "full" | "long" | "medium" | "short"
     *   - timeZone: IANA timezone string (defaults to UTC for Instant)
     *   - calendar: calendar identifier appended as u-ca locale extension
     *
     * @param string|array<array-key, mixed>|null $locales  BCP 47 locale string or array of strings.
     * @param array<array-key, mixed>|object|null $options  Intl.DateTimeFormat options array.
     * @psalm-api
     */
    public function toLocaleString(string|array|null $locales = null, array|object|null $options = null): string
    {
        $locale = CalendarMath::resolveLocale($locales);
        $opts = is_array($options) ? $options : [];
        /** @psalm-var array<string, mixed> $opts */

        $timeZone = isset($opts['timeZone']) && is_string($opts['timeZone']) ? $opts['timeZone'] : 'UTC';

        $opts['_locale'] = $locale;
        $formatter = CalendarMath::buildIntlFormatter($locale, $timeZone, $opts);
        $seconds = intdiv(num1: $this->epochNanoseconds, num2: self::NS_PER_SECOND);
        $result = $formatter->format($seconds);

        return $result !== false ? $result : $this->toString();
    }

    /**
     * Returns a ZonedDateTime for this Instant in the given time zone.
     *
     * @param string $timeZone A timezone string: 'UTC', '±HH:MM', or an ISO datetime string
     *                        with an inline offset or bracket annotation (e.g. '2020-01-01T00:00Z').
     * @psalm-api used by test262 scripts
     * @throws InvalidArgumentException if the timezone string is invalid (empty, sub-minute offset, etc.).
     */
    public function toZonedDateTimeISO(string $timeZone): ZonedDateTime
    {
        $tzId = self::parseTimeZoneId($timeZone);
        return new ZonedDateTime($this->epochNanoseconds, $tzId);
    }

    /**
     * Parses a timezone string and returns its canonical timezone ID.
     *
     * Accepts: 'UTC' (case-insensitive), '±HH:MM', or ISO datetime strings
     * with an inline offset (Z or ±HH:MM) or a bracket annotation [tzId].
     * Sub-minute offsets, bare datetimes, and empty strings are rejected.
     *
     * @throws InvalidArgumentException for invalid timezone strings.
     */
    private static function parseTimeZoneId(string $tz): string
    {
        if ($tz === '') {
            throw new InvalidArgumentException('Time zone string must not be empty.');
        }
        // 'UTC' (case-insensitive).
        if (strtoupper($tz) === 'UTC') {
            return 'UTC';
        }
        // Reject minus-zero extended year.
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $tz) === 1) {
            throw new InvalidArgumentException("Invalid time zone string \"{$tz}\": minus-zero year.");
        }

        // Determine if this looks like a datetime (has a T-separator after a date part).
        $isDatetime = preg_match('/\d{4,}-\d{2}-\d{2}[Tt]|\d{8}[Tt]/', $tz) === 1;

        if ($isDatetime) {
            // Bracket annotation takes precedence over the inline offset.
            if (preg_match('/\[(!?[^\]]+)\]/', $tz, $bm) === 1) {
                $bracket = $bm[1];
                // Sub-minute offset in bracket: reject.
                if (preg_match('/^[+\-]\d{2}:\d{2}:\d{2}/', $bracket) === 1) {
                    throw new InvalidArgumentException(
                        "Invalid time zone string \"{$tz}\": sub-minute offset in bracket annotation.",
                    );
                }
                if (strtoupper($bracket) === 'UTC') {
                    return 'UTC';
                }
                if (preg_match('/^[+\-]\d{2}:\d{2}$/', $bracket) === 1) {
                    return $bracket;
                }
                throw new InvalidArgumentException(
                    "Invalid time zone string \"{$tz}\": unsupported bracket timezone \"{$bracket}\".",
                );
            }
            // No bracket: inline offset (Z or ±HH:MM) required.
            // Reject sub-minute inline offset.
            if (preg_match('/[+\-]\d{2}:\d{2}:\d{2}/i', $tz) === 1) {
                throw new InvalidArgumentException(
                    "Invalid time zone string \"{$tz}\": inline offset contains a seconds component.",
                );
            }
            // Extract inline offset (Z or ±HH:MM at end or after time part).
            if (preg_match('/[Zz](?:\[|$)/', $tz) === 1) {
                return 'UTC';
            }
            if (preg_match('/([+\-]\d{2}:\d{2})(?:\[|$)/', $tz, $om) === 1) {
                return $om[1];
            }
            // Bare datetime with no offset and no bracket.
            throw new InvalidArgumentException(
                "Invalid time zone string \"{$tz}\": bare datetime without Z, offset, or bracket.",
            );
        }

        // Pure UTC-offset strings: accept only ±HH:MM (no seconds component).
        if (preg_match('/^[+\-]\d{2}:\d{2}$/', $tz) === 1) {
            return $tz;
        }
        // ±HHMM (compact form) → normalize to ±HH:MM.
        if (preg_match('/^([+\-])(\d{2})(\d{2})$/', $tz, $m) === 1) {
            return sprintf('%s%s:%s', $m[1], $m[2], $m[3]);
        }
        // Anything with more than ±HH:MM (seconds or fractional) → reject.
        if (preg_match('/^[+\-]\d{2}:\d{2}[:.].*/i', $tz) === 1) {
            throw new InvalidArgumentException(
                "Invalid time zone string \"{$tz}\": sub-minute offset is not a valid timezone identifier.",
            );
        }

        throw new InvalidArgumentException("Invalid time zone string \"{$tz}\": not a recognized timezone identifier.");
    }

    /**
     * Returns a new Instant advanced by the given duration.
     *
     * Calendar fields (years, months, weeks, days) are forbidden — Instant has
     * no calendar context. Passing a Duration with any of those fields non-zero
     * throws RangeError.
     *
     * @param Duration|string|array<array-key, mixed>|object $duration Duration, ISO 8601 duration string, or property-bag array.
     * @psalm-api used by test262 scripts
     * @throws InvalidArgumentException if the duration contains calendar fields or the result is out of range.
     */
    public function add(string|array|object $duration): self
    {
        $d = Duration::from($duration);
        if ($d->years !== 0 || $d->months !== 0 || $d->weeks !== 0 || $d->days !== 0) {
            throw new InvalidArgumentException(
                'Temporal\\Instant::add() does not support calendar fields (years, months, weeks, days).',
            );
        }
        return self::addNsOffset(
            $this->epochNanoseconds,
            $d->hours,
            $d->minutes,
            $d->seconds,
            $d->milliseconds,
            $d->microseconds,
            $d->nanoseconds,
        );
    }

    /**
     * Returns a new Instant moved back by the given duration.
     *
     * Calendar fields (years, months, weeks, days) are forbidden.
     *
     * @param Duration|string|array<array-key, mixed>|object $duration Duration, ISO 8601 duration string, or property-bag array.
     * @psalm-api used by test262 scripts
     * @throws InvalidArgumentException if the duration contains calendar fields or the result is out of range.
     */
    public function subtract(string|array|object $duration): self
    {
        $d = Duration::from($duration);
        if ($d->years !== 0 || $d->months !== 0 || $d->weeks !== 0 || $d->days !== 0) {
            throw new InvalidArgumentException(
                'Temporal\\Instant::subtract() does not support calendar fields (years, months, weeks, days).',
            );
        }
        return self::addNsOffset(
            $this->epochNanoseconds,
            -$d->hours,
            -$d->minutes,
            -$d->seconds,
            -$d->milliseconds,
            -$d->microseconds,
            -$d->nanoseconds,
        );
    }

    /**
     * Returns a new Instant rounded to the given unit and increment.
     *
     * The $roundTo argument may be a string (treated as smallestUnit) or an
     * options array with keys: smallestUnit (required), roundingMode (default
     * 'halfExpand'), roundingIncrement (default 1).
     *
     * @param string|array<array-key, mixed>|object $roundTo
     * @psalm-api used by test262 scripts
     * @throws InvalidArgumentException if smallestUnit is missing/invalid or roundingIncrement is invalid.
     */
    public function round(string|array|object $roundTo): self
    {
        if (is_string($roundTo)) {
            $roundTo = ['smallestUnit' => $roundTo];
        } elseif (is_object($roundTo)) {
            // TC39: any object is a valid options bag treated as empty if no properties.
            $roundTo = [];
        }

        /** @psalm-suppress MixedAssignment */
        $suRaw = $roundTo['smallestUnit'] ?? null;
        if ($suRaw === null) {
            throw new InvalidArgumentException('Temporal\\Instant::round() requires smallestUnit.');
        }
        if (!is_string($suRaw)) {
            throw new \TypeError('smallestUnit must be a string.');
        }
        // Maps unit name → [ns-per-unit, max-increment-divisor (next unit size)]
        $unitMap = [
            'nanosecond' => [1, 86_400_000_000_000],
            'nanoseconds' => [1, 86_400_000_000_000],
            'microsecond' => [1_000, 86_400_000_000],
            'microseconds' => [1_000, 86_400_000_000],
            'millisecond' => [1_000_000, 86_400_000],
            'milliseconds' => [1_000_000, 86_400_000],
            'second' => [1_000_000_000, 86_400],
            'seconds' => [1_000_000_000, 86_400],
            'minute' => [60_000_000_000, 1_440],
            'minutes' => [60_000_000_000, 1_440],
            'hour' => [3_600_000_000_000, 24],
            'hours' => [3_600_000_000_000, 24],
        ];
        if (!array_key_exists($suRaw, $unitMap)) {
            throw new InvalidArgumentException("Invalid smallestUnit \"{$suRaw}\" for Temporal\\Instant::round().");
        }
        [$nsPerUnit, $maxDivisor] = $unitMap[$suRaw];

        $roundingMode = 'halfExpand';
        if (array_key_exists('roundingMode', $roundTo) && $roundTo['roundingMode'] !== null) {
            /** @psalm-suppress MixedArgument */
            $roundingMode = (string) $roundTo['roundingMode'];
        }

        $increment = 1;
        if (array_key_exists('roundingIncrement', $roundTo) && $roundTo['roundingIncrement'] !== null) {
            /** @psalm-suppress MixedArgument */
            $increment = (int) $roundTo['roundingIncrement'];
        }
        if ($increment < 1) {
            throw new InvalidArgumentException('roundingIncrement must be a positive integer.');
        }
        if (($maxDivisor % $increment) !== 0) {
            throw new InvalidArgumentException(
                "roundingIncrement {$increment} does not evenly divide {$maxDivisor} for unit \"{$suRaw}\".",
            );
        }

        $nsIncrement = $nsPerUnit * $increment;
        $rounded = self::roundAsIfPositive($this->epochNanoseconds, $nsIncrement, $roundingMode);
        return new self($rounded);
    }

    /**
     * Returns the elapsed time from $other to $this as a Duration.
     *
     * The result is positive when $this is after $other.
     *
     * @param string|object $other The starting instant (Instant or ISO string).
     * @param array<array-key, mixed>|object|null $options
     * @psalm-api used by test262 scripts
     */
    public function since(string|object $other, array|object|null $options = null): Duration
    {
        $otherInst = $other instanceof self ? $other : self::coerceToInstant($other);
        $diffNs = $this->epochNanoseconds - $otherInst->epochNanoseconds;
        return self::diffInstant($diffNs, $options);
    }

    /**
     * Returns the elapsed time from $this to $other as a Duration.
     *
     * The result is positive when $other is after $this.
     *
     * @param string|object $other The ending instant (Instant or ISO string).
     * @param array<array-key, mixed>|object|null $options
     * @psalm-api used by test262 scripts
     */
    public function until(string|object $other, array|object|null $options = null): Duration
    {
        $otherInst = $other instanceof self ? $other : self::coerceToInstant($other);
        $diffNs = $otherInst->epochNanoseconds - $this->epochNanoseconds;
        return self::diffInstant($diffNs, $options);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Parses an offset string captured by the regex into [sign, absSec, fracNs].
     *
     * Accepted forms:
     *   Z                              → [+1, 0, 0]
     *   ±HH                            → [sign, H*3600, 0]
     *   ±HH:MM | ±HH:MM:SS[.,f]       → colon-separated
     *   ±HHMM  | ±HHMMSS[.,f]         → no separators
     *
     * @return array{-1|1, int<0, 86399>, int<0, 999999999>}  [sign (+1|-1), absSec, fracNs]
     * @throws InvalidArgumentException if the offset is out of range
     */
    private static function parseOffset(string $offset, string $original): array
    {
        if ($offset === 'Z' || $offset === 'z') {
            return [1, 0, 0];
        }

        $sign = $offset[0] === '+' ? 1 : -1;
        $rest = substr(string: $offset, offset: 1); // digits (and separators) after the sign

        $hours = (int) substr(string: $rest, offset: 0, length: 2);
        $rest = substr(string: $rest, offset: 2);
        $minutes = 0;
        $seconds = 0;
        $fracNs = 0;

        if ($rest !== '') {
            if ($rest[0] === ':') {
                // Colon-separated: :MM[:SS[.frac]]
                $minutes = (int) substr(string: $rest, offset: 1, length: 2);
                $rest = substr(string: $rest, offset: 3);
                if ($rest !== '' && $rest[0] === ':') {
                    $seconds = (int) substr(string: $rest, offset: 1, length: 2);
                    $rest = substr(string: $rest, offset: 3);
                    if ($rest !== '' && ($rest[0] === '.' || $rest[0] === ',')) {
                        $fracNs = self::parseFraction($rest);
                    }
                }
            } else {
                // No separators: MM[SS[.frac]]
                $minutes = (int) substr(string: $rest, offset: 0, length: 2);
                $rest = substr(string: $rest, offset: 2);
                if (strlen($rest) >= 2) {
                    $seconds = (int) substr(string: $rest, offset: 0, length: 2);
                    $rest = substr(string: $rest, offset: 2);
                    if ($rest !== '' && ($rest[0] === '.' || $rest[0] === ',')) {
                        $fracNs = self::parseFraction($rest);
                    }
                }
            }
        }

        $absSec = ($hours * 3600) + ($minutes * 60) + $seconds;
        if ((($absSec * self::NS_PER_SECOND) + $fracNs) > 86_399_999_999_999) {
            throw new InvalidArgumentException("Invalid Instant string \"{$original}\": UTC offset out of range.");
        }
        /** @var int<0, 86399> $absSec — range validated above */

        return [$sign, $absSec, $fracNs];
    }

    /**
     * Validates the bracket-annotation section of an ISO string.
     *
     * Rules (per Temporal spec §13.29):
     *  - Annotation keys must be all-lowercase.
     *  - A critical unknown annotation (e.g. [!foo=bar]) → reject.
     *  - Multiple time-zone annotations → reject.
     *  - Multiple calendar annotations where any carries ! → reject.
     *  - A time-zone annotation may only use ±HH:MM (no seconds component) as an offset.
     *
     * Non-critical unknown annotations and calendar annotations are ignored.
     *
     * @throws InvalidArgumentException on any violation.
     */
    /**
     * Strips the leading separator and truncates/pads the fractional-second
     * string to exactly 9 digits, then returns the nanosecond count.
     *
     * The Temporal spec allows arbitrarily long fraction strings; digits beyond
     * the 9th are discarded (truncation, not rounding).
     *
     * @return int<0, 999999999>
     */
    private static function parseFraction(string $fractionRaw): int
    {
        $digits = substr($fractionRaw, offset: 1); // strip leading '.' or ','
        /** @var int<0, 999999999> — 9 decimal digits, range 000000000–999999999 */
        $ns = (int) str_pad(substr($digits, offset: 0, length: 9), length: 9, pad_string: '0');
        return $ns;
    }

    /**
     * Rounds $ns to the nearest multiple of $increment using
     * RoundNumberToIncrementAsIfPositive (spec §8.5.8).
     *
     * Unlike the standard signed rounding, this algorithm always applies the
     * unsigned rounding mode corresponding to a positive sign, so that 'trunc'
     * and 'floor' always round toward -∞ (floor of the real quotient) and
     * 'ceil' and 'expand' always round toward +∞.
     *
     * The tie-breaking half-modes (halfExpand, halfCeil, halfTrunc, halfFloor,
     * halfEven) use the same positive-sign convention.
     *
     * @throws InvalidArgumentException for unknown rounding modes.
     */
    private static function roundAsIfPositive(int $ns, int $increment, string $mode): int
    {
        // Integer floor-division: r1 = floor(ns / increment).
        $q = intdiv($ns, $increment);
        $rem = $ns - ($q * $increment);
        $r1 = $rem < 0 ? $q - 1 : $q;

        // d1 = distance of $ns from r1 (always in [0, $increment)).
        $d1 = $ns - ($r1 * $increment);

        // Directed rounding (AsIfPositive: trunc/floor → r1; ceil/expand → r2):
        $r2 = $r1 + 1;
        $rounded = match ($mode) {
            'trunc', 'floor' => $r1,
            'ceil', 'expand' => $d1 === 0 ? $r1 : $r2,
            'halfExpand', 'halfCeil' => ($d1 * 2) >= $increment ? $r2 : $r1,
            'halfTrunc', 'halfFloor' => ($d1 * 2) > $increment ? $r2 : $r1,
            'halfEven' => ($d1 * 2) < $increment ? $r1 : (($d1 * 2) > $increment ? $r2 : (($r1 % 2) === 0 ? $r1 : $r2)),
            default => throw new InvalidArgumentException("Invalid roundingMode \"{$mode}\"."),
        };

        return $rounded * $increment;
    }

    /**
     * Computes a new Instant by adding a time-field offset to an epoch-nanoseconds value.
     *
     * Uses a float approximation for the spec-range check (±8.64e21 ns), then falls
     * back to a sentinel (PHP_INT_MIN/MAX) when the result fits in the spec but not
     * in a PHP int64.  The exact integer computation is performed only when the result
     * is guaranteed to fit.
     *
     * @throws InvalidArgumentException if the resulting instant is outside the Temporal spec range.
     */
    private static function addNsOffset(
        int $epochNs,
        int|float $hours,
        int|float $minutes,
        int|float $seconds,
        int|float $milliseconds,
        int|float $microseconds,
        int|float $nanoseconds,
    ): self {
        // Float approximation — sufficient for spec-range check.
        $floatDelta =
            ((float) $hours * 3_600_000_000_000.0)
            + ((float) $minutes * 60_000_000_000.0)
            + ((float) $seconds * 1_000_000_000.0)
            + ((float) $milliseconds * 1_000_000.0)
            + ((float) $microseconds * 1_000.0)
            + (float) $nanoseconds;
        $floatResult = (float) $epochNs + $floatDelta;

        // Spec range: |epochNs| ≤ 8_640_000_000_000 × 10⁹.
        $specMaxNs = 8_640_000_000_000.0 * 1_000_000_000.0;
        if ($floatResult > $specMaxNs || $floatResult < -$specMaxNs) {
            throw new InvalidArgumentException('Instant result is outside the representable nanosecond range.');
        }

        // Use sentinel when outside PHP int64 range but within spec range.
        if ($floatResult > (float) PHP_INT_MAX || $floatResult < (float) PHP_INT_MIN) {
            return new self($floatResult < 0.0 ? PHP_INT_MIN : PHP_INT_MAX);
        }

        // Exact integer computation.
        $h = (int) $hours;
        $m = (int) $minutes;
        $s = (int) $seconds;
        $ms = (int) $milliseconds;
        $us = (int) $microseconds;
        $ns = (int) $nanoseconds;

        $result =
            $epochNs
            + $ns
            + ($us * 1_000)
            + ($ms * 1_000_000)
            + ($s * self::NS_PER_SECOND)
            + ($m * 60 * self::NS_PER_SECOND)
            + ($h * 3_600 * self::NS_PER_SECOND);

        // PHP promotes int to float on overflow; use float-result sentinel in that case.
        if (is_float($result)) { // @phpstan-ignore function.impossibleType
            return new self($floatResult < 0.0 ? PHP_INT_MIN : PHP_INT_MAX);
        }

        return new self($result);
    }

    /**
     * Core implementation for since() and until().
     *
     * Rounds and balances a nanosecond difference into a Duration according to
     * the given options.
     *
     * Unit ordering (smallest to largest):
     *   nanosecond < microsecond < millisecond < second < minute < hour
     *
     * @param int|float $diffNs Signed nanosecond difference (this − other for since, other − this for until).
     *                         May be float when the subtraction of two int64 sentinels overflows.
     * @param array<array-key, mixed>|object|null $options
     * @throws InvalidArgumentException for invalid unit/mode strings.
     * @throws InvalidArgumentException for invalid roundingIncrement.
     * @throws \TypeError for wrong-typed option values.
     */
    private static function diffInstant(int|float $diffNs, array|object|null $options): Duration
    {
        // If diffNs is a float (int64 overflow), clamp it to the spec range for balancing.
        if (is_float($diffNs)) {
            $specMaxNs = 8_640_000_000_000.0 * 1_000_000_000.0;
            if ($diffNs > $specMaxNs) {
                $diffNs = (int) $specMaxNs;
            } elseif ($diffNs < -$specMaxNs) {
                $diffNs = (int) -$specMaxNs;
            } else {
                // Values at ≥2^63 in float notation can wrap when cast to int.
                if ($diffNs >= 9_223_372_036_854_775_808.0) {
                    $diffNs = PHP_INT_MAX;
                } elseif ($diffNs <= -9_223_372_036_854_775_808.0) {
                    $diffNs = PHP_INT_MIN + 1;
                } else {
                    $diffNs = (int) $diffNs;
                }
            }
        }
        // Unit name → index (0 = smallest).
        $unitOrder = [
            'nanosecond' => 0,
            'nanoseconds' => 0,
            'microsecond' => 1,
            'microseconds' => 1,
            'millisecond' => 2,
            'milliseconds' => 2,
            'second' => 3,
            'seconds' => 3,
            'minute' => 4,
            'minutes' => 4,
            'hour' => 5,
            'hours' => 5,
        ];

        // ns-per-unit for each canonical index.
        $nsPerUnitByIndex = [
            0 => 1,
            1 => 1_000,
            2 => 1_000_000,
            3 => 1_000_000_000,
            4 => 60_000_000_000,
            5 => 3_600_000_000_000,
        ];

        // Maximum roundingIncrement per unit (TC39 MaximumTemporalDurationRoundingIncrement).
        $maxIncrementByIndex = [
            0 => 1_000, // ns → µs: max 999
            1 => 1_000, // µs → ms: max 999
            2 => 1_000, // ms → s:  max 999
            3 => 60, // s  → min: max 59
            4 => 60, // min → h: max 59
            5 => 24, // h: max 24 (must evenly divide 24 and be < 24)
        ];

        // ---- Parse options ----
        // TC39: any object (including closures) is a valid options bag treated as empty.
        if (is_object($options)) {
            $options = [];
        } elseif ($options === null) {
            $options = [];
        }

        // Track whether largestUnit was explicitly provided.
        $luProvided = array_key_exists('largestUnit', $options) && $options['largestUnit'] !== null;
        /** @psalm-suppress MixedAssignment */
        $luVal = $luProvided ? $options['largestUnit'] : null;
        /** @psalm-suppress MixedAssignment */
        $suVal = array_key_exists('smallestUnit', $options) ? $options['smallestUnit'] : null;

        if ($luVal !== null && !is_string($luVal)) {
            throw new \TypeError('largestUnit must be a string.');
        }
        if ($suVal !== null && !is_string($suVal)) {
            throw new \TypeError('smallestUnit must be a string.');
        }

        $suRaw = is_string($suVal) ? $suVal : 'nanosecond';

        if (!array_key_exists($suRaw, $unitOrder)) {
            throw new InvalidArgumentException("Invalid smallestUnit \"{$suRaw}\".");
        }

        $suIdx = $unitOrder[$suRaw];

        if ($luProvided) {
            $luRaw = (string) $luVal;
            if (!array_key_exists($luRaw, $unitOrder)) {
                throw new InvalidArgumentException("Invalid largestUnit \"{$luRaw}\".");
            }
            $luIdx = $unitOrder[$luRaw];
        } else {
            // Default: LargerOfTwoTemporalUnits('second', smallestUnit) → max(3, suIdx).
            $luIdx = max(3, $suIdx);
            $luRaw = $luIdx === $suIdx ? $suRaw : 'second';
        }

        // smallestUnit must not be larger than largestUnit.
        if ($suIdx > $luIdx) {
            throw new InvalidArgumentException(
                "smallestUnit \"{$suRaw}\" must not be larger than largestUnit \"{$luRaw}\".",
            );
        }

        $roundingMode = 'trunc';
        if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
            /** @psalm-suppress MixedArgument */
            $roundingMode = (string) $options['roundingMode'];
        }

        $increment = 1;
        if (array_key_exists('roundingIncrement', $options) && $options['roundingIncrement'] !== null) {
            /** @psalm-suppress MixedArgument */
            $increment = (int) $options['roundingIncrement'];
        }
        if ($increment < 1) {
            throw new InvalidArgumentException('roundingIncrement must be a positive integer.');
        }
        $maxInc = $maxIncrementByIndex[$suIdx];
        // increment must evenly divide maxInc AND be strictly less than maxInc.
        if ($increment >= $maxInc || $increment > 1 && ($maxInc % $increment) !== 0) {
            throw new InvalidArgumentException(
                "roundingIncrement {$increment} is invalid for unit \"{$suRaw}\" (max is {$maxInc}, must divide evenly and be < {$maxInc}).",
            );
        }

        // ---- Round ----
        // Round on the absolute magnitude. For directional modes (floor, ceil,
        // halfFloor, halfCeil), negate the mode when the diff is negative so that
        // e.g. floor(-376435.5h) = -376436h (toward -∞) rather than -376435h.
        // This matches TC39 DifferenceInstant step 15 (NegateTemporalRoundingMode).
        $nsInc = $nsPerUnitByIndex[$suIdx] * $increment;
        $diffSign = $diffNs <=> 0;
        $absDiff = $diffNs === PHP_INT_MIN ? PHP_INT_MAX : abs($diffNs);
        $effectiveMode = $roundingMode;
        if ($diffSign < 0) {
            $effectiveMode = match ($roundingMode) {
                'floor' => 'ceil',
                'ceil' => 'floor',
                'halfFloor' => 'halfCeil',
                'halfCeil' => 'halfFloor',
                default => $roundingMode,
            };
        }
        $roundedAbs = $nsInc === 1 ? $absDiff : self::roundAsIfPositive($absDiff, $nsInc, $effectiveMode);
        $roundedNs = $diffSign * $roundedAbs;

        // ---- Balance ----
        // Work with absolute value, restore sign at the end.
        // abs(PHP_INT_MIN) overflows to float; clamp to PHP_INT_MAX for sentinel support.
        $sign = $roundedNs <=> 0;
        $absNs = $roundedNs === PHP_INT_MIN ? PHP_INT_MAX : abs($roundedNs);

        $ns = $absNs;
        $us = 0;
        $ms = 0;
        $s = 0;
        $min = 0;
        $h = 0;

        if ($luIdx >= 1) { // at least microseconds
            $us = intdiv(num1: $ns, num2: 1_000);
            $ns = $ns - ($us * 1_000);
        }
        if ($luIdx >= 2) { // at least milliseconds
            $ms = intdiv(num1: $us, num2: 1_000);
            $us = $us - ($ms * 1_000);
        }
        if ($luIdx >= 3) { // at least seconds
            $s = intdiv(num1: $ms, num2: 1_000);
            $ms = $ms - ($s * 1_000);
        }
        if ($luIdx >= 4) { // at least minutes
            $min = intdiv(num1: $s, num2: 60);
            $s = $s - ($min * 60);
        }
        if ($luIdx >= 5) { // hours
            $h = intdiv(num1: $min, num2: 60);
            $min = $min - ($h * 60);
        }

        return new Duration(0, 0, 0, 0, $sign * $h, $sign * $min, $sign * $s, $sign * $ms, $sign * $us, $sign * $ns);
    }
}
