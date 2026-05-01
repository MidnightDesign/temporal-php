<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal\Calendar;

use InvalidArgumentException;

/**
 * Singleton factory for calendar protocol instances.
 *
 * Caches one CalendarProtocol per canonical calendar ID. Handles calendar ID
 * canonicalization (lowercasing, alias resolution) and validation.
 *
 * @internal
 */
final class CalendarFactory
{
    /** @var array<string, CalendarProtocol> */
    private static array $instances = [];

    /** @var array<string, string> Memoized canonicalize() results. */
    private static array $canonicalCache = [];

    /**
     * All calendar identifiers recognized by TC39 Temporal (ECMA-402).
     *
     * @var list<string>
     */
    private const KNOWN_CALENDARS = [
        'iso8601',
        'buddhist',
        'chinese',
        'coptic',
        'dangi',
        'ethioaa',
        'ethiopic',
        'gregory',
        'hebrew',
        'indian',
        'islamic-civil',
        'islamic-tbla',
        'islamic-umalqura',
        'japanese',
        'persian',
        'roc',
    ];

    /**
     * Alias map: deprecated or alternate IDs -> canonical ID.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'islamicc' => 'islamic-civil',
        'ethiopic-amete-alem' => 'ethioaa',
    ];

    /**
     * Returns the CalendarProtocol for the given canonical calendar ID.
     */
    public static function get(string $calendarId): CalendarProtocol
    {
        return self::$instances[$calendarId] ??= self::create($calendarId);
    }

    /**
     * Canonicalizes a calendar identifier: lowercases, resolves aliases.
     *
     * @throws InvalidArgumentException if the calendar ID is unknown.
     */
    public static function canonicalize(string $id): string
    {
        if (isset(self::$canonicalCache[$id])) {
            return self::$canonicalCache[$id];
        }

        $lower = strtolower($id);

        if (array_key_exists($lower, self::ALIASES)) {
            $lower = self::ALIASES[$lower];
        }

        if (!in_array($lower, self::KNOWN_CALENDARS, strict: true)) {
            throw new InvalidArgumentException("Unknown calendar \"{$id}\".");
        }

        return self::$canonicalCache[$id] = $lower;
    }

    /**
     * Resolves a property-bag `calendar` field to a canonical calendar ID.
     *
     * Runs the full pipeline used by every Plain* `from()` property-bag path:
     * type-check, minus-zero extended-year reject, ISO-string-with-bracket
     * validation, [u-ca=...] / date-like extraction, and final canonicalization.
     *
     * The $context label is used in the TypeError message (e.g. "PlainDate"
     * produces "PlainDate calendar must be a string; got int.").
     *
     * @throws \TypeError                if $value is not a string.
     * @throws InvalidArgumentException if $value is malformed or names an unknown calendar.
     */
    public static function resolveBagCalendar(mixed $value, string $context): string
    {
        if (!is_string($value)) {
            throw new \TypeError(sprintf('%s calendar must be a string; got %s.', $context, get_debug_type($value)));
        }
        // Reject minus-zero extended year in calendar strings.
        if (preg_match(pattern: '/^-0{6}/', subject: $value) === 1) {
            throw new InvalidArgumentException(
                "Cannot use negative zero as extended year in calendar string \"{$value}\".",
            );
        }
        return self::canonicalize(self::extractIdFromCalendarString($value));
    }

    /**
     * Returns true if the given ID (after lowercasing and alias resolution) is a known calendar.
     */
    public static function isKnownCalendar(string $id): bool
    {
        $lower = strtolower($id);

        if (array_key_exists($lower, self::ALIASES)) {
            $lower = self::ALIASES[$lower];
        }

        return in_array($lower, self::KNOWN_CALENDARS, strict: true);
    }

    /**
     * Extracts a calendar ID from a calendar-field string.
     *
     * Accepts bare calendar IDs ("iso8601", "hebrew", ...), ISO date / datetime
     * strings ("2020-01-01", "01-01"), and ISO strings carrying a `[u-ca=X]`
     * annotation. ISO strings carrying a non-`u-ca` bracket annotation
     * (e.g. `"2020-01-01[UTC]"`) yield "iso8601".
     *
     * Per ParseTemporalCalendarString, a string with a bracket annotation must
     * parse as a Temporal date/time/etc string — i.e. the prefix before the
     * first '[' must look like an ISO date. Bare bracket annotations and
     * bracket annotations following non-temporal prefixes are RangeError.
     */
    private static function extractIdFromCalendarString(string $cal): string
    {
        if (str_contains($cal, '[')) {
            $prefix = substr($cal, offset: 0, length: (int) strpos($cal, needle: '['));
            if (preg_match(pattern: '/^\d{1,6}-/', subject: $prefix) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid calendar string \"{$cal}\": bracket annotation must follow an ISO date prefix.",
                );
            }
            $m = null;
            if (preg_match(pattern: '/\[!?u-ca=([^\]]+)\]/', subject: $cal, matches: $m) === 1) {
                return strtolower($m[1]);
            }
            // Bracket without u-ca (e.g. time-zone annotation) → default iso8601.
            return 'iso8601';
        }
        // Date-like strings: starts with digits and has a dash within the first 7 chars.
        if (
            preg_match(pattern: '/^\d/', subject: $cal) === 1
            && preg_match(pattern: '/^\d{1,6}-/', subject: $cal) === 1
        ) {
            return 'iso8601';
        }
        // Plain calendar ID: ASCII-only lowercase (rejects non-ASCII via downstream canonicalize).
        $lower = '';
        $len = strlen($cal);
        for ($i = 0; $i < $len; $i++) {
            $c = $cal[$i];
            $o = ord($c);
            $lower .= $o >= 0x41 && $o <= 0x5A ? chr($o + 32) : $c;
        }
        return $lower;
    }

    private static function create(string $id): CalendarProtocol
    {
        if ($id === 'iso8601') {
            return new IsoCalendar();
        }

        if ($id === 'hebrew') {
            return new PureHebrewCalendar();
        }

        if ($id === 'indian') {
            return new PureIndianCalendar();
        }

        return new IntlCalendarBridge($id);
    }
}
