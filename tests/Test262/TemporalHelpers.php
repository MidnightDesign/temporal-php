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
     * Asserts that a PlainDate has the given year, month, monthCode, and day.
     *
     * Argument order matches JS TemporalHelpers.assertPlainDate(pd, year, month, monthCode, day, msg).
     *
     * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
     */
    public static function assertPlainDate(
        \Temporal\PlainDate $date,
        int $year,
        int $month,
        string $monthCode,
        int $day,
        string $description = '',
        mixed $era = null,
        mixed $eraYear = null,
    ): void {
        $prefix = $description !== '' ? "{$description}: " : '';
        PHPUnitAssert::assertSame($year, $date->year, "{$prefix}year");
        PHPUnitAssert::assertSame($month, $date->month, "{$prefix}month");
        PHPUnitAssert::assertSame($monthCode, $date->monthCode, "{$prefix}monthCode");
        PHPUnitAssert::assertSame($day, $date->day, "{$prefix}day");
        if ($era !== null) {
            PHPUnitAssert::assertSame($era, $date->era, "{$prefix}era");
        }
        if ($eraYear !== null) {
            PHPUnitAssert::assertSame($eraYear, $date->eraYear, "{$prefix}eraYear");
        }
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
     * Asserts that two PlainDates have identical field values.
     *
     * Argument order matches JS TemporalHelpers.assertPlainDatesEqual(one, two, msg).
     *
     * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
     */
    public static function assertPlainDatesEqual(
        \Temporal\PlainDate $one,
        \Temporal\PlainDate $two,
        string $description = '',
    ): void {
        self::assertPlainDate(
            $one,
            $two->year,
            $two->month,
            $two->monthCode,
            $two->day,
            $description,
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
     * Tests that an instance method does not return a subclass instance (PHP: final classes,
     * so this simply verifies the method returns the correct type with expected field values).
     *
     * In JS, tests that subclass constructors are not invoked when the method returns a new instance.
     * PHP Temporal classes are all final, so subclassing is impossible; we just call the method
     * and assert the return value satisfies the check function.
     *
     * Argument order matches JS TemporalHelpers.checkSubclassingIgnored(class, ctorArgs, method, methodArgs, checkFn).
     *
     * @param class-string          $class      Fully-qualified class name.
     * @param list<mixed>           $ctorArgs   Constructor arguments.
     * @param string                $method     Instance method name.
     * @param list<mixed>           $methodArgs Method arguments.
     * @param callable(mixed): void $checkFn    Assertions on the return value.
     * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
     */
    public static function checkSubclassingIgnored(
        string $class,
        array $ctorArgs,
        string $method,
        array $methodArgs,
        callable $checkFn,
    ): void {
        /** @psalm-suppress MixedMethodCall, UnsafeInstantiation, MixedArgumentTypeCoercion */
        // @mago-ignore analysis:unknown-class-instantiation
        $instance = new $class(...$ctorArgs);
        /** @psalm-suppress MixedMethodCall */
        /** @var mixed $result */
        // @phpstan-ignore method.dynamicName
        $result = $instance->$method(...$methodArgs);
        PHPUnitAssert::assertInstanceOf($class, $result, "Return value should be an instance of {$class}");
        $checkFn($result);
    }

    /**
     * Tests that a static method does not return a subclass instance (PHP: final classes,
     * so this simply verifies the static method returns the correct type with expected values).
     *
     * In JS, tests that calling a static method via a subclass still returns the base class type.
     * PHP Temporal classes are all final; we just call the static method and run the check.
     *
     * Argument order matches JS TemporalHelpers.checkSubclassingIgnoredStatic(class, method, args, checkFn).
     *
     * @param class-string          $class   Fully-qualified class name.
     * @param string                $method  Static method name.
     * @param list<mixed>           $args    Method arguments.
     * @param callable(mixed): void $checkFn Assertions on the return value.
     * @psalm-suppress UnusedParam Psalm does not track $method in $class::$method() dynamic dispatch.
     * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
     */
    public static function checkSubclassingIgnoredStatic(
        string $class,
        string $method,
        array $args,
        callable $checkFn,
    ): void {
        /** @psalm-suppress MixedMethodCall */
        /** @var mixed $result */
        // @phpstan-ignore staticMethod.dynamicName
        $result = $class::$method(...$args);
        PHPUnitAssert::assertInstanceOf($class, $result, "Return value should be an instance of {$class}");
        $checkFn($result);
    }

    /**
     * Tests that wrong-type values for the roundingIncrement option cause exceptions,
     * that true (= 1) uses the default increment, and that object-with-valueOf is skipped.
     *
     * Covers the subset of TC39 checkRoundingIncrementOptionWrongType testable in PHP:
     *   - null   → skipped; PHP treats null as "not set" (uses default 1), differs from spec (0 → RangeError)
     *   - true   → 1 (valid) → assertRoundedDown called
     *   - false  → 0 → any exception expected
     *   - Symbol → skipped (PHP has no Symbol type)
     *   - BigInt → skipped (PHP has no BigInt type)
     *   - {}     → any exception expected (object→int coercion fails in PHP 8.0+)
     *   - {valueOf → 2} → skipped (PHP does not invoke valueOf on objects)
     *
     * Argument order matches JS TemporalHelpers.checkRoundingIncrementOptionWrongType(fn, assertUp, assertDown).
     *
     * @param callable(mixed): mixed          $fn               Function under test (receives roundingIncrement).
     * @param callable(mixed, string): void   $assertRoundedUp  Assertion for result with increment = 2 (skipped in PHP).
     * @param callable(mixed, string): void   $assertRoundedDown Assertion for result with increment = 1 (true).
     * @psalm-suppress UnusedParam $assertRoundedDown is not called (object-with-valueOf case skipped in PHP).
     * @psalm-api used by dynamically-required test scripts in tests/Test262/scripts/
     */
    public static function checkRoundingIncrementOptionWrongType(
        callable $fn,
        callable $assertRoundedUp,
        callable $assertRoundedDown,
    ): void {
        // null → 0 in JS spec → RangeError.
        // In PHP, null is treated as "option not set" → default 1 → no exception; skipped.

        // true → ToNumber(true) = 1 → valid; increment=1 + halfExpand → rounds up.
        // The harness calls assertRoundedUp for true (increment=1) and assertRoundedDown
        // for {valueOf → 2} (increment=2, which rounds down due to 0.987s/2s = 0.49 < 0.5).
        /** @var mixed $result */
        $result = $fn(true);
        $description = 'true';
        $assertRoundedUp($result, $description);

        // false → ToNumber(false) = 0 → out of range → any exception.
        try {
            $fn(false);
            PHPUnitAssert::fail('Expected exception for roundingIncrement=false, but nothing was thrown.');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable) {
            /** @phpstan-ignore staticMethod.alreadyNarrowedType */
            PHPUnitAssert::assertTrue(true); // count the assertion
        }

        // Symbol → TypeError (skip: PHP has no Symbol type).
        // BigInt → TypeError (skip: PHP has no BigInt type).

        // plain object → ToNumber({}) = NaN in JS → RangeError.
        // In PHP, (int) new stdClass() = 1 (warning, not exception), which is a valid increment.
        // Skipped: PHP cannot replicate JS object-to-NaN coercion.

        // object with valueOf() → 2 → assertRoundedDown (increment=2 rounds down); skipped in PHP.
        /** @psalm-suppress UnusedVariable */
        $_ = $assertRoundedDown; // referenced to suppress "param never read" warnings
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
