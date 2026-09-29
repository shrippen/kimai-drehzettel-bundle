<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\MailTemplate;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\MailRecipient;
use KimaiPlugin\DrehzettelBundle\Enum\PeriodKind;

/**
 * Subject and text of the timesheet mail: the engagement's template (or the
 * translated default), filled for one period.
 *
 *   "Stundenzettel {period} – {project}" -> "Stundenzettel KW 39/2026 – Graufeld"
 */
class MailComposer
{
    private const MONTH_PATTERN = 'MMMM y';

    public function __construct(private readonly Labels $labels)
    {
    }

    // Stored template; an empty part falls back to the default text.
    public function template(Engagement $engagement, ?MailRecipient $settings): MailTemplate
    {
        $default = $this->defaultTemplate($engagement);
        $stored = $settings?->getTemplate();
        if ($stored === null) {
            return $default;
        }

        return new MailTemplate(
            trim($stored->subject) !== '' ? $stored->subject : $default->subject,
            trim($stored->body) !== '' ? $stored->body : $default->body,
        );
    }

    public function defaultTemplate(Engagement $engagement): MailTemplate
    {
        $locale = $engagement->getUser()->getLanguage();

        return new MailTemplate(
            $this->labels->t('drehzettel.mail.default_subject', $locale),
            $this->labels->t('drehzettel.mail.default_body', $locale),
        );
    }

    public function compose(Engagement $engagement, Period $period, ?MailRecipient $settings): MailTemplate
    {
        return $this->template($engagement, $settings)->render($this->values($engagement, $period));
    }

    // Fills placeholders typed straight into the mail dialog, too.
    public function fill(MailTemplate $mail, Engagement $engagement, Period $period): MailTemplate
    {
        return $mail->render($this->values($engagement, $period));
    }

    /**
     * @return array<string, string>
     */
    public function values(Engagement $engagement, Period $period): array
    {
        $user = $engagement->getUser();
        $locale = $user->getLanguage();
        $zone = $period->from->getTimezone();
        $date = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, $zone);
        $month = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $zone, \IntlDateFormatter::GREGORIAN, self::MONTH_PATTERN);

        $week = (string) (int) $period->from->format('W');
        $weekYear = $period->from->format('o');
        $monthLabel = (string) $month->format($period->from);
        $label = $period->kind === PeriodKind::MONTH
            ? $monthLabel
            : $this->labels->t('drehzettel.mail.period_week', $locale, ['%week%' => $week, '%year%' => $weekYear]);

        return [
            'name' => (string) $user->getDisplayName(),
            'project' => (string) $engagement->getProject()?->getName(),
            'customer' => (string) $engagement->getProject()?->getCustomer()?->getName(),
            'role' => $engagement->getRole(),
            'period' => $label,
            'from' => (string) $date->format($period->from),
            'to' => (string) $date->format($period->to),
            'week' => $week,
            'month' => $monthLabel,
            'year' => $period->kind === PeriodKind::MONTH ? $period->from->format('Y') : $weekYear,
        ];
    }
}
