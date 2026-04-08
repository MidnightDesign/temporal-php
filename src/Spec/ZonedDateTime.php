<?php

declare(strict_types=1);

namespace Temporal\Spec;

use InvalidArgumentException;
use Stringable;
use Temporal\Spec\Internal\Calendar\CalendarFactory;
use Temporal\Spec\Internal\CalendarMath;
use Temporal\Spec\Internal\TemporalSerde;

/**
 * A date-time anchored to a specific timezone and instant.
 *
 * Stores the number of nanoseconds since the Unix epoch alongside a timezone
 * identifier and calendar identifier. Only the ISO 8601 calendar is supported.
 * Supported timezones: 'UTC', fixed-offset strings (±HH:MM), and IANA names
 * accepted by PHP's DateTimeZone.
 *
 * @psalm-api
 * @see https://tc39.es/proposal-temporal/#sec-temporal-zoneddatetime-objects
 */
final class ZonedDateTime implements Stringable
{
    use TemporalSerde;

    private const int NS_PER_SECOND = 1_000_000_000;
    private const int NS_PER_MILLISECOND = 1_000_000;
    private const int NS_PER_MICROSECOND = 1_000;

    // -------------------------------------------------------------------------
    // Actual stored property
    // -------------------------------------------------------------------------

    /** @psalm-suppress PropertyNotSetInConstructor — set unconditionally in constructor */
    public readonly int $epochNanoseconds;

    /** @psalm-suppress PropertyNotSetInConstructor — set unconditionally in constructor */
    public readonly string $timeZoneId;

    /**
     * @psalm-suppress PropertyNotSetInConstructor — set unconditionally in constructor
     * @psalm-suppress PossiblyUnusedProperty — used from test262 scripts excluded from Psalm
     */
    public readonly string $calendarId;

    /** @var array{year:int, month:int<1,12>, day:int<1,31>, hour:int<0,23>, minute:int<0,59>, second:int<0,59>, millisecond:int<0,999>, microsecond:int<0,999>, nanosecond:int<0,999>, offsetSec:int, offset:string}|null $localCache */
    private ?array $localCache = null;

    /**
     * True epoch seconds (UTC) — set when epochNanoseconds is a sentinel
     * (PHP_INT_MIN/MAX) because the actual value overflows int64 nanoseconds.
     */
    private ?int $trueEpochSec = null;

    /** Sub-second nanoseconds (0–999_999_999) paired with $trueEpochSec. */
    private int $trueSubNs = 0;

    // -------------------------------------------------------------------------
    // Virtual (get-only) date/time properties
    // -------------------------------------------------------------------------

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $year {
        get {
            $c = $this->localComponents();
            return $this->calendarId === 'iso8601'
                ? $c['year']
                : CalendarFactory::get($this->calendarId)->year($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<1, 12>
     */
    public int $month {
        get {
            $c = $this->localComponents();
            return $this->calendarId === 'iso8601'
                ? $c['month']
                : CalendarFactory::get($this->calendarId)->month($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<1, 31>
     */
    public int $day {
        get {
            $c = $this->localComponents();
            return $this->calendarId === 'iso8601'
                ? $c['day']
                : CalendarFactory::get($this->calendarId)->day($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<0, 23>
     */
    public int $hour {
        get => $this->localComponents()['hour'];
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<0, 59>
     */
    public int $minute {
        get => $this->localComponents()['minute'];
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<0, 59>
     */
    public int $second {
        get => $this->localComponents()['second'];
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<0, 999>
     */
    public int $millisecond {
        get => $this->localComponents()['millisecond'];
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<0, 999>
     */
    public int $microsecond {
        get => $this->localComponents()['microsecond'];
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<0, 999>
     */
    public int $nanosecond {
        get => $this->localComponents()['nanosecond'];
    }

    /**
     * Milliseconds since the Unix epoch (floor-divided from nanoseconds).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $epochMilliseconds {
        get => CalendarMath::floorDiv($this->epochNanoseconds, self::NS_PER_MILLISECOND);
    }

    /**
     * The UTC offset string for this instant in this timezone (e.g. '+05:30').
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $offset {
        get => $this->localComponents()['offset'];
    }

    /**
     * The UTC offset in nanoseconds for this instant in this timezone.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $offsetNanoseconds {
        get => $this->localComponents()['offsetSec'] * self::NS_PER_SECOND;
    }

    // -------------------------------------------------------------------------
    // Virtual calendar properties
    // -------------------------------------------------------------------------

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?string $era {
        get {
            $c = $this->localComponents();
            return CalendarFactory::get($this->calendarId)->era($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $eraYear {
        get {
            $c = $this->localComponents();
            return CalendarFactory::get($this->calendarId)->eraYear($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode {
        get {
            $c = $this->localComponents();
            return CalendarFactory::get($this->calendarId)->monthCode($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * ISO 8601 day of week: 1 = Monday, 7 = Sunday.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<1, 7>
     */
    public int $dayOfWeek {
        get {
            $c = $this->localComponents();
            return CalendarMath::isoWeekday($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * Ordinal day of the year: 1–366.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<1, 366>
     */
    public int $dayOfYear {
        get {
            $c = $this->localComponents();
            return $this->calendarId === 'iso8601'
                ? CalendarMath::calcDayOfYear($c['year'], $c['month'], $c['day'])
                : CalendarFactory::get($this->calendarId)->dayOfYear($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * ISO 8601 week number: 1–53, or null for non-ISO calendars.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $weekOfYear {
        get {
            if ($this->calendarId !== 'iso8601') {
                return null;
            }
            $c = $this->localComponents();
            return CalendarMath::isoWeekInfo($c['year'], $c['month'], $c['day'])['week'];
        }
    }

    /**
     * ISO 8601 week-year, or null for non-ISO calendars.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $yearOfWeek {
        get {
            if ($this->calendarId !== 'iso8601') {
                return null;
            }
            $c = $this->localComponents();
            return CalendarMath::isoWeekInfo($c['year'], $c['month'], $c['day'])['year'];
        }
    }

    /**
     * Number of days in this date's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<28, 31>
     */
    public int $daysInMonth {
        get {
            $c = $this->localComponents();
            return CalendarFactory::get($this->calendarId)->daysInMonth($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * Always 7 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<7, 7>
     */
    public int $daysInWeek {
        get => 7;
    }

    /**
     * 365 or 366, depending on whether this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<365, 366>
     */
    public int $daysInYear {
        get {
            $c = $this->localComponents();
            return CalendarFactory::get($this->calendarId)->daysInYear($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * Always 12 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     * @var int<12, 12>
     */
    public int $monthsInYear {
        get {
            $c = $this->localComponents();
            return CalendarFactory::get($this->calendarId)->monthsInYear($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * True if this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public bool $inLeapYear {
        get {
            $c = $this->localComponents();
            return CalendarFactory::get($this->calendarId)->inLeapYear($c['year'], $c['month'], $c['day']);
        }
    }

    /**
     * Number of hours in the current day (always 24 for UTC/fixed-offset timezones).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int|float $hoursInDay {
        get {
            // Compute the actual hours in the local day by finding
            // start-of-day for today and tomorrow, accounting for DST.
            $lc = $this->localComponents();
            $todayJdn = CalendarMath::toJulianDay($lc['year'], $lc['month'], $lc['day']);
            $todayEpochDays = $todayJdn - 2_440_588;
            $tomorrowEpochDays = $todayEpochDays + 1;

            $todayWallSec = $todayEpochDays * 86_400;
            $tomorrowWallSec = $tomorrowEpochDays * 86_400;

            $todayEpochSec = self::wallSecToEpochSecStartOfDay($todayWallSec, $this->timeZoneId);
            $tomorrowEpochSec = self::wallSecToEpochSecStartOfDay($tomorrowWallSec, $this->timeZoneId);

            $diffSec = $tomorrowEpochSec - $todayEpochSec;
            $hours = $diffSec / 3600.0;

            // Return int when it's a whole number, float otherwise.
            return $hours === (float) (int) $hours ? (int) $hours : $hours;
        }
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param int|float $epochNanoseconds Nanoseconds since the Unix epoch. Must be a
     *        finite integer value within the Temporal spec range (±8.64e21 ns).
     * @param string    $timeZoneId       Timezone identifier: 'UTC', '±HH:MM', or an IANA name.
     * @param string    $calendarId       Calendar identifier (only 'iso8601' is supported).
     * @throws InvalidArgumentException if epochNanoseconds is not a finite integer value,
     *                                  or if the timezone is invalid.
     */
    public function __construct(int|float $epochNanoseconds, string $timeZoneId, string $calendarId = 'iso8601')
    {
        if (is_float($epochNanoseconds)) {
            if (!is_finite($epochNanoseconds) || floor($epochNanoseconds) !== $epochNanoseconds) {
                throw new InvalidArgumentException('ZonedDateTime epochNanoseconds must be a finite integer value.');
            }
            // Float beyond int64 range: validate using epoch-second range and use sentinel.
            if ($epochNanoseconds > (float) PHP_INT_MAX || $epochNanoseconds < (float) PHP_INT_MIN) {
                // Validate epoch seconds first (avoids garbage from int cast of huge floats).
                $epochSecF = floor($epochNanoseconds / 1e9);
                $maxSec = 8_640_000_000_000.0;
                if ($epochSecF > $maxSec || $epochSecF < -$maxSec) {
                    throw new InvalidArgumentException(
                        'ZonedDateTime epochNanoseconds value exceeds the representable range.',
                    );
                }
                // Check for boundary: reject if at the exact limit with any sub-ns remainder.
                // Since PHP floats can't distinguish nsMax from nsMax+1, accept only the exact boundary.
                $epochSec = (int) $epochSecF;
                $subNs = (int) fmod(num1: $epochNanoseconds, num2: 1e9);
                if ($subNs < 0) {
                    $epochSec--;
                    $subNs += self::NS_PER_SECOND;
                }
                $this->epochNanoseconds = $epochNanoseconds < 0 ? PHP_INT_MIN : PHP_INT_MAX;
                $this->trueEpochSec = $epochSec;
                $this->trueSubNs = $subNs;
                $this->timeZoneId = self::normalizeTimezoneId($timeZoneId, true);
                $this->calendarId = CalendarFactory::canonicalize($calendarId);
                return;
            }
            $epochNanoseconds = (int) $epochNanoseconds;
        }
        $this->epochNanoseconds = $epochNanoseconds;
        $this->timeZoneId = self::normalizeTimezoneId($timeZoneId, true);
        $this->calendarId = CalendarFactory::canonicalize($calendarId);
    }

    // -------------------------------------------------------------------------
    // Static factory / comparison methods
    // -------------------------------------------------------------------------

    /**
     * Creates a ZonedDateTime from another ZonedDateTime, a ZDT ISO string,
     * or a property-bag array/object.
     *
     * String format: ISO datetime with REQUIRED bracket timezone annotation,
     * e.g. '2020-01-01T12:00:00+05:30[Asia/Kolkata]'.
     *
     * @param self|string|array<array-key, mixed>|object $item    ZonedDateTime, ISO string, or property-bag array/object.
     * @param array<array-key, mixed>|object|null $options Options array; supports 'disambiguation' (string).
     * @throws \TypeError              for unsupported types.
     * @throws InvalidArgumentException for invalid strings or property bags.
     * @psalm-api
     */
    public static function from(string|array|object $item, array|object|null $options = null): self
    {
        $opts = null;
        if (is_array($options)) {
            $opts = $options;
        } elseif (is_object($options)) {
            $opts = (array) $options;
        }

        // Validate 'disambiguation' option if present.
        if ($opts !== null && array_key_exists('disambiguation', $opts)) {
            /** @var mixed $dv */
            $dv = $opts['disambiguation'];
            if (!is_string($dv)) {
                throw new InvalidArgumentException('ZonedDateTime::from() disambiguation option must be a string.');
            }
            if (!in_array(needle: $dv, haystack: ['compatible', 'earlier', 'later', 'reject'], strict: true)) {
                throw new InvalidArgumentException(
                    "Invalid disambiguation value \"{$dv}\"; must be 'compatible', 'earlier', 'later', or 'reject'.",
                );
            }
        }

        // Validate 'overflow' option.
        $overflow = 'constrain';
        if ($opts !== null && array_key_exists('overflow', $opts)) {
            /** @var mixed $ov */
            $ov = $opts['overflow'];
            if ($ov === null || is_bool($ov)) {
                throw new InvalidArgumentException("Invalid overflow value: must be 'constrain' or 'reject'.");
            }
            if (!is_string($ov)) {
                throw new \TypeError('overflow option must be a string.');
            }
            if ($ov !== 'constrain' && $ov !== 'reject') {
                throw new InvalidArgumentException(
                    "Invalid overflow value \"{$ov}\": must be 'constrain' or 'reject'.",
                );
            }
            $overflow = $ov;
        }

        // Validate 'offset' option if present.
        if ($opts !== null && array_key_exists('offset', $opts)) {
            /** @var mixed $offOpt */
            $offOpt = $opts['offset'];
            if ($offOpt !== null) {
                if (!is_string($offOpt)) {
                    throw new \TypeError('offset option must be a string.');
                }
                if (!in_array(needle: $offOpt, haystack: ['use', 'ignore', 'prefer', 'reject'], strict: true)) {
                    throw new InvalidArgumentException(
                        "Invalid offset option \"{$offOpt}\"; must be 'use', 'ignore', 'prefer', or 'reject'.",
                    );
                }
            }
        }

        if ($item instanceof self) {
            return new self($item->epochNanoseconds, $item->timeZoneId, $item->calendarId);
        }
        if (is_string($item)) {
            return self::parseZdtString($item, $options);
        }
        $disambiguation = 'compatible';
        if ($opts !== null && array_key_exists('disambiguation', $opts) && is_string($opts['disambiguation'])) {
            $disambiguation = $opts['disambiguation'];
        }
        $offsetOption = 'reject';
        if ($opts !== null && array_key_exists('offset', $opts) && is_string($opts['offset'])) {
            $offsetOption = $opts['offset'];
        }
        $bag = is_array($item) ? $item : (array) $item;
        return self::fromPropertyBag($bag, $overflow, $disambiguation, $offsetOption);
    }

    /**
     * Compares two ZonedDateTimes by their epoch nanoseconds.
     *
     * @param self|string|array<array-key, mixed>|object $one ZonedDateTime or value coercible via from().
     * @param self|string|array<array-key, mixed>|object $two ZonedDateTime or value coercible via from().
     * @return int -1, 0, or 1.
     * @psalm-api
     */
    public static function compare(string|array|object $one, string|array|object $two): int
    {
        $a = $one instanceof self ? $one : self::from($one);
        $b = $two instanceof self ? $two : self::from($two);
        return self::compareInstants($a, $b);
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    /**
     * Returns an Instant representing the same point in time.
     *
     * @psalm-api
     */
    public function toInstant(): Instant
    {
        return new Instant($this->epochNanoseconds);
    }

    /**
     * Returns a PlainDate containing the local date in this timezone.
     *
     * @psalm-api
     */
    public function toPlainDate(): PlainDate
    {
        $c = $this->localComponents();
        return new PlainDate($c['year'], $c['month'], $c['day'], $this->calendarId);
    }

    /**
     * Returns a PlainTime containing the local time in this timezone.
     *
     * @psalm-api
     */
    public function toPlainTime(): PlainTime
    {
        return new PlainTime(
            $this->hour,
            $this->minute,
            $this->second,
            $this->millisecond,
            $this->microsecond,
            $this->nanosecond,
        );
    }

    /**
     * Returns a PlainDateTime containing the local date and time in this timezone.
     *
     * @psalm-api
     */
    public function toPlainDateTime(): PlainDateTime
    {
        $c = $this->localComponents();
        return new PlainDateTime(
            $c['year'],
            $c['month'],
            $c['day'],
            $c['hour'],
            $c['minute'],
            $c['second'],
            $c['millisecond'],
            $c['microsecond'],
            $c['nanosecond'],
            $this->calendarId,
        );
    }

    /**
     * Returns a new ZonedDateTime with a different timezone.
     *
     * The epoch nanoseconds remain the same; only the local time display changes.
     *
     * @throws \TypeError              if $timeZone is not a string.
     * @throws InvalidArgumentException if the timezone is invalid.
     * @psalm-api
     */
    public function withTimeZone(string $timeZone): self
    {
        // Normalize before constructing so datetime strings are accepted here
        // (the constructor rejects them with $rejectDatetimeStrings = true).
        $normalizedTz = self::normalizeTimezoneId($timeZone);
        return new self($this->epochNanoseconds, $normalizedTz, $this->calendarId);
    }

    /**
     * Returns a new ZonedDateTime with a different calendar.
     *
     * Only 'iso8601' is supported (case-insensitive).
     *
     * @throws InvalidArgumentException if an unsupported calendar is given.
     * @psalm-api
     */
    public function withCalendar(string $calendar): self
    {
        $calId = self::extractCalendarFromString($calendar);
        return new self($this->epochNanoseconds, $this->timeZoneId, $calId);
    }

    /**
     * Returns a new ZonedDateTime with the time portion replaced.
     *
     * If $time is null the time is set to midnight (00:00:00).
     * Accepts PlainTime, null, a time string, or a property-bag array.
     *
     * @param PlainTime|string|array<array-key, mixed>|object|null $time PlainTime, null, string, or array.
     * @psalm-api
     */
    public function withPlainTime(string|array|object|null $time = null): self
    {
        // When called with no arguments, use startOfDay semantics (TC39 spec).
        // This handles cross-midnight DST gaps correctly.
        if (func_num_args() === 0) {
            return $this->startOfDay();
        }
        if ($time === null) {
            throw new \TypeError(
                'ZonedDateTime::withPlainTime() argument must be a PlainTime, string, or property bag; null given.',
            );
        } elseif ($time instanceof PlainTime) {
            $h = $time->hour;
            $m = $time->minute;
            $s = $time->second;
            $ms = $time->millisecond;
            $us = $time->microsecond;
            $ns = $time->nanosecond;
        } else {
            $pt = PlainTime::from($time);
            $h = $pt->hour;
            $m = $pt->minute;
            $s = $pt->second;
            $ms = $pt->millisecond;
            $us = $pt->microsecond;
            $ns = $pt->nanosecond;
        }

        // Compute the local wall-clock seconds for the new datetime using the existing ISO date.
        $lc = $this->localComponents();
        try {
            $wallDt = new \DateTimeImmutable(sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d+00:00',
                $lc['year'],
                $lc['month'],
                $lc['day'],
                $h,
                $m,
                $s,
            ));
        } catch (\Exception) {
            throw new InvalidArgumentException('ZonedDateTime::withPlainTime() could not construct datetime.');
        }
        $wallSec = $wallDt->getTimestamp();

        // Determine the timezone offset at this new wall-clock second.
        // For a fixed offset timezone we can use it directly; for IANA we need
        // to do a wall-clock → UTC conversion.
        $epochSec = self::wallSecToEpochSec($wallSec, $this->timeZoneId);

        $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;

        return self::fromEpochParts($epochSec, $subNs, $this->timeZoneId, $this->calendarId);
    }

    /**
     * Returns a new ZonedDateTime representing the start of this date's day
     * in the same timezone.
     *
     * For most timezones this is midnight (00:00:00), but DST transitions that
     * skip midnight may produce a different start-of-day time.
     *
     * @throws InvalidArgumentException if the resulting epoch nanoseconds are out of range.
     * @psalm-api
     */
    public function startOfDay(): self
    {
        // Compute wall-clock midnight for the current local date.
        $lc = $this->localComponents();
        $epochDays = CalendarMath::toJulianDay($lc['year'], $lc['month'], $lc['day']) - 2_440_588;
        $wallSec = $epochDays * 86_400; // midnight in wall-clock seconds

        // For cross-midnight DST gaps (e.g., 1919-03-31 America/Toronto where
        // midnight doesn't exist), startOfDay should return the transition
        // epoch itself — the first valid instant of the day.
        $epochSec = self::wallSecToEpochSecStartOfDay($wallSec, $this->timeZoneId);

        return self::createFromEpochParts($epochSec, 0, $this->timeZoneId, $this->calendarId);
    }

    /**
     * Returns true if this ZonedDateTime represents the same instant, timezone, and calendar.
     *
     * @param self|string|array<array-key, mixed>|object $other ZonedDateTime, string, or array.
     * @throws \TypeError for unsupported types.
     * @psalm-api
     */
    public function equals(string|array|object $other): bool
    {
        if (!$other instanceof self) {
            $other = self::from($other);
        }
        return (
            self::compareInstants($this, $other) === 0
            && self::canonicalizeTimezoneForComparison($this->timeZoneId)
                === self::canonicalizeTimezoneForComparison($other->timeZoneId)
            && $this->calendarId === $other->calendarId
        );
    }

    /**
     * Canonicalizes a timezone ID for comparison purposes only.
     * Does NOT change the stored timeZoneId. This allows Etc/GMT to be
     * preserved in the timeZoneId property but still compare equal to UTC.
     */
    private static function canonicalizeTimezoneForComparison(string $id): string
    {
        // UTC aliases all compare equal.
        /** @var list<string> $utcAliases */
        static $utcAliases = [
            'etc/utc', 'etc/gmt', 'etc/gmt+0', 'etc/gmt-0', 'etc/gmt0',
            'etc/uct', 'etc/universal', 'etc/zulu',
            'gmt', 'gmt+0', 'gmt-0', 'gmt0', 'uct', 'universal', 'zulu', 'utc',
        ];
        $lower = strtolower($id);
        if (in_array($lower, $utcAliases, strict: true)) {
            return 'UTC';
        }
        // Fixed offset +00:00 and -00:00 are equivalent to UTC.
        if ($id === '+00:00' || $id === '-00:00') {
            return 'UTC';
        }
        // Case-fold and use ICU canonical ID for IANA alias resolution.
        // ICU's getCanonicalID is case-sensitive, so we look up using
        // the properly-cased IANA ID from PHP's timezone list.
        /** @var array<string, string>|null $lowerMap */
        static $lowerMap = null;
        if ($lowerMap === null) {
            $lowerMap = [];
            foreach (\DateTimeZone::listIdentifiers() as $ident) {
                $lowerMap[strtolower($ident)] = $ident;
            }
        }
        $properCase = $lowerMap[$lower] ?? $id;
        return \IntlTimeZone::getCanonicalID($properCase) ?: $properCase;
    }

    /**
     * Returns an ISO 8601 representation with timezone and calendar annotations.
     *
     * Options (all optional):
     *   - fractionalSecondDigits: 'auto' (default) | 0–9
     *   - offset: 'auto' (default, include offset) | 'never' (omit offset)
     *   - timeZoneName: 'auto' (default, include name) | 'never' | 'critical'
     *   - calendarName: 'auto' (default, omit for iso8601) | 'always' | 'never' | 'critical'
     *
     * @param array<array-key, mixed>|object|null $options null, array, or object (treated as empty bag).
     * @throws \TypeError              if option values have wrong types.
     * @throws InvalidArgumentException if option values are invalid strings.
     * @psalm-api
     */
    #[\Override]
    public function toString(array|object|null $options = null): string
    {
        if (is_object($options)) {
            $options = [];
        }

        $digits = -2; // -2 = 'auto'
        $offsetMode = 'auto';
        $tzNameMode = 'auto';
        $calendarName = 'auto';
        $isMinute = false;
        $roundMode = 'trunc';

        if ($options !== null) {
            if (array_key_exists('fractionalSecondDigits', $options)) {
                /** @psalm-suppress MixedAssignment */
                $fsd = $options['fractionalSecondDigits'];
                if ($fsd !== 'auto') {
                    if ($fsd === null || is_bool($fsd)) {
                        throw new InvalidArgumentException("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if (is_float($fsd)) {
                        if (is_nan($fsd) || is_infinite($fsd)) {
                            throw new InvalidArgumentException(
                                "fractionalSecondDigits must be 'auto' or a finite integer 0–9.",
                            );
                        }
                        $fsd = (int) floor($fsd);
                    } elseif (!is_int($fsd)) {
                        throw new InvalidArgumentException("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if ($fsd < 0 || $fsd > 9) {
                        throw new InvalidArgumentException(
                            "fractionalSecondDigits {$fsd} is out of range (must be 0–9).",
                        );
                    }
                    $digits = $fsd;
                }
            }

            // smallestUnit overrides fractionalSecondDigits.
            if (array_key_exists('smallestUnit', $options) && $options['smallestUnit'] !== null) {
                /** @var mixed $su */
                $su = $options['smallestUnit'];
                if (!is_string($su)) {
                    throw new \TypeError('smallestUnit must be a string.');
                }
                [$digits, $isMinute] = match ($su) {
                    'minute', 'minutes' => [-1, true],
                    'second', 'seconds' => [0, false],
                    'millisecond', 'milliseconds' => [3, false],
                    'microsecond', 'microseconds' => [6, false],
                    'nanosecond', 'nanoseconds' => [9, false],
                    default => throw new InvalidArgumentException("Invalid smallestUnit \"{$su}\"."),
                };
            }

            // roundingMode (default 'trunc').
            if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
                /** @var mixed $rm */
                $rm = $options['roundingMode'];
                if (!is_string($rm)) {
                    throw new \TypeError('roundingMode must be a string.');
                }
                $roundMode = $rm;
            }

            if (array_key_exists('offset', $options)) {
                /** @var mixed $ov */
                $ov = $options['offset'];
                if (!is_string($ov)) {
                    throw new \TypeError('offset option must be a string.');
                }
                if ($ov !== 'auto' && $ov !== 'never') {
                    throw new InvalidArgumentException("Invalid offset option \"{$ov}\"; must be 'auto' or 'never'.");
                }
                $offsetMode = $ov;
            }

            if (array_key_exists('timeZoneName', $options)) {
                /** @var mixed $tzn */
                $tzn = $options['timeZoneName'];
                if (!is_string($tzn)) {
                    throw new \TypeError('timeZoneName option must be a string.');
                }
                if ($tzn !== 'auto' && $tzn !== 'never' && $tzn !== 'critical') {
                    throw new InvalidArgumentException("Invalid timeZoneName option \"{$tzn}\".");
                }
                $tzNameMode = $tzn;
            }

            if (array_key_exists('calendarName', $options)) {
                /** @var mixed $cn */
                $cn = $options['calendarName'];
                if (!is_string($cn)) {
                    throw new \TypeError('calendarName option must be a string.');
                }
                if ($cn !== 'auto' && $cn !== 'always' && $cn !== 'never' && $cn !== 'critical') {
                    throw new InvalidArgumentException("Invalid calendarName value: \"{$cn}\".");
                }
                $calendarName = $cn;
            }
        }

        // Compute rounding increment in nanoseconds.
        if ($isMinute) {
            $increment = 60_000_000_000;
        } elseif ($digits >= 0) {
            $increment = (int) 10 ** (9 - $digits); // @phpstan-ignore cast.useless
        } else {
            $increment = 1;
        }

        // Round epoch nanoseconds using RoundNumberToIncrementAsIfPositive.
        $roundedNs = $increment === 1
            ? $this->epochNanoseconds
            : self::roundAsIfPositive($this->epochNanoseconds, $increment, $roundMode);

        // Recompute local date/time components from the rounded epoch.
        $epochSec = CalendarMath::floorDiv($roundedNs, self::NS_PER_SECOND);
        $maxSecForNsCheck = 9_223_372_035;
        if ($epochSec > $maxSecForNsCheck || $epochSec < -$maxSecForNsCheck) {
            $roundedSubNs = 0;
        } else {
            $roundedSubNs = $roundedNs - ($epochSec * self::NS_PER_SECOND); // 0–999_999_999
        }

        $offsetSec = $this->resolveOffsetSecondsAt($epochSec);
        $localSec = $epochSec + $offsetSec;
        $dt = new \DateTimeImmutable(sprintf('@%d', $localSec));

        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $day = (int) $dt->format('j');
        $hour = (int) $dt->format('G');
        $min = (int) $dt->format('i');
        $sec = (int) $dt->format('s');

        $ms = intdiv(num1: $roundedSubNs, num2: self::NS_PER_MILLISECOND);
        $us = intdiv(num1: $roundedSubNs % self::NS_PER_MILLISECOND, num2: self::NS_PER_MICROSECOND);
        $ns = $roundedSubNs % self::NS_PER_MICROSECOND;

        // Build offset string: ±HH:MM or ±HH:MM:SS when seconds are non-zero.
        $absOffsetSec = abs($offsetSec);
        $offH = intdiv(num1: $absOffsetSec, num2: 3600);
        $offM = intdiv(num1: $absOffsetSec % 3600, num2: 60);
        $offS = $absOffsetSec % 60;
        $offSign = $offsetSec >= 0 ? '+' : '-';
        $offsetStr = $offS !== 0
            ? sprintf('%s%02d:%02d:%02d', $offSign, $offH, $offM, $offS)
            : sprintf('%s%02d:%02d', $offSign, $offH, $offM);

        // Year formatting: normal 4-digit, extended ±YYYYYY for out-of-range.
        if ($year < 0) {
            $yearStr = sprintf('-%06d', abs($year));
        } elseif ($year > 9999) {
            $yearStr = sprintf('+%06d', $year);
        } else {
            $yearStr = sprintf('%04d', $year);
        }

        $datePart = sprintf('%s-%02d-%02d', $yearStr, $month, $day);

        // Sub-second nanoseconds: ms * 1e6 + us * 1e3 + ns
        $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;

        if ($isMinute) {
            $timePart = sprintf('%02d:%02d', $hour, $min);
        } elseif ($digits === -2) {
            // 'auto': strip trailing zeros.
            if ($subNs === 0) {
                $timePart = sprintf('%02d:%02d:%02d', $hour, $min, $sec);
            } else {
                $fraction = rtrim(sprintf('%09d', $subNs), characters: '0');
                $timePart = sprintf('%02d:%02d:%02d.%s', $hour, $min, $sec, $fraction);
            }
        } elseif ($digits === 0) {
            $timePart = sprintf('%02d:%02d:%02d', $hour, $min, $sec);
        } else {
            $fraction = substr(string: sprintf('%09d', $subNs), offset: 0, length: $digits);
            $timePart = sprintf('%02d:%02d:%02d.%s', $hour, $min, $sec, $fraction);
        }

        $result = sprintf('%sT%s', $datePart, $timePart);

        if ($offsetMode !== 'never') {
            $result .= $offsetStr;
        }

        if ($tzNameMode !== 'never') {
            if ($tzNameMode === 'critical') {
                $result .= sprintf('[!%s]', $this->timeZoneId);
            } else {
                $result .= sprintf('[%s]', $this->timeZoneId);
            }
        }

        if ($calendarName === 'always') {
            $result .= sprintf('[u-ca=%s]', $this->calendarId);
        } elseif ($calendarName === 'critical') {
            $result .= sprintf('[!u-ca=%s]', $this->calendarId);
        } elseif ($calendarName === 'auto' && $this->calendarId !== 'iso8601') { // @phpstan-ignore booleanAnd.alwaysFalse, notIdentical.alwaysFalse
            $result .= sprintf('[u-ca=%s]', $this->calendarId);
        }
        // 'never': omit calendar annotation entirely.

        return $result;
    }

    /**
     * Returns a locale-sensitive string for this ZonedDateTime using IntlDateFormatter.
     *
     * Supports a subset of Intl.DateTimeFormat options:
     *   - dateStyle: "full" | "long" | "medium" | "short"
     *   - timeStyle: "full" | "long" | "medium" | "short"
     *   - timeZone: IANA timezone string (defaults to this ZonedDateTime's timezone)
     *   - calendar: calendar identifier appended as u-ca locale extension
     *
     * @param string|array<array-key, mixed>|null $locales BCP 47 locale string or array of strings.
     * @param array<array-key, mixed>|object|null $options Intl.DateTimeFormat options array.
     * @psalm-api
     */
    public function toLocaleString(string|array|null $locales = null, array|object|null $options = null): string
    {
        $opts = $options !== null ? (is_array($options) ? $options : (array) $options) : [];
        /** @psalm-var array<string, mixed> $opts */

        // TC39: timeZone option is disallowed for ZonedDateTime.toLocaleString.
        if (array_key_exists('timeZone', $opts) && $opts['timeZone'] !== null) {
            throw new \TypeError('toLocaleString(): timeZone option is not allowed for ZonedDateTime.');
        }

        $locale = CalendarMath::resolveLocale($locales);
        $timeZone = $this->timeZoneId;
        $opts['_locale'] = $locale;

        // TC39: ZDT's default format includes the timezone name.
        if (!array_key_exists('timeZoneName', $opts) && !array_key_exists('dateStyle', $opts) && !array_key_exists('timeStyle', $opts)) {
            $opts['timeZoneName'] = 'short';
        }

        // Validate style + component conflicts
        CalendarMath::validateStyleConflicts($opts);

        $formatter = CalendarMath::buildIntlFormatter($locale, $timeZone, $opts, 'datetime');
        [$epochSec, ] = $this->getEpochParts();
        $result = $formatter->format($epochSec);

        return $result !== false ? $result : $this->toString();
    }

    // -------------------------------------------------------------------------
    // Arithmetic methods
    // -------------------------------------------------------------------------

    /**
     * Returns a new ZonedDateTime with the given duration added.
     *
     * Calendar units (years/months) modify local date fields and re-resolve to ZDT.
     * Time units add nanoseconds directly to the epoch.
     *
     * @param Duration|string|array<array-key, mixed>|object $duration Duration, ISO 8601 duration string, or property-bag array.
     * @param array<array-key, mixed>|object|null $options Options array; supports 'overflow' ('constrain'|'reject').
     * @psalm-api
     */
    public function add(string|array|object $duration, array|object|null $options = null): self
    {
        $dur = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addDurationZdt(1, $dur, $options);
    }

    /**
     * Returns a new ZonedDateTime with the given duration subtracted.
     *
     * @param Duration|string|array<array-key,mixed>|object $duration Duration, ISO 8601 duration string, or property-bag array.
     * @param array<array-key, mixed>|object|null $options Options array; supports 'overflow' ('constrain'|'reject').
     * @psalm-api
     */
    public function subtract(string|array|object $duration, array|object|null $options = null): self
    {
        $dur = $duration instanceof Duration ? $duration : Duration::from($duration);
        return $this->addDurationZdt(-1, $dur, $options);
    }

    /**
     * Returns the Duration from $other to this ZonedDateTime (this - other).
     *
     * Default largestUnit is 'hour' (per TC39 ZonedDateTime spec).
     *
     * @param self|string|array<array-key, mixed>|object $other   ZonedDateTime or ZDT string.
     * @param array<array-key, mixed>|object|null $options Options array with largestUnit, smallestUnit, roundingMode, roundingIncrement.
     * @psalm-api
     */
    public function since(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        if ($this->calendarId !== $o->calendarId) {
            throw new InvalidArgumentException("Cannot compute since() between different calendars: \"{$this->calendarId}\" and \"{$o->calendarId}\".");
        }
        return self::diffZdt($this, $o, 'since', $options);
    }

    /**
     * Returns the Duration from this ZonedDateTime to $other (other - this).
     *
     * Default largestUnit is 'hour' (per TC39 ZonedDateTime spec).
     *
     * @param self|string|array<array-key, mixed>|object $other   ZonedDateTime or ZDT string.
     * @param array<array-key, mixed>|object|null $options Options array with largestUnit, smallestUnit, roundingMode, roundingIncrement.
     * @psalm-api
     */
    public function until(string|array|object $other, array|object|null $options = null): Duration
    {
        $o = $other instanceof self ? $other : self::from($other);
        if ($this->calendarId !== $o->calendarId) {
            throw new InvalidArgumentException("Cannot compute until() between different calendars: \"{$this->calendarId}\" and \"{$o->calendarId}\".");
        }
        return self::diffZdt($this, $o, 'until', $options);
    }

    /**
     * Returns a new ZonedDateTime rounded to the given unit and increment.
     *
     * For 'day': rounds relative to local midnight in the timezone.
     * For sub-day units: rounds the epoch nanoseconds directly.
     *
     * @param string|array<array-key, mixed>|object $options string smallestUnit or array with keys:
     *   - smallestUnit (required): 'day'|'hour'|'minute'|'second'|'millisecond'|'microsecond'|'nanosecond'
     *   - roundingMode (default 'halfExpand')
     *   - roundingIncrement (default 1)
     * @psalm-api
     */
    public function round(string|array|object $options): self
    {
        if (is_string($options)) {
            $options = ['smallestUnit' => $options];
        } elseif (is_object($options)) {
            $options = (array) $options;
        }

        /** @psalm-suppress MixedAssignment */
        $suRaw = $options['smallestUnit'] ?? null;
        if ($suRaw === null) {
            throw new InvalidArgumentException('Temporal\\ZonedDateTime::round() requires smallestUnit.');
        }
        if (!is_string($suRaw)) {
            throw new \TypeError('smallestUnit must be a string.');
        }

        // [nsPerUnit, maxIncrement (next-unit size, or 1 for day)]
        $unitMap = [
            'day' => [86_400_000_000_000, 1],
            'days' => [86_400_000_000_000, 1],
            'hour' => [3_600_000_000_000, 24],
            'hours' => [3_600_000_000_000, 24],
            'minute' => [60_000_000_000, 60],
            'minutes' => [60_000_000_000, 60],
            'second' => [self::NS_PER_SECOND, 60],
            'seconds' => [self::NS_PER_SECOND, 60],
            'millisecond' => [self::NS_PER_MILLISECOND, 1_000],
            'milliseconds' => [self::NS_PER_MILLISECOND, 1_000],
            'microsecond' => [self::NS_PER_MICROSECOND, 1_000],
            'microseconds' => [self::NS_PER_MICROSECOND, 1_000],
            'nanosecond' => [1, 1_000],
            'nanoseconds' => [1, 1_000],
        ];
        if (!array_key_exists($suRaw, $unitMap)) {
            throw new InvalidArgumentException(
                "Invalid smallestUnit \"{$suRaw}\" for Temporal\\ZonedDateTime::round().",
            );
        }
        [$nsPerUnit, $maxDivisor] = $unitMap[$suRaw];

        $roundingMode = 'halfExpand';
        if (array_key_exists('roundingMode', $options) && $options['roundingMode'] !== null) {
            /** @psalm-suppress MixedArgument */
            $roundingMode = (string) $options['roundingMode'];
        }

        $increment = 1;
        if (array_key_exists('roundingIncrement', $options) && $options['roundingIncrement'] !== null) {
            /** @psalm-suppress MixedArgument */
            $rawIncrement = (int) $options['roundingIncrement'];
            if ($rawIncrement < 1) {
                throw new InvalidArgumentException('roundingIncrement must be a positive integer.');
            }
            $increment = $rawIncrement;
        }
        if ($maxDivisor === 1) {
            if ($increment !== 1) {
                throw new InvalidArgumentException("roundingIncrement {$increment} is invalid for unit \"{$suRaw}\".");
            }
        } elseif ($increment >= $maxDivisor || ($maxDivisor % $increment) !== 0) {
            throw new InvalidArgumentException(
                "roundingIncrement {$increment} does not evenly divide {$maxDivisor} for unit \"{$suRaw}\".",
            );
        }

        $nsIncrement = $nsPerUnit * $increment;
        $isDay = str_starts_with($suRaw, 'day');

        // ZonedDateTime rounding is always relative to local midnight (start of day).
        // Get local midnight epoch seconds and the offset from midnight in nanoseconds.
        $lc = $this->localComponents();
        $epochDays = CalendarMath::toJulianDay($lc['year'], $lc['month'], $lc['day']) - 2_440_588;
        $midnightWallSec = $epochDays * 86_400;
        $midnightEpochSec = self::wallSecToEpochSecStartOfDay($midnightWallSec, $this->timeZoneId);

        // Compute offset from midnight using true epoch parts to handle sentinels.
        if ($this->trueEpochSec !== null) {
            $thisEpochSec = $this->trueEpochSec;
            $thisSubNs = $this->trueSubNs;
        } else {
            $thisEpochSec = CalendarMath::floorDiv($this->epochNanoseconds, self::NS_PER_SECOND);
            $thisSubNs = $this->epochNanoseconds - ($thisEpochSec * self::NS_PER_SECOND);
        }
        $offsetFromMidnight = (($thisEpochSec - $midnightEpochSec) * self::NS_PER_SECOND) + $thisSubNs;

        if ($isDay) {
            // Compute actual day length for DST-aware day rounding.
            $nextDayWallSec = $midnightWallSec + 86_400;
            $nextDayEpochSec = self::wallSecToEpochSecStartOfDay($nextDayWallSec, $this->timeZoneId);
            $dayLengthNs = ($nextDayEpochSec - $midnightEpochSec) * self::NS_PER_SECOND;

            if ($dayLengthNs <= 0) {
                throw new InvalidArgumentException(
                    'Cannot round to day: day length is zero or negative (DST transition).',
                );
            }

            $roundedOffsetNs = self::roundDayNs($offsetFromMidnight, $dayLengthNs, $roundingMode);
        } elseif ($nsIncrement === 1) {
            $roundedOffsetNs = $offsetFromMidnight;
        } else {
            // Round the offset from midnight, then add back midnight.
            $roundedOffsetNs = self::roundPositiveNs($offsetFromMidnight, $nsIncrement, $roundingMode);
        }

        // Compute the rounded result as epoch seconds + sub-ns.
        $roundedEpochSec = $midnightEpochSec + intdiv(num1: $roundedOffsetNs, num2: self::NS_PER_SECOND);
        $roundedSubNs = $roundedOffsetNs % self::NS_PER_SECOND;
        if ($roundedSubNs < 0) {
            $roundedEpochSec--;
            $roundedSubNs += self::NS_PER_SECOND;
        }

        return self::fromEpochParts($roundedEpochSec, $roundedSubNs, $this->timeZoneId, $this->calendarId);
    }

    /**
     * Returns a new ZonedDateTime with the specified fields overridden.
     *
     * @param array<array-key,mixed> $fields   Property bag with fields to override.
     * @param array<array-key, mixed>|object|null       $options Options bag: ['overflow' => ..., 'disambiguation' => ...]
     * @psalm-api
     */
    public function with(array $fields, array|object|null $options = null): self
    {
        if (array_key_exists('calendar', $fields) || array_key_exists('timeZone', $fields)) {
            throw new \TypeError('ZonedDateTime::with() fields must not contain a calendar or timeZone property.');
        }

        $recognized = [
            'year',
            'month',
            'monthCode',
            'day',
            'hour',
            'minute',
            'second',
            'millisecond',
            'microsecond',
            'nanosecond',
            'offset',
            'era',
            'eraYear',
        ];
        $hasField = false;
        foreach ($recognized as $f) {
            if (array_key_exists($f, $fields)) {
                $hasField = true;
                break;
            }
        }
        if (!$hasField) {
            throw new \TypeError('ZonedDateTime::with() requires at least one recognized property.');
        }

        $overflow = self::extractOverflow($options);
        $disambiguation = self::extractDisambiguation($options);

        // Extract the 'offset' option (default is 'prefer' for with()).
        $offsetOption = 'prefer';
        if ($options !== null) {
            $optArr = is_array($options) ? $options : (array) $options;
            if (array_key_exists('offset', $optArr)) {
                /** @var mixed $offOpt */
                $offOpt = $optArr['offset'];
                if ($offOpt !== null) {
                    if (!is_string($offOpt)) {
                        throw new \TypeError('ZonedDateTime::with() offset option must be a string.');
                    }
                    if (!in_array($offOpt, ['prefer', 'use', 'ignore', 'reject'], strict: true)) {
                        throw new InvalidArgumentException(
                            "Invalid offset option \"{$offOpt}\": must be 'prefer', 'use', 'ignore', or 'reject'.",
                        );
                    }
                    $offsetOption = $offOpt;
                }
            }
        }

        // Validate the 'offset' field in the property bag.
        $hasOffsetField = array_key_exists('offset', $fields);
        if ($hasOffsetField) {
            /** @var mixed $offVal */
            $offVal = $fields['offset'];
            if (!is_string($offVal)) {
                throw new \TypeError('ZonedDateTime::with() offset field must be a string.');
            }
            if (preg_match('/^[+-]\d{2}:\d{2}(:\d{2})?$/', $offVal) !== 1) {
                throw new InvalidArgumentException("Invalid offset string \"{$offVal}\": must be ±HH:MM or ±HH:MM:SS.");
            }
        }

        $lc = $this->localComponents();
        $h = $lc['hour'];
        $min = $lc['minute'];
        $sec = $lc['second'];
        $ms = $lc['millisecond'];
        $us = $lc['microsecond'];
        $ns = $lc['nanosecond'];

        // --- Resolve time fields (shared by ISO and non-ISO paths) ---
        if (array_key_exists('hour', $fields)) {
            /** @var mixed $v */
            $v = $fields['hour'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('ZonedDateTime::with() hour must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $h = (int) $v;
        }
        if (array_key_exists('minute', $fields)) {
            /** @var mixed $v */
            $v = $fields['minute'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('ZonedDateTime::with() minute must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $min = (int) $v;
        }
        if (array_key_exists('second', $fields)) {
            /** @var mixed $v */
            $v = $fields['second'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('ZonedDateTime::with() second must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $sec = (int) $v;
        }
        if (array_key_exists('millisecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['millisecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('ZonedDateTime::with() millisecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ms = (int) $v;
        }
        if (array_key_exists('microsecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['microsecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('ZonedDateTime::with() microsecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $us = (int) $v;
        }
        if (array_key_exists('nanosecond', $fields)) {
            /** @var mixed $v */
            $v = $fields['nanosecond'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $v)) {
                throw new InvalidArgumentException('ZonedDateTime::with() nanosecond must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $ns = (int) $v;
        }

        // --- Constrain/reject time fields ---
        if ($overflow === 'constrain') {
            $h = max(0, min(23, $h));
            $min = max(0, min(59, $min));
            $sec = max(0, min(59, $sec));
            $ms = max(0, min(999, $ms));
            $us = max(0, min(999, $us));
            $ns = max(0, min(999, $ns));
        } else {
            if ($h < 0 || $h > 23) {
                throw new InvalidArgumentException("Invalid hour {$h}: must be 0–23.");
            }
            if ($min < 0 || $min > 59) {
                throw new InvalidArgumentException("Invalid minute {$min}: must be 0–59.");
            }
            if ($sec < 0 || $sec > 59) {
                throw new InvalidArgumentException("Invalid second {$sec}: must be 0–59.");
            }
            if ($ms < 0 || $ms > 999) {
                throw new InvalidArgumentException("Invalid millisecond {$ms}: must be 0–999.");
            }
            if ($us < 0 || $us > 999) {
                throw new InvalidArgumentException("Invalid microsecond {$us}: must be 0–999.");
            }
            if ($ns < 0 || $ns > 999) {
                throw new InvalidArgumentException("Invalid nanosecond {$ns}: must be 0–999.");
            }
        }

        $calendar = $this->calendarId !== 'iso8601'
            ? CalendarFactory::get($this->calendarId)
            : null;

        // --- Non-ISO calendar date resolution ---
        if ($calendar !== null) {
            $hasYear = array_key_exists('year', $fields);
            $hasEra = array_key_exists('era', $fields);
            $hasEraYear = array_key_exists('eraYear', $fields);
            $hasMonth = array_key_exists('month', $fields);
            $hasMonthCode = array_key_exists('monthCode', $fields);

            // Chinese/Dangi have no eras — providing era or eraYear is always a TypeError.
            if (($hasEra || $hasEraYear) && in_array($calendar->id(), ['chinese', 'dangi'], true)) {
                throw new \TypeError('eraYear and era are invalid for this calendar.');
            }

            // TC39: era without eraYear (or vice versa) is TypeError when year is not also provided.
            if ($hasEra && !$hasEraYear && !$hasYear) {
                throw new \TypeError('era provided without eraYear in with() fields.');
            }
            if ($hasEraYear && !$hasEra && !$hasYear) {
                throw new \TypeError('eraYear provided without era in with() fields.');
            }

            // Resolve year: era+eraYear takes precedence over the current year if both provided.
            $year = $this->year;
            if ($hasYear) {
                /** @var mixed $yr */
                $yr = $fields['year'];
                /** @phpstan-ignore cast.double */
                if (!is_finite((float) $yr)) {
                    throw new InvalidArgumentException('ZonedDateTime::with() year must be finite.');
                }
                /** @phpstan-ignore cast.int */
                $year = (int) $yr;
            } elseif ($hasEra && $hasEraYear) {
                /** @var mixed $eraRaw */
                $eraRaw = $fields['era'];
                /** @var mixed $eraYearRaw */
                $eraYearRaw = $fields['eraYear'];
                if (is_string($eraRaw) && $eraYearRaw !== null) {
                    /** @phpstan-ignore cast.double */
                    if (!is_finite((float) $eraYearRaw)) {
                        throw new InvalidArgumentException('eraYear must be finite.');
                    }
                    /** @phpstan-ignore cast.int */
                    $resolved = $calendar->resolveEra($eraRaw, (int) $eraYearRaw);
                    if ($resolved !== null) {
                        $year = $resolved;
                    }
                }
            }

            // Resolve monthCode/month with mutual exclusion.
            // When neither is provided, default to current monthCode (not ordinal month).
            $monthCode = null;
            $month = null;
            $useMonthCode = false;

            if ($hasMonthCode) {
                /** @var mixed $mc */
                $mc = $fields['monthCode'];
                /** @phpstan-ignore cast.string */
                $monthCode = (string) $mc;
                $useMonthCode = true;
            }
            if ($hasMonth) {
                /** @var mixed $m */
                $m = $fields['month'];
                /** @phpstan-ignore cast.double */
                if (!is_finite((float) $m)) {
                    throw new InvalidArgumentException('ZonedDateTime::with() month must be finite.');
                }
                /** @phpstan-ignore cast.int */
                $month = (int) $m;
                // Validate month/monthCode conflict.
                if ($hasMonthCode && $monthCode !== null) {
                    $monthFromCode = $calendar->monthCodeToMonth($monthCode, $year);
                    if ($month !== $monthFromCode) {
                        throw new InvalidArgumentException('Conflicting month and monthCode fields.');
                    }
                }
                $useMonthCode = false; // explicit month takes precedence
            }
            if (!$hasMonth && !$hasMonthCode) {
                // Default: preserve current monthCode.
                $monthCode = $this->monthCode;
                $useMonthCode = true;
            }

            $day = $this->day;
            if (array_key_exists('day', $fields)) {
                /** @var mixed $dy */
                $dy = $fields['day'];
                /** @phpstan-ignore cast.double */
                if (!is_finite((float) $dy)) {
                    throw new InvalidArgumentException('ZonedDateTime::with() day must be finite.');
                }
                /** @phpstan-ignore cast.int */
                $day = (int) $dy;
            }

            if ($day < 1) {
                throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
            }

            if ($useMonthCode && $monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, $day, $overflow);
            } else {
                /** @var int $month */
                if ($month < 1) {
                    throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
                }
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, $day, $overflow);
            }

            return self::localToZdt(
                $isoY,
                $isoM,
                $isoD,
                $h,
                $min,
                $sec,
                $ms,
                $us,
                $ns,
                $this->timeZoneId,
                $this->calendarId,
                $disambiguation,
            );
        }

        // --- ISO calendar date resolution ---
        $year = $lc['year'];
        $month = $lc['month'];
        $day = $lc['day'];

        if (array_key_exists('year', $fields)) {
            /** @var mixed $yr */
            $yr = $fields['year'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $yr)) {
                throw new InvalidArgumentException('ZonedDateTime::with() year must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $year = (int) $yr;
        }

        $hasMonth = array_key_exists('month', $fields);
        $hasMonthCode = array_key_exists('monthCode', $fields);
        if ($hasMonthCode) {
            /** @var mixed $mc */
            $mc = $fields['monthCode'];
            /** @phpstan-ignore cast.string */
            $mcStr = (string) $mc;
            $month = CalendarMath::monthCodeToMonth($mcStr);
        }
        if ($hasMonth) {
            /** @var mixed $m */
            $m = $fields['month'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $m)) {
                throw new InvalidArgumentException('ZonedDateTime::with() month must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $newMonth = (int) $m;
            if ($hasMonthCode && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }

        if (array_key_exists('day', $fields)) {
            /** @var mixed $dy */
            $dy = $fields['day'];
            /** @phpstan-ignore cast.double */
            if (!is_finite((float) $dy)) {
                throw new InvalidArgumentException('ZonedDateTime::with() day must be finite.');
            }
            /** @phpstan-ignore cast.int */
            $day = (int) $dy;
        }

        if ($month < 1) {
            throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }

        if ($overflow === 'constrain') {
            /**
             * @var int<1, 12>
             * @psalm-suppress UnnecessaryVarAnnotation — Mago can't narrow min()
             */
            $month = min(12, $month);
            $maxDay = CalendarMath::calcDaysInMonth($year, $month);
            $day = min($maxDay, $day);
        } else {
            // overflow === 'reject'
            if ($month > 12) {
                throw new InvalidArgumentException("Invalid month {$month}: must be 1–12.");
            }
            $maxDay = CalendarMath::calcDaysInMonth($year, $month);
            if ($day > $maxDay) {
                throw new InvalidArgumentException("Day {$day} is out of range for {$year}-{$month} (max {$maxDay}).");
            }
        }

        // If no offset field was provided but offset option requires preserving,
        // use the ZDT's current offset for wall-to-epoch conversion. Per TC39,
        // 'use'/'prefer'/'reject' all preserve the existing offset when possible.
        if (!$hasOffsetField && $offsetOption !== 'ignore') {
            [$curEpochSec, ] = $this->getEpochParts();
            $currentOffsetSec = self::staticResolveOffset($curEpochSec, $this->timeZoneId);

            $epochDays = CalendarMath::toJulianDay($year, $month, $day) - 2_440_588;
            $wallSec = ($epochDays * 86_400) + ($h * 3600) + ($min * 60) + $sec;

            if ($offsetOption === 'use') {
                $epochSec = $wallSec - $currentOffsetSec;
                $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;
                return self::fromEpochParts($epochSec, $subNs, $this->timeZoneId, $this->calendarId);
            }
            // 'prefer'/'reject': check if current offset is valid at new wall time
            $epochFromOffset = $wallSec - $currentOffsetSec;
            $actualOffset = self::staticResolveOffset($epochFromOffset, $this->timeZoneId);
            if ($actualOffset === $currentOffsetSec) {
                $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;
                return self::fromEpochParts($epochFromOffset, $subNs, $this->timeZoneId, $this->calendarId);
            }
            // Current offset not valid at new wall time — fall through to disambiguation
        }

        // Handle offset field with offset option (like from()).
        if ($hasOffsetField) {
            /** @var string $offVal */
            $offVal = $fields['offset'];
            $offSign = $offVal[0] === '+' ? 1 : -1;
            $offParts = explode(separator: ':', string: substr(string: $offVal, offset: 1));
            $givenOffsetSec = $offSign * (((int) $offParts[0] * 3600) + ((int) $offParts[1] * 60)
                + (isset($offParts[2]) ? (int) $offParts[2] : 0));

            if ($offsetOption === 'ignore') {
                // Fall through to normal localToZdt.
            } else {
                $epochDays = CalendarMath::toJulianDay($year, $month, $day) - 2_440_588;
                $wallSec = ($epochDays * 86_400) + ($h * 3600) + ($min * 60) + $sec;

                if ($offsetOption === 'use') {
                    // Use the offset directly, regardless of timezone rules.
                    $epochSec = $wallSec - $givenOffsetSec;
                    $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;
                    return self::fromEpochParts($epochSec, $subNs, $this->timeZoneId, $this->calendarId);
                }

                // 'prefer' or 'reject': try using the given offset.
                $epochFromOffset = $wallSec - $givenOffsetSec;
                $actualOffset = self::staticResolveOffset($epochFromOffset, $this->timeZoneId);
                if ($actualOffset === $givenOffsetSec) {
                    $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;
                    return self::fromEpochParts($epochFromOffset, $subNs, $this->timeZoneId, $this->calendarId);
                }
                if ($offsetOption === 'reject') {
                    throw new InvalidArgumentException(
                        "The offset {$offVal} does not match the timezone offset at the given instant.",
                    );
                }
                // 'prefer': fall through to normal localToZdt.
            }
        }

        return self::localToZdt(
            $year,
            $month,
            $day,
            $h,
            $min,
            $sec,
            $ms,
            $us,
            $ns,
            $this->timeZoneId,
            $this->calendarId,
            $disambiguation,
        );
    }

    /**
     * Finds the next or previous DST transition relative to this instant.
     *
     * Returns null for fixed-offset timezones (UTC, ±HH:MM).
     *
     * @param string|array<array-key, mixed>|object $direction 'next' or 'previous', or an array with 'direction' key.
     * @psalm-api
     */
    public function getTimeZoneTransition(string|array|object $direction): ?self
    {
        if (func_num_args() === 0) {
            throw new \TypeError('ZonedDateTime::getTimeZoneTransition() requires a direction argument.');
        }

        $dir = null;
        if (is_string($direction)) {
            $dir = $direction;
        } elseif (is_array($direction)) {
            if (array_key_exists('direction', $direction)) {
                /** @var mixed $dv */
                $dv = $direction['direction'];
                if (is_string($dv)) {
                    $dir = $dv;
                }
            }
            if ($dir === null) {
                throw new InvalidArgumentException(
                    "ZonedDateTime::getTimeZoneTransition() requires a valid 'direction' option ('next' or 'previous').",
                );
            }
        } else {
            throw new InvalidArgumentException(
                "ZonedDateTime::getTimeZoneTransition() requires a valid 'direction' option ('next' or 'previous').",
            );
        }

        if ($dir !== 'next' && $dir !== 'previous') {
            throw new InvalidArgumentException("Invalid direction \"{$dir}\": must be 'next' or 'previous'.");
        }

        if ($this->timeZoneId === 'UTC') {
            return null;
        }
        if (preg_match('/^[+\-]\d{2}:\d{2}$/', $this->timeZoneId) === 1) {
            return null;
        }

        $epochSec = CalendarMath::floorDiv($this->epochNanoseconds, self::NS_PER_SECOND);
        /** @psalm-suppress ArgumentTypeCoercion — timeZoneId is validated non-empty in constructor */
        $tz = new \DateTimeZone($this->timeZoneId);

        if ($dir === 'next') {
            $transitions = $tz->getTransitions($epochSec, $epochSec + (200 * 365 * 86_400));
            if ($transitions === [] || $transitions === false || count($transitions) < 2) { // @phpstan-ignore identical.alwaysFalse
                return null;
            }
            // Skip index 0 (initial state at range start). Find first entry
            // with a DIFFERENT UTC offset (TC39 defines transition as offset change).
            $prevOffset = $transitions[0]['offset'];
            for ($i = 1; $i < count($transitions); $i++) {
                if ($transitions[$i]['offset'] !== $prevOffset) {
                    $ts = (int) $transitions[$i]['ts'];
                    return new self($ts * self::NS_PER_SECOND, $this->timeZoneId, $this->calendarId);
                }
                $prevOffset = $transitions[$i]['offset'];
            }
            return null;
        }

        // 'previous': find the most recent transition BEFORE the current epoch.
        $transitions = $tz->getTransitions($epochSec - (200 * 365 * 86_400), $epochSec);
        if ($transitions === [] || $transitions === false || count($transitions) < 2) { // @phpstan-ignore identical.alwaysFalse
            return null;
        }
        // Walk backwards from the end. Find entries where offset differs from
        // the following entry (= an actual UTC offset transition).
        // Skip index 0 (initial state).
        /** @var ?int $candidateTs */
        $candidateTs = null;
        for ($i = count($transitions) - 1; $i >= 1; $i--) {
            $ts = (int) $transitions[$i]['ts'];
            if ($ts <= $epochSec && $transitions[$i]['offset'] !== $transitions[$i - 1]['offset']) {
                $candidateTs = $ts;
                break;
            }
        }
        if ($candidateTs === null) {
            return null;
        }
        $transNs = $candidateTs * self::NS_PER_SECOND;
        return new self($transNs, $this->timeZoneId, $this->calendarId);
    }

    /**
     * Always throws TypeError — ZonedDateTime must not be used in numeric context.
     *
     * @throws \TypeError always.
     * @psalm-return never
     * @psalm-api
     */
    public function valueOf(): never
    {
        throw new \TypeError('Use comparison methods instead of relying on ZonedDateTime object coercion.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Extracts and validates a calendar ID from a string.
     *
     * Accepts:
     *   - 'iso8601' (case-insensitive)
     *   - ISO date/datetime/year-month/month-day strings (no annotation → iso8601,
     *     [u-ca=iso8601] → iso8601, other [u-ca=X] → throw)
     *
     * Rejects:
     *   - Empty string
     *   - Minus-zero extended year strings
     *   - Unknown calendar IDs
     *
     * @throws InvalidArgumentException for invalid/unsupported calendars.
     * @internal Used by PlainDate/PlainDateTime for calendar validation.
     * @psalm-suppress UnusedReturnValue — callers invoke this only for validation (side-effects/throws)
     * @psalm-api
     */
    public static function extractCalendarFromString(string $s): string
    {
        if ($s === '') {
            throw new InvalidArgumentException('Calendar ID must not be empty.');
        }
        // Reject minus-zero extended year.
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $s) === 1) {
            throw new InvalidArgumentException("Invalid calendar \"{$s}\": minus-zero year.");
        }
        // Check for [u-ca=X] annotation.
        if (preg_match('/\[u-ca=([^\]]+)\]/', $s, $m) === 1) {
            return CalendarFactory::canonicalize($m[1]);
        }
        // ISO date/datetime strings → iso8601 (check BEFORE time-only, to avoid ambiguity).
        // Match: date patterns (YYYY-MM, MM-DD, ±YYYYYY-) or datetime T-separator after digits.
        if (preg_match('/^\d{2}-\d{2}|^\d{4}-\d{2}|^[+-]\d{6}-/', $s) === 1 || preg_match('/\d[Tt]\d/', $s) === 1) {
            return 'iso8601';
        }
        // Time-only strings (no calendar annotation) → iso8601.
        // These are checked AFTER date strings to avoid false positives on year-like digits.
        if (preg_match('/^[Tt]\d/', $s) === 1) {
            return 'iso8601';
        }
        // Extended time: starts with 2 digits followed by colon (HH:MM or HH:MM:SS).
        if (preg_match('/^\d{2}:/', $s) === 1) {
            return 'iso8601';
        }
        // Bare hour: exactly 2 digits (HH alone, no suffix).
        if (preg_match('/^\d{2}$/', $s) === 1) {
            return 'iso8601';
        }
        // Compact time HHMMSS or HHMM: 4–6 digits NOT followed by '-'
        // (4 or 6 digits followed by end-of-string, fraction, or +/- offset).
        if (preg_match('/^\d{4,6}(?:[.,]|\+|$)/', $s) === 1 || preg_match('/^\d{4,6}-(?!\d{2}-)/', $s) === 1) {
            return 'iso8601';
        }
        // Plain calendar ID: validate through CalendarFactory.
        return CalendarFactory::canonicalize($s);
    }

    /**
     * @internal Used by PlainDate/PlainDateTime for timezone validation.
     * @psalm-api
     */
    public static function normalizeTimezoneId(string $id, bool $rejectDatetimeStrings = false): string
    {
        if ($id === '') {
            throw new InvalidArgumentException('ZonedDateTime timeZoneId must not be empty.');
        }

        // 'UTC' (case-insensitive).
        if (strtoupper($id) === 'UTC') {
            return 'UTC';
        }

        // Reject minus-zero extended year.
        if (preg_match('/^-0{6}(?:[^0-9]|$)/', $id) === 1) {
            throw new InvalidArgumentException("Invalid timeZoneId \"{$id}\": minus-zero year.");
        }

        // Datetime strings (have a T-separator after a date part).
        $isDatetime = preg_match('/\d{4,}-\d{2}-\d{2}[Tt]|\d{8}[Tt]/', $id) === 1;

        if ($isDatetime) {
            if ($rejectDatetimeStrings) {
                throw new InvalidArgumentException(
                    "Invalid timeZoneId \"{$id}\": ISO date-time string is not a valid timezone identifier for ZonedDateTime constructor.",
                );
            }
            // Bracket annotation takes precedence.
            if (preg_match('/\[(!?[^\]]+)\]/', $id, $bm) === 1) {
                $bracket = $bm[1];
                if (preg_match('/^[+\-]\d{2}:\d{2}:\d{2}/', $bracket) === 1) {
                    throw new InvalidArgumentException(
                        "Invalid timeZoneId \"{$id}\": sub-minute offset in bracket annotation.",
                    );
                }
                if (strtoupper($bracket) === 'UTC') {
                    return 'UTC';
                }
                if (preg_match('/^[+\-]\d{2}:\d{2}$/', $bracket) === 1) {
                    return $bracket;
                }
                // IANA name in bracket.
                try {
                    /** @psalm-suppress ArgumentTypeCoercion — $bracket is non-empty (matched by regex) */
                    new \DateTimeZone($bracket);
                    return $bracket;
                } catch (\Exception) {
                    throw new InvalidArgumentException(
                        "Invalid timeZoneId \"{$id}\": unsupported bracket timezone \"{$bracket}\".",
                    );
                }
            }
            // No bracket: use inline offset.
            if (preg_match('/[+\-]\d{2}:\d{2}:\d{2}/i', $id) === 1) {
                throw new InvalidArgumentException(
                    "Invalid timeZoneId \"{$id}\": inline offset contains a seconds component.",
                );
            }
            if (preg_match('/[Zz](?:\[|$)/', $id) === 1) {
                return 'UTC';
            }
            if (preg_match('/([+\-]\d{2}:\d{2})(?:\[|$)/', $id, $om) === 1) {
                return $om[1];
            }
            throw new InvalidArgumentException(
                "Invalid timeZoneId \"{$id}\": bare datetime without Z, offset, or bracket.",
            );
        }

        // Pure UTC-offset strings.
        // ±HH:MM
        if (preg_match('/^([+\-]\d{2}):(\d{2})$/', $id) === 1) {
            return $id;
        }
        // ±HHMM → ±HH:MM
        if (preg_match('/^([+\-])(\d{2})(\d{2})$/', $id, $m) === 1) {
            return sprintf('%s%s:%s', $m[1], $m[2], $m[3]);
        }
        // ±HH → ±HH:00
        if (preg_match('/^([+\-])(\d{2})$/', $id, $m) === 1) {
            return sprintf('%s%s:00', $m[1], $m[2]);
        }
        // Sub-minute offsets → reject.
        if (preg_match('/^[+\-]\d{2}:\d{2}[:.].*/i', $id) === 1) {
            throw new InvalidArgumentException(
                "Invalid timeZoneId \"{$id}\": sub-minute offset is not a valid timezone identifier.",
            );
        }

        // IANA timezone name: validate via PHP DateTimeZone (case-insensitive).
        try {
            new \DateTimeZone($id);
        } catch (\Exception) {
            throw new InvalidArgumentException("Invalid timeZoneId \"{$id}\": not a recognized timezone identifier.");
        }

        // Case-normalize the timezone ID using the canonical timezone list.
        /** @var array<string, string>|null $lowerToCanonical */
        static $lowerToCanonical = null;
        if ($lowerToCanonical === null) {
            $lowerToCanonical = [];
            foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC) as $ident) {
                $lowerToCanonical[strtolower($ident)] = $ident;
            }
            // PHP doesn't include Etc/UTC in listIdentifiers but accepts it
            $lowerToCanonical['etc/utc'] = 'Etc/UTC';
        }
        // Must be in the IANA timezone list — reject abbreviations like "AST", "EST".
        $lower = strtolower($id);
        if (!array_key_exists($lower, $lowerToCanonical)) {
            throw new InvalidArgumentException("Invalid timeZoneId \"{$id}\": not a recognized IANA timezone identifier.");
        }
        return $lowerToCanonical[$lower];
    }

    /**
     * Returns the true UTC epoch seconds and sub-second nanoseconds,
     * handling sentinel epochNanoseconds values transparently.
     *
     * @return array{int, int} [epochSec, subNs] where subNs is 0–999_999_999
     */
    private function getEpochParts(): array
    {
        if ($this->trueEpochSec !== null) {
            return [$this->trueEpochSec, $this->trueSubNs];
        }
        $epochSec = CalendarMath::floorDiv($this->epochNanoseconds, self::NS_PER_SECOND);
        $subNs = $this->epochNanoseconds - ($epochSec * self::NS_PER_SECOND);
        return [$epochSec, $subNs];
    }

    /**
     * Compares two ZonedDateTimes by their true epoch instant, handling sentinels.
     *
     * @return int -1, 0, or 1
     */
    private static function compareInstants(self $a, self $b): int
    {
        [$aSec, $aSubNs] = $a->getEpochParts();
        [$bSec, $bSubNs] = $b->getEpochParts();
        return ($aSec <=> $bSec) ?: ($aSubNs <=> $bSubNs);
    }

    /**
     * Computes the signed nanosecond difference ($b - $a) using true epoch parts.
     *
     * When both values fit in int64, uses plain arithmetic. Falls back to
     * seconds + sub-ns decomposition to avoid int overflow for proleptic dates.
     *
     * @return int Nanosecond difference (may still overflow for spans > ~292 years,
     *             but calendar-largest paths only use this for sign detection).
     */
    private static function diffEpochNs(self $a, self $b): int
    {
        [$aSec, $aSubNs] = $a->getEpochParts();
        [$bSec, $bSubNs] = $b->getEpochParts();
        $diffSec = $bSec - $aSec;
        $diffSubNs = $bSubNs - $aSubNs;
        // Safe multiplication check: |diffSec| * 1e9 fits int64 when |diffSec| < ~9.2e9
        $maxSafeSecDiff = 9_000_000_000;
        if ($diffSec > $maxSafeSecDiff || $diffSec < -$maxSafeSecDiff) {
            // Return a large sentinel value preserving sign; callers that need
            // the calendar path only use this for sign, not magnitude.
            return $diffSec > 0 ? PHP_INT_MAX : PHP_INT_MIN;
        }
        return ($diffSec * self::NS_PER_SECOND) + $diffSubNs;
    }

    /**
     * Computes (and caches) all local date/time components for this instant in the stored timezone.
     *
     * @return array{year:int, month:int<1,12>, day:int<1,31>, hour:int<0,23>, minute:int<0,59>, second:int<0,59>, millisecond:int<0,999>, microsecond:int<0,999>, nanosecond:int<0,999>, offsetSec:int, offset:string}
     * @psalm-suppress UnusedMethod — called from PHP 8.4 property hooks that Psalm does not track
     */
    private function localComponents(): array
    {
        if ($this->localCache !== null) {
            return $this->localCache;
        }

        // Use stored true epoch parts when available (sentinel values).
        if ($this->trueEpochSec !== null) {
            $epochSec = $this->trueEpochSec;
            $subNs = $this->trueSubNs;
        } else {
            $epochNs = $this->epochNanoseconds;
            $epochSec = CalendarMath::floorDiv($epochNs, self::NS_PER_SECOND);
            $subNs = $epochNs - ($epochSec * self::NS_PER_SECOND); // always 0–999_999_999
        }

        $offsetSec = $this->resolveOffsetSecondsAt($epochSec);
        $localSec = $epochSec + $offsetSec;

        // Create a UTC DateTimeImmutable at local seconds to extract Y/m/d H:i:s.
        $dt = new \DateTimeImmutable(sprintf('@%d', $localSec));

        $year = (int) $dt->format('Y');
        /** @var int<1, 12> format('n') always returns 1–12 */
        $month = (int) $dt->format('n');
        /** @var int<1, 31> format('j') always returns 1–31 */
        $day = (int) $dt->format('j');
        /** @var int<0, 23> format('G') always returns 0–23 */
        $hour = (int) $dt->format('G');
        /** @var int<0, 59> format('i') always returns 00–59 */
        $minute = (int) $dt->format('i');
        /** @var int<0, 59> format('s') always returns 00–59 */
        $second = (int) $dt->format('s');

        /** @var int<0, 999> $ms — $subNs < 10^9, dividing by 10^6 gives 0–999 */
        $ms = intdiv(num1: $subNs, num2: self::NS_PER_MILLISECOND);
        /** @var int<0, 999> $us — remainder mod 10^6 / 10^3 gives 0–999 */
        $us = intdiv(num1: $subNs % self::NS_PER_MILLISECOND, num2: self::NS_PER_MICROSECOND);
        /** @var int<0, 999> $ns — remainder mod 10^3 gives 0–999 */
        $ns = $subNs % self::NS_PER_MICROSECOND;

        // Build offset string: ±HH:MM or ±HH:MM:SS when seconds are non-zero.
        $absOffsetSec = abs($offsetSec);
        $offH = intdiv(num1: $absOffsetSec, num2: 3600);
        $offM = intdiv(num1: $absOffsetSec % 3600, num2: 60);
        $offS = $absOffsetSec % 60;
        $offSign = $offsetSec >= 0 ? '+' : '-';
        $offsetStr = $offS !== 0
            ? sprintf('%s%02d:%02d:%02d', $offSign, $offH, $offM, $offS)
            : sprintf('%s%02d:%02d', $offSign, $offH, $offM);

        $this->localCache = [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second,
            'millisecond' => $ms,
            'microsecond' => $us,
            'nanosecond' => $ns,
            'offsetSec' => $offsetSec,
            'offset' => $offsetStr,
        ];

        return $this->localCache;
    }

    /**
     * Returns the UTC offset in seconds for this timezone at a given epoch second.
     *
     * - 'UTC' → 0.
     * - '±HH:MM' → ±(H*3600 + M*60).
     * - IANA name → use PHP DateTimeZone::getOffset().
     */
    private function resolveOffsetSecondsAt(int $epochSec): int
    {
        if ($this->timeZoneId === 'UTC') {
            return 0;
        }
        // Fixed offset ±HH:MM.
        if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $this->timeZoneId, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            return $sign * (((int) $m[2] * 3600) + ((int) $m[3] * 60));
        }
        // IANA timezone: use PHP to find the offset at the given instant.
        /** @psalm-suppress ArgumentTypeCoercion — timeZoneId is validated to be non-empty in constructor */
        $tz = new \DateTimeZone($this->timeZoneId);
        return $tz->getOffset(new \DateTimeImmutable(sprintf('@%d', $epochSec)));
    }

    /**
     * Parses a ZonedDateTime ISO string (with required bracket timezone annotation).
     *
     * @param array<array-key, mixed>|object|null $options Options from from() (may contain 'offset' key).
     * @throws InvalidArgumentException if the string is invalid.
     */
    private static function parseZdtString(string $text, array|object|null $options = null): self
    {
        // Resolve the 'offset' option (default: 'reject').
        $offsetOption = 'reject';
        if (is_array($options) && array_key_exists('offset', $options)) {
            /** @var mixed $ov */
            $ov = $options['offset'];
            if (
                is_string($ov) && in_array(needle: $ov, haystack: ['use', 'ignore', 'prefer', 'reject'], strict: true)
            ) {
                $offsetOption = $ov;
            }
        }

        // Resolve the 'disambiguation' option (default: 'compatible').
        $disambiguation = 'compatible';
        if (is_array($options) && array_key_exists('disambiguation', $options)) {
            /** @var mixed $dv */
            $dv = $options['disambiguation'];
            if (is_string($dv) && in_array($dv, ['compatible', 'earlier', 'later', 'reject'], true)) {
                $disambiguation = $dv;
            }
        }

        // Reject more than 9 fractional-second digits.
        if (preg_match('/[.,]\d{10,}/', $text) === 1) {
            throw new InvalidArgumentException(
                "Invalid ZonedDateTime string \"{$text}\": fractional seconds may have at most 9 digits.",
            );
        }

        /*
         * Pattern groups:
         *   1 — year (±YYYYYY or YYYY)
         *   2 — date rest (-MM-DD or MMDD); must not mix extended and compact formats
         *   3 — hour
         *   4 — minute (only present if consistent format: extended has :, compact has no :)
         *   5 — second (optional)
         *   6 — time fraction (optional)
         *   7 — inline offset (optional: Z, ±HH:MM, ±HHMM, etc.)
         *   8 — bracket annotation section (required: one or more [...])
         *
         * To reject mixed date formats (e.g. 202501-01 or 2025-0101) and mixed time
         * formats (e.g. HH:MMSS or HHMMSS:), we use strict alternation:
         *   - Extended date: -MM-DD  (year then -MM-DD)
         *   - Compact date: MMDD     (year then 4 digits)
         *   - Extended time: HH:MM[:SS]
         *   - Compact time: HHMM[SS]  or just HH
         */
        // Extended date + extended time
        $patternExtDateExtTime = '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2})[T ](\d{2})(?::(\d{2})(?::(\d{2}))?)?([.,]\d+)?(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?((?:\[[^\]]*\])+)$/i';
        // Extended date + compact time
        $patternExtDateCptTime = '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2})[T ](\d{2})(\d{2})(\d{2})?([.,]\d+)?(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?((?:\[[^\]]*\])+)$/i';
        // Compact date + extended time
        $patternCptDateExtTime = '/^([+-]\d{6}|\d{4})(\d{4})[T ](\d{2})(?::(\d{2})(?::(\d{2}))?)?([.,]\d+)?(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?((?:\[[^\]]*\])+)$/i';
        // Compact date + compact time
        $patternCptDateCptTime = '/^([+-]\d{6}|\d{4})(\d{4})[T ](\d{2})(\d{2})(\d{2})?([.,]\d+)?(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?((?:\[[^\]]*\])+)$/i';

        // Date-only pattern: YYYY-MM-DD[tzAnnotation] (no time part; defaults to midnight).
        $dateOnlyPattern = '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})((?:\[[^\]]*\])+)$/i';

        /** @var list<string> $m */
        $m = [];
        $matched = false;
        $isDateOnly = false;
        foreach ([
            $patternExtDateExtTime,
            $patternExtDateCptTime,
            $patternCptDateExtTime,
            $patternCptDateCptTime,
        ] as $pat) {
            /** @var list<string> $tmp */
            $tmp = [];
            if (preg_match($pat, $text, $tmp) === 1) {
                $m = $tmp;
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            /** @var list<string> $dm */
            $dm = [];
            if (preg_match($dateOnlyPattern, $text, $dm) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid ZonedDateTime string \"{$text}\": expected ISO 8601 with bracket timezone annotation.",
                );
            }
            // Normalize to the same $m layout with empty time fields (defaults to midnight).
            $m = [$dm[0], $dm[1], $dm[2], '', '', '', '', '', $dm[3]];
            $isDateOnly = true;
        }

        [, $yearRaw, $dateRest, $hourStr, $minStr, $secStr, $fractionRaw, $offsetRaw, $annotationSection] = $m;

        // Normalize compact date rest.
        if (!str_starts_with($dateRest, '-')) {
            $dateRest = sprintf(
                '-%s-%s',
                substr(string: $dateRest, offset: 0, length: 2),
                substr(string: $dateRest, offset: 2, length: 2),
            );
        }

        $yearNum = (int) $yearRaw;
        // Reject minus-zero year.
        if ($yearNum === 0 && str_starts_with($yearRaw, '-')) {
            throw new InvalidArgumentException(
                "Invalid ZonedDateTime string \"{$text}\": year -000000 (negative zero) is not valid.",
            );
        }

        $monthNum = (int) substr(string: $dateRest, offset: 1, length: 2);
        $dayNum = (int) substr(string: $dateRest, offset: 4, length: 2);
        $hourNum = (int) $hourStr;
        $minNum = (int) $minStr;
        $secNum = $secStr !== '' ? (int) $secStr : 0;

        if ($monthNum < 1 || $monthNum > 12) {
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$text}\": month out of range.");
        }
        $maxDay = CalendarMath::calcDaysInMonth($yearNum, $monthNum);
        if ($dayNum < 1 || $dayNum > $maxDay) {
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$text}\": day out of range.");
        }
        if ($hourNum > 23) {
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$text}\": hour out of range.");
        }
        if ($minNum > 59) {
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$text}\": minute out of range.");
        }

        // Leap second: map :60 → last nanosecond of :59.
        $sec60 = $secNum === 60;
        $normalSec = $sec60 ? 59 : $secNum;
        if (!$sec60 && $secNum > 59) {
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$text}\": second out of range.");
        }

        // Extract the timezone and calendar from bracket annotations.
        [$tzId, $calendarId] = self::extractTzFromAnnotations($annotationSection, $text);

        // Parse inline offset if present.
        $hasInlineOffset = $offsetRaw !== '';
        $inlineOffsetSec = 0;
        // Whether the inline offset string included a seconds component (e.g. +05:30:00 vs +05:30).
        $inlineOffsetHasSeconds = false;
        if ($hasInlineOffset) {
            [$inlineSign, $inlineAbsSec] = self::parseSimpleOffset($offsetRaw);
            $inlineOffsetSec = $inlineSign * $inlineAbsSec;
            // Detect seconds: extended ±HH:MM:SS or compact ±HHMMSS (7+ chars after sign).
            $inlineOffsetHasSeconds = preg_match('/^[+\-]\d{2}:\d{2}:\d{2}/', $offsetRaw) === 1
                || preg_match('/^[+\-]\d{6}/', $offsetRaw) === 1;
        }

        // Build wall-clock DateTimeImmutable (treat as UTC to get Unix seconds).
        try {
            $wallDt = new \DateTimeImmutable(sprintf(
                '%s%sT%02d:%02d:%02d+00:00',
                $yearRaw,
                $dateRest,
                $hourNum,
                $minNum,
                $normalSec,
            ));
        } catch (\Exception) {
            throw new InvalidArgumentException("Could not parse \"{$text}\".");
        }

        $wallSec = $wallDt->getTimestamp();
        // When offset='use' or 'ignore', the epoch is derived directly from the stated offset
        // (or the timezone offset), so the wall-clock time need not be within the spec range.
        // For 'prefer' and 'reject', we need the wall-clock-derived UTC to be valid, so check.
        if ($offsetOption !== 'use' && $offsetOption !== 'ignore') {
            // ISODateTimeWithinLimits check: the wall-clock (local) date must itself be within the
            // representable ZonedDateTime date range [-271821-04-20, +275760-09-13].
            // - Min: any wallSec < -8640000000000 is on a date before April 20, -271821.
            // - Max: wallSec >= 8640000086400 is on a date after September 13, +275760.
            //   (8640000086400 = max boundary epoch + 86400 s = midnight of +275760-09-14.)
            if ($wallSec < -8_640_000_000_000 || $wallSec >= 8_640_000_086_400) {
                throw new InvalidArgumentException(
                    "ZonedDateTime string \"{$text}\": local date-time is outside the representable range.",
                );
            }
        }
        $subNs = $fractionRaw !== '' ? self::parseFraction($fractionRaw) : 0;

        // Determine epoch seconds.
        if ($hasInlineOffset && (strtoupper($offsetRaw) === 'Z' || $offsetRaw === 'Z' || $offsetRaw === 'z')) {
            // Z → UTC, epochSec = wallSec.
            $epochSec = $wallSec;
        } elseif ($hasInlineOffset) {
            // Inline offset present: behavior depends on offset option.
            $normalizedTzId = self::normalizeTimezoneId($tzId);

            if ($offsetOption === 'use') {
                // Use the stated inline offset directly.
                $epochSec = $wallSec - $inlineOffsetSec;
            } elseif ($offsetOption === 'ignore') {
                // Ignore the inline offset; use the wall clock with the bracket timezone.
                $epochSec = self::wallSecToEpochSec($wallSec, $normalizedTzId, $disambiguation);
            } elseif ($offsetOption === 'prefer') {
                // Prefer the inline offset if it exactly matches the timezone; otherwise use timezone.
                $epochSec = $wallSec - $inlineOffsetSec;
                $actualOffsetSec = self::staticResolveOffset($epochSec, $normalizedTzId);
                if ($actualOffsetSec !== $inlineOffsetSec) {
                    // HH:MM-only offsets that round-match still fall through to timezone resolution,
                    // since the inline offset lacks sub-minute precision.
                    $epochSec = self::wallSecToEpochSec($wallSec, $normalizedTzId, $disambiguation);
                }
            } else {
                // offset: 'reject' (default): throw if inline offset doesn't match timezone.
                $epochSec = $wallSec - $inlineOffsetSec;
                $actualOffsetSec = self::staticResolveOffset($epochSec, $normalizedTzId);
                if ($actualOffsetSec !== $inlineOffsetSec) {
                    // When the inline offset has no seconds, accept if it rounds to the actual offset.
                    if ($inlineOffsetHasSeconds
                        || (int) round($actualOffsetSec / 60.0) * 60 !== $inlineOffsetSec
                    ) {
                        throw new InvalidArgumentException(
                            "Invalid ZonedDateTime string \"{$text}\": inline offset does not match timezone offset.",
                        );
                    }
                    // HH:MM rounds to match: use timezone resolution to preserve wall time.
                    $epochSec = self::wallSecToEpochSec($wallSec, $normalizedTzId, $disambiguation);
                }
            }
            $tzId = $normalizedTzId;
        } else {
            // No inline offset: convert wall clock to UTC via the timezone.
            $normalizedTzId = self::normalizeTimezoneId($tzId);
            if ($isDateOnly) {
                // Date-only string: use startOfDay semantics (TC39 spec).
                $epochSec = self::wallSecToEpochSecStartOfDay($wallSec, $normalizedTzId);
            } else {
                $epochSec = self::wallSecToEpochSec($wallSec, $normalizedTzId, $disambiguation);
            }
            $tzId = $normalizedTzId;
        }

        // Validate spec range.
        $maxSec = 8_640_000_000_000;
        if ($epochSec < -$maxSec || $epochSec > $maxSec || $epochSec === $maxSec && $subNs > 0) {
            throw new InvalidArgumentException(
                "ZonedDateTime string \"{$text}\" is outside the representable nanosecond range.",
            );
        }

        return self::fromEpochParts($epochSec, $subNs, $tzId, $calendarId ?? 'iso8601');
    }

    /**
     * Creates a ZonedDateTime from a property-bag array.
     *
     * Required fields: epochNanoseconds (or a datetime bag), timeZone.
     *
     * @param array<array-key, mixed> $bag
     * @throws \TypeError              if required fields are missing or wrong type.
     * @throws InvalidArgumentException if values are invalid.
     */
    private static function fromPropertyBag(array $bag, string $overflow = 'constrain', string $disambiguation = 'compatible', string $offsetOption = 'reject'): self
    {
        // Validate calendar first (spec validates calendar before required fields).
        $calendarId = 'iso8601';
        if (array_key_exists('calendar', $bag)) {
            /** @var mixed $calRaw */
            $calRaw = $bag['calendar'];
            if (!is_string($calRaw)) {
                throw new \TypeError('ZonedDateTime calendar must be a string.');
            }
            $calendarId = self::extractCalendarFromString($calRaw);
        }

        // Must have a timeZone key.
        if (!array_key_exists('timeZone', $bag)) {
            throw new \TypeError('ZonedDateTime property bag must have a timeZone field.');
        }
        /** @var mixed $tzRaw */
        $tzRaw = $bag['timeZone'];
        if (!is_string($tzRaw)) {
            throw new \TypeError('ZonedDateTime timeZone must be a string.');
        }

        // If epochNanoseconds is provided, use it directly.
        if (array_key_exists('epochNanoseconds', $bag)) {
            /** @var mixed $ensRaw */
            $ensRaw = $bag['epochNanoseconds'];
            if (!is_int($ensRaw) && !is_float($ensRaw)) {
                throw new \TypeError('ZonedDateTime epochNanoseconds must be an integer or float.');
            }
            return new self(is_int($ensRaw) ? $ensRaw : (int) $ensRaw, $tzRaw, $calendarId);
        }

        // Otherwise expect year/month/day/hour/minute/second fields.
        $hasEra = array_key_exists('era', $bag);
        $hasEraYear = array_key_exists('eraYear', $bag);
        $hasEraAndEraYear = $hasEra && $hasEraYear;

        // era and eraYear must come as a pair.
        if ($hasEra !== $hasEraYear) {
            throw new \TypeError('ZonedDateTime property bag must have both era and eraYear, or neither.');
        }

        $calendarSupportsEras = $calendarId !== null && $calendarId !== 'iso8601'
            && !in_array($calendarId, ['chinese', 'dangi'], true);

        if (!array_key_exists('year', $bag) && (!$hasEraAndEraYear || !$calendarSupportsEras)) {
            throw new \TypeError("ZonedDateTime property bag must have a year field.");
        }
        if (!array_key_exists('day', $bag)) {
            throw new \TypeError("ZonedDateTime property bag must have a day field.");
        }

        // month can come from 'month' or 'monthCode'.
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('ZonedDateTime property bag must have a month or monthCode field.');
        }

        /** @var mixed $yr */
        $yr = $bag['year'] ?? null;
        /** @var mixed $dy */
        $dy = $bag['day'];
        /** @var mixed $hr */
        $hr = $bag['hour'] ?? 0;
        /** @var mixed $mn */
        $mn = $bag['minute'] ?? 0;
        /** @var mixed $sc */
        $sc = $bag['second'] ?? 0;
        /** @var mixed $ms */
        $ms = $bag['millisecond'] ?? 0;
        /** @var mixed $us */
        $us = $bag['microsecond'] ?? 0;
        /** @var mixed $ns */
        $ns = $bag['nanosecond'] ?? 0;

        // Validate and cast numeric fields; reject INF/-INF.
        $numericFields = [
            'day' => $dy,
            'hour' => $hr,
            'minute' => $mn,
            'second' => $sc,
            'millisecond' => $ms,
            'microsecond' => $us,
            'nanosecond' => $ns,
        ];
        if ($yr !== null) {
            $numericFields['year'] = $yr;
        }
        /** @psalm-suppress MixedAssignment — array values are all typed as mixed via @var annotations above */
        foreach ($numericFields as $fname => $fval) {
            if (is_float($fval) && is_infinite($fval)) {
                throw new InvalidArgumentException(sprintf(
                    'ZonedDateTime %s must be finite; got %s.',
                    $fname,
                    $fval > 0 ? 'INF' : '-INF',
                ));
            }
        }

        /** @phpstan-ignore cast.int */
        $year = $yr !== null ? (is_int($yr) ? $yr : (int) $yr) : 0;
        /** @phpstan-ignore cast.int */
        $day = is_int($dy) ? $dy : (int) $dy;
        /** @phpstan-ignore cast.int */
        $hour = is_int($hr) ? $hr : (int) $hr;
        /** @phpstan-ignore cast.int */
        $minute = is_int($mn) ? $mn : (int) $mn;
        /** @phpstan-ignore cast.int */
        $second = is_int($sc) ? $sc : (int) $sc;
        /** @phpstan-ignore cast.int */
        $milli = is_int($ms) ? $ms : (int) $ms;
        /** @phpstan-ignore cast.int */
        $micro = is_int($us) ? $us : (int) $us;
        /** @phpstan-ignore cast.int */
        $nano = is_int($ns) ? $ns : (int) $ns;

        $calendar = $calendarId !== 'iso8601'
            ? CalendarFactory::get($calendarId)
            : null;

        // Resolve era + eraYear if present (overrides year for era-based calendars).
        if ($calendar !== null && array_key_exists('era', $bag) && array_key_exists('eraYear', $bag)) {
            /** @var mixed $eraRaw */
            $eraRaw = $bag['era'];
            /** @var mixed $eraYearRaw */
            $eraYearRaw = $bag['eraYear'];
            if (is_string($eraRaw) && $eraYearRaw !== null) {
                /** @phpstan-ignore cast.double */
                if (!is_finite((float) $eraYearRaw)) {
                    throw new InvalidArgumentException('eraYear must be finite.');
                }
                /** @phpstan-ignore cast.int */
                $eraYearInt = is_int($eraYearRaw) ? $eraYearRaw : (int) $eraYearRaw;
                $resolved = $calendar->resolveEra($eraRaw, $eraYearInt);
                if ($resolved !== null) {
                    $year = $resolved;
                }
            }
        }

        // Resolve month from 'month' and/or 'monthCode'.
        $month = null;
        $monthCode = null;
        $hasMonth = array_key_exists('month', $bag);
        $hasMC = array_key_exists('monthCode', $bag);

        if ($hasMC) {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            if (!is_string($mc)) {
                throw new \TypeError('ZonedDateTime monthCode must be a string.');
            }
            $monthCode = $mc;
            $month = $calendar !== null
                ? $calendar->monthCodeToMonth($mc, $year)
                : CalendarMath::monthCodeToMonth($mc);
        }

        if ($hasMonth) {
            /** @var mixed $mo */
            $mo = $bag['month'];
            if (is_float($mo) && is_infinite($mo)) {
                throw new InvalidArgumentException(sprintf(
                    'ZonedDateTime month must be finite; got %s.',
                    $mo > 0 ? 'INF' : '-INF',
                ));
            }
            /** @phpstan-ignore cast.int */
            $newMonth = is_int($mo) ? $mo : (int) $mo;
            if ($hasMC && $newMonth !== $month) {
                throw new InvalidArgumentException('Conflicting month and monthCode fields.');
            }
            $month = $newMonth;
        }
        /** @var int $month */

        // Apply overflow (constrain or reject).
        if ($month < 1) {
            throw new InvalidArgumentException("Invalid month {$month}: must be at least 1.");
        }
        if ($day < 1) {
            throw new InvalidArgumentException("Invalid day {$day}: must be at least 1.");
        }

        // Non-ISO calendar: resolve calendar fields to ISO via the calendar protocol.
        if ($calendar !== null) {
            if ($hasMC && $monthCode !== null) {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIsoFromMonthCode($year, $monthCode, $day, $overflow);
            } else {
                [$isoY, $isoM, $isoD] = $calendar->calendarToIso($year, $month, $day, $overflow);
            }
            $year = $isoY;
            $month = $isoM;
            $day = $isoD;
        } else {
            if ($overflow === 'constrain') {
                /**
                 * @var int<1, 12>
                 * @psalm-suppress UnnecessaryVarAnnotation — Mago can't narrow min()
                 */
                $month = min(12, $month);
                $maxDay = CalendarMath::calcDaysInMonth($year, $month);
                $day = min($maxDay, $day);
            } else {
                // overflow === 'reject'
                if ($month > 12) {
                    throw new InvalidArgumentException("Invalid month {$month}: must be 1–12.");
                }
                $maxDay = CalendarMath::calcDaysInMonth($year, $month);
                if ($day > $maxDay) {
                    throw new InvalidArgumentException("Invalid day {$day}: exceeds {$maxDay} for {$year}-{$month}.");
                }
            }
        }

        // Constrain time fields.
        if ($overflow === 'constrain') {
            $hour = max(0, min(23, $hour));
            $minute = max(0, min(59, $minute));
            $second = max(0, min(59, $second));
            $milli = max(0, min(999, $milli));
            $micro = max(0, min(999, $micro));
            $nano = max(0, min(999, $nano));
        }

        // Use JDN-based computation to handle extreme years (DateTimeImmutable
        // cannot represent years beyond ~9999 or negative years reliably).
        $epochDays = CalendarMath::toJulianDay($year, $month, $day) - 2_440_588;
        $wallSec = ($epochDays * 86_400) + ($hour * 3600) + ($minute * 60) + $second;
        // ISODateTimeWithinLimits check.
        if ($wallSec > 8_640_000_000_000 || $wallSec < -8_640_000_000_000) {
            throw new InvalidArgumentException(
                'ZonedDateTime property bag: local date-time is outside the representable range.',
            );
        }

        $normalTzId = self::normalizeTimezoneId($tzRaw);
        $epochSec = self::wallSecToEpochSec($wallSec, $normalTzId, $disambiguation);
        $subNs = ($milli * self::NS_PER_MILLISECOND) + ($micro * self::NS_PER_MICROSECOND) + $nano;

        // Handle 'offset' field if provided: depends on offset option.
        if (array_key_exists('offset', $bag) && $offsetOption !== 'ignore') {
            /** @var mixed $offRaw */
            $offRaw = $bag['offset'];
            if (!is_string($offRaw)) {
                throw new \TypeError('ZonedDateTime offset must be a string.');
            }
            // Valid format: ±HH:MM or ±HH:MM:SS.
            if (preg_match('/^[+-]\d{2}:\d{2}(:\d{2})?$/', $offRaw) !== 1) {
                throw new InvalidArgumentException("Invalid offset string \"{$offRaw}\": must be ±HH:MM or ±HH:MM:SS.");
            }
            $offSign = $offRaw[0] === '+' ? 1 : -1;
            $offParts = explode(separator: ':', string: substr(string: $offRaw, offset: 1));
            $givenOffsetSec = $offSign * (((int) $offParts[0] * 3600) + ((int) $offParts[1] * 60)
                + (isset($offParts[2]) ? (int) $offParts[2] : 0));

            if ($offsetOption === 'use') {
                // Use the offset directly, regardless of timezone rules.
                $epochSec = $wallSec - $givenOffsetSec;
            } else {
                // 'prefer' or 'reject': try using the given offset.
                $epochFromOffset = $wallSec - $givenOffsetSec;
                $actualOffset = self::staticResolveOffset($epochFromOffset, $normalTzId);
                if ($actualOffset === $givenOffsetSec) {
                    // The offset is valid at this instant — use it.
                    $epochSec = $epochFromOffset;
                } elseif ($offsetOption === 'reject') {
                    // Offset doesn't match timezone at this wall time → reject.
                    throw new InvalidArgumentException(
                        "The offset {$offRaw} does not match the timezone {$normalTzId} offset at the given instant.",
                    );
                }
                // 'prefer': keep disambiguation-resolved epochSec.
            }
        }

        return self::fromEpochParts($epochSec, $subNs, $normalTzId, $calendarId);
    }

    /**
     * Extracts the timezone identifier and optional calendar ID from the bracket annotation section.
     *
     * The FIRST bracket without '=' is the timezone annotation. Key-value brackets
     * (with '=') are metadata (e.g. [u-ca=hebrew]).
     *
     * @return array{0: string, 1: ?string} [timezoneId, calendarId]
     * @throws InvalidArgumentException if no timezone annotation is found, or calendar is unknown.
     */
    private static function extractTzFromAnnotations(string $section, string $original): array
    {
        preg_match_all('/\[(!?)([^\]]*)\]/', $section, $matches, PREG_SET_ORDER);

        $tzId = null;
        $tzCount = 0;
        $calCount = 0;
        $calHasCritical = false;
        $calendarId = null;

        foreach ($matches as $match) {
            [, $bang, $content] = $match;
            $critical = $bang === '!';

            if (str_contains($content, '=')) {
                [$key] = explode(separator: '=', string: $content, limit: 2);
                if ($key !== strtolower($key)) {
                    throw new InvalidArgumentException(
                        "Invalid annotation key \"{$key}\" in \"{$original}\": annotation keys must be lowercase.",
                    );
                }
                if ($key === 'u-ca') {
                    if ($calCount === 0) {
                        $calValue = substr(string: $content, offset: strlen($key) + 1);
                        if (!CalendarFactory::isKnownCalendar($calValue)) {
                            throw new InvalidArgumentException(
                                "Unknown calendar \"{$calValue}\" in \"{$original}\".",
                            );
                        }
                        $calendarId = CalendarFactory::canonicalize($calValue);
                    }
                    ++$calCount;
                    if ($critical) {
                        $calHasCritical = true;
                    }
                    if ($calCount > 1 && $calHasCritical) {
                        throw new InvalidArgumentException(
                            "Multiple calendar annotations with critical flag in \"{$original}\".",
                        );
                    }
                } else {
                    if ($critical) {
                        throw new InvalidArgumentException(
                            "Critical unknown annotation \"[!{$content}]\" in \"{$original}\".",
                        );
                    }
                }
            } else {
                ++$tzCount;
                if ($tzCount > 1) {
                    throw new InvalidArgumentException("Multiple time-zone annotations in \"{$original}\".");
                }
                $tzId = $content;

                // Validate offset-style TZ annotation: ±HH:MM only (no seconds).
                if (preg_match('/^[+-]/', $content) === 1) {
                    if (
                        preg_match('/^[+-]\d{2}:\d{2}:\d{2}/', $content) === 1
                        || preg_match('/^[+-]\d{2}:\d{2}[.,]/', $content) === 1
                    ) {
                        throw new InvalidArgumentException(
                            "Sub-minute UTC offset in time-zone annotation in \"{$original}\".",
                        );
                    }
                    if (preg_match('/^[+-]\d{2}(?!\d*:)\d{4,}/', $content) === 1) {
                        throw new InvalidArgumentException(
                            "Sub-minute UTC offset in time-zone annotation in \"{$original}\".",
                        );
                    }
                }
            }
        }

        if ($tzId === null) {
            throw new InvalidArgumentException(
                "Invalid ZonedDateTime string \"{$original}\": no timezone annotation found.",
            );
        }

        return [$tzId, $calendarId];
    }

    /**
     * Parses a simple offset string (Z, ±HH, ±HH:MM, ±HHMM, ±HH:MM:SS, etc.)
     * into [sign, absSec].
     *
     * @return array{int, int} [sign (+1|-1), absSec]
     */
    private static function parseSimpleOffset(string $offset): array
    {
        if ($offset === 'Z' || $offset === 'z') {
            return [1, 0];
        }
        [$sign, $absSec] = self::parseOffset($offset, $offset);
        return [$sign, $absSec];
    }

    /**
     * Converts wall-clock seconds (as if UTC) to epoch seconds given a timezone.
     *
     * For 'UTC' / fixed-offset: subtract the fixed offset.
     * For IANA: use PHP DateTimeZone transition data.
     */
    /**
     * @internal Used by PlainDate/PlainDateTime for timezone resolution.
     * @psalm-api
     */
    /**
     * Like wallSecToEpochSec, but for startOfDay: when midnight is in a gap,
     * returns the transition epoch (first valid instant of the day) instead of
     * the regular gap disambiguation.
     */
    private static function wallSecToEpochSecStartOfDay(int $wallSec, string $tzId): int
    {
        if ($tzId === 'UTC' || preg_match('/^[+\-]\d{2}:\d{2}$/', $tzId) === 1) {
            return self::wallSecToEpochSec($wallSec, $tzId);
        }
        $tz = new \DateTimeZone($tzId);
        $approxOffset = $tz->getOffset(new \DateTimeImmutable(sprintf('@%d', $wallSec)));
        $epoch1 = $wallSec - $approxOffset;
        $transitions = $tz->getTransitions($epoch1 - 86400, $epoch1 + 86400);
        if (count($transitions) >= 2) {
            for ($i = 1; $i < count($transitions); $i++) {
                $tEpoch = $transitions[$i]['ts'];
                $pre = $transitions[$i - 1]['offset'];
                $post = $transitions[$i]['offset'];
                if ($post > $pre) {
                    // Gap: check if wallSec is in [wallAtPre, wallAtPost)
                    $wallAtPre = $tEpoch + $pre;
                    $wallAtPost = $tEpoch + $post;
                    if ($wallSec >= $wallAtPre && $wallSec < $wallAtPost) {
                        // Midnight is in a gap: return the transition epoch.
                        return $tEpoch;
                    }
                }
            }
        }
        return self::wallSecToEpochSec($wallSec, $tzId);
    }

    public static function wallSecToEpochSec(int $wallSec, string $tzId, string $disambiguation = 'compatible'): int
    {
        if ($tzId === 'UTC') {
            return $wallSec;
        }
        // Fixed offset ±HH:MM.
        if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $tzId, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            $offsetSec = $sign * (((int) $m[2] * 3600) + ((int) $m[3] * 60));
            return $wallSec - $offsetSec;
        }
        // IANA: use PHP's DateTimeZone to resolve wall clock to epoch.
        /** @psalm-suppress ArgumentTypeCoercion — $tzId is validated non-empty before this call */
        $tz = new \DateTimeZone($tzId);

        // Get the standard resolution.
        $approxOffset = $tz->getOffset(new \DateTimeImmutable(sprintf('@%d', $wallSec)));
        $epoch1 = $wallSec - $approxOffset;
        $offset1 = $tz->getOffset(new \DateTimeImmutable(sprintf('@%d', $epoch1)));

        // Check for gap/overlap by looking at timezone transitions near this epoch.
        $transitions = $tz->getTransitions($epoch1 - 86400, $epoch1 + 86400);
        $transitionEpoch = null;
        $preOffset = null;
        $postOffset = null;
        if (count($transitions) >= 2) {
            for ($i = 1; $i < count($transitions); $i++) {
                $tEpoch = $transitions[$i]['ts'];
                $pre = $transitions[$i - 1]['offset'];
                $post = $transitions[$i]['offset'];
                // Check if the wall time falls in a gap or overlap around this transition.
                $wallAtPre = $tEpoch + $pre;
                $wallAtPost = $tEpoch + $post;
                if ($pre > $post) {
                    // Fall-back (overlap): wallAtPost < wallAtPre, wall times in [wallAtPost, wallAtPre) are ambiguous.
                    if ($wallSec >= $wallAtPost && $wallSec < $wallAtPre) {
                        $transitionEpoch = $tEpoch;
                        $preOffset = $pre;
                        $postOffset = $post;
                        break;
                    }
                } elseif ($post > $pre) {
                    // Spring-forward (gap): wallAtPre < wallAtPost, wall times in [wallAtPre, wallAtPost) don't exist.
                    if ($wallSec >= $wallAtPre && $wallSec < $wallAtPost) {
                        $transitionEpoch = $tEpoch;
                        $preOffset = $pre;
                        $postOffset = $post;
                        break;
                    }
                }
            }
        }

        if ($transitionEpoch !== null && $preOffset !== null && $postOffset !== null) {
            if ($preOffset > $postOffset) {
                // Overlap (fall-back): two valid epochs.
                $earlierEpoch = $wallSec - $preOffset;  // Earlier occurrence (before transition, higher offset)
                $laterEpoch = $wallSec - $postOffset;    // Later occurrence (after transition, lower offset)
                return match ($disambiguation) {
                    'earlier', 'compatible' => $earlierEpoch,
                    'later' => $laterEpoch,
                    'reject' => throw new InvalidArgumentException(
                        "Ambiguous wall clock time in timezone {$tzId}.",
                    ),
                    default => $earlierEpoch,
                };
            }
            // Gap (spring-forward): wall time doesn't exist.
            // TC39: resolve by interpreting the wall time in the opposite offset.
            // 'earlier': use post offset → gives an instant before the gap.
            // 'later'/'compatible': use pre offset → gives an instant after the gap.
            $beforeGapEpoch = $wallSec - $postOffset;
            $afterGapEpoch = $wallSec - $preOffset;
            return match ($disambiguation) {
                'compatible', 'later' => $afterGapEpoch,
                'earlier' => $beforeGapEpoch,
                'reject' => throw new InvalidArgumentException(
                    "Non-existent wall clock time in timezone {$tzId}.",
                ),
                default => $afterGapEpoch,
            };
        }

        // No gap/overlap: simple resolution.
        return $wallSec - $offset1;
    }

    /**
     * Resolves the UTC offset (in seconds) for a given timezone at a given epoch second.
     * Static version for use in parseZdtString.
     */
    private static function staticResolveOffset(int $epochSec, string $tzId): int
    {
        if ($tzId === 'UTC') {
            return 0;
        }
        if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $tzId, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            return $sign * (((int) $m[2] * 3600) + ((int) $m[3] * 60));
        }
        /** @psalm-suppress ArgumentTypeCoercion — $tzId is validated non-empty before this call */
        $tz = new \DateTimeZone($tzId);
        return $tz->getOffset(new \DateTimeImmutable(sprintf('@%d', $epochSec)));
    }

    /**
     * Rounds $ns to the nearest multiple of $increment, treating the number "as if positive"
     * (i.e., using floor-division for the base and always rounding toward positive infinity
     * for ties in 'halfCeil'/'halfExpand').
     *
     * @throws InvalidArgumentException for unknown rounding modes.
     */
    private static function roundAsIfPositive(int $ns, int $increment, string $mode): int
    {
        // Integer floor-division: r1 = floor(ns / increment).
        $q = intdiv($ns, $increment);
        $rem = $ns - ($q * $increment);
        $r1 = $rem < 0 ? $q - 1 : $q;

        // d1 = distance of $ns from r1 (always in [0, $increment)).
        $d1 = $ns - ($r1 * $increment);

        // Directed rounding (AsIfPositive: trunc/floor → r1; ceil/expand → r2):
        $r2 = $r1 + 1;
        $rounded = match ($mode) {
            'trunc', 'floor' => $r1,
            'ceil', 'expand' => $d1 === 0 ? $r1 : $r2,
            'halfExpand', 'halfCeil' => ($d1 * 2) >= $increment ? $r2 : $r1,
            'halfTrunc', 'halfFloor' => ($d1 * 2) > $increment ? $r2 : $r1,
            'halfEven' => ($d1 * 2) < $increment ? $r1 : (($d1 * 2) > $increment ? $r2 : (($r1 % 2) === 0 ? $r1 : $r2)),
            default => throw new InvalidArgumentException("Invalid roundingMode \"{$mode}\"."),
        };

        return $rounded * $increment;
    }

    // -------------------------------------------------------------------------
    // Helpers copied from Instant.php
    // -------------------------------------------------------------------------

    /** @return int<0, 999999999> */
    private static function parseFraction(string $fractionRaw): int
    {
        $digits = substr($fractionRaw, offset: 1);
        /** @var int<0, 999999999> — 9 decimal digits, range 000000000–999999999 */
        $ns = (int) str_pad(substr($digits, offset: 0, length: 9), length: 9, pad_string: '0');
        return $ns;
    }

    /**
     * Parses an offset string into [sign, absSec, fracNs].
     *
     * @return array{-1|1, int<0, 86399>, int<0, 999999999>}  [sign (+1|-1), absSec, fracNs]
     * @throws InvalidArgumentException if the offset is out of range.
     */
    private static function parseOffset(string $offset, string $original): array
    {
        if ($offset === 'Z' || $offset === 'z') {
            return [1, 0, 0];
        }

        $sign = $offset[0] === '+' ? 1 : -1;
        $rest = substr(string: $offset, offset: 1);

        $hours = (int) substr(string: $rest, offset: 0, length: 2);
        $rest = substr(string: $rest, offset: 2);
        $minutes = 0;
        $seconds = 0;
        $fracNs = 0;

        if ($rest !== '') {
            if ($rest[0] === ':') {
                $minutes = (int) substr(string: $rest, offset: 1, length: 2);
                $rest = substr(string: $rest, offset: 3);
                if ($rest !== '' && $rest[0] === ':') {
                    $seconds = (int) substr(string: $rest, offset: 1, length: 2);
                    $rest = substr(string: $rest, offset: 3);
                    if ($rest !== '' && ($rest[0] === '.' || $rest[0] === ',')) {
                        $fracNs = self::parseFraction($rest);
                    }
                }
            } else {
                $minutes = (int) substr(string: $rest, offset: 0, length: 2);
                $rest = substr(string: $rest, offset: 2);
                if (strlen($rest) >= 2) {
                    $seconds = (int) substr(string: $rest, offset: 0, length: 2);
                    $rest = substr(string: $rest, offset: 2);
                    if ($rest !== '' && ($rest[0] === '.' || $rest[0] === ',')) {
                        $fracNs = self::parseFraction($rest);
                    }
                }
            }
        }

        $absSec = ($hours * 3600) + ($minutes * 60) + $seconds;
        if ((($absSec * self::NS_PER_SECOND) + $fracNs) > 86_399_999_999_999) {
            throw new InvalidArgumentException(
                "Invalid ZonedDateTime string \"{$original}\": UTC offset out of range.",
            );
        }
        /** @var int<0, 86399> $absSec — range validated above */

        return [$sign, $absSec, $fracNs];
    }

    // -------------------------------------------------------------------------
    // Private helpers for add/subtract/since/until/round/with
    // -------------------------------------------------------------------------

    /**
     * Shared add/subtract implementation for ZonedDateTime.
     *
     * Calendar units are applied to local date fields and re-resolved;
     * time units are added as nanoseconds to the epoch.
     *
     * @param array<array-key, mixed>|object|null $options
     */
    private function addDurationZdt(int $sign, Duration $dur, array|object|null $options): self
    {
        // Sentinel epoch values: if we don't have trueEpochSec for pure-time-unit
        // arithmetic on a sentinel, reject (calendar-unit path uses localComponents
        // which handles sentinels correctly via trueEpochSec).
        if ($this->epochNanoseconds === PHP_INT_MAX || $this->epochNanoseconds === PHP_INT_MIN) {
            $isBlank =
                $dur->years === 0
                && $dur->months === 0
                && $dur->weeks === 0
                && $dur->days === 0
                && $dur->hours === 0
                && $dur->minutes === 0
                && $dur->seconds === 0
                && $dur->milliseconds === 0
                && $dur->microseconds === 0
                && $dur->nanoseconds === 0;
            $hasCalendar = $dur->years !== 0 || $dur->months !== 0 || $dur->weeks !== 0 || $dur->days !== 0;
            if (!$isBlank && !$hasCalendar && $this->trueEpochSec === null) {
                throw new InvalidArgumentException(
                    'ZonedDateTime arithmetic result is outside the representable range.',
                );
            }
        }

        $overflow = self::extractOverflow($options);

        $years = $sign * (int) $dur->years;
        $months = $sign * (int) $dur->months;
        $weeks = $sign * (int) $dur->weeks;
        $days = $sign * (int) $dur->days;
        $hours = $sign * (int) $dur->hours;
        $minutes = $sign * (int) $dur->minutes;
        $seconds = $sign * (int) $dur->seconds;
        $ms = $sign * (int) $dur->milliseconds;
        $us = $sign * (int) $dur->microseconds;
        $ns = $sign * (int) $dur->nanoseconds;

        $hasCalendarUnits = $years !== 0 || $months !== 0 || $weeks !== 0 || $days !== 0;

        if ($hasCalendarUnits) {
            // Get local date/time, apply calendar units, then re-resolve to ZDT.
            $lc = $this->localComponents();

            // Use calendar protocol for non-ISO calendars.
            if ($this->calendarId !== 'iso8601') {
                $cal = CalendarFactory::get($this->calendarId);
                [$newYear, $newMonth, $newDay] = $cal->dateAdd(
                    $lc['year'], $lc['month'], $lc['day'],
                    $years, $months, $weeks, $days,
                    $overflow,
                );
            } else {
                $newYear = $lc['year'] + $years;
                $newMonth = $lc['month'] + $months;

                // Normalize month into 1-12, carrying into year.
                if ($newMonth > 12) {
                    $newYear += intdiv(num1: $newMonth - 1, num2: 12);
                    $newMonth = (($newMonth - 1) % 12) + 1;
                } elseif ($newMonth < 1) {
                    $newYear += intdiv(num1: $newMonth - 12, num2: 12);
                    $newMonth = (((($newMonth - 1) % 12) + 12) % 12) + 1;
                }

                // Clamp or reject day.
                $newDay = $lc['day'];
                $maxDay = CalendarMath::calcDaysInMonth($newYear, $newMonth);
                if ($newDay > $maxDay) {
                    if ($overflow === 'constrain') {
                        $newDay = $maxDay;
                    } else {
                        throw new InvalidArgumentException("Day {$newDay} is out of range for {$newYear}-{$newMonth}.");
                    }
                }

                // Add weeks and days via JDN.
                $totalDays = ($weeks * 7) + $days;
                $jdn = CalendarMath::toJulianDay($newYear, $newMonth, $newDay) + $totalDays;
                [$newYear, $newMonth, $newDay] = CalendarMath::fromJulianDay($jdn);
            }

            // TC39 AddZonedDateTime: first resolve the new local date+time to
            // an intermediate ZDT epoch, then add time units to the epoch.
            // This correctly handles DST day length differences.

            // Balance time units to nanoseconds.
            $timeNs =
                ($hours * 3_600_000_000_000)
                + ($minutes * 60_000_000_000)
                + ($seconds * self::NS_PER_SECOND)
                + ($ms * self::NS_PER_MILLISECOND)
                + ($us * self::NS_PER_MICROSECOND)
                + $ns;

            if ($timeNs === 0) {
                // No time units: just resolve the new local date with original time.
                return self::localToZdt(
                    $newYear, $newMonth, $newDay,
                    $lc['hour'], $lc['minute'], $lc['second'],
                    $lc['millisecond'], $lc['microsecond'], $lc['nanosecond'],
                    $this->timeZoneId, $this->calendarId, 'compatible',
                );
            }

            // Step 1: Resolve new date + original time to intermediate epoch.
            $epochDays = CalendarMath::toJulianDay($newYear, $newMonth, $newDay) - 2_440_588;
            $wallSec = ($epochDays * 86_400)
                + ($lc['hour'] * 3600) + ($lc['minute'] * 60) + $lc['second'];
            $intermediateEpochSec = self::wallSecToEpochSec($wallSec, $this->timeZoneId, 'compatible');
            $intermediateSubNs =
                ($lc['millisecond'] * self::NS_PER_MILLISECOND)
                + ($lc['microsecond'] * self::NS_PER_MICROSECOND)
                + $lc['nanosecond'];

            // Step 2: Add time nanoseconds to the epoch.
            $totalSubNs = $intermediateSubNs + $timeNs;
            $overflowSec = CalendarMath::floorDiv($totalSubNs, self::NS_PER_SECOND);
            $resultSubNs = $totalSubNs - ($overflowSec * self::NS_PER_SECOND);
            $resultEpochSec = $intermediateEpochSec + $overflowSec;

            return self::fromEpochParts($resultEpochSec, $resultSubNs, $this->timeZoneId, $this->calendarId);
        }

        // Pure time units: balance to days + sub-day ns to avoid int64 overflow.
        // Step-by-step carry approach (same as PlainDateTime).
        $hDays = intdiv(num1: $hours, num2: 24);
        $hRem = $hours % 24;

        $totalMin = ($hRem * 60) + $minutes;
        $mDays = intdiv(num1: $totalMin, num2: 1_440);
        $mRem = $totalMin % 1_440;

        $totalSec = ($mRem * 60) + $seconds;
        $sDays = intdiv(num1: $totalSec, num2: 86_400);
        $sRem = $totalSec % 86_400;

        $totalMs = ($sRem * 1_000) + $ms;
        $msDays = intdiv(num1: $totalMs, num2: 86_400_000);
        $msRem = $totalMs % 86_400_000;

        $totalUs = ($msRem * 1_000) + $us;
        $usDays = intdiv(num1: $totalUs, num2: 86_400_000_000);
        $usRem = $totalUs % 86_400_000_000;

        $totalNsRem = ($usRem * 1_000) + $ns;
        $nsDays = intdiv(num1: $totalNsRem, num2: 86_400_000_000_000);
        $nsRem = $totalNsRem % 86_400_000_000_000;

        $totalDays = $hDays + $mDays + $sDays + $msDays + $usDays + $nsDays;

        // Convert days to epoch seconds and add the sub-day ns.
        if ($this->trueEpochSec !== null) {
            $epochSec = $this->trueEpochSec;
            $subNsOrig = $this->trueSubNs;
        } else {
            $epochSec = CalendarMath::floorDiv($this->epochNanoseconds, self::NS_PER_SECOND);
            $subNsOrig = $this->epochNanoseconds - ($epochSec * self::NS_PER_SECOND);
        }

        $newEpochSec = $epochSec + ($totalDays * 86_400);
        $newSubNs = $subNsOrig + $nsRem;

        // Carry from sub-ns.
        if ($newSubNs >= self::NS_PER_SECOND) {
            $carry = intdiv(num1: $newSubNs, num2: self::NS_PER_SECOND);
            $newEpochSec += $carry;
            $newSubNs -= $carry * self::NS_PER_SECOND;
        } elseif ($newSubNs < 0) {
            $carry = (int) ceil(-$newSubNs / self::NS_PER_SECOND);
            $newEpochSec -= $carry;
            $newSubNs += $carry * self::NS_PER_SECOND;
        }

        return self::fromEpochParts($newEpochSec, $newSubNs, $this->timeZoneId, $this->calendarId);
    }

    /**
     * Converts local date/time components to a ZonedDateTime via the given timezone.
     *
     * Uses JDN-based epoch-day arithmetic to handle extreme years correctly
     * (DateTimeImmutable cannot represent years beyond ~9999 or negative years reliably).
     *
     * @param string $disambiguation 'compatible', 'earlier', 'later', or 'reject'.
     * @psalm-suppress UnusedParam — $disambiguation reserved for future DST-aware resolution
     */
    private static function localToZdt(
        int $year,
        int $month,
        int $day,
        int $h,
        int $min,
        int $sec,
        int $ms,
        int $us,
        int $ns,
        string $tzId,
        string $calendarId,
        string $disambiguation,
    ): self {
        // Compute wall-clock seconds from JDN to handle extreme years.
        $epochDays = CalendarMath::toJulianDay($year, $month, $day) - 2_440_588;
        $wallSec = ($epochDays * 86_400) + ($h * 3600) + ($min * 60) + $sec;
        $epochSec = self::wallSecToEpochSec($wallSec, $tzId, $disambiguation);

        $subNs = ($ms * self::NS_PER_MILLISECOND) + ($us * self::NS_PER_MICROSECOND) + $ns;

        return self::fromEpochParts($epochSec, $subNs, $tzId, $calendarId);
    }

    /**
     * Creates a ZonedDateTime from UTC epoch seconds and sub-second nanoseconds.
     *
     * Internal factory used by PlainDate::toZonedDateTime() for dates outside
     * the int64 nanosecond range. Not part of the public API.
     *
     * @internal
     */
    public static function createFromEpochParts(
        int $epochSec,
        int $subNs,
        string $tzId,
        string $calendarId = 'iso8601',
    ): self {
        return self::fromEpochParts($epochSec, $subNs, $tzId, $calendarId);
    }

    /**
     * Creates a ZonedDateTime from UTC epoch seconds and sub-second nanoseconds.
     *
     * Handles int64 overflow by storing a sentinel epochNanoseconds value while
     * preserving the true epoch seconds for later decomposition in localComponents().
     */
    private static function fromEpochParts(
        int $epochSec,
        int $subNs,
        string $tzId,
        string $calendarId = 'iso8601',
    ): self {
        // Range check.
        $absEpochSec = abs($epochSec);
        if ($absEpochSec > 8_640_000_000_000 || $absEpochSec === 8_640_000_000_000 && $subNs > 0) {
            throw new InvalidArgumentException('ZonedDateTime arithmetic result is outside the representable range.');
        }

        $maxSecForNs = 9_223_372_035;
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            $epochNs = $epochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX;
            $zdt = new self($epochNs, $tzId, $calendarId);
            $zdt->trueEpochSec = $epochSec;
            $zdt->trueSubNs = $subNs;
            return $zdt;
        }

        return new self(($epochSec * self::NS_PER_SECOND) + $subNs, $tzId, $calendarId);
    }

    /**
     * Core diff implementation for ZonedDateTime since() and until().
     *
     * TC39 CalendarDateUntil is always called as (temporalDate, other). For
     * "since", the final result is negated.
     *
     * @param string $operation 'since' or 'until'
     * @param array<array-key, mixed>|object|null $options
     */
    private static function diffZdt(self $temporalDate, self $other, string $operation, array|object|null $options): Duration
    {
        /** @var list<string> $validUnits */
        static $validUnits = [
            'auto',
            'day',
            'days',
            'week',
            'weeks',
            'month',
            'months',
            'year',
            'years',
            'hour',
            'hours',
            'minute',
            'minutes',
            'second',
            'seconds',
            'millisecond',
            'milliseconds',
            'microsecond',
            'microseconds',
            'nanosecond',
            'nanoseconds',
        ];
        /** @var array<string, int> $unitRank */
        static $unitRank = [
            'year' => 8,
            'years' => 8,
            'month' => 7,
            'months' => 7,
            'week' => 6,
            'weeks' => 6,
            'day' => 5,
            'days' => 5,
            'auto' => 4,
            'hour' => 4,
            'hours' => 4,
            'minute' => 3,
            'minutes' => 3,
            'second' => 2,
            'seconds' => 2,
            'millisecond' => 1,
            'milliseconds' => 1,
            'microsecond' => 1,
            'microseconds' => 1,
            'nanosecond' => 1,
            'nanoseconds' => 1,
        ];

        // Default for ZDT: largestUnit = 'hour' (not 'day').
        $largestUnit = 'hour';
        $largestUnitExplicit = false;
        $smallestUnit = null;
        $roundingMode = 'trunc';
        $roundingIncrement = 1;

        if ($options !== null) {
            $opts = is_array($options) ? $options : (array) $options;

            if (array_key_exists('largestUnit', $opts)) {
                /** @var mixed $lu */
                $lu = $opts['largestUnit'];
                if ($lu !== null && !is_string($lu)) {
                    throw new \TypeError('largestUnit option must be a string.');
                }
                if (is_string($lu)) {
                    if (!in_array($lu, $validUnits, strict: true)) {
                        throw new InvalidArgumentException("Invalid largestUnit value: \"{$lu}\".");
                    }
                    $largestUnit = $lu;
                    $largestUnitExplicit = true;
                }
            }

            if (array_key_exists('roundingIncrement', $opts)) {
                /** @var mixed $ri */
                $ri = $opts['roundingIncrement'];
                if ($ri !== null) {
                    $roundingIncrement = CalendarMath::validateRoundingIncrement($ri);
                }
            }

            if (array_key_exists('roundingMode', $opts)) {
                /** @var mixed $rm */
                $rm = $opts['roundingMode'];
                if ($rm !== null && !is_string($rm)) {
                    throw new \TypeError('roundingMode option must be a string.');
                }
                if (is_string($rm)) {
                    if (!in_array($rm, CalendarMath::ROUNDING_MODES, strict: true)) {
                        throw new InvalidArgumentException("Invalid roundingMode value: \"{$rm}\".");
                    }
                    $roundingMode = $rm;
                }
            }

            if (array_key_exists('smallestUnit', $opts)) {
                /** @var mixed $su */
                $su = $opts['smallestUnit'];
                if ($su !== null && !is_string($su)) {
                    throw new \TypeError('smallestUnit option must be a string.');
                }
                if (is_string($su)) {
                    if (!in_array($su, $validUnits, strict: true)) {
                        throw new InvalidArgumentException("Invalid smallestUnit value: \"{$su}\".");
                    }
                    $smallestUnit = $su;
                }
            }
        }

        if ($smallestUnit === null) {
            $smallestUnit = 'nanosecond';
        }

        $normLargest = match ($largestUnit) {
            'years' => 'year',
            'months' => 'month',
            'weeks' => 'week',
            'days' => 'day',
            'auto' => 'hour',
            'hours' => 'hour',
            'minutes' => 'minute',
            'seconds' => 'second',
            'milliseconds' => 'millisecond',
            'microseconds' => 'microsecond',
            'nanoseconds' => 'nanosecond',
            default => $largestUnit,
        };
        $normSmallest = match ($smallestUnit) {
            'years' => 'year',
            'months' => 'month',
            'weeks' => 'week',
            'days' => 'day',
            'auto' => 'hour',
            'hours' => 'hour',
            'minutes' => 'minute',
            'seconds' => 'second',
            'milliseconds' => 'millisecond',
            'microseconds' => 'microsecond',
            'nanoseconds' => 'nanosecond',
            default => $smallestUnit,
        };

        /** @var array<string, int> $canonRank */
        static $canonRank = [
            'year' => 10,
            'month' => 9,
            'week' => 8,
            'day' => 7,
            'hour' => 6,
            'minute' => 5,
            'second' => 4,
            'millisecond' => 3,
            'microsecond' => 2,
            'nanosecond' => 1,
        ];
        $suRank = $canonRank[$normSmallest] ?? 1;
        $luRank = $canonRank[$normLargest] ?? 4;

        if ($suRank > $luRank) {
            if ($largestUnitExplicit) {
                throw new InvalidArgumentException(
                    "smallestUnit \"{$normSmallest}\" cannot be larger than largestUnit \"{$normLargest}\".",
                );
            }
            $normLargest = $normSmallest;
        }

        // Validate roundingIncrement against smallest unit.
        if ($roundingIncrement > 1) {
            /** @var array<string, int> $maxIncrementForUnit */
            static $maxIncrementForUnit = [
                'hour' => 24,
                'minute' => 60,
                'second' => 60,
                'millisecond' => 1000,
                'microsecond' => 1000,
                'nanosecond' => 1000,
            ];
            $maxIncrement = $maxIncrementForUnit[$normSmallest] ?? 0;
            if (
                $maxIncrement > 0
                && ($roundingIncrement >= $maxIncrement || ($maxIncrement % $roundingIncrement) !== 0)
            ) {
                throw new InvalidArgumentException(
                    "roundingIncrement {$roundingIncrement} is invalid for unit \"{$normSmallest}\".",
                );
            }
        }

        // Validate that rounding increment for day units doesn't exceed the date range.
        if ($roundingIncrement > 1 && in_array($normSmallest, ['day', 'week'], strict: true)) {
            $incDays = $normSmallest === 'week' ? $roundingIncrement * 7 : $roundingIncrement;
            $maxEpochDays = 100_000_000;
            // Check both directions from the earlier/later endpoints.
            $recLocal = $temporalDate->localComponents();
            $recEpochDays =
                CalendarMath::toJulianDay($recLocal['year'], $recLocal['month'], $recLocal['day']) - 2_440_588;
            if ((abs($recEpochDays) + $incDays) > $maxEpochDays) {
                throw new InvalidArgumentException(
                    "roundingIncrement {$roundingIncrement} for unit \"{$normSmallest}\" would exceed the representable date range.",
                );
            }
        }

        $isCalendarLargest = in_array($normLargest, ['year', 'month', 'week', 'day'], strict: true);

        // TC39: for calendar-largest units, require matching canonical timezones.
        if ($isCalendarLargest
            && self::canonicalizeTimezoneForComparison($temporalDate->timeZoneId)
                !== self::canonicalizeTimezoneForComparison($other->timeZoneId)
        ) {
            throw new InvalidArgumentException(
                "Cannot compute {$operation}() with largestUnit '{$normLargest}' between different timezones.",
            );
        }

        // Epoch ns difference: other − temporalDate.
        // Positive when other > temporalDate (the "until" direction).
        $diffNs = self::diffEpochNs($temporalDate, $other);

        // Overall sign.
        $sign = $diffNs > 0 ? 1 : ($diffNs < 0 ? -1 : 0);

        // For "since", negate the output sign per TC39 spec.
        $outputSign = $operation === 'since' ? -$sign : $sign;

        // Negate directional rounding modes for negative output durations so that
        // floor/ceil behave correctly toward -infinity/+infinity.
        $effectiveMode = $outputSign < 0 ? self::negateRoundingMode($roundingMode) : $roundingMode;

        if ($isCalendarLargest) {
            // Use local date/time fields for calendar-aware diff.
            $tdLocal = $temporalDate->localComponents();
            $otherLocal = $other->localComponents();

            // Assign earlier/later so we always diff in the positive direction.
            if ($sign >= 0) {
                $earlierLocal = $tdLocal;
                $laterLocal = $otherLocal;
            } else {
                $earlierLocal = $otherLocal;
                $laterLocal = $tdLocal;
            }

            // Date diff in JDN.
            $laterJdn = CalendarMath::toJulianDay($laterLocal['year'], $laterLocal['month'], $laterLocal['day']);
            $earlierJdn = CalendarMath::toJulianDay(
                $earlierLocal['year'],
                $earlierLocal['month'],
                $earlierLocal['day'],
            );
            $laterTimeNs =
                ($laterLocal['hour'] * 3_600_000_000_000)
                + ($laterLocal['minute'] * 60_000_000_000)
                + ($laterLocal['second'] * self::NS_PER_SECOND)
                + ($laterLocal['millisecond'] * self::NS_PER_MILLISECOND)
                + ($laterLocal['microsecond'] * self::NS_PER_MICROSECOND)
                + $laterLocal['nanosecond'];
            $earlierTimeNs =
                ($earlierLocal['hour'] * 3_600_000_000_000)
                + ($earlierLocal['minute'] * 60_000_000_000)
                + ($earlierLocal['second'] * self::NS_PER_SECOND)
                + ($earlierLocal['millisecond'] * self::NS_PER_MILLISECOND)
                + ($earlierLocal['microsecond'] * self::NS_PER_MICROSECOND)
                + $earlierLocal['nanosecond'];

            $dateDiff = $laterJdn - $earlierJdn;
            $timeDiffNs = $laterTimeNs - $earlierTimeNs;

            // Borrow one day if time part is negative.
            if ($timeDiffNs < 0) {
                $dateDiff--;
                $timeDiffNs += 86_400_000_000_000;
            }

            // Calendar diff. adjOtherJdn is the adjusted other date after borrow.
            $adjOtherJdn = $earlierJdn + $dateDiff;
            [$adjY2, $adjM2, $adjD2] = CalendarMath::fromJulianDay($adjOtherJdn);
            $calId = $temporalDate->calendarId;

            if ($normLargest === 'day') {
                $days = $dateDiff;
                [$years, $months, $weeks] = [0, 0, 0];
            } elseif ($normLargest === 'week') {
                $weeks = intdiv(num1: $dateDiff, num2: 7);
                $days = $dateDiff - ($weeks * 7);
                [$years, $months] = [0, 0];
            } elseif ($calId !== 'iso8601') {
                // TC39 CalendarDateUntil(temporalDate, adjustedOther) — always
                // in (this, other) order. Compute adjustedOther per TC39
                // DifferenceISODateTime: only borrow when signs conflict.
                $tdJdn = CalendarMath::toJulianDay($tdLocal['year'], $tdLocal['month'], $tdLocal['day']);
                $otherJdn2 = CalendarMath::toJulianDay($otherLocal['year'], $otherLocal['month'], $otherLocal['day']);
                $rawTdTimeNs =
                    ($tdLocal['hour'] * 3_600_000_000_000) + ($tdLocal['minute'] * 60_000_000_000)
                    + ($tdLocal['second'] * self::NS_PER_SECOND) + ($tdLocal['millisecond'] * self::NS_PER_MILLISECOND)
                    + ($tdLocal['microsecond'] * self::NS_PER_MICROSECOND) + $tdLocal['nanosecond'];
                $rawOtherTimeNs =
                    ($otherLocal['hour'] * 3_600_000_000_000) + ($otherLocal['minute'] * 60_000_000_000)
                    + ($otherLocal['second'] * self::NS_PER_SECOND) + ($otherLocal['millisecond'] * self::NS_PER_MILLISECOND)
                    + ($otherLocal['microsecond'] * self::NS_PER_MICROSECOND) + $otherLocal['nanosecond'];
                $rawTD = $rawOtherTimeNs - $rawTdTimeNs;
                $tS = $rawTD > 0 ? 1 : ($rawTD < 0 ? -1 : 0);
                $dS = $tdJdn > $otherJdn2 ? 1 : ($tdJdn < $otherJdn2 ? -1 : 0);
                $tc39AdjJdn = $otherJdn2;
                if ($tS !== 0 && $tS === -$dS) {
                    $tc39AdjJdn = $otherJdn2 - $tS;
                }
                [$tc39Y, $tc39M, $tc39D] = CalendarMath::fromJulianDay($tc39AdjJdn);
                $cal = CalendarFactory::get($calId);
                [$years, $months, , $days] = $cal->dateUntil(
                    $tdLocal['year'], $tdLocal['month'], $tdLocal['day'],
                    $tc39Y, $tc39M, $tc39D,
                    $normLargest,
                );
                $years = abs($years);
                $months = abs($months);
                $days = abs($days);
                $weeks = 0;
            } else {
                // ISO calendar: calendarDiff expects (smaller, larger).
                $receiverIsLater = $sign < 0;
                [$years, $months, $days] = self::calendarDiff(
                    $earlierLocal['year'],
                    $earlierLocal['month'],
                    $earlierLocal['day'],
                    $adjY2,
                    $adjM2,
                    $adjD2,
                    $receiverIsLater,
                );
                $weeks = 0;
            }

            // Convert years to months when largestUnit is 'month'.
            if ($normLargest === 'month') {
                $months = ($years * 12) + $months;
                $years = 0;
            }

            // TC39 DifferenceZonedDateTime: recompute timeDiffNs using actual
            // epoch arithmetic when the timezone is an IANA zone (not fixed-offset).
            // This correctly handles DST transitions where wall-clock time
            // differs from elapsed time.
            $tzForRecompute = $temporalDate->timeZoneId;
            $isIanaTz = $tzForRecompute !== 'UTC'
                && !preg_match('/^[+\-]\d{2}:\d{2}$/', $tzForRecompute);
            if ($isIanaTz && ($years !== 0 || $months !== 0 || $weeks !== 0 || $days !== 0)) {
                // Add date portion to the earlier ZDT, measure remaining ns.
                $earlierZ = $sign >= 0 ? $temporalDate : $other;
                $laterZ = $sign >= 0 ? $other : $temporalDate;
                try {
                    $intermediate = $earlierZ->add(new Duration(
                        years: $years, months: $months, weeks: $weeks, days: $days,
                    ));
                    [$intSec, $intSub] = $intermediate->getEpochParts();
                    [$latSec, $latSub] = $laterZ->getEpochParts();
                    $recomputedNs = ($latSec - $intSec) * 1_000_000_000 + ($latSub - $intSub);
                    if ($recomputedNs >= 0) {
                        $timeDiffNs = $recomputedNs;
                    } elseif ($days > 0) {
                        // Negative time means the date portion overshot (DST gap at
                        // the intermediate date). Reduce days by 1 and recompute.
                        $days--;
                        $intermediate2 = $earlierZ->add(new Duration(
                            years: $years, months: $months, weeks: $weeks, days: $days,
                        ));
                        [$intSec2, $intSub2] = $intermediate2->getEpochParts();
                        $recomputedNs2 = ($latSec - $intSec2) * 1_000_000_000 + ($latSub - $intSub2);
                        if ($recomputedNs2 >= 0) {
                            $timeDiffNs = $recomputedNs2;
                        }
                    }
                } catch (\Throwable) {
                    // Keep wall-clock timeDiffNs on failure
                }
            } elseif ($isIanaTz && $years === 0 && $months === 0 && $weeks === 0 && $days === 0) {
                // Same date, no date diff: use raw epoch diff for the time part.
                $absDiffNsSameDay = $sign < 0 ? -$diffNs : $diffNs;
                if ($absDiffNsSameDay >= 0) {
                    $timeDiffNs = $absDiffNsSameDay;
                }
            }

            $isSmallestCalendar = in_array($normSmallest, ['year', 'month', 'week', 'day'], strict: true);

            // The receiver (temporalDate) is the later date when sign < 0.
            $receiverIsLater = $sign < 0;

            // For rounding, determine earlier/later local components.
            if ($sign >= 0) {
                $earlierLocal = $tdLocal;
                $laterLocal = $otherLocal;
            } else {
                $earlierLocal = $otherLocal;
                $laterLocal = $tdLocal;
            }

            // For IANA timezones, compute the actual day length at the intermediate
            // date (after adding date portion). This is needed for DST-aware
            // progress computation where 24h might not equal 1 day.
            $nsPerDayF = 86_400_000_000_000.0;
            if ($isIanaTz && ($years !== 0 || $months !== 0 || $weeks !== 0 || $days !== 0)) {
                try {
                    $earlierZ3 = $sign >= 0 ? $temporalDate : $other;
                    $intermediate3 = $earlierZ3->add(new Duration(
                        years: $years, months: $months, weeks: $weeks, days: $days,
                    ));
                    $actualHours = $intermediate3->hoursInDay;
                    if ($actualHours !== 24 && $actualHours > 0) {
                        $nsPerDayF = $actualHours * 3_600_000_000_000.0;
                    }
                } catch (\Throwable) {
                    // Keep default 24h
                }
            }

            if ($isSmallestCalendar) {
                // Calendar-unit rounding: zero out time and round the calendar part.

                // Receiver's local components for calendar-aware rounding.
                $recLocal = $tdLocal;

                if ($normSmallest === 'year') {
                    $floorCount = intdiv(num1: $years, num2: $roundingIncrement) * $roundingIncrement;

                    $progress = self::calcYearProgress(
                        $recLocal,
                        $earlierLocal,
                        $laterLocal,
                        $floorCount,
                        $roundingIncrement,
                        $days,
                        $timeDiffNs,
                        $receiverIsLater,
                    );
                    $roundUp = self::applyRoundingProgress($years, $progress, $roundingIncrement, $effectiveMode);
                    $roundedYears = $roundUp ? $floorCount + $roundingIncrement : $floorCount;
                    return new Duration(years: $outputSign * $roundedYears);
                }
                if ($normSmallest === 'month') {
                    $totalMonths = ($years * 12) + $months;
                    $floorCount = intdiv(num1: $totalMonths, num2: $roundingIncrement) * $roundingIncrement;

                    $progress = self::calcMonthProgress(
                        $recLocal,
                        $earlierLocal,
                        $laterLocal,
                        $floorCount,
                        $roundingIncrement,
                        $days,
                        $timeDiffNs,
                        $receiverIsLater,
                    );
                    $roundUp = self::applyRoundingProgress($totalMonths, $progress, $roundingIncrement, $effectiveMode);
                    $roundedMonths = $roundUp ? $floorCount + $roundingIncrement : $floorCount;
                    if ($normLargest === 'year') {
                        $ry = intdiv(num1: $roundedMonths, num2: 12);
                        $rm = $roundedMonths - ($ry * 12);
                        return new Duration(years: $outputSign * $ry, months: $outputSign * $rm);
                    }
                    return new Duration(months: $outputSign * $roundedMonths);
                }
                if ($normSmallest === 'week') {
                    $totalDays = ($weeks * 7) + $days;
                    $progress = $timeDiffNs > 0 ? (float) $timeDiffNs / $nsPerDayF : 0.0;
                    $weekDays = $totalDays;
                    $weekIncrement = $roundingIncrement * 7;
                    $roundUp = self::applyRoundingProgress($weekDays, $progress, $weekIncrement, $effectiveMode);
                    $q = intdiv(num1: $weekDays, num2: $weekIncrement);
                    $roundedDays = $roundUp ? ($q + 1) * $weekIncrement : $q * $weekIncrement;
                    return new Duration(weeks: $outputSign * intdiv(num1: $roundedDays, num2: 7));
                }
                // normSmallest === 'day'
                $progress = $timeDiffNs > 0 ? (float) $timeDiffNs / $nsPerDayF : 0.0;
                $roundUp = self::applyRoundingProgress($days, $progress, $roundingIncrement, $effectiveMode);
                $q = intdiv(num1: $days, num2: $roundingIncrement);
                $roundedDays = $roundUp ? ($q + 1) * $roundingIncrement : $q * $roundingIncrement;
                if ($normLargest === 'day') {
                    return new Duration(days: $outputSign * $roundedDays);
                }
                if ($normLargest === 'week') {
                    $totalDays = ($weeks * 7) + $roundedDays;
                    $roundedWeeks = intdiv(num1: $totalDays, num2: 7);
                    $remDays = $totalDays - ($roundedWeeks * 7);
                    return new Duration(weeks: $outputSign * $roundedWeeks, days: $outputSign * $remDays);
                }
                return new Duration(years: $outputSign * $years, months: $outputSign * $months, days: $outputSign * $roundedDays);
            }

            // smallestUnit is a time unit but largestUnit is a calendar unit.
            $absTimeNs = $timeDiffNs;
            $nsPerSmallest = match ($normSmallest) {
                'hour' => 3_600_000_000_000,
                'minute' => 60_000_000_000,
                'second' => self::NS_PER_SECOND,
                'millisecond' => self::NS_PER_MILLISECOND,
                'microsecond' => self::NS_PER_MICROSECOND,
                default => 1,
            };
            /** @psalm-var int<1, 1000> $roundingIncrement */
            $nsIncrement = $nsPerSmallest * $roundingIncrement;
            $absTimeNs = self::roundPositiveNs($absTimeNs, $nsIncrement, $effectiveMode);

            // Handle day overflow from rounding time.
            // Use DST-aware day length for IANA timezones.
            $nsPerDayForOverflow = (int) $nsPerDayF;
            $overflowDays = intdiv(num1: $absTimeNs, num2: $nsPerDayForOverflow);
            $absTimeNs = $absTimeNs % $nsPerDayForOverflow;
            $days += $overflowDays;

            // Re-balance calendar units when day overflow pushes past month boundaries.
            if ($overflowDays > 0 && in_array($normLargest, ['year', 'month'], strict: true)) {
                if ($calId !== 'iso8601') {
                    // Non-ISO: shift tc39AdjJdn by overflow in the diff direction.
                    $tc39Jdn2 = $tc39AdjJdn + ($sign >= 0 ? $overflowDays : -$overflowDays);
                    [$anchorY, $anchorM, $anchorD] = CalendarMath::fromJulianDay($tc39Jdn2);
                    $cal2 = CalendarFactory::get($calId);
                    [$years, $months, , $days] = $cal2->dateUntil(
                        $tdLocal['year'], $tdLocal['month'], $tdLocal['day'],
                        $anchorY, $anchorM, $anchorD,
                        $normLargest,
                    );
                    $years = abs($years);
                    $months = abs($months);
                    $days = abs($days);
                } else {
                    // ISO: use swap-based adjOtherJdn + overflow.
                    $isoAdjJdn2 = $adjOtherJdn + $overflowDays;
                    [$anchorY, $anchorM, $anchorD] = CalendarMath::fromJulianDay($isoAdjJdn2);
                    [$years, $months, $days] = self::calendarDiff(
                        $earlierLocal['year'],
                        $earlierLocal['month'],
                        $earlierLocal['day'],
                        $anchorY,
                        $anchorM,
                        $anchorD,
                        $sign < 0,
                    );
                }
                if ($normLargest === 'month') {
                    $months = ($years * 12) + $months;
                    $years = 0;
                }
                $weeks = 0;
            }

            $h = intdiv(num1: $absTimeNs, num2: 3_600_000_000_000);
            $rem = $absTimeNs % 3_600_000_000_000;
            $min = intdiv(num1: $rem, num2: 60_000_000_000);
            $rem = $rem % 60_000_000_000;
            $sec = intdiv(num1: $rem, num2: self::NS_PER_SECOND);
            $rem = $rem % self::NS_PER_SECOND;
            $msR = intdiv(num1: $rem, num2: self::NS_PER_MILLISECOND);
            $rem = $rem % self::NS_PER_MILLISECOND;
            $usR = intdiv(num1: $rem, num2: self::NS_PER_MICROSECOND);
            $nsR = $rem % self::NS_PER_MICROSECOND;

            return new Duration(
                years: $outputSign * $years,
                months: $outputSign * $months,
                weeks: $outputSign * $weeks,
                days: $outputSign * $days,
                hours: $outputSign * $h,
                minutes: $outputSign * $min,
                seconds: $outputSign * $sec,
                milliseconds: $outputSign * $msR,
                microseconds: $outputSign * $usR,
                nanoseconds: $outputSign * $nsR,
            );
        }

        // Time-only units: use epoch ns difference.
        $absDiffNs = $sign < 0 ? -$diffNs : $diffNs;

        $nsPerSmallest = match ($normSmallest) {
            'hour' => 3_600_000_000_000,
            'minute' => 60_000_000_000,
            'second' => self::NS_PER_SECOND,
            'millisecond' => self::NS_PER_MILLISECOND,
            'microsecond' => self::NS_PER_MICROSECOND,
            default => 1,
        };
        /** @psalm-var int<1, 1000> $roundingIncrement */
        $nsIncrement = $nsPerSmallest * $roundingIncrement;
        $roundedAbsNs = self::roundPositiveNs($absDiffNs, $nsIncrement, $effectiveMode);

        /** @var array<string,int> $timeUnitRank */
        static $timeUnitRank = [
            'hour' => 6,
            'minute' => 5,
            'second' => 4,
            'millisecond' => 3,
            'microsecond' => 2,
            'nanosecond' => 1,
        ];
        $luTimeRank = $timeUnitRank[$normLargest] ?? 6;

        $h = $luTimeRank >= 6 ? intdiv(num1: $roundedAbsNs, num2: 3_600_000_000_000) : 0;
        $rem = $luTimeRank >= 6 ? $roundedAbsNs % 3_600_000_000_000 : $roundedAbsNs;
        $min = $luTimeRank >= 5 ? intdiv(num1: $rem, num2: 60_000_000_000) : 0;
        $rem = $luTimeRank >= 5 ? $rem % 60_000_000_000 : $rem;
        $sec = $luTimeRank >= 4 ? intdiv(num1: $rem, num2: self::NS_PER_SECOND) : 0;
        $rem = $luTimeRank >= 4 ? $rem % self::NS_PER_SECOND : $rem;
        $msR = $luTimeRank >= 3 ? intdiv(num1: $rem, num2: self::NS_PER_MILLISECOND) : 0;
        $rem = $luTimeRank >= 3 ? $rem % self::NS_PER_MILLISECOND : $rem;
        $usR = $luTimeRank >= 2 ? intdiv(num1: $rem, num2: self::NS_PER_MICROSECOND) : 0;
        $nsR = $luTimeRank >= 2 ? $rem % self::NS_PER_MICROSECOND : $rem;

        return new Duration(
            hours: $outputSign * $h,
            minutes: $outputSign * $min,
            seconds: $outputSign * $sec,
            milliseconds: $outputSign * $msR,
            microseconds: $outputSign * $usR,
            nanoseconds: $outputSign * $nsR,
        );
    }

    /**
     * Calendar-aware year/month/day breakdown between two dates.
     *
     * @param int<1, 12> $m1
     * @param int<1, 12> $m2
     * @return array{0: int, 1: int, 2: int} [years, months, days] — all non-negative.
     */
    private static function calendarDiff(
        int $y1,
        int $m1,
        int $d1,
        int $y2,
        int $m2,
        int $d2,
        bool $receiverIsY2 = true,
    ): array {
        $sign = $y2 > $y1 || $y2 === $y1 && ($m2 > $m1 || $m2 === $m1 && $d2 >= $d1) ? 1 : -1;

        $receiverIsY2AfterSwap = $receiverIsY2;

        if ($sign < 0) {
            [$y1, $m1, $d1, $y2, $m2, $d2] = [$y2, $m2, $d2, $y1, $m1, $d1];
            $receiverIsY2AfterSwap = !$receiverIsY2;
        }

        $years = $y2 - $y1;
        $months = $m2 - $m1;

        if ($months < 0) {
            $years--;
            $months += 12;
        }

        if ($d2 < $d1) {
            if ($months > 0) {
                $months--;
            } else {
                $years--;
                $months = 11;
            }
        }

        if ($receiverIsY2AfterSwap) {
            $anchorMonth = $m2 - $months;
            $anchorYear = $y2 - $years;
            if ($anchorMonth <= 0) {
                $anchorYear--;
                $anchorMonth += 12;
            }
            $anchorMaxDay = CalendarMath::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay = min($d2, $anchorMaxDay);
            $days =
                CalendarMath::toJulianDay($anchorYear, $anchorMonth, $anchorDay)
                - CalendarMath::toJulianDay($y1, $m1, $d1);
        } else {
            $anchorMonth = $m1 + $months;
            $anchorYear = $y1 + $years;
            if ($anchorMonth > 12) {
                $anchorYear++;
                $anchorMonth -= 12;
            }
            $anchorMaxDay = CalendarMath::calcDaysInMonth($anchorYear, $anchorMonth);
            $anchorDay = min($d1, $anchorMaxDay);
            $days =
                CalendarMath::toJulianDay($y2, $m2, $d2)
                - CalendarMath::toJulianDay($anchorYear, $anchorMonth, $anchorDay);
        }

        return [$sign * $years, $sign * $months, $sign * $days];
    }

    /**
     * Rounds a non-negative nanosecond value to the nearest multiple of $increment.
     */
    private static function roundPositiveNs(int $ns, int $increment, string $mode): int
    {
        $q = intdiv(num1: $ns, num2: $increment);
        $rem = $ns - ($q * $increment);
        $r1 = $q * $increment;
        $r2 = $r1 + $increment;
        return match ($mode) {
            'trunc', 'floor' => $r1,
            'ceil', 'expand' => $rem === 0 ? $r1 : $r2,
            'halfExpand', 'halfCeil' => ($rem * 2) >= $increment ? $r2 : $r1,
            'halfTrunc', 'halfFloor' => ($rem * 2) > $increment ? $r2 : $r1,
            'halfEven' => ($rem * 2) < $increment
                ? $r1
                : (($rem * 2) > $increment ? $r2 : (($q % 2) === 0 ? $r1 : $r2)),
            default => throw new InvalidArgumentException("Invalid roundingMode \"{$mode}\"."),
        };
    }

    /**
     * Rounds a nanosecond offset within a day for day-level rounding.
     *
     * Uses the actual day length (which may differ from 86400s due to DST).
     */
    private static function roundDayNs(int $offsetNs, int $dayLengthNs, string $mode): int
    {
        return match ($mode) {
            'trunc', 'floor' => 0,
            'ceil', 'expand' => $offsetNs === 0 ? 0 : $dayLengthNs,
            'halfExpand', 'halfCeil' => ($offsetNs * 2) >= $dayLengthNs ? $dayLengthNs : 0,
            'halfTrunc', 'halfFloor' => ($offsetNs * 2) > $dayLengthNs ? $dayLengthNs : 0,
            'halfEven' => ($offsetNs * 2) < $dayLengthNs ? 0 : (($offsetNs * 2) > $dayLengthNs ? $dayLengthNs : 0),
            default => throw new InvalidArgumentException("Invalid roundingMode \"{$mode}\"."),
        };
    }

    /**
     * Extracts and validates the 'overflow' option.
     *
     * When the key is present, the value must be a string ('constrain'|'reject').
     * null/bool/other types throw.
     *
     * @param array<array-key, mixed>|object|null $options
     */
    // TODO: extractOverflow diverges across PlainDateTime, PlainTime, and ZonedDateTime.
    // ZonedDateTime: any non-string (including null/bool) → InvalidArgumentException with get_debug_type.
    // PlainDateTime: null/bool → InvalidArgumentException; other non-string → TypeError.
    // PlainTime:     null → 'constrain' (default); non-string → TypeError.
    // Unification is unsafe until the spec-correct behavior for each case is confirmed.
    private static function extractOverflow(array|object|null $options): string
    {
        if ($options === null) {
            return 'constrain';
        }
        if (is_object($options)) {
            $options = (array) $options;
        }
        if (!array_key_exists('overflow', $options)) {
            return 'constrain';
        }
        /** @var mixed $val */
        $val = $options['overflow'];
        if (!is_string($val)) {
            throw new InvalidArgumentException(sprintf(
                'overflow option must be a string; got %s.',
                get_debug_type($val),
            ));
        }
        if ($val !== 'constrain' && $val !== 'reject') {
            throw new InvalidArgumentException("Invalid overflow value \"{$val}\": must be 'constrain' or 'reject'.");
        }
        return $val;
    }

    /**
     * Extracts and validates the 'disambiguation' option.
     *
     * @param array<array-key, mixed>|object|null $options
     */
    private static function extractDisambiguation(array|object|null $options): string
    {
        if ($options === null) {
            return 'compatible';
        }
        if (is_object($options)) {
            $options = (array) $options;
        }
        if (!array_key_exists('disambiguation', $options)) {
            return 'compatible';
        }
        /** @var mixed $val */
        $val = $options['disambiguation'];
        if ($val === null) {
            return 'compatible';
        }
        if (!is_string($val)) {
            throw new InvalidArgumentException('ZonedDateTime disambiguation option must be a string.');
        }
        if (!in_array(needle: $val, haystack: ['compatible', 'earlier', 'later', 'reject'], strict: true)) {
            throw new InvalidArgumentException(
                "Invalid disambiguation value \"{$val}\"; must be 'compatible', 'earlier', 'later', or 'reject'.",
            );
        }
        return $val;
    }

    /**
     * Computes the fractional progress for year-level rounding using actual calendar dates.
     *
     * Adds floorYears to the receiver date, then floorYears+increment to get
     * the true interval length, and measures how far the remainder extends.
     *
     * @param array{year:int,month:int,day:int,hour:int,minute:int,second:int,millisecond:int,microsecond:int,nanosecond:int,offsetSec:int,offset:string} $recLocal
     * @param array{year:int,month:int,day:int,hour:int,minute:int,second:int,millisecond:int,microsecond:int,nanosecond:int,offsetSec:int,offset:string} $earlierLocal
     * @param array{year:int,month:int,day:int,hour:int,minute:int,second:int,millisecond:int,microsecond:int,nanosecond:int,offsetSec:int,offset:string} $laterLocal
     */
    private static function calcYearProgress(
        array $recLocal,
        array $earlierLocal,
        array $laterLocal,
        int $floorCount,
        int $increment,
        int $days,
        int $timeDiffNs,
        bool $receiverIsLater,
    ): float {
        $nsPerDayF = 86_400_000_000_000.0;
        if ($receiverIsLater) {
            // Anchor from the later date backward.
            $floorDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                -$floorCount,
                0,
            );
            $nextDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                -($floorCount + $increment),
                0,
            );
            // Remaining: from earlier to the floor anchor.
            $floorJdn = CalendarMath::toJulianDay($floorDate[0], $floorDate[1], $floorDate[2]);
            $earlierJdn = CalendarMath::toJulianDay(
                $earlierLocal['year'],
                $earlierLocal['month'],
                $earlierLocal['day'],
            );
            $remDays = $floorJdn - $earlierJdn;
        } else {
            // Anchor from the earlier date forward.
            $floorDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                $floorCount,
                0,
            );
            $nextDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                $floorCount + $increment,
                0,
            );
            // Remaining: from the floor anchor to the later date.
            $floorJdn = CalendarMath::toJulianDay($floorDate[0], $floorDate[1], $floorDate[2]);
            $laterJdn = CalendarMath::toJulianDay($laterLocal['year'], $laterLocal['month'], $laterLocal['day']);
            $remDays = $laterJdn - $floorJdn;
        }
        $nextJdn = CalendarMath::toJulianDay($nextDate[0], $nextDate[1], $nextDate[2]);
        $intervalDays = abs($nextJdn - $floorJdn);

        $totalRemNs = (float) (($remDays * 86_400_000_000_000) + $timeDiffNs);
        return $intervalDays > 0 ? $totalRemNs / ((float) $intervalDays * $nsPerDayF) : 0.0;
    }

    /**
     * Computes the fractional progress for month-level rounding using actual calendar dates.
     *
     * @param array{year:int,month:int,day:int,hour:int,minute:int,second:int,millisecond:int,microsecond:int,nanosecond:int,offsetSec:int,offset:string} $recLocal
     * @param array{year:int,month:int,day:int,hour:int,minute:int,second:int,millisecond:int,microsecond:int,nanosecond:int,offsetSec:int,offset:string} $earlierLocal
     * @param array{year:int,month:int,day:int,hour:int,minute:int,second:int,millisecond:int,microsecond:int,nanosecond:int,offsetSec:int,offset:string} $laterLocal
     */
    private static function calcMonthProgress(
        array $recLocal,
        array $earlierLocal,
        array $laterLocal,
        int $floorCount,
        int $increment,
        int $days,
        int $timeDiffNs,
        bool $receiverIsLater,
    ): float {
        $nsPerDayF = 86_400_000_000_000.0;
        if ($receiverIsLater) {
            $floorDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                0,
                -$floorCount,
            );
            $nextDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                0,
                -($floorCount + $increment),
            );
            $floorJdn = CalendarMath::toJulianDay($floorDate[0], $floorDate[1], $floorDate[2]);
            $earlierJdn = CalendarMath::toJulianDay(
                $earlierLocal['year'],
                $earlierLocal['month'],
                $earlierLocal['day'],
            );
            $remDays = $floorJdn - $earlierJdn;
        } else {
            $floorDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                0,
                $floorCount,
            );
            $nextDate = self::addYearsMonthsToDate(
                $recLocal['year'],
                $recLocal['month'],
                $recLocal['day'],
                0,
                $floorCount + $increment,
            );
            $floorJdn = CalendarMath::toJulianDay($floorDate[0], $floorDate[1], $floorDate[2]);
            $laterJdn = CalendarMath::toJulianDay($laterLocal['year'], $laterLocal['month'], $laterLocal['day']);
            $remDays = $laterJdn - $floorJdn;
        }
        $nextJdn = CalendarMath::toJulianDay($nextDate[0], $nextDate[1], $nextDate[2]);
        $intervalDays = abs($nextJdn - $floorJdn);

        $totalRemNs = (float) (($remDays * 86_400_000_000_000) + $timeDiffNs);
        return $intervalDays > 0 ? $totalRemNs / ((float) $intervalDays * $nsPerDayF) : 0.0;
    }

    /**
     * Adds years and months to a date, clamping the day to the new month's max.
     *
     * @return array{0:int, 1:int, 2:int} [year, month, day]
     */
    private static function addYearsMonthsToDate(int $year, int $month, int $day, int $addYears, int $addMonths): array
    {
        $newYear = $year + $addYears;
        $newMonth = $month + $addMonths;
        if ($newMonth > 12) {
            $newYear += intdiv(num1: $newMonth - 1, num2: 12);
            $newMonth = (($newMonth - 1) % 12) + 1;
        } elseif ($newMonth < 1) {
            $newYear += intdiv(num1: $newMonth - 12, num2: 12);
            $newMonth = (((($newMonth - 1) % 12) + 12) % 12) + 1;
        }
        $maxDay = CalendarMath::calcDaysInMonth($newYear, $newMonth);
        return [$newYear, $newMonth, min($day, $maxDay)];
    }

    /**
     * Determines whether to round up based on fractional progress within an interval.
     */
    private static function applyRoundingProgress(int $wholeUnits, float $progress, int $increment, string $mode): bool
    {
        $q = intdiv(num1: $wholeUnits, num2: $increment);
        $unitRem = $wholeUnits - ($q * $increment);
        $hasFraction = $unitRem > 0 || $progress > 0.0;
        $halfPoint = (float) $increment / 2.0;
        $totalFrac = (float) $unitRem + $progress;

        return match ($mode) {
            'trunc', 'floor' => false,
            'ceil', 'expand' => $hasFraction,
            'halfExpand', 'halfCeil' => $totalFrac >= $halfPoint,
            'halfTrunc', 'halfFloor' => $totalFrac > $halfPoint,
            'halfEven' => $totalFrac > $halfPoint || $totalFrac === $halfPoint && ($q % 2) !== 0,
            default => false,
        };
    }

    /**
     * Negates directional rounding modes for use on absolute values of negative durations.
     *
     * Symmetric modes (trunc, expand, halfTrunc, halfExpand, halfEven) are unchanged.
     */
    private static function negateRoundingMode(string $mode): string
    {
        return match ($mode) {
            'floor' => 'ceil',
            'ceil' => 'floor',
            'halfFloor' => 'halfCeil',
            'halfCeil' => 'halfFloor',
            default => $mode,
        };
    }
}
