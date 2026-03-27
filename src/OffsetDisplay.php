<?php

declare(strict_types=1);

namespace Temporal;

/**
 * Controls whether the UTC offset is included in ZonedDateTime string output.
 */
enum OffsetDisplay: string
{
    /** Include the UTC offset (default behavior). */
    case Auto = 'auto';

    /** Omit the UTC offset. */
    case Never = 'never';
}
