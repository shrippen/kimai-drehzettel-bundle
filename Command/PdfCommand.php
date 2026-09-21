<?php

/*
 * This file is part of the DrehzettelBundle plugin for Kimai.
 */

namespace KimaiPlugin\DrehzettelBundle\Command;

use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\PdfExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'drehzettel:pdf', description: 'Write the timesheet PDF of an engagement to a file')]
class PdfCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ProjectRepository $projects,
        private readonly EngagementService $engagements,
        private readonly PdfExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED);
        $this->addArgument('project-id', InputArgument::REQUIRED);
        $this->addOption('week', null, InputOption::VALUE_REQUIRED, 'ISO week, e.g. 2025-21');
        $this->addOption('month', null, InputOption::VALUE_REQUIRED, 'Month, e.g. 2025-05');
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date, e.g. 2025-05-19');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date, e.g. 2025-05-23');
        $this->addOption('with', null, InputOption::VALUE_REQUIRED, 'Comma list of PDF options to switch on, e.g. pay,under');
        $this->addOption('without', null, InputOption::VALUE_REQUIRED, 'Comma list of PDF options to switch off');
        $this->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Document language, de or en (default: the user\'s language)');
        $this->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Directory to write into', '.');
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

        $period = $this->period($input, $user->getDateTimezone());
        if ($period === null) {
            $io->error('Give --week, --month or --from and --to.');

            return Command::FAILURE;
        }

        // An engagement that starts or ends inside the period counts too.
        $engagement = $this->engagements->active($user, $project, $period->from)
            ?? $this->engagements->active($user, $project, $period->to);
        if ($engagement === null) {
            $io->error('No active engagement at the start or end of the period.');

            return Command::FAILURE;
        }

        $keys = $engagement->getPdfOptions()->toKeys();
        $keys = array_merge($keys, $this->list($input->getOption('with')));
        $keys = array_diff($keys, $this->list($input->getOption('without')));

        $document = $this->exporter->export($engagement, $period, PdfOptions::fromKeys(array_values($keys)), $input->getOption('locale'));
        $target = rtrim((string) $input->getOption('out'), '/') . '/' . $document->filename;
        file_put_contents($target, $document->content);

        $io->success(sprintf('%s (%d bytes)', $target, strlen($document->content)));

        return Command::SUCCESS;
    }

    private function period(InputInterface $input, \DateTimeZone $zone): ?Period
    {
        if ($input->getOption('week') !== null) {
            [$year, $week] = array_map('intval', explode('-', (string) $input->getOption('week')));

            return Period::week($year, $week, $zone);
        }
        if ($input->getOption('month') !== null) {
            [$year, $month] = array_map('intval', explode('-', (string) $input->getOption('month')));

            return Period::month($year, $month, $zone);
        }
        if ($input->getOption('from') !== null && $input->getOption('to') !== null) {
            return Period::range(new \DateTimeImmutable((string) $input->getOption('from'), $zone), new \DateTimeImmutable((string) $input->getOption('to'), $zone));
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function list(?string $value): array
    {
        return $value === null || $value === '' ? [] : array_map('trim', explode(',', $value));
    }
}
