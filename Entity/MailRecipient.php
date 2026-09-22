<?php

namespace KimaiPlugin\DrehzettelBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;

// Remembers the last address a timesheet was mailed to, per engagement.
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
}
