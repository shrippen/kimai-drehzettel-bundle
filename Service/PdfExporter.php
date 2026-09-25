<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Pdf\HtmlToPdfConverter;
use KimaiPlugin\DrehzettelBundle\Domain\Format;
use KimaiPlugin\DrehzettelBundle\Domain\PdfDocument;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Domain\TimesheetMeta;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\PdfOption;
use Twig\Environment;

/**
 * Timesheet PDF of one engagement for a week, a month or any range.
 */
class PdfExporter
{
    private const TEMPLATE = '@Drehzettel/pdf/timesheet.html.twig';
    private const FILE_DATE = 'Ymd';

    // A4 landscape, margins in mm.
    private const PDF_OPTIONS = [
        'format' => 'A4',
        'orientation' => 'L',
        'margin_left' => 14,
        'margin_right' => 14,
        'margin_top' => 12,
        'margin_bottom' => 12,
        'default_font_size' => 9,
    ];

    public function __construct(
        private readonly FilmWeekService $weeks,
        private readonly EngagementService $engagements,
        private readonly TimesheetViewBuilder $views,
        private readonly SignatureService $signatures,
        private readonly Environment $twig,
        private readonly HtmlToPdfConverter $converter,
    ) {
    }

    public function export(Engagement $engagement, Period $period, PdfOptions $options, ?string $locale = null): PdfDocument
    {
        $view = $this->view($engagement, $period, $options, $locale);
        $html = $this->twig->render(self::TEMPLATE, ['v' => $view]);

        return new PdfDocument($this->filename($engagement, $view['from'], $view['to']), $this->converter->convertToPdf($html, self::PDF_OPTIONS));
    }

    /**
     * The data behind the PDF, also used by the tests.
     *
     * @return array<string, mixed>
     */
    public function view(Engagement $engagement, Period $period, PdfOptions $options, ?string $locale = null): array
    {
        $user = $engagement->getUser();
        $rules = $this->engagements->ruleset($engagement);
        $weeks = $this->weeks->period($engagement, $period->from, $period->endExclusive());

        $signature = $options->has(PdfOption::SIGNATURE_IMAGE) ? $this->signatures->dataUri($user) : null;
        $meta = new TimesheetMeta(
            displayName: $user->getDisplayName(),
            projectName: $engagement->getProject()->getName(),
            role: $engagement->getRole(),
            locale: $locale ?? $user->getLanguage(),
            hasPay: $engagement->getGageCents() > 0,
            signatureDataUri: $signature,
            currency: $engagement->getProject()?->getCustomer()?->getCurrency() ?? Format::CURRENCY,
        );

        return $this->views->build($meta, $period, $weeks, $rules, $options);
    }

    // Timesheet_Surname_Project_20260615-20260618.pdf
    private function filename(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $surname = $this->surname($engagement->getUser()->getDisplayName());

        return sprintf(
            'Timesheet_%s_%s_%s-%s.pdf',
            $this->slug($surname),
            $this->slug($engagement->getProject()->getName()),
            $from->format(self::FILE_DATE),
            $to->format(self::FILE_DATE),
        );
    }

    // "Muster, Erika" -> "Muster"; "Erika Muster" -> "Muster"
    private function surname(string $displayName): string
    {
        if (str_contains($displayName, ',')) {
            return trim(explode(',', $displayName)[0]);
        }
        $parts = preg_split('/\s+/', trim($displayName)) ?: [];

        return (string) end($parts);
    }

    private function slug(string $text): string
    {
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
        $slug = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-');

        return $slug === '' ? 'x' : $slug;
    }
}
