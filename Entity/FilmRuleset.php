<?php

namespace KimaiPlugin\DrehzettelBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;

/**
 * User-defined ruleset template. Built-in presets live in code, not here.
 */
#[ORM\Entity(repositoryClass: FilmRulesetRepository::class)]
#[ORM\Table(name: 'kimai2_ext_drehzettel_ruleset')]
#[ORM\UniqueConstraint(name: 'uniq_drehzettel_ruleset_name', columns: ['name'])]
class FilmRuleset
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $rules = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }
}
