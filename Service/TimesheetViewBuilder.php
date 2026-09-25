<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\AzvBalance;
use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Domain\Format;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\Share;
use KimaiPlugin\DrehzettelBundle\Domain\TimesheetMeta;
use KimaiPlugin\DrehzettelBundle\Domain\Units;
use KimaiPlugin\DrehzettelBundle\Domain\WeekResult;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\PdfOption;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;

/**
 * Calculated weeks -> the plain array the PDF template renders.
 *
 *   week 1  row row row  ... weekly line | week sums
 *   week 2  row row      ... weekly line | week sums
 *   total (only when the period spans more than one week)
 *
 * Only days inside the period are listed, but weekly overtime always
 * comes from the whole calendar week. It is listed and paid only in the
 * period holding the week's last worked day, so a week across a month
 * boundary is never paid twice (Mon 30.3. - Sat 4.4. -> April).
 */
class TimesheetViewBuilder
{
    private const DATE_KEY = 'Y-m-d';

    // Intl patterns of the long date: "Montag, 15. Juni 2026" / "Monday, 15 June 2026"
    private const DATE_PATTERNS = [
        'de' => 'EEEE, d. MMMM y',
        'en' => 'EEEE, d MMMM y',
    ];

    private const SHORT_DATES = ['de' => 'd.m.Y', 'en' => 'j M Y'];

    // Currency of the timesheet being built, set by build() for the money columns.
    private string $currency = Format::CURRENCY;

    public function __construct(private readonly Labels $labels)
    {
    }

    /**
     * @param list<WeekResult> $weeks
     * @return array<string, mixed>
     */
    public function build(TimesheetMeta $meta, Period $period, array $weeks, Ruleset $rules, PdfOptions $options, ?AzvBalance $azv = null): array
    {
        $locale = $meta->locale === 'de' ? 'de' : 'en';
        $this->currency = $meta->currency;
        $showPay = $meta->hasPay && $options->has(PdfOption::PAY);

        $blocks = [];
        $worked = [];
        foreach ($weeks as $week) {
            $days = array_values(array_filter($week->days, fn (DayResult $d): bool => $period->contains($d->begin)));
            if ($days === []) {
                continue;
            }
            foreach ($days as $day) {
                $worked[] = $day->begin;
            }
            $blocks[] = $this->weekBlock($week, $days, $period, $rules, $options, $locale, $showPay);
        }

        $from = $worked === [] ? $period->from : min($worked);
        $to = $worked === [] ? $period->to : max($worked);

        return [
            'locale' => $locale,
            't' => $this->texts($locale, $meta->displayName, $meta->projectName, $meta->role),
            'period' => $this->periodLabel($from, $to, $locale),
            'from' => $from,
            'to' => $to,
            'columns' => $this->columns($rules, $options, $showPay),
            'weeks' => array_column($blocks, 'view'),
            'total' => count($blocks) > 1 ? $this->total($blocks, $rules, $options, $locale, $showPay) : null,
            'notes' => $options->has(PdfOption::NOTES) ? $this->notes($weeks, $period, $locale) : [],
            'rounding' => $options->has(PdfOption::ROUNDING_NOTE) ? $this->roundingNote($rules, $locale) : null,
            'signature_lines' => $options->has(PdfOption::SIGNATURE_LINES),
            'signature_image' => $options->has(PdfOption::SIGNATURE_IMAGE) ? $meta->signatureDataUri : null,
            'azv' => $azv !== null && $azv->eligible ? $this->azvLine($azv, $locale) : null,
        ];
    }

    // "AZV-Guthaben (TV FFS TZ 6) bis 30.06.2026: 12:30 h aus 25 Drehtagen, davon 1 AZV-Tag (10 h)."
    private function azvLine(AzvBalance $azv, string $locale): string
    {
        return $this->labels->t('drehzettel.azv.pdf_line', $locale, [
            '%date%' => $azv->until->format(self::SHORT_DATES[$locale]),
            '%hours%' => Format::hm($azv->minutes()),
            '%shooting%' => $azv->shootingDays,
            '%days%' => $this->labels->t('drehzettel.azv.days', $locale, ['%count%' => $azv->days()]),
        ]);
    }

    /**
     * @param list<DayResult> $days days of this week inside the period
     * @return array{view: array<string, mixed>, sums: array<string, mixed>}
     */
    private function weekBlock(WeekResult $week, array $days, Period $period, Ruleset $rules, PdfOptions $options, string $locale, bool $showPay): array
    {
        $byDate = [];
        foreach ($days as $day) {
            $byDate[$day->begin->format(self::DATE_KEY)] = $day;
        }

        $rows = [];
        foreach ($this->dates($days, $period, $options) as $date) {
            $day = $byDate[$date->format(self::DATE_KEY)] ?? null;
            $rows[] = $day === null ? ['empty' => true, 'key' => $date->format(self::DATE_KEY), 'date' => $this->dateLabel($date, $locale)] : $this->row($day, $rules, $locale, $showPay);
        }

        $ownsWeekly = $period->contains($week->days[array_key_last($week->days)]->begin);
        $sums = $this->sums($week, $days, $rules, $ownsWeekly);
        $weekly = $ownsWeekly && $options->has(PdfOption::WEEKLY_OVERTIME) ? $this->weeklyLine($week, $rules, $locale) : null;

        return [
            'view' => ['rows' => $rows, 'weekly' => $weekly, 'sums' => $this->formatSums($sums, $locale, $showPay)],
            'sums' => $sums,
        ];
    }

    /**
     * @param list<DayResult> $days
     * @return list<\DateTimeImmutable>
     */
    private function dates(array $days, Period $period, PdfOptions $options): array
    {
        if (!$options->has(PdfOption::ALL_WEEKDAYS)) {
            return array_map(static fn (DayResult $d): \DateTimeImmutable => $d->begin->setTime(0, 0), $days);
        }

        $first = $days[0]->begin->setTime(0, 0);
        $monday = $first->modify('monday this week');
        $dates = [];
        for ($i = 0; $i < 7; ++$i) {
            $date = $monday->modify("+$i days");
            if ($period->contains($date)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DayResult $day, Ruleset $rules, string $locale, bool $showPay): array
    {
        return [
            'empty' => false,
            'key' => $day->begin->format(self::DATE_KEY),
            'date' => $this->dateLabel($day->begin, $locale),
            'shooting_day' => $day->shootingDayNumber === null ? '' : $this->labels->t('drehzettel.shooting_day.label', $locale, ['%number%' => $day->shootingDayNumber]),
            'day_number' => $day->showsDayNumber() ? $this->labels->t('drehzettel.production_day.label', $locale, ['%number%' => $day->dayNumber]) : '',
            'begin' => $day->begin->format('H:i'),
            'end' => $day->end->format('H:i'),
            'break' => Format::hm($day->breakMinutes),
            'work' => Format::hours($day->workMinutes),
            'tiers' => array_map(static fn (Share $s): string => Format::hours($s->minutes), $day->dailyShares),
            'night' => Format::hours($day->nightMinutes),
            'under' => Format::hours($day->underMinutes),
            'catering' => $this->labels->t($day->catering === Catering::YES ? 'drehzettel.pdf.yes' : 'drehzettel.pdf.no', $locale),
            'day_type' => $this->labels->t('drehzettel.day_type.' . $day->dayType->value, $locale),
            'pay' => $showPay && $day->amountCents !== null ? Format::money($day->amountCents, $locale, $this->currency) : '',
            'extra_pay' => $showPay && $day->extraPayCents > 0 ? $this->labels->t('drehzettel.pdf.extra_pay', $locale, ['%amount%' => Format::money($day->extraPayCents, $locale, $this->currency)]) : '',
        ];
    }

    /**
     * @param list<DayResult> $days
     * @return array<string, mixed>
     */
    private function sums(WeekResult $week, array $days, Ruleset $rules, bool $ownsWeekly): array
    {
        $tiers = array_fill(0, count($rules->dailyTiers), 0);
        $work = $night = $under = $catering = $pay = 0;
        foreach ($days as $day) {
            $work += $day->workMinutes;
            $night += $day->nightMinutes;
            $under += $day->underMinutes;
            $catering += $day->catering === Catering::YES ? 1 : 0;
            $pay += $day->amountCents ?? 0;
            foreach ($day->dailyShares as $i => $share) {
                $tiers[$i] += $share->minutes;
            }
        }

        return [
            'work' => $work, 'tiers' => $tiers, 'night' => $night, 'under' => $under,
            'catering' => $catering, 'pay' => $pay + ($ownsWeekly ? $week->weeklyCents ?? 0 : 0),
        ];
    }

    /**
     * @param array<string, mixed> $sums
     * @return array<string, mixed>
     */
    private function formatSums(array $sums, string $locale, bool $showPay): array
    {
        return [
            'work' => Format::hours($sums['work']),
            'tiers' => array_map(static fn (int $m): string => Format::hours($m), $sums['tiers']),
            'night' => Format::hours($sums['night']),
            'under' => Format::hours($sums['under']),
            'catering' => $sums['catering'] . 'x',
            'pay' => $showPay ? Format::money($sums['pay'], $locale, $this->currency) : '',
        ];
    }

    /**
     * @param list<array{view: array<string, mixed>, sums: array<string, mixed>}> $blocks
     * @return array<string, mixed>
     */
    private function total(array $blocks, Ruleset $rules, PdfOptions $options, string $locale, bool $showPay): array
    {
        $total = ['work' => 0, 'tiers' => array_fill(0, count($rules->dailyTiers), 0), 'night' => 0, 'under' => 0, 'catering' => 0, 'pay' => 0];
        foreach ($blocks as $block) {
            $sums = $block['sums'];
            foreach (['work', 'night', 'under', 'catering', 'pay'] as $key) {
                $total[$key] += $sums[$key];
            }
            foreach ($sums['tiers'] as $i => $minutes) {
                $total['tiers'][$i] += $minutes;
            }
        }

        return $this->formatSums($total, $locale, $showPay);
    }

    private function weeklyLine(WeekResult $week, Ruleset $rules, string $locale): ?string
    {
        if ($rules->weeklyTiers === []) {
            return null;
        }

        $parts = [];
        foreach ($rules->weeklyTiers as $i => $tier) {
            $parts[] = $this->labels->t('drehzettel.pdf.weekly_part', $locale, [
                '%time%' => Format::hours($week->weeklyShares[$i]->minutes),
                '%percent%' => $this->percent($tier->basisPoints, $locale),
            ]);
        }

        return $this->labels->t('drehzettel.pdf.weekly_overtime', $locale, ['%parts%' => implode(', ', $parts)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Ruleset $rules, PdfOptions $options, bool $showPay): array
    {
        $tiers = [];
        foreach ($rules->dailyTiers as $tier) {
            $tiers[] = '+ ' . $this->percent($tier->basisPoints, 'en');
        }

        return [
            'break' => $options->has(PdfOption::BREAK),
            'tiers' => $options->has(PdfOption::TIERS) ? $tiers : [],
            'night' => $options->has(PdfOption::NIGHT),
            'under' => $options->has(PdfOption::UNDER),
            'catering' => $options->has(PdfOption::CATERING),
            'day_type' => $options->has(PdfOption::DAY_TYPE),
            'pay' => $showPay,
            'night_percent' => $this->percent($rules->nightBasisPoints, 'en'),
        ];
    }

    /**
     * @param list<WeekResult> $weeks
     * @return list<array{date: string, text: string}>
     */
    private function notes(array $weeks, Period $period, string $locale): array
    {
        $notes = [];
        foreach ($weeks as $week) {
            foreach ($week->days as $day) {
                if ($day->note === null || trim($day->note) === '' || !$period->contains($day->begin)) {
                    continue;
                }
                $notes[] = ['date' => $this->dateLabel($day->begin, $locale), 'text' => trim($day->note)];
            }
        }

        return $notes;
    }

    private function roundingNote(Ruleset $rules, string $locale): string
    {
        return $this->labels->t('drehzettel.pdf.rounding', $locale, [
            '%work%' => $this->roundingLabel($rules->workRounding->unit, $rules->workRounding->mode->value, $locale),
            '%surcharge%' => $this->roundingLabel($rules->surchargeRounding->unit, $rules->surchargeRounding->mode->value, $locale),
        ]);
    }

    private function roundingLabel(RoundingUnit $unit, string $mode, string $locale): string
    {
        if ($unit === RoundingUnit::MINUTE) {
            return $this->labels->t('drehzettel.rounding.exact', $locale);
        }

        return $this->labels->t('drehzettel.rounding.rule', $locale, [
            '%unit%' => $this->labels->t('drehzettel.rounding.unit.' . $unit->value, $locale),
            '%mode%' => $this->labels->t('drehzettel.rounding.mode.' . $mode, $locale),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function texts(string $locale, string $name, string $project, string $role): array
    {
        $keys = [
            'title', 'period', 'name', 'project', 'role', 'date', 'begin', 'end', 'break', 'work', 'night', 'under',
            'catering', 'day_type', 'pay', 'notes', 'production', 'crew', 'total', 'week',
        ];
        $texts = [];
        foreach ($keys as $key) {
            $texts[$key] = $this->labels->t('drehzettel.pdf.' . $key, $locale);
        }
        $texts['name_line'] = $this->labels->t('drehzettel.pdf.name_line', $locale, ['%value%' => $name]);
        $texts['project_line'] = $this->labels->t('drehzettel.pdf.project_line', $locale, ['%value%' => $project]);
        $texts['role_line'] = $this->labels->t('drehzettel.pdf.role_line', $locale, ['%value%' => $role]);

        return $texts;
    }

    // "Montag, 15. Juni 2026" / "Monday, 15 June 2026"
    public function dateLabel(\DateTimeImmutable $date, string $locale): string
    {
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $date->getTimezone(), \IntlDateFormatter::GREGORIAN, self::DATE_PATTERNS[$locale]);

        return (string) $formatter->format($date);
    }

    private function periodLabel(\DateTimeImmutable $from, \DateTimeImmutable $to, string $locale): string
    {
        $firstWeek = (int) $from->format('W');
        $lastWeek = (int) $to->format('W');
        $weeks = $firstWeek === $lastWeek ? "KW $firstWeek" : "KW $firstWeek-$lastWeek";
        if ($locale !== 'de') {
            $weeks = str_replace('KW', 'CW', $weeks);
        }

        return $this->dateLabel($from, $locale) . ' - ' . $this->dateLabel($to, $locale) . ' / ' . $weeks;
    }

    // 2500 -> "25%", 1250 -> "12,5%" (de) / "12.5%" (en)
    private function percent(int $basisPoints, string $locale): string
    {
        $value = $basisPoints / (Units::BASIS_POINTS / 100);
        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return ($locale === 'de' ? str_replace('.', ',', $text) : $text) . '%';
    }
}
