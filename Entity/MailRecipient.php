<?php

namespace KimaiPlugin\DrehzettelBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\DrehzettelBundle\Domain\MailSchedule;
use KimaiPlugin\DrehzettelBundle\Domain\MailTemplate;
use KimaiPlugin\DrehzettelBundle\Enum\MailRhythm;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;

// Mail settings per engagement: last address used, text template, automatic schedule.
#[ORM\Entity(repositoryClass: MailRecipientRepository::class)]
#[ORM\Table(name: 'kimai2_ext_drehzettel_mail_recipient')]
#[ORM\UniqueConstraint(name: 'uniq_drehzettel_mail_engagement', columns: ['engagement_id'])]
class MailRecipient
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Engagement::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Engagement $engagement = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email = '';

    // Null: the translated default text.
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    #[ORM\Column(type: Types::STRING, length: 16, options: ['default' => 'off'])]
    private string $rhythm = 'off'; // MailRhythm::OFF; an enum case in a default needs PHP 8.2

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => MailSchedule::DEFAULT_WEEKDAY])]
    private int $weekday = MailSchedule::DEFAULT_WEEKDAY;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => MailSchedule::DEFAULT_HOUR])]
    private int $hour = MailSchedule::DEFAULT_HOUR;

    // Send time last handled (sent or skipped), e.g. "2026-09-28T07:00:00+02:00".
    // A string: a DATETIME column would lose the zone the slot was computed in.
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $lastSlot = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEngagement(): ?Engagement
    {
        return $this->engagement;
    }

    public function setEngagement(Engagement $engagement): void
    {
        $this->engagement = $engagement;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    // Null parts fall back to the default text.
    public function getTemplate(): ?MailTemplate
    {
        if ($this->subject === null && $this->body === null) {
            return null;
        }

        return new MailTemplate((string) $this->subject, (string) $this->body);
    }

    public function setTemplate(?MailTemplate $template): void
    {
        $this->subject = $template?->subject;
        $this->body = $template?->body;
    }

    public function getSchedule(): MailSchedule
    {
        return new MailSchedule(MailRhythm::tryFrom($this->rhythm) ?? MailRhythm::OFF, $this->weekday, $this->hour);
    }

    public function setSchedule(MailSchedule $schedule): void
    {
        $this->rhythm = $schedule->rhythm->value;
        $this->weekday = $schedule->weekday;
        $this->hour = $schedule->hour;
    }

    // True when this send time was already handled.
    public function isHandled(\DateTimeImmutable $slot): bool
    {
        return $this->lastSlot === $slot->format(\DATE_ATOM);
    }

    public function setLastSlot(?\DateTimeImmutable $slot): void
    {
        $this->lastSlot = $slot?->format(\DATE_ATOM);
    }
}
