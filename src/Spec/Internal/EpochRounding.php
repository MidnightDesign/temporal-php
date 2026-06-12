<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

use Temporal\Exception\RangeError;

/**
 * Rounds a true epoch value, held as a (epochSec, subNs) pair, to a nanosecond
 * increment using TC39 RoundNumberToIncrementAsIfPositive.
 *
 * The decomposition into seconds + sub-second parts is load-bearing: an
 * over-int64 instant's combined nanosecond value does not fit a signed 64-bit
 * int, so the rounding is performed in the seconds domain (for minute-or-coarser
 * increments) or on the sub-second remainder alone (for sub-second increments),
 * and the combined value is never materialized. Shared by {@see Temporal\Spec\Instant}
 * and {@see Temporal\Spec\ZonedDateTime}.
 *
 * @internal
 */
final class EpochRounding
{
    private const int NS_PER_SECOND = 1_000_000_000;

    /**
     * Rounds a true (epochSec, subNs) pair to the nearest multiple of $increment
     * nanoseconds. $subNs must be in [0, 1e9).
     *
     * @return array{int, int} [epochSec, subNs] with subNs in [0, 1e9)
     * @throws RangeError for unknown rounding modes.
     */
    public static function round(int $epochSec, int $subNs, int $increment, string $mode): array
    {
        if ($increment === 1) {
            return [$epochSec, $subNs];
        }

        if ($increment <= self::NS_PER_SECOND) {
            // Round the sub-second portion in isolation; carry into seconds.
            $roundedSubNs = self::roundAsIfPositive($subNs, $increment, $mode);
            if ($roundedSubNs >= self::NS_PER_SECOND) {
                $epochSec += intdiv($roundedSubNs, self::NS_PER_SECOND);
                $roundedSubNs %= self::NS_PER_SECOND;
            }
            return [$epochSec, $roundedSubNs];
        }

        // Minute (or coarser) increment: round in the seconds domain so the
        // combined nanosecond value never has to fit in int64. epochSec is bounded
        // by the valid-instant range (~|2.7e14|), so seconds-domain math is safe.
        // The sub-second remainder only matters at exact-half boundaries, where it
        // tips the distance strictly past the midpoint (AsIfPositive semantics).
        $incSec = intdiv($increment, self::NS_PER_SECOND);
        $floorSec = self::floorToIncrement($epochSec, $incSec);
        // d1 = distance from the floor multiple, in nanoseconds within [0, increment).
        $d1Ns = (($epochSec - $floorSec) * self::NS_PER_SECOND) + $subNs;
        $expand = match ($mode) {
            'trunc', 'floor' => false,
            'ceil', 'expand' => $d1Ns > 0,
            'halfExpand', 'halfCeil' => ($d1Ns * 2) >= $increment,
            'halfTrunc', 'halfFloor' => ($d1Ns * 2) > $increment,
            'halfEven' => ($d1Ns * 2) === $increment
                ? (intdiv($floorSec, $incSec) % 2) !== 0
                : ($d1Ns * 2) > $increment,
            default => throw new RangeError("Invalid roundingMode \"{$mode}\"."),
        };
        return [$expand ? $floorSec + $incSec : $floorSec, 0];
    }

    /**
     * Rounds $ns to the nearest multiple of $increment using
     * RoundNumberToIncrementAsIfPositive: 'floor'/'trunc' round toward zero,
     * 'ceil'/'expand' toward +∞, and the half-modes break ties with the positive-
     * sign convention regardless of the original value's sign.
     *
     * Package-internal seam: the epoch sibling classes ({@see Temporal\Spec\ZonedDateTime}
     * and {@see Temporal\Spec\Instant}) call this for their non-negative as-if-positive
     * nanosecond rounding rather than re-implementing it. Not part of any public surface.
     *
     * @internal Only for the Temporal\Spec epoch sibling classes.
     *
     * @throws RangeError for unknown rounding modes.
     */
    public static function roundAsIfPositive(int $ns, int $increment, string $mode): int
    {
        // Integer floor-division: r1 = floor(ns / increment).
        $q = intdiv($ns, $increment);
        $rem = $ns - ($q * $increment);
        $r1 = $rem < 0 ? $q - 1 : $q;

        // d1 = distance of $ns from r1 (always in [0, $increment)).
        $d1 = $ns - ($r1 * $increment);

        // Directed rounding (AsIfPositive: trunc/floor → r1; ceil/expand → r2):
        $r2 = $r1 + 1;
        if ($mode === 'halfEven') {
            $cmp = $d1 * 2;
            if ($cmp < $increment) {
                $rounded = $r1;
            } elseif ($cmp > $increment) {
                $rounded = $r2;
            } else {
                $rounded = ($r1 % 2) === 0 ? $r1 : $r2;
            }
        } else {
            $rounded = match ($mode) {
                'trunc', 'floor' => $r1,
                'ceil', 'expand' => $d1 === 0 ? $r1 : $r2,
                'halfExpand', 'halfCeil' => ($d1 * 2) >= $increment ? $r2 : $r1,
                'halfTrunc', 'halfFloor' => ($d1 * 2) > $increment ? $r2 : $r1,
                default => throw new RangeError("Invalid roundingMode \"{$mode}\"."),
            };
        }

        return $rounded * $increment;
    }

    /** Largest multiple of $increment ≤ $value. */
    private static function floorToIncrement(int $value, int $increment): int
    {
        $q = intdiv($value, $increment);
        if (($value - ($q * $increment)) < 0) {
            $q--;
        }
        return $q * $increment;
    }
}
