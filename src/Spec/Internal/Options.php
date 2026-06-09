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
    public static function coerceEnumOption(mixed $value, string $invalidMessage): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new RangeError($invalidMessage);
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
}
