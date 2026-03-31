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
     * Returns a locale-sensitive string representation using IntlDateFormatter.
     *
     * For date-only types (PlainDate, PlainYearMonth, PlainMonthDay), timeStyle is forbidden.
     * For time-only types (PlainTime), dateStyle is forbidden.
     * Style options (dateStyle/timeStyle) cannot be combined with individual component options.
     *
     * @param string|array<array-key, mixed>|null $locales
     * @param array<array-key, mixed>|object|null $options
     * @psalm-api
     */
    public function toLocaleString(string|array|null $locales = null, array|object|null $options = null): string
    {
        $opts = $options !== null ? (is_array($options) ? $options : (array) $options) : [];
        /** @psalm-var array<string, mixed> $opts */
        $hasTimeStyle = array_key_exists('timeStyle', $opts) && $opts['timeStyle'] !== null;
        $hasDateStyle = array_key_exists('dateStyle', $opts) && $opts['dateStyle'] !== null;

        $isDateOnly = $this instanceof \Temporal\Spec\PlainDate
            || $this instanceof \Temporal\Spec\PlainYearMonth
            || $this instanceof \Temporal\Spec\PlainMonthDay;
        $isTimeOnly = $this instanceof \Temporal\Spec\PlainTime;

        // PlainDate, PlainYearMonth, PlainMonthDay: timeStyle is forbidden.
        if ($hasTimeStyle && $isDateOnly) {
            throw new \TypeError('toLocaleString(): timeStyle option is not allowed for this type.');
        }
        // PlainTime: dateStyle is forbidden.
        if ($hasDateStyle && $isTimeOnly) {
            throw new \TypeError('toLocaleString(): dateStyle option is not allowed for this type.');
        }

        $locale = CalendarMath::resolveLocale($locales);

        // Plain types always format in UTC to prevent date/time shifting.
        // The timeZone option is accepted but ignored for display purposes.
        $timeZone = 'UTC';

        // Determine default component mode
        if ($this instanceof \Temporal\Spec\PlainYearMonth) {
            $defaultComponents = 'yearmonth';
        } elseif ($this instanceof \Temporal\Spec\PlainMonthDay) {
            $defaultComponents = 'monthday';
        } elseif ($isDateOnly) {
            $defaultComponents = 'date';
        } elseif ($isTimeOnly) {
            $defaultComponents = 'time';
        } else {
            $defaultComponents = 'datetime';
        }

        // Pass locale into opts for pattern generator
        $opts['_locale'] = $locale;

        $formatter = CalendarMath::buildIntlFormatter($locale, $timeZone, $opts, $defaultComponents);

        // Build a timestamp from the type's fields
        $timestamp = $this->toLocaleTimestamp($timeZone);
        $result = $formatter->format($timestamp);

        return $result !== false ? $result : $this->toString();
    }

    /**
     * Converts this temporal value to a Unix timestamp (seconds) for IntlDateFormatter.
     */
    private function toLocaleTimestamp(string $timeZone): int
    {
        if ($this instanceof \Temporal\Spec\PlainDate) {
            // Create a DateTime at midnight in UTC for this date
            $dt = new \DateTime(sprintf('%04d-%02d-%02d 00:00:00', $this->isoYear, $this->isoMonth, $this->isoDay), new \DateTimeZone('UTC'));
            return $dt->getTimestamp();
        }

        if ($this instanceof \Temporal\Spec\PlainDateTime) {
            $dt = new \DateTime(sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d',
                $this->isoYear,
                $this->isoMonth,
                $this->isoDay,
                $this->hour,
                $this->minute,
                $this->second,
            ), new \DateTimeZone('UTC'));
            return $dt->getTimestamp();
        }

        if ($this instanceof \Temporal\Spec\PlainTime) {
            // Use Unix epoch date (1970-01-01) with the given time
            $dt = new \DateTime(sprintf('1970-01-01T%02d:%02d:%02d', $this->hour, $this->minute, $this->second), new \DateTimeZone('UTC'));
            return $dt->getTimestamp();
        }

        if ($this instanceof \Temporal\Spec\PlainYearMonth) {
            // Use referenceISODay to ensure the timestamp falls within the correct
            // calendar month for non-ISO calendars.
            $dt = new \DateTime(sprintf('%04d-%02d-%02d 00:00:00', $this->isoYear, $this->isoMonth, $this->referenceISODay), new \DateTimeZone('UTC'));
            return $dt->getTimestamp();
        }

        if ($this instanceof \Temporal\Spec\PlainMonthDay) {
            // Use a reference year
            $dt = new \DateTime(sprintf('%04d-%02d-%02d 00:00:00', $this->referenceISOYear, $this->isoMonth, $this->isoDay), new \DateTimeZone('UTC'));
            return $dt->getTimestamp();
        }

        return 0;
    }
}
