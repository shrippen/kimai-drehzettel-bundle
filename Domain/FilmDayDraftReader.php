<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

/**
 * Reads the week view's form fields for one day into a draft.
 * Browser input is never trusted: unknown values fall back to the default.
 */
final class FilmDayDraftReader
{
    private const MAX_BREAK_MINUTES = 720;
    private const MAX_PRODUCTION_DAY = 7;
    private const MAX_NOTE_LENGTH = 500;
    private const CENTS = 100;

    /**
     * @param array<string, mixed> $data fields of one day: break, catering, category, type, production_day, note, extra_pay (currency units), shooting_day
     */
    public static function read(array $data): FilmDayDraft
    {
        return new FilmDayDraft(
            breakMinutes: self::intOrNull($data['break'] ?? null, 0, self::MAX_BREAK_MINUTES),
            catering: self::checked($data['catering'] ?? null) ? Catering::YES : Catering::NO,
            category: DayCategory::tryFrom((string) ($data['category'] ?? '')),
            type: DayType::tryFrom((string) ($data['type'] ?? '')) ?? DayType::WORKDAY,
            productionDay: self::intOrNull($data['production_day'] ?? null, 1, self::MAX_PRODUCTION_DAY),
            note: self::note($data['note'] ?? null),
            extraPayCents: self::cents($data['extra_pay'] ?? null),
            shootingDayNumber: self::intOrNull($data['shooting_day'] ?? null, 1, FilmDayPatch::MAX_SHOOTING_DAY),
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
