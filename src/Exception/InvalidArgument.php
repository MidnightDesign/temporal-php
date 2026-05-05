<?php

declare(strict_types=1);

namespace Temporal\Exception;

/**
 * Thrown when a Temporal API receives an argument whose value is not valid
 * for the operation — e.g. an unknown calendar identifier or a malformed
 * input string.
 *
 * Extends `\InvalidArgumentException` so existing SPL-shaped catches keep
 * working; implements {@see TemporalException} so callers can catch every
 * Temporal-origin throwable through a single marker.
 */
final class InvalidArgument extends \InvalidArgumentException implements TemporalException {}
