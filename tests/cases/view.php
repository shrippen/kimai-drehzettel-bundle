<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\Format;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Domain\TimesheetMeta;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\PdfOption;
use KimaiPlugin\DrehzettelBundle\Service\TimesheetViewBuilder;

$zone = new DateTimeZone('Europe/Berlin');
$rules = Rulesets::tvFfs2024();
$builder = new TimesheetViewBuilder(stubLabels());
$meta = new TimesheetMeta('Muster, Erika', 'Musterfilm', 'Oberbeleuchterin', 'de', false);

// The made-up week of the landing page: four days, TV FFS rounding.
$days = [
    shift('2026-03-02', '08:00', '19:15', 45, Catering::YES),
    shift('2026-03-03', '09:00', '21:45', 45, Catering::YES),
    shift('2026-03-04', '13:30', '22:45', 45, Catering::YES),
    shift('2026-03-05', '12:00', '23:30', 45, Catering::YES),
];
$week = weekCalc()->calc($days, $rules, null);
$period = Period::week(2026, 10, $zone);

$v = $builder->build($meta, $period, [$week], $rules, PdfOptions::defaults());
$rows = $v['weeks'][0]['rows'];
check('view: four rows', 4, count($rows));
check('view: date label de', 'Montag, 2. März 2026', $rows[0]['date']);
check('view: begin/end/break', ['08:00', '19:15', '00:45'], [$rows[0]['begin'], $rows[0]['end'], $rows[0]['break']]);
check('view: day 1 work and tiers', ['10:30 h', ['01:00 h', '00:00 h']], [$rows[0]['work'], $rows[0]['tiers']]);
check('view: day 2 tiers', ['12:00 h', ['01:00 h', '01:00 h']], [$rows[1]['work'], $rows[1]['tiers']]);
check('view: night hour up', ['01:00 h', '02:00 h'], [$rows[2]['night'], $rows[3]['night']]);
$sums = $v['weeks'][0]['sums'];
check('view: week sums', ['41:45 h', ['03:00 h', '01:00 h'], '03:00 h', '4x'], [$sums['work'], $sums['tiers'], $sums['night'], $sums['catering']]);
check('view: period label', 'Montag, 2. März 2026 - Donnerstag, 5. März 2026 / KW 10', $v['period']);
check('view: single week has no total', null, $v['total']);
check('view: no pay column by default', false, $v['columns']['pay']);
check('view: tier headers', ['+ 25%', '+ 50%'], $v['columns']['tiers']);
check('view: weekly line', 'drehzettel.pdf.weekly_overtime(%parts%=drehzettel.pdf.weekly_part(%time%=00:00 h,%percent%=25%), drehzettel.pdf.weekly_part(%time%=00:00 h,%percent%=50%))', $v['weeks'][0]['weekly']);
check('view: rounding note present', true, $v['rounding'] !== null);

// English dates and no german weekday in en.
$en = $builder->build(new TimesheetMeta('X', 'Y', 'Z', 'en', false), $period, [$week], $rules, PdfOptions::defaults());
check('view: date label en', 'Monday, 2 March 2026', $en['weeks'][0]['rows'][0]['date']);
check('view: period label en', 'Monday, 2 March 2026 - Thursday, 5 March 2026 / CW 10', $en['period']);

// All weekdays lists the empty ones, in period order.
$all = $builder->build($meta, $period, [$week], $rules, new PdfOptions([PdfOption::ALL_WEEKDAYS]));
$allRows = $all['weeks'][0]['rows'];
check('view: all weekdays rows', 7, count($allRows));
check('view: empty flags', [false, false, false, false, true, true, true], array_column($allRows, 'empty'));
check('view: empty weekday label', 'Freitag, 6. März 2026', $allRows[4]['date']);

// A period that covers only part of the week lists only those days.
$partial = $builder->build($meta, Period::range(new DateTimeImmutable('2026-03-04'), new DateTimeImmutable('2026-03-05')), [$week], $rules, PdfOptions::defaults());
check('view: partial rows', 2, count($partial['weeks'][0]['rows']));
check('view: partial sums', '19:15 h', $partial['weeks'][0]['sums']['work']);

// Notes and pay.
$noted = [
    new KimaiPlugin\DrehzettelBundle\Domain\DayInput(at('2026-03-02', '08:00'), at('2026-03-02', '16:00'), note: 'Nachtdreh geplant'),
];
$noteWeek = weekCalc()->calc($noted, $rules, new KimaiPlugin\DrehzettelBundle\Domain\PayTerms(KimaiPlugin\DrehzettelBundle\Enum\PayKind::WEEKLY, 158100));
$withPay = $builder->build(new TimesheetMeta('X', 'Y', 'Z', 'de', true), $period, [$noteWeek], $rules, new PdfOptions([PdfOption::PAY, PdfOption::NOTES]));
check('view: note line', [['date' => 'Montag, 2. März 2026', 'text' => 'Nachtdreh geplant']], $withPay['notes']);
check('view: pay shown', true, $withPay['weeks'][0]['rows'][0]['pay'] !== '');
$noPay = $builder->build(new TimesheetMeta('X', 'Y', 'Z', 'de', true), $period, [$noteWeek], $rules, new PdfOptions([]));
check('view: pay hidden without option', ['', ''], [$noPay['weeks'][0]['rows'][0]['pay'], $noPay['weeks'][0]['sums']['pay']]);

// Under-time: 7:15 h day against the 8 h minimum.
$short = weekCalc()->calc([shift('2026-03-02', '08:00', '16:00', 45)], $rules, null);
check('view: under-time', '00:45 h', $builder->build($meta, $period, [$short], $rules, new PdfOptions([PdfOption::UNDER]))['weeks'][0]['rows'][0]['under']);

// Month of several weeks gets a total row.
$w2 = weekCalc()->calc([shift('2026-03-10', '08:00', '18:45', 45)], $rules, null);
$month = $builder->build($meta, Period::month(2026, 3, $zone), [$week, $w2], $rules, PdfOptions::defaults());
check('view: month total', ['51:45 h', 2], [$month['total']['work'], count($month['weeks'])]);
check('view: month label spans weeks', 'Montag, 2. März 2026 - Dienstag, 10. März 2026 / KW 10-11', $month['period']);

// A week across a month boundary: its weekly overtime is paid in one month only,
// the one holding the week's last worked day (Mon 30.3. - Sat 4.4.2026 -> April).
$split = [];
foreach (['2026-03-30', '2026-03-31', '2026-04-01', '2026-04-02', '2026-04-03'] as $d) {
    $split[] = shift($d, '08:00', '18:45', 45);
}
$split[] = shift('2026-04-04', '08:00', '16:45', 45, category: KimaiPlugin\DrehzettelBundle\Enum\DayCategory::SATURDAY);
$splitWeek = weekCalc()->calc($split, $rules, new KimaiPlugin\DrehzettelBundle\Domain\PayTerms(KimaiPlugin\DrehzettelBundle\Enum\PayKind::WEEKLY, 158100));
$payMeta = new TimesheetMeta('X', 'Y', 'Z', 'de', true);
$payOptions = new PdfOptions([PdfOption::PAY, PdfOption::WEEKLY_OVERTIME]);
$march = $builder->build($payMeta, Period::month(2026, 3, $zone), [$splitWeek], $rules, $payOptions);
$april = $builder->build($payMeta, Period::month(2026, 4, $zone), [$splitWeek], $rules, $payOptions);
$dayCents = array_map(static fn ($d): int => $d->amountCents, $splitWeek->days);
check('view: split week has weekly pay', true, $splitWeek->weeklyCents > 0);
check('view: split week march without weekly', [null, Format::money($dayCents[0] + $dayCents[1], 'de')], [$march['weeks'][0]['weekly'], $march['weeks'][0]['sums']['pay']]);
check('view: split week april with weekly', [true, Format::money(array_sum(array_slice($dayCents, 2)) + $splitWeek->weeklyCents, 'de')], [$april['weeks'][0]['weekly'] !== null, $april['weeks'][0]['sums']['pay']]);

// Helpers.
check('options round trip', ['break', 'pay'], PdfOptions::fromKeys(['break', 'pay', 'bogus'])->toKeys());
check('options default has signature', true, PdfOptions::defaults()->has(PdfOption::SIGNATURE_LINES));
check('options default hides pay', false, PdfOptions::defaults()->has(PdfOption::PAY));
check('money de', '1.234,50 €', Format::money(123450, 'de'));
check('money en', '€1,234.50', Format::money(123450, 'en'));
check('hours', '31:30 h', Format::hours(1890));
check('money currency', '1.234,50 CHF', Format::money(123450, 'de', 'CHF'));
check('money negative en', '-€12.00', Format::money(-1200, 'en'));

// The PDF uses the customer currency of the project.
$chf = $builder->build(new TimesheetMeta('X', 'Y', 'Z', 'de', true, currency: 'CHF'), $period, [$noteWeek], $rules, new PdfOptions([PdfOption::PAY]));
check('view: pay in customer currency', true, str_ends_with($chf['weeks'][0]['sums']['pay'], ' CHF'));
