<?php

/*
 * This file is part of the DrehzettelBundle plugin for Kimai.
 */

namespace KimaiPlugin\DrehzettelBundle\Command;

use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmWeekService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'drehzettel:week', description: 'Print the calculated film week of an engagement')]
class WeekCommand extends Command
{
    private const MINUTES_PER_HOUR = 60;

    public function __construct(
        private readonly UserRepository $users,
        private readonly ProjectRepository $projects,
        private readonly EngagementService $engagements,
        private readonly FilmWeekService $weeks,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED);
        $this->addArgument('project-id', InputArgument::REQUIRED);
        $this->addArgument('year', InputArgument::REQUIRED, 'ISO year');
        $this->addArgument('week', InputArgument::REQUIRED, 'ISO week');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->users->findByUsername($input->getArgument('username'));
        $project = $this->projects->find((int) $input->getArgument('project-id'));
        if ($user === null || $project === null) {
            $io->error('Unknown user or project.');

            return Command::FAILURE;
        }

        $year = (int) $input->getArgument('year');
        $week = (int) $input->getArgument('week');
        $monday = (new \DateTimeImmutable('today', $user->getDateTimezone()))->setISODate($year, $week);

        // An engagement that starts or ends mid-week counts too: take the first active day.
        $engagement = null;
        for ($day = 0; $day < 7 && $engagement === null; ++$day) {
            $engagement = $this->engagements->active($user, $project, $monday->modify("+$day days"));
        }
        if ($engagement === null) {
            $io->error('No active engagement in this week.');

            return Command::FAILURE;
        }

        $result = $this->weeks->week($engagement, $year, $week);

        $rows = [];
        foreach ($result->days as $day) {
            $rows[] = $this->row($day);
        }
        $io->title(sprintf('%s, KW %02d/%d (%s)', $engagement->getRole(), $week, $year, $engagement->getRulesetName()));
        $io->table(['Date', 'Begin', 'End', 'Break', 'Work', 'Tiers', 'Night', 'Cat.', 'Cents'], $rows);
        $io->writeln(sprintf(
            'Work %s, night %s, weekly pool %s, weekly %s, total %s',
            $this->hm($result->workMinutes),
            $this->hm($result->nightMinutes),
            $this->hm($result->weeklyPoolMinutes),
            $result->weeklyCents ?? '-',
            $result->totalCents ?? '-',
        ));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function row(DayResult $day): array
    {
        $tiers = array_map(
            fn ($s): string => sprintf('%d%%:%s', intdiv($s->basisPoints, 100), $this->hm($s->minutes)),
            $day->dailyShares,
        );

        return [
            $day->begin->format('D Y-m-d'),
            $day->begin->format('H:i'),
            $day->end->format('H:i'),
            $this->hm($day->breakMinutes),
            $this->hm($day->workMinutes),
            implode(' ', $tiers),
            $this->hm($day->nightMinutes),
            $day->catering->value,
            (string) ($day->amountCents ?? '-'),
        ];
    }

    private function hm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, self::MINUTES_PER_HOUR), $minutes % self::MINUTES_PER_HOUR);
    }
}
