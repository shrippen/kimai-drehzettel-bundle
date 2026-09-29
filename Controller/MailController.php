<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\DrehzettelBundle\Domain\MailSchedule;
use KimaiPlugin\DrehzettelBundle\Domain\MailTemplate;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\MailRecipient;
use KimaiPlugin\DrehzettelBundle\Enum\MailRhythm;
use KimaiPlugin\DrehzettelBundle\Form\MailSettingsType;
use KimaiPlugin\DrehzettelBundle\Form\MailType;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Repository\MailRecipientRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementAccess;
use KimaiPlugin\DrehzettelBundle\Service\MailComposer;
use KimaiPlugin\DrehzettelBundle\Service\PageSetups;
use KimaiPlugin\DrehzettelBundle\Service\PdfExporter;
use KimaiPlugin\DrehzettelBundle\Service\TimesheetMailer;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Timesheet mail of an engagement:
 *   week/{year}/{week}/mail  dialog: recipient, subject, text (from the template, editable), send
 *   mail/settings            template and automatic sending (MailSchedule)
 */
#[Route(path: '/drehzettel/{id}', requirements: ['id' => '\d+'])]
#[IsGranted('drehzettel')]
class MailController extends AbstractController
{
    use KpuFormSuccessTrait;

    private const WEEK_KEY = 'stats.workingTimeWeekShort';

    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly EngagementAccess $access,
        private readonly PdfExporter $pdfExporter,
        private readonly TimesheetMailer $mailer,
        private readonly MailComposer $composer,
        private readonly MailRecipientRepository $mailRecipients,
        private readonly PageSetups $pages,
    ) {
    }

    // Kimai modal: mail form (GET), send the week PDF (POST).
    #[Route(path: '/week/{year}/{week}/mail', name: 'drehzettel_week_mail', requirements: ['year' => '\d+', 'week' => '\d+'], methods: ['GET', 'POST'])]
    public function mail(Request $request, int $id, int $year, int $week): Response
    {
        $engagement = $this->findEngagement($id);
        $settings = $this->mailRecipients->findForEngagement($engagement);
        $period = Period::week($year, $week, $engagement->getUser()->getDateTimezone());
        $draft = $this->composer->compose($engagement, $period, $settings);

        $form = $this->createForm(MailType::class, [
            MailType::FIELD => $settings?->getEmail(),
            MailType::FIELD_SUBJECT => $draft->subject,
            MailType::FIELD_BODY => $draft->body,
        ], [
            'action' => $this->generateUrl('drehzettel_week_mail', ['id' => $id, 'year' => $year, 'week' => $week]),
            'attr' => ['data-form-event' => 'kpu.reload'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $address = (string) $form->get(MailType::FIELD)->getData();
            $typed = new MailTemplate((string) $form->get(MailType::FIELD_SUBJECT)->getData(), (string) $form->get(MailType::FIELD_BODY)->getData());
            $mail = $this->composer->fill($typed, $engagement, $period);

            try {
                $document = $this->pdfExporter->export($engagement, $period, $engagement->getPdfOptions());
                $this->mailer->send($engagement->getUser(), $address, $mail, $document);
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
            'page_setup' => $this->pages->create(WeekController::ACTIONS . '_mail', $this->pages->trans(self::WEEK_KEY, ['%week%' => $week])),
            'form' => $form->createView(),
            'engagement' => $engagement,
            'settings_url' => $this->generateUrl('drehzettel_mail_settings', ['id' => $id]),
            'back' => $this->generateUrl('drehzettel_week', ['id' => $id, 'year' => $year, 'week' => $week]),
        ]);
    }

    // Kimai modal: template and automatic sending.
    #[Route(path: '/mail/settings', name: 'drehzettel_mail_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, int $id): Response
    {
        $engagement = $this->findEngagement($id);
        $settings = $this->mailRecipients->settingsFor($engagement);
        $default = $this->composer->defaultTemplate($engagement);
        $schedule = $settings->getSchedule();
        $template = $settings->getTemplate();

        $form = $this->createForm(MailSettingsType::class, [
            MailSettingsType::FIELD_TO => $settings->getEmail(),
            MailSettingsType::FIELD_SUBJECT => $template?->subject,
            MailSettingsType::FIELD_BODY => $template?->body,
            MailSettingsType::FIELD_RHYTHM => $schedule->rhythm->value,
            MailSettingsType::FIELD_WEEKDAY => $schedule->weekday,
            MailSettingsType::FIELD_HOUR => $schedule->hour,
        ], [
            'action' => $this->generateUrl('drehzettel_mail_settings', ['id' => $id]),
            'attr' => ['data-form-event' => 'kpu.reload'],
            'default_subject' => $default->subject,
            'default_body' => $default->body,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            if ($this->apply($settings, $data)) {
                $this->mailRecipients->save($settings);
                $this->addFlash('kpu_result', $this->pages->trans('drehzettel.mail.settings_saved'));

                return $this->kpuFormSuccess($request, 'drehzettel_week', ['id' => $id], true);
            }
            $form->get(MailSettingsType::FIELD_TO)->addError(new FormError($this->pages->trans('drehzettel.mail.to_required')));
        }

        $now = new \DateTimeImmutable('now', $engagement->getUser()->getDateTimezone());
        $next = $settings->getSchedule()->nextSlot($now);

        return $this->render('@Drehzettel/drehzettel/mail_settings.html.twig', [
            'page_setup' => $this->pages->create(WeekController::ACTIONS . '_mail', $this->pages->trans('drehzettel.mail.settings')),
            'form' => $form->createView(),
            'engagement' => $engagement,
            'next_slot' => $next,
            'next_period' => $next !== null ? $this->composer->values($engagement, $settings->getSchedule()->period($next))['period'] : null,
            'back' => $this->generateUrl('drehzettel_week', ['id' => $id]),
        ]);
    }

    /**
     * Form data -> settings. False when automatic sending lacks a recipient.
     *
     * @param array<string, mixed> $data
     */
    private function apply(MailRecipient $settings, array $data): bool
    {
        $address = trim((string) ($data[MailSettingsType::FIELD_TO] ?? ''));
        $schedule = new MailSchedule(
            MailRhythm::tryFrom((string) $data[MailSettingsType::FIELD_RHYTHM]) ?? MailRhythm::OFF,
            (int) $data[MailSettingsType::FIELD_WEEKDAY],
            (int) $data[MailSettingsType::FIELD_HOUR],
        );
        if ($schedule->rhythm !== MailRhythm::OFF && $address === '') {
            return false;
        }

        $subject = trim((string) ($data[MailSettingsType::FIELD_SUBJECT] ?? ''));
        $body = trim((string) ($data[MailSettingsType::FIELD_BODY] ?? ''));
        $settings->setEmail($address);
        $settings->setTemplate($subject === '' && $body === '' ? null : new MailTemplate($subject, $body));

        // A new schedule starts with the next send time, not with one already past.
        if ($schedule != $settings->getSchedule()) {
            $now = new \DateTimeImmutable('now', $settings->getEngagement()->getUser()->getDateTimezone());
            $settings->setLastSlot($schedule->lastSlot($now));
        }
        $settings->setSchedule($schedule);

        return true;
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
}
