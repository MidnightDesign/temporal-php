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
        PHPUnitAssert::assertSame($years, $duration->years, "{$prefix}years");
        PHPUnitAssert::assertSame($months, $duration->months, "{$prefix}months");
        PHPUnitAssert::assertSame($weeks, $duration->weeks, "{$prefix}weeks");
        PHPUnitAssert::assertSame($days, $duration->days, "{$prefix}days");
        PHPUnitAssert::assertSame($hours, $duration->hours, "{$prefix}hours");
        PHPUnitAssert::assertSame($minutes, $duration->minutes, "{$prefix}minutes");
        PHPUnitAssert::assertSame($seconds, $duration->seconds, "{$prefix}seconds");
        PHPUnitAssert::assertSame($milliseconds, $duration->milliseconds, "{$prefix}milliseconds");
        PHPUnitAssert::assertSame($microseconds, $duration->microseconds, "{$prefix}microseconds");
        PHPUnitAssert::assertSame($nanoseconds, $duration->nanoseconds, "{$prefix}nanoseconds");
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
}
