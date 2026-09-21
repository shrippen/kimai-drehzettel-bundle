<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

// Minutes worked at one surcharge rate. 2500 basis points = 25 %.
final class Share
{
    public function __construct(
        public readonly int $basisPoints,
        public readonly int $minutes,
    ) {
    }
}
