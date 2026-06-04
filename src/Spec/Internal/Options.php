<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

use Stringable;
use Temporal\Exception\RangeError;

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
}
