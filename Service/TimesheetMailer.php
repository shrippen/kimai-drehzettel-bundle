<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Mail\KimaiMailer;
use KimaiPlugin\DrehzettelBundle\Domain\PdfDocument;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Mails a generated timesheet PDF to the production office.
 */
class TimesheetMailer
{
    public function __construct(private readonly KimaiMailer $mailer)
    {
    }

    /**
     * @throws \RuntimeException when no "from" address is configured (Kimai's mail settings)
     */
    public function send(string $toAddress, string $subject, string $body, PdfDocument $document): void
    {
        $email = (new Email())
            ->to(new Address($toAddress))
            ->subject($subject)
            ->text($body)
            ->attach($document->content, $document->filename, 'application/pdf');

        $this->mailer->send($email);
    }
}
