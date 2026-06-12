<?php

declare(strict_types=1);

namespace Temporal\Spec\Internal;

/**
 * Shared (epochSec, subNs) normalization for the over-int64 instant representation
 * used by {@see Temporal\Spec\Instant} and {@see Temporal\Spec\ZonedDateTime}.
 *
 * The TC39 nanosecond range (±8.64e21 ns) exceeds PHP's signed 64-bit int, so both
 * classes keep the true value as a (epochSec, subNs) pair and clamp the public
 * epochNanoseconds field to a sentinel (PHP_INT_MIN/MAX) once the combined value no
 * longer fits int64. This helper houses the two pieces of that bookkeeping that are
 * byte-for-byte identical between the two classes; the spec range CHECK is left at
 * the call sites because its RangeError message is class-specific (Instant vs.
 * ZonedDateTime wording).
 *
 * @internal
 */
final class EpochParts
{
    private const int NS_PER_SECOND = 1_000_000_000;

    /**
     * Normalizes a sub-second nanosecond count into [0, 1e9), carrying any whole
     * seconds into $epochSec (flooring toward −∞, so a negative remainder borrows a
     * second). A pair already in range is returned unchanged.
     *
     * @return array{int, int} [epochSec, subNs] with subNs in [0, 1e9)
     */
    public static function normalizeSubNs(int $epochSec, int $subNs): array
    {
        // Unconditional floor-divide carry: for a $subNs already in [0, 1e9) the
        // carry is 0 and the pair is returned unchanged, so no in-range fast-path
        // guard is needed (it would only be an unobservable optimization, and an
        // `if ($subNs < 0 || $subNs >= 1e9)` guard's `< 0` arm is in fact dead —
        // every caller reaches here with $subNs ≥ 0).
        $carry = CalendarMath::floorDiv($subNs, self::NS_PER_SECOND);
        $epochSec += $carry;
        $subNs -= $carry * self::NS_PER_SECOND;
        return [$epochSec, $subNs];
    }

    /**
     * Resolves the public epochNanoseconds field from an in-range, already
     * sub-second-normalized (epochSec, subNs) pair: when the combined nanosecond
     * value fits a signed 64-bit int it is packed exactly; otherwise the field is
     * clamped to a sentinel (PHP_INT_MIN for negative, PHP_INT_MAX for positive) and
     * the true parts are carried alongside.
     *
     * Callers MUST have already range-checked the pair against the spec bound; this
     * helper only encodes the int64-fit / sentinel rule.
     *
     * @return array{int, ?int, int} [epochNanoseconds, trueEpochSec, trueSubNs];
     *         trueEpochSec is null (and trueSubNs 0) when the value fits int64 exactly.
     */
    public static function packOrClamp(int $epochSec, int $subNs): array
    {
        $maxSecForNs = EpochLimits::MAX_EPOCH_SECONDS_FOR_INT64_NS;
        // @infection-ignore-all > vs >= (and < vs <=) at ±MAX_EPOCH_SECONDS_FOR_INT64_NS
        // is unobservable: the boundary only differs at the exact second
        // ±9_223_372_035, and either way the sole effect is which of an equal-magnitude
        // int64-fitting pack vs. a sentinel clamp is chosen — and the clamped
        // epochNanoseconds field is never read directly (over-int64 reads route through
        // the carried trueEpochSec; the only fixtures that would assert the sentinel are
        // the BigInt-overflow limit tests the transpiler skips).
        if ($epochSec > $maxSecForNs || $epochSec < -$maxSecForNs) {
            // @infection-ignore-all Reached only when |epochSec| > MAX_EPOCH_SECONDS_FOR_INT64_NS,
            // so $epochSec is never 0 here (the < 0 ⇒ <= 0 boundary is dead) and the chosen
            // sentinel (PHP_INT_MIN/MAX) is never observed: every over-int64 read goes through
            // the carried trueEpochSec, and no runnable test262 fixture asserts the raw sentinel
            // (the BigInt limit tests transpile to skips). Swapping the ternary arms is therefore
            // equivalent under the corpus.
            return [$epochSec < 0 ? PHP_INT_MIN : PHP_INT_MAX, $epochSec, $subNs];
        }
        // @infection-ignore-all trueSubNs literal 0: dead in the fits-int64 case. This branch
        // returns trueEpochSec = null, and both consumers (Instant::getEpochParts,
        // ZonedDateTime::epochParts) read $this->trueSubNs only when trueEpochSec !== null —
        // so the value written here is never read back. 0/1/-1 are indistinguishable.
        return [($epochSec * self::NS_PER_SECOND) + $subNs, null, 0];
    }
}
