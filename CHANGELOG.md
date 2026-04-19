# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Until 1.0.0 the public API may change between minor versions.

## [0.1.0] - 2026-04-19

Initial public release.

### Added

- **Porcelain API** (`Temporal\` namespace) — PHP-native facade over the TC39 Temporal spec with strict types, readonly fields, backed enums, and named arguments:
  - `PlainDate`, `PlainTime`, `PlainDateTime`, `PlainYearMonth`, `PlainMonthDay`
  - `Instant`, `ZonedDateTime`
  - `Duration`
  - `Now` (factory for current-time values)
  - Enums: `Calendar`, `Disambiguation`, `OffsetOption`, `Overflow`, `RoundingMode`, `Unit`, plus display-side enums (`CalendarDisplay`, `OffsetDisplay`, `TimeZoneDisplay`, `TransitionDirection`).
- **Spec layer** (`Temporal\Spec\` namespace) — TC39-faithful implementation, exercised by the test262 conformance suite. Considered internal; prefer the porcelain API.
- **ECMA-402 calendar support** — 16 non-ISO calendars (Hebrew, Islamic family, Japanese, Buddhist, Chinese/Dangi, Coptic, Ethiopic family, Persian, ROC, Indian) via `IntlCalendarBridge`, with pure-PHP implementations for Hebrew and Indian.
- **Factory surface per porcelain class** — each class exposes a typed constructor (named arguments supported), a `parse(string)` for ISO 8601 strings, and for the five calendar-aware classes (`PlainDate`, `PlainDateTime`, `PlainYearMonth`, `PlainMonthDay`, `ZonedDateTime`) a `fromFields(...)` named-argument factory covering calendar-specific fields (`monthCode`, `era`, `eraYear`).
- **Interop** — `toSpec()` / `fromSpec()` on every porcelain class for bridging to the spec layer.
- **Test262 conformance** — 6615 passing test262 scripts, 0 failures (~494k assertions).

### Deliberate deviations from TC39

The porcelain layer adapts TC39 semantics to PHP-native conventions rather than mirroring the JavaScript API shape 1:1.

- **No polymorphic `from()` method.** TC39's `Temporal.X.from(string|object)` is not provided at the porcelain level. Use `parse()` for strings and `fromFields()` for calendar fields. The spec layer (`Temporal\Spec\*`) retains TC39-faithful `from()` for anyone needing exact spec semantics.
- **Named arguments, not property bags.** `PlainDate::fromFields(year: 2024, month: 3, day: 15, calendar: Calendar::Gregory)` rather than `PlainDate.from({year: 2024, ...})`.
- **Backed enums, not option strings.** `Overflow::Reject` rather than `{overflow: 'reject'}`.

### Known limitations

- `Duration::round()` and `Duration::total()` throw `NotYetImplementedException` when balancing across calendar units that require a reference point.
- The `Temporal\Spec\` namespace is public PSR-4 but documented as internal; a formal BC policy for the seam will land before 1.0.0.
- Mutation testing currently reports ~72% MSI; see [issue #2](https://github.com/MidnightDesign/temporal-php/issues/2).

[0.1.0]: https://github.com/MidnightDesign/temporal-php/releases/tag/v0.1.0
