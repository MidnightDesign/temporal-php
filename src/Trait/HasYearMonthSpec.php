<?php

declare(strict_types=1);

namespace Temporal\Trait;

use Temporal\Spec\PlainDate as SpecPlainDate;
use Temporal\Spec\PlainDateTime as SpecPlainDateTime;
use Temporal\Spec\PlainYearMonth as SpecPlainYearMonth;
use Temporal\Spec\ZonedDateTime as SpecZonedDateTime;

/**
 * Marker interface declaring that a class exposes a `$spec` property whose
 * type has year/month/calendar identity fields.
 *
 * Used only as a `@phpstan-require-implements` target by
 * {@see HasYearMonthProperties} so static analyzers can resolve
 * `$this->spec->year` etc. inside the trait.
 *
 * @internal
 * @property-read SpecPlainYearMonth|SpecPlainDate|SpecPlainDateTime|SpecZonedDateTime $spec
 */
interface HasYearMonthSpec {}
