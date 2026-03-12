<?php

declare(strict_types=1);

namespace Temporal;

use InvalidArgumentException;
use Stringable;

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
    private const int NS_PER_SECOND      = 1_000_000_000;
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

    /** @var array{year:int, month:int, day:int, hour:int, minute:int, second:int, millisecond:int, microsecond:int, nanosecond:int, offsetSec:int, offset:string}|null $localCache */
    private ?array $localCache = null;

    // -------------------------------------------------------------------------
    // Virtual (get-only) date/time properties
    // -------------------------------------------------------------------------

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $year { get => $this->localComponents()['year']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $month { get => $this->localComponents()['month']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $day { get => $this->localComponents()['day']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $hour { get => $this->localComponents()['hour']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $minute { get => $this->localComponents()['minute']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $second { get => $this->localComponents()['second']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $millisecond { get => $this->localComponents()['millisecond']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $microsecond { get => $this->localComponents()['microsecond']; }

    /**
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $nanosecond { get => $this->localComponents()['nanosecond']; }

    /**
     * Milliseconds since the Unix epoch (floor-divided from nanoseconds).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $epochMilliseconds { get => self::floorDiv($this->epochNanoseconds, self::NS_PER_MILLISECOND); }

    /**
     * The UTC offset string for this instant in this timezone (e.g. '+05:30').
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $offset { get => $this->localComponents()['offset']; }

    /**
     * The UTC offset in nanoseconds for this instant in this timezone.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $offsetNanoseconds { get => $this->localComponents()['offsetSec'] * self::NS_PER_SECOND; }

    // -------------------------------------------------------------------------
    // Virtual calendar properties
    // -------------------------------------------------------------------------

    /**
     * Always undefined (null) for the ISO 8601 calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?string $era { get => null; }

    /**
     * Always undefined (null) for the ISO 8601 calendar.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public ?int $eraYear { get => null; }

    /**
     * Month code in "M01"–"M12" format.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public string $monthCode { get => sprintf('M%02d', $this->month); }

    /**
     * ISO 8601 day of week: 1 = Monday, 7 = Sunday.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $dayOfWeek { get => self::isoWeekday($this->year, $this->month, $this->day); }

    /**
     * Ordinal day of the year: 1–366.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $dayOfYear { get => self::calcDayOfYear($this->year, $this->month, $this->day); }

    /**
     * ISO 8601 week number: 1–53.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $weekOfYear { get => self::isoWeekInfo($this->year, $this->month, $this->day)['week']; }

    /**
     * ISO 8601 week-year (may differ from calendar year near year boundaries).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $yearOfWeek { get => self::isoWeekInfo($this->year, $this->month, $this->day)['year']; }

    /**
     * Number of days in this date's month.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInMonth { get => self::calcDaysInMonth($this->year, $this->month); }

    /**
     * Always 7 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInWeek { get => 7; }

    /**
     * 365 or 366, depending on whether this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $daysInYear { get => self::isLeapYear($this->year) ? 366 : 365; }

    /**
     * Always 12 (ISO 8601 calendar).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $monthsInYear { get => 12; }

    /**
     * True if this date's year is a leap year.
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public bool $inLeapYear { get => self::isLeapYear($this->year); }

    /**
     * Number of hours in the current day (always 24 for UTC/fixed-offset timezones).
     *
     * @psalm-suppress PropertyNotSetInConstructor — virtual property (get-only hook, no backing store)
     * @psalm-suppress PossiblyUnusedProperty — accessed externally via test262 scripts
     * @psalm-api
     */
    public int $hoursInDay { get => 24; }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param int|float $epochNanoseconds Nanoseconds since the Unix epoch. Must be a
     *        finite integer value within the PHP int64 range.
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
            if ($epochNanoseconds > (float) PHP_INT_MAX || $epochNanoseconds < (float) PHP_INT_MIN) {
                throw new InvalidArgumentException('ZonedDateTime epochNanoseconds value exceeds the PHP int64 range.');
            }
            $epochNanoseconds = (int) $epochNanoseconds;
        }
        $this->epochNanoseconds = $epochNanoseconds;
        $this->timeZoneId = self::normalizeTimezoneId($timeZoneId, true);
        if (strtolower($calendarId) !== 'iso8601') {
            throw new InvalidArgumentException(
                "Unsupported calendar \"{$calendarId}\": only iso8601 is supported.",
            );
        }
        $this->calendarId = 'iso8601';
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
     * @param mixed $item    ZonedDateTime, ISO string, or property-bag array/object.
     * @param mixed $options Options array; supports 'disambiguation' (string).
     * @throws \TypeError              for unsupported types.
     * @throws InvalidArgumentException for invalid strings or property bags.
     * @psalm-api
     */
    public static function from(mixed $item, mixed $options = null): self
    {
        // Validate 'disambiguation' option if present.
        if (is_array($options) && array_key_exists('disambiguation', $options)) {
            /** @var mixed $dv */
            $dv = $options['disambiguation'];
            if (!is_string($dv)) {
                throw new InvalidArgumentException(
                    'ZonedDateTime::from() disambiguation option must be a string.',
                );
            }
            if (!in_array(needle: $dv, haystack: ['compatible', 'earlier', 'later', 'reject'], strict: true)) {
                throw new InvalidArgumentException(
                    "Invalid disambiguation value \"{$dv}\"; must be 'compatible', 'earlier', 'later', or 'reject'.",
                );
            }
        }

        if ($item instanceof self) {
            return new self($item->epochNanoseconds, $item->timeZoneId, $item->calendarId);
        }
        if (is_string($item)) {
            return self::parseZdtString($item, $options);
        }
        if (is_array($item) || is_object($item)) {
            $bag = is_array($item) ? $item : (array) $item;
            return self::fromPropertyBag($bag);
        }
        throw new \TypeError(
            'ZonedDateTime::from() requires a ZonedDateTime, string, or property-bag array; got '
            . get_debug_type($item) . '.',
        );
    }

    /**
     * Compares two ZonedDateTimes by their epoch nanoseconds.
     *
     * @param mixed $one ZonedDateTime or value coercible via from().
     * @param mixed $two ZonedDateTime or value coercible via from().
     * @return int -1, 0, or 1.
     * @psalm-api
     */
    public static function compare(mixed $one, mixed $two): int
    {
        $a = $one instanceof self ? $one : self::from($one);
        $b = $two instanceof self ? $two : self::from($two);
        return $a->epochNanoseconds <=> $b->epochNanoseconds;
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
        return new PlainDate($this->year, $this->month, $this->day);
    }

    /**
     * Returns a PlainTime containing the local time in this timezone.
     *
     * @psalm-api
     */
    public function toPlainTime(): PlainTime
    {
        return new PlainTime($this->hour, $this->minute, $this->second, $this->millisecond, $this->microsecond, $this->nanosecond);
    }

    /**
     * Returns a PlainDateTime containing the local date and time in this timezone.
     *
     * @psalm-api
     */
    public function toPlainDateTime(): PlainDateTime
    {
        return new PlainDateTime(
            $this->year, $this->month, $this->day,
            $this->hour, $this->minute, $this->second,
            $this->millisecond, $this->microsecond, $this->nanosecond,
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
    public function withTimeZone(mixed $timeZone): self
    {
        if (!is_string($timeZone)) {
            throw new \TypeError('ZonedDateTime::withTimeZone() timeZone must be a string.');
        }
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
     * @throws \TypeError              if $calendar is not a string.
     * @throws InvalidArgumentException if an unsupported calendar is given.
     * @psalm-api
     */
    public function withCalendar(mixed $calendar): self
    {
        if (!is_string($calendar)) {
            throw new \TypeError('ZonedDateTime::withCalendar() calendar must be a string.');
        }
        self::extractCalendarFromString($calendar); // validates; throws if unsupported
        return new self($this->epochNanoseconds, $this->timeZoneId, 'iso8601');
    }

    /**
     * Returns a new ZonedDateTime with the time portion replaced.
     *
     * If $time is null the time is set to midnight (00:00:00).
     * Accepts PlainTime, null, a time string, or a property-bag array.
     *
     * @param mixed $time PlainTime, null, string, or array.
     * @psalm-api
     */
    public function withPlainTime(mixed $time = null): self
    {
        if ($time === null) {
            $h = $m = $s = $ms = $us = $ns = 0;
        } elseif ($time instanceof PlainTime) {
            $h  = $time->hour;
            $m  = $time->minute;
            $s  = $time->second;
            $ms = $time->millisecond;
            $us = $time->microsecond;
            $ns = $time->nanosecond;
        } else {
            $pt = PlainTime::from($time);
            $h  = $pt->hour;
            $m  = $pt->minute;
            $s  = $pt->second;
            $ms = $pt->millisecond;
            $us = $pt->microsecond;
            $ns = $pt->nanosecond;
        }

        // Compute the local wall-clock seconds for the new datetime using the existing date.
        try {
            $wallDt = new \DateTimeImmutable(sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d+00:00',
                $this->year, $this->month, $this->day,
                $h, $m, $s,
            ));
        } catch (\Exception) {
            throw new InvalidArgumentException('ZonedDateTime::withPlainTime() could not construct datetime.');
        }
        $wallSec = $wallDt->getTimestamp();

        // Determine the timezone offset at this new wall-clock second.
        // For a fixed offset timezone we can use it directly; for IANA we need
        // to do a wall-clock → UTC conversion.
        $epochSec = self::wallSecToEpochSec($wallSec, $this->timeZoneId);

        $subNs = $ms * self::NS_PER_MILLISECOND + $us * self::NS_PER_MICROSECOND + $ns;
        $maxSecForNs = 9_223_372_035;
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            $epochNs = $epochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX;
        } else {
            $epochNs = $epochSec * self::NS_PER_SECOND + $subNs;
        }

        return new self($epochNs, $this->timeZoneId, $this->calendarId);
    }

    /**
     * Returns true if this ZonedDateTime represents the same instant, timezone, and calendar.
     *
     * @param mixed $other ZonedDateTime, string, or array.
     * @throws \TypeError for unsupported types.
     * @psalm-api
     */
    public function equals(mixed $other): bool
    {
        if (!$other instanceof self) {
            if (is_string($other) || is_array($other)) {
                $other = self::from($other);
            } else {
                throw new \TypeError(
                    'ZonedDateTime::equals() requires a ZonedDateTime, string, or array; got '
                    . get_debug_type($other) . '.',
                );
            }
        }
        return $this->epochNanoseconds === $other->epochNanoseconds
            && $this->timeZoneId === $other->timeZoneId
            && $this->calendarId === $other->calendarId;
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
     * @param mixed $options null, array, or object (treated as empty bag).
     * @throws \TypeError              if option values have wrong types.
     * @throws InvalidArgumentException if option values are invalid strings.
     * @psalm-api
     */
    public function toString(mixed $options = null): string
    {
        if (is_object($options)) {
            $options = [];
        } elseif ($options !== null && !is_array($options)) {
            throw new \TypeError('ZonedDateTime::toString() options must be null, an array, or an object.');
        }

        $digits        = -2; // -2 = 'auto'
        $offsetMode    = 'auto';
        $tzNameMode    = 'auto';
        $calendarName  = 'auto';
        $isMinute      = false;
        $roundMode     = 'trunc';

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
                            throw new InvalidArgumentException("fractionalSecondDigits must be 'auto' or a finite integer 0–9.");
                        }
                        $fsd = (int) floor($fsd);
                    } elseif (!is_int($fsd)) {
                        throw new InvalidArgumentException("fractionalSecondDigits must be 'auto' or an integer 0–9.");
                    }
                    if ($fsd < 0 || $fsd > 9) {
                        throw new InvalidArgumentException("fractionalSecondDigits {$fsd} is out of range (must be 0–9).");
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
        $epochSec = self::floorDiv($roundedNs, self::NS_PER_SECOND);
        $maxSecForNsCheck = 9_223_372_035;
        if ($epochSec > $maxSecForNsCheck || $epochSec < -$maxSecForNsCheck) {
            $roundedSubNs = 0;
        } else {
            $roundedSubNs = $roundedNs - $epochSec * self::NS_PER_SECOND; // 0–999_999_999
        }

        $offsetSec = $this->resolveOffsetSecondsAt($epochSec);
        $localSec  = $epochSec + $offsetSec;
        $dt        = new \DateTimeImmutable('@' . $localSec);

        $year  = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $day   = (int) $dt->format('j');
        $hour  = (int) $dt->format('G');
        $min   = (int) $dt->format('i');
        $sec   = (int) $dt->format('s');

        $ms = intdiv(num1: $roundedSubNs, num2: self::NS_PER_MILLISECOND);
        $us = intdiv(num1: $roundedSubNs % self::NS_PER_MILLISECOND, num2: self::NS_PER_MICROSECOND);
        $ns = $roundedSubNs % self::NS_PER_MICROSECOND;

        // Build offset string: ±HH:MM.
        $absOffsetSec = abs($offsetSec);
        $offH = intdiv(num1: $absOffsetSec, num2: 3600);
        $offM = intdiv(num1: $absOffsetSec % 3600, num2: 60);
        $offSign = $offsetSec >= 0 ? '+' : '-';
        $offsetStr = sprintf('%s%02d:%02d', $offSign, $offH, $offM);

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
        $subNs = $ms * self::NS_PER_MILLISECOND + $us * self::NS_PER_MICROSECOND + $ns;

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

        $result = $datePart . 'T' . $timePart;

        if ($offsetMode !== 'never') {
            $result .= $offsetStr;
        }

        if ($tzNameMode !== 'never') {
            if ($tzNameMode === 'critical') {
                $result .= '[!' . $this->timeZoneId . ']';
            } else {
                $result .= '[' . $this->timeZoneId . ']';
            }
        }

        if ($calendarName === 'always') {
            $result .= '[u-ca=' . $this->calendarId . ']';
        } elseif ($calendarName === 'critical') {
            $result .= '[!u-ca=' . $this->calendarId . ']';
        } elseif ($calendarName === 'auto' && $this->calendarId !== 'iso8601') { // @phpstan-ignore booleanAnd.alwaysFalse, notIdentical.alwaysFalse
            $result .= '[u-ca=' . $this->calendarId . ']';
        }
        // 'never': omit calendar annotation entirely.

        return $result;
    }

    /** @psalm-api */
    public function toJSON(): string
    {
        return $this->toString();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
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
     * Normalizes a timezone identifier string to a canonical form.
     *
     * 'UTC' (case-insensitive) → 'UTC'.
     * '±HH:MM' → kept as-is.
     * '±HHMM'  → '±HH:MM'.
     * '±HH'    → '±HH:00'.
     * Datetime strings → extract timezone (same as Instant::parseTimeZoneId).
     * IANA names → validate via DateTimeZone; return as-is.
     *
     * @throws InvalidArgumentException if the timezone is empty or unrecognized.
     */
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
     * @psalm-suppress UnusedReturnValue — callers invoke this only for validation (side-effects/throws)
     */
    private static function extractCalendarFromString(string $s): string
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
            $calId = strtolower($m[1]);
            if ($calId !== 'iso8601') {
                throw new InvalidArgumentException("Unsupported calendar \"{$m[1]}\": only iso8601 is supported.");
            }
            return 'iso8601';
        }
        // ISO date/datetime strings → iso8601 (check BEFORE time-only, to avoid ambiguity).
        // Match: date patterns (YYYY-MM, MM-DD, ±YYYYYY-) or datetime T-separator after digits.
        if (preg_match('/^\d{2}-\d{2}|^\d{4}-\d{2}|^[+-]\d{6}-/', $s) === 1
            || preg_match('/\d[Tt]\d/', $s) === 1
        ) {
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
        if (preg_match('/^\d{4,6}(?:[.,]|\+|$)/', $s) === 1
            || preg_match('/^\d{4,6}-(?!\d{2}-)/', $s) === 1
        ) {
            return 'iso8601';
        }
        // Plain calendar ID.
        if (strtolower($s) === 'iso8601') {
            return 'iso8601';
        }
        throw new InvalidArgumentException("Unsupported calendar \"{$s}\": only iso8601 is supported.");
    }

    private static function normalizeTimezoneId(string $id, bool $rejectDatetimeStrings = false): string
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
            return $m[1] . $m[2] . ':' . $m[3];
        }
        // ±HH → ±HH:00
        if (preg_match('/^([+\-])(\d{2})$/', $id, $m) === 1) {
            return $m[1] . $m[2] . ':00';
        }
        // Sub-minute offsets → reject.
        if (preg_match('/^[+\-]\d{2}:\d{2}[:.].*/i', $id) === 1) {
            throw new InvalidArgumentException(
                "Invalid timeZoneId \"{$id}\": sub-minute offset is not a valid timezone identifier.",
            );
        }

        // IANA timezone name: validate via PHP DateTimeZone.
        try {
            new \DateTimeZone($id);
            return $id;
        } catch (\Exception) {
            throw new InvalidArgumentException(
                "Invalid timeZoneId \"{$id}\": not a recognized timezone identifier.",
            );
        }
    }

    /**
     * Computes (and caches) all local date/time components for this instant in the stored timezone.
     *
     * @return array{year:int, month:int, day:int, hour:int, minute:int, second:int, millisecond:int, microsecond:int, nanosecond:int, offsetSec:int, offset:string}
     * @psalm-suppress UnusedMethod — called from PHP 8.4 property hooks that Psalm does not track
     */
    private function localComponents(): array
    {
        if ($this->localCache !== null) {
            return $this->localCache;
        }

        $epochNs  = $this->epochNanoseconds;
        $epochSec = self::floorDiv($epochNs, self::NS_PER_SECOND);
        // Guard against int64 overflow when computing the sub-second remainder.
        // Sentinel epoch values (PHP_INT_MIN/MAX) correspond to dates outside the int64
        // nanosecond range; their sub-second part is not representable exactly.
        $maxSecForNs = 9_223_372_035;
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            $subNs = 0;
        } else {
            $subNs = $epochNs - $epochSec * self::NS_PER_SECOND; // always 0–999_999_999
        }

        $offsetSec = $this->resolveOffsetSecondsAt($epochSec);
        $localSec  = $epochSec + $offsetSec;

        // Create a UTC DateTimeImmutable at local seconds to extract Y/m/d H:i:s.
        $dt = new \DateTimeImmutable('@' . $localSec);

        $year   = (int) $dt->format('Y');
        $month  = (int) $dt->format('n');
        $day    = (int) $dt->format('j');
        $hour   = (int) $dt->format('G');
        $minute = (int) $dt->format('i');
        $second = (int) $dt->format('s');

        $ms = intdiv(num1: $subNs, num2: self::NS_PER_MILLISECOND);
        $us = intdiv(num1: $subNs % self::NS_PER_MILLISECOND, num2: self::NS_PER_MICROSECOND);
        $ns = $subNs % self::NS_PER_MICROSECOND;

        // Build offset string: ±HH:MM.
        $absOffsetSec = abs($offsetSec);
        $offH = intdiv(num1: $absOffsetSec, num2: 3600);
        $offM = intdiv(num1: $absOffsetSec % 3600, num2: 60);
        $offSign = $offsetSec >= 0 ? '+' : '-';
        $offsetStr = sprintf('%s%02d:%02d', $offSign, $offH, $offM);

        $this->localCache = [
            'year'        => $year,
            'month'       => $month,
            'day'         => $day,
            'hour'        => $hour,
            'minute'      => $minute,
            'second'      => $second,
            'millisecond' => $ms,
            'microsecond' => $us,
            'nanosecond'  => $ns,
            'offsetSec'   => $offsetSec,
            'offset'      => $offsetStr,
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
            return $sign * ((int) $m[2] * 3600 + (int) $m[3] * 60);
        }
        // IANA timezone: use PHP to find the offset at the given instant.
        /** @psalm-suppress ArgumentTypeCoercion — timeZoneId is validated to be non-empty in constructor */
        $tz = new \DateTimeZone($this->timeZoneId);
        return $tz->getOffset(new \DateTimeImmutable('@' . $epochSec));
    }

    /**
     * Parses a ZonedDateTime ISO string (with required bracket timezone annotation).
     *
     * @param mixed $options Options from from() (may contain 'offset' key).
     * @throws InvalidArgumentException if the string is invalid.
     */
    private static function parseZdtString(string $text, mixed $options = null): self
    {
        // Resolve the 'offset' option (default: 'reject').
        $offsetOption = 'reject';
        if (is_array($options) && array_key_exists('offset', $options)) {
            /** @var mixed $ov */
            $ov = $options['offset'];
            if (is_string($ov) && in_array(needle: $ov, haystack: ['use', 'ignore', 'prefer', 'reject'], strict: true)) {
                $offsetOption = $ov;
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
        $patternExtDateExtTime =
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2})'
            . '[T ]'
            . '(\d{2})(?::(\d{2})(?::(\d{2}))?)?'
            . '([.,]\d+)?'
            . '(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?'
            . '((?:\[[^\]]*\])+)'
            . '$/i';
        // Extended date + compact time
        $patternExtDateCptTime =
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2})'
            . '[T ]'
            . '(\d{2})(\d{2})(\d{2})?'
            . '([.,]\d+)?'
            . '(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?'
            . '((?:\[[^\]]*\])+)'
            . '$/i';
        // Compact date + extended time
        $patternCptDateExtTime =
            '/^([+-]\d{6}|\d{4})(\d{4})'
            . '[T ]'
            . '(\d{2})(?::(\d{2})(?::(\d{2}))?)?'
            . '([.,]\d+)?'
            . '(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?'
            . '((?:\[[^\]]*\])+)'
            . '$/i';
        // Compact date + compact time
        $patternCptDateCptTime =
            '/^([+-]\d{6}|\d{4})(\d{4})'
            . '[T ]'
            . '(\d{2})(\d{2})(\d{2})?'
            . '([.,]\d+)?'
            . '(Z|[+-]\d{2}(?::\d{2}(?::\d{2}(?:[.,]\d+)?)?|\d{2}(?:\d{2}(?:[.,]\d+)?)?)?)?'
            . '((?:\[[^\]]*\])+)'
            . '$/i';

        // Date-only pattern: YYYY-MM-DD[tzAnnotation] (no time part; defaults to midnight).
        $dateOnlyPattern =
            '/^([+-]\d{6}|\d{4})(-\d{2}-\d{2}|\d{4})'
            . '((?:\[[^\]]*\])+)'
            . '$/i';

        /** @var list<string> $m */
        $m = [];
        $matched = false;
        foreach ([
            $patternExtDateExtTime, $patternExtDateCptTime,
            $patternCptDateExtTime, $patternCptDateCptTime,
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
        }

        [, $yearRaw, $dateRest, $hourStr, $minStr, $secStr, $fractionRaw, $offsetRaw, $annotationSection] = $m;

        // Normalize compact date rest.
        if (!str_starts_with($dateRest, '-')) {
            $dateRest =
                '-'
                . substr(string: $dateRest, offset: 0, length: 2)
                . '-'
                . substr(string: $dateRest, offset: 2, length: 2);
        }

        $yearNum  = (int) $yearRaw;
        // Reject minus-zero year.
        if ($yearNum === 0 && str_starts_with($yearRaw, '-')) {
            throw new InvalidArgumentException(
                "Invalid ZonedDateTime string \"{$text}\": year -000000 (negative zero) is not valid.",
            );
        }

        $monthNum = (int) substr(string: $dateRest, offset: 1, length: 2);
        $dayNum   = (int) substr(string: $dateRest, offset: 4, length: 2);
        $hourNum  = (int) $hourStr;
        $minNum   = (int) $minStr;
        $secNum   = $secStr !== '' ? (int) $secStr : 0;

        if ($monthNum < 1 || $monthNum > 12) {
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$text}\": month out of range.");
        }
        $maxDay = self::calcDaysInMonth($yearNum, $monthNum);
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
        $sec60     = $secNum === 60;
        $normalSec = $sec60 ? 59 : $secNum;
        if (!$sec60 && $secNum > 59) {
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$text}\": second out of range.");
        }

        // Extract the timezone from bracket annotations.
        $tzId = self::extractTzFromAnnotations($annotationSection, $text);

        // Parse inline offset if present.
        $hasInlineOffset = $offsetRaw !== '';
        $inlineOffsetSec = 0;
        if ($hasInlineOffset) {
            [$inlineSign, $inlineAbsSec] = self::parseSimpleOffset($offsetRaw);
            $inlineOffsetSec = $inlineSign * $inlineAbsSec;
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
            // Inline offset present: epochSec = wallSec - inlineOffsetSec.
            $epochSec = $wallSec - $inlineOffsetSec;
            // Verify that the stated timezone agrees at this epoch second.
            $normalizedTzId = self::normalizeTimezoneId($tzId);
            $actualOffsetSec = self::staticResolveOffset($epochSec, $normalizedTzId);
            if ($actualOffsetSec !== $inlineOffsetSec) {
                throw new InvalidArgumentException(
                    "Invalid ZonedDateTime string \"{$text}\": inline offset does not match timezone offset.",
                );
            }
            $tzId = $normalizedTzId;
        } else {
            // No inline offset: convert wall clock to UTC via the timezone.
            $normalizedTzId = self::normalizeTimezoneId($tzId);
            $epochSec = self::wallSecToEpochSec($wallSec, $normalizedTzId);
            $tzId = $normalizedTzId;
        }

        // Validate spec range.
        $maxSec = 8_640_000_000_000;
        if ($epochSec < -$maxSec || $epochSec > $maxSec || ($epochSec === $maxSec && $subNs > 0)) {
            throw new InvalidArgumentException(
                "ZonedDateTime string \"{$text}\" is outside the representable nanosecond range.",
            );
        }

        // Guard int64 overflow.
        $maxSecForNs = 9_223_372_035;
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            $epochNs = $epochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX;
        } else {
            $epochNs = $epochSec * self::NS_PER_SECOND + $subNs;
        }

        return new self($epochNs, $tzId);
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
    private static function fromPropertyBag(array $bag): self
    {
        // Validate calendar first (spec validates calendar before required fields).
        if (array_key_exists('calendar', $bag)) {
            /** @var mixed $calRaw */
            $calRaw = $bag['calendar'];
            if (!is_string($calRaw)) {
                throw new \TypeError('ZonedDateTime calendar must be a string.');
            }
            self::extractCalendarFromString($calRaw); // throws for invalid calendars
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
            return new self((is_int($ensRaw) ? $ensRaw : (int) $ensRaw), $tzRaw);
        }

        // Otherwise expect year/month/day/hour/minute/second fields.
        foreach (['year', 'day'] as $field) {
            if (!array_key_exists($field, $bag)) {
                throw new \TypeError("ZonedDateTime property bag must have a {$field} field.");
            }
        }

        // month can come from 'month' or 'monthCode'.
        if (!array_key_exists('month', $bag) && !array_key_exists('monthCode', $bag)) {
            throw new \TypeError('ZonedDateTime property bag must have a month or monthCode field.');
        }

        /** @var mixed $yr */
        $yr = $bag['year'];
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
        /** @psalm-suppress MixedAssignment — array values are all typed as mixed via @var annotations above */
        foreach ([
            'year' => $yr, 'day' => $dy, 'hour' => $hr, 'minute' => $mn,
            'second' => $sc, 'millisecond' => $ms, 'microsecond' => $us, 'nanosecond' => $ns,
        ] as $fname => $fval) {
            if (is_float($fval) && is_infinite($fval)) {
                throw new InvalidArgumentException(
                    "ZonedDateTime {$fname} must be finite; got " . ($fval > 0 ? 'INF' : '-INF') . '.',
                );
            }
        }

        /** @phpstan-ignore cast.int */
        $year   = is_int($yr) ? $yr : (int) $yr;
        /** @phpstan-ignore cast.int */
        $day    = is_int($dy) ? $dy : (int) $dy;
        /** @phpstan-ignore cast.int */
        $hour   = is_int($hr) ? $hr : (int) $hr;
        /** @phpstan-ignore cast.int */
        $minute = is_int($mn) ? $mn : (int) $mn;
        /** @phpstan-ignore cast.int */
        $second = is_int($sc) ? $sc : (int) $sc;
        /** @phpstan-ignore cast.int */
        $milli  = is_int($ms) ? $ms : (int) $ms;
        /** @phpstan-ignore cast.int */
        $micro  = is_int($us) ? $us : (int) $us;
        /** @phpstan-ignore cast.int */
        $nano   = is_int($ns) ? $ns : (int) $ns;

        // Resolve month from 'month' or 'monthCode'.
        if (array_key_exists('month', $bag)) {
            /** @var mixed $mo */
            $mo = $bag['month'];
            if (is_float($mo) && is_infinite($mo)) {
                throw new InvalidArgumentException(
                    'ZonedDateTime month must be finite; got ' . ($mo > 0 ? 'INF' : '-INF') . '.',
                );
            }
            /** @phpstan-ignore cast.int */
            $month = is_int($mo) ? $mo : (int) $mo;
        } else {
            /** @var mixed $mc */
            $mc = $bag['monthCode'];
            if (!is_string($mc)) {
                throw new \TypeError('ZonedDateTime monthCode must be a string.');
            }
            if (preg_match('/^M(0[1-9]|1[0-2])$/', $mc, $mcm) !== 1) {
                throw new InvalidArgumentException("Invalid monthCode \"{$mc}\": must be M01–M12.");
            }
            $month = (int) $mcm[1];
        }

        try {
            $wallDt = new \DateTimeImmutable(sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d+00:00',
                $year, $month, $day, $hour, $minute, $second,
            ));
        } catch (\Exception) {
            throw new InvalidArgumentException('ZonedDateTime::from() could not construct datetime from property bag.');
        }

        $wallSec   = $wallDt->getTimestamp();
        // ISODateTimeWithinLimits check.
        if ($wallSec > 8_640_000_000_000 || $wallSec < -8_640_000_000_000) {
            throw new InvalidArgumentException('ZonedDateTime property bag: local date-time is outside the representable range.');
        }

        $normalTzId = self::normalizeTimezoneId($tzRaw);
        $epochSec  = self::wallSecToEpochSec($wallSec, $normalTzId);
        $subNs     = $milli * self::NS_PER_MILLISECOND + $micro * self::NS_PER_MICROSECOND + $nano;

        // Validate 'offset' field if provided.
        if (array_key_exists('offset', $bag)) {
            /** @var mixed $offRaw */
            $offRaw = $bag['offset'];
            if (!is_string($offRaw)) {
                throw new \TypeError('ZonedDateTime offset must be a string.');
            }
            // Valid format: ±HH:MM exactly.
            if (preg_match('/^[+-]\d{2}:\d{2}$/', $offRaw) !== 1) {
                throw new InvalidArgumentException("Invalid offset string \"{$offRaw}\": must be ±HH:MM.");
            }
            $offSign = $offRaw[0] === '+' ? 1 : -1;
            $offParts = explode(separator: ':', string: substr(string: $offRaw, offset: 1));
            $givenOffsetSec = $offSign * ((int) $offParts[0] * 3600 + (int) $offParts[1] * 60);
            $expectedOffsetSec = self::staticResolveOffset($epochSec, $normalTzId);
            if ($givenOffsetSec !== $expectedOffsetSec) {
                throw new InvalidArgumentException(
                    "The offset {$offRaw} does not match the timezone {$normalTzId} offset at the given instant.",
                );
            }
        }

        $maxSecForNs = 9_223_372_035;
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            $epochNs = $epochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX;
        } else {
            $epochNs = $epochSec * self::NS_PER_SECOND + $subNs;
        }

        return new self($epochNs, $normalTzId);
    }

    /**
     * Extracts the timezone identifier from the bracket annotation section.
     *
     * The FIRST bracket without '=' is the timezone annotation. Key-value brackets
     * (with '=') are metadata (e.g. [u-ca=iso8601]).
     *
     * @throws InvalidArgumentException if no timezone annotation is found.
     */
    private static function extractTzFromAnnotations(string $section, string $original): string
    {
        preg_match_all('/\[(!?)([^\]]*)\]/', $section, $matches, PREG_SET_ORDER);

        $tzId = null;
        $tzCount = 0;
        $calCount = 0;
        $calHasCritical = false;

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

        return $tzId;
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
    private static function wallSecToEpochSec(int $wallSec, string $tzId): int
    {
        if ($tzId === 'UTC') {
            return $wallSec;
        }
        // Fixed offset ±HH:MM.
        if (preg_match('/^([+\-])(\d{2}):(\d{2})$/', $tzId, $m) === 1) {
            $sign = $m[1] === '+' ? 1 : -1;
            $offsetSec = $sign * ((int) $m[2] * 3600 + (int) $m[3] * 60);
            return $wallSec - $offsetSec;
        }
        // IANA: approximate by getting the offset at the presumed UTC time, then adjust.
        // Use a two-pass approach to handle DST transitions accurately.
        /** @psalm-suppress ArgumentTypeCoercion — $tzId is validated non-empty before this call */
        $tz = new \DateTimeZone($tzId);
        // First approximation: use wallSec as UTC.
        $approxOffset = $tz->getOffset(new \DateTimeImmutable('@' . $wallSec));
        $approxEpoch  = $wallSec - $approxOffset;
        // Second pass: refine with the actual offset at the approximation.
        $finalOffset = $tz->getOffset(new \DateTimeImmutable('@' . $approxEpoch));
        return $wallSec - $finalOffset;
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
            return $sign * ((int) $m[2] * 3600 + (int) $m[3] * 60);
        }
        /** @psalm-suppress ArgumentTypeCoercion — $tzId is validated non-empty before this call */
        $tz = new \DateTimeZone($tzId);
        return $tz->getOffset(new \DateTimeImmutable('@' . $epochSec));
    }

    // -------------------------------------------------------------------------
    // Calendar helpers (copied from PlainDate.php)
    // -------------------------------------------------------------------------

    private static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    private static function calcDaysInMonth(int $year, int $month): int
    {
        return match ($month) {
            1, 3, 5, 7, 8, 10, 12 => 31,
            4, 6, 9, 11 => 30,
            2 => self::isLeapYear($year) ? 29 : 28,
            default => 0,
        };
    }

    private static function isoWeekday(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $t */
        static $t = [0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4];
        if ($month < 3) {
            $year--;
        }
        $dow = ($year
            + intdiv(num1: $year, num2: 4)
            - intdiv(num1: $year, num2: 100)
            + intdiv(num1: $year, num2: 400)
            + $t[$month - 1]
            + $day) % 7;
        return $dow === 0 ? 7 : $dow;
    }

    private static function calcDayOfYear(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $cumDays */
        static $cumDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $result = $cumDays[$month - 1] + $day;
        if ($month > 2 && self::isLeapYear($year)) {
            $result++;
        }
        return $result;
    }

    /**
     * Returns the ISO 8601 week number and week-year for the given date.
     *
     * @return array{week: int, year: int}
     * @psalm-suppress UnusedMethod — called from weekOfYear and yearOfWeek property hooks
     */
    private static function isoWeekInfo(int $year, int $month, int $day): array
    {
        $dow     = self::isoWeekday($year, $month, $day);
        $ordinal = self::calcDayOfYear($year, $month, $day);

        $thursdayOrdinal = $ordinal + (4 - $dow);

        if ($thursdayOrdinal < 1) {
            $prevYear  = $year - 1;
            $dec31Dow  = self::isoWeekday($prevYear, 12, 31);
            $dec31Ord  = self::isLeapYear($prevYear) ? 366 : 365;
            $prevWeek  = intdiv(num1: $dec31Ord + (4 - $dec31Dow) - 1, num2: 7) + 1;
            return ['week' => $prevWeek, 'year' => $prevYear];
        }

        $yearDays = self::isLeapYear($year) ? 366 : 365;
        if ($thursdayOrdinal > $yearDays) {
            return ['week' => 1, 'year' => $year + 1];
        }

        $week = intdiv(num1: $thursdayOrdinal - 1, num2: 7) + 1;
        return ['week' => $week, 'year' => $year];
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

    private static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        $r = $a - ($q * $b);
        return $r < 0 ? $q - 1 : $q;
    }

    // -------------------------------------------------------------------------
    // Helpers copied from Instant.php
    // -------------------------------------------------------------------------

    private static function parseFraction(string $fractionRaw): int
    {
        $digits = substr($fractionRaw, offset: 1);
        return (int) str_pad(substr($digits, offset: 0, length: 9), length: 9, pad_string: '0');
    }

    /**
     * Parses an offset string into [sign, absSec, fracNs].
     *
     * @return array{int, int, int}  [sign (+1|-1), absSec, fracNs]
     * @throws InvalidArgumentException if the offset is out of range.
     */
    private static function parseOffset(string $offset, string $original): array
    {
        if ($offset === 'Z' || $offset === 'z') {
            return [1, 0, 0];
        }

        $sign = $offset[0] === '+' ? 1 : -1;
        $rest = substr(string: $offset, offset: 1);

        $hours   = (int) substr(string: $rest, offset: 0, length: 2);
        $rest    = substr(string: $rest, offset: 2);
        $minutes = 0;
        $seconds = 0;
        $fracNs  = 0;

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
            throw new InvalidArgumentException("Invalid ZonedDateTime string \"{$original}\": UTC offset out of range.");
        }

        return [$sign, $absSec, $fracNs];
    }
}
