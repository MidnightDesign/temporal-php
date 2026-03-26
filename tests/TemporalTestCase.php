<?php

declare(strict_types=1);

namespace Temporal\Tests;

use PHPUnit\Framework\TestCase;
use Temporal\Duration;
use Temporal\PlainDate;
use Temporal\PlainDateTime;
use Temporal\PlainMonthDay;
use Temporal\PlainTime;
use Temporal\PlainYearMonth;

abstract class TemporalTestCase extends TestCase
{
    public const int YEAR_MIN = -271821;
    public const int YEAR_MAX = 275760;

    protected function assertPlainDateIs(int $year, int $month, int $day, PlainDate $date, string $message = ''): void
    {
        $prefix = $message !== '' ? "$message: " : '';
        static::assertSame($year, $date->year, "{$prefix}year");
        static::assertSame($month, $date->month, "{$prefix}month");
        static::assertSame($day, $date->day, "{$prefix}day");
    }

    protected function assertPlainTimeIs(int $h, int $min, int $sec, int $ms, int $us, int $ns, PlainTime $time, string $message = ''): void
    {
        $prefix = $message !== '' ? "$message: " : '';
        static::assertSame($h, $time->hour, "{$prefix}hour");
        static::assertSame($min, $time->minute, "{$prefix}minute");
        static::assertSame($sec, $time->second, "{$prefix}second");
        static::assertSame($ms, $time->millisecond, "{$prefix}millisecond");
        static::assertSame($us, $time->microsecond, "{$prefix}microsecond");
        static::assertSame($ns, $time->nanosecond, "{$prefix}nanosecond");
    }

    protected function assertPlainDateTimeIs(int $year, int $month, int $day, int $h, int $min, int $sec, int $ms, int $us, int $ns, PlainDateTime $dt, string $message = ''): void
    {
        $prefix = $message !== '' ? "$message: " : '';
        static::assertSame($year, $dt->year, "{$prefix}year");
        static::assertSame($month, $dt->month, "{$prefix}month");
        static::assertSame($day, $dt->day, "{$prefix}day");
        static::assertSame($h, $dt->hour, "{$prefix}hour");
        static::assertSame($min, $dt->minute, "{$prefix}minute");
        static::assertSame($sec, $dt->second, "{$prefix}second");
        static::assertSame($ms, $dt->millisecond, "{$prefix}millisecond");
        static::assertSame($us, $dt->microsecond, "{$prefix}microsecond");
        static::assertSame($ns, $dt->nanosecond, "{$prefix}nanosecond");
    }

    protected function assertDurationIs(int $years, int $months, int $weeks, int $days, int $hours, int $minutes, int $seconds, int $ms, int $us, int $ns, Duration $d, string $message = ''): void
    {
        $prefix = $message !== '' ? "$message: " : '';
        static::assertSame($years, $d->years, "{$prefix}years");
        static::assertSame($months, $d->months, "{$prefix}months");
        static::assertSame($weeks, $d->weeks, "{$prefix}weeks");
        static::assertSame($days, $d->days, "{$prefix}days");
        static::assertSame($hours, $d->hours, "{$prefix}hours");
        static::assertSame($minutes, $d->minutes, "{$prefix}minutes");
        static::assertSame($seconds, $d->seconds, "{$prefix}seconds");
        static::assertSame($ms, $d->milliseconds, "{$prefix}milliseconds");
        static::assertSame($us, $d->microseconds, "{$prefix}microseconds");
        static::assertSame($ns, $d->nanoseconds, "{$prefix}nanoseconds");
    }

    protected function assertPlainYearMonthIs(int $year, int $month, PlainYearMonth $ym, string $message = ''): void
    {
        $prefix = $message !== '' ? "$message: " : '';
        static::assertSame($year, $ym->year, "{$prefix}year");
        static::assertSame($month, $ym->month, "{$prefix}month");
    }

    protected function assertPlainMonthDayIs(int $month, int $day, PlainMonthDay $md, string $message = ''): void
    {
        $prefix = $message !== '' ? "$message: " : '';
        static::assertSame($month, $md->month, "{$prefix}month");
        static::assertSame($day, $md->day, "{$prefix}day");
    }
}
