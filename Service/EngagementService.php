<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\Project;
use App\Entity\User;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;

class EngagementService
{
    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly RulesetCatalog $catalog,
    ) {
    }

    /**
     * Opens an engagement with a snapshot of the chosen ruleset.
     *
     * @throws \DomainException when it overlaps another engagement of the same user and project
     */
    public function open(
        User $user,
        Project $project,
        string $role,
        PayTerms $terms,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
        string $rulesetKey,
    ): Engagement {
        if ($validTo !== null && $validTo < $validFrom) {
            throw new \DomainException('Engagement ends before it starts.');
        }
        $this->assertNoOverlap($user, $project, $validFrom, $validTo);

        $rules = $this->catalog->get($rulesetKey);

        $engagement = new Engagement();
        $engagement->setUser($user);
        $engagement->setProject($project);
        $engagement->setRole($role);
        $engagement->setPayKind($terms->kind);
        $engagement->setGageCents($terms->gageCents);
        $engagement->setCateringDeductionCents($terms->cateringDeductionCents);
        $engagement->setValidFrom($validFrom);
        $engagement->setValidTo($validTo);
        $engagement->setRulesetName($rules->name);
        $engagement->setRules(RulesetCodec::toArray($rules));

        $this->engagements->save($engagement);

        return $engagement;
    }

    public function active(User $user, Project $project, \DateTimeImmutable $date): ?Engagement
    {
        return $this->engagements->findActive($user, $project, $date);
    }

    public function ruleset(Engagement $engagement): Ruleset
    {
        return RulesetCodec::fromArray($engagement->getRules());
    }

    public function terms(Engagement $engagement): PayTerms
    {
        return new PayTerms(
            $engagement->getPayKind(),
            $engagement->getGageCents(),
            $engagement->getCateringDeductionCents(),
        );
    }

    private function assertNoOverlap(User $user, Project $project, \DateTimeImmutable $from, ?\DateTimeImmutable $to): void
    {
        foreach ($this->engagements->findForUserProject($user, $project) as $existing) {
            $existingTo = $existing->getValidTo();
            $endsBefore = $existingTo !== null && $existingTo < $from;
            $startsAfter = $to !== null && $to < $existing->getValidFrom();
            if (!$endsBefore && !$startsAfter) {
                throw new \DomainException('Engagement overlaps an existing one for this user and project.');
            }
        }
    }
}
