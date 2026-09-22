<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use App\Utils\PageSetup;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraftReader;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementAccess;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;
use KimaiPlugin\DrehzettelBundle\Service\PdfExporter;
use KimaiPlugin\DrehzettelBundle\Service\TimesheetMailer;
use KimaiPlugin\DrehzettelBundle\Service\WeekPageBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/drehzettel/{id}', requirements: ['id' => '\d+'])]
#[IsGranted('drehzettel')]
class WeekController extends AbstractController
{
    private const CSRF_ID = 'drehzettel_week';

    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly EngagementAccess $access,
        private readonly WeekPageBuilder $pageBuilder,
        private readonly FilmDayService $filmDays,
        private readonly PdfExporter $pdfExporter,
        private readonly TimesheetMailer $mailer,
        private readonly MailRecipientRepository $mailRecipients,
    ) {
    }

    #[Route(path: '/week/{year}/{week}', name: 'drehzettel_week', defaults: ['year' => null, 'week' => null], requirements: ['week' => '\d+'], methods: ['GET'])]
    public function week(Request $request, int $id, ?int $year, ?int $week): Response
    {
        $engagement = $this->findEngagement($id);
        [$year, $week] = $this->currentWeek($year, $week);

        $view = $this->pageBuilder->build($engagement, $year, $week);

        return $this->render('@Drehzettel/drehzettel/week.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'v' => $view,
        ]);
    }

    #[Route(path: '/week/{year}/{week}/save', name: 'drehzettel_week_save', requirements: ['week' => '\d+'], methods: ['POST'])]
    public function save(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $this->flashError('action.update.error');

            return $this->redirectToRoute('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]);
        }

        $monday = Period::week($year, $week, $engagement->getUser()->getDateTimezone())->from;
        $days = (array) $request->request->all('day');
        foreach ($days as $dateKey => $fields) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $dateKey);
            if ($date === false || $date < $monday || $date > $monday->modify('+6 days')) {
                continue; // only accept dates of this week
            }
            $draft = FilmDayDraftReader::read((array) $fields);
            $this->filmDays->save($engagement, $date, $draft->breakMinutes, $draft->catering, $draft->category, $draft->type, $draft->productionDay, $draft->note);
        }

        $this->flashSuccess('action.update.success');

        return $this->redirectToRoute('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]);
    }

    // Live preview while typing: same calculation, no save. Returns the row/sum fragment.
    #[Route(path: '/week/{year}/{week}/preview', name: 'drehzettel_week_preview', requirements: ['week' => '\d+'], methods: ['POST'])]
    public function preview(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);

        $drafts = [];
        foreach ((array) $request->request->all('day') as $dateKey => $fields) {
            $drafts[(string) $dateKey] = FilmDayDraftReader::read((array) $fields);
        }

        $view = $this->pageBuilder->build($engagement, $year, $week, $drafts);

        return $this->render('@Drehzettel/drehzettel/_week_table.html.twig', ['v' => $view]);
    }

    #[Route(path: '/pdf/{year}/{week}', name: 'drehzettel_week_pdf', requirements: ['week' => '\d+'], methods: ['GET'])]
    public function pdf(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);
        $zone = $engagement->getUser()->getDateTimezone();
        $period = Period::week($year, $week, $zone);
        $options = $this->optionsFromQuery($request, $engagement->getPdfOptions());
        $document = $this->pdfExporter->export($engagement, $period, $options);

        return $this->pdfResponse($document->filename, $document->content);
    }

    #[Route(path: '/pdf/month/{year}/{month}', name: 'drehzettel_month_pdf', requirements: ['month' => '\d+'], methods: ['GET'])]
    public function monthPdf(Request $request, int $id, int $year, int $month): Response
    {
        $engagement = $this->findEngagement($id);
        $zone = $engagement->getUser()->getDateTimezone();
        $period = Period::month($year, $month, $zone);
        $options = $this->optionsFromQuery($request, $engagement->getPdfOptions());
        $document = $this->pdfExporter->export($engagement, $period, $options);

        return $this->pdfResponse($document->filename, $document->content);
    }

    #[Route(path: '/week/{year}/{week}/mail', name: 'drehzettel_week_mail', requirements: ['week' => '\d+'], methods: ['POST'])]
    public function mail(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $this->flashError('action.update.error');

            return $this->redirectToRoute('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]);
        }

        $address = trim((string) $request->request->get('mail_to'));
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            $this->flashError('action.update.error');

            return $this->redirectToRoute('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]);
        }

        $period = Period::week($year, $week, $engagement->getUser()->getDateTimezone());
        $document = $this->pdfExporter->export($engagement, $period, $engagement->getPdfOptions());
        $subject = sprintf('%s — %s', $document->filename, $engagement->getProject()->getName());

        try {
            $this->mailer->send($address, $subject, $subject, $document);
            $this->mailRecipients->remember($engagement, $address);
            $this->flashSuccess('action.update.success');
        } catch (\Throwable $e) {
            $this->flashError('action.update.error', $e->getMessage());
        }

        return $this->redirectToRoute('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]);
    }

    private function findEngagement(int $id): Engagement
    {
        $engagement = $this->engagements->find($id);
        if ($engagement === null) {
            throw $this->createNotFoundException('Unknown engagement.');
        }
        $this->access->assertView($engagement);

        return $engagement;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function currentWeek(?int $year, ?int $week): array
    {
        if ($year !== null && $week !== null) {
            return [$year, $week];
        }
        $today = new \DateTimeImmutable('today');

        return [(int) $today->format('o'), (int) $today->format('W')];
    }

    private function optionsFromQuery(Request $request, PdfOptions $default): PdfOptions
    {
        $with = array_filter(explode(',', (string) $request->query->get('with', '')));
        $without = array_filter(explode(',', (string) $request->query->get('without', '')));
        if ($with === [] && $without === []) {
            return $default;
        }
        $keys = array_diff(array_unique(array_merge($default->toKeys(), $with)), $without);

        return PdfOptions::fromKeys(array_values($keys));
    }

    private function pdfResponse(string $filename, string $content): Response
    {
        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }
}
