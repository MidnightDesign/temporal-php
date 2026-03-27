<?php

declare(strict_types=1);

namespace Temporal;

use Temporal\Spec\PlainMonthDay as SpecPlainMonthDay;

/**
 * A calendar month-day without a year, time, or time zone.
 *
 * This is the porcelain (user-facing) wrapper around the spec-layer
 * {@see SpecPlainMonthDay}. It provides typed enums, named parameters, and
 * value-object semantics while delegating all calendar math to the spec layer.
 */
final class PlainMonthDay implements \Stringable, \JsonSerializable
{
    // -------------------------------------------------------------------------
    // Virtual (get-only) properties — delegated to the spec instance
    // -------------------------------------------------------------------------

    /**
     * Always "iso8601" — the only supported calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public string $calendarId {
        get => $this->spec->calendarId;
    }

    /**
     * Month code in "M01"–"M12" format.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public string $monthCode {
        get => $this->spec->monthCode;
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    private readonly SpecPlainMonthDay $spec;

    /**
     * Creates a new PlainMonthDay from month and day.
     *
     * @param int<1, 12> $month Month of the year (1–12).
     * @param int<1, 31> $day   Day of the month (1–31, depending on month).
     * @throws \InvalidArgumentException if the month-day is invalid or out of range.
     */
    public function __construct(
        public readonly int $month,
        public readonly int $day,
    ) {
        $this->spec = new SpecPlainMonthDay($month, $day);
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    /**
     * Parses an ISO 8601 month-day string into a PlainMonthDay.
     *
     * @param string $text ISO 8601 month-day string (e.g. "--12-25" or "12-25").
     * @return self
     * @throws \InvalidArgumentException if the string cannot be parsed.
     */
    public static function parse(string $text): self
    {
        $spec = SpecPlainMonthDay::from($text);

        return self::fromSpec($spec);
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainMonthDay with the specified fields overridden.
     *
     * @param int<1, 12>|null $month     Month override (1–12), or null to keep current.
     * @param int<1, 31>|null $day       Day override, or null to keep current.
     * @param Overflow        $overflow  How to handle out-of-range values.
     * @return self A new PlainMonthDay with the overridden fields.
     * @throws \InvalidArgumentException if the resulting month-day is invalid (overflow: reject) or fields conflict.
     */
    public function with(
        ?int $month = null,
        ?int $day = null,
        Overflow $overflow = Overflow::Constrain,
    ): self {
        $fields = [];
        if ($month !== null) {
            $fields['month'] = $month;
        }
        if ($day !== null) {
            $fields['day'] = $day;
        }

        $spec = $this->spec->with($fields, ['overflow' => $overflow->value]);

        return self::fromSpec($spec);
    }

    /**
     * Returns true if this PlainMonthDay represents the same month-day as $other.
     *
     * @param self $other The month-day to compare with.
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->spec->equals($other->spec);
    }

    /**
     * Returns the ISO 8601 string representation of this month-day.
     *
     * @param CalendarDisplay $calendarName Whether to include the calendar annotation.
     * @return string
     */
    public function toString(CalendarDisplay $calendarName = CalendarDisplay::Auto): string
    {
        return $this->spec->toString(['calendarName' => $calendarName->value]);
    }

    /**
     * Converts this month-day to a PlainDate by supplying the year.
     *
     * The day is constrained to the valid range for that year's month.
     *
     * @param int $year The year to combine with this month-day.
     * @return PlainDate
     * @throws \InvalidArgumentException if the resulting date is invalid.
     */
    public function toPlainDate(int $year): PlainDate
    {
        return PlainDate::fromSpec($this->spec->toPlainDate(['year' => $year]));
    }

    // -------------------------------------------------------------------------
    // Stringable / JsonSerializable
    // -------------------------------------------------------------------------

    /**
     * Returns the ISO 8601 string representation.
     *
     * @return string
     */
    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns the ISO 8601 string for JSON serialization.
     *
     * @return string
     */
    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    // -------------------------------------------------------------------------
    // Spec-layer interop
    // -------------------------------------------------------------------------

    /**
     * Returns the underlying spec-layer PlainMonthDay.
     *
     * @return SpecPlainMonthDay
     */
    public function toSpec(): SpecPlainMonthDay
    {
        return $this->spec;
    }

    /**
     * Creates a porcelain PlainMonthDay from a spec-layer PlainMonthDay.
     *
     * The referenceISOYear from the spec is not preserved; the porcelain layer
     * hides this implementation detail and uses the default (1972).
     *
     * @param SpecPlainMonthDay $spec The spec-layer instance to wrap.
     * @return self
     */
    public static function fromSpec(SpecPlainMonthDay $spec): self
    {
        return new self($spec->isoMonth, $spec->day);
    }

    // -------------------------------------------------------------------------
    // Debug
    // -------------------------------------------------------------------------

    /**
     * Returns debug information for var_dump().
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'month' => $this->month,
            'day' => $this->day,
            'calendarId' => $this->calendarId,
            'iso' => $this->toString(),
        ];
    }
}
