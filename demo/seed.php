<?php

/*
 * Demo data of the Studio Weber world (shrippen demo): Jonas Brandt works as
 * camera assistant on "Harbour Lights" for Northlight Pictures (weekly gage,
 * TV FFS rules). His timesheets on the shoot days are replaced by call-to-wrap
 * entries, and each day gets its film day (break, catering, note).
 * Needs the core data first (shrippen.github.io/demo/kimai/seed-core.php);
 * demo/start.sh runs both.
 */

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Kernel;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;
use KimaiPlugin\DrehzettelBundle\Repository\TimesheetRangeRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\DayNotes;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use KimaiPlugin\DrehzettelBundle\Service\FlushTimesheetWriter;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;

require '/opt/kimai/vendor/autoload.php';
require __DIR__ . '/DemoWorld.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/opt/kimai/.env');

$kernel = new Kernel('prod', false);
$kernel->boot();
$registry = $kernel->getContainer()->get('doctrine');
$em = $registry->getManager();

$world = new DemoWorld(getenv('DEMO_LANG') ?: 'de', null, 'today');
$w = $world->data;
$e = $w['film_engagement'];
$people = array_column($w['people'], null, 'id');
$projects = array_column($w['projects'], null, 'id');
$activities = array_column($w['activities'], null, 'id');
$places = array_column($w['places'], null, 'id');

$user = $em->getRepository(User::class)->findOneBy(['email' => $people[$e['user']]['email']])
    ?? throw new RuntimeException('Core demo data missing (seed-core.php first).');
$project = $em->getRepository(Project::class)->findOneBy(['name' => $world->t($projects[$e['project']]['name'])]);
$activity = $em->getRepository(Activity::class)->findOneBy(['name' => $world->t($activities[$e['activity']]['name'])]);
if ($em->getRepository(Engagement::class)->findOneBy(['user' => $user, 'project' => $project]) !== null) {
    echo "Already seeded.\n";
    exit(0);
}

$engagements = new EngagementRepository($registry);
$service = new EngagementService($engagements, new RulesetCatalog(new FilmRulesetRepository($registry)));
$engagement = $service->open(
    $user,
    $project,
    $world->t($e['position']),
    new PayTerms(PayKind::WEEKLY, $e['weekly_gage'] * 100, 950),
    $world->date($e['from']),
    $world->date($e['to']),
    RulesetCatalog::TV_FFS_2024,
);

// The shoot days replace Jonas' ordinary entries on the project in the engagement period.
$em->createQueryBuilder()->delete(Timesheet::class, 't')
    ->where('t.user = :user')->andWhere('t.project = :project')
    ->andWhere('t.begin >= :from')->andWhere('t.begin < :to')
    ->setParameter('user', $user)->setParameter('project', $project)
    ->setParameter('from', DateTime::createFromImmutable($world->date($e['from'])))
    ->setParameter('to', DateTime::createFromImmutable($world->date($e['to'] + 1)))
    ->getQuery()->execute();

$filmDays = new FilmDayService(new FilmDayRepository($registry), new TimesheetRangeRepository($registry), new DayNotes(new TimesheetRangeRepository($registry), new FlushTimesheetWriter($em)));
$now = new DateTimeImmutable('now', $world->today->getTimezone());
$hourly = (float) $projects[$e['project']]['hourly_rate'];
$count = 0;
foreach ($e['days'] as $i => $d) {
    $begin = $world->date($d['day'], $d['call']);
    $end = $world->date($d['day'], $d['wrap']);
    if ($end <= $begin) {
        $end = $end->modify('+1 day');
    }
    if ($end > $now) {
        continue;                       // no entries in the future
    }
    $sheet = new Timesheet();
    $sheet->setUser($user);
    $sheet->setProject($project);
    $sheet->setActivity($activity);
    $sheet->setBegin(DateTime::createFromImmutable($begin));
    $sheet->setEnd(DateTime::createFromImmutable($end));
    $sheet->setDuration($end->getTimestamp() - $begin->getTimestamp());
    $place = $world->t($places[$d['location']]['name']);
    $sheet->setDescription($place . ' · ' . $world->t($d['note']));
    $sheet->setBillable(false);
    $sheet->setHourlyRate($hourly);
    $em->persist($sheet);
    $em->flush();

    $weekday = (int) $begin->format('N');
    $category = $weekday === 6 ? DayCategory::SATURDAY : ($weekday === 7 ? DayCategory::SUNDAY : DayCategory::WORKDAY);
    $filmDays->save(
        $engagement,
        $begin->setTime(0, 0),
        $d['break_min'],
        $i % 3 === 0 ? Catering::YES : Catering::NO,
        $category,
        note: $sheet->getDescription(),
        shootingDayNumber: $i + 1,
    );
    $count++;
}
$em->flush();

echo "Seeded engagement {$engagement->getId()} for {$user->getAlias()}: $count shoot days.\n";
