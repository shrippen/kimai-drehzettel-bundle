<?php

/*
 * Dev seed: customer, project, timesheet entries of all reference weeks,
 * engagement and film days. Idempotent by project name.
 * Run inside the Kimai container:
 *   docker compose -f dev/compose.yaml exec -T kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/seed.php
 */

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Kernel;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;

const KIMAI_ROOT = '/opt/kimai';
const PROJECT_NAME = 'Sample Film (dev)';
const ZONE = 'Europe/Berlin';

require KIMAI_ROOT . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(KIMAI_ROOT . '/.env');

$kernel = new Kernel('prod', false);
$kernel->boot();
$registry = $kernel->getContainer()->get('doctrine');
$em = $registry->getManager();

$user = $em->getRepository(User::class)->findOneBy([]);
$existing = $em->getRepository(Project::class)->findOneBy(['name' => PROJECT_NAME]);
if ($existing !== null) {
    echo "Already seeded (project {$existing->getId()}).\n";
    exit(0);
}

$customer = new Customer('Produktion (dev)');
$customer->setCurrency('EUR');
$customer->setCountry('DE');
$customer->setTimezone(ZONE);
$em->persist($customer);

$project = new Project();
$project->setName(PROJECT_NAME);
$project->setCustomer($customer);
$project->setBillable(false);
$em->persist($project);

$activity = new Activity();
$activity->setName('Crew member');
$activity->setProject($project);
$em->persist($activity);
$em->flush();

$fixtures = json_decode(file_get_contents(__DIR__ . '/../tests/fixtures/reference_days.json'), true, flags: JSON_THROW_ON_ERROR);
$zone = new DateTimeZone(ZONE);

$engagements = new EngagementRepository($registry);
$service = new EngagementService($engagements, new RulesetCatalog(new FilmRulesetRepository($registry)));
$engagement = $service->open(
    $user,
    $project,
    'Crew member',
    new PayTerms(PayKind::WEEKLY, 158100, 950),
    new DateTimeImmutable('2025-05-02'),
    null,
    RulesetCatalog::TIMESHEET_APP,
);

$filmDays = new FilmDayService(new FilmDayRepository($registry));
$isFirstDay = true;
foreach ($fixtures['weekly_gage']['weeks'] as $week) {
    foreach ($week['rows'] as $row) {
        $begin = new DateTime("{$row['date']} {$row['begin']}", $zone);
        $end = new DateTime("{$row['date']} {$row['end']}", $zone);
        if ($end <= $begin) {
            $end->modify('+1 day');
        }

        $sheet = new Timesheet();
        $sheet->setUser($user);
        $sheet->setActivity($activity);
        $sheet->setProject($project);
        $sheet->setBegin($begin);
        $sheet->setEnd($end);
        $em->persist($sheet);

        // One note, to see it on the PDF.
        $note = $isFirstDay ? 'Studio rebuild in the morning' : null;
        $isFirstDay = false;
        $filmDays->save(
            $engagement,
            new DateTimeImmutable($row['date']),
            $row['breakMinutes'],
            $row['catering'] ? Catering::YES : Catering::NO,
            note: $note,
        );
    }
}
$em->flush();

echo "Seeded project {$project->getId()}, user {$user->getUserIdentifier()}, engagement {$engagement->getId()}.\n";
