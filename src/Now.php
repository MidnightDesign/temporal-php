<?php

declare(strict_types=1);

namespace Temporal;

use Temporal\Spec\Now as SpecNow;

/**
 * Provides access to the current date and time as porcelain Temporal types.
 *
 * This is the user-facing counterpart of {@see SpecNow}. Each method delegates
 * to the spec layer and wraps the result in the corresponding porcelain type.
 */
final class Now
{
    /** Not instantiable.
     * @psalm-suppress UnusedConstructor
     */
    private function __construct() {}

    /**
     * Returns the current time as a Temporal.Instant.
     *
     * Uses microsecond precision (sub-microsecond bits are zero).
     */
    public static function instant(): Instant
    {
        return Instant::fromSpec(SpecNow::instant());
    }

    /**
     * Returns the system default time zone identifier string.
     */
    public static function timeZoneId(): string
    {
        return SpecNow::timeZoneId();
    }

    /**
     * Returns today's date in the ISO 8601 calendar.
     *
     * @param string|null $timeZone IANA time zone or UTC offset; null uses the system default.
     * @throws \TypeError              if $timeZone is explicitly passed as null.
     * @throws \InvalidArgumentException if the string is not a valid time zone identifier.
     */
    public static function plainDate(?string $timeZone = null): PlainDate
    {
        // Forward the argument only when explicitly provided, so the spec layer's
        // func_num_args() check can distinguish "omitted" from "passed null".
        return PlainDate::fromSpec(func_num_args() > 0 ? SpecNow::plainDateISO($timeZone) : SpecNow::plainDateISO());
    }

    /**
     * Returns the current wall-clock time (no date) in the ISO 8601 calendar.
     *
     * @param string|null $timeZone IANA time zone or UTC offset; null uses the system default.
     * @throws \TypeError              if $timeZone is explicitly passed as null.
     * @throws \InvalidArgumentException if the string is not a valid time zone identifier.
     */
    public static function plainTime(?string $timeZone = null): PlainTime
    {
        return PlainTime::fromSpec(func_num_args() > 0 ? SpecNow::plainTimeISO($timeZone) : SpecNow::plainTimeISO());
    }

    /**
     * Returns the current date and time in the ISO 8601 calendar.
     *
     * @param string|null $timeZone IANA time zone or UTC offset; null uses the system default.
     * @throws \TypeError              if $timeZone is explicitly passed as null.
     * @throws \InvalidArgumentException if the string is not a valid time zone identifier.
     */
    public static function plainDateTime(?string $timeZone = null): PlainDateTime
    {
        return PlainDateTime::fromSpec(
            func_num_args() > 0 ? SpecNow::plainDateTimeISO($timeZone) : SpecNow::plainDateTimeISO(),
        );
    }

    /**
     * Returns the current date and time as a ZonedDateTime in the ISO 8601 calendar.
     *
     * @param string|null $timeZone IANA time zone or UTC offset; null uses the system default.
     * @throws \TypeError              if $timeZone is explicitly passed as null.
     * @throws \InvalidArgumentException if the string is not a valid time zone identifier.
     */
    public static function zonedDateTime(?string $timeZone = null): ZonedDateTime
    {
        return ZonedDateTime::fromSpec(
            func_num_args() > 0 ? SpecNow::zonedDateTimeISO($timeZone) : SpecNow::zonedDateTimeISO(),
        );
    }
}
