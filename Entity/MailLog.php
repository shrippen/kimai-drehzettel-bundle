<?php

namespace KimaiPlugin\DrehzettelBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\DrehzettelBundle\Enum\MailStatus;
use KimaiPlugin\DrehzettelBundle\Enum\MailTrigger;
use KimaiPlugin\DrehzettelBundle\Repository\MailLogRepository;

/**
 * One timesheet mail of an engagement, shown in the mail settings. An automatic
 * mail has one row per send time: a retry after a failure updates it (attempts).
 */
#[ORM\Entity(repositoryClass: MailLogRepository::class)]
#[ORM\Table(name: 'kimai2_ext_drehzettel_mail_log')]
#[ORM\Index(name: 'idx_drehzettel_mail_log_slot', columns: ['engagement_id', 'slot'])]
class MailLog
{
    public const ERROR_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Engagement::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Engagement $engagement = null;

    // Time of the (last) attempt, e.g. "2026-10-26T07:04:12+01:00". A string like MailRecipient::$lastSlot.
    #[ORM\Column(name: 'attempted_at', type: Types::STRING, length: 32)]
    private string $attemptedAt = '';

    // Send time of an automatic mail, null for a manual one.
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $slot = null;

    #[ORM\Column(name: 'mail_trigger', type: Types::STRING, length: 16)]
    private string $trigger = 'manual'; // MailTrigger::MANUAL; an enum case in a default needs PHP 8.2

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $status = 'sent'; // MailStatus::SENT

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $recipient = '';

    #[ORM\Column(name: 'period_from', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $from = null;

    #[ORM\Column(name: 'period_to', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $to = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    private int $attempts = 1;

    // Reason of the last failure (exception message, cut to ERROR_LENGTH).
    #[ORM\Column(type: Types::STRING, length: self::ERROR_LENGTH, nullable: true)]
    private ?string $error = null;

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

    public function getAttemptedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->attemptedAt);
    }

    public function setAttemptedAt(\DateTimeImmutable $at): void
    {
        $this->attemptedAt = $at->format(\DATE_ATOM);
    }

    public function setSlot(?\DateTimeImmutable $slot): void
    {
        $this->slot = $slot?->format(\DATE_ATOM);
    }

    public function getTrigger(): MailTrigger
    {
        return MailTrigger::from($this->trigger);
    }

    public function setTrigger(MailTrigger $trigger): void
    {
        $this->trigger = $trigger->value;
    }

    public function getStatus(): MailStatus
    {
        return MailStatus::from($this->status);
    }

    public function setStatus(MailStatus $status): void
    {
        $this->status = $status->value;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): void
    {
        $this->recipient = $recipient;
    }

    public function getFrom(): ?\DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): ?\DateTimeImmutable
    {
        return $this->to;
    }

    public function setPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function countAttempt(): void
    {
        ++$this->attempts;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error === null ? null : mb_substr($error, 0, self::ERROR_LENGTH);
    }
}
