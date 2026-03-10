<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

/**
 * A calendar date without a time or time zone.
 *
 * Only the ISO 8601 calendar is supported. Years must fit in the range
 * representable by PHP integers.
 *
 * @see https://tc39.es/proposal-temporal/#sec-temporal-plaindate-objects
 */
final class PlainDate implements Stringable
{
    /**
     * @throws InvalidArgumentException if year/month/day form an invalid ISO date.
     */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: month {$month} is out of range 1–12.",
            );
        }
        if ($day < 1) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$day} must be at least 1.",
            );
        }
        $daysInMonth = self::daysInMonth($year, $month);
        if ($day > $daysInMonth) {
            throw new InvalidArgumentException(
                "Invalid PlainDate: day {$day} exceeds {$daysInMonth} for {$year}-{$month}.",
            );
        }
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    /**
     * Creates a PlainDate from another PlainDate, an ISO 8601 string, or a
     * property-bag array with 'year', 'month'/'monthCode', and 'day' fields.
     *
     * @param mixed $item PlainDate, ISO 8601 date string, or property-bag array.
     * @throws InvalidArgumentException if the string is invalid.
     * @throws \TypeError if the type cannot be interpreted as a PlainDate.
     * @psalm-api
     */
    public static function from(mixed $item): self
    {
        if ($item instanceof self) {
            return new self($item->year, $item->month, $item->day);
        }
        if (is_string($item)) {
            return self::fromString($item);
        }
        if (is_array($item)) {
            return self::fromPropertyBag($item);
        }
        throw new \TypeError(
            'PlainDate::from() expects a PlainDate, ISO 8601 string, or property-bag array; got '
            . get_debug_type($item) . '.',
        );
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
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
     * Parses an ISO 8601 date string into a PlainDate.
     *
     * Accepted formats:
     *   YYYY-MM-DD, ±YYYYYY-MM-DD
     * Optional trailing annotations ([u-ca=iso8601]) and time parts are accepted
     * and ignored (the date portion is extracted).
     *
     * @throws InvalidArgumentException for invalid or out-of-range dates.
     */
    private static function fromString(string $s): self
    {
        if ($s === '') {
            throw new InvalidArgumentException('PlainDate::from() received an empty string.');
        }
        // Extract the date portion: extended year or 4-digit year, then -MM-DD.
        if (preg_match('/^([+\-]?\d{4,6})-(\d{2})-(\d{2})/', $s, $m) !== 1
            && preg_match('/^(\d{4})(\d{2})(\d{2})/', $s, $m) !== 1
        ) {
            throw new InvalidArgumentException(
                "PlainDate::from() cannot parse \"{$s}\": expected YYYY-MM-DD format.",
            );
        }
        // Reject minus-zero extended year (-000000).
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $s) === 1) {
            throw new InvalidArgumentException('Cannot use negative zero as extended year.');
        }

        $year  = (int) $m[1];
        $month = (int) $m[2];
        $day   = (int) $m[3];

        return new self($year, $month, $day);
    }

    /**
     * Creates a PlainDate from a property-bag array.
     *
     * @param array<array-key,mixed> $bag
     * @throws \TypeError if required fields are missing.
     * @throws InvalidArgumentException if the date is invalid.
     */
    private static function fromPropertyBag(array $bag): self
    {
        if (!array_key_exists('year', $bag)) {
            throw new \TypeError('PlainDate property bag must have a year field.');
        }
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('PlainDate property bag must have a month or monthCode field.');
        }
        if (!array_key_exists('day', $bag)) {
            throw new \TypeError('PlainDate property bag must have a day field.');
        }

        /** @var mixed $yearRaw */
        $yearRaw = $bag['year'];
        /** @phpstan-ignore cast.int */
        $year = is_int($yearRaw) ? $yearRaw : (int) $yearRaw;

        if (array_key_exists('month', $bag)) {
            /** @var mixed $monthRaw */
            $monthRaw = $bag['month'];
            /** @phpstan-ignore cast.int */
            $month = is_int($monthRaw) ? $monthRaw : (int) $monthRaw;
        } else {
            /** @var mixed $monthCodeRaw */
            $monthCodeRaw = $bag['monthCode'];
            /** @phpstan-ignore cast.string */
            $mc = is_string($monthCodeRaw) ? $monthCodeRaw : (string) $monthCodeRaw;
            $month = (int) substr(string: $mc, offset: 1);
        }

        /** @var mixed $dayRaw */
        $dayRaw = $bag['day'];
        /** @phpstan-ignore cast.int */
        $day = is_int($dayRaw) ? $dayRaw : (int) $dayRaw;

        return new self($year, $month, $day);
    }

    /**
     * Returns the number of days in the given ISO calendar month.
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

    private static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }
}
