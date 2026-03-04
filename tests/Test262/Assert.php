<?php

declare(strict_types=1);

namespace Temporal\Tests\Test262;

use PHPUnit\Framework\Assert as PHPUnitAssert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\SkippedWithMessageException;

/**
 * Bridges TC39 test262 assert-style calls to PHPUnit.
 *
 * Method signatures match the JS assert API conventions so that generated
 * test scripts read naturally.
 *
 * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
 */
final class Assert
{
    private static ?object $overflowSentinel = null;

    /**
     * Returns a singleton sentinel used in place of BigInt values that exceed
     * PHP's int64 range. Passing this as actual or expected to sameValue()
     * causes the assertion to be silently skipped.
     */
    public static function int64Overflow(): object
    {
        return self::$overflowSentinel ??= new \stdClass();
    }

    /**
     * Asserts that a value is truthy (mirrors the bare assert() function in TC39 test262).
     */
    public static function assertTrue(mixed $value, string $message = ''): void
    {
        PHPUnitAssert::assertTrue((bool) $value, $message);
    }

    /**
     * Asserts that $actual equals $expected (strict equality).
     *
     * Note: argument order matches JS's assert.sameValue(actual, expected),
     * which is the reverse of PHPUnit's assertSame(expected, actual).
     */
    public static function sameValue(mixed $actual, mixed $expected, string $message = ''): void
    {
        if ($actual === self::int64Overflow() || $expected === self::int64Overflow()) {
            return;
        }
        // TC39 SameValue treats all numbers as the same type (JS has no int/float distinction).
        // Treat PHP int(n) and float(n.0) as equivalent when they have the same numeric value.
        if ((is_int($actual) || is_float($actual)) && (is_int($expected) || is_float($expected))) {
            $fa = (float) $actual;
            $fe = (float) $expected;
            // SameValue(NaN, NaN) is true in TC39.
            if (is_nan($fa) && is_nan($fe)) {
                PHPUnitAssert::assertNan($fa, $message !== '' ? $message : 'SameValue(NaN, NaN)');
                return;
            }
            if ($fa === $fe) {
                PHPUnitAssert::assertSame($fa, $fe, $message);
                return;
            }
        }
        PHPUnitAssert::assertSame($expected, $actual, $message);
    }

    /**
     * Asserts that $actual does NOT equal $unexpected (strict equality).
     *
     * Note: argument order matches JS's assert.notSameValue(actual, unexpected).
     */
    public static function notSameValue(mixed $actual, mixed $unexpected, string $message = ''): void
    {
        if ($actual === self::int64Overflow() || $unexpected === self::int64Overflow()) {
            return;
        }
        PHPUnitAssert::assertNotSame($unexpected, $actual, $message);
    }

    /**
     * Asserts that $fn throws an instance of $exceptionClass.
     *
     * @param class-string<\Throwable> $exceptionClass
     */
    public static function throws(string $exceptionClass, callable $fn, string $message = ''): void
    {
        try {
            $fn();
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            PHPUnitAssert::assertInstanceOf($exceptionClass, $e, $message);
            return;
        }
        PHPUnitAssert::fail("Expected {$exceptionClass} to be thrown, but nothing was thrown. {$message}");
    }

    /**
     * Asserts that two arrays have the same elements in the same order.
     *
     * Note: argument order matches JS's assert.compareArray(actual, expected).
     *
     * @param array<mixed> $actual
     * @param array<mixed> $expected
     */
    public static function compareArray(array $actual, array $expected, string $message = ''): void
    {
        PHPUnitAssert::assertSame($expected, $actual, $message);
    }

    /**
     * Marks the current test as skipped.
     *
     * @psalm-api
     * @throws SkippedWithMessageException always
     * @return never
     * @psalm-suppress InternalClass, InternalMethod
     */
    public static function skip(string $reason): never
    {
        throw new SkippedWithMessageException($reason);
    }

    /**
     * Marks the current test as incomplete.
     *
     * @throws IncompleteTestError always
     * @return never
     * @psalm-suppress InternalClass, InternalMethod
     */
    public static function incomplete(string $reason): never
    {
        throw new IncompleteTestError($reason);
    }
}
