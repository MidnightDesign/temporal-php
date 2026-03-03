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

    /**
     * @param int $epochNanoseconds Nanoseconds since the Unix epoch.
     */
    public function __construct(
        public readonly int $epochNanoseconds,
    ) {}

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
     *   '2020-01-01T15:23Z'                        (seconds optional)
     *   '1976-11-18T15:23:30,12Z'                  (comma as decimal separator)
     *   '19761118T152330Z'                         (compact date + compact time)
     *   '1976-11-18T15:23:30+0530'                 (short offset ±HHMM)
     *   '1976-11-18T15:23:30+00'                   (short offset ±HH)
     *   '+001976-11-18T15:23:30Z'                  (extended positive year)
     *   '-009999-11-18T15:23:30Z'                  (negative year; throws if out of ns range)
     *   '2020-01-01T00:00:00Z[UTC][u-ca=iso8601]'  (multiple annotations ignored)
     *   '2016-12-31T23:59:60Z'                     (leap second → normalized to next second)
     *
     * @throws InvalidArgumentException if the string cannot be parsed, has no UTC offset,
     *                                  or represents a timestamp outside the nanosecond range.
     */
    public static function from(string $text): self
    {
        /*
         * Regex groups:
         *   1 — year (±YYYYYY or YYYY)   2 — date rest (-MM-DD or MMDD)
         *   3 — hour (HH)                4 — minute (MM)
         *   5 — second (SS, optional)    6 — fraction ([.,]\d+, optional)
         *   7 — offset (Z, ±HH, ±HHMM, ±HH:MM)
         *
         * PHP's DateTimeImmutable natively handles extended years (±YYYYYY),
         * compact dates (YYYYMMDD), and all UTC-offset forms (Z, ±HH, ±HHMM,
         * ±HH:MM), so no manual normalisation of those fields is required.
         */
        $pattern =
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})' // year + date rest
            . '[T ]'
            . '(\d{2}):?(\d{2})(?::?(\d{2}))?' // HH[:MM][:SS?]
            . '([.,]\d+)?' // optional fraction (any number of digits)
            . '(Z|[+-]\d{2}(?::\d{2}|\d{2})?)' // required offset
            . '(?:\[.*?\])*' // zero or more annotations
            . '$/i';

        /** @var list<string> $m */
        $m = [];
        if (preg_match($pattern, $text, $m) !== 1) {
            throw new InvalidArgumentException(
                "Invalid Instant string \"{$text}\": expected ISO 8601 with a UTC offset.",
            );
        }

        [, $yearRaw, $dateRest, $hour, $min, $sec, $fractionRaw, $offset] = $m;

        // Seconds default to '00' when omitted; normalise leap second 60 → 59.
        $sec60 = $sec === '60';
        $normalSec = match ($sec) {
            '' => '00',
            '60' => '59',
            default => $sec,
        };

        try {
            $dt = new DateTimeImmutable("{$yearRaw}{$dateRest}T{$hour}:{$min}:{$normalSec}{$offset}");
        } catch (\Exception) {
            throw new InvalidArgumentException("Could not parse \"{$text}\".");
        }

        if ($sec60) {
            $dt = $dt->modify('+1 second');
        }

        $seconds = $dt->getTimestamp();

        // Guard against int64 overflow: seconds × 1_000_000_000 must stay within range.
        // The representable nanosecond range is roughly years 1678–2262.
        if ($seconds > 9_223_372_036 || $seconds < -9_223_372_036) {
            throw new InvalidArgumentException(
                "Instant string \"{$text}\" is outside the representable nanosecond range.",
            );
        }

        $subNs = $fractionRaw !== '' ? self::parseFraction($fractionRaw) : 0;

        return new self(($seconds * self::NS_PER_SECOND) + $subNs);
    }

    /**
     * Creates an Instant from a Unix timestamp in milliseconds.
     */
    public static function fromEpochMilliseconds(int $epochMilliseconds): self
    {
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
     * @return int -1, 0, or 1.
     */
    public static function compare(self $one, self $two): int
    {
        return $one->epochNanoseconds <=> $two->epochNanoseconds;
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns true when both Instants represent the same point in time.
     */
    public function equals(self $other): bool
    {
        return $this->epochNanoseconds === $other->epochNanoseconds;
    }

    /**
     * Returns an ISO 8601 string in UTC.
     *
     * Sub-second digits are included only when non-zero, and trailing zeros
     * within the fractional part are stripped.
     *
     * Examples:
     *   '2020-01-01T00:00:00Z'
     *   '2020-01-01T00:00:00.5Z'
     *   '2020-01-01T00:00:00.123456789Z'
     */
    public function toString(): string
    {
        $secs = self::floorDiv($this->epochNanoseconds, self::NS_PER_SECOND);
        $subNs = $this->epochNanoseconds - ($secs * self::NS_PER_SECOND); // 0–999_999_999

        $dt = new DateTimeImmutable('@' . $secs)->setTimezone(new DateTimeZone('UTC'));
        $base = $dt->format('Y-m-d\TH:i:s');

        if ($subNs === 0) {
            return $base . 'Z';
        }

        $fraction = rtrim(sprintf('%09d', $subNs), characters: '0');
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
