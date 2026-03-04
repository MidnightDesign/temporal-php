<?php

declare(strict_types=1);

namespace Temporal;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;

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
        get => self::floorDiv($this->epochNanoseconds, self::NS_PER_MILLISECOND);
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
    public static function from(self|string $item): self
    {
        if ($item instanceof self) {
            return new self($item->epochNanoseconds);
        }
        $text = $item;
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
        $pattern =
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})'
            . '[T ]'
            . '(\d{2})(?::?(\d{2})(?::?(\d{2}))?)?'
            . '([.,]\d+)?'
            . '(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)'
            . '((?:\[[^\]]*\])*)' // annotation section (group 8)
            . '$/i';

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
            $dateRest =
                '-'
                . substr(string: $dateRest, offset: 0, length: 2)
                . '-'
                . substr(string: $dateRest, offset: 2, length: 2);
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
        $maxDay = self::daysInMonth($yearNum, $monthNum);
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

        self::validateAnnotations($annotationSection, $text);

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
        if (
            $utcEpochSec < -$maxSec
            || $utcEpochSec > $maxSec
            || $utcEpochSec === $maxSec && $baseNs > 0
            || $utcEpochSec === -$maxSec && $baseNs < 0 // always false, but for clarity
        ) {
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
    public static function compare(self|string $one, self|string $two): int
    {
        $a = $one instanceof self ? $one : self::from($one);
        $b = $two instanceof self ? $two : self::from($two);
        return $a->epochNanoseconds <=> $b->epochNanoseconds;
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
    public function equals(mixed $other): bool
    {
        if ($other instanceof self) {
            return $this->epochNanoseconds === $other->epochNanoseconds;
        }
        if (!is_string($other)) {
            throw new InvalidArgumentException(
                'Temporal\\Instant::equals() argument must be a Temporal\\Instant or a string.',
            );
        }
        return $this->epochNanoseconds === self::from($other)->epochNanoseconds;
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
     * @param mixed $options null, an array of options, or any object (treated as empty options bag).
     * @throws InvalidArgumentException if options are invalid.
     * @throws \TypeError if $options is a non-null, non-array, non-object scalar.
     */
    public function toString(mixed $options = null): string
    {
        // TC39: any object (including closures) is a valid options bag treated as empty.
        if (is_object($options)) {
            $options = [];
        } elseif ($options !== null && !is_array($options)) {
            throw new \TypeError('Instant::toString() options must be null, an array, or an object.');
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

        // Extract whole seconds and sub-second nanoseconds.
        $secs = self::floorDiv($ns, self::NS_PER_SECOND);
        $subNs = $ns - ($secs * self::NS_PER_SECOND); // always 0–999_999_999

        $dt = new DateTimeImmutable('@' . $secs)->setTimezone(new DateTimeZone('UTC'));

        if ($isMinute) {
            return $dt->format('Y-m-d\TH:i\Z');
        }

        $base = $dt->format('Y-m-d\TH:i:s');

        if ($digits === -2) {
            // 'auto': strip trailing zeros.
            if ($subNs === 0) {
                return $base . 'Z';
            }
            $fraction = rtrim(sprintf('%09d', $subNs), characters: '0');
            return "{$base}.{$fraction}Z";
        }

        if ($digits === 0) {
            return $base . 'Z';
        }

        $fraction = substr(sprintf('%09d', $subNs), offset: 0, length: $digits);
        return "{$base}.{$fraction}Z";
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
     * @return array{int, int, int}  [sign (+1|-1), absSec, fracNs]
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
                // Key-value annotation.
                [$key] = explode(separator: '=', string: $content, limit: 2);

                // Key must be all-lowercase ASCII.
                if ($key !== strtolower($key)) {
                    throw new InvalidArgumentException(
                        "Invalid annotation key \"{$key}\" in \"{$original}\":" . ' annotation keys must be lowercase.',
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
                    // Unknown annotation type.
                    if ($critical) {
                        throw new InvalidArgumentException(
                            "Critical unknown annotation \"[!{$content}]\" in \"{$original}\".",
                        );
                    }

                    // Non-critical unknown annotation: ignore.
                }
            } else {
                // Time-zone annotation (no '=').
                ++$tzCount;
                if ($tzCount > 1) {
                    throw new InvalidArgumentException("Multiple time-zone annotations in \"{$original}\".");
                }

                // An offset-style TZ annotation must use only ±HH:MM (no seconds).
                // Pattern: starts with + or -, followed by digits and colons/dots.
                if (preg_match('/^[+-]/', $content) === 1) {
                    // It's an offset. It's invalid if it contains a seconds component:
                    // ±HH:MM:SS or ±HH:MM:SS.frac  →  reject.
                    if (
                        preg_match('/^[+-]\d{2}:\d{2}:\d{2}/', $content) === 1
                        || preg_match('/^[+-]\d{2}:\d{2}[.,]/', $content) === 1
                    ) {
                        throw new InvalidArgumentException(
                            "Sub-minute UTC offset in time-zone annotation in \"{$original}\".",
                        );
                    }
                    // Also reject bare-seconds forms like ±HHMMSS
                    // (the spec only allows ±HH:MM in named-offset TZ annotations for Instant).
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
     * Returns the number of days in the given month of the given year,
     * applying the proleptic Gregorian calendar (including for BCE years).
     */
    private static function daysInMonth(int $year, int $month): int
    {
        return match ($month) {
            1, 3, 5, 7, 8, 10, 12 => 31,
            4, 6, 9, 11 => 30,
            2 => self::isLeapYear($year) ? 29 : 28,
            default => 0,
        };
    }

    /**
     * Proleptic Gregorian leap-year test (valid for negative years too).
     */
    private static function isLeapYear(int $year): bool
    {
        return ($year % 4) === 0 && ($year % 100) !== 0 || ($year % 400) === 0;
    }

    /**
     * Strips the leading separator and truncates/pads the fractional-second
     * string to exactly 9 digits, then returns the nanosecond count.
     *
     * The Temporal spec allows arbitrarily long fraction strings; digits beyond
     * the 9th are discarded (truncation, not rounding).
     */
    private static function parseFraction(string $fractionRaw): int
    {
        $digits = substr($fractionRaw, offset: 1); // strip leading '.' or ','
        return (int) str_pad(substr($digits, offset: 0, length: 9), length: 9, pad_string: '0');
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
     * Integer division that always rounds towards negative infinity.
     *
     * PHP's intdiv() truncates towards zero; when the remainder is negative
     * the true floor is one less than the truncated quotient.
     */
    private static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        $r = $a - ($q * $b);
        return $r < 0 ? $q - 1 : $q;
    }
}
