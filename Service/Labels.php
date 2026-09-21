<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

// Translations for generated documents. Tests use a stub that returns the key.
interface Labels
{
    /**
     * @param array<string, string> $params
     */
    public function t(string $key, string $locale, array $params = []): string;
}
