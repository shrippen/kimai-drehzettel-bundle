<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Domain\Share;
use KimaiPlugin\DrehzettelBundle\Domain\Tier;
use KimaiPlugin\DrehzettelBundle\Domain\Units;
use KimaiPlugin\DrehzettelBundle\Domain\WeekResult;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;

/**
 * Everything the week page shows, for the page and for the live preview:
 * seven rows, the film day inputs, sums, weekly overtime and warnings.
 *
 * Values stay raw so the template formats them with Kimai's filters:
 * durations in seconds (|duration), money in the currency unit (|money),
 * dates as DateTimeImmutable (|date_short), percent as number (|amount).
 */
class WeekPageBuilder
{
    private const DATE_KEY = 'Y-m-d';
    private const DAYS_PER_WEEK = 7;
    private const CENTS = 100;
    private const DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private readonly FilmWeekService $weeks,
        private readonly EngagementService $engagements,
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
        $period = Period::week($isoYear, $isoWeek, $user->getDateTimezone());
        $rules = $this->engagements->ruleset($engagement);
        $result = $this->weeks->week($engagement, $isoYear, $isoWeek, $drafts);
        $monday = $period->from;
        $hasPay = $engagement->getGageCents() > 0;
        $today = new \DateTimeImmutable('today', $user->getDateTimezone());

        return [
            'engagement' => $engagement,
            'year' => $isoYear,
            'week' => $isoWeek,
            'monday' => $monday,
            'sunday' => $period->to,
            'month' => (int) $monday->format('n'),
            'month_year' => (int) $monday->format('Y'),
            'previous' => $this->weekOf($monday->modify('-7 days')),
            'next' => $this->weekOf($monday->modify('+7 days')),
            'is_current' => $period->contains($today),
            'currency' => $engagement->getProject()?->getCustomer()?->getCurrency() ?? self::DEFAULT_CURRENCY,
            'tiers' => array_map(static fn (Tier $t): float => self::percent($t->basisPoints), $rules->dailyTiers),
            'night_percent' => self::percent($rules->nightBasisPoints),
            'rows' => $this->rows($result, $period, count($rules->dailyTiers), $hasPay),
            'film' => $this->filmValues($engagement, $period, $drafts),
            'sums' => $this->sums($result, count($rules->dailyTiers), $hasPay),
            'weekly' => $rules->weeklyTiers === [] ? null : $this->weekly($result, $rules->weeklyTiers),
            'default_break' => $rules->defaultBreakMinutes,
            'has_pay' => $hasPay,
            'warnings' => $this->compliance->check($result, $this->weeks->lastDayBefore($engagement, $period->from)),
            'mail_to' => $this->mailRecipients->findForEngagement($engagement)?->getEmail() ?? '',
        ];
    }

    /**
     * Seven rows Monday to Sunday; days without a timesheet entry are "empty".
     *
     * @return list<array<string, mixed>>
     */
    private function rows(WeekResult $result, Period $period, int $tierCount, bool $hasPay): array
    {
        $byDate = [];
        foreach ($result->days as $day) {
            $byDate[$day->begin->format(self::DATE_KEY)] = $day;
        }

        $rows = [];
        for ($i = 0; $i < self::DAYS_PER_WEEK; ++$i) {
            $date = $period->from->modify("+$i days");
            $key = $date->format(self::DATE_KEY);
            $day = $byDate[$key] ?? null;
            $rows[] = $day === null ? ['empty' => true, 'key' => $key, 'date' => $date] : $this->row($day, $key, $date, $tierCount, $hasPay);
        }

        return $rows;
    }

    /**
     * Days without tier shares (travel days) still get one zero per tier column.
     *
     * @return array<string, mixed>
     */
    private function row(DayResult $day, string $key, \DateTimeImmutable $date, int $tierCount, bool $hasPay): array
    {
        return [
            'empty' => false,
            'key' => $key,
            'date' => $date,
            'begin' => $day->begin,
            'end' => $day->end,
            'work' => self::seconds($day->workMinutes),
            'tiers' => array_pad(array_map(static fn (Share $s): int => self::seconds($s->minutes), $day->dailyShares), $tierCount, 0),
            'night' => self::seconds($day->nightMinutes),
            'under' => self::seconds($day->underMinutes),
            'pay' => $hasPay && $day->amountCents !== null ? $day->amountCents / self::CENTS : null,
        ];
    }

    /**
     * Week sums. In the week view the week always owns its weekly overtime pay.
     *
     * @return array<string, mixed>
     */
    private function sums(WeekResult $result, int $tierCount, bool $hasPay): array
    {
        $tiers = array_fill(0, $tierCount, 0);
        $work = $night = $under = $catering = $cents = 0;
        foreach ($result->days as $day) {
            $work += $day->workMinutes;
            $night += $day->nightMinutes;
            $under += $day->underMinutes;
            $catering += $day->catering === Catering::YES ? 1 : 0;
            $cents += $day->amountCents ?? 0;
            foreach ($day->dailyShares as $i => $share) {
                $tiers[$i] += $share->minutes;
            }
        }

        return [
            'work' => self::seconds($work),
            'tiers' => array_map(static fn (int $m): int => self::seconds($m), $tiers),
            'surcharges' => self::seconds(array_sum($tiers)),
            'night' => self::seconds($night),
            'under' => self::seconds($under),
            'catering' => $catering,
            'pay' => $hasPay ? ($cents + ($result->weeklyCents ?? 0)) / self::CENTS : null,
        ];
    }

    /**
     * @param list<Tier> $tiers
     * @return list<array{seconds: int, percent: float}>
     */
    private function weekly(WeekResult $result, array $tiers): array
    {
        $parts = [];
        foreach ($tiers as $i => $tier) {
            $parts[] = [
                'seconds' => self::seconds($result->weeklyShares[$i]->minutes ?? 0),
                'percent' => self::percent($tier->basisPoints),
            ];
        }

        return $parts;
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
                'extra_pay' => $day->getExtraPayCents() / self::CENTS,
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
                'extra_pay' => $draft->extraPayCents / self::CENTS,
            ];
        }

        return $values;
    }

    /**
     * @return array{year: int, week: int}
     */
    private function weekOf(\DateTimeImmutable $monday): array
    {
        return ['year' => (int) $monday->format('o'), 'week' => (int) $monday->format('W')];
    }

    private static function seconds(int $minutes): int
    {
        return $minutes * Units::SECONDS_PER_MINUTE;
    }

    // 2500 basis points -> 25.0
    private static function percent(int $basisPoints): float
    {
        return $basisPoints / (Units::BASIS_POINTS / 100);
    }
}
