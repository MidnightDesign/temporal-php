<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;

/**
 * A date-time anchored to a specific timezone and instant.
 *
 * This is a minimal implementation supporting only construction and use as a
 * relativeTo argument in Duration arithmetic. Only UTC and fixed-offset timezones
 * (±HH:MM) are supported.
 *
 * @psalm-api
 * @see https://tc39.es/proposal-temporal/#sec-temporal-zoneddatetime-objects
 */
final class ZonedDateTime
{
    /**
     * @throws InvalidArgumentException if epochNanoseconds is not a finite integer value.
     */
    public function __construct(
        public readonly int|float $epochNanoseconds,
        public readonly string $timeZoneId,
        /** @psalm-suppress PossiblyUnusedProperty — used from test262 scripts excluded from Psalm */
        public readonly string $calendarId = 'iso8601',
    ) {
        if (is_float($this->epochNanoseconds)
            && (!is_finite($this->epochNanoseconds) || floor($this->epochNanoseconds) !== $this->epochNanoseconds)
        ) {
            throw new InvalidArgumentException('ZonedDateTime epochNanoseconds must be a finite integer value.');
        }
    }

    /**
     * Returns an ISO 8601 representation: YYYY-MM-DDTHH:mm:ss+HH:MM[timeZoneId].
     */
    public function __toString(): string
    {
        $epochNs = $this->epochNanoseconds;
        $epochSec = is_int($epochNs)
            ? intdiv(num1: $epochNs, num2: 1_000_000_000)
            : (int) floor($epochNs / 1_000_000_000.0);
        if (is_int($epochNs) && $epochNs < 0 && $epochNs % 1_000_000_000 !== 0) {
            $epochSec -= 1;
        }
        $dt = (new \DateTimeImmutable('@' . $epochSec, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s') . '+00:00[' . $this->timeZoneId . ']';
    }

    /**
     * @throws \TypeError always — ZonedDateTime must not be used in numeric context.
     */
    public function valueOf(): never
    {
        throw new \TypeError('Use comparison methods instead of relying on ZonedDateTime object coercion.');
    }
}
