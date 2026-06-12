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
 * @internal
 */
final class Options
{
    /**
     * Canonical TC39 rounding-mode keyword set (RoundingMode enum). The order matches
     * the spec's enumeration. Validated against by {@see self::roundingMode()}.
     *
     * This is the strict set only — it deliberately excludes the legacy ECMA-402
     * aliases "truncate"/"ceiling", which a handful of {@see Temporal\Spec\Duration}
     * paths still accept via their own normalizer; those are NOT routed through this
     * constant.
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
     * token (e.g. "smallestUnit", "disambiguation"). No test asserts on the message
     * text — `tests/Test262/Assert.php::throws()` checks only the exception CLASS, and
     * the project's PHPUnit suites have no message assertions — so the wording is free;
     * only the RangeError TYPE is contractual.
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
     * Both RangeError messages are owned here. No test asserts on the message text —
     * `tests/Test262/Assert.php::throws()` checks only the exception CLASS, and the
     * project's PHPUnit suites have no message assertions — so the wording is free;
     * only the RangeError TYPE is contractual.
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
     * Normalizes an options bag and resolves its `overflow` field to a validated
     * "constrain" / "reject" keyword, defaulting to "constrain" when the bag is null
     * (an omitted options argument) or has no `overflow` key.
     *
     * Absorbs the GetOptionsObject + default-to-"constrain" scaffolding that the
     * Plain... and ZonedDateTime `from`/`with`/`add` paths copy-pasted, delegating the
     * keyword coercion/validation to {@see self::overflowOption()}.
     *
     * An explicit `overflow => null` VALUE is treated as the default "constrain" only
     * when $nullValueIsDefault is true (the PlainTime contract); otherwise it falls
     * through to {@see self::overflowOption()}, where a null coerces to neither
     * keyword and is a RangeError (the contract every other class preserves).
     *
     * The null-OPTIONS-argument distinction (default "constrain" vs. a GetOptionsObject
     * TypeError) is intentionally left to the caller: PlainMonthDay/PlainYearMonth run
     * {@see self::requireObject()} (TypeError) before delegating here, while the others
     * pass a null through to the default. So this helper always defaults a null bag.
     *
     * @param array<array-key, mixed>|object|null $options
     * @param bool $nullValueIsDefault When true, an explicit `overflow => null` value
     *                                 resolves to "constrain" instead of raising.
     * @throws RangeError per {@see self::overflowOption()}.
     */
    public static function overflowFromBag(array|object|null $options, bool $nullValueIsDefault = false): string
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
        /** @var mixed $value */
        $value = $options['overflow'];
        if ($nullValueIsDefault && $value === null) {
            return 'constrain';
        }
        return self::overflowOption($value);
    }

    /**
     * Validates an already-coerced `roundingMode` string against the canonical
     * {@see self::ROUNDING_MODES} set and returns it unchanged.
     *
     * Replaces the ~7 inline `!in_array($mode, ROUNDING_MODES, true)` checks. The
     * RangeError message is owned here and embeds the offending value. No test asserts
     * on the message text — `tests/Test262/Assert.php::throws()` checks only the
     * exception CLASS, and the project's PHPUnit suites have no message assertions — so
     * the wording is free; only the RangeError TYPE is contractual.
     *
     * This validates the STRICT keyword set only; the legacy "truncate"/"ceiling"
     * aliases some {@see Temporal\Spec\Duration} normalizers accept are intentionally
     * NOT recognized here and must keep their bespoke handling.
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
                (string) $options;
                throw new TypeError('options must be an object.');
            }
            return get_object_vars($options);
        }
        return $options;
    }

    /**
     * TC39 GetOptionsObject variant that also permits an explicit null (the
     * spec-layer sentinel for JS `undefined`). A non-null, non-array, non-object
     * primitive (int/float/string/bool) — or a Symbol sentinel (a \Stringable whose
     * __toString throws) — is a spec-layer TypeError. null, array and object pass
     * through unchanged.
     *
     * @return array<array-key, mixed>|object|null
     */
    public static function requireObjectOrNull(mixed $options): array|object|null
    {
        if ($options === null || is_array($options)) {
            return $options;
        }
        if (is_object($options)) {
            if ($options instanceof Stringable) {
                // JsSymbol sentinel: __toString throws Temporal\Exception\TypeError.
                (string) $options;
                throw new TypeError('options must be an object.');
            }
            return $options;
        }
        throw new TypeError('options must be an object.');
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

    /**
     * TC39 ToIntegerWithTruncation applied to an already-finiteness-validated
     * property-bag field. The value has been checked for Infinity/NaN upstream
     * (see {@see Temporal\Spec\Duration}'s relativeTo validation), so all that
     * remains is to truncate toward zero: an int passes through unchanged, every
     * other finite numeric/coercible value goes through PHP's `(int)` cast, which
     * truncates toward zero exactly as ToIntegerWithTruncation specifies.
     */
    public static function toIntegerTruncation(mixed $value): int
    {
        /** @phpstan-ignore cast.int */
        return is_int($value) ? $value : (int) $value;
    }
}
