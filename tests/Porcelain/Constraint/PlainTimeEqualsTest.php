<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain\Constraint;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Temporal\PlainTime;

final class PlainTimeEqualsTest extends TestCase
{
    public function testMatchesIdenticalPlainTime(): void
    {
        $constraint = new PlainTimeEquals(13, 45, 30, 123, 456, 789);
        $time = new PlainTime(13, 45, 30, 123, 456, 789);

        self::assertTrue($constraint->evaluate($time, '', true));
    }

    public function testMatchesAllZeros(): void
    {
        $constraint = new PlainTimeEquals(0, 0, 0, 0, 0, 0);
        $time = new PlainTime();

        self::assertTrue($constraint->evaluate($time, '', true));
    }

    public function testDoesNotMatchDifferentHour(): void
    {
        $constraint = new PlainTimeEquals(10, 0, 0, 0, 0, 0);
        $time = new PlainTime(11, 0, 0, 0, 0, 0);

        self::assertFalse($constraint->evaluate($time, '', true));
    }

    public function testDoesNotMatchDifferentNanosecond(): void
    {
        $constraint = new PlainTimeEquals(13, 45, 30, 123, 456, 789);
        $time = new PlainTime(13, 45, 30, 123, 456, 0);

        self::assertFalse($constraint->evaluate($time, '', true));
    }

    public function testDoesNotMatchNonPlainTimeValue(): void
    {
        $constraint = new PlainTimeEquals(0, 0, 0, 0, 0, 0);

        self::assertFalse($constraint->evaluate('not a PlainTime', '', true));
    }

    public function testToStringFormatsAsTimeString(): void
    {
        $constraint = new PlainTimeEquals(13, 45, 30, 123, 456, 789);

        self::assertSame('is PlainTime 13:45:30.123456789', $constraint->toString());
    }

    public function testToStringPadsZeros(): void
    {
        $constraint = new PlainTimeEquals(1, 2, 3, 4, 5, 6);

        self::assertSame('is PlainTime 01:02:03.004005006', $constraint->toString());
    }

    public function testFailureMessageShowsMismatchedFields(): void
    {
        $constraint = new PlainTimeEquals(10, 30, 45, 0, 0, 0);
        $time = new PlainTime(10, 31, 0, 0, 0, 0);

        try {
            $constraint->evaluate($time);
            self::fail('Expected ExpectationFailedException');
        } catch (ExpectationFailedException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('minute: expected 30, actual 31', $message);
            self::assertStringContainsString('second: expected 45, actual 0', $message);
            self::assertStringNotContainsString('hour:', $message);
        }
    }

    public function testFailureMessageIncludesCustomMessage(): void
    {
        $constraint = new PlainTimeEquals(10, 0, 0, 0, 0, 0);
        $time = new PlainTime(11, 0, 0, 0, 0, 0);

        try {
            $constraint->evaluate($time, 'custom context');
            self::fail('Expected ExpectationFailedException');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('custom context', $e->getMessage());
        }
    }
}
