<?php

namespace KimaiPlugin\DrehzettelBundle\Entity;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;

/**
 * Employment of one user on one project. Holds pay and a ruleset snapshot,
 * so later edits of a template never change past results.
 */
#[ORM\Entity(repositoryClass: EngagementRepository::class)]
#[ORM\Table(name: 'kimai2_ext_drehzettel_engagement')]
#[ORM\Index(columns: ['user_id', 'project_id'], name: 'idx_drehzettel_engagement_user_project')]
class Engagement
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $role = '';

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $payKind = PayKind::WEEKLY->value;

    #[ORM\Column(type: Types::INTEGER)]
    private int $gageCents = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $cateringDeductionCents = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $rulesetName = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $rules = [];

    /** @var list<string>|null null means the defaults */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pdfOptions = null;

    /**
     * Kimai Activity ids this engagement counts as film time for. Null or empty means
     * "every activity on the project" (the pre-2026-09-24 default) - set this to
     * restrict detection to specific activities, e.g. a "Set" activity while a
     * separate "Anfahrt" (commute) activity on the same project stays private,
     * unbillable tracking: not a film day, not summed into the shooting day span,
     * not paid.
     *
     * @var list<int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $activityIds = null;

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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(Project $project): void
    {
        $this->project = $project;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getPayKind(): PayKind
    {
        return PayKind::from($this->payKind);
    }

    public function setPayKind(PayKind $kind): void
    {
        $this->payKind = $kind->value;
    }

    public function getGageCents(): int
    {
        return $this->gageCents;
    }

    public function setGageCents(int $cents): void
    {
        $this->gageCents = $cents;
    }

    public function getCateringDeductionCents(): int
    {
        return $this->cateringDeductionCents;
    }

    public function setCateringDeductionCents(int $cents): void
    {
        $this->cateringDeductionCents = $cents;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeImmutable $date): void
    {
        $this->validFrom = $date;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $date): void
    {
        $this->validTo = $date;
    }

    public function getRulesetName(): string
    {
        return $this->rulesetName;
    }

    public function setRulesetName(string $name): void
    {
        $this->rulesetName = $name;
    }

    public function getPdfOptions(): PdfOptions
    {
        return PdfOptions::fromKeys($this->pdfOptions);
    }

    public function setPdfOptions(PdfOptions $options): void
    {
        $this->pdfOptions = $options->toKeys();
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

    /**
     * @return list<int> empty means unrestricted (every activity on the project counts)
     */
    public function getActivityIds(): array
    {
        return $this->activityIds ?? [];
    }

    /**
     * @param list<int> $activityIds empty/[] clears the restriction
     */
    public function setActivityIds(array $activityIds): void
    {
        $this->activityIds = $activityIds === [] ? null : array_values(array_unique($activityIds));
    }

    /**
     * Whether an entry on this activity counts towards this engagement's film time -
     * true when unrestricted, or the activity's id is in the configured list.
     */
    public function appliesToActivity(?Activity $activity): bool
    {
        if ($this->activityIds === null || $this->activityIds === []) {
            return true;
        }
        if ($activity === null || $activity->getId() === null) {
            return false;
        }

        return \in_array($activity->getId(), $this->activityIds, true);
    }
}
