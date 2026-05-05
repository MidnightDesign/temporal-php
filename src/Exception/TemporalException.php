<?php

declare(strict_types=1);

namespace Temporal\Exception;

/**
 * Marker interface implemented by every exception thrown from the Temporal\ namespace.
 *
 * Catch this to handle any Temporal-origin failure regardless of its SPL parent:
 *
 * ```php
 * try {
 *     Temporal\PlainDate::from($input);
 * } catch (TemporalException $e) {
 *     // ...
 * }
 * ```
 *
 * Concrete classes also extend the corresponding SPL parent
 * (`\InvalidArgumentException`, `\RangeException`, …), so existing
 * SPL-shaped catches keep working.
 */
interface TemporalException extends \Throwable {}
