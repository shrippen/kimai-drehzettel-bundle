<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

// Surcharge that starts after "afterMinutes" and lasts until the next tier.
final class Tier
{
    public function __construct(
        public readonly int $afterMinutes,
        public readonly int $basisPoints,
    ) {
    }
}
