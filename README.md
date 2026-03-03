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

// Compare
Instant::compare($a, $b); // -1, 0, or 1
$a->equals($b);            // bool

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

## Development

This project runs in Docker. Start the environment:

```bash
docker compose up -d
```

Then run commands via the `php` service:

```bash
docker compose exec php ./vendor/bin/phpunit tests/
docker compose exec php ./vendor/bin/phpstan analyse
docker compose exec php ./vendor/bin/psalm
docker compose exec php ./vendor/bin/mago lint
```

Or run the full check suite (static analysis + tests + mutation testing):

```bash
docker compose exec php composer check
```

### Individual scripts

| Script | Command |
|--------|---------|
| Tests | `composer test` |
| Tests + coverage | `composer test-coverage` |
| PHPStan (level 9) | `composer phpstan` |
| Psalm (level 1) | `composer psalm` |
| Mago lint | `composer mago` |
| Mutation testing | `composer infection` |
| Format check | `composer mago-check` |
| Auto-format | `composer mago-format` |

## Status

The API surface is being implemented incrementally, one class at a time, starting with the most fundamental types and building up to the higher-level ones.

| Class | Status |
|-------|--------|
| `Temporal\Instant` | Partial (arithmetic methods pending `Duration`, `ZonedDateTime`) |
| `Temporal\Duration` | Planned |
| `Temporal\PlainDate` | Planned |
| `Temporal\PlainTime` | Planned |
| `Temporal\PlainDateTime` | Planned |
| `Temporal\ZonedDateTime` | Planned |

## Transparency

This codebase is written with [Claude Code](https://claude.ai/claude-code). All production code and tests are AI-generated.

Quality is enforced by PHPStan (level 9), Psalm (error level 1), Mago, PHPUnit, and Infection with a 100% mutation kill threshold. None of that is negotiable — every change must pass the full suite before it counts.

## License

MIT
