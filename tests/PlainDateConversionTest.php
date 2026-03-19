<?php

declare(strict_types=1);

namespace Temporal\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Temporal\PlainDate;
use Temporal\PlainDateTime;
use Temporal\PlainTime;
use Temporal\ZonedDateTime;

final class PlainDateConversionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // toPlainDateTime
    // -------------------------------------------------------------------------

    public function testToPlainDateTimeNoArgDefaultsToMidnight(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        $pdt = $pd->toPlainDateTime();

        static::assertSame(2020, $pdt->year);
        static::assertSame(6, $pdt->month);
        static::assertSame(15, $pdt->day);
        static::assertSame(0, $pdt->hour);
        static::assertSame(0, $pdt->minute);
        static::assertSame(0, $pdt->second);
    }

    public function testToPlainDateTimeWithPlainTime(): void
    {
        $pd = new PlainDate(2020, 6, 15);
        $pt = new PlainTime(14, 30, 45, 100, 200, 300);

        $pdt = $pd->toPlainDateTime($pt);

        static::assertSame(2020, $pdt->year);
        static::assertSame(6, $pdt->month);
        static::assertSame(15, $pdt->day);
        static::assertSame(14, $pdt->hour);
        static::assertSame(30, $pdt->minute);
        static::assertSame(45, $pdt->second);
        static::assertSame(100, $pdt->millisecond);
        static::assertSame(200, $pdt->microsecond);
        static::assertSame(300, $pdt->nanosecond);
    }

    public function testToPlainDateTimeWithString(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        $pdt = $pd->toPlainDateTime('12:30:00');

        static::assertSame(12, $pdt->hour);
        static::assertSame(30, $pdt->minute);
        static::assertSame(0, $pdt->second);
    }

    public function testToPlainDateTimeWithArray(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        $pdt = $pd->toPlainDateTime(['hour' => 8, 'minute' => 15]);

        static::assertSame(8, $pdt->hour);
        static::assertSame(15, $pdt->minute);
    }

    public function testToPlainDateTimeNullThrowsTypeError(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        $this->expectException(\TypeError::class);
        $pd->toPlainDateTime(null);
    }

    public function testToPlainDateTimeNumberThrowsTypeError(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        $this->expectException(\TypeError::class);
        $pd->toPlainDateTime(42);
    }

    public function testToPlainDateTimeBoolThrowsTypeError(): void
    {
        $pd = new PlainDate(2020, 6, 15);

        $this->expectException(\TypeError::class);
        $pd->toPlainDateTime(true);
    }

    // -------------------------------------------------------------------------
    // toZonedDateTime
    // -------------------------------------------------------------------------

    public function testToZonedDateTimeWithStringTimezone(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $zdt = $pd->toZonedDateTime('UTC');

        static::assertSame('UTC', $zdt->timeZoneId);
        static::assertSame('iso8601', $zdt->calendarId);
    }

    public function testToZonedDateTimeStringTimezoneAtMidnight(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $zdt = $pd->toZonedDateTime('UTC');

        static::assertSame('2020-01-01T00:00:00+00:00[UTC]', $zdt->toString());
    }

    public function testToZonedDateTimeWithPropertyBag(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $zdt = $pd->toZonedDateTime([
            'timeZone' => 'UTC',
            'plainTime' => new PlainTime(12, 0),
        ]);

        static::assertSame('2020-01-01T12:00:00+00:00[UTC]', $zdt->toString());
    }

    public function testToZonedDateTimeWithTimeString(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $zdt = $pd->toZonedDateTime([
            'timeZone' => 'UTC',
            'plainTime' => '12:00',
        ]);

        static::assertSame('2020-01-01T12:00:00+00:00[UTC]', $zdt->toString());
    }

    public function testToZonedDateTimeWithOffset(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $zdt = $pd->toZonedDateTime('+05:30');

        static::assertSame('+05:30', $zdt->timeZoneId);
    }

    public function testToZonedDateTimeNullThrowsTypeError(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $this->expectException(\TypeError::class);
        $pd->toZonedDateTime(null);
    }

    public function testToZonedDateTimeNumberThrowsTypeError(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $this->expectException(\TypeError::class);
        $pd->toZonedDateTime(42);
    }

    public function testToZonedDateTimeEmptyStringThrowsInvalidArgument(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $this->expectException(InvalidArgumentException::class);
        $pd->toZonedDateTime('');
    }

    public function testToZonedDateTimeMissingTimeZoneKeyThrowsTypeError(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $this->expectException(\TypeError::class);
        $pd->toZonedDateTime(['plainTime' => '12:00']);
    }

    public function testToZonedDateTimeNonStringTimeZoneThrowsTypeError(): void
    {
        $pd = new PlainDate(2020, 1, 1);

        $this->expectException(\TypeError::class);
        $pd->toZonedDateTime(['timeZone' => null]);
    }

    public function testToZonedDateTimeBoundaryMinDateThrows(): void
    {
        $pd = new PlainDate(-271821, 4, 19);

        $this->expectException(InvalidArgumentException::class);
        $pd->toZonedDateTime('UTC');
    }

    public function testToZonedDateTimeBoundaryMaxDateWithNegativeOffsetThrows(): void
    {
        $pd = new PlainDate(275760, 9, 13);

        $this->expectException(InvalidArgumentException::class);
        $pd->toZonedDateTime('-01:00');
    }

    // -------------------------------------------------------------------------
    // withCalendar
    // -------------------------------------------------------------------------

    public function testWithCalendarReturnsNewInstance(): void
    {
        $pd = new PlainDate(1976, 11, 18);

        $result = $pd->withCalendar('iso8601');

        static::assertNotSame($pd, $result);
        static::assertSame(1976, $result->year);
        static::assertSame(11, $result->month);
        static::assertSame(18, $result->day);
        static::assertSame('iso8601', $result->calendarId);
    }

    public function testWithCalendarAcceptsIsoDateString(): void
    {
        $pd = new PlainDate(1976, 11, 18);

        $result = $pd->withCalendar('2020-01-01[u-ca=iso8601]');

        static::assertSame('iso8601', $result->calendarId);
    }

    public function testWithCalendarNullThrowsTypeError(): void
    {
        $pd = new PlainDate(1976, 11, 18);

        $this->expectException(\TypeError::class);
        $pd->withCalendar(null);
    }

    public function testWithCalendarNumberThrowsTypeError(): void
    {
        $pd = new PlainDate(1976, 11, 18);

        $this->expectException(\TypeError::class);
        $pd->withCalendar(42);
    }

    public function testWithCalendarEmptyStringThrowsInvalidArgument(): void
    {
        $pd = new PlainDate(1976, 11, 18);

        $this->expectException(InvalidArgumentException::class);
        $pd->withCalendar('');
    }

    public function testWithCalendarUnsupportedThrowsInvalidArgument(): void
    {
        $pd = new PlainDate(1976, 11, 18);

        $this->expectException(InvalidArgumentException::class);
        $pd->withCalendar('japanese');
    }
}
