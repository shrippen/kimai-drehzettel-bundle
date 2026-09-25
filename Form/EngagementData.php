<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use App\Entity\Project;
use App\Entity\User;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form model of EngagementType. Money in the currency unit (1581.00), the
 * engagement stores cents; see terms().
 */
final class EngagementData
{
    private const CENTS = 100;

    #[Assert\NotNull(groups: ['create'])]
    public ?User $user = null;

    #[Assert\NotNull(groups: ['create'])]
    public ?Project $project = null;

    #[Assert\NotBlank(groups: ['create'])]
    public ?string $ruleset = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $role = null;

    public PayKind $payKind = PayKind::WEEKLY;

    #[Assert\PositiveOrZero]
    public ?float $gage = null;

    #[Assert\PositiveOrZero]
    public ?float $cateringDeduction = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $validFrom = null;

    #[Assert\GreaterThanOrEqual(propertyPath: 'validFrom', message: 'drehzettel.engagement.ends_before_start')]
    public ?\DateTimeImmutable $validTo = null;

    public static function fromEngagement(Engagement $engagement): self
    {
        $data = new self();
        $data->user = $engagement->getUser();
        $data->project = $engagement->getProject();
        $data->role = $engagement->getRole();
        $data->payKind = $engagement->getPayKind();
        $data->gage = $engagement->getGageCents() / self::CENTS;
        $data->cateringDeduction = $engagement->getCateringDeductionCents() / self::CENTS;
        $data->validFrom = $engagement->getValidFrom();
        $data->validTo = $engagement->getValidTo();

        return $data;
    }

    public function terms(): PayTerms
    {
        return new PayTerms($this->payKind, self::cents($this->gage), self::cents($this->cateringDeduction));
    }

    // Copies the editable fields; user, project and ruleset are fixed after creation.
    public function applyTo(Engagement $engagement): void
    {
        $terms = $this->terms();
        $engagement->setRole((string) $this->role);
        $engagement->setPayKind($terms->kind);
        $engagement->setGageCents($terms->gageCents);
        $engagement->setCateringDeductionCents($terms->cateringDeductionCents);
        $engagement->setValidFrom($this->validFrom);
        $engagement->setValidTo($this->validTo);
    }

    private static function cents(?float $amount): int
    {
        return (int) round(($amount ?? 0) * self::CENTS);
    }
}
