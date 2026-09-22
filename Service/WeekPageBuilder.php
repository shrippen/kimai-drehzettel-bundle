<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Domain\Format;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Domain\TimesheetMeta;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\PdfOption;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;

/**
 * Everything the week view shows, for the page and for the live preview:
 * seven rows with calculated values, the film day inputs, sums, weekly line.
 */
class WeekPageBuilder
{
    private const DATE_KEY = 'Y-m-d';
    private const DAYS_PER_WEEK = 7;

    public function __construct(
        private readonly FilmWeekService $weeks,
        private readonly EngagementService $engagements,
        private readonly TimesheetViewBuilder $views,
        private readonly FilmDayRepository $filmDays,
        private readonly ComplianceChecker $compliance,
        private readonly MailRecipientRepository $mailRecipients,
    ) {
    }

    /**
     * @param array<string, FilmDayDraft> $drafts unsaved input by date, wins over stored data
     * @return array<string, mixed>
     */
    public function build(Engagement $engagement, int $isoYear, int $isoWeek, array $drafts = []): array
    {
        $user = $engagement->getUser();
        $locale = $user->getLanguage() === 'de' ? 'de' : 'en';
        $period = Period::week($isoYear, $isoWeek, $user->getDateTimezone());
        $rules = $this->engagements->ruleset($engagement);

        $result = $this->weeks->week($engagement, $isoYear, $isoWeek, $drafts);
        $meta = new TimesheetMeta($user->getDisplayName(), $engagement->getProject()->getName(), $engagement->getRole(), $locale, $engagement->getGageCents() > 0);
        $options = new PdfOptions([
            PdfOption::BREAK, PdfOption::TIERS, PdfOption::NIGHT, PdfOption::UNDER, PdfOption::CATERING,
            PdfOption::DAY_TYPE, PdfOption::PAY, PdfOption::ALL_WEEKDAYS, PdfOption::WEEKLY_OVERTIME,
        ]);
        $view = $this->views->build($meta, $period, [$result], $rules, $options);

        $rows = $view['weeks'][0]['rows'] ?? $this->emptyRows($period, $locale);
        $film = $this->filmValues($engagement, $period, $drafts);
        $monday = $period->from;

        return [
            'engagement' => $engagement,
            'year' => $isoYear,
            'week' => $isoWeek,
            'monday' => $monday,
            'sunday' => $period->to,
            'previous' => ['year' => (int) $monday->modify('-7 days')->format('o'), 'week' => (int) $monday->modify('-7 days')->format('W')],
            'next' => ['year' => (int) $monday->modify('+7 days')->format('o'), 'week' => (int) $monday->modify('+7 days')->format('W')],
            'rows' => $rows,
            'film' => $film,
            'columns' => $view['columns'],
            'sums' => $view['weeks'][0]['sums'] ?? $this->zeroSums(count($rules->dailyTiers), $locale),
            'weekly' => $view['weeks'][0]['weekly'] ?? null,
            'default_break' => $rules->defaultBreakMinutes,
            'has_pay' => $engagement->getGageCents() > 0,
            'locale' => $locale,
            'warnings' => $this->compliance->check($result, $this->weeks->lastDayBefore($engagement, $period->from)),
            'mail_to' => $this->mailRecipients->findForEngagement($engagement)?->getEmail() ?? '',
        ];
    }

    /**
     * Film day inputs by date: stored values, replaced by drafts.
     *
     * @param array<string, FilmDayDraft> $drafts
     * @return array<string, array<string, mixed>>
     */
    private function filmValues(Engagement $engagement, Period $period, array $drafts): array
    {
        $values = [];
        foreach ($this->filmDays->findRange($engagement, $period->from, $period->endExclusive()) as $day) {
            $values[$day->getDate()->format(self::DATE_KEY)] = [
                'break' => $day->getBreakMinutes(),
                'catering' => $day->getCatering() === Catering::YES,
                'category' => $day->getCategory()?->value,
                'type' => $day->getDayType()->value,
                'production_day' => $day->getProductionDay(),
                'note' => (string) $day->getNote(),
            ];
        }

        foreach ($drafts as $key => $draft) {
            $values[$key] = [
                'break' => $draft->breakMinutes,
                'catering' => $draft->catering === Catering::YES,
                'category' => $draft->category?->value,
                'type' => $draft->type->value,
                'production_day' => $draft->productionDay,
                'note' => (string) $draft->note,
            ];
        }

        return $values;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emptyRows(Period $period, string $locale): array
    {
        $rows = [];
        for ($i = 0; $i < self::DAYS_PER_WEEK; ++$i) {
            $date = $period->from->modify("+$i days");
            $rows[] = ['empty' => true, 'key' => $date->format(self::DATE_KEY), 'date' => $this->views->dateLabel($date, $locale)];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function zeroSums(int $tierCount, string $locale): array
    {
        $zero = Format::hours(0);

        return [
            'work' => $zero, 'tiers' => array_fill(0, $tierCount, $zero), 'night' => $zero, 'under' => $zero,
            'catering' => '0x', 'pay' => Format::money(0, $locale),
        ];
    }
}
