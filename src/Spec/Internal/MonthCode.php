<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

use Temporal\Exception\RangeError;
use Temporal\Exception\TypeError;

/**
 * Validates a TC39 calendar `monthCode` FIELD value at read time, following the
 * ECMA-402 ToMonthCode ordering: the value's TYPE (must be a string) is checked
 * first — a non-string is a TypeError — and only then is its SYNTAX (well-formed
 * "M" + two digits + optional leap marker "L") validated, an ill-formed string
 * being a RangeError. A monthCode's *suitability* for a given calendar/year
 * (e.g. "M13", or a leap code in a non-leap year) is NOT checked here; that is a
 * later, calendar-specific concern.
 *
 * This is the consistent type-then-syntax behavior shared by
 * {@see Temporal\Spec\PlainDate} and {@see Temporal\Spec\PlainYearMonth}. The
 * {@see Temporal\Spec\PlainDateTime} property-bag path historically DRIFTED: it
 * skipped the type-check at read time (only validating syntax when the value was
 * already a string) and deferred the TypeError to after the year field was
 * coerced. Routing that path through this helper deliberately realigns it with
 * the consistent pair, so the test262 monthCode tests must be re-run when it is.
 *
 * monthCode is a date FIELD rather than an options keyword, so it lives in a small
 * dedicated helper instead of {@see Options}.
 *
 * @internal
 */
final class MonthCode
{
    /** Well-formed ISO/extended monthCode: "M" + two digits + optional leap marker "L". */
    private const string SYNTAX_PATTERN = '/^M\d{2}L?$/';

    /**
     * Validates a freshly-read monthCode field value and returns the well-formed
     * string. A non-string $value is a TypeError; an ill-formed string is a
     * RangeError. Both messages are owned here and are non-contractual:
     * `tests/Test262/Assert.php::throws()` checks only the exception CLASS, and the
     * project's PHPUnit suites have no message assertions, so the wording is free.
     * Only the TypeError-vs-RangeError TYPE split is contractual.
     *
     * @throws TypeError  if $value is not a string.
     * @throws RangeError if $value is a string that is not well-formed.
     */
    public static function validate(mixed $value): string
    {
        if (!is_string($value)) {
            throw new TypeError('monthCode must be a string.');
        }
        if (preg_match(self::SYNTAX_PATTERN, $value) !== 1) {
            throw new RangeError(sprintf('Invalid monthCode: "%s".', $value));
        }
        return $value;
    }
}
