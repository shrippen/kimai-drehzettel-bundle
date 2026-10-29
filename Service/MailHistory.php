<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\MailLog;
use KimaiPlugin\DrehzettelBundle\Enum\MailStatus;
use KimaiPlugin\DrehzettelBundle\Enum\MailTrigger;
use KimaiPlugin\DrehzettelBundle\Repository\MailLogRepository;
use Psr\Log\LoggerInterface;

/**
 * Log of the timesheet mails of an engagement, to check afterwards that the
 * automatic ones went out. "Sent" means the mail server accepted the mail.
 * Writing the log never throws: a mail that went out must not count as failed,
 * and a failed one must still be retried (ScheduledMails).
 */
class MailHistory
{
    public const SHOWN = 10;

    public function __construct(
        private readonly MailLogRepository $log,
        private readonly LoggerInterface $logger,
    ) {
    }

    // One row per send time: a retry after a failure updates the row and counts the attempt.
    public function automatic(Engagement $engagement, \DateTimeImmutable $slot, Period $period, string $recipient, MailStatus $status, ?string $error = null): void
    {
        $entry = $this->log->findForSlot($engagement, $slot);
        if ($entry === null) {
            $entry = $this->entry($engagement, MailTrigger::AUTOMATIC, $period);
            $entry->setSlot($slot);
        } else {
            $entry->countAttempt();
        }

        $this->record($entry, $recipient, $status, $error);
    }

    public function manual(Engagement $engagement, Period $period, string $recipient, MailStatus $status, ?string $error = null): void
    {
        $this->record($this->entry($engagement, MailTrigger::MANUAL, $period), $recipient, $status, $error);
    }

    /**
     * @return list<MailLog> the last SHOWN mails, newest first
     */
    public function latest(Engagement $engagement): array
    {
        return $this->log->findLatest($engagement, self::SHOWN);
    }

    private function entry(Engagement $engagement, MailTrigger $trigger, Period $period): MailLog
    {
        $entry = new MailLog();
        $entry->setEngagement($engagement);
        $entry->setTrigger($trigger);
        $entry->setPeriod($period->from, $period->to);

        return $entry;
    }

    private function record(MailLog $entry, string $recipient, MailStatus $status, ?string $error): void
    {
        $zone = $entry->getEngagement()->getUser()->getDateTimezone();
        $entry->setAttemptedAt(new \DateTimeImmutable('now', $zone));
        $entry->setRecipient($recipient);
        $entry->setStatus($status);
        $entry->setError($error);

        try {
            $this->log->save($entry);
        } catch (\Throwable $e) {
            $this->logger->error('Drehzettel: mail log not written: {error}', ['error' => $e->getMessage()]);
        }
    }
}
