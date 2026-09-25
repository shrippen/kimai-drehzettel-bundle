<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\ApiError;
use KimaiPlugin\DrehzettelBundle\Domain\ApiInfo;
use KimaiPlugin\DrehzettelBundle\Domain\ApiQuery;

// [status, body] of a failing parse, or the parsed value.
function apiParse(callable $parse): mixed
{
    try {
        return $parse();
    } catch (ApiError $e) {
        return [$e->status, $e->body()];
    }
}

// Project: missing and malformed are 400 with distinct codes.
check('api project', 12, apiParse(fn () => ApiQuery::projectId('12')));
check('api project missing', [400, ['error' => 'Missing query parameter project.', 'code' => 'missing_project']], apiParse(fn () => ApiQuery::projectId(null)));
check('api project empty', 'missing_project', apiParse(fn () => ApiQuery::projectId(''))[1]['code']);
check('api project text', [400, 'invalid_project'], (fn ($r) => [$r[0], $r[1]['code']])(apiParse(fn () => ApiQuery::projectId('abc'))));
check('api project zero', 'invalid_project', apiParse(fn () => ApiQuery::projectId('0'))[1]['code']);
check('api project array', 'invalid_project', apiParse(fn () => ApiQuery::projectId(['1']))[1]['code']);

// User: absent means the token owner.
check('api user absent', null, apiParse(fn () => ApiQuery::userId(null)));
check('api user', 3, apiParse(fn () => ApiQuery::userId('3')));
check('api user bad', 'invalid_user', apiParse(fn () => ApiQuery::userId('-3'))[1]['code']);

// Date: missing takes the default, overflow and garbage are 400.
$today = new DateTimeImmutable('2026-09-25');
check('api date default', '2026-09-25', apiParse(fn () => ApiQuery::date(null, $today))->format('Y-m-d'));
check('api date', '2025-02-28 00:00', apiParse(fn () => ApiQuery::date('2025-02-28', $today))->format('Y-m-d H:i'));
check('api date overflow', [400, 'invalid_date'], (fn ($r) => [$r[0], $r[1]['code']])(apiParse(fn () => ApiQuery::date('2025-02-30', $today))));
check('api date format', 'invalid_date', apiParse(fn () => ApiQuery::date('25.09.2026', $today))[1]['code']);

// Not found and forbidden keep their status.
check('api not found', [404, ['error' => 'x', 'code' => 'no_engagement']], [ApiError::notFound(ApiError::NO_ENGAGEMENT, 'x')->status, ApiError::notFound(ApiError::NO_ENGAGEMENT, 'x')->body()]);
check('api forbidden', [403, 'forbidden'], [ApiError::forbidden('x')->status, ApiError::forbidden('x')->body()['code']]);

// Ping keeps its first keys and adds permissions and features.
$ping = ApiInfo::ping(['view' => true, 'manage' => false]);
check('api ping keys', ['installed', 'pluginVersion', 'apiVersions', 'permissions', 'features'], array_keys($ping));
check('api ping permissions', ['view' => true, 'manage' => false], $ping['permissions']);
check('api ping v1', true, in_array('v1', $ping['apiVersions'], true));
check('api ping error codes', true, in_array('errorCodes', $ping['features'], true));

// Engagement list entry. Kimai's Project/Customer are stubbed: tests run without Kimai.
if (!class_exists('App\Entity\Project')) {
    eval('namespace App\Entity; class Customer { public function getName(): string { return "ACME"; } public function getCurrency(): string { return "EUR"; } }
        class Project { public function getId(): int { return 7; } public function getName(): string { return "Musterfilm"; } public function getCustomer(): Customer { return new Customer(); } }');
}
$engagement = new KimaiPlugin\DrehzettelBundle\Entity\Engagement();
$engagement->setProject(new App\Entity\Project());
$engagement->setRole('Oberbeleuchterin');
$engagement->setRulesetName('TV FFS 2024');
$engagement->setValidFrom(new DateTimeImmutable('2026-03-01'));
check('api engagement json', [
    'engagementId' => null, 'projectId' => 7, 'projectName' => 'Musterfilm', 'customerName' => 'ACME',
    'rulesetName' => 'TV FFS 2024', 'crewRole' => 'Oberbeleuchterin', 'validFrom' => '2026-03-01', 'validTo' => null, 'toggleDefault' => true,
], KimaiPlugin\DrehzettelBundle\Domain\ApiJson::engagement($engagement));
check('api ping engagements', true, in_array('engagements', ApiInfo::ping(['view' => true, 'manage' => true])['features'], true));

// Film day: a null field reports the default it falls back to.
$rules = KimaiPlugin\DrehzettelBundle\Domain\Rulesets::tvFfs2024();
$json = KimaiPlugin\DrehzettelBundle\Domain\ApiJson::filmDay('2026-03-07', $engagement, null, $rules, KimaiPlugin\DrehzettelBundle\Enum\DayCategory::SATURDAY);
check('api film day defaults', [null, null, $rules->defaultBreakMinutes, 'saturday'], [$json['breakMinutes'], $json['category'], $json['defaultBreakMinutes'], $json['effectiveCategory']]);
check('api film day keys kept', ['date', 'engagementId', 'breakMinutes', 'catering', 'category', 'note', 'dayType', 'productionDay'], array_slice(array_keys($json), 0, 8));

$stored = new KimaiPlugin\DrehzettelBundle\Entity\FilmDay();
$stored->setCategory(KimaiPlugin\DrehzettelBundle\Enum\DayCategory::HOLIDAY);
$stored->setBreakMinutes(30);
$json = KimaiPlugin\DrehzettelBundle\Domain\ApiJson::filmDay('2026-03-07', $engagement, $stored, $rules, KimaiPlugin\DrehzettelBundle\Enum\DayCategory::SATURDAY);
check('api film day override wins', [30, 'holiday', 'holiday'], [$json['breakMinutes'], $json['category'], $json['effectiveCategory']]);
check('api ping defaults', true, in_array('defaults', ApiInfo::ping(['view' => true, 'manage' => true])['features'], true));
check('api film day extra pay', [5000, 0], [
    (function () use ($engagement, $rules): int {
        $d = new KimaiPlugin\DrehzettelBundle\Entity\FilmDay();
        $d->setExtraPayCents(5000);

        return KimaiPlugin\DrehzettelBundle\Domain\ApiJson::filmDay('2026-03-07', $engagement, $d, $rules, KimaiPlugin\DrehzettelBundle\Enum\DayCategory::SATURDAY)['extraPayCents'];
    })(),
    KimaiPlugin\DrehzettelBundle\Domain\ApiJson::filmDay('2026-03-07', $engagement, null, $rules, KimaiPlugin\DrehzettelBundle\Enum\DayCategory::SATURDAY)['extraPayCents'],
]);
check('api ping extra pay', true, in_array('extraPay', ApiInfo::ping(['view' => true, 'manage' => true])['features'], true));

// Day summary: one day out of the calculated week, with its warnings. TV FFS rounds begun hours up.
$engagement->setGageCents(158100);
$summaryWeek = weekCalc()->calc([
    shift('2026-03-02', '08:00', '21:00', 45, KimaiPlugin\DrehzettelBundle\Enum\Catering::YES),
    new KimaiPlugin\DrehzettelBundle\Domain\DayInput(at('2026-03-03', '08:00'), at('2026-03-03', '16:45'), breakMinutes: 45, extraPayCents: 5000),
], $rules, new KimaiPlugin\DrehzettelBundle\Domain\PayTerms(KimaiPlugin\DrehzettelBundle\Enum\PayKind::WEEKLY, 158100, 950));
$summaryWarnings = (new KimaiPlugin\DrehzettelBundle\Service\ComplianceChecker())->check($summaryWeek);
$monday = KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of($summaryWeek, '2026-03-02', $summaryWarnings, $engagement);
check('summary long day', [true, 735, 45, [['percent' => 25.0, 'minutes' => 60], ['percent' => 50.0, 'minutes' => 120]], 'workday', null, 1], [$monday['hasEntry'], $monday['workMinutes'], $monday['breakMinutes'], $monday['overtime'], $monday['category'], $monday['categoryPercent'], $monday['dayNumber']]);
check('summary long day warning', [['issue' => 'daily_max', 'minutes' => 780, 'limitMinutes' => 720]], $monday['warnings']);
check('summary pay is the day amount', $summaryWeek->days[0]->amountCents, $monday['payCents']);
$tuesday = KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of($summaryWeek, '2026-03-03', $summaryWarnings, $engagement);
// Tuesday starts 11 h after a 13 h day: 11.5 h rest were due.
check('summary extra pay', [5000, $summaryWeek->days[1]->amountCents, [['issue' => 'rest_time', 'minutes' => 660, 'limitMinutes' => 690]], 'EUR'], [$tuesday['extraPayCents'], $tuesday['payCents'], $tuesday['warnings'], $tuesday['currency']]);
$empty = KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of($summaryWeek, '2026-03-04', $summaryWarnings, $engagement);
check('summary no entry', [false, 0, [], null, null], [$empty['hasEntry'], $empty['workMinutes'], $empty['overtime'], $empty['payCents'], $empty['dayNumber']]);
$engagement->setGageCents(0);
check('summary no gage no pay', null, KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of($summaryWeek, '2026-03-03', $summaryWarnings, $engagement)['payCents']);
check('api ping day summary', true, in_array('daySummary', ApiInfo::ping(['view' => true, 'manage' => true])['features'], true));
