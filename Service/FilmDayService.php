<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;

class FilmDayService
{
    public function __construct(private readonly FilmDayRepository $days)
    {
    }

    // Creates the film day or updates the existing one for this date.
    public function save(
        Engagement $engagement,
        \DateTimeImmutable $date,
        ?int $breakMinutes,
        Catering $catering,
        ?DayCategory $category = null,
        DayType $type = DayType::WORKDAY,
        ?int $productionDay = null,
        ?string $note = null,
    ): FilmDay {
        $day = $this->days->findOne($engagement, $date) ?? new FilmDay();
        $day->setEngagement($engagement);
        $day->setDate($date);
        $day->setBreakMinutes($breakMinutes);
        $day->setCatering($catering);
        $day->setCategory($category);
        $day->setDayType($type);
        $day->setProductionDay($productionDay);
        $day->setNote($note);

        $this->days->save($day);

        return $day;
    }
}
