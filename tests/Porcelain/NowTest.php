<?php

declare(strict_types=1);

namespace Temporal\Tests\Porcelain;

use PHPUnit\Framework\TestCase;
use Temporal\Instant;
use Temporal\Now;
use Temporal\PlainDate;
use Temporal\PlainDateTime;
use Temporal\PlainTime;

final class NowTest extends TestCase
{
    // -------------------------------------------------------------------------
    // timeZoneId
    // -------------------------------------------------------------------------

    public function testTimeZoneIdReturnsSystemDefault(): void
    {
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('America/New_York');
            static::assertSame('America/New_York', Now::timeZoneId());

            date_default_timezone_set('Europe/London');
            static::assertSame('Europe/London', Now::timeZoneId());
        } finally {
            date_default_timezone_set($original);
        }
    }

    // -------------------------------------------------------------------------
    // instant
    // -------------------------------------------------------------------------

    public function testInstantReturnsPorcelainInstant(): void
    {
        $instant = Now::instant();

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertInstanceOf(Instant::class, $instant);
    }

    public function testInstantEpochNanosecondsAreReasonable(): void
    {
        $before = (int) (microtime(true) * 1_000_000.0) * 1_000;
        $instant = Now::instant();
        $after = (int) (microtime(true) * 1_000_000.0) * 1_000;

        static::assertGreaterThanOrEqual($before, $instant->epochNanoseconds);
        static::assertLessThanOrEqual($after, $instant->epochNanoseconds);
    }

    // -------------------------------------------------------------------------
    // plainDate
    // -------------------------------------------------------------------------

    public function testPlainDateReturnsPorcelainPlainDate(): void
    {
        $date = Now::plainDate('UTC');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertInstanceOf(PlainDate::class, $date);
    }

    public function testPlainDateWithoutArgumentUsesSystemDefault(): void
    {
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $date = Now::plainDate();

            static::assertSame((int) $dt->format('Y'), $date->year);
            static::assertSame((int) $dt->format('n'), $date->month);
            static::assertSame((int) $dt->format('j'), $date->day);
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testPlainDateWithExplicitTimeZone(): void
    {
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('Pacific/Auckland'));
        $date = Now::plainDate('Pacific/Auckland');

        static::assertSame((int) $dt->format('Y'), $date->year);
        static::assertSame((int) $dt->format('n'), $date->month);
        static::assertSame((int) $dt->format('j'), $date->day);
    }

    public function testPlainDateExplicitNullThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        Now::plainDate(null);
    }

    public function testPlainDateEmptyStringThrowsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Now::plainDate('');
    }

    // -------------------------------------------------------------------------
    // plainTime
    // -------------------------------------------------------------------------

    public function testPlainTimeReturnsPorcelainPlainTime(): void
    {
        $time = Now::plainTime('UTC');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertInstanceOf(PlainTime::class, $time);
    }

    public function testPlainTimeWithoutArgumentUsesSystemDefault(): void
    {
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $time = Now::plainTime();

            // Allow a 2-second window for clock drift between the two calls.
            $expectedSeconds = ((int) $dt->format('G') * 3600) + ((int) $dt->format('i') * 60) + (int) $dt->format('s');
            $actualSeconds = ($time->hour * 3600) + ($time->minute * 60) + $time->second;
            static::assertEqualsWithDelta($expectedSeconds, $actualSeconds, 2);
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testPlainTimeExplicitNullThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        Now::plainTime(null);
    }

    public function testPlainTimeEmptyStringThrowsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Now::plainTime('');
    }

    // -------------------------------------------------------------------------
    // plainDateTime
    // -------------------------------------------------------------------------

    public function testPlainDateTimeReturnsPorcelainPlainDateTime(): void
    {
        $pdt = Now::plainDateTime('UTC');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertInstanceOf(PlainDateTime::class, $pdt);
    }

    public function testPlainDateTimeWithoutArgumentUsesSystemDefault(): void
    {
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $pdt = Now::plainDateTime();

            static::assertSame((int) $dt->format('Y'), $pdt->year);
            static::assertSame((int) $dt->format('n'), $pdt->month);
            static::assertSame((int) $dt->format('j'), $pdt->day);
            // Allow a 2-second window for the time component.
            $expectedSeconds = ((int) $dt->format('G') * 3600) + ((int) $dt->format('i') * 60) + (int) $dt->format('s');
            $actualSeconds = ($pdt->hour * 3600) + ($pdt->minute * 60) + $pdt->second;
            static::assertEqualsWithDelta($expectedSeconds, $actualSeconds, 2);
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testPlainDateTimeExplicitNullThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        Now::plainDateTime(null);
    }

    public function testPlainDateTimeEmptyStringThrowsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Now::plainDateTime('');
    }

    // -------------------------------------------------------------------------
    // zonedDateTime
    // -------------------------------------------------------------------------

    public function testZonedDateTimeReturnsPorcelainZonedDateTime(): void
    {
        if (!class_exists(\Temporal\ZonedDateTime::class)) {
            static::markTestSkipped('Porcelain ZonedDateTime class not yet implemented.');
        }
        $zdt = Now::zonedDateTime('UTC');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertInstanceOf(\Temporal\ZonedDateTime::class, $zdt);
    }

    public function testZonedDateTimeWithoutArgumentUsesSystemDefault(): void
    {
        if (!class_exists(\Temporal\ZonedDateTime::class)) {
            static::markTestSkipped('Porcelain ZonedDateTime class not yet implemented.');
        }
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            // Should not throw -- omitted argument uses system default.
            $zdt = Now::zonedDateTime();
            /** @phpstan-ignore staticMethod.alreadyNarrowedType */
            static::assertInstanceOf(\Temporal\ZonedDateTime::class, $zdt);
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testZonedDateTimeExplicitNullThrowsTypeError(): void
    {
        if (!class_exists(\Temporal\ZonedDateTime::class)) {
            static::markTestSkipped('Porcelain ZonedDateTime class not yet implemented.');
        }
        $this->expectException(\TypeError::class);
        Now::zonedDateTime(null);
    }

    public function testZonedDateTimeEmptyStringThrowsInvalidArgument(): void
    {
        if (!class_exists(\Temporal\ZonedDateTime::class)) {
            static::markTestSkipped('Porcelain ZonedDateTime class not yet implemented.');
        }
        $this->expectException(\InvalidArgumentException::class);
        Now::zonedDateTime('');
    }

    // -------------------------------------------------------------------------
    // Not instantiable
    // -------------------------------------------------------------------------

    public function testNotInstantiable(): void
    {
        $ref = new \ReflectionClass(Now::class);

        static::assertFalse($ref->isInstantiable());
    }
}
