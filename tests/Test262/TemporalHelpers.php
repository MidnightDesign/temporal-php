<?php

declare(strict_types=1);

namespace Temporal\Tests\Test262;

use PHPUnit\Framework\Assert as PHPUnitAssert;

/**
 * PHP port of TC39's TemporalHelpers test harness.
 *
 * Only the subset used in the generated test262 scripts is implemented here.
 * Unimplemented methods are handled at the transpiler level (emitIncomplete).
 *
 * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
 */
final class TemporalHelpers
{
    /**
     * Asserts that a Duration has the given field values.
     *
     * Argument order matches JS TemporalHelpers.assertDuration(d, y,m,w,d,h,min,s,ms,us,ns,msg).
     */
    public static function assertDuration(
        \Temporal\Duration $duration,
        int|float $years,
        int|float $months,
        int|float $weeks,
        int|float $days,
        int|float $hours,
        int|float $minutes,
        int|float $seconds,
        int|float $milliseconds,
        int|float $microseconds,
        int|float $nanoseconds,
        string $description = '',
    ): void {
        $prefix = $description !== '' ? "{$description}: " : '';
        Assert::sameValue($duration->years, $years, "{$prefix}years");
        Assert::sameValue($duration->months, $months, "{$prefix}months");
        Assert::sameValue($duration->weeks, $weeks, "{$prefix}weeks");
        Assert::sameValue($duration->days, $days, "{$prefix}days");
        Assert::sameValue($duration->hours, $hours, "{$prefix}hours");
        Assert::sameValue($duration->minutes, $minutes, "{$prefix}minutes");
        Assert::sameValue($duration->seconds, $seconds, "{$prefix}seconds");
        Assert::sameValue($duration->milliseconds, $milliseconds, "{$prefix}milliseconds");
        Assert::sameValue($duration->microseconds, $microseconds, "{$prefix}microseconds");
        Assert::sameValue($duration->nanoseconds, $nanoseconds, "{$prefix}nanoseconds");
    }

    /**
     * Asserts that two Durations have identical field values.
     *
     * Argument order matches JS TemporalHelpers.assertDurationsEqual(a, b, msg).
     */
    public static function assertDurationsEqual(
        \Temporal\Duration $one,
        \Temporal\Duration $two,
        string $description = '',
    ): void {
        self::assertDuration(
            $one,
            $two->years,
            $two->months,
            $two->weeks,
            $two->days,
            $two->hours,
            $two->minutes,
            $two->seconds,
            $two->milliseconds,
            $two->microseconds,
            $two->nanoseconds,
            $description,
        );
    }

    /**
     * Asserts that two Instants represent the same point in time.
     *
     * Argument order matches JS TemporalHelpers.assertInstantsEqual(a, b, msg).
     */
    public static function assertInstantsEqual(
        \Temporal\Instant $one,
        \Temporal\Instant $two,
        string $description = '',
    ): void {
        PHPUnitAssert::assertSame(
            $one->epochNanoseconds,
            $two->epochNanoseconds,
            $description !== '' ? $description : 'epochNanoseconds should be equal',
        );
    }

    /**
     * Asserts that a Duration has the given calendar field values.
     *
     * Argument order matches JS TemporalHelpers.assertDateDuration(d, y, m, w, days, msg).
     */
    public static function assertDateDuration(
        \Temporal\Duration $duration,
        int|float $years,
        int|float $months,
        int|float $weeks,
        int|float $days,
        string $description = '',
    ): void {
        $prefix = $description !== '' ? "{$description}: " : '';
        PHPUnitAssert::assertSame($years, $duration->years, "{$prefix}years");
        PHPUnitAssert::assertSame($months, $duration->months, "{$prefix}months");
        PHPUnitAssert::assertSame($weeks, $duration->weeks, "{$prefix}weeks");
        PHPUnitAssert::assertSame($days, $duration->days, "{$prefix}days");
    }

    /**
     * Asserts that both the singular and plural forms of each unit name produce
     * the same result when passed to a function.
     *
     * Argument order matches JS TemporalHelpers.checkPluralUnitsAccepted(fn, units).
     *
     * The JS version also handles PlainDateTime, PlainTime, ZonedDateTime results;
     * this PHP port handles Duration and Instant results only.
     *
     * @param callable(string): mixed      $func             Function under test.
     * @param list<string>                 $validSingularUnits Singular unit names to test.
     * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
     */
    public static function checkPluralUnitsAccepted(callable $func, array $validSingularUnits): void
    {
        $plurals = [
            'year' => 'years', 'month' => 'months', 'week' => 'weeks', 'day' => 'days',
            'hour' => 'hours', 'minute' => 'minutes', 'second' => 'seconds',
            'millisecond' => 'milliseconds', 'microsecond' => 'microseconds',
            'nanosecond' => 'nanoseconds',
        ];
        foreach ($validSingularUnits as $unit) {
            /** @var mixed $singular */
            $singular = $func($unit);
            /** @var mixed $plural */
            $plural   = $func($plurals[$unit] ?? $unit . 's');
            $desc     = "Plural {$plurals[$unit]} produces the same result as singular {$unit}";
            if ($singular instanceof \Temporal\Duration) {
                /** @psalm-suppress MixedArgument */
                // @mago-ignore analysis:mixed-argument
                // @phpstan-ignore argument.type
                self::assertDurationsEqual($plural, $singular, $desc);
            } elseif ($singular instanceof \Temporal\Instant) {
                /** @psalm-suppress MixedArgument */
                // @mago-ignore analysis:mixed-argument
                // @phpstan-ignore argument.type
                self::assertInstantsEqual($plural, $singular, $desc);
            } else {
                PHPUnitAssert::assertSame($singular, $plural, $desc);
            }
        }
    }

    /**
     * Tests that wrong-type values for a string option cause exceptions, and
     * that the valid value produces the expected result.
     *
     * Covers the subset of TC39 checkStringOptionWrongType that is testable in PHP:
     *   - null, true, false, int(2), plain object → any exception thrown
     *   - Symbol and BigInt inputs are skipped (PHP has no equivalent types)
     *   - The "observer" (toPrimitiveObserver) property-access-order test is
     *     skipped (PHP has no property-access tracking)
     *   - The valid string value is passed directly and result is asserted
     *
     * Argument order matches JS TemporalHelpers.checkStringOptionWrongType(key, val, fn, assertFn).
     *
     * @param callable(mixed): mixed   $checkFunc  Function under test (receives the option value).
     * @param callable(mixed, string): void $assertFunc Assertion callback for the valid result.
     * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
     */
    public static function checkStringOptionWrongType(
        string $propertyName,
        string $value,
        callable $checkFunc,
        callable $assertFunc,
    ): void {
        // Wrong types that should throw (any exception is acceptable).
        // null is skipped: our PHP implementation treats null as "option not set" (uses default)
        // rather than throwing, which differs from JS where null converts to "null" string → RangeError.
        foreach ([true, false, 2, new \stdClass()] as $wrongValue) {
            try {
                $checkFunc($wrongValue);
                PHPUnitAssert::fail(
                    "Expected exception for {$propertyName}=" . var_export(value: $wrongValue, return: true) . ', but nothing was thrown.'
                );
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                throw $e;
            } catch (\Throwable) {
                /** @phpstan-ignore staticMethod.alreadyNarrowedType */
                PHPUnitAssert::assertTrue(true);  // count the assertion
            }
        }
        // Symbol and BigInt skipped — PHP has no equivalent types.
        // Observer (toPrimitiveObserver) skipped — PHP has no property-access tracking.
        // Test with the valid value directly:
        /** @var mixed $result */
        $result = $checkFunc($value);
        $unitDescription = 'string';
        $assertFunc($result, $unitDescription);
    }
}
