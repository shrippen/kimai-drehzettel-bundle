<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

final class Tiers
{
    /**
     * Splits the span [from, to) over the tiers, one share per tier.
     *
     * Tiers 10:00 -> 25 %, 11:00 -> 50 %, span 0..11:45 gives
     * 25 %: 60 min, 50 %: 45 min.
     *
     * @param list<Tier> $tiers sorted ascending
     * @return list<Share>
     */
    public static function split(array $tiers, int $from, int $to, Rounding $rounding): array
    {
        $shares = [];
        foreach ($tiers as $i => $tier) {
            $end = isset($tiers[$i + 1]) ? $tiers[$i + 1]->afterMinutes : PHP_INT_MAX;
            $minutes = max(0, min($to, $end) - max($from, $tier->afterMinutes));
            $shares[] = new Share($tier->basisPoints, $rounding->apply($minutes));
        }

        return $shares;
    }
}
