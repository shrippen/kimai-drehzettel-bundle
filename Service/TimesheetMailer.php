<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Configuration\MailConfiguration;
use App\Entity\User;
use App\Mail\KimaiMailer;
use KimaiPlugin\DrehzettelBundle\Domain\MailTemplate;
use KimaiPlugin\DrehzettelBundle\Domain\PdfDocument;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Mails a generated timesheet PDF to the production office: text and HTML
 * part, sent in the user's name (Kimai's "from" address), replies go to the user.
 */
class TimesheetMailer
{
    private const TEMPLATE = '@Drehzettel/mail/timesheet.html.twig';

    public function __construct(
        private readonly KimaiMailer $mailer,
        private readonly MailConfiguration $configuration,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @throws \RuntimeException when no "from" address is configured (Kimai's mail settings)
     */
    public function send(User $sender, string $toAddress, MailTemplate $mail, PdfDocument $document): void
    {
        $email = (new Email())
            ->to(new Address($toAddress))
            ->subject($mail->subject)
            ->text($mail->body)
            ->html($this->html($mail, $document, (string) $sender->getLanguage()))
            ->attach($document->content, $document->filename, 'application/pdf');

        $name = (string) $sender->getDisplayName();
        $from = $this->configuration->getFromAddress();
        if ($from !== null) {
            $email->from(new Address($from, $name));
        }
        if ($sender->getEmail() !== null && $sender->getEmail() !== '') {
            $email->replyTo(new Address($sender->getEmail(), $name));
        }

        $this->mailer->send($email);
    }

    // Paragraphs split at blank lines: "Hallo,\n\nanbei ..." -> <p>Hallo,</p><p>anbei ...</p>
    private function html(MailTemplate $mail, PdfDocument $document, string $locale): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($mail->body));
        $paragraphs = array_values(array_filter(preg_split('/\n\s*\n/', $text) ?: [], static fn (string $p): bool => trim($p) !== ''));

        return $this->twig->render(self::TEMPLATE, [
            'subject' => $mail->subject,
            'paragraphs' => $paragraphs,
            'attachment' => $document->filename,
            'locale' => $locale,
        ]);
    }
}
