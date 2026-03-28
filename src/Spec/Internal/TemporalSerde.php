<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

/** @internal */
trait TemporalSerde
{
    abstract public function toString(): string;

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
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
        // Validate that timeStyle is not provided for date-only types, and
        // dateStyle is not provided for time-only types.
        if ($options !== null) {
            $opts = is_array($options) ? $options : (array) $options;
            $hasTimeStyle = array_key_exists('timeStyle', $opts) && $opts['timeStyle'] !== null;
            $hasDateStyle = array_key_exists('dateStyle', $opts) && $opts['dateStyle'] !== null;

            // PlainDate, PlainYearMonth, PlainMonthDay: timeStyle is forbidden.
            if ($hasTimeStyle && ($this instanceof \Temporal\Spec\PlainDate
                || $this instanceof \Temporal\Spec\PlainYearMonth
                || $this instanceof \Temporal\Spec\PlainMonthDay)) {
                throw new \TypeError('toLocaleString(): timeStyle option is not allowed for this type.');
            }
            // PlainTime: dateStyle is forbidden.
            if ($hasDateStyle && $this instanceof \Temporal\Spec\PlainTime) {
                throw new \TypeError('toLocaleString(): dateStyle option is not allowed for this type.');
            }
            // Instant: dateStyle without timeZone is forbidden (handled elsewhere).
        }

        return $this->toString();
    }
}
