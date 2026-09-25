<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

// Response shapes of the external API. Dates are Y-m-d, money is in cents.
final class ApiJson
{
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * One entry of GET /v1/engagements. No pay: the list is for picking a project.
     *
     * @return array<string, mixed>
     */
    public static function engagement(Engagement $engagement): array
    {
        $project = $engagement->getProject();

        return [
            'engagementId' => $engagement->getId(),
            'projectId' => $project?->getId(),
            'projectName' => $project?->getName(),
            'customerName' => $project?->getCustomer()?->getName(),
            'rulesetName' => $engagement->getRulesetName(),
            'crewRole' => $engagement->getRole(),
            'validFrom' => $engagement->getValidFrom()?->format(self::DATE_FORMAT),
            'validTo' => $engagement->getValidTo()?->format(self::DATE_FORMAT),
            'toggleDefault' => true,
        ];
    }
}
