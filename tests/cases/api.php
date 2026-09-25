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
    eval('namespace App\Entity; class Customer { public function getName(): string { return "ACME"; } }
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
