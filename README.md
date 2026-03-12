# temporal-php

A PHP 8.4 implementation of the [TC39 Temporal API](https://tc39.es/proposal-temporal/).

Temporal is the modern replacement for JavaScript's `Date`, providing a precise, unambiguous date/time API. This library brings those semantics to PHP with full nanosecond precision.

## Requirements

- PHP 8.4+
- Composer

## Installation

```bash
composer require temporal/temporal
```

## Usage

### `Temporal\Instant`

Represents a fixed point in time with nanosecond precision. Internally stores nanoseconds since the Unix epoch as a 64-bit integer, covering approximately 1677–2262.

```php
use Temporal\Instant;

// Parse an ISO 8601 string (UTC offset required)
$instant = Instant::from('2020-01-01T12:00:00Z');
$instant = Instant::from('2020-01-01T12:00:00.123456789+05:30');

// Create from epoch values
$instant = Instant::fromEpochMilliseconds(1_577_880_000_000);
$instant = Instant::fromEpochNanoseconds(1_577_880_000_000_000_000);

// Read properties
echo $instant->epochNanoseconds;  // int
echo $instant->epochMilliseconds; // int (floor division)

// Arithmetic
$later  = $instant->add('PT1H30M');
$earlier = $instant->subtract(new Duration(hours: 1));
$diff   = $instant->since($other);
$diff   = $instant->until($other);
$rounded = $instant->round(['smallestUnit' => 'minute']);

// Compare
Instant::compare($a, $b); // -1, 0, or 1
$a->equals($b);            // bool

// Convert to ZonedDateTime
$zdt = $instant->toZonedDateTimeISO('America/New_York');
$zdt = $instant->toZonedDateTimeISO('+05:30');

// Serialize
echo $instant->toString(); // '2020-01-01T12:00:00Z'
echo $instant->toJSON();   // same
echo (string) $instant;    // same
```

#### Supported `from()` formats

| Format | Example |
|--------|---------|
| ISO 8601 with Z | `2020-01-01T00:00:00Z` |
| ISO 8601 with offset | `2020-01-01T00:00:00+05:30` |
| With nanoseconds | `2020-01-01T00:00:00.123456789Z` |
| Comma as decimal separator | `1976-11-18T15:23:30,12Z` |
| Seconds optional | `2020-01-01T15:23Z` |
| Compact date + time | `19761118T152330Z` |
| Short offset | `1976-11-18T15:23:30+0530` |
| Extended year | `+001976-11-18T15:23:30Z` |
| Negative year | `-009999-11-18T15:23:30Z` |
| Annotations (ignored) | `2020-01-01T00:00:00Z[UTC][u-ca=iso8601]` |
| Leap second (normalized) | `2016-12-31T23:59:60Z` |

---

### `Temporal\Duration`

Represents an ISO 8601 duration with 10 fields: years, months, weeks, days, hours, minutes, seconds, milliseconds, microseconds, nanoseconds.

```php
use Temporal\Duration;

$d = new Duration(years: 1, months: 6, days: 15);
$d = Duration::from('P1Y6M15D');
$d = Duration::from('PT2H30M');
$d = Duration::from([hours: 2, minutes: 30]);

// Properties (read-only)
echo $d->years;        // int|float
echo $d->sign;         // -1, 0, or 1
echo $d->blank;        // true if all fields are zero

// Arithmetic
$sum  = $d->add($other);
$diff = $d->subtract($other);

// Mutation
$negated = $d->negated();
$abs     = $d->abs();
$copy    = $d->with(years: 2);

// Comparison
$d->equals($other);
Duration::compare($a, $b, relativeTo: '2020-01-01'); // for calendar units

// Total duration in a given unit
$hours = $d->total('hours');
$days  = $d->total(['unit' => 'days', 'relativeTo' => '2020-01-01']);

// Serialization
echo $d->toString(); // 'P1Y6M15D'
echo $d->toJSON();   // same
```

---

### `Temporal\PlainDate`

Represents a calendar date (year, month, day) without time or time zone. Only the ISO 8601 calendar is supported.

```php
use Temporal\PlainDate;

$date = new PlainDate(2024, 3, 15);
$date = PlainDate::from('2024-03-15');
$date = PlainDate::from(['year' => 2024, 'month' => 3, 'day' => 15]);
$date = PlainDate::from(['year' => 2024, 'monthCode' => 'M03', 'day' => 15]);

// Read-only fields
echo $date->year;         // 2024
echo $date->month;        // 3
echo $date->day;          // 15

// Calendar-derived properties (virtual, get-only)
echo $date->calendarId;   // 'iso8601'
echo $date->monthCode;    // 'M03'
echo $date->dayOfWeek;    // 1–7 (1=Monday, 7=Sunday)
echo $date->dayOfYear;    // 1–366
echo $date->weekOfYear;   // 1–53
echo $date->yearOfWeek;   // ISO week-year (may differ from calendar year near boundaries)
echo $date->daysInMonth;  // 28–31
echo $date->daysInWeek;   // 7
echo $date->daysInYear;   // 365 or 366
echo $date->monthsInYear; // 12
echo $date->inLeapYear;   // bool
echo $date->era;          // null (ISO calendar)
echo $date->eraYear;      // null (ISO calendar)

// Arithmetic
$later   = $date->add(['days' => 30]);
$earlier = $date->subtract(new Duration(months: 2));
$copy    = $date->with(['year' => 2025]);

// Comparison
PlainDate::compare($a, $b);  // -1, 0, or 1
$a->equals($b);               // bool

// Serialization
echo $date->toString();                          // '2024-03-15'
echo $date->toString(['calendarName' => 'always']); // '2024-03-15[u-ca=iso8601]'
echo $date->toJSON();                            // '2024-03-15'
```

---

### `Temporal\PlainTime`

Represents a wall-clock time (hour, minute, second, millisecond, microsecond, nanosecond) without a date or time zone.

```php
use Temporal\PlainTime;

$time = new PlainTime(9, 30, 0);
$time = PlainTime::from('09:30:00');
$time = PlainTime::from('T093000.123456789');
$time = PlainTime::from(['hour' => 9, 'minute' => 30]);

// Read-only fields
echo $time->hour;         // 9
echo $time->minute;       // 30
echo $time->second;       // 0
echo $time->millisecond;  // 0
echo $time->microsecond;  // 0
echo $time->nanosecond;   // 0

// Arithmetic
$later   = $time->add(['hours' => 2, 'minutes' => 15]);
$earlier = $time->subtract(new Duration(minutes: 30));
$copy    = $time->with(['hour' => 10]);

// Comparison
PlainTime::compare($a, $b);  // -1, 0, or 1
$a->equals($b);               // bool

// Rounding
$rounded = $time->round(['smallestUnit' => 'minute']);
$rounded = $time->round(['smallestUnit' => 'second', 'roundingMode' => 'ceil']);

// Calendar difference
$duration = $a->since($b, ['largestUnit' => 'hours']);
$duration = $a->until($b, ['largestUnit' => 'minutes']);

// Serialization
echo $time->toString();  // '09:30:00'
echo $time->toJSON();    // '09:30:00'
```

---

### `Temporal\Now`

Provides access to the current date and time. Not instantiable — all methods are static.

```php
use Temporal\Now;

// Current instant (microsecond precision)
$instant = Now::instant();

// Current local time zone identifier
$tzId = Now::timeZoneId();  // e.g. 'Europe/Amsterdam'

// Today's date in the given (or system default) time zone
$date = Now::plainDateISO();
$date = Now::plainDateISO('America/New_York');

// Current time (no date) in the given (or system default) time zone
$time = Now::plainTimeISO();
$time = Now::plainTimeISO('+05:30');
```

---

### `Temporal\PlainDateTime`

Represents a date and time without a time zone (year, month, day, hour, minute, second, millisecond, microsecond, nanosecond). Only the ISO 8601 calendar is supported.

```php
use Temporal\PlainDateTime;

$dt = new PlainDateTime(2024, 3, 15, 9, 30, 0);
$dt = PlainDateTime::from('2024-03-15T09:30:00');
$dt = PlainDateTime::from('2024-03-15');                  // midnight
$dt = PlainDateTime::from(['year' => 2024, 'month' => 3, 'day' => 15, 'hour' => 9]);

// Read-only fields
echo $dt->year;         // 2024
echo $dt->month;        // 3
echo $dt->day;          // 15
echo $dt->hour;         // 9
echo $dt->minute;       // 30
echo $dt->second;       // 0
echo $dt->millisecond;  // 0
echo $dt->microsecond;  // 0
echo $dt->nanosecond;   // 0

// Calendar-derived properties (virtual, get-only)
echo $dt->calendarId;   // 'iso8601'
echo $dt->monthCode;    // 'M03'
echo $dt->dayOfWeek;    // 1–7 (1=Monday, 7=Sunday)
echo $dt->dayOfYear;    // 1–366
echo $dt->weekOfYear;   // 1–53
echo $dt->yearOfWeek;   // ISO week-year
echo $dt->daysInMonth;  // 28–31
echo $dt->daysInWeek;   // 7
echo $dt->daysInYear;   // 365 or 366
echo $dt->monthsInYear; // 12
echo $dt->inLeapYear;   // bool
echo $dt->era;          // null (ISO calendar)
echo $dt->eraYear;      // null (ISO calendar)

// Arithmetic
$later   = $dt->add(['days' => 30, 'hours' => 2]);
$earlier = $dt->subtract(new Duration(months: 2));
$copy    = $dt->with(['year' => 2025, 'hour' => 12]);

// Comparison
PlainDateTime::compare($a, $b);  // -1, 0, or 1
$a->equals($b);                   // bool

// Rounding
$rounded = $dt->round(['smallestUnit' => 'minute']);
$rounded = $dt->round(['smallestUnit' => 'hour', 'roundingMode' => 'floor']);

// Calendar difference
$duration = $a->since($b, ['largestUnit' => 'months']);
$duration = $a->until($b, ['largestUnit' => 'years']);

// Convert to PlainDate / PlainTime
$date = $dt->toPlainDate();
$time = $dt->toPlainTime();

// Serialization
echo $dt->toString();                             // '2024-03-15T09:30:00'
echo $dt->toString(['calendarName' => 'always']); // '2024-03-15T09:30:00[u-ca=iso8601]'
echo $dt->toJSON();                               // '2024-03-15T09:30:00'
```

---

## Development

This project runs in Docker. Start the environment:

```bash
docker compose up -d
```

Then run commands via the `php` service:

```bash
docker compose exec php ./vendor/bin/phpunit tests/
docker compose exec php composer phpstan
docker compose exec php composer psalm
docker compose exec php composer mago
```

Or run the full check suite (static analysis + tests + mutation testing):

```bash
docker compose exec php composer check
```

### Individual scripts

| Script | Command |
|--------|---------|
| Unit tests | `composer test` |
| test262 conformance | `composer test262:run` |
| Transpile test262 | `composer test262:build` |
| Tests + coverage | `composer test-coverage` |
| PHPStan (level 9) | `composer phpstan` |
| Psalm (level 1) | `composer psalm` |
| Mago lint | `composer mago` |
| Mutation testing | `composer infection` |
| Format check | `composer mago-check` |
| Auto-format | `composer mago-format` |

### test262 conformance

TC39 maintains [test262](https://github.com/tc39/test262), the official JavaScript conformance test suite. This project includes a transpiler (`tools/transpile-test262.mjs`) that converts Temporal test262 JS files to PHP, enabling direct conformance testing.

```bash
# Transpile JS test files to PHP
docker compose exec php composer test262:build

# Run the test262 suite
docker compose exec php composer test262:run
```

Currently **2627 test262 tests passing** (1 known failure, 660 incomplete due to JS-only features like Symbol and Proxy) across `Temporal.Instant`, `Temporal.Duration`, `Temporal.PlainDate`, `Temporal.PlainDateTime`, `Temporal.PlainTime`, `Temporal.Now`, and `Temporal.ZonedDateTime`. 249 additional hand-written unit tests also pass (total: 2876 tests).

The 1 known failure (`ZonedDateTime/prototype/withPlainTime/argument-wrong-type.php`) is a PHP limitation: PHP cannot distinguish JS `null` from `undefined`, so the test expects a `TypeError` for `null` but our implementation treats `null` as missing (defaulting to midnight).

---

## Status

| Class | Status |
|-------|--------|
| `Temporal\Instant` | Complete — `from`, `fromEpochMilliseconds`, `fromEpochNanoseconds`, `compare`, `equals`, `add`, `subtract`, `since`, `until`, `round`, `toZonedDateTimeISO` (returns ZonedDateTime with iso8601 calendar), `toString`, `toJSON` |
| `Temporal\Duration` | Complete — all 10 fields, `from`, `compare`, `add`, `subtract`, `negated`, `abs`, `with`, `equals`, `total`, `toString`, `toJSON` |
| `Temporal\PlainDate` | Complete — `from`, `compare`, `with`, `add`, `subtract`, `since`, `until`, `equals`, `toString` (with `calendarName` option), `toJSON`; all 13 calendar properties |
| `Temporal\PlainDateTime` | Complete — `from`, `compare`, `with`, `add`, `subtract`, `since`, `until`, `round`, `equals`, `toString` (with `calendarName` option), `toJSON`, `toPlainDate`, `toPlainTime`; all 22 virtual properties |
| `Temporal\PlainTime` | Complete — `from`, `compare`, `with`, `add`, `subtract`, `since`, `until`, `round`, `equals`, `toString`, `toJSON`; all 6 time fields |
| `Temporal\Now` | Complete — `instant`, `timeZoneId`, `plainDateISO`, `plainTimeISO` |
| `Temporal\ZonedDateTime` | Complete — `from` (with `disambiguation`/`offset` options), `compare`, `equals`, `toString`, `toJSON`, `toInstant`, `toPlainDate`, `toPlainTime`, `toPlainDateTime`, `withTimeZone`, `withCalendar`, `withPlainTime`; all virtual date/time/calendar/timezone properties |

## Transparency

This codebase is written with [Claude Code](https://claude.ai/claude-code). All production code and tests are AI-generated.

Quality is enforced by PHPStan (level 9), Psalm (error level 1), Mago, PHPUnit, and Infection with a 100% mutation kill threshold. None of that is negotiable — every change must pass the full suite before it counts.

## License

MIT
