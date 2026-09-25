<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\Project;
use App\Entity\Timesheet;
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

        $this->save($engagement);

        return $engagement;
    }

    /**
     * Saves a new or changed engagement.
     *
     * @throws \DomainException when it ends before it starts or overlaps another one of the same user and project
     */
    public function save(Engagement $engagement): void
    {
        $from = $engagement->getValidFrom();
        $to = $engagement->getValidTo();
        if ($to !== null && $to < $from) {
            throw new \DomainException('Engagement ends before it starts.');
        }
        $this->assertNoOverlap($engagement->getUser(), $engagement->getProject(), $from, $to, $engagement);

        $this->engagements->save($engagement);
    }

    // Replaces the snapshot of one engagement. Other engagements keep theirs.
    public function replaceRules(Engagement $engagement, Ruleset $rules): void
    {
        $engagement->setRulesetName($rules->name);
        $engagement->setRules(RulesetCodec::toArray($rules));
        $this->engagements->save($engagement);
    }

    public function remove(Engagement $engagement): void
    {
        $this->engagements->remove($engagement);
    }

    public function active(User $user, Project $project, \DateTimeImmutable $date): ?Engagement
    {
        return $this->engagements->findActive($user, $project, $date);
    }

    // Shared by TimesheetFormExtension and TimesheetCleanupSubscriber: both need the
    // engagement a given timesheet entry belongs to, keyed off its own project/user/date.
    public function activeFor(Timesheet $timesheet): ?Engagement
    {
        $project = $timesheet->getProject();
        $user = $timesheet->getUser();
        if ($project === null || $user === null || $timesheet->getBegin() === null) {
            return null;
        }

        return $this->active($user, $project, self::dateOf($timesheet));
    }

    public static function dateOf(Timesheet $timesheet): \DateTimeImmutable
    {
        $begin = $timesheet->getBegin();
        \assert($begin !== null);

        return self::dayOf($begin);
    }

    // Calendar date of a begin, in the zone it carries (the timesheet's own, as Kimai shows it).
    public static function dayOf(\DateTimeInterface $begin): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($begin)->setTime(0, 0);
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

    private function assertNoOverlap(User $user, Project $project, \DateTimeImmutable $from, ?\DateTimeImmutable $to, ?Engagement $except = null): void
    {
        foreach ($this->engagements->findForUserProject($user, $project) as $existing) {
            if ($existing === $except || ($existing->getId() !== null && $existing->getId() === $except?->getId())) {
                continue;
            }
            $existingTo = $existing->getValidTo();
            $endsBefore = $existingTo !== null && $existingTo < $from;
            $startsAfter = $to !== null && $to < $existing->getValidFrom();
            if (!$endsBefore && !$startsAfter) {
                throw new \DomainException('Engagement overlaps an existing one for this user and project.');
            }
        }
    }
}
