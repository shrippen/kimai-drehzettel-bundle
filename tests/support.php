<?php

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Service\DayCalculator;
use KimaiPlugin\DrehzettelBundle\Service\PayCalculator;
use KimaiPlugin\DrehzettelBundle\Service\WeekCalculator;

function pay(): PayCalculator
{
    return new PayCalculator();
}

function dayCalc(): DayCalculator
{
    return new DayCalculator(pay());
}

function weekCalc(): WeekCalculator
{
    return new WeekCalculator(dayCalc(), pay());
}

function at(string $date, string $time): DateTimeImmutable
{
    return new DateTimeImmutable("$date $time");
}

// End at or before begin means the next calendar day (07:30 -> 00:00).
function shift(string $date, string $begin, string $end, ?int $break = null, Catering $catering = Catering::NO, DayCategory $category = DayCategory::WORKDAY): DayInput
{
    $endAt = at($date, $end);
    $beginAt = at($date, $begin);
    if ($endAt <= $beginAt) {
        $endAt = $endAt->modify('+1 day');
    }

    return new DayInput($beginAt, $endAt, $category, breakMinutes: $break, catering: $catering);
}

// A ruleset switched to another StreakMode, the way a stored ruleset carries it.
function withMode(Ruleset $rules, KimaiPlugin\DrehzettelBundle\Enum\StreakMode $mode): Ruleset
{
    return KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec::fromArray(['streakMode' => $mode->value] + KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec::toArray($rules));
}

function shareMinutes(array $shares): array
{
    return array_map(static fn ($s): int => $s->minutes, $shares);
}

// Labels stub: returns the key, with parameters when given. Keeps view tests readable.
function stubLabels(): KimaiPlugin\DrehzettelBundle\Service\Labels
{
    return new class implements KimaiPlugin\DrehzettelBundle\Service\Labels {
        public function t(string $key, string $locale, array $params = []): string
        {
            if ($params === []) {
                return $key;
            }
            $parts = [];
            foreach ($params as $name => $value) {
                $parts[] = "$name=$value";
            }

            return $key . '(' . implode(',', $parts) . ')';
        }
    };
}
