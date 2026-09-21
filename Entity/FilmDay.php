<?php

namespace KimaiPlugin\DrehzettelBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;

/**
 * Film-specific data for one shooting day. Times come from the Kimai
 * timesheet entry. Null values fall back to defaults of the ruleset.
 */
#[ORM\Entity(repositoryClass: FilmDayRepository::class)]
#[ORM\Table(name: 'kimai2_ext_drehzettel_day')]
#[ORM\UniqueConstraint(name: 'uniq_drehzettel_day_engagement_date', columns: ['engagement_id', 'day_date'])]
class FilmDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Engagement::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Engagement $engagement = null;

    #[ORM\Column(name: 'day_date', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $breakMinutes = null;

    #[ORM\Column(type: Types::STRING, length: 8)]
    private string $catering = Catering::NO->value;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $dayType = DayType::WORKDAY->value;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $productionDay = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

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

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getBreakMinutes(): ?int
    {
        return $this->breakMinutes;
    }

    public function setBreakMinutes(?int $minutes): void
    {
        $this->breakMinutes = $minutes;
    }

    public function getCatering(): Catering
    {
        return Catering::from($this->catering);
    }

    public function setCatering(Catering $catering): void
    {
        $this->catering = $catering->value;
    }

    // Null means: derive from the weekday.
    public function getCategory(): ?DayCategory
    {
        return $this->category === null ? null : DayCategory::from($this->category);
    }

    public function setCategory(?DayCategory $category): void
    {
        $this->category = $category?->value;
    }

    public function getDayType(): DayType
    {
        return DayType::from($this->dayType);
    }

    public function setDayType(DayType $type): void
    {
        $this->dayType = $type->value;
    }

    public function getProductionDay(): ?int
    {
        return $this->productionDay;
    }

    public function setProductionDay(?int $day): void
    {
        $this->productionDay = $day;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }
}
