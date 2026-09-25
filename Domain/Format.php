<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

final class Format
{
    public const CURRENCY = 'EUR';

    // 525 -> "08:45"
    public static function hm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, Units::MINUTES_PER_HOUR), $minutes % Units::MINUTES_PER_HOUR);
    }

    // 525 -> "08:45 h"
    public static function hours(int $minutes): string
    {
        return self::hm($minutes) . ' h';
    }

    // 28458, "de" -> "284,58 €"; "en" -> "€284.58"; "de", "CHF" -> "284,58 CHF"
    public static function money(int $cents, string $locale, string $currency = self::CURRENCY): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $text = (string) $formatter->formatCurrency($cents / 100, $currency);

        // Intl separates with non-breaking spaces; the PDF font and the tests expect plain ones.
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', $text);
    }
}
