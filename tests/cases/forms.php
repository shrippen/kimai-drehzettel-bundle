<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraftReader;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetFormMapper;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

// Ruleset -> form -> ruleset changes nothing, for both presets.
foreach ([Rulesets::tvFfs2024(), Rulesets::quarterHour()] as $rules) {
    $form = RulesetFormMapper::toForm($rules);
    $back = RulesetFormMapper::fromForm($form);
    check("form round trip {$rules->name}", RulesetCodec::toArray($rules), RulesetCodec::toArray($back));
}

$form = RulesetFormMapper::toForm(Rulesets::tvFfs2024());
check('form shows hours and percent', [10.0, 25.0, 11.0, 50.0, null], [$form['dailyTier1After'], $form['dailyTier1Percent'], $form['dailyTier2After'], $form['dailyTier2Percent'], $form['dailyTier3After']]);
check('form clock', ['22:00', '06:00'], [$form['nightFrom'], $form['nightTo']]);
check('form no fixed 6th day', null, $form['sixthDayPercent']);

// Emptying a tier slot removes the tier, clearing a category removes its surcharge.
$form['dailyTier2After'] = null;
$form['dailyTier2Percent'] = null;
$form['sundayPercent'] = '';
$edited = RulesetFormMapper::fromForm($form);
check('form removes a tier', 1, count($edited->dailyTiers));
check('form removes a category', null, $edited->surchargeFor(DayCategory::SUNDAY));
check('form keeps saturday', 2500, $edited->surchargeFor(DayCategory::SATURDAY)?->basisPoints);

// Hours with decimals: 10.5 h -> 630 min, 12.5 % -> 1250 bp.
$form['dailyTier1After'] = '10.5';
$form['dailyTier1Percent'] = '12.5';
$fine = RulesetFormMapper::fromForm($form);
check('form decimal hours and percent', [630, 1250], [$fine->dailyTiers[0]->afterMinutes, $fine->dailyTiers[0]->basisPoints]);

// Day form reader: bad input never breaks anything.
$day = FilmDayDraftReader::read(['break' => '60', 'catering' => 'on', 'category' => 'sunday', 'type' => 'travel', 'production_day' => '6', 'note' => '  Nachtdreh  ']);
check('draft reads values', [60, Catering::YES, DayCategory::SUNDAY, DayType::TRAVEL, 6, 'Nachtdreh'], [$day->breakMinutes, $day->catering, $day->category, $day->type, $day->productionDay, $day->note]);
$blank = FilmDayDraftReader::read([]);
check('draft defaults', [null, Catering::NO, null, DayType::WORKDAY, null, null], [$blank->breakMinutes, $blank->catering, $blank->category, $blank->type, $blank->productionDay, $blank->note]);
$bad = FilmDayDraftReader::read(['break' => '99999', 'category' => 'bogus', 'type' => 'nope', 'production_day' => '42', 'note' => str_repeat('x', 900)]);
check('draft clamps and ignores junk', [720, null, DayType::WORKDAY, 7, 500], [$bad->breakMinutes, $bad->category, $bad->type, $bad->productionDay, mb_strlen($bad->note)]);
$neg = FilmDayDraftReader::read(['break' => '-5', 'production_day' => 'abc']);
check('draft negative and non numeric', [0, null], [$neg->breakMinutes, $neg->productionDay]);

// Extra pay is typed in currency units: comma or dot, clamped to 0..100,000.00.
check('draft extra pay comma', 1250, FilmDayDraftReader::read(['extra_pay' => '12,50'])->extraPayCents);
check('draft extra pay dot', 5000, FilmDayDraftReader::read(['extra_pay' => ' 50.00 '])->extraPayCents);
check('draft extra pay blank', 0, FilmDayDraftReader::read(['extra_pay' => ''])->extraPayCents);
check('draft extra pay junk', 0, FilmDayDraftReader::read(['extra_pay' => '12€'])->extraPayCents);
check('draft extra pay clamp', [0, 10000000], [FilmDayDraftReader::read(['extra_pay' => '-3'])->extraPayCents, FilmDayDraftReader::read(['extra_pay' => '999999'])->extraPayCents]);

// Shooting day of the production: 1..999, blank or junk is none.
check('draft shooting day', 37, FilmDayDraftReader::read(['shooting_day' => '37'])->shootingDayNumber);
check('draft shooting day blank', [null, null], [FilmDayDraftReader::read(['shooting_day' => ''])->shootingDayNumber, FilmDayDraftReader::read(['shooting_day' => 'x'])->shootingDayNumber]);
check('draft shooting day clamp', [1, 999], [FilmDayDraftReader::read(['shooting_day' => '0'])->shootingDayNumber, FilmDayDraftReader::read(['shooting_day' => '5000'])->shootingDayNumber]);
