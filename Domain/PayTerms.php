<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\PayKind;

// Contract pay of one engagement. Amounts in cents.
final class PayTerms
{
    public function __construct(
        public readonly PayKind $kind,
        public readonly int $gageCents,
        public readonly int $cateringDeductionCents = 0,
    ) {
    }
}
