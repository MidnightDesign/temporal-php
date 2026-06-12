<?php

declare(strict_types=1);

namespace Temporal\Tests\Test262;

/**
 * PHP equivalents of JavaScript String/Array prototype methods used in test262
 * fixtures. All methods implement JS semantics faithfully so that transpiled
 * scripts behave identically to their JS originals.
 *
 * @psalm-api used by dynamically-required test262 scripts in tests/Test262/scripts/
 */
final class Js
{
    /**
     * Implements JS String.prototype.slice / Array.prototype.slice.
     *
     * For strings: indices are byte offsets (safe for ASCII date/offset strings).
     * For arrays: returns a re-indexed (list) sub-array.
     *
     * JS semantics:
     *  - Negative $start / $end count from the end of the value.
     *  - $end is an exclusive index (not a length).
     *  - Indices are clamped to [0, length]; out-of-bounds do not throw.
     *  - If $end is omitted the slice extends to the end.
     *  - If the resolved start >= resolved end, the result is empty (''/[]).
     *
     * @param string|list<mixed> $value
     * @return ($value is string ? string : list<mixed>)
     */
    public static function slice(string|array $value, int $start, ?int $end = null): string|array
    {
        if (is_string($value)) {
            return self::sliceString($value, $start, $end);
        }
        return self::sliceArray($value, $start, $end);
    }

    /**
     * Implements JS String(value) coercion.
     *
     * For most values this is equivalent to a (string) cast. The key difference
     * from PHP's cast is JsSymbol: in JS, String(Symbol()) returns "Symbol()"
     * without throwing, while interpolating a symbol throws TypeError. This method
     * mirrors the non-throwing String() path used in assertion message strings.
     *
     * @param mixed $value
     */
    public static function toString(mixed $value): string
    {
        if ($value instanceof JsSymbol) {
            return 'Symbol()';
        }
        // JS String([]) → "" (empty array), String([1,2]) → "1,2" (join with comma).
        // PHP's (string) [] triggers "Array to string conversion" warning; handle explicitly.
        if (is_array($value)) {
            /** @phpstan-ignore cast.string */
            return implode(',', array_map(static fn(mixed $v): string => (string) $v, $value));
        }

        /** @phpstan-ignore cast.string */
        return (string) $value;
    }

    /**
     * Implements JS Number.prototype.toPrecision(precision).
     *
     * Returns a string representation of $number with exactly $precision
     * significant digits, using exponential notation when necessary —
     * matching JS behaviour closely enough for test262 fixture comparisons.
     *
     * @psalm-api used by dynamically-required test262 scripts in tests/Test262/scripts/
     */
    public static function toPrecision(float $number, int $precision): string
    {
        if (!is_finite($number)) {
            return (string) $number;
        }
        // PHP's %g uses the shorter of %e/%f with the given significant digits.
        // sprintf("%.*g", $precision, $x) gives $precision significant digits — matching toPrecision(N).
        // The * width specifier injects $precision as an argument, avoiding string concatenation.
        $result = sprintf('%.*g', $precision, $number);
        // Normalise exponential notation: JS uses "e+7" and PHP uses "E+7"; lowercase.
        return strtolower($result);
    }

    /**
     * Implements JS String.prototype.startsWith.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    /**
     * Implements JS String.prototype.endsWith.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    /**
     * Implements JS String.prototype.includes / Array.prototype.includes.
     *
     * For strings: delegates to str_contains (position argument is not used
     * by any of the temporal fixtures and is intentionally ignored).
     * For arrays: uses strict (SameValueZero) comparison via in_array with
     * strict=true, which is correct for the numeric/string members in these
     * fixtures.
     *
     * @param string|list<mixed> $haystack
     * @param string|int $needle
     */
    public static function includes(string|array $haystack, string|int $needle): bool
    {
        if (is_string($haystack)) {
            return str_contains($haystack, (string) $needle);
        }
        return in_array($needle, $haystack, strict: true);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function sliceString(string $str, int $start, ?int $end): string
    {
        $len = strlen($str);
        $realStart = $start >= 0 ? min($start, $len) : max($start + $len, 0);
        if ($end === null) {
            $realEnd = $len;
        } else {
            $realEnd = $end >= 0 ? min($end, $len) : max($end + $len, 0);
        }
        if ($realStart >= $realEnd) {
            return '';
        }
        return substr($str, $realStart, $realEnd - $realStart);
    }

    /**
     * @param list<mixed> $arr
     * @return list<mixed>
     */
    private static function sliceArray(array $arr, int $start, ?int $end): array
    {
        $len = count($arr);
        $realStart = $start >= 0 ? min($start, $len) : max($start + $len, 0);
        if ($end === null) {
            $length = $len - $realStart;
        } else {
            $realEnd = $end >= 0 ? min($end, $len) : max($end + $len, 0);
            $length = max(0, $realEnd - $realStart);
        }
        /** @var list<mixed> */
        return array_slice($arr, $realStart, $length);
    }
}
