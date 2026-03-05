<?php

declare(strict_types=1);

namespace Temporal\Exception;

/**
 * Thrown when a method requires functionality (e.g. PlainDate arithmetic) not yet implemented.
 * RunnerTest catches this and marks the test as incomplete rather than failed.
 */
final class NotYetImplementedException extends \RuntimeException {}
