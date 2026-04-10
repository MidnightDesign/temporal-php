<?php

declare(strict_types=1);

namespace Temporal;

use Temporal\Spec\PlainDateTime as SpecPlainDateTime;

/**
 * A calendar date combined with a wall-clock time, without a time zone.
 *
 * This is the porcelain (user-facing) wrapper around the spec-layer
 * {@see SpecPlainDateTime}. It provides typed enums, named parameters, and
 * value-object semantics while delegating all calendar math to the spec layer.
 */
final class PlainDateTime implements \Stringable, \JsonSerializable
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
     * Day of the month (projected through the active calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $day {
        get => $this->spec->day;
    }

    /**
     * Hour of the day (0–23).
     *
     * @var int<0, 23>
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $hour {
        get => $this->spec->hour;
    }

    /**
     * Minute of the hour (0–59).
     *
     * @var int<0, 59>
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $minute {
        get => $this->spec->minute;
    }

    /**
     * Second of the minute (0–59).
     *
     * @var int<0, 59>
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $second {
        get => $this->spec->second;
    }

    /**
     * Millisecond (0–999).
     *
     * @var int<0, 999>
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $millisecond {
        get => $this->spec->millisecond;
    }

    /**
     * Microsecond (0–999).
     *
     * @var int<0, 999>
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $microsecond {
        get => $this->spec->microsecond;
    }

    /**
     * Nanosecond (0–999).
     *
     * @var int<0, 999>
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $nanosecond {
        get => $this->spec->nanosecond;
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
     * ISO 8601 day of week: 1 = Monday, 7 = Sunday.
     *
     * @var int<1, 7>
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $dayOfWeek {
        get => $this->spec->dayOfWeek;
    }

    /**
     * Ordinal day of the year (1-based). Range depends on the calendar system.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $dayOfYear {
        get => $this->spec->dayOfYear;
    }

    /**
     * ISO 8601 week number: 1–53, or null for non-ISO calendars.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public ?int $weekOfYear {
        get => $this->spec->weekOfYear;
    }

    /**
     * ISO 8601 week-year (may differ from calendar year near year boundaries),
     * or null for non-ISO calendars.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public ?int $yearOfWeek {
        get => $this->spec->yearOfWeek;
    }

    /**
     * Number of days in this date's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $daysInMonth {
        get => $this->spec->daysInMonth;
    }

    /**
     * Days in a week (always 7).
     *
     * @psalm-api
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $daysInWeek {
        get => $this->spec->daysInWeek;
    }

    /**
     * Number of days in this date's year.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $daysInYear {
        get => $this->spec->daysInYear;
    }

    /**
     * Number of months in this date's year.
     *
     * @psalm-api
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public int $monthsInYear {
        get => $this->spec->monthsInYear;
    }

    /**
     * True if this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public bool $inLeapYear {
        get => $this->spec->inLeapYear;
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    private readonly SpecPlainDateTime $spec;

    /**
     * Creates a new PlainDateTime from ISO date and time components.
     *
     * @param int          $isoYear     ISO year.
     * @param int<1, 12>   $isoMonth    ISO month of the year (1–12).
     * @param int<1, 31>   $isoDay      ISO day of the month (1–31, depending on month/year).
     * @param int<0, 23>   $hour        Hour of the day (0–23).
     * @param int<0, 59>   $minute      Minute of the hour (0–59).
     * @param int<0, 59>   $second      Second of the minute (0–59).
     * @param int<0, 999>  $millisecond Millisecond (0–999).
     * @param int<0, 999>  $microsecond Microsecond (0–999).
     * @param int<0, 999>  $nanosecond  Nanosecond (0–999).
     * @param string|null  $calendarId  Calendar identifier, or null for "iso8601".
     * @throws \InvalidArgumentException if any value is out of range.
     */
    public function __construct(
        int $isoYear,
        int $isoMonth,
        int $isoDay,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        int $millisecond = 0,
        int $microsecond = 0,
        int $nanosecond = 0,
        ?string $calendarId = null,
    ) {
        $this->spec = new SpecPlainDateTime(
            $isoYear,
            $isoMonth,
            $isoDay,
            $hour,
            $minute,
            $second,
            $millisecond,
            $microsecond,
            $nanosecond,
            $calendarId,
        );
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Parses an ISO 8601 datetime string into a PlainDateTime.
     *
     * @param string $text ISO 8601 datetime string (e.g. "2020-01-01T12:30:00").
     * @return self
     * @throws \InvalidArgumentException if the string cannot be parsed.
     */
    public static function parse(string $text): self
    {
        $spec = SpecPlainDateTime::from($text);

        return self::fromSpec($spec);
    }

    /**
     * Compares two PlainDateTimes chronologically.
     *
     * @return int Negative if $one is earlier, positive if later, zero if equal.
     */
    public static function compare(self $one, self $two): int
    {
        return SpecPlainDateTime::compare($one->spec, $two->spec);
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new PlainDateTime with the specified fields overridden.
     *
     * @param int|null         $year        Year override, or null to keep current.
     * @param int<1, 12>|null  $month       Month override (1–12), or null to keep current.
     * @param int<1, 31>|null  $day         Day override, or null to keep current.
     * @param int<0, 23>|null  $hour        Hour override (0–23), or null to keep current.
     * @param int<0, 59>|null  $minute      Minute override (0–59), or null to keep current.
     * @param int<0, 59>|null  $second      Second override (0–59), or null to keep current.
     * @param int<0, 999>|null $millisecond Millisecond override (0–999), or null to keep current.
     * @param int<0, 999>|null $microsecond Microsecond override (0–999), or null to keep current.
     * @param int<0, 999>|null $nanosecond  Nanosecond override (0–999), or null to keep current.
     * @param Overflow         $overflow    How to handle out-of-range values.
     * @return self A new PlainDateTime with the overridden fields.
     * @throws \InvalidArgumentException if the resulting datetime is invalid (overflow: reject) or fields conflict.
     */
    public function with(
        ?int $year = null,
        ?int $month = null,
        ?int $day = null,
        ?int $hour = null,
        ?int $minute = null,
        ?int $second = null,
        ?int $millisecond = null,
        ?int $microsecond = null,
        ?int $nanosecond = null,
        Overflow $overflow = Overflow::Constrain,
    ): self {
        $fields = [];
        if ($year !== null) {
            $fields['year'] = $year;
        }
        if ($month !== null) {
            $fields['month'] = $month;
        }
        if ($day !== null) {
            $fields['day'] = $day;
        }
        if ($hour !== null) {
            $fields['hour'] = $hour;
        }
        if ($minute !== null) {
            $fields['minute'] = $minute;
        }
        if ($second !== null) {
            $fields['second'] = $second;
        }
        if ($millisecond !== null) {
            $fields['millisecond'] = $millisecond;
        }
        if ($microsecond !== null) {
            $fields['microsecond'] = $microsecond;
        }
        if ($nanosecond !== null) {
            $fields['nanosecond'] = $nanosecond;
        }

        $spec = $this->spec->with($fields, ['overflow' => $overflow->value]);

        return self::fromSpec($spec);
    }

    /**
     * Returns a new PlainDateTime with the given duration added.
     *
     * @param Duration $duration The duration to add.
     * @param Overflow $overflow How to handle out-of-range values after calendar arithmetic.
     * @return self
     */
    public function add(Duration $duration, Overflow $overflow = Overflow::Constrain): self
    {
        $spec = $this->spec->add($duration->toSpec(), ['overflow' => $overflow->value]);

        return self::fromSpec($spec);
    }

    /**
     * Returns a new PlainDateTime with the given duration subtracted.
     *
     * @param Duration $duration The duration to subtract.
     * @param Overflow $overflow How to handle out-of-range values after calendar arithmetic.
     * @return self
     */
    public function subtract(Duration $duration, Overflow $overflow = Overflow::Constrain): self
    {
        $spec = $this->spec->subtract($duration->toSpec(), ['overflow' => $overflow->value]);

        return self::fromSpec($spec);
    }

    /**
     * Returns the duration from $other to this datetime (this - other).
     *
     * @param self         $other             The other datetime to measure from.
     * @param Unit         $largestUnit       The largest unit to use in the result.
     * @param Unit         $smallestUnit      The smallest unit to use in the result.
     * @param RoundingMode $roundingMode      How to round the result.
     * @param int          $roundingIncrement The rounding increment for the smallest unit.
     * @return Duration
     */
    public function since(
        self $other,
        Unit $largestUnit = Unit::Day,
        Unit $smallestUnit = Unit::Nanosecond,
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
     * Returns the duration from this datetime to $other (other - this).
     *
     * @param self         $other             The other datetime to measure to.
     * @param Unit         $largestUnit       The largest unit to use in the result.
     * @param Unit         $smallestUnit      The smallest unit to use in the result.
     * @param RoundingMode $roundingMode      How to round the result.
     * @param int          $roundingIncrement The rounding increment for the smallest unit.
     * @return Duration
     */
    public function until(
        self $other,
        Unit $largestUnit = Unit::Day,
        Unit $smallestUnit = Unit::Nanosecond,
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
     * Returns a new PlainDateTime rounded to the given unit and increment.
     *
     * @param Unit         $smallestUnit      The unit to round to.
     * @param RoundingMode $roundingMode      Rounding mode (default: HalfExpand).
     * @param int          $roundingIncrement Must evenly divide the next-larger unit.
     * @return self
     * @throws \InvalidArgumentException for invalid unit or increment values.
     */
    public function round(
        Unit $smallestUnit,
        RoundingMode $roundingMode = RoundingMode::HalfExpand,
        int $roundingIncrement = 1,
    ): self {
        $spec = $this->spec->round([
            'smallestUnit' => $smallestUnit->value,
            'roundingMode' => $roundingMode->value,
            'roundingIncrement' => $roundingIncrement,
        ]);

        return self::fromSpec($spec);
    }

    /**
     * Returns true if this PlainDateTime represents the same date and time as $other.
     *
     * @param self $other The datetime to compare with.
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->spec->equals($other->spec);
    }

    /**
     * Returns the ISO 8601 string representation of this datetime.
     *
     * @param CalendarDisplay $calendarName          Whether to include the calendar annotation.
     * @param int|null        $fractionalSecondDigits Number of fractional second digits (0–9), or null for 'auto'.
     * @param Unit|null       $smallestUnit           Smallest unit to display; overrides $fractionalSecondDigits.
     * @param RoundingMode    $roundingMode           Rounding mode for display (default: Trunc).
     * @return string
     */
    public function toString(
        CalendarDisplay $calendarName = CalendarDisplay::Auto,
        ?int $fractionalSecondDigits = null,
        ?Unit $smallestUnit = null,
        RoundingMode $roundingMode = RoundingMode::Trunc,
    ): string {
        $opts = ['calendarName' => $calendarName->value];

        if ($fractionalSecondDigits !== null) {
            $opts['fractionalSecondDigits'] = $fractionalSecondDigits;
        }
        if ($smallestUnit !== null) {
            $opts['smallestUnit'] = $smallestUnit->value;
        }
        $opts['roundingMode'] = $roundingMode->value;

        return $this->spec->toString($opts);
    }

    /**
     * Returns the date part as a PlainDate.
     *
     * @return PlainDate
     */
    public function toPlainDate(): PlainDate
    {
        return PlainDate::fromSpec($this->spec->toPlainDate());
    }

    /**
     * Returns the time part as a PlainTime.
     *
     * @return PlainTime
     */
    public function toPlainTime(): PlainTime
    {
        return PlainTime::fromSpec($this->spec->toPlainTime());
    }

    /**
     * Returns a new PlainDateTime with the time part replaced.
     *
     * When called with no argument (or null), the time defaults to midnight (00:00:00).
     *
     * @param PlainTime|null $time The time to set, or null for midnight.
     * @return self
     */
    public function withPlainTime(?PlainTime $time = null): self
    {
        if ($time === null) {
            $spec = $this->spec->withPlainTime();
        } else {
            $spec = $this->spec->withPlainTime($time->toSpec());
        }

        return self::fromSpec($spec);
    }

    /**
     * Converts this datetime to a ZonedDateTime in the given time zone.
     *
     * @api
     * @param string         $timeZone      IANA time zone identifier or UTC offset string.
     * @param Disambiguation $disambiguation How to resolve ambiguous wall-clock times.
     * @return ZonedDateTime
     * @throws \InvalidArgumentException if the time zone is invalid or the result is out of range.
     */
    public function toZonedDateTime(
        string $timeZone,
        Disambiguation $disambiguation = Disambiguation::Compatible,
    ): ZonedDateTime {
        $specZdt = $this->spec->toZonedDateTime($timeZone, [
            'disambiguation' => $disambiguation->value,
        ]);

        return ZonedDateTime::fromSpec($specZdt);
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
     * Returns the underlying spec-layer PlainDateTime.
     *
     * @return SpecPlainDateTime
     */
    public function toSpec(): SpecPlainDateTime
    {
        return $this->spec;
    }

    /**
     * Creates a porcelain PlainDateTime from a spec-layer PlainDateTime.
     *
     * @param SpecPlainDateTime $spec The spec-layer instance to wrap.
     * @return self
     */
    public static function fromSpec(SpecPlainDateTime $spec): self
    {
        return new self(
            $spec->isoYear,
            $spec->isoMonth,
            $spec->isoDay,
            $spec->hour,
            $spec->minute,
            $spec->second,
            $spec->millisecond,
            $spec->microsecond,
            $spec->nanosecond,
            $spec->calendarId,
        );
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
            'day' => $this->day,
            'hour' => $this->hour,
            'minute' => $this->minute,
            'second' => $this->second,
            'millisecond' => $this->millisecond,
            'microsecond' => $this->microsecond,
            'nanosecond' => $this->nanosecond,
            'calendarId' => $this->calendarId,
            'iso' => $this->toString(),
        ];
    }
}
