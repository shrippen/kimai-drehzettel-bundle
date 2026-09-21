<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;

foreach ([Rulesets::tvFfs2024(), Rulesets::timesheetApp()] as $rules) {
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
