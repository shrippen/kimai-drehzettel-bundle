<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;

foreach ([Rulesets::tvFfs2024(), Rulesets::quarterHour()] as $rules) {
    $data = RulesetCodec::toArray($rules);
    $copy = RulesetCodec::fromArray(json_decode(json_encode($data), true));
    check("codec round trip {$rules->name}", $data, RulesetCodec::toArray($copy));

    // The decoded copy must calculate like the original.
    $terms = new PayTerms(PayKind::WEEKLY, 158100, 950);
    $day = shift('2025-05-20', '08:30', '20:15', 0);
    check(
        "codec same result {$rules->name}",
        dayCalc()->calc($day, $rules, $terms)->amountCents,
        dayCalc()->calc($day, $copy, $terms)->amountCents,
    );
}

$broken = RulesetCodec::toArray(Rulesets::tvFfs2024());
$broken['breakRule'] = 'nonsense';
try {
    RulesetCodec::fromArray($broken);
    check('codec rejects unknown enum', true, false);
} catch (InvalidArgumentException) {
    check('codec rejects unknown enum', true, true);
}

unset($broken['night']);
try {
    RulesetCodec::fromArray($broken);
    check('codec rejects missing key', true, false);
} catch (InvalidArgumentException) {
    check('codec rejects missing key', true, true);
}

// A ruleset stored with the removed streakMode key still loads; the key is ignored.
$withStreak = ['streakMode' => 'consecutive'] + RulesetCodec::toArray(Rulesets::tvFfs2024());
check('codec ignores streakMode', RulesetCodec::toArray(Rulesets::tvFfs2024()), RulesetCodec::toArray(RulesetCodec::fromArray($withStreak)));

// Travel days option: stored and read back; a ruleset stored before the option reads as the tariff.
$countedData = ['travelDays' => 'counted'] + RulesetCodec::toArray(Rulesets::tvFfs2024());
check('codec travel days', 'counted', RulesetCodec::toArray(RulesetCodec::fromArray($countedData))['travelDays']);
$legacy = RulesetCodec::toArray(Rulesets::tvFfs2024());
unset($legacy['travelDays']);
check('codec travel days missing', KimaiPlugin\DrehzettelBundle\Enum\TravelDays::EXCLUDED, RulesetCodec::fromArray($legacy)->travelDays);
