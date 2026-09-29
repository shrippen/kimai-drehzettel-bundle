<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Entity\MailRecipient;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;
use Psr\Log\LoggerInterface;

/**
 * Sends the automatic timesheet mails that are due (MailSchedule):
 *
 *   cron: bin/console drehzettel:mail:due ──┐
 *   web request end (ScheduledMailSubscriber)├──► sendDue() ──► claim slot ──► PDF + mail
 *
 * A period without film days is skipped. A failed send is retried by the next run,
 * for at most a day after its send time.
 */
class ScheduledMails
{
    private const RETRY_FOR = '+1 day';

    public function __construct(
        private readonly MailRecipientRepository $recipients,
        private readonly MailComposer $composer,
        private readonly PdfExporter $pdf,
        private readonly TimesheetMailer $mailer,
        private readonly DayInputBuilder $days,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Returns the number of mails sent.
    public function sendDue(\DateTimeImmutable $now): int
    {
        $sent = 0;
        foreach ($this->recipients->findScheduled() as $settings) {
            if ($this->sendIfDue($settings, $now)) {
                ++$sent;
            }
        }

        return $sent;
    }

    private function sendIfDue(MailRecipient $settings, \DateTimeImmutable $now): bool
    {
        $engagement = $settings->getEngagement();
        $local = $now->setTimezone($engagement->getUser()->getDateTimezone());
        $schedule = $settings->getSchedule();
        $slot = $schedule->lastSlot($local);
        if ($slot === null || $settings->isHandled($slot)) {
            return false;
        }

        $previous = $this->recipients->claim($settings, $slot);
        if ($previous === false) {
            return false;
        }

        $period = $schedule->period($slot);
        if ($this->days->build($engagement, $period->from, $period->endExclusive()) === []) {
            return false; // nothing worked: no empty timesheet
        }

        try {
            $document = $this->pdf->export($engagement, $period, $engagement->getPdfOptions());
            $mail = $this->composer->compose($engagement, $period, $settings);
            $this->mailer->send($engagement->getUser(), $settings->getEmail(), $mail, $document);
        } catch (\Throwable $e) {
            $this->logger->error('Drehzettel: automatic mail of engagement {id} failed: {error}', ['id' => $engagement->getId(), 'error' => $e->getMessage()]);
            if ($local < $slot->modify(self::RETRY_FOR)) {
                $this->recipients->release($settings, $previous);
            }

            return false;
        }

        return true;
    }
}
