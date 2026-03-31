<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

use InvalidArgumentException;
use Temporal\Spec\Internal\Calendar\CalendarFactory;

/** @internal */
final class CalendarMath
{
    /** @var list<string> */
    public const ROUNDING_MODES = [
        'ceil',
        'floor',
        'expand',
        'trunc',
        'halfCeil',
        'halfFloor',
        'halfExpand',
        'halfTrunc',
        'halfEven',
    ];

    /**
     * Extracts an optional int field from a property bag, returning $default if absent.
     *
     * @param array<array-key, mixed> $bag
     * @param non-empty-string $field
     * @param non-empty-string $className Used in error messages (e.g. "PlainDateTime").
     * @throws \TypeError if the field is present but null.
     * @throws InvalidArgumentException if the value is non-finite.
     */
    public static function extractIntField(array $bag, string $field, int $default, string $className): int
    {
        if (!array_key_exists($field, $bag)) {
            return $default;
        }
        /** @var mixed $raw */
        $raw = $bag[$field];
        if ($raw === null) {
            throw new \TypeError("{$className} property bag {$field} field must not be undefined.");
        }
        /** @phpstan-ignore cast.double */
        if (!is_finite((float) $raw)) {
            throw new InvalidArgumentException("{$className} {$field} must be finite.");
        }
        /** @phpstan-ignore cast.int */
        return is_int($raw) ? $raw : (int) $raw;
    }

    /** @var list<string> Individual date/time component options that conflict with dateStyle/timeStyle. */
    private const COMPONENT_OPTIONS = [
        'weekday', 'era', 'year', 'month', 'day',
        'hour', 'minute', 'second', 'dayPeriod',
        'fractionalSecondDigits', 'timeZoneName',
    ];

    /**
     * Validates that dateStyle/timeStyle are not combined with individual component options.
     *
     * Per ECMA-402, mixing dateStyle or timeStyle with any individual date/time component
     * option (weekday, era, year, month, day, hour, minute, second, dayPeriod,
     * fractionalSecondDigits, timeZoneName) throws a TypeError.
     *
     * @param array<string, mixed> $opts
     * @throws \TypeError if style and component options are mixed.
     */
    public static function validateStyleConflicts(array $opts): void
    {
        $hasDateStyle = isset($opts['dateStyle']) && $opts['dateStyle'] !== null;
        $hasTimeStyle = isset($opts['timeStyle']) && $opts['timeStyle'] !== null;

        if (!$hasDateStyle && !$hasTimeStyle) {
            return;
        }

        foreach (self::COMPONENT_OPTIONS as $opt) {
            if (isset($opts[$opt]) && $opts[$opt] !== null) {
                if ($hasDateStyle) {
                    throw new \TypeError("toLocaleString(): dateStyle and {$opt} cannot be used together.");
                }
                if ($hasTimeStyle) {
                    throw new \TypeError("toLocaleString(): timeStyle and {$opt} cannot be used together.");
                }
            }
        }
    }

    /**
     * Builds a configured IntlDateFormatter from a resolved locale, timezone, and options array.
     *
     * Reads `dateStyle` and `timeStyle` from $opts (each: "full"|"long"|"medium"|"short") and maps
     * them to IntlDateFormatter constants. When neither style is provided, uses a pattern built
     * from individual component options, or defaults based on the $defaultComponents parameter.
     * Appends a `@calendar=…` extension to $locale if $opts['calendar'] is set.
     * Supports `hour12` and `hourCycle` options for hour format control.
     *
     * @param array<string, mixed> $opts
     * @param string $defaultComponents Which components to include by default: 'datetime', 'date', or 'time'.
     */
    public static function buildIntlFormatter(
        string $locale,
        string $timeZone,
        array $opts,
        string $defaultComponents = 'datetime',
    ): \IntlDateFormatter {
        if (isset($opts['calendar']) && is_string($opts['calendar'])) {
            $locale = sprintf('%s@calendar=%s', $locale, $opts['calendar']);
        }

        // Apply hourCycle as a Unicode locale extension
        if (isset($opts['hourCycle']) && is_string($opts['hourCycle'])) {
            $locale = self::applyHourCycle($locale, $opts['hourCycle']);
        } elseif (isset($opts['hour12'])) {
            // hour12=false -> h23, hour12=true -> h12
            $hc = $opts['hour12'] ? 'h12' : 'h23';
            $locale = self::applyHourCycle($locale, $hc);
        }

        // Detect non-gregorian calendar from locale keywords (e.g. en-u-ca-islamic-tbla
        // or en@calendar=islamic-tbla). IntlDateFormatter only respects non-gregorian calendars
        // when an explicit IntlCalendar instance is passed.
        $calendarObj = null;
        $keywords = \Locale::getKeywords($locale);
        if (is_array($keywords) && isset($keywords['calendar']) && $keywords['calendar'] !== 'gregory' && $keywords['calendar'] !== 'gregorian') {
            $calendarObj = \IntlCalendar::createInstance($timeZone, $locale);
        }

        $styleMap = [
            'full' => \IntlDateFormatter::FULL,
            'long' => \IntlDateFormatter::LONG,
            'medium' => \IntlDateFormatter::MEDIUM,
            'short' => \IntlDateFormatter::SHORT,
        ];

        $dateStyle = isset($opts['dateStyle']) && is_string($opts['dateStyle']) ? $opts['dateStyle'] : null;
        $timeStyle = isset($opts['timeStyle']) && is_string($opts['timeStyle']) ? $opts['timeStyle'] : null;

        if ($dateStyle !== null || $timeStyle !== null) {
            self::validateStyleConflicts($opts);

            $dateType = $dateStyle !== null
                ? $styleMap[$dateStyle] ?? \IntlDateFormatter::MEDIUM
                : \IntlDateFormatter::NONE;
            $timeType = $timeStyle !== null
                ? $styleMap[$timeStyle] ?? \IntlDateFormatter::SHORT
                : \IntlDateFormatter::NONE;

            $formatter = new \IntlDateFormatter($locale, $dateType, $timeType, $timeZone, $calendarObj);
            if ($calendarObj !== null) {
                $formatter->setCalendar($calendarObj);
            }
            return $formatter;
        }

        // Check for individual component options that require a custom pattern
        $hasComponents = false;
        foreach (self::COMPONENT_OPTIONS as $opt) {
            if (isset($opts[$opt]) && $opts[$opt] !== null) {
                $hasComponents = true;
                break;
            }
        }

        if ($hasComponents) {
            $pattern = self::buildPatternFromComponents($opts, $defaultComponents, $locale);
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $timeZone, $calendarObj, $pattern);
            if ($calendarObj !== null) {
                $formatter->setCalendar($calendarObj);
            }
            return $formatter;
        }

        // Default: use skeleton-based patterns to match JS Intl.DateTimeFormat defaults
        $generator = new \IntlDatePatternGenerator($locale);
        if ($defaultComponents === 'date') {
            $pattern = $generator->getBestPattern('yMd');
        } elseif ($defaultComponents === 'time') {
            $pattern = $generator->getBestPattern('jms');
        } elseif ($defaultComponents === 'datetime-tz') {
            // ZonedDateTime default includes timezone name
            $pattern = $generator->getBestPattern('yMdjmsz');
        } else {
            $pattern = $generator->getBestPattern('yMdjms');
        }

        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $timeZone, $calendarObj, $pattern);
        if ($calendarObj !== null) {
            $formatter->setCalendar($calendarObj);
        }
        return $formatter;
    }

    /**
     * Appends a -u-hc-{hourCycle} extension to a BCP 47 locale string.
     */
    private static function applyHourCycle(string $locale, string $hourCycle): string
    {
        // If there's already a -u- extension, append hc keyword
        if (str_contains($locale, '-u-')) {
            return $locale . '-hc-' . $hourCycle;
        }
        // If there's an @keyword section, insert before it
        $atPos = strpos($locale, '@');
        if ($atPos !== false) {
            return substr($locale, 0, $atPos) . '-u-hc-' . $hourCycle . substr($locale, $atPos);
        }
        return $locale . '-u-hc-' . $hourCycle;
    }

    /**
     * Builds an ICU skeleton pattern from individual component options.
     *
     * @param array<string, mixed> $opts
     */
    private static function buildPatternFromComponents(array $opts, string $defaultComponents, string $locale = 'en'): string
    {
        $parts = [];

        // Date components
        if (isset($opts['weekday'])) {
            $parts[] = match ($opts['weekday']) {
                'narrow' => 'EEEEE',
                'short' => 'EEE',
                'long' => 'EEEE',
                default => 'EEE',
            };
        }
        if (isset($opts['era'])) {
            $parts[] = match ($opts['era']) {
                'narrow' => 'GGGGG',
                'short' => 'GGG',
                'long' => 'GGGG',
                default => 'GGG',
            };
        }
        if (isset($opts['year'])) {
            $parts[] = $opts['year'] === '2-digit' ? 'yy' : 'y';
        }
        if (isset($opts['month'])) {
            $parts[] = match ($opts['month']) {
                'numeric' => 'M',
                '2-digit' => 'MM',
                'narrow' => 'MMMMM',
                'short' => 'MMM',
                'long' => 'MMMM',
                default => 'M',
            };
        }
        if (isset($opts['day'])) {
            $parts[] = $opts['day'] === '2-digit' ? 'dd' : 'd';
        }

        // Time components
        if (isset($opts['hour'])) {
            // Use 'j' skeleton symbol which picks locale-appropriate hour cycle
            $parts[] = $opts['hour'] === '2-digit' ? 'jj' : 'j';
        }
        if (isset($opts['minute'])) {
            $parts[] = $opts['minute'] === '2-digit' ? 'mm' : 'm';
        }
        if (isset($opts['second'])) {
            $parts[] = $opts['second'] === '2-digit' ? 'ss' : 's';
        }
        if (isset($opts['fractionalSecondDigits'])) {
            $digits = (int) $opts['fractionalSecondDigits'];
            $parts[] = str_repeat('S', $digits);
        }
        if (isset($opts['dayPeriod'])) {
            $parts[] = match ($opts['dayPeriod']) {
                'narrow' => 'BBBBB',
                'short' => 'B',
                'long' => 'BBBB',
                default => 'B',
            };
        }
        if (isset($opts['timeZoneName'])) {
            $parts[] = match ($opts['timeZoneName']) {
                'short' => 'z',
                'long' => 'zzzz',
                'shortOffset' => 'O',
                'longOffset' => 'OOOO',
                'shortGeneric' => 'v',
                'longGeneric' => 'vvvv',
                default => 'z',
            };
        }

        $skeleton = implode('', $parts);

        // Use ICU's DateTimePatternGenerator to get a best-fit pattern
        $generator = new \IntlDatePatternGenerator($locale);
        return $generator->getBestPattern($skeleton);
    }

    /**
     * Validates bracket annotations in a Temporal string (e.g. from `from()` or `fromISO()`).
     *
     * Rejects: uppercase annotation keys, critical unknown annotations, multiple time-zone
     * annotations, sub-minute UTC offsets inside time-zone annotations, and unknown calendar IDs.
     *
     * When $checkCalendar is true (the default), validates the first u-ca annotation value
     * against the known calendar list and returns the canonicalized calendar ID (or null if
     * no u-ca annotation is present). Pass false for types that do not use a calendar
     * (PlainTime, Instant) where the Temporal spec requires calendar annotations to be
     * ignored regardless of value; in that case null is always returned.
     *
     * @return ?string Canonicalized calendar ID from the first u-ca annotation, or null.
     * @throws InvalidArgumentException on any violation.
     */
    public static function validateAnnotations(string $section, string $original, bool $checkCalendar = true): ?string
    {
        if ($section === '') {
            return null;
        }

        $tzCount = 0;
        $calCount = 0;
        $calHasCritical = false;
        $calendarId = null;

        preg_match_all('/\[(!?)([^\]]*)\]/', $section, $matches, PREG_SET_ORDER);

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
                    if ($checkCalendar && $calCount === 0) {
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
                // Offset-style TZ annotation: reject sub-minute (seconds component).
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

        return $calendarId;
    }

    /**
     * Resolves a locale value from a string, array, or null.
     *
     * Returns the first non-empty string from the input, or the system default locale.
     *
     * @param string|array<mixed>|null $locales
     */
    public static function resolveLocale(string|array|null $locales): string
    {
        if (is_string($locales) && $locales !== '') {
            return $locales;
        }
        if (is_array($locales)) {
            /** @psalm-suppress MixedAssignment */
            foreach ($locales as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }
        }
        return \Locale::getDefault();
    }

    /**
     * Determines whether to round up based on fractional progress through the current unit.
     *
     * For negative diffs, floor and ceil are swapped so they retain their directional meaning.
     */
    public static function applyRoundingProgress(float $progress, string $mode, int $sign): bool
    {
        // For negative diffs, flip floor/ceil so they retain their directional meaning.
        $effectiveMode = $mode;
        if ($sign < 0) {
            $effectiveMode = match ($mode) {
                'floor' => 'ceil',
                'ceil' => 'floor',
                'halfFloor' => 'halfCeil',
                'halfCeil' => 'halfFloor',
                default => $mode,
            };
        }
        return match ($effectiveMode) {
            'trunc', 'floor' => false,
            'ceil', 'expand' => $progress > 0.0,
            'halfExpand', 'halfCeil' => $progress >= 0.5,
            'halfTrunc', 'halfFloor' => $progress > 0.5,
            'halfEven' => $progress > 0.5, // at exactly 0.5, leave at floor (calendar units differ from numeric even/odd)
            default => false,
        };
    }

    /**
     * Validates all time fields and throws if any are out of their valid range.
     *
     * @phpstan-assert int<0, 23> $h
     * @phpstan-assert int<0, 59> $min
     * @phpstan-assert int<0, 59> $sec
     * @phpstan-assert int<0, 999> $ms
     * @phpstan-assert int<0, 999> $us
     * @phpstan-assert int<0, 999> $ns
     * @throws InvalidArgumentException if any field is out of its valid range.
     */
    public static function validateTimeFields(int $h, int $min, int $sec, int $ms, int $us, int $ns): void
    {
        if ($h < 0 || $h > 23) {
            throw new InvalidArgumentException("Invalid time: hour {$h} is out of range 0–23.");
        }
        if ($min < 0 || $min > 59) {
            throw new InvalidArgumentException("Invalid time: minute {$min} is out of range 0–59.");
        }
        if ($sec < 0 || $sec > 59) {
            throw new InvalidArgumentException("Invalid time: second {$sec} is out of range 0–59.");
        }
        if ($ms < 0 || $ms > 999) {
            throw new InvalidArgumentException("Invalid time: millisecond {$ms} is out of range 0–999.");
        }
        if ($us < 0 || $us > 999) {
            throw new InvalidArgumentException("Invalid time: microsecond {$us} is out of range 0–999.");
        }
        if ($ns < 0 || $ns > 999) {
            throw new InvalidArgumentException("Invalid time: nanosecond {$ns} is out of range 0–999.");
        }
    }

    /**
     * Validates and returns the integer value of a `roundingIncrement` option.
     *
     * Accepts int, float, string, or bool. Returns the truncated integer value.
     * Throws TypeError for non-numeric types, InvalidArgumentException for NaN, infinite, or out-of-range values.
     *
     * @throws \TypeError if the value is not numeric.
     * @throws InvalidArgumentException if the value is NaN, infinite, or outside 1–1000000000.
     */
    public static function validateRoundingIncrement(mixed $value): int
    {
        if (!is_int($value) && !is_float($value) && !is_string($value) && !is_bool($value)) {
            throw new \TypeError('roundingIncrement must be numeric.');
        }
        $riFloat = (float) $value;
        if (is_nan($riFloat) || !is_finite($riFloat)) {
            throw new InvalidArgumentException('roundingIncrement must be a finite number.');
        }
        $riInt = (int) $riFloat; // truncate toward zero per spec
        if ($riInt < 1 || $riInt > 1_000_000_000) {
            throw new InvalidArgumentException("roundingIncrement {$riInt} is out of range; must be 1–1000000000.");
        }
        return $riInt;
    }

    /**
     * Validates an ISO month code and returns the month number 1–12.
     *
     * @return int<1, 12>
     * @throws InvalidArgumentException if the month code is not M01–M12.
     */
    public static function monthCodeToMonth(string $monthCode): int
    {
        if (preg_match('/^M(0[1-9]|1[0-2])$/', $monthCode) !== 1) {
            throw new InvalidArgumentException("Invalid monthCode for ISO calendar: \"{$monthCode}\".");
        }
        /** @var int<1, 12> */
        return (int) substr($monthCode, offset: 1);
    }

    /**
     * Floor division: rounds towards negative infinity (unlike intdiv which truncates towards zero).
     *
     * Required for correct Julian Day Number conversions with negative years.
     */
    public static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        $r = $a - ($q * $b);
        return $r < 0 ? $q - 1 : $q;
    }

    public static function isLeapYear(int $year): bool
    {
        return ($year % 4) === 0 && ($year % 100) !== 0 || ($year % 400) === 0;
    }

    /**
     * @param int<1, 12> $month
     * @return int<28, 31>
     */
    public static function calcDaysInMonth(int $year, int $month): int
    {
        return match ($month) {
            1, 3, 5, 7, 8, 10, 12 => 31,
            4, 6, 9, 11 => 30,
            2 => self::isLeapYear($year) ? 29 : 28,
        };
    }

    /**
     * Day of year for a proleptic ISO date.
     */
    public static function isoDayOfYear(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $cumDays */
        static $cumDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $doy = $cumDays[$month - 1] + $day;
        if ($month > 2 && self::isLeapYear($year)) {
            $doy++;
        }
        return $doy;
    }

    /**
     * ISO 8601 day of week using Sakamoto's algorithm.
     *
     * Returns 1 = Monday … 7 = Sunday.
     *
     * @return int<1, 7>
     */
    public static function isoWeekday(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $t */
        static $t = [0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4];
        if ($month < 3) {
            $year--;
        }
        $dow =
            (
                $year + intdiv(num1: $year, num2: 4)
                - intdiv(num1: $year, num2: 100)
                + intdiv(num1: $year, num2: 400)
                + $t[$month - 1]
                + $day
            )
            % 7;
        /** @var int<1, 7> Sakamoto maps 0→7, rest 1–6 unchanged */
        $result = $dow === 0 ? 7 : $dow;
        return $result;
    }

    /**
     * Ordinal day of the year (1 = January 1).
     *
     * @return int<1, 366>
     */
    public static function calcDayOfYear(int $year, int $month, int $day): int
    {
        /** @var array<int, int> $cumDays */
        static $cumDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $result = $cumDays[$month - 1] + $day;
        if ($month > 2 && self::isLeapYear($year)) {
            $result++;
        }
        /** @var int<1, 366> $result — max 335 + 31 = 366 (Dec 31 in leap year) */
        $ordinal = $result;
        return $ordinal;
    }

    /**
     * Returns the ISO 8601 week number and week-year for the given date.
     *
     * @return array{week: int<1, 53>, year: int}
     * @psalm-suppress UnusedMethod — called from weekOfYear and yearOfWeek property hooks
     */
    public static function isoWeekInfo(int $year, int $month, int $day): array
    {
        $dow = self::isoWeekday($year, $month, $day);
        $ordinal = self::calcDayOfYear($year, $month, $day);

        // Move to the Thursday of this ISO week; its ordinal determines the week number.
        $thursdayOrdinal = $ordinal + (4 - $dow);

        if ($thursdayOrdinal < 1) {
            // Thursday fell in the previous year → last week of that year.
            $prevYear = $year - 1;
            $dec31Dow = self::isoWeekday($prevYear, 12, 31);
            $dec31Ord = self::isLeapYear($prevYear) ? 366 : 365;
            /** @var int<1, 53> ISO week of previous year's Dec 31 */
            $prevWeek = intdiv(num1: $dec31Ord + (4 - $dec31Dow) - 1, num2: 7) + 1;
            return ['week' => $prevWeek, 'year' => $prevYear];
        }

        $yearDays = self::isLeapYear($year) ? 366 : 365;
        if ($thursdayOrdinal > $yearDays) {
            // Thursday fell in the next year → week 1 of next year.
            return ['week' => 1, 'year' => $year + 1];
        }

        /** @var int<1, 53> thursdayOrdinal 1–366 maps to week 1–53 */
        $week = intdiv(num1: $thursdayOrdinal - 1, num2: 7) + 1;
        return ['week' => $week, 'year' => $year];
    }

    /**
     * Converts a proleptic Gregorian calendar date to a Julian Day Number.
     * Algorithm: Richards (2013).
     */
    public static function toJulianDay(int $year, int $month, int $day): int
    {
        $a = intdiv(num1: 14 - $month, num2: 12);
        $y = $year + 4800 - $a;
        $m = $month + (12 * $a) - 3;
        return (
            $day
            + intdiv(num1: (153 * $m) + 2, num2: 5)
            + (365 * $y)
            + self::floorDiv($y, 4)
            - self::floorDiv($y, 100)
            + self::floorDiv($y, 400)
            - 32_045
        );
    }

    /**
     * Converts a Julian Day Number to a proleptic Gregorian calendar date.
     *
     * @return array{0: int, 1: int<1, 12>, 2: int<1, 31>} [year, month, day]
     */
    public static function fromJulianDay(int $jdn): array
    {
        $a = $jdn + 32_044;
        $b = self::floorDiv((4 * $a) + 3, 146_097);
        $c = $a - self::floorDiv(146_097 * $b, 4);
        $d = self::floorDiv((4 * $c) + 3, 1_461);
        $e = $c - self::floorDiv(1_461 * $d, 4);
        $m = self::floorDiv((5 * $e) + 2, 153);
        /** @var int<1, 31> Richards algorithm guarantees day is 1–31 */
        $day = $e - intdiv(num1: (153 * $m) + 2, num2: 5) + 1;
        /** @var int<1, 12> Richards algorithm guarantees month is 1–12 */
        $month = $m + 3 - (12 * intdiv(num1: $m, num2: 10));
        $year = (100 * $b) + $d - 4800 + intdiv(num1: $m, num2: 10);
        return [$year, $month, $day];
    }
}
