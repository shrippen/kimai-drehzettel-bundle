<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;

/**
 * Reads the week view's form fields for one day into a draft.
 * Browser input is never trusted: unknown values fall back to the default.
 * A key missing from the form keeps the stored value; a sent empty value clears it:
 *
 *   stored {production_day 6}, form {break: "30"}                      -> production_day 6
 *   stored {production_day 6}, form {break: "30", production_day: ""} -> production_day null
 */
final class FilmDayDraftReader
{
    private const MAX_BREAK_MINUTES = 720;
    private const MAX_NOTE_LENGTH = 500;
    private const CENTS = 100;

    /**
     * @param array<string, mixed> $data fields of one day: break, catering, category, type, production_day, note, extra_pay (currency units), shooting_day
     * @param StreakMode $mode of the engagement's ruleset: production_day up to 7 or 999
     */
    public static function read(array $data, ?FilmDay $stored = null, StreakMode $mode = StreakMode::DEFAULT): FilmDayDraft
    {
        $has = static fn (string $key): bool => array_key_exists($key, $data);

        return new FilmDayDraft(
            breakMinutes: $has('break') ? self::intOrNull($data['break'], 0, self::MAX_BREAK_MINUTES) : $stored?->getBreakMinutes(),
            catering: $has('catering') ? (self::checked($data['catering']) ? Catering::YES : Catering::NO) : ($stored?->getCatering() ?? Catering::NO),
            category: $has('category') ? DayCategory::tryFrom((string) $data['category']) : $stored?->getCategory(),
            type: $has('type') ? (DayType::tryFrom((string) $data['type']) ?? DayType::WORKDAY) : ($stored?->getDayType() ?? DayType::WORKDAY),
            productionDay: $has('production_day') ? self::intOrNull($data['production_day'], 1, $mode->maxDay()) : $stored?->getProductionDay(),
            note: $has('note') ? self::note($data['note']) : $stored?->getNote(),
            extraPayCents: $has('extra_pay') ? self::cents($data['extra_pay']) : ($stored?->getExtraPayCents() ?? 0),
            shootingDayNumber: $has('shooting_day') ? self::intOrNull($data['shooting_day'], 1, FilmDayPatch::MAX_SHOOTING_DAY) : $stored?->getShootingDayNumber(),
        );
    }

    private static function checked(mixed $value): bool
    {
        return in_array($value, ['1', 'on', 'yes', 'true', 1, true], true);
    }

    private static function intOrNull(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    // "12,50" or "12.50" -> 1250; anything else -> 0.
    private static function cents(mixed $value): int
    {
        $text = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($text)) {
            return 0;
        }

        return max(0, min(FilmDayPatch::MAX_EXTRA_PAY_CENTS, (int) round((float) $text * self::CENTS)));
    }

    private static function note(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, self::MAX_NOTE_LENGTH);
    }
}
