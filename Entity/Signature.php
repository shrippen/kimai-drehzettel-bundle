<?php

namespace KimaiPlugin\DrehzettelBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\DrehzettelBundle\Repository\SignatureRepository;

/**
 * Image of the user's signature for the crew line of the timesheet PDF.
 */
#[ORM\Entity(repositoryClass: SignatureRepository::class)]
#[ORM\Table(name: 'kimai2_ext_drehzettel_signature')]
#[ORM\UniqueConstraint(name: 'uniq_drehzettel_signature_user', columns: ['user_id'])]
class Signature
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $mime = '';

    // base64, so the value survives any text column
    #[ORM\Column(type: Types::TEXT)]
    private string $data = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function setMime(string $mime): void
    {
        $this->mime = $mime;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }
}
