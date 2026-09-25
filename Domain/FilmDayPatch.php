<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;

/**
 * Partial update of one film day from an API body. Only sent keys change:
 *
 *   stored  {break 0, travel, day 6, note "Reise"}
 *   body    {"breakMinutes": 30}
 *   result  {break 30, travel, day 6, note "Reise"}
 *
 * Unlike the week view's reader, bad values are rejected, not clamped.
 */
final class FilmDayPatch
{
    public const MAX_BREAK_MINUTES = 720;
    public const MAX_NOTE_LENGTH = 500;
    public const MAX_EXTRA_PAY_CENTS = 10_000_000;
    public const MAX_SHOOTING_DAY = 999;

    /**
     * @param array<string, mixed> $values validated values of the sent keys only
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * @param array<mixed> $body decoded JSON; unknown keys are ignored
     * @param StreakMode $mode of the engagement's ruleset: productionDay up to 7 or 999
     * @throws \InvalidArgumentException naming the first invalid field
     */
    public static function fromArray(array $body, StreakMode $mode): self
    {
        $values = [];
        if (array_key_exists('breakMinutes', $body)) {
            $values['breakMinutes'] = self::intOrNull($body['breakMinutes'], 0, self::MAX_BREAK_MINUTES, 'breakMinutes');
        }
        if (array_key_exists('catering', $body)) {
            $values['catering'] = self::catering($body['catering']);
        }
        if (array_key_exists('category', $body)) {
            $values['category'] = self::category($body['category']);
        }
        if (array_key_exists('dayType', $body)) {
            $values['dayType'] = self::dayType($body['dayType']);
        }
        if (array_key_exists('productionDay', $body)) {
            $values['productionDay'] = self::intOrNull($body['productionDay'], 1, $mode->maxDay(), 'productionDay');
        }
        if (array_key_exists('note', $body)) {
            $values['note'] = self::note($body['note']);
        }
        if (array_key_exists('extraPayCents', $body)) {
            $values['extraPayCents'] = self::intOrNull($body['extraPayCents'], 0, self::MAX_EXTRA_PAY_CENTS, 'extraPayCents') ?? 0;
        }
        if (array_key_exists('shootingDayNumber', $body)) {
            $values['shootingDayNumber'] = self::intOrNull($body['shootingDayNumber'], 1, self::MAX_SHOOTING_DAY, 'shootingDayNumber');
        }

        return new self($values);
    }

    public function applyTo(FilmDay $day): void
    {
        if (array_key_exists('breakMinutes', $this->values)) {
            $day->setBreakMinutes($this->values['breakMinutes']);
        }
        if (array_key_exists('catering', $this->values)) {
            $day->setCatering($this->values['catering']);
        }
        if (array_key_exists('category', $this->values)) {
            $day->setCategory($this->values['category']);
        }
        if (array_key_exists('dayType', $this->values)) {
            $day->setDayType($this->values['dayType']);
        }
        if (array_key_exists('productionDay', $this->values)) {
            $day->setProductionDay($this->values['productionDay']);
        }
        if (array_key_exists('note', $this->values)) {
            $day->setNote($this->values['note']);
        }
        if (array_key_exists('extraPayCents', $this->values)) {
            $day->setExtraPayCents($this->values['extraPayCents']);
        }
        if (array_key_exists('shootingDayNumber', $this->values)) {
            $day->setShootingDayNumber($this->values['shootingDayNumber']);
        }
    }

    // Null means "ruleset default". Integral numbers and digit strings ("45") pass.
    private static function intOrNull(mixed $value, int $min, int $max, string $field): ?int
    {
        if ($value === null) {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);
        if (is_bool($value) || $int === false || $int < $min || $int > $max) {
            throw new \InvalidArgumentException(sprintf('%s must be an integer from %d to %d.', $field, $min, $max));
        }

        return $int;
    }

    private static function catering(mixed $value): Catering
    {
        if (!in_array($value, [true, false, 0, 1], true)) {
            throw new \InvalidArgumentException('catering must be a boolean.');
        }

        return $value ? Catering::YES : Catering::NO;
    }

    // Null or "" means "derive from the weekday".
    private static function category(mixed $value): ?DayCategory
    {
        if ($value === null || $value === '') {
            return null;
        }

        $category = is_string($value) ? DayCategory::tryFrom($value) : null;
        if ($category === null) {
            throw new \InvalidArgumentException(sprintf('Unknown category "%s".', is_scalar($value) ? $value : gettype($value)));
        }

        return $category;
    }

    private static function dayType(mixed $value): DayType
    {
        $type = is_string($value) ? DayType::tryFrom($value) : null;
        if ($type === null) {
            throw new \InvalidArgumentException(sprintf('Unknown dayType "%s".', is_scalar($value) ? $value : ''));
        }

        return $type;
    }

    // Blank means no note. Length is counted after trimming, in characters.
    private static function note(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = is_string($value) ? trim($value) : null;
        if ($text === null || mb_strlen($text) > self::MAX_NOTE_LENGTH) {
            throw new \InvalidArgumentException(sprintf('note must be a string of at most %d characters.', self::MAX_NOTE_LENGTH));
        }

        return $text === '' ? null : $text;
    }
}
