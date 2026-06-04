<?php

declare(strict_types=1);

namespace Temporal\Spec;

use DateTimeImmutable;
use DateTimeZone;
use Stringable;
use Temporal\Exception\RangeError;
use Temporal\Exception\TypeError;
use Temporal\Spec\Internal\CalendarMath;
use Temporal\Spec\Internal\TimeZoneHelper;

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
     * @psalm-api
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $epochMilliseconds {
        get {
            [$epochSec, $subNs] = $this->getEpochParts();
            // ms = floor(trueNs / 1e6); decompose to avoid an int64-overflowing
            // intermediate trueNs for over-int64 instants.
            return ($epochSec * 1_000) + CalendarMath::floorDiv($subNs, self::NS_PER_MILLISECOND);
        }
    }

    /** @psalm-suppress PropertyNotSetInConstructor — set unconditionally in constructor */
    public readonly int $epochNanoseconds;

    /**
     * True UTC epoch seconds — set when {@see $epochNanoseconds} is a sentinel
     * (PHP_INT_MIN/MAX) because the actual value overflows int64 nanoseconds.
     * Mirrors ZonedDateTime's design so over-int64 (but in-spec) instants
     * survive construction, arithmetic, and conversion without clamping.
     */
    private ?int $trueEpochSec = null;

    /** Sub-second nanoseconds (0–999_999_999) paired with $trueEpochSec. */
    private int $trueSubNs = 0;

    /**
     * @param int|float|string|bool $epochNanoseconds Nanoseconds since the Unix epoch.
     *        An int is taken verbatim (the PHP stand-in for an in-range BigInt). A bool
     *        is coerced (true→1, false→0) to mirror TC39 ToBigInt(boolean). A decimal
     *        integer string carries an over-int64 value, decomposed exactly so full
     *        precision is preserved (see {@see decimalStringToEpochParts()}). A float is
     *        rejected: TC39 coerces the argument with ToBigInt and ToBigInt(Number) is a
     *        TypeError.
     * @throws TypeError if epochNanoseconds is a float.
     * @throws RangeError if a string is not a decimal integer, or the value is outside
     *         the representable Temporal nanosecond range.
     */
    public function __construct(int|float|string|bool $epochNanoseconds)
    {
        // TC39 coerces the argument with ToBigInt; ToBigInt(true)=1n, ToBigInt(false)=0n.
        if (is_bool($epochNanoseconds)) {
            $epochNanoseconds = (int) $epochNanoseconds;
        }
        if (is_string($epochNanoseconds)) {
            // Exact decomposition of a (possibly over-int64) decimal integer string
            // into floor epoch-seconds + sub-second nanoseconds, preserving full
            // precision the lossy float path cannot.
            if (preg_match('/^[+-]?\d+$/', $epochNanoseconds) !== 1) {
                throw new RangeError('epochNanoseconds string must be a decimal integer.');
            }
            [$sec, $subNs] = self::decimalStringToEpochParts($epochNanoseconds);
            [$this->epochNanoseconds, $this->trueEpochSec, $this->trueSubNs] = self::normalizeEpochParts($sec, $subNs);
            return;
        }
        // ToBigInt(Number) is a TypeError, so a PHP float (our Number stand-in) is
        // rejected; an over-int64 instant is supplied as a decimal string instead.
        if (is_float($epochNanoseconds)) {
            throw new TypeError('epochNanoseconds must be an integer, not a float.');
        }
        $this->epochNanoseconds = $epochNanoseconds;
    }

    /**
     * Decomposes a decimal-integer nanosecond string into floor epoch-seconds and
     * sub-second nanoseconds in [0, 1e9) using exact string/integer math.
     *
     * @return array{int, int} [epochSec, subNs]
     */
    private static function decimalStringToEpochParts(string $decimal): array
    {
        $negative = $decimal[0] === '-';
        $digits = ltrim($decimal, characters: '+-');
        $digits = ltrim($digits, characters: '0');
        if ($digits === '') {
            return [0, 0];
        }
        // Split off the last 9 digits as the sub-second nanosecond magnitude.
        if (strlen($digits) <= 9) {
            $secMagnitude = 0;
            $subMagnitude = (int) $digits;
        } else {
            $secMagnitude = (int) substr($digits, offset: 0, length: -9);
            $subMagnitude = (int) substr($digits, offset: -9);
        }
        if (!$negative) {
            return [$secMagnitude, $subMagnitude];
        }
        // Negative: floor toward -inf. -(sec.frac) = -(sec) - frac; floor folds the
        // fractional part down by one second when it is non-zero.
        if ($subMagnitude === 0) {
            return [-$secMagnitude, 0];
        }
        return [-$secMagnitude - 1, self::NS_PER_SECOND - $subMagnitude];
    }

    /**
     * Normalizes true epoch parts and resolves the public {@see $epochNanoseconds}
     * field value (a sentinel for over-int64 instants) plus the true parts. Shared
     * by the constructor and {@see fromEpochParts()} so normalization, the spec
     * range check, and the over-int64 sentinel rule live in one place.
     *
     * @return array{int, ?int, int} [epochNanoseconds, trueEpochSec, trueSubNs];
     *         trueEpochSec is null when the value fits int64 exactly.
     * @throws RangeError if the result is outside the representable Temporal range.
     */
    private static function normalizeEpochParts(int $epochSec, int $subNs): array
    {
        // Normalize sub-second nanoseconds into [0, 1e9).
        if ($subNs < 0 || $subNs >= self::NS_PER_SECOND) {
            $carry = CalendarMath::floorDiv($subNs, self::NS_PER_SECOND);
            $epochSec += $carry;
            $subNs -= $carry * self::NS_PER_SECOND;
        }

        $maxSec = 8_640_000_000_000;
        if ($epochSec < -$maxSec || $epochSec > $maxSec || $epochSec === $maxSec && $subNs > 0) {
            throw new RangeError('Instant result is outside the representable nanosecond range.');
        }

        // When the full nanosecond value fits int64, store it exactly; otherwise
        // clamp the public field to a sentinel and carry the true parts.
        $maxSecForNs = 9_223_372_035;
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            return [$epochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX, $epochSec, $subNs];
        }
        return [($epochSec * self::NS_PER_SECOND) + $subNs, null, 0];
    }

    /**
     * Creates an Instant from true UTC epoch seconds and sub-second nanoseconds,
     * preserving the true value when it overflows int64 by clamping the public
     * {@see $epochNanoseconds} field to a sentinel while storing the true parts.
     *
     * Mirrors ZonedDateTime::fromEpochParts(). The seam used by the constructor
     * paths, arithmetic, rounding, and the toInstant()/toZonedDateTimeISO()
     * converters so every over-int64 instant carries its true value.
     *
     * The test262 transpiler renders over-int64 BigInt epoch-seconds (only ever
     * produced for deliberately out-of-range fixtures, since every valid instant's
     * epochSec ≤ 8.64e12 fits int64) as PHP float literals, so $epochSec/$subNs
     * accept int|float. A finite over-int64 float epoch-second is always outside the
     * spec range and therefore throws RangeError — never a TypeError.
     *
     * @internal
     * @psalm-internal Temporal\Spec
     * @throws RangeError if a part is a non-integer float or the result
     *         is outside the representable Temporal range.
     */
    public static function fromEpochParts(int|float $epochSec, int|float $subNs): self
    {
        // Spec range: |epochNs| ≤ 8_640_000_000_000 × 10⁹. Out-of-range results
        // throw RangeError — the project's range-violation type,
        // to which the test262 transpiler maps the JS-spec RangeError. Normalization,
        // the range check, and the over-int64 sentinel are shared with the
        // constructor via normalizeEpochParts().
        $maxSec = 8_640_000_000_000;
        if (is_float($epochSec)) {
            // A finite over-int64 float epochSec cannot be in the ±8.64e12 s spec
            // range, so it is unconditionally out of range. (float)PHP_INT_MAX rounds
            // up past PHP_INT_MAX, so compare against the spec bound directly.
            if (!is_finite($epochSec) || $epochSec > (float) $maxSec || $epochSec < -(float) $maxSec) {
                throw new RangeError('Instant result is outside the representable nanosecond range.');
            }
            $epochSec = (int) $epochSec;
        }
        if (is_float($subNs)) {
            if (
                !is_finite($subNs)
                || floor($subNs) !== $subNs
                || $subNs > (float) PHP_INT_MAX
                || $subNs < (float) PHP_INT_MIN
            ) {
                throw new RangeError('Instant result is outside the representable nanosecond range.');
            }
            $subNs = (int) $subNs;
        }

        [$epochNs, $trueSec, $trueSubNs] = self::normalizeEpochParts($epochSec, $subNs);
        $self = new self($epochNs);
        $self->trueEpochSec = $trueSec;
        $self->trueSubNs = $trueSubNs;
        return $self;
    }

    /**
     * Returns the true UTC epoch seconds and sub-second nanoseconds, handling
     * sentinel {@see $epochNanoseconds} values transparently.
     *
     * @return array{int, int} [epochSec, subNs] where subNs is 0–999_999_999
     */
    private function getEpochParts(): array
    {
        if ($this->trueEpochSec !== null) {
            return [$this->trueEpochSec, $this->trueSubNs];
        }
        $epochSec = CalendarMath::floorDiv($this->epochNanoseconds, self::NS_PER_SECOND);
        $subNs = $this->epochNanoseconds - ($epochSec * self::NS_PER_SECOND);
        return [$epochSec, $subNs];
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
     * @throws RangeError if the string cannot be parsed, has no UTC offset,
     *                    or represents a timestamp outside the nanosecond range.
     * @throws TypeError if $item is a non-string, non-Instant object.
     */
    public static function from(string|object $item): self
    {
        if ($item instanceof self) {
            [$epochSec, $subNs] = $item->getEpochParts();
            return self::fromEpochParts($epochSec, $subNs);
        }
        if (!is_string($item)) {
            throw new TypeError(sprintf(
                'Temporal.Instant.from() requires an Instant or string, got %s.',
                get_debug_type($item),
            ));
        }
        $text = $item;
        // Reject more than 9 fractional-second digits (time part or offset fraction).
        // TC39 spec: strings with 10+ fractional digits are invalid (test262 argument-string-too-many-decimals).
        if (preg_match('/[.,]\d{10,}/', $text) === 1) {
            throw new RangeError("Invalid Instant string \"{$text}\": fractional seconds may have at most 9 digits.");
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
            throw new RangeError("Invalid Instant string \"{$text}\": expected ISO 8601 with a UTC offset.");
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
            throw new RangeError("Invalid Instant string \"{$text}\": year -000000 (negative zero) is not valid.");
        }
        $monthNum = (int) substr(string: $dateRest, offset: 1, length: 2);
        $dayNum = (int) substr(string: $dateRest, offset: 4, length: 2);
        $hourNum = (int) $hour;
        $minNum = (int) $min;
        $secNum = $sec !== '' ? (int) $sec : 0;

        if ($monthNum < 1 || $monthNum > 12) {
            throw new RangeError("Invalid Instant string \"{$text}\": month out of range.");
        }
        $maxDay = CalendarMath::calcDaysInMonth($yearNum, $monthNum);
        if ($dayNum < 1 || $dayNum > $maxDay) {
            throw new RangeError("Invalid Instant string \"{$text}\": day out of range.");
        }
        if ($hourNum > 23) {
            throw new RangeError("Invalid Instant string \"{$text}\": hour out of range.");
        }
        if ($minNum > 59) {
            throw new RangeError("Invalid Instant string \"{$text}\": minute out of range.");
        }

        // Leap second: 60 is valid and maps to the last nanosecond of :59 (spec §8.5.6).
        $sec60 = $secNum === 60;
        $normalSec = $sec60 ? 59 : $secNum;
        if (!$sec60 && $secNum > 59) {
            throw new RangeError("Invalid Instant string \"{$text}\": second out of range.");
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
            throw new RangeError("Could not parse \"{$text}\".");
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
            throw new RangeError("Instant string \"{$text}\" is outside the representable nanosecond range.");
        }

        // For dates far from the Unix epoch (years roughly outside 1678–2262),
        // utcEpochSec × NS_PER_SECOND would overflow PHP's int64. fromEpochParts()
        // stores the public field as a saturated sentinel while preserving the
        // true epoch seconds / sub-ns, so spec-valid but int64-unrepresentable
        // instants survive construction intact.
        return self::fromEpochParts($utcEpochSec, $baseNs);
    }

    /**
     * Creates an Instant from a Unix timestamp in milliseconds.
     *
     * @param int|float|null $epochMilliseconds Milliseconds since the Unix epoch.
     *        Must be a finite integer value within ±8_640_000_000_000_000.
     * @throws RangeError if the value is not a finite integer or is out of range.
     */
    public static function fromEpochMilliseconds(int|float|null $epochMilliseconds = null): self
    {
        if ($epochMilliseconds === null) {
            throw new RangeError('epochMilliseconds must be provided.');
        }
        if (is_float($epochMilliseconds)) {
            if (!is_finite($epochMilliseconds) || floor($epochMilliseconds) !== $epochMilliseconds) {
                throw new RangeError("epochMilliseconds must be a finite integer value, got {$epochMilliseconds}.");
            }
            $epochMilliseconds = (int) $epochMilliseconds;
        }
        $limit = 8_640_000_000_000_000;
        if ($epochMilliseconds < -$limit || $epochMilliseconds > $limit) {
            throw new RangeError("epochMilliseconds {$epochMilliseconds} is outside the valid range of ±{$limit}.");
        }
        // Guard against int64 overflow when multiplying ms × 10^6 to get nanoseconds.
        // Threshold: floor(PHP_INT_MAX / NS_PER_MILLISECOND) = 9_223_372_036_854.
        // Beyond it, decompose into (epochSec, subNs) and let fromEpochParts()
        // store the true value behind a saturated sentinel.
        $threshold = 9_223_372_036_854;
        if ($epochMilliseconds > $threshold || $epochMilliseconds < -$threshold) {
            $epochSec = CalendarMath::floorDiv($epochMilliseconds, 1_000);
            $subMs = $epochMilliseconds - ($epochSec * 1_000);
            return self::fromEpochParts($epochSec, $subMs * self::NS_PER_MILLISECOND);
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
        [$aSec, $aSubNs] = $a->getEpochParts();
        [$bSec, $bSubNs] = $b->getEpochParts();
        $cmp = $aSec <=> $bSec;
        return $cmp !== 0 ? $cmp : $aSubNs <=> $bSubNs;
    }

    /**
     * Coerces a non-Instant value to an Instant by parsing it as an ISO string.
     *
     * Per TC39, the argument undergoes ToTemporalInstant, which performs ToString
     * on a non-Instant value and then parses the result. A foreign object
     * stringifies and fails to parse, so it surfaces a RangeError; a Symbol's
     * ToString throws a TypeError (modelled here by the JsSymbol sentinel's
     * throwing {@see \Stringable::__toString()}). Non-string, non-object
     * primitives (number/bool/null/bigint) never reach this method — the typed
     * `string|object` signature rejects them with a native TypeError first.
     *
     * @throws TypeError if $arg is a Symbol (Stringable whose cast throws).
     * @throws RangeError if $arg is a foreign object or an invalid ISO string.
     */
    private static function coerceToInstant(string|object $arg): self
    {
        if (!is_string($arg)) {
            if ($arg instanceof Stringable) {
                // JsSymbol's __toString() throws TypeError here; a genuine
                // Stringable is parsed below and any parse failure is a RangeError.
                $arg = (string) $arg;
            } else {
                // A non-string, non-Stringable value (number, bool, plain object,
                // Temporal type other than Instant) is a wrong-TYPE argument —
                // TC39 throws TypeError before any string coercion is attempted.
                throw new TypeError('Temporal\\Instant argument must be an Instant or an ISO string.');
            }
        }
        return self::from($arg);
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns true when both Instants represent the same point in time.
     *
     * Accepts either an Instant instance or a string parseable by {@see from()}.
     * Any other type throws TypeError (matches JS coercion failure behaviour).
     *
     * @throws TypeError if $other is not an Instant or a string.
     * @throws RangeError if $other is an invalid ISO string.
     */
    public function equals(string|object $other): bool
    {
        $otherInst = $other instanceof self ? $other : self::coerceToInstant($other);
        [$aSec, $aSubNs] = $this->getEpochParts();
        [$bSec, $bSubNs] = $otherInst->getEpochParts();
        return $aSec === $bSec && $aSubNs === $bSubNs;
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
     * @throws RangeError if options are invalid.
     * @throws TypeError if the timeZone option is a non-string.
     */
    public function toString(array|object|null $options = null): string
    {
        $options = is_object($options) ? get_object_vars($options) : $options;

        // $digits: -2 = 'auto' (strip trailing zeros), -1 = minute format, 0-9 = fixed.
        $digits = -2;
        $roundMode = 'trunc';
        $isMinute = false;
        $increment = 1;

        if ($options !== null) {
            // fractionalSecondDigits
            if (array_key_exists('fractionalSecondDigits', $options)) {
                /** @var mixed $fsd */
                $fsd = $options['fractionalSecondDigits'];
                // A Stringable in this Number-or-String position is coerced via
                // ToString: the JsSymbol sentinel's throwing __toString surfaces a
                // TypeError, while any other Stringable yields a string that is not
                // "auto" and therefore a RangeError below.
                if (!is_string($fsd) && !is_int($fsd) && !is_float($fsd) && $fsd instanceof Stringable) {
                    $fsd = (string) $fsd;
                }
                if ($fsd !== 'auto') {
                    if ($fsd === null || is_bool($fsd) || is_string($fsd)) {
                        throw new RangeError("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if (is_float($fsd)) {
                        if (is_nan($fsd) || is_infinite($fsd)) {
                            throw new RangeError("fractionalSecondDigits must be 'auto' or a finite integer 0–9.");
                        }
                        $fsd = (int) floor($fsd);
                    } elseif (!is_int($fsd)) {
                        throw new RangeError("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if ($fsd < 0 || $fsd > 9) {
                        throw new RangeError("fractionalSecondDigits {$fsd} is out of range (must be 0–9).");
                    }
                    $digits = $fsd;
                }
            }

            // smallestUnit overrides fractionalSecondDigits
            if (array_key_exists('smallestUnit', $options) && $options['smallestUnit'] !== null) {
                $su = self::coerceOptionString($options['smallestUnit']);
                [$digits, $isMinute] = match ($su) {
                    'minute', 'minutes' => [-1, true],
                    'second', 'seconds' => [0, false],
                    'millisecond', 'milliseconds' => [3, false],
                    'microsecond', 'microseconds' => [6, false],
                    'nanosecond', 'nanoseconds' => [9, false],
                    default => throw new RangeError("Invalid smallestUnit \"{$su}\"."),
                };
            }

            // roundingMode (null → default 'trunc'). Validated even on the fast
            // (increment === 1) path so an unknown / non-string mode is rejected
            // rather than silently accepted.
            if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
                $roundMode = self::coerceOptionString($options['roundingMode']);
                self::validateRoundingMode($roundMode);
            }

            // timeZone: must be a string; non-string (including null) → TypeError.
            if (array_key_exists('timeZone', $options)) {
                /** @var mixed $tzVal */
                $tzVal = $options['timeZone'];
                if (!is_string($tzVal)) {
                    throw new TypeError('timeZone must be a string.');
                }
                self::validateTimeZoneString($tzVal);
            }
        }

        // Resolve timezone offset in seconds (null = UTC / 'Z' suffix).
        $tzOffsetSec = null;
        $ianaTimeZone = null;
        if ($options !== null && array_key_exists('timeZone', $options)) {
            $tzStr = (string) $options['timeZone'];
            $resolved = self::resolveTimeZoneOffsetSeconds($tzStr);
            if ($resolved !== null) {
                $tzOffsetSec = $resolved;
            } else {
                // IANA timezone: extract the timezone name from the string.
                // For bracket annotations, extract the bracket content.
                $bm2 = null;
                if (preg_match('/\[([^\]]+)\]/', $tzStr, $bm2) === 1) {
                    $ianaTimeZone = $bm2[1];
                } else {
                    $ianaTimeZone = $tzStr;
                }
            }
        }

        // Determine the rounding increment in nanoseconds.
        if ($isMinute) {
            $increment = 60_000_000_000;
        } elseif ($digits >= 0) {
            $increment = match ($digits) {
                0 => 1_000_000_000,
                1 => 100_000_000,
                2 => 10_000_000,
                3 => 1_000_000,
                4 => 100_000,
                5 => 10_000,
                6 => 1_000,
                7 => 100,
                8 => 10,
                default => 1,
            };
        }
        // For 'auto' ($digits === -2), increment stays 1 (no rounding).

        // Round using RoundNumberToIncrementAsIfPositive, decomposed into
        // (seconds, sub-ns) so the combined nanosecond value never has to fit
        // int64 — keeping over-int64 instants intact.
        [$trueSec, $trueSubNs] = $this->getEpochParts();
        [$secs, $subNs] = $increment === 1
            ? [$trueSec, $trueSubNs]
            : self::roundEpochParts($trueSec, $trueSubNs, $increment, $roundMode);

        // For IANA timezones, compute the offset at this epoch.
        if ($ianaTimeZone !== null) {
            $tzOffsetSec = self::ianaOffsetSeconds($ianaTimeZone, $secs);
        }

        // Apply timezone offset to get local datetime.
        $localSecs = $tzOffsetSec !== null ? $secs + $tzOffsetSec : $secs;
        $dt = new DateTimeImmutable(sprintf('@%d', $localSecs))->setTimezone(new DateTimeZone('UTC'));

        // Build the UTC-offset suffix: 'Z' or ±HH:MM (always rounded to minutes for Instant).
        if ($tzOffsetSec === null) {
            $tzSuffix = 'Z';
        } else {
            $roundedMin = (int) round((float) $tzOffsetSec / 60.0);
            $absMin = abs($roundedMin);
            $tzH = intdiv(num1: $absMin, num2: 60);
            $tzM = $absMin % 60;
            $tzSign = $roundedMin < 0 ? '-' : '+';
            $tzSuffix = sprintf('%s%02d:%02d', $tzSign, $tzH, $tzM);
        }

        // Year formatting: normal 4-digit, extended ±YYYYYY when out of range.
        // PHP's 'Y' format token does not emit the signed 6-digit extended form
        // the spec requires for years <0 or >9999 (now reachable since the true
        // value is preserved beyond the int64-clamp).
        $year = (int) $dt->format('Y');
        if ($year < 0) {
            $yearStr = sprintf('-%06d', abs($year));
        } elseif ($year > 9999) {
            $yearStr = sprintf('+%06d', $year);
        } else {
            $yearStr = sprintf('%04d', $year);
        }
        $datePart = $yearStr . $dt->format('-m-d');

        if ($isMinute) {
            return $datePart . $dt->format('\TH:i') . $tzSuffix;
        }

        $base = $datePart . $dt->format('\TH:i:s');

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
     * @throws RangeError for invalid timezone strings.
     */
    private static function validateTimeZoneString(string $tz): void
    {
        // Reject empty string.
        if ($tz === '') {
            throw new RangeError('Invalid timeZone "": empty string is not a valid timezone identifier.');
        }
        // Reject minus-zero extended year.
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $tz) === 1) {
            throw new RangeError("Invalid timeZone \"{$tz}\": minus-zero year.");
        }
        // Reject bracket annotation with a seconds component (e.g. [+23:59:60]).
        $bm = null;
        if (preg_match('/\[([^\]]+)\]/', $tz, $bm) === 1) {
            if (preg_match('/^[+\-]\d{2}:\d{2}:\d{2}/', $bm[1]) === 1) {
                throw new RangeError("Invalid timeZone \"{$tz}\": sub-minute seconds in bracket annotation.");
            }
        }
        // Pure UTC-offset strings (no T date/time part): must be ±HH:MM or ±HHMM.
        if (preg_match('/^[+\-]\d{2}/', $tz) === 1 && !str_contains($tz, 'T') && !str_contains($tz, 't')) {
            if (preg_match('/^[+\-]\d{2}:\d{2}(?:$|[^:\d])/', $tz) !== 1 && preg_match('/^[+\-]\d{4}$/', $tz) !== 1) {
                throw new RangeError("Invalid timeZone \"{$tz}\": offset contains seconds or is in an invalid format.");
            }
            return;
        }
        // Datetime strings: must have Z, an offset, or a bracket annotation.
        if (preg_match('/\d{4,}-\d{2}-\d{2}[Tt]|\d{8}[Tt]/', $tz) === 1) {
            if (preg_match('/T\d{2}:?\d{2}(?::?\d{2})?(?:\.\d+)?(?:Z|[+\-]|\[)/i', $tz) !== 1) {
                throw new RangeError("Invalid timeZone \"{$tz}\": bare datetime without Z, offset, or bracket.");
            }
            // Inline offset must not include a seconds component (e.g. -07:00:01).
            if (preg_match('/[+\-]\d{2}:\d{2}:\d{2}(?!\])/i', $tz) === 1) {
                throw new RangeError("Invalid timeZone \"{$tz}\": inline offset contains a seconds component.");
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
    private static function resolveTimeZoneOffsetSeconds(string $tz): ?int
    {
        // 'UTC' (case-insensitive)
        if (strtoupper($tz) === 'UTC') {
            return 0;
        }
        // Pure UTC-offset strings: ±HH:MM or ±HHMM
        $m = null;
        if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $tz, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            return $sign * (((int) $m[2] * 3600) + ((int) $m[3] * 60));
        }
        if (preg_match('/^([+\-])(\d{2})(\d{2})$/', $tz, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            return $sign * (((int) $m[2] * 3600) + ((int) $m[3] * 60));
        }
        // Datetime strings: bracket annotation takes precedence.
        $bm = null;
        if (preg_match('/\[([^\]]+)\]/', $tz, $bm) === 1) {
            /** @var non-empty-string $bracket */
            $bracket = $bm[1];
            if (strtoupper($bracket) === 'UTC') {
                return 0;
            }
            $om = null;
            if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $bracket, $om) === 1) {
                $sign = $om[1] === '+' ? 1 : -1;
                return $sign * (((int) $om[2] * 3600) + ((int) $om[3] * 60));
            }
            // IANA timezone in bracket: return null to signal epoch-dependent resolution.
            try {
                new \DateTimeZone($bracket);
                return null; // Caller will use ianaOffsetSeconds
            } catch (\Exception $e) {
                // Not a valid timezone; ignore the error and fall through to
                // the inline-offset path below.
                unset($e);
            }
        }
        // Datetime strings without bracket: use inline offset or Z.
        $om = null;
        if (preg_match('/[Tt].*?(Z|([+\-])(\d{2}):(\d{2}))/i', $tz, $om) === 1) {
            if ($om[1] === 'Z' || $om[1] === 'z') {
                return 0;
            }
            /** @var array{non-falsy-string, non-falsy-string, '+'|'-', non-falsy-string, non-falsy-string} $om */
            $sign = $om[2] === '+' ? 1 : -1;
            return $sign * (((int) $om[3] * 3600) + ((int) $om[4] * 60));
        }
        // IANA timezone name: look up the offset at the current epoch.
        // This method doesn't have access to the instant's epoch, so we
        // validate the timezone now and defer the offset computation to the caller.
        self::validateTimeZoneString($tz);
        return null; // Signal to caller that it's an IANA timezone needing epoch-relative offset.
    }

    /**
     * Resolves an IANA timezone to an offset in seconds at a given epoch second.
     */
    private static function ianaOffsetSeconds(string $tz, int $epochSec): int
    {
        if ($tz === '') {
            return 0;
        }
        try {
            $phpTz = new \DateTimeZone($tz);
            return $phpTz->getOffset(new \DateTimeImmutable(sprintf('@%d', $epochSec)));
        } catch (\Exception) {
            return 0;
        }
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @psalm-suppress UnusedParam toJSON ignores its argument per TC39 spec
     * @psalm-api
     */
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
        /** @var array<string, mixed> $opts */
        $opts = is_object($options) ? get_object_vars($options) : $options ?? [];

        /** @var mixed $tzOpt */
        $tzOpt = $opts['timeZone'] ?? null;
        $timeZone = is_string($tzOpt) ? $tzOpt : 'UTC';

        $opts['_locale'] = $locale;
        $formatter = CalendarMath::buildIntlFormatter($locale, $timeZone, $opts);
        [$seconds] = $this->getEpochParts();
        $result = $formatter->format($seconds);

        return $result !== false ? $result : $this->toString();
    }

    /**
     * Returns a ZonedDateTime for this Instant in the given time zone.
     *
     * @param string $timeZone A timezone string: 'UTC', '±HH:MM', or an ISO datetime string
     *                        with an inline offset or bracket annotation (e.g. '2020-01-01T00:00Z').
     * @psalm-api used by test262 scripts
     * @throws RangeError if the timezone string is invalid (empty, sub-minute offset, etc.).
     */
    public function toZonedDateTimeISO(string $timeZone): ZonedDateTime
    {
        $tzId = self::parseTimeZoneId($timeZone);
        [$epochSec, $subNs] = $this->getEpochParts();
        return ZonedDateTime::fromInstantParts($epochSec, $subNs, $tzId);
    }

    /**
     * Parses a timezone string and returns its canonical timezone ID.
     *
     * Accepts: 'UTC' (case-insensitive), '±HH:MM', or ISO datetime strings
     * with an inline offset (Z or ±HH:MM) or a bracket annotation [tzId].
     * Sub-minute offsets, bare datetimes, and empty strings are rejected.
     *
     * @throws RangeError for invalid timezone strings.
     */
    private static function parseTimeZoneId(string $tz): string
    {
        if ($tz === '') {
            throw new RangeError('Time zone string must not be empty.');
        }
        // 'UTC' (case-insensitive).
        if (strtoupper($tz) === 'UTC') {
            return 'UTC';
        }
        // Reject minus-zero extended year.
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $tz) === 1) {
            throw new RangeError("Invalid time zone string \"{$tz}\": minus-zero year.");
        }

        // Determine if this looks like a datetime (has a T-separator after a date part).
        $isDatetime = preg_match('/\d{4,}-\d{2}-\d{2}[Tt]|\d{8}[Tt]/', $tz) === 1;

        if ($isDatetime) {
            // Bracket annotation takes precedence over the inline offset.
            $bm = null;
            if (preg_match('/\[(!?[^\]]+)\]/', $tz, $bm) === 1) {
                /** @var non-empty-string $bracket */
                $bracket = $bm[1];
                // Sub-minute offset in bracket: reject.
                if (preg_match('/^[+\-]\d{2}:\d{2}:\d{2}/', $bracket) === 1) {
                    throw new RangeError(
                        "Invalid time zone string \"{$tz}\": sub-minute offset in bracket annotation.",
                    );
                }
                if (strtoupper($bracket) === 'UTC') {
                    return 'UTC';
                }
                if (preg_match('/^[+\-]\d{2}:\d{2}$/', $bracket) === 1) {
                    return $bracket;
                }
                // Try as IANA timezone name.
                try {
                    new \DateTimeZone($bracket);
                    return TimeZoneHelper::normalizeTimezoneId($bracket);
                } catch (\Exception) {
                    throw new RangeError(
                        "Invalid time zone string \"{$tz}\": unsupported bracket timezone \"{$bracket}\".",
                    );
                }
            }
            // No bracket: inline offset (Z or ±HH:MM) required.
            // Reject sub-minute inline offset.
            if (preg_match('/[+\-]\d{2}:\d{2}:\d{2}/i', $tz) === 1) {
                throw new RangeError("Invalid time zone string \"{$tz}\": inline offset contains a seconds component.");
            }
            // Extract inline offset (Z or ±HH:MM at end or after time part).
            if (preg_match('/[Zz](?:\[|$)/', $tz) === 1) {
                return 'UTC';
            }
            $om = null;
            if (preg_match('/([+\-]\d{2}:\d{2})(?:\[|$)/', $tz, $om) === 1) {
                return $om[1];
            }
            // Bare datetime with no offset and no bracket.
            throw new RangeError("Invalid time zone string \"{$tz}\": bare datetime without Z, offset, or bracket.");
        }

        // Pure UTC-offset strings: accept only ±HH:MM (no seconds component).
        if (preg_match('/^[+\-]\d{2}:\d{2}$/', $tz) === 1) {
            return $tz;
        }
        // ±HHMM (compact form) → normalize to ±HH:MM.
        $m = null;
        if (preg_match('/^([+\-])(\d{2})(\d{2})$/', $tz, $m) === 1) {
            return sprintf('%s%s:%s', $m[1], $m[2], $m[3]);
        }
        // Anything with more than ±HH:MM (seconds or fractional) → reject.
        if (preg_match('/^[+\-]\d{2}:\d{2}[:.].*/i', $tz) === 1) {
            throw new RangeError(
                "Invalid time zone string \"{$tz}\": sub-minute offset is not a valid timezone identifier.",
            );
        }

        // IANA timezone name: validate via PHP DateTimeZone.
        try {
            new \DateTimeZone($tz);
            return TimeZoneHelper::normalizeTimezoneId($tz);
        } catch (\Exception) {
            throw new RangeError("Invalid time zone string \"{$tz}\": not a recognized timezone identifier.");
        }
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
     * @throws RangeError if the duration contains calendar fields or the result is out of range.
     */
    public function add(string|array|object $duration): self
    {
        $d = Duration::from($duration);
        if ($d->years !== 0 || $d->months !== 0 || $d->weeks !== 0 || $d->days !== 0) {
            throw new RangeError(
                'Temporal\\Instant::add() does not support calendar fields (years, months, weeks, days).',
            );
        }
        [$epochSec, $subNs] = $this->getEpochParts();
        return self::addNsOffset(
            $epochSec,
            $subNs,
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
     * @throws RangeError if the duration contains calendar fields or the result is out of range.
     */
    public function subtract(string|array|object $duration): self
    {
        $d = Duration::from($duration);
        if ($d->years !== 0 || $d->months !== 0 || $d->weeks !== 0 || $d->days !== 0) {
            throw new RangeError(
                'Temporal\\Instant::subtract() does not support calendar fields (years, months, weeks, days).',
            );
        }
        [$epochSec, $subNs] = $this->getEpochParts();
        return self::addNsOffset(
            $epochSec,
            $subNs,
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
     * @throws RangeError if smallestUnit is missing/invalid or roundingIncrement is invalid.
     * @throws TypeError if smallestUnit or roundingMode is a non-string.
     */
    public function round(string|array|object $roundTo): self
    {
        if (is_string($roundTo)) {
            $roundTo = ['smallestUnit' => $roundTo];
        } elseif (is_object($roundTo)) {
            $roundTo = get_object_vars($roundTo);
        }

        /** @var mixed $suRaw */
        $suRaw = $roundTo['smallestUnit'] ?? null;
        if ($suRaw === null) {
            throw new RangeError('Temporal\\Instant::round() requires smallestUnit.');
        }
        if (!is_string($suRaw)) {
            throw new TypeError('smallestUnit must be a string.');
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
            throw new RangeError("Invalid smallestUnit \"{$suRaw}\" for Temporal\\Instant::round().");
        }
        [$nsPerUnit, $maxDivisor] = $unitMap[$suRaw];

        $roundingMode = 'halfExpand';
        if (array_key_exists('roundingMode', $roundTo) && $roundTo['roundingMode'] !== null) {
            /** @var mixed $rmRaw */
            $rmRaw = $roundTo['roundingMode'];
            if (!is_string($rmRaw)) {
                throw new TypeError('roundingMode must be a string.');
            }
            self::validateRoundingMode($rmRaw);
            $roundingMode = $rmRaw;
        }

        $increment = 1;
        if (array_key_exists('roundingIncrement', $roundTo) && $roundTo['roundingIncrement'] !== null) {
            // Per TC39 ToTemporalRoundingIncrement: GetOption with type «Number» calls ToNumber,
            // which coerces booleans/numeric strings. CalendarMath::toFiniteInt mirrors that.
            $increment = CalendarMath::toFiniteInt($roundTo['roundingIncrement'], 'roundingIncrement');
        }
        if ($increment < 1) {
            throw new RangeError('roundingIncrement must be a positive integer.');
        }
        if ($increment > $maxDivisor || ($maxDivisor % $increment) !== 0) {
            throw new RangeError(
                "roundingIncrement {$increment} does not evenly divide {$maxDivisor} for unit \"{$suRaw}\".",
            );
        }

        $nsIncrement = $nsPerUnit * $increment;
        [$trueSec, $trueSubNs] = $this->getEpochParts();
        [$roundedSec, $roundedSubNs] = self::roundEpochParts($trueSec, $trueSubNs, $nsIncrement, $roundingMode);
        return self::fromEpochParts($roundedSec, $roundedSubNs);
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
        [$aSec, $aSubNs] = $this->getEpochParts();
        [$bSec, $bSubNs] = $otherInst->getEpochParts();
        return self::diffInstant($aSec - $bSec, $aSubNs - $bSubNs, $options);
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
        [$aSec, $aSubNs] = $otherInst->getEpochParts();
        [$bSec, $bSubNs] = $this->getEpochParts();
        return self::diffInstant($aSec - $bSec, $aSubNs - $bSubNs, $options);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Allowed roundingMode option values (TC39 RoundingMode). */
    private const array ROUNDING_MODES = [
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

    /**
     * Coerces a mixed option value to an enum string per the universal coercion
     * contract: scalars stringify; a {@see \Stringable} object is cast via
     * `(string)` (so a Symbol sentinel's throwing __toString surfaces a
     * {@see TypeError}); any other object / array / null is rejected.
     *
     * @throws RangeError if the value is an array, a non-Stringable object, or null.
     */
    private static function coerceOptionString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }
        throw new RangeError('Option value must be a string.');
    }

    /**
     * Validates a roundingMode string against the allowed TC39 set so an unknown
     * (or coerced non-enum) mode is rejected even on a fast path where no
     * rounding is performed.
     *
     * @throws RangeError for an unknown roundingMode.
     */
    private static function validateRoundingMode(string $mode): void
    {
        if (!in_array($mode, self::ROUNDING_MODES, strict: true)) {
            throw new RangeError("Invalid roundingMode \"{$mode}\".");
        }
    }

    /**
     * Coerces a mixed roundingIncrement option to an integer per the universal
     * coercion contract: int/float/bool/numeric-string coerce (a non-finite
     * float is rejected); a {@see \Stringable} object stringifies then validates;
     * a non-numeric string, any other object, an array, or null is rejected.
     *
     * @throws RangeError if the value cannot be coerced to a finite number.
     */
    private static function coerceRoundingIncrement(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new RangeError('roundingIncrement must be a finite number.');
            }
            return (int) $value;
        }
        if (is_string($value) || $value instanceof Stringable) {
            $str = (string) $value;
            if (!is_numeric($str)) {
                throw new RangeError('roundingIncrement must be a number.');
            }
            $floatVal = (float) $str;
            if (!is_finite($floatVal)) {
                throw new RangeError('roundingIncrement must be a finite number.');
            }
            return (int) $floatVal;
        }
        throw new RangeError('roundingIncrement must be a number.');
    }

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
     * @throws RangeError if the offset is out of range
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
                if (str_starts_with($rest, ':')) {
                    $seconds = (int) substr(string: $rest, offset: 1, length: 2);
                    $rest = substr(string: $rest, offset: 3);
                    if (str_starts_with($rest, '.') || str_starts_with($rest, ',')) {
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
                    if (str_starts_with($rest, '.') || str_starts_with($rest, ',')) {
                        $fracNs = self::parseFraction($rest);
                    }
                }
            }
        }

        $absSec = ($hours * 3600) + ($minutes * 60) + $seconds;
        if ((($absSec * self::NS_PER_SECOND) + $fracNs) > 86_399_999_999_999) {
            throw new RangeError("Invalid Instant string \"{$original}\": UTC offset out of range.");
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
     * @throws RangeError on any violation.
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
     * @throws RangeError for unknown rounding modes.
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
        if ($mode === 'halfEven') {
            $cmp = $d1 * 2;
            if ($cmp < $increment) {
                $rounded = $r1;
            } elseif ($cmp > $increment) {
                $rounded = $r2;
            } else {
                $rounded = ($r1 % 2) === 0 ? $r1 : $r2;
            }
        } else {
            $rounded = match ($mode) {
                'trunc', 'floor' => $r1,
                'ceil', 'expand' => $d1 === 0 ? $r1 : $r2,
                'halfExpand', 'halfCeil' => ($d1 * 2) >= $increment ? $r2 : $r1,
                'halfTrunc', 'halfFloor' => ($d1 * 2) > $increment ? $r2 : $r1,
                default => throw new RangeError("Invalid roundingMode \"{$mode}\"."),
            };
        }

        return $rounded * $increment;
    }

    /**
     * Rounds a true (epochSec, subNs) pair to the nearest multiple of $increment
     * nanoseconds using RoundNumberToIncrementAsIfPositive, decomposed so the
     * combined nanosecond value never has to fit int64.
     *
     * Mirrors ZonedDateTime::roundEpochParts(). $subNs must be in [0, 1e9).
     *
     * @return array{int, int} [epochSec, subNs] with subNs in [0, 1e9)
     * @throws RangeError for unknown rounding modes.
     */
    private static function roundEpochParts(int $epochSec, int $subNs, int $increment, string $mode): array
    {
        if ($increment === 1) {
            return [$epochSec, $subNs];
        }

        if ($increment <= self::NS_PER_SECOND) {
            // Round the sub-second portion in isolation; carry into seconds.
            $roundedSubNs = self::roundAsIfPositive($subNs, $increment, $mode);
            if ($roundedSubNs >= self::NS_PER_SECOND) {
                $epochSec += intdiv($roundedSubNs, self::NS_PER_SECOND);
                $roundedSubNs %= self::NS_PER_SECOND;
            }
            return [$epochSec, $roundedSubNs];
        }

        // Minute (or coarser) increment: round in the seconds domain so the
        // combined nanosecond value never has to fit in int64.
        $incSec = intdiv($increment, self::NS_PER_SECOND);
        $floorSec = self::floorToIncrement($epochSec, $incSec);
        // d1 = distance from the floor multiple, in nanoseconds within [0, increment).
        $d1Ns = (($epochSec - $floorSec) * self::NS_PER_SECOND) + $subNs;
        $expand = match ($mode) {
            'trunc', 'floor' => false,
            'ceil', 'expand' => $d1Ns > 0,
            'halfExpand', 'halfCeil' => ($d1Ns * 2) >= $increment,
            'halfTrunc', 'halfFloor' => ($d1Ns * 2) > $increment,
            'halfEven' => ($d1Ns * 2) === $increment
                ? (intdiv($floorSec, $incSec) % 2) !== 0
                : ($d1Ns * 2) > $increment,
            default => throw new RangeError("Invalid roundingMode \"{$mode}\"."),
        };
        return [$expand ? $floorSec + $incSec : $floorSec, 0];
    }

    /** Largest multiple of $increment ≤ $value. */
    private static function floorToIncrement(int $value, int $increment): int
    {
        $q = intdiv($value, $increment);
        if (($value - ($q * $increment)) < 0) {
            $q--;
        }
        return $q * $increment;
    }

    /**
     * Computes a new Instant by adding a time-field offset to a true (epochSec, subNs) pair.
     *
     * All arithmetic is performed in the seconds domain, with sub-second
     * contributions carried separately, so no combined nanosecond value ever has
     * to fit int64. This keeps over-int64 (but in-spec) instants exact and makes
     * the max-sentinel + small-ns overflow case (no PHP_INT_MAX → PHP_INT_MIN
     * wraparound) impossible.
     *
     * @throws RangeError if the resulting instant is outside the Temporal spec range.
     */
    private static function addNsOffset(
        int $epochSec,
        int $subNs,
        int|float $hours,
        int|float $minutes,
        int|float $seconds,
        int|float $milliseconds,
        int|float $microseconds,
        int|float $nanoseconds,
    ): self {
        // Float approximation — used only to reject deltas so large their whole-
        // second magnitude could itself overflow int64 before decomposition.
        $floatDeltaSec =
            ((float) $hours * 3_600.0)
            + ((float) $minutes * 60.0)
            + (float) $seconds
            + ((float) $milliseconds / 1_000.0)
            + ((float) $microseconds / 1_000_000.0)
            + ((float) $nanoseconds / 1_000_000_000.0);
        // Spec range in seconds: |epochSec| ≤ 8_640_000_000_000. A delta whose
        // magnitude exceeds twice that can never land in range; reject early so
        // the integer seconds sum below cannot overflow.
        $specMaxSec = 8_640_000_000_000.0;
        if ($floatDeltaSec > (4.0 * $specMaxSec) || $floatDeltaSec < (-4.0 * $specMaxSec)) {
            throw new RangeError('Instant result is outside the representable nanosecond range.');
        }

        // Decompose each field into whole seconds + sub-second nanoseconds.
        // Crucially, ms/us/ns are each split into a whole-second part (added in
        // the seconds domain) and a sub-second remainder, so that a huge field
        // value (e.g. microseconds ≈ 9e18) never forms an int64-overflowing
        // nanosecond product — which would silently wrap and skip the range check.
        $h = (int) $hours;
        $m = (int) $minutes;
        $s = (int) $seconds;

        // Each sub-second field is split via decomposeUnit(): exact integer math
        // when the field fits int64 (so values up to 9.2e18 stay precise), or a
        // float-domain split when the field exceeds int64 (so over-int64-but-in-spec
        // float fields like nanoseconds ≈ 1.728e22 don't overflow on the int cast).
        [$msSec, $msSubNs] = self::decomposeUnit($milliseconds, 1_000_000, 1_000);
        [$usSec, $usSubNs] = self::decomposeUnit($microseconds, 1_000, 1_000_000);
        [$nsSec, $nsSubNs] = self::decomposeUnit($nanoseconds, 1, 1_000_000_000);

        // Whole-second contribution from every field.
        $deltaSec = ($h * 3_600) + ($m * 60) + $s + $msSec + $usSec + $nsSec;

        // Sub-second contribution in nanoseconds: each field's remainder is < 1e9 ns
        // apiece (sum < 3e9, well within int64).
        $subDeltaNs = $msSubNs + $usSubNs + $nsSubNs;

        // fromEpochParts() normalizes the sub-ns carry and enforces the spec range.
        return self::fromEpochParts($epochSec + $deltaSec, $subNs + $subDeltaNs);
    }

    /**
     * Splits one sub-second duration field into a whole-second part and a
     * sub-second nanosecond remainder, without ever forming an int64-overflowing
     * nanosecond product.
     *
     * @param int|float $value          The field value (e.g. nanoseconds, microseconds).
     * @param int       $nsPerUnit      Nanoseconds per one of this unit (ns=1, us=1_000, ms=1_000_000).
     * @param int       $unitsPerSecond Units of this kind per second (ns=1e9, us=1e6, ms=1e3).
     * @return array{int, int} [wholeSeconds, subNanoseconds] where subNanoseconds
     *                         carries the sign of $value and |subNanoseconds| < 1e9.
     */
    private static function decomposeUnit(int|float $value, int $nsPerUnit, int $unitsPerSecond): array
    {
        // Fast exact path: the field fits int64, so integer division and the
        // sub-second remainder (always < 1e9 ns) are computed without precision loss.
        if (is_int($value)) {
            $whole = CalendarMath::floorDiv($value, $unitsPerSecond);
            $remainderUnits = $value - ($whole * $unitsPerSecond);
            return [$whole, $remainderUnits * $nsPerUnit];
        }

        // Over-int64 float field: split in the float domain. floor() yields the
        // whole-second count (≤ ~3.46e13 for any in-spec delta, exact in float and
        // int64), and the remainder stays < 1e9 ns.
        $wholeFloat = floor($value / (float) $unitsPerSecond);
        $whole = (int) $wholeFloat;
        $remainderUnits = $value - ($wholeFloat * (float) $unitsPerSecond);
        return [$whole, (int) ($remainderUnits * (float) $nsPerUnit)];
    }

    /**
     * Core implementation for since() and until().
     *
     * Rounds and balances a signed epoch-second / sub-second difference into a
     * Duration according to the given options. The difference is supplied as a
     * (seconds, sub-ns) pair rather than a single nanosecond value so that
     * over-int64 spans survive without clamping.
     *
     * Unit ordering (smallest to largest):
     *   nanosecond < microsecond < millisecond < second < minute < hour
     *
     * @param int $diffSec   Signed whole-second difference (this − other for since, other − this for until).
     * @param int $diffSubNs Signed sub-second nanosecond difference paired with $diffSec.
     * @param array<array-key, mixed>|object|null $options
     * @throws RangeError for invalid unit/mode strings or invalid roundingIncrement.
     * @throws TypeError for wrong-typed option values.
     */
    private static function diffInstant(int $diffSec, int $diffSubNs, array|object|null $options): Duration
    {
        // Normalize to true signed seconds with sub-ns in [0, 1e9). Then derive
        // a signed magnitude so the whole diff is represented as (sign, absSec,
        // absSubNs) — never a single int64 nanosecond value, so over-int64
        // spans survive intact.
        if ($diffSubNs < 0 || $diffSubNs >= self::NS_PER_SECOND) {
            $carry = CalendarMath::floorDiv($diffSubNs, self::NS_PER_SECOND);
            $diffSec += $carry;
            $diffSubNs -= $carry * self::NS_PER_SECOND;
        }
        // Sign of the whole diff (subNs is now ≥ 0, so the diff is negative iff
        // diffSec < 0, zero iff both parts are zero).
        if ($diffSec < 0) {
            $diffSign = -1;
            // Magnitude of a negative value: -(diffSec*1e9 + diffSubNs).
            if ($diffSubNs > 0) {
                $absSec = -($diffSec + 1);
                $absSubNs = self::NS_PER_SECOND - $diffSubNs;
            } else {
                $absSec = -$diffSec;
                $absSubNs = 0;
            }
        } else {
            $diffSign = $diffSec > 0 || $diffSubNs > 0 ? 1 : 0;
            $absSec = $diffSec;
            $absSubNs = $diffSubNs;
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
        if (is_object($options)) {
            $options = get_object_vars($options);
        } elseif ($options === null) {
            $options = [];
        }

        // Track whether largestUnit was explicitly provided.
        $luProvided = array_key_exists('largestUnit', $options) && $options['largestUnit'] !== null;
        /** @var mixed $luVal */
        $luVal = $luProvided ? $options['largestUnit'] : null;
        /** @var mixed $suVal */
        $suVal = array_key_exists('smallestUnit', $options) ? $options['smallestUnit'] : null;

        if ($luVal !== null && !is_string($luVal)) {
            throw new TypeError('largestUnit must be a string.');
        }
        if ($suVal !== null && !is_string($suVal)) {
            throw new TypeError('smallestUnit must be a string.');
        }

        $suRaw = is_string($suVal) ? $suVal : 'nanosecond';

        if (!array_key_exists($suRaw, $unitOrder)) {
            throw new RangeError("Invalid smallestUnit \"{$suRaw}\".");
        }

        $suIdx = $unitOrder[$suRaw];

        if ($luProvided) {
            $luRaw = (string) $luVal;
            if (!array_key_exists($luRaw, $unitOrder)) {
                throw new RangeError("Invalid largestUnit \"{$luRaw}\".");
            }
            $luIdx = $unitOrder[$luRaw];
        } else {
            // Default: LargerOfTwoTemporalUnits('second', smallestUnit) → max(3, suIdx).
            $luIdx = max(3, $suIdx);
            $luRaw = $luIdx === $suIdx ? $suRaw : 'second';
        }

        // smallestUnit must not be larger than largestUnit.
        if ($suIdx > $luIdx) {
            throw new RangeError("smallestUnit \"{$suRaw}\" must not be larger than largestUnit \"{$luRaw}\".");
        }

        $roundingMode = 'trunc';
        if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
            $roundingMode = self::coerceOptionString($options['roundingMode']);
            self::validateRoundingMode($roundingMode);
        }

        $increment = 1;
        if (array_key_exists('roundingIncrement', $options) && $options['roundingIncrement'] !== null) {
            $increment = self::coerceRoundingIncrement($options['roundingIncrement']);
        }
        if ($increment < 1) {
            throw new RangeError('roundingIncrement must be a positive integer.');
        }
        $maxInc = $maxIncrementByIndex[$suIdx];
        // increment must evenly divide maxInc AND be strictly less than maxInc.
        if ($increment >= $maxInc || $increment > 1 && ($maxInc % $increment) !== 0) {
            throw new RangeError(
                "roundingIncrement {$increment} is invalid for unit \"{$suRaw}\" (max is {$maxInc}, must divide evenly and be < {$maxInc}).",
            );
        }

        // ---- Round ----
        // Round on the absolute magnitude, decomposed into (seconds, sub-ns) so
        // the combined nanosecond value never has to fit int64. For directional
        // modes (floor, ceil, halfFloor, halfCeil), negate the mode when the diff
        // is negative so that e.g. floor(-376435.5h) = -376436h (toward -∞) rather
        // than -376435h. Matches TC39 DifferenceInstant step 15
        // (NegateTemporalRoundingMode).
        $nsInc = $nsPerUnitByIndex[$suIdx] * $increment;
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
        [$roundedSec, $roundedSubNs] = $nsInc === 1
            ? [$absSec, $absSubNs]
            : self::roundEpochParts($absSec, $absSubNs, $nsInc, $effectiveMode);

        // ---- Balance ----
        // The rounded magnitude (roundedSec, roundedSubNs) is non-negative; build
        // the Duration from the seconds and sub-second parts separately so no
        // intermediate nanosecond value can overflow int64. Sign restored last.
        $sign = $diffSign;

        // Sub-second components (ns/us/ms) come entirely from roundedSubNs after
        // rounding has settled below the second boundary.
        $ns = $roundedSubNs;
        $us = 0;
        $ms = 0;
        if ($luIdx >= 1) { // at least microseconds
            $us = intdiv(num1: $ns, num2: 1_000);
            $ns -= $us * 1_000;
        }
        if ($luIdx >= 2) { // at least milliseconds
            $ms = intdiv(num1: $us, num2: 1_000);
            $us -= $ms * 1_000;
        }

        // Seconds and coarser components come from roundedSec.
        $s = $roundedSec;
        $min = 0;
        $h = 0;
        if ($luIdx >= 4) { // at least minutes
            $min = intdiv(num1: $s, num2: 60);
            $s -= $min * 60;
        }
        if ($luIdx >= 5) { // hours
            $h = intdiv(num1: $min, num2: 60);
            $min -= $h * 60;
        }

        // When largestUnit is below seconds, fold whole seconds back down into the
        // largest available sub-second unit (ms/us/ns) so the Duration still
        // represents the full magnitude.
        if ($luIdx < 3 && $roundedSec !== 0) {
            $s = 0;
            if ($luIdx === 2) {
                $ms += $roundedSec * 1_000;
            } elseif ($luIdx === 1) {
                $us += $roundedSec * 1_000_000;
            } else {
                $ns += $roundedSec * 1_000_000_000;
            }
        }

        return new Duration(0, 0, 0, 0, $sign * $h, $sign * $min, $sign * $s, $sign * $ms, $sign * $us, $sign * $ns);
    }
}
