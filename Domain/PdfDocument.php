<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

final class PdfDocument
{
    public function __construct(
        public readonly string $filename,
        public readonly string $content,
    ) {
    }
}
