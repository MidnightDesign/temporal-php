<?php

declare(strict_types=1);

namespace Temporal;

/**
 * A date-time anchored to a specific time zone and instant.
 *
 * This is the porcelain (user-facing) wrapper around the spec-layer
 * {@see Spec\ZonedDateTime}. It provides typed enums, named parameters, and
 * a simpler API surface for application code while delegating all computation
 * to the spec layer.
 */
final class ZonedDateTime implements \Stringable, \JsonSerializable
{
    // -------------------------------------------------------------------------
    // Virtual (get-only) epoch properties
    // -------------------------------------------------------------------------

    /**
     * Nanoseconds since the Unix epoch (1970-01-01T00:00:00Z).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $epochNanoseconds {
        get => $this->spec->epochNanoseconds;
    }

    /**
     * Milliseconds since the Unix epoch (floor-divided from nanoseconds).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $epochMilliseconds {
        get => $this->spec->epochMilliseconds;
    }

    // -------------------------------------------------------------------------
    // Virtual (get-only) identity properties
    // -------------------------------------------------------------------------

    /**
     * The IANA timezone identifier, 'UTC', or a fixed offset string (e.g. '+05:30').
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public string $timeZoneId {
        get => $this->spec->timeZoneId;
    }

    /**
     * Calendar system used for date field projection.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public Calendar $calendar {
        get => Calendar::from($this->spec->calendarId);
    }

    // -------------------------------------------------------------------------
    // Virtual (get-only) date/time component properties
    // -------------------------------------------------------------------------

    /**
     * Calendar year (projected through the active calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $year {
        get => $this->spec->year;
    }

    /**
     * Month of the year (projected through the active calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $month {
        get => $this->spec->month;
    }

    /**
     * Day of the month (projected through the active calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $day {
        get => $this->spec->day;
    }

    /**
     * Hour of the day (0-23).
     *
     * @var int<0, 23>
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $hour {
        get => $this->spec->hour;
    }

    /**
     * Minute of the hour (0-59).
     *
     * @var int<0, 59>
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $minute {
        get => $this->spec->minute;
    }

    /**
     * Second of the minute (0-59).
     *
     * @var int<0, 59>
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $second {
        get => $this->spec->second;
    }

    /**
     * Millisecond within the second (0-999).
     *
     * @var int<0, 999>
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $millisecond {
        get => $this->spec->millisecond;
    }

    /**
     * Microsecond within the millisecond (0-999).
     *
     * @var int<0, 999>
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $microsecond {
        get => $this->spec->microsecond;
    }

    /**
     * Nanosecond within the microsecond (0-999).
     *
     * @var int<0, 999>
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $nanosecond {
        get => $this->spec->nanosecond;
    }

    // -------------------------------------------------------------------------
    // Virtual (get-only) offset properties
    // -------------------------------------------------------------------------

    /**
     * The UTC offset string for this instant in this time zone (e.g. '+05:30').
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public string $offset {
        get => $this->spec->offset;
    }

    /**
     * The UTC offset in nanoseconds for this instant in this time zone.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $offsetNanoseconds {
        get => $this->spec->offsetNanoseconds;
    }

    // -------------------------------------------------------------------------
    // Virtual (get-only) calendar properties
    // -------------------------------------------------------------------------

    /**
     * Calendar era identifier (e.g. "ce", "bce", "reiwa"), or null for calendars without eras.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public ?string $era {
        get => $this->spec->era;
    }

    /**
     * Year within the calendar era, or null for calendars without eras.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    public ?int $eraYear {
        get => $this->spec->eraYear;
    }

    /**
     * Month code in "M01"-"M12" format, or "M01L"-"M12L" for leap months.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public string $monthCode {
        get => $this->spec->monthCode;
    }

    /**
     * ISO 8601 day of week: 1 = Monday, 7 = Sunday.
     *
     * @var int<1, 7>
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $dayOfWeek {
        get => $this->spec->dayOfWeek;
    }

    /**
     * Ordinal day of the year: 1-366.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $dayOfYear {
        get => $this->spec->dayOfYear;
    }

    /**
     * ISO 8601 week number: 1-53, or null for non-ISO calendars.
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
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $daysInMonth {
        get => $this->spec->daysInMonth;
    }

    /**
     * Always 7 (ISO 8601 calendar).
     *
     * @psalm-api
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $daysInWeek {
        get => $this->spec->daysInWeek;
    }

    /**
     * Number of days in this date's year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $daysInYear {
        get => $this->spec->daysInYear;
    }

    /**
     * Number of months in this date's year.
     *
     * @psalm-api
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $monthsInYear {
        get => $this->spec->monthsInYear;
    }

    /**
     * True if this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public bool $inLeapYear {
        get => $this->spec->inLeapYear;
    }

    /**
     * Number of hours in the current day (typically 24; may differ during DST transitions).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     */
    public int $hoursInDay {
        get => (int) $this->spec->hoursInDay;
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /** The underlying spec-layer instance. */
    private readonly Spec\ZonedDateTime $spec;

    /**
     * @param int      $epochNanoseconds Nanoseconds since the Unix epoch.
     * @param string   $timeZoneId       Timezone identifier: 'UTC', '+-HH:MM', or an IANA name.
     * @param Calendar $calendar         Calendar system (default ISO 8601).
     * @throws \InvalidArgumentException if the epoch nanoseconds or time zone are invalid.
     */
    public function __construct(int $epochNanoseconds, string $timeZoneId, Calendar $calendar = Calendar::Iso8601)
    {
        $this->spec = new Spec\ZonedDateTime($epochNanoseconds, $timeZoneId, $calendar->value);
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Parses a ZonedDateTime ISO 8601 string.
     *
     * The string must include a bracketed time zone annotation, e.g.
     * "2020-01-01T12:00:00+05:30[Asia/Kolkata]".
     *
     * @param string         $text           ISO 8601 ZonedDateTime string.
     * @param Disambiguation $disambiguation How to resolve ambiguous wall-clock times.
     * @param OffsetOption   $offset         How to handle a provided UTC offset.
     * @throws \InvalidArgumentException if the string cannot be parsed.
     */
    public static function parse(
        string $text,
        Disambiguation $disambiguation = Disambiguation::Compatible,
        OffsetOption $offset = OffsetOption::Reject,
    ): self {
        return self::fromSpec(Spec\ZonedDateTime::from($text, [
            'disambiguation' => $disambiguation->value,
            'offset' => $offset->value,
        ]));
    }

    /**
     * Creates a ZonedDateTime from a string, property bag, or another ZonedDateTime.
     *
     * @param self|string|array<string, mixed> $item
     */
    public static function from(
        self|string|array $item,
        Disambiguation $disambiguation = Disambiguation::Compatible,
        OffsetOption $offset = OffsetOption::Reject,
        Overflow $overflow = Overflow::Constrain,
    ): self {
        if ($item instanceof self) {
            return self::fromSpec(Spec\ZonedDateTime::from($item->spec));
        }

        return self::fromSpec(Spec\ZonedDateTime::from($item, [
            'disambiguation' => $disambiguation->value,
            'offset' => $offset->value,
            'overflow' => $overflow->value,
        ]));
    }

    /**
     * Compares two ZonedDateTimes by their epoch nanoseconds.
     *
     * @return int -1, 0, or 1.
     */
    public static function compare(self $one, self $two): int
    {
        return Spec\ZonedDateTime::compare($one->spec, $two->spec);
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new ZonedDateTime with the specified fields overridden.
     *
     * @param int|null         $year           Year override, or null to keep.
     * @param int<1, 12>|null  $month          Month override (1-12), or null to keep.
     * @param int<1, 31>|null  $day            Day override, or null to keep.
     * @param int<0, 23>|null  $hour           Hour override (0-23), or null to keep.
     * @param int<0, 59>|null  $minute         Minute override (0-59), or null to keep.
     * @param int<0, 59>|null  $second         Second override (0-59), or null to keep.
     * @param int<0, 999>|null $millisecond    Millisecond override (0-999), or null to keep.
     * @param int<0, 999>|null $microsecond    Microsecond override (0-999), or null to keep.
     * @param int<0, 999>|null $nanosecond     Nanosecond override (0-999), or null to keep.
     * @param string|null $offset         UTC offset string override (e.g. "+05:30"), or null to keep.
     * @param Overflow       $overflow       How to handle out-of-range values.
     * @param Disambiguation $disambiguation How to resolve ambiguous wall-clock times.
     * @param OffsetOption   $offsetOption   How to use the provided offset.
     * @throws \InvalidArgumentException if fields are invalid.
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
        ?string $offset = null,
        ?string $monthCode = null,
        ?string $era = null,
        ?int $eraYear = null,
        Overflow $overflow = Overflow::Constrain,
        Disambiguation $disambiguation = Disambiguation::Compatible,
        OffsetOption $offsetOption = OffsetOption::Prefer,
    ): self {
        $fields = array_filter(
            [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'hour' => $hour,
                'minute' => $minute,
                'second' => $second,
                'millisecond' => $millisecond,
                'microsecond' => $microsecond,
                'nanosecond' => $nanosecond,
                'offset' => $offset,
            ],
            fn($v) => $v !== null,
        );

        if ($monthCode !== null) {
            $fields['monthCode'] = $monthCode;
        }
        if ($era !== null) {
            $fields['era'] = $era;
        }
        if ($eraYear !== null) {
            $fields['eraYear'] = $eraYear;
        }

        $opts = [
            'overflow' => $overflow->value,
            'disambiguation' => $disambiguation->value,
            'offset' => $offsetOption->value,
        ];

        return self::fromSpec($this->spec->with($fields, $opts));
    }

    /**
     * Returns a new ZonedDateTime with the given duration added.
     *
     * @param Duration $duration The duration to add.
     * @param Overflow $overflow How to handle out-of-range values after calendar arithmetic.
     */
    public function add(Duration $duration, Overflow $overflow = Overflow::Constrain): self
    {
        return self::fromSpec($this->spec->add($duration->toSpec(), ['overflow' => $overflow->value]));
    }

    /**
     * Returns a new ZonedDateTime with the given duration subtracted.
     *
     * @param Duration $duration The duration to subtract.
     * @param Overflow $overflow How to handle out-of-range values after calendar arithmetic.
     */
    public function subtract(Duration $duration, Overflow $overflow = Overflow::Constrain): self
    {
        return self::fromSpec($this->spec->subtract($duration->toSpec(), ['overflow' => $overflow->value]));
    }

    /**
     * Returns the duration from $other to this ZonedDateTime (this - other).
     *
     * @param self         $other             The other ZonedDateTime to measure from.
     * @param Unit         $largestUnit       The largest unit in the result (default: Hour).
     * @param Unit         $smallestUnit      The smallest unit in the result (default: Nanosecond).
     * @param RoundingMode $roundingMode      How to round the result (default: Trunc).
     * @param int          $roundingIncrement The rounding increment for the smallest unit.
     */
    public function since(
        self $other,
        Unit $largestUnit = Unit::Hour,
        Unit $smallestUnit = Unit::Nanosecond,
        RoundingMode $roundingMode = RoundingMode::Trunc,
        int $roundingIncrement = 1,
    ): Duration {
        return Duration::fromSpec($this->spec->since($other->spec, [
            'largestUnit' => $largestUnit->value,
            'smallestUnit' => $smallestUnit->value,
            'roundingMode' => $roundingMode->value,
            'roundingIncrement' => $roundingIncrement,
        ]));
    }

    /**
     * Returns the duration from this ZonedDateTime to $other (other - this).
     *
     * @param self         $other             The other ZonedDateTime to measure to.
     * @param Unit         $largestUnit       The largest unit in the result (default: Hour).
     * @param Unit         $smallestUnit      The smallest unit in the result (default: Nanosecond).
     * @param RoundingMode $roundingMode      How to round the result (default: Trunc).
     * @param int          $roundingIncrement The rounding increment for the smallest unit.
     */
    public function until(
        self $other,
        Unit $largestUnit = Unit::Hour,
        Unit $smallestUnit = Unit::Nanosecond,
        RoundingMode $roundingMode = RoundingMode::Trunc,
        int $roundingIncrement = 1,
    ): Duration {
        return Duration::fromSpec($this->spec->until($other->spec, [
            'largestUnit' => $largestUnit->value,
            'smallestUnit' => $smallestUnit->value,
            'roundingMode' => $roundingMode->value,
            'roundingIncrement' => $roundingIncrement,
        ]));
    }

    /**
     * Returns a new ZonedDateTime rounded to the given unit and increment.
     *
     * @param Unit         $smallestUnit       The unit to round to.
     * @param RoundingMode $roundingMode       Rounding mode (default: HalfExpand).
     * @param int          $roundingIncrement  Must evenly divide the next-larger unit.
     * @throws \InvalidArgumentException for invalid unit or increment values.
     */
    public function round(
        Unit $smallestUnit,
        RoundingMode $roundingMode = RoundingMode::HalfExpand,
        int $roundingIncrement = 1,
    ): self {
        return self::fromSpec($this->spec->round([
            'smallestUnit' => $smallestUnit->value,
            'roundingMode' => $roundingMode->value,
            'roundingIncrement' => $roundingIncrement,
        ]));
    }

    /**
     * Returns a new ZonedDateTime representing the start of this date's day
     * in the same time zone.
     *
     * For most time zones this is midnight (00:00:00), but DST transitions
     * that skip midnight may produce a different start-of-day time.
     */
    public function startOfDay(): self
    {
        return self::fromSpec($this->spec->startOfDay());
    }

    /**
     * Returns true if this ZonedDateTime represents the same instant, time zone, and calendar.
     */
    public function equals(self $other): bool
    {
        return $this->spec->equals($other->spec);
    }

    // -------------------------------------------------------------------------
    // toString / Stringable / JsonSerializable
    // -------------------------------------------------------------------------

    /**
     * Returns an ISO 8601 string with time zone and optional calendar annotations.
     *
     * @param int|null         $fractionalSecondDigits Number of fractional second digits (0-9), or null for 'auto'.
     * @param Unit|null        $smallestUnit           Smallest unit to display; overrides $fractionalSecondDigits.
     * @param RoundingMode     $roundingMode           Rounding mode for display (default: Trunc).
     * @param OffsetDisplay    $offset                 Whether to include the UTC offset.
     * @param TimeZoneDisplay  $timeZoneName           Whether to include the time zone name.
     * @param CalendarDisplay  $calendarName           Whether to include the calendar annotation.
     */
    public function toString(
        ?int $fractionalSecondDigits = null,
        ?Unit $smallestUnit = null,
        RoundingMode $roundingMode = RoundingMode::Trunc,
        OffsetDisplay $offset = OffsetDisplay::Auto,
        TimeZoneDisplay $timeZoneName = TimeZoneDisplay::Auto,
        CalendarDisplay $calendarName = CalendarDisplay::Auto,
    ): string {
        $opts = [
            'roundingMode' => $roundingMode->value,
            'offset' => $offset->value,
            'timeZoneName' => $timeZoneName->value,
            'calendarName' => $calendarName->value,
        ];
        if ($fractionalSecondDigits !== null) {
            $opts['fractionalSecondDigits'] = $fractionalSecondDigits;
        }
        if ($smallestUnit !== null) {
            $opts['smallestUnit'] = $smallestUnit->value;
        }

        return $this->spec->toString($opts);
    }

    /**
     * Returns the ISO 8601 string representation (default formatting).
     */
    #[\Override]
    public function __toString(): string
    {
        return $this->spec->toString();
    }

    /**
     * Returns the ISO 8601 string for JSON serialization.
     */
    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->spec->toString();
    }

    // -------------------------------------------------------------------------
    // Conversion methods
    // -------------------------------------------------------------------------

    /**
     * Returns an Instant representing the same point in time.
     */
    public function toInstant(): Instant
    {
        return Instant::fromSpec($this->spec->toInstant());
    }

    /**
     * Returns a PlainDate containing the local date in this time zone.
     */
    public function toPlainDate(): PlainDate
    {
        return PlainDate::fromSpec($this->spec->toPlainDate());
    }

    /**
     * Returns a PlainTime containing the local time in this time zone.
     */
    public function toPlainTime(): PlainTime
    {
        return PlainTime::fromSpec($this->spec->toPlainTime());
    }

    /**
     * Returns a PlainDateTime containing the local date and time in this time zone.
     */
    public function toPlainDateTime(): PlainDateTime
    {
        return PlainDateTime::fromSpec($this->spec->toPlainDateTime());
    }

    /**
     * Returns a new ZonedDateTime with a different time zone.
     *
     * The epoch nanoseconds remain the same; only the local time display changes.
     *
     * @param string $timeZone IANA timezone identifier, UTC offset string, or 'UTC'.
     * @throws \InvalidArgumentException if the time zone is invalid.
     */
    public function withTimeZone(string $timeZone): self
    {
        return self::fromSpec($this->spec->withTimeZone($timeZone));
    }

    /**
     * Returns a new ZonedDateTime with a different calendar system.
     *
     * The epoch nanoseconds and time zone remain the same; only the calendar projection changes.
     */
    public function withCalendar(Calendar $calendar): self
    {
        return self::fromSpec($this->spec->withCalendar($calendar->value));
    }

    /**
     * Returns a new ZonedDateTime with the time portion replaced.
     *
     * If $time is null (or omitted), the time is set to midnight (00:00:00).
     *
     * @param PlainTime|null $time The time to set, or null for midnight.
     */
    public function withPlainTime(?PlainTime $time = null): self
    {
        if ($time === null) {
            return self::fromSpec($this->spec->withPlainTime());
        }

        return self::fromSpec($this->spec->withPlainTime($time->toSpec()));
    }

    /**
     * Finds the next or previous time zone transition (e.g. DST change).
     *
     * Returns null for fixed-offset time zones (UTC, +-HH:MM).
     *
     * @param TransitionDirection $direction Whether to search forward ('next') or backward ('previous').
     */
    public function getTimeZoneTransition(TransitionDirection $direction): ?self
    {
        $result = $this->spec->getTimeZoneTransition($direction->value);

        if ($result === null) {
            return null;
        }

        return self::fromSpec($result);
    }

    // -------------------------------------------------------------------------
    // Spec-layer interop
    // -------------------------------------------------------------------------

    /**
     * Returns the underlying spec-layer ZonedDateTime.
     */
    public function toSpec(): Spec\ZonedDateTime
    {
        return $this->spec;
    }

    /**
     * Creates a porcelain ZonedDateTime from a spec-layer ZonedDateTime.
     */
    public static function fromSpec(Spec\ZonedDateTime $spec): self
    {
        return new self($spec->epochNanoseconds, $spec->timeZoneId, Calendar::from($spec->calendarId));
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
            'epochNanoseconds' => $this->epochNanoseconds,
            'timeZoneId' => $this->timeZoneId,
            'calendar' => $this->calendar->value,
            'string' => $this->spec->toString(),
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
            'hour' => $this->hour,
            'minute' => $this->minute,
            'second' => $this->second,
            'millisecond' => $this->millisecond,
            'microsecond' => $this->microsecond,
            'nanosecond' => $this->nanosecond,
            'offset' => $this->offset,
        ];
    }
}
