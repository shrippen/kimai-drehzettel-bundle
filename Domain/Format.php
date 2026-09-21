<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

final class Format
{
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

    // 28458, "de" -> "284,58 €"; "en" -> "€284.58"
    public static function money(int $cents, string $locale): string
    {
        $amount = number_format(abs($cents) / 100, 2, $locale === 'de' ? ',' : '.', $locale === 'de' ? '.' : ',');
        $sign = $cents < 0 ? '-' : '';

        return $locale === 'de' ? "{$sign}{$amount} €" : "{$sign}€{$amount}";
    }
}
