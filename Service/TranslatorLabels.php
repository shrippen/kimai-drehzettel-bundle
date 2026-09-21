<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatorLabels implements Labels
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function t(string $key, string $locale, array $params = []): string
    {
        return $this->translator->trans($key, $params, 'messages', $locale);
    }
}
