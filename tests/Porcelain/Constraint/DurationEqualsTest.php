<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain\Constraint;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Temporal\Duration;

final class DurationEqualsTest extends TestCase
{
    public function testMatchesIdenticalDuration(): void
    {
        $constraint = new DurationEquals(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
        $duration = new Duration(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

        self::assertTrue($constraint->evaluate($duration, '', true));
    }

    public function testMatchesAllZeros(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $duration = new Duration();

        self::assertTrue($constraint->evaluate($duration, '', true));
    }

    public function testDoesNotMatchDifferentHours(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 1, 30, 0, 0, 0, 0);
        $duration = new Duration(hours: 2, minutes: 30);

        self::assertFalse($constraint->evaluate($duration, '', true));
    }

    public function testDoesNotMatchDifferentNanoseconds(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 0, 0, 0, 0, 0, 100);
        $duration = new Duration(nanoseconds: 200);

        self::assertFalse($constraint->evaluate($duration, '', true));
    }

    public function testDoesNotMatchNonDurationValue(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

        self::assertFalse($constraint->evaluate('not a Duration', '', true));
    }

    public function testToStringShowsOnlyNonZeroFields(): void
    {
        $constraint = new DurationEquals(1, 2, 0, 0, 0, 0, 0, 0, 0, 0);

        self::assertSame('is Duration {years: 1, months: 2}', $constraint->toString());
    }

    public function testToStringAllZerosShowsEmptyBraces(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

        self::assertSame('is Duration {}', $constraint->toString());
    }

    public function testFailureMessageShowsMismatchedFields(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 1, 30, 45, 0, 0, 0);
        $duration = new Duration(hours: 2, minutes: 31);

        try {
            $constraint->evaluate($duration);
            self::fail('Expected ExpectationFailedException');
        } catch (ExpectationFailedException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('hours: expected 1, actual 2', $message);
            self::assertStringContainsString('minutes: expected 30, actual 31', $message);
            self::assertStringContainsString('seconds: expected 45, actual 0', $message);
            self::assertStringNotContainsString('years:', $message);
        }
    }

    public function testFailureDescriptionShowsActualAndExpected(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 1, 30, 45, 0, 0, 0);
        $duration = new Duration(hours: 2, minutes: 31);

        try {
            $constraint->evaluate($duration);
            self::fail('Expected ExpectationFailedException');
        } catch (ExpectationFailedException $e) {
            $message = $e->getMessage();
            // Actual: Duration {hours: 2, minutes: 31}
            self::assertStringContainsString('Duration {hours: 2, minutes: 31}', $message);
            // Expected: is Duration {hours: 1, minutes: 30, seconds: 45}
            self::assertStringContainsString('Duration {hours: 1, minutes: 30, seconds: 45}', $message);
        }
    }

    public function testFailureMessageIncludesCustomMessage(): void
    {
        $constraint = new DurationEquals(0, 0, 0, 0, 1, 0, 0, 0, 0, 0);
        $duration = new Duration(hours: 2);

        try {
            $constraint->evaluate($duration, 'custom context');
            self::fail('Expected ExpectationFailedException');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('custom context', $e->getMessage());
        }
    }
}
