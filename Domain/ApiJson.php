<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

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

    /**
     * GET/PUT /v1/film-days/{date}. Keys were added over time: clients must ignore unknown ones.
     *
     *   stored category null, date a Saturday -> category null, effectiveCategory "saturday"
     *
     * @param DayCategory $autoCategory what the date is without an override (weekday, public holiday)
     * @return array<string, mixed>
     */
    public static function filmDay(string $date, Engagement $engagement, ?FilmDay $day, Ruleset $rules, DayCategory $autoCategory): array
    {
        return [
            'date' => $date,
            'engagementId' => $engagement->getId(),
            'breakMinutes' => $day?->getBreakMinutes(),
            'catering' => ($day?->getCatering() ?? Catering::NO) === Catering::YES,
            'category' => $day?->getCategory()?->value,
            'note' => $day?->getNote(),
            'dayType' => ($day?->getDayType() ?? DayType::WORKDAY)->value,
            'productionDay' => $day?->getProductionDay(),
            'extraPayCents' => $day?->getExtraPayCents() ?? 0,
            'shootingDayNumber' => $day?->getShootingDayNumber(),
            'defaultBreakMinutes' => $rules->defaultBreakMinutes,
            'effectiveCategory' => ($day?->getCategory() ?? $autoCategory)->value,
            'streakMode' => $rules->streakMode->value,
        ];
    }
}
