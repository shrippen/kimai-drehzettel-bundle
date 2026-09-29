<?php

namespace KimaiPlugin\DrehzettelBundle\Command;

use KimaiPlugin\DrehzettelBundle\Service\ScheduledMails;
use KimaiPlugin\DrehzettelBundle\Service\SchemaStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// For cron, e.g. every 15 minutes: */15 * * * * bin/console drehzettel:mail:due
#[AsCommand(name: 'drehzettel:mail:due', description: 'Send the automatic timesheet mails that are due')]
final class MailCommand extends Command
{
    public function __construct(
        private readonly ScheduledMails $mails,
        private readonly SchemaStatus $schema,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Cron output reaches the administrator: name the missing step.
        if (!$this->schema->isCurrent()) {
            $output->writeln('Database update pending, no mails sent. Run: ' . SchemaStatus::INSTALL_COMMAND);

            return Command::FAILURE;
        }

        $sent = $this->mails->sendDue(new \DateTimeImmutable());
        $output->writeln(sprintf('%d mail(s) sent.', $sent));

        return Command::SUCCESS;
    }
}
