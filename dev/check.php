<?php

/*
 * Dev integration check, after seed.php:
 * - two users on one project with different rulesets
 * - overlapping engagement is rejected
 * - period() splits by ISO week
 * Run: docker compose -f dev/compose.yaml exec -T kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/check.php
 */

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Kernel;
use KimaiPlugin\DrehzettelBundle\Domain\MailSchedule;
use KimaiPlugin\DrehzettelBundle\Enum\MailRhythm;
use KimaiPlugin\DrehzettelBundle\Repository\MailLogRepository;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;
use KimaiPlugin\DrehzettelBundle\Repository\TimesheetRangeRepository;
use KimaiPlugin\DrehzettelBundle\Service\DayCalculator;
use KimaiPlugin\DrehzettelBundle\Service\DayInputBuilder;
use KimaiPlugin\DrehzettelBundle\Service\DayNotes;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmWeekService;
use KimaiPlugin\DrehzettelBundle\Service\NullHolidayLookup;
use KimaiPlugin\DrehzettelBundle\Service\PayCalculator;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;
use KimaiPlugin\DrehzettelBundle\Service\FlushTimesheetWriter;
use KimaiPlugin\DrehzettelBundle\Service\WeekCalculator;

require '/opt/kimai/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/opt/kimai/.env');
$kernel = new Kernel('prod', false);
$kernel->boot();
$registry = $kernel->getContainer()->get('doctrine');
$em = $registry->getManager();

$fails = 0;
function expect(string $name, mixed $expected, mixed $actual): void
{
    global $fails;
    $ok = $expected === $actual;
    $fails += $ok ? 0 : 1;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok ? '' : ' expected ' . json_encode($expected) . ' got ' . json_encode($actual)) . "\n";
}

$project = $em->getRepository(Project::class)->findOneBy(['name' => 'Sample Film (dev)']);
$admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);
$crew = $em->getRepository(User::class)->findOneBy(['username' => 'crew2']);
$activity = $em->getRepository(Activity::class)->findOneBy(['project' => $project]);
$zone = new DateTimeZone('Europe/Berlin');

$engagements = new EngagementRepository($registry);
$service = new EngagementService($engagements, new RulesetCatalog(new FilmRulesetRepository($registry)));
$pay = new PayCalculator();
$dayCalc = new DayCalculator($pay);
$notes = new DayNotes(new TimesheetRangeRepository($registry), new FlushTimesheetWriter($em));
$weeks = new FilmWeekService(
    new DayInputBuilder(new TimesheetRangeRepository($registry), new FilmDayRepository($registry), new NullHolidayLookup(), $notes),
    new WeekCalculator($dayCalc, $pay),
    $dayCalc,
    $service,
);

// Overlap is rejected.
try {
    $service->open($admin, $project, 'x', new PayTerms(PayKind::WEEKLY, 1), new DateTimeImmutable('2025-06-02'), null, RulesetCatalog::TV_FFS_2024);
    expect('overlap rejected', true, false);
} catch (DomainException) {
    expect('overlap rejected', true, true);
}

// Second person: same times as admin on Tue 19 May, TV FFS rules.
$engagement = $service->active($crew, $project, new DateTimeImmutable('2025-05-20'))
    ?? $service->open($crew, $project, 'Oberbeleuchter', new PayTerms(PayKind::WEEKLY, 170000, 0), new DateTimeImmutable('2025-05-02'), null, RulesetCatalog::TV_FFS_2024);
if ($em->getRepository(Timesheet::class)->findOneBy(['user' => $crew]) === null) {
    $sheet = new Timesheet();
    $sheet->setUser($crew);
    $sheet->setActivity($activity);
    $sheet->setProject($project);
    $sheet->setBegin(new DateTime('2025-05-20 08:30', $zone));
    $sheet->setEnd(new DateTime('2025-05-20 20:15', $zone));
    $em->persist($sheet);
    $em->flush();
}

$mine = $weeks->week($engagements->findActive($admin, $project, new DateTimeImmutable('2025-05-20')), 2025, 21);
$theirs = $weeks->week($engagement, 2025, 21);
expect('admin tier minutes Tue', [60, 45, 0], array_map(fn ($s) => $s->minutes, $mine->days[1]->dailyShares));
expect('crew2 tier minutes Tue (TV FFS, 11:00 h work)', [60, 0], array_map(fn ($s) => $s->minutes, $theirs->days[0]->dailyShares));
expect('crew2 break default 45 min, excess rule', 45, $theirs->days[0]->breakMinutes);
expect('crew2 work 11:00 (no break entered -> default 45)', 660, $theirs->days[0]->workMinutes);
expect('snapshot names differ', ['Quarter-Hour Ruleset', 'TV FFS 2024'], [$engagements->findActive($admin, $project, new DateTimeImmutable('2025-05-20'))->getRulesetName(), $engagement->getRulesetName()]);

// Activity restriction: crew2's engagement counts only $activity. A same-project
// "Anfahrt" (commute) entry on a different activity must not be detected as film time.
$commuteActivity = $em->getRepository(Activity::class)->findOneBy(['name' => 'Anfahrt (dev)']);
if ($commuteActivity === null) {
    $commuteActivity = new Activity();
    $commuteActivity->setName('Anfahrt (dev)');
    $commuteActivity->setProject($project);
    $em->persist($commuteActivity);
    $em->flush();
}
$commuteSheet = $em->getRepository(Timesheet::class)->findOneBy(['user' => $crew, 'activity' => $commuteActivity]);
if ($commuteSheet === null) {
    $commuteSheet = new Timesheet();
    $commuteSheet->setUser($crew);
    $commuteSheet->setActivity($commuteActivity);
    $commuteSheet->setProject($project);
    $commuteSheet->setBegin(new DateTime('2025-05-20 07:00', $zone));
    $commuteSheet->setEnd(new DateTime('2025-05-20 08:15', $zone));
    $em->persist($commuteSheet);
    $em->flush();
}
expect('unrestricted engagement: commute activity still counts', true, $engagement->appliesToActivity($commuteActivity));
expect('unrestricted engagement: commute entry has an active engagement', true, $service->activeFor($commuteSheet) !== null);

$engagement->setActivityIds([$activity->getId()]);
$service->save($engagement);
expect('restricted engagement: primary activity still counts', true, $engagement->appliesToActivity($activity));
expect('restricted engagement: commute activity excluded', false, $engagement->appliesToActivity($commuteActivity));
expect('restricted engagement: commute entry has no active engagement', true, $service->activeFor($commuteSheet) === null);
$engagement->setActivityIds([]);
$service->save($engagement);

// Period over one week: query range narrowed to week 21 only -> exactly one week result.
// (The seed data spans many weeks of the reference fixtures, not just week 21, so a wider
// range here would legitimately split into several WeekResults - that's not a bug.)
$period = $weeks->period($engagements->findActive($admin, $project, new DateTimeImmutable('2025-05-20')), new DateTimeImmutable('2025-05-19', $zone), new DateTimeImmutable('2025-05-24', $zone));
expect('period weeks', 1, count($period));
expect('period work', 43 * 60 + 15, $period[0]->workMinutes);

// Mail log: an automatic mail is logged once per send time. A failure and its retry
// stay on that row (attempts), a period without entries is logged as skipped.
$recipients = new MailRecipientRepository($registry);
$mailLog = new MailLogRepository($registry);
$console = new Application($kernel);
$console->setAutoExit(false);
$sendDue = static fn () => $console->run(new ArrayInput(['command' => 'drehzettel:mail:due']), new NullOutput());
$clearLog = static function () use ($mailLog, $em, $engagement): void {
    foreach ($mailLog->findBy(['engagement' => $engagement]) as $entry) {
        $em->remove($entry);
    }
    $em->flush();
};

$settings = $recipients->settingsFor($engagement);
$settings->setEmail('kaputt'); // no address: the mail fails
// Send time in the current hour: a failure is retried only for a day after it.
$now = new DateTimeImmutable('now', $crew->getDateTimezone());
$settings->setSchedule(new MailSchedule(MailRhythm::WEEKLY, (int) $now->format('N'), (int) $now->format('G')));
$settings->setLastSlot(null); // a run before handled this hour's send time already
$recipients->save($settings);
$schedule = $settings->getSchedule();
$slot = $schedule->lastSlot($now);
$period = $schedule->period($slot);

$shoot = new Timesheet();
$shoot->setUser($crew);
$shoot->setActivity($activity);
$shoot->setProject($project);
$shoot->setBegin(DateTime::createFromImmutable($period->from->setTime(8, 0)));
$shoot->setEnd(DateTime::createFromImmutable($period->from->setTime(18, 0)));
$em->persist($shoot);
$em->flush();
$clearLog();

$recipients->release($settings, null);
$sendDue();
$entry = $mailLog->findForSlot($engagement, $slot);
expect('mail log: failed', ['failed', 'automatic', 1, true], [$entry?->getStatus()->value, $entry?->getTrigger()->value, $entry?->getAttempts(), $entry?->getError() !== null]);
$sendDue(); // released after the failure: the next run retries
expect('mail log: retry on the same row', ['failed', 2], [$entry->getStatus()->value, $entry->getAttempts()]);

$settings->setEmail('produktion@example.test');
$recipients->save($settings);
$sendDue();
$sendDue(); // handled: no second mail
expect('mail log: sent after retry', ['sent', 3, null, 'produktion@example.test'], [$entry->getStatus()->value, $entry->getAttempts(), $entry->getError(), $entry->getRecipient()]);
expect('mail log: one row per send time', 1, count($mailLog->findBy(['engagement' => $engagement])));

$em->remove($shoot);
$em->flush();
$clearLog();
$recipients->release($settings, null);
$sendDue();
expect('mail log: period without entries skipped', 'skipped', $mailLog->findForSlot($engagement, $slot)?->getStatus()->value);

$clearLog();
$settings->setSchedule(new MailSchedule(MailRhythm::OFF));
$recipients->save($settings);

// A log that cannot be written (database error) must not disturb the sending: no exception.
$brokenLog = new class($registry) extends MailLogRepository {
    public function save(KimaiPlugin\DrehzettelBundle\Entity\MailLog $entry): void
    {
        throw new RuntimeException('database down');
    }
};
$quiet = new Psr\Log\NullLogger();
try {
    (new KimaiPlugin\DrehzettelBundle\Service\MailHistory($brokenLog, $quiet))->manual($engagement, $period, 'produktion@example.test', KimaiPlugin\DrehzettelBundle\Enum\MailStatus::SENT);
    expect('mail log: write error does not throw', true, true);
} catch (Throwable $e) {
    expect('mail log: write error does not throw', 'no exception', $e->getMessage());
}

echo $fails === 0 ? "All checks passed\n" : "$fails failed\n";
exit($fails === 0 ? 0 : 1);
