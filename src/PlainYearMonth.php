<?php

declare(strict_types=1);

namespace Temporal;

use Temporal\Spec\PlainYearMonth as SpecPlainYearMonth;

/**
 * A calendar year-month without a specific day, time, or time zone.
 *
 * This is the porcelain (user-facing) wrapper around the spec-layer
 * {@see SpecPlainYearMonth}. It provides typed enums, named parameters, and
 * value-object semantics while delegating all calendar math to the spec layer.
 */
final class PlainYearMonth implements \Stringable, \JsonSerializable
{
    // -------------------------------------------------------------------------
    // Virtual (get-only) properties — delegated to the spec instance
    // -------------------------------------------------------------------------

    /**
     * Calendar year (projected through the active calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $year {
        get => $this->spec->year;
    }

    /**
     * Month of the year (projected through the active calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $month {
        get => $this->spec->month;
    }

    /**
     * Calendar identifier (e.g. "iso8601", "hebrew", "japanese").
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public string $calendarId {
        get => $this->spec->calendarId;
    }

    /**
     * Month code in "M01"–"M12" format (or "M01L"–"M12L" for leap months).
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public string $monthCode {
        get => $this->spec->monthCode;
    }

    /**
     * Number of days in this year-month's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $daysInMonth {
        get => $this->spec->daysInMonth;
    }

    /**
     * Number of days in this year-month's year.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $daysInYear {
        get => $this->spec->daysInYear;
    }

    /**
     * Number of months in this year-month's year.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $monthsInYear {
        get => $this->spec->monthsInYear;
    }

    /**
     * True if this year-month's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public bool $inLeapYear {
        get => $this->spec->inLeapYear;
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    private readonly SpecPlainYearMonth $spec;

    /**
     * Creates a new PlainYearMonth from ISO year and month.
     *
     * @param int         $isoYear     ISO year.
     * @param int<1, 12>  $isoMonth    ISO month of the year (1–12).
     * @param string|null $calendarId  Calendar identifier, or null for "iso8601".
     * @param int         $referenceISODay Reference ISO day for round-trip fidelity (default 1).
     * @throws \InvalidArgumentException if the year-month is invalid or out of range.
     */
    public function __construct(
        int $isoYear,
        int $isoMonth,
        ?string $calendarId = null,
        int $referenceISODay = 1,
    ) {
        $this->spec = new SpecPlainYearMonth($isoYear, $isoMonth, $calendarId, $referenceISODay);
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Parses an ISO 8601 year-month string into a PlainYearMonth.
     *
     * @param string $text ISO 8601 year-month string (e.g. "2020-01").
     * @return self
     * @throws \InvalidArgumentException if the string cannot be parsed.
     */
    public static function parse(string $text): self
    {
        $spec = SpecPlainYearMonth::from($text);

        return self::fromSpec($spec);
    }

    /**
     * Compares two PlainYearMonths chronologically.
     *
     * @return int Negative if $one is earlier, positive if later, zero if equal.
     */
    public static function compare(self $one, self $two): int
    {
        return SpecPlainYearMonth::compare($one->spec, $two->spec);
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainYearMonth with the specified fields overridden.
     *
     * @param int|null        $year  Year override, or null to keep current.
     * @param int<1, 12>|null $month Month override (1–12), or null to keep current.
     * @return self A new PlainYearMonth with the overridden fields.
     * @throws \InvalidArgumentException if the resulting year-month is invalid or fields conflict.
     */
    public function with(
        ?int $year = null,
        ?int $month = null,
    ): self {
        $fields = [];
        if ($year !== null) {
            $fields['year'] = $year;
        }
        if ($month !== null) {
            $fields['month'] = $month;
        }
        $spec = $this->spec->with($fields);

        return self::fromSpec($spec);
    }

    /**
     * Returns a new PlainYearMonth with the given duration added.
     *
     * Only years and months are relevant; weeks and days are rejected.
     *
     * @param Duration $duration The duration to add.
     * @return self
     */
    public function add(Duration $duration): self
    {
        $spec = $this->spec->add($duration->toSpec());

        return self::fromSpec($spec);
    }

    /**
     * Returns a new PlainYearMonth with the given duration subtracted.
     *
     * @param Duration $duration The duration to subtract.
     * @return self
     */
    public function subtract(Duration $duration): self
    {
        $spec = $this->spec->subtract($duration->toSpec());

        return self::fromSpec($spec);
    }

    /**
     * Returns the duration from $other to this year-month (this - other).
     *
     * @param self         $other             The other year-month to measure from.
     * @param Unit         $largestUnit       The largest unit to use in the result.
     * @param Unit         $smallestUnit      The smallest unit to use in the result.
     * @param RoundingMode $roundingMode      How to round the result.
     * @param int          $roundingIncrement The rounding increment for the smallest unit.
     * @return Duration
     */
    public function since(
        self $other,
        Unit $largestUnit = Unit::Year,
        Unit $smallestUnit = Unit::Month,
        RoundingMode $roundingMode = RoundingMode::Trunc,
        int $roundingIncrement = 1,
    ): Duration {
        $specDuration = $this->spec->since($other->spec, [
            'largestUnit' => $largestUnit->value,
            'smallestUnit' => $smallestUnit->value,
            'roundingMode' => $roundingMode->value,
            'roundingIncrement' => $roundingIncrement,
        ]);

        return Duration::fromSpec($specDuration);
    }

    /**
     * Returns the duration from this year-month to $other (other - this).
     *
     * @param self         $other             The other year-month to measure to.
     * @param Unit         $largestUnit       The largest unit to use in the result.
     * @param Unit         $smallestUnit      The smallest unit to use in the result.
     * @param RoundingMode $roundingMode      How to round the result.
     * @param int          $roundingIncrement The rounding increment for the smallest unit.
     * @return Duration
     */
    public function until(
        self $other,
        Unit $largestUnit = Unit::Year,
        Unit $smallestUnit = Unit::Month,
        RoundingMode $roundingMode = RoundingMode::Trunc,
        int $roundingIncrement = 1,
    ): Duration {
        $specDuration = $this->spec->until($other->spec, [
            'largestUnit' => $largestUnit->value,
            'smallestUnit' => $smallestUnit->value,
            'roundingMode' => $roundingMode->value,
            'roundingIncrement' => $roundingIncrement,
        ]);

        return Duration::fromSpec($specDuration);
    }

    /**
     * Returns true if this PlainYearMonth represents the same year-month as $other.
     *
     * @param self $other The year-month to compare with.
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->spec->equals($other->spec);
    }

    /**
     * Returns the ISO 8601 string representation of this year-month.
     *
     * @param CalendarDisplay $calendarName Whether to include the calendar annotation.
     * @return string
     */
    public function toString(CalendarDisplay $calendarName = CalendarDisplay::Auto): string
    {
        return $this->spec->toString(['calendarName' => $calendarName->value]);
    }

    /**
     * Converts this year-month to a PlainDate by supplying the day.
     *
     * @param int<1, 31> $day Day of the month.
     * @return PlainDate
     * @throws \InvalidArgumentException if the resulting date is invalid.
     */
    public function toPlainDate(int $day): PlainDate
    {
        return PlainDate::fromSpec($this->spec->toPlainDate(['day' => $day]));
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
     * Returns the underlying spec-layer PlainYearMonth.
     *
     * @return SpecPlainYearMonth
     */
    public function toSpec(): SpecPlainYearMonth
    {
        return $this->spec;
    }

    /**
     * Creates a porcelain PlainYearMonth from a spec-layer PlainYearMonth.
     *
     * @param SpecPlainYearMonth $spec The spec-layer instance to wrap.
     * @return self
     */
    public static function fromSpec(SpecPlainYearMonth $spec): self
    {
        return new self($spec->isoYear, $spec->isoMonth, $spec->calendarId, $spec->referenceISODay);
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
            'year' => $this->year,
            'month' => $this->month,
            'calendarId' => $this->calendarId,
            'iso' => $this->toString(),
        ];
    }
}
