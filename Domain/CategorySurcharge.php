<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\SurchargeBasis;

final class CategorySurcharge
{
    public function __construct(
        public readonly int $basisPoints,
        public readonly SurchargeBasis $basis,
    ) {
    }
}
