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
        $lower = strtolower($id);

        if (isset(self::ALIASES[$lower])) {
            $lower = self::ALIASES[$lower];
        }

        if (!in_array($lower, self::KNOWN_CALENDARS, true)) {
            throw new InvalidArgumentException("Unknown calendar \"{$id}\".");
        }

        return $lower;
    }

    /**
     * Returns true if the given ID (after lowercasing and alias resolution) is a known calendar.
     */
    public static function isKnownCalendar(string $id): bool
    {
        $lower = strtolower($id);

        if (isset(self::ALIASES[$lower])) {
            $lower = self::ALIASES[$lower];
        }

        return in_array($lower, self::KNOWN_CALENDARS, true);
    }

    private static function create(string $id): CalendarProtocol
    {
        if ($id === 'iso8601') {
            return new IsoCalendar();
        }

        return new IntlCalendarBridge($id);
    }

    /** @codeCoverageIgnore */
    private function __construct() {}
}
