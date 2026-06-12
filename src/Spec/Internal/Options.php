<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

use Stringable;
use Temporal\Exception\RangeError;
use Temporal\Exception\TypeError;

/**
 * Faithful coercion of a TC39 string-typed option value.
 *
 * GetOption(options, prop, "string", ...) applies ToString: a string passes
 * through; a Stringable coerces via (string) (a Symbol-like sentinel's
 * __toString throws Temporal\Exception\TypeError); any other type (number,
 * bool, plain object, null) would ToString to a value that is never a valid
 * option keyword, so it is rejected with a RangeError. The returned string must
 * still be validated against the option's allowed set by the caller.
 *
 * MESSAGE TEXT IS NON-CONTRACTUAL. No test asserts on any exception's message in
 * this class: `tests/Test262/Assert.php::throws()` checks only the exception
 * CLASS, and the project's PHPUnit suites have no message assertions. Only the
 * exception TYPE (RangeError vs. TypeError) is contractual; the wording of every
 * message owned here is free to change. The per-method docblocks below reference
 * this note rather than repeating it verbatim.
 *
 * @internal
 */
final class Options
{
    /**
     * Canonical TC39 rounding-mode keyword set (RoundingMode enum). The order matches
     * the spec's enumeration. Validated against by {@see self::roundingMode()}.
     *
     * This is the strict keyword set only — no legacy ECMA-402 aliases ("truncate"/
     * "ceiling") are accepted anywhere; they were never part of TC39 Temporal.
     *
     * @var list<string>
     */
    public const array ROUNDING_MODES = [
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
     * Faithful TC39 GetOption(..., "string", ...) ToString coercion of an option
     * value: a string passes through; a Stringable coerces via __toString (a JsSymbol
     * sentinel's throwing __toString surfaces as Temporal\Exception\TypeError); any
     * other type is rejected with a RangeError. The returned string must still be
     * validated against the option's allowed keyword set by the caller.
     *
     * The RangeError message is owned here and parameterized only by the option's NAME
     * token (e.g. "smallestUnit", "disambiguation"). See the class-level note on message
     * text.
     *
     * @param string $optionName Bare option name interpolated into the RangeError text.
     * @throws RangeError if $value is neither a string nor a Stringable.
     */
    public static function coerceEnumOption(mixed $value, string $optionName): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new RangeError("{$optionName} must be a string.");
    }

    /**
     * Coerces and validates a TC39 `overflow` option value, which must stringify to
     * one of "constrain" / "reject".
     *
     * Combines the canonical {@see self::coerceEnumOption()} ToString coercion (a
     * string passes through; a Stringable coerces via __toString — a JsSymbol
     * sentinel's throwing __toString surfaces as Temporal\Exception\TypeError; any
     * other type is a RangeError) with the keyword check that the ~9 inline copies
     * across the Plain... and ZonedDateTime classes perform.
     *
     * Both RangeError messages are owned here. See the class-level note on message text.
     *
     * @throws RangeError if $value does not coerce to a string, or coerces to a string
     *                    that is neither "constrain" nor "reject".
     */
    public static function overflowOption(mixed $value): string
    {
        $overflow = self::coerceEnumOption($value, 'overflow');
        if ($overflow !== 'constrain' && $overflow !== 'reject') {
            throw new RangeError(sprintf(
                'Invalid overflow value: "%s"; must be \'constrain\' or \'reject\'.',
                $overflow,
            ));
        }
        return $overflow;
    }

    /**
     * Resolves the full GetOptionsObject + GetTemporalOverflowOption pipeline from a
     * RAW options argument to a validated "constrain" / "reject" keyword.
     *
     * This is the single resolver for the `overflow` option across all five Plain...
     * classes. It folds the GetOptionsObject step ({@see self::requireObject()}) and
     * the default-to-"constrain" + keyword-coercion step ({@see self::overflowFromBag()})
     * into one call, so callers no longer copy a `requireObject`-then-`overflowFromBag`
     * two-step or a per-class `resolveOverflowOption` wrapper.
     *
     * GetOptionsObject contract (TC39): the options argument must be undefined (omitted)
     * or an object. Omitted arrives as the empty-array default and resolves to the
     * "constrain" default; an explicit `null` or any other non-object primitive
     * (int/float/string/bool) is a TypeError; a Symbol sentinel (a \Stringable whose
     * __toString throws) is a TypeError. A genuine bag's `overflow` value is then
     * coerced/validated by {@see self::overflowOption()} (string keyword, else
     * RangeError).
     *
     * @param mixed $options Raw options argument (omitted → []; null/primitive → TypeError).
     * @throws TypeError  if $options is an explicit null, a non-object primitive, or a
     *                    Symbol sentinel (GetOptionsObject).
     * @throws RangeError if the `overflow` value is not "constrain"/"reject".
     */
    public static function overflowFromValue(mixed $options): string
    {
        // GetOptionsObject step 3: a non-null, non-array, non-object primitive
        // (int/float/string/bool) is a TypeError — raised here at the spec-layer origin
        // (after a caller's string parse), not by a from()/with() parameter-type guard.
        if ($options !== null && !is_array($options) && !is_object($options)) {
            throw new TypeError('options must be an object.');
        }
        // requireObject turns an explicit null / Symbol sentinel into a TypeError and
        // normalizes an object to an array; the empty-array default passes through.
        return self::overflowFromBag(self::requireObject($options));
    }

    /**
     * Resolves an already-validated options BAG (post-GetOptionsObject) to a validated
     * "constrain" / "reject" keyword, defaulting to "constrain" when the bag is null
     * (an omitted options argument) or has no `overflow` key.
     *
     * Delegates the keyword coercion/validation to {@see self::overflowOption()}, where
     * an explicit `overflow => null` value coerces to neither keyword and is a RangeError.
     * Callers that need the GetOptionsObject (null-argument → TypeError) step should use
     * {@see self::overflowFromValue()} instead; this helper always defaults a null bag.
     *
     * After the Plain... convergence on {@see self::overflowFromValue()}, the only
     * external caller is {@see Temporal\Spec\ZonedDateTime}, which performs its own
     * GetOptionsObject (null handling) upstream and so wants the bag-only resolver here;
     * internally, {@see self::overflowFromValue()} also delegates to this method after
     * its own GetOptionsObject step.
     *
     * @param array<array-key, mixed>|object|null $options
     * @throws RangeError per {@see self::overflowOption()}.
     */
    public static function overflowFromBag(array|object|null $options): string
    {
        if ($options === null) {
            return 'constrain';
        }
        if (is_object($options)) {
            $options = get_object_vars($options);
        }
        if (!array_key_exists('overflow', $options)) {
            return 'constrain';
        }
        return self::overflowOption($options['overflow']);
    }

    /**
     * Validates an already-coerced `roundingMode` string against the canonical
     * {@see self::ROUNDING_MODES} set and returns it unchanged.
     *
     * Replaces the ~7 inline `!in_array($mode, ROUNDING_MODES, true)` checks. The
     * RangeError message is owned here and embeds the offending value. See the
     * class-level note on message text.
     *
     * This validates the STRICT keyword set only; no legacy "truncate"/"ceiling"
     * aliases are recognized — they are not part of TC39 Temporal.
     *
     * @throws RangeError if $mode is not one of {@see self::ROUNDING_MODES}.
     */
    public static function roundingMode(string $mode): string
    {
        if (!in_array($mode, self::ROUNDING_MODES, strict: true)) {
            throw new RangeError("Invalid roundingMode: \"{$mode}\".");
        }
        return $mode;
    }

    /**
     * Performs the universal part of TC39 ToTemporalRoundingIncrement on an already-
     * read `roundingIncrement` value: ToIntegerWithTruncation followed by the
     * "finite and ≥ 1" validation, returning the truncated integer.
     *
     * A Number (int or float) truncates toward zero; a non-finite float (NaN/±∞) is a
     * RangeError. Other scalar inputs (numeric string, bool) are coerced through PHP's
     * int cast, faithfully reproducing the inlined original this replaces — which
     * relied on a loose `(int)` cast over the int|float|string|bool values the option
     * resolver produces — without its suppression. Operation-specific bounds (the
     * per-unit maximum and the even-divisibility check) are deliberately left at the
     * call sites; only the coerce + finite + ≥ 1 core lives here.
     *
     * Two-tier design: this is the Duration-facing core (no upper bound). Plain* and
     * ZonedDateTime use {@see CalendarMath::validateRoundingIncrement()}, which adds the
     * universal 1e9 upper bound for time-domain increments.
     *
     * The two RangeError messages match the {@see Temporal\Spec\Duration::round()}
     * original byte-for-byte (the test262 suite asserts on them).
     *
     * @throws RangeError if the value is a non-finite number or rounds to < 1.
     */
    public static function roundingIncrement(mixed $value): int
    {
        // Mirror the original `is_float($v) ? $v : (int) $v` shape: a float keeps its
        // NaN/±∞ check before truncation; int/string/bool go straight through the int
        // cast. Any other type never reaches here from the option resolver; it maps to
        // 0 so the ≥ 1 check rejects it (matching the original's effective behavior).
        if (is_float($value)) {
            // @infection-ignore-all || ⇒ && is equivalent under test262: is_nan and
            // is_infinite are mutually exclusive, so the && form never enters this branch,
            // but every non-finite float then casts to (int) 0 (PHP: (int) NAN/INF/-INF === 0)
            // and is rejected by the `< 1` check below — still a RangeError, only the message
            // differs, and test262 asserts the exception type, not the text.
            if (is_nan($value) || is_infinite($value)) {
                throw new RangeError('roundingIncrement must be a finite positive integer.');
            }
            $increment = (int) $value;
        } elseif (is_int($value) || is_string($value) || is_bool($value)) {
            $increment = (int) $value;
        } else {
            $increment = 0;
        }
        if ($increment < 1) {
            throw new RangeError('roundingIncrement must be at least 1.');
        }
        return $increment;
    }

    /**
     * TC39 GetOptionsObject: the options argument must be undefined (omitted) or an
     * object. An explicit `null` (or any other non-object primitive — those are
     * already rejected by the parameter type) is a TypeError. A Symbol reaching here
     * (a \Stringable whose __toString throws) is likewise a TypeError.
     *
     * Omitted options arrive as the empty-array default, which passes through as "no
     * options". A genuine options object/array is returned normalized to an array.
     *
     * @param array<array-key, mixed>|object|null $options
     * @return array<array-key, mixed>
     */
    public static function requireObject(array|object|null $options): array
    {
        if ($options === null) {
            throw new TypeError('options must be an object.');
        }
        if (is_object($options)) {
            if ($options instanceof Stringable) {
                // JsSymbol sentinel: __toString throws Temporal\Exception\TypeError.
                // For any other Stringable (e.g. JsUndefined which returns 'undefined'),
                // the cast succeeds and we fall through to get_object_vars.
                (string) $options;
            }
            return get_object_vars($options);
        }
        return $options;
    }

    /**
     * TC39 GetStringOrNumberOption(options, "fractionalSecondDigits", «"auto"», 0, 9, "auto"),
     * applied to an already-read value. A Number (int or float) is range-checked
     * (NaN/±∞ → RangeError) and floored to an integer in 0–9; any non-number value is
     * coerced via ToString and must equal "auto" (a JsSymbol sentinel's throwing
     * __toString surfaces as Temporal\Exception\TypeError, exactly as ToString(Symbol)
     * does in JS). Returns null for "auto" (the no-op default), or the digit count 0–9.
     *
     * @throws RangeError if the value is a non-finite/out-of-range number or a
     *                    non-number that does not stringify to "auto".
     */
    public static function fractionalSecondDigits(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            if (is_float($value)) {
                if (is_nan($value) || is_infinite($value)) {
                    throw new RangeError("fractionalSecondDigits must be 'auto' or a finite integer 0–9.");
                }
                $value = (int) floor($value);
            }
            if ($value < 0 || $value > 9) {
                throw new RangeError("fractionalSecondDigits {$value} is out of range (must be 0–9).");
            }
            return $value;
        }
        if ($value instanceof Stringable) {
            // JsSymbol sentinel: __toString throws Temporal\Exception\TypeError.
            $value = (string) $value;
        }
        if ($value !== 'auto') {
            throw new RangeError("fractionalSecondDigits must be 'auto' or an integer 0–9.");
        }
        return null;
    }
}
