<?php

declare(strict_types=1);

namespace Temporal\Exception;

/**
 * Thrown when a Temporal API receives a numeric value or computes a result
 * that lies outside the supported range — e.g. an epoch nanosecond outside
 * ±10^8 days, or a date arithmetic result exceeding the ISO date limits.
 *
 * Extends `\RangeException` so existing SPL-shaped catches keep working;
 * implements {@see TemporalException} so callers can catch every
 * Temporal-origin throwable through a single marker.
 */
final class RangeError extends \RangeException implements TemporalException {}
