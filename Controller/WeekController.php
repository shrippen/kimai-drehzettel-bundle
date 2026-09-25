<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Form\MailType;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementAccess;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use KimaiPlugin\DrehzettelBundle\Service\PageSetups;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;
use KimaiPlugin\DrehzettelBundle\Service\PdfExporter;
use KimaiPlugin\DrehzettelBundle\Service\TimesheetMailer;
use KimaiPlugin\DrehzettelBundle\Service\WeekPageBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/drehzettel/{id}', requirements: ['id' => '\d+'])]
#[IsGranted('drehzettel')]
class WeekController extends AbstractController
{
    use KpuFormSuccessTrait;

    private const CSRF_ID = 'drehzettel_week';
    public const ACTIONS = 'drehzettel_week';
    private const WEEK_KEY = 'stats.workingTimeWeekShort';

    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly EngagementAccess $access,
        private readonly WeekPageBuilder $pageBuilder,
        private readonly FilmDayService $filmDays,
        private readonly PdfExporter $pdfExporter,
        private readonly TimesheetMailer $mailer,
        private readonly MailRecipientRepository $mailRecipients,
        private readonly PageSetups $pages,
    ) {
    }

    #[Route(path: '/week/{year}/{week}', name: 'drehzettel_week', defaults: ['year' => null, 'week' => null], requirements: ['year' => '\d+', 'week' => '\d+'], methods: ['GET'])]
    public function week(Request $request, int $id, ?int $year, ?int $week): Response
    {
        $engagement = $this->findEngagement($id);
        [$year, $week] = $this->currentWeek($year, $week);

        $view = $this->pageBuilder->build($engagement, $year, $week);
        $title = $this->pages->trans(self::WEEK_KEY, ['%week%' => $week]);

        return $this->render('@Drehzettel/drehzettel/week.html.twig', [
            'page_setup' => $this->pages->create(self::ACTIONS, $title, ['engagement' => $engagement, 'view' => $view]),
            'v' => $view,
        ]);
    }

    #[Route(path: '/week/{year}/{week}/save', name: 'drehzettel_week_save', requirements: ['year' => '\d+', 'week' => '\d+'], methods: ['POST'])]
    public function save(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $this->flashError('action.update.error');

            return $this->redirectToRoute('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]);
        }

        $period = Period::week($year, $week, $engagement->getUser()->getDateTimezone());
        $days = (array) $request->request->all('day');
        foreach ($days as $dateKey => $fields) {
            $date = $period->day((string) $dateKey);
            if ($date === null) {
                continue; // only accept dates of this week
            }
            $draft = $this->filmDays->draft($engagement, $date, (array) $fields);
            $this->filmDays->save($engagement, $date, $draft->breakMinutes, $draft->catering, $draft->category, $draft->type, $draft->productionDay, $draft->note, $draft->extraPayCents, $draft->shootingDayNumber);
        }

        $this->flashSuccess('action.update.success');

        return $this->redirectToRoute('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]);
    }

    // Live preview while typing: same calculation, no save. Returns the row/sum fragment.
    #[Route(path: '/week/{year}/{week}/preview', name: 'drehzettel_week_preview', requirements: ['year' => '\d+', 'week' => '\d+'], methods: ['POST'])]
    public function preview(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);

        $period = Period::week($year, $week, $engagement->getUser()->getDateTimezone());
        $drafts = [];
        foreach ((array) $request->request->all('day') as $dateKey => $fields) {
            $date = $period->day((string) $dateKey);
            if ($date === null) {
                continue; // only accept dates of this week
            }
            $drafts[(string) $dateKey] = $this->filmDays->draft($engagement, $date, (array) $fields);
        }

        $view = $this->pageBuilder->build($engagement, $year, $week, $drafts);

        return $this->render('@Drehzettel/drehzettel/_week_table.html.twig', ['v' => $view]);
    }

    #[Route(path: '/pdf/{year}/{week}', name: 'drehzettel_week_pdf', requirements: ['year' => '\d+', 'week' => '\d+'], methods: ['GET'])]
    public function pdf(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);
        $zone = $engagement->getUser()->getDateTimezone();
        $period = Period::week($year, $week, $zone);
        $options = $this->optionsFromQuery($request, $engagement->getPdfOptions());
        $document = $this->pdfExporter->export($engagement, $period, $options);

        return $this->pdfResponse($document->filename, $document->content);
    }

    #[Route(path: '/pdf/month/{year}/{month}', name: 'drehzettel_month_pdf', requirements: ['year' => '\d+', 'month' => '\d+'], methods: ['GET'])]
    public function monthPdf(Request $request, int $id, int $year, int $month): Response
    {
        $engagement = $this->findEngagement($id);
        $zone = $engagement->getUser()->getDateTimezone();
        $period = Period::month($year, $month, $zone);
        $options = $this->optionsFromQuery($request, $engagement->getPdfOptions());
        $document = $this->pdfExporter->export($engagement, $period, $options);

        return $this->pdfResponse($document->filename, $document->content);
    }

    // Kimai modal: recipient form (GET), send the week PDF (POST).
    #[Route(path: '/week/{year}/{week}/mail', name: 'drehzettel_week_mail', requirements: ['year' => '\d+', 'week' => '\d+'], methods: ['GET', 'POST'])]
    public function mail(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);
        $url = $this->generateUrl('drehzettel_week_mail', ['id' => $id, 'year' => $year, 'week' => $week]);
        $form = $this->createForm(MailType::class, [MailType::FIELD => $this->mailRecipients->findForEngagement($engagement)?->getEmail()], [
            'action' => $url,
            'attr' => ['data-form-event' => 'kpu.reload'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $address = (string) $form->get(MailType::FIELD)->getData();
            $period = Period::week($year, $week, $engagement->getUser()->getDateTimezone());
            $document = $this->pdfExporter->export($engagement, $period, $engagement->getPdfOptions());
            $subject = sprintf('%s — %s', $document->filename, $engagement->getProject()->getName());

            try {
                $this->mailer->send($address, $subject, $subject, $document);
                $this->mailRecipients->remember($engagement, $address);
                $this->addFlash('kpu_result', $this->pages->trans('drehzettel.mail.sent', ['%address%' => $address]));

                return $this->kpuFormSuccess($request, 'drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week], true);
            } catch (\Throwable $e) {
                // Transport errors can name hosts or accounts: log them, show only a generic error.
                $this->logException($e);
                $form->addError(new FormError($this->pages->trans('drehzettel.mail.failed')));
            }
        }

        return $this->render('@Drehzettel/drehzettel/mail.html.twig', [
            'page_setup' => $this->pages->create(self::ACTIONS . '_mail', $this->pages->trans(self::WEEK_KEY, ['%week%' => $week])),
            'form' => $form->createView(),
            'engagement' => $engagement,
            'back' => $this->generateUrl('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]),
        ]);
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
