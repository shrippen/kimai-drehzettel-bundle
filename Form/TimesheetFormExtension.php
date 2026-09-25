<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use App\Entity\Timesheet;
use App\Form\TimesheetAdminEditForm;
use App\Form\TimesheetEditForm;
use App\Form\Type\YesNoType;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayPatch;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\PendingFilmDays;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Adds the "film day" toggle and film-day fields to Kimai's own timesheet
 * entry form, conditionally: only for entries whose project + user + date
 * fall inside an active Drehzettel engagement
 * (research/ux-flows-film-day-data.md, decision 2026-09-23, form concept A).
 *
 * Kimai's native "break" field is removed from the form in that case.
 * The two break concepts stay fully independent data - FilmDay.breakMinutes
 * is never read from or written to Timesheet::break, this only avoids
 * showing two conflicting "Pause" inputs on the same form at once.
 *
 * Limitation: the decision is made once, at form-build time, from the
 * timesheet's project/user/begin as known when the form is built. A
 * brand-new entry without a project yet will not show the toggle even if
 * the user picks a project with an active engagement afterwards - a live
 * re-check via the plugin's API (research/api-external-clients.md) is a
 * follow-up, not part of this pass.
 */
final class TimesheetFormExtension extends AbstractTypeExtension
{
    public const FIELD_TOGGLE = 'drehzettelFilmDay';
    public const FIELD_BREAK = 'drehzettelBreak';
    public const FIELD_CATERING = 'drehzettelCatering';
    public const FIELD_CATEGORY = 'drehzettelCategory';
    public const FIELD_NOTE = 'drehzettelNote';

    private const MAX_BREAK_MINUTES = 720;
    private const MAX_NOTE_LENGTH = 500;

    public function __construct(
        private readonly EngagementService $engagements,
        private readonly FilmDayRepository $filmDays,
        private readonly PendingFilmDays $pending,
    ) {
    }

    /**
     * @return iterable<class-string>
     */
    public static function getExtendedTypes(): iterable
    {
        // TimesheetAdminEditForm extends TimesheetEditForm as a PHP class, but Symfony's
        // form registry treats it as its own type - both must be listed here, or the
        // fieldset is silently missing from the admin edit form.
        return [TimesheetEditForm::class, TimesheetAdminEditForm::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $timesheet = $options['data'] ?? null;
        if (!$timesheet instanceof Timesheet) {
            return;
        }

        $engagement = $this->engagements->activeFor($timesheet);
        if ($engagement === null) {
            return;
        }

        if ($builder->has('break')) {
            $builder->remove('break');
        }

        // The film day is shared by every entry of its date, so a new or duplicated entry
        // shows it too - otherwise saving would reset the day to blank fields.
        $existing = $this->filmDays->findOne($engagement, $this->dateOf($timesheet));

        // row_attr classes group these five rows visually (amber box, see the sitewide CSS
        // added by EventSubscriber\ThemeSubscriber) - matches form concept A from the workflow
        // artifact (https://claude.ai/artifact/CB2kY9aB66GbHzLTVnTZjS), chosen 2026-09-23.
        $builder->add(self::FIELD_TOGGLE, YesNoType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.form.film_day',
            'data' => true,
            'row_attr' => ['class' => 'dz-form-row dz-form-row-first'],
        ]);

        $builder->add(self::FIELD_BREAK, IntegerType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.pdf.break',
            'data' => $existing?->getBreakMinutes(),
            'attr' => ['min' => 0, 'max' => self::MAX_BREAK_MINUTES],
            'constraints' => [new Range(min: 0, max: self::MAX_BREAK_MINUTES)],
            'row_attr' => ['class' => 'dz-form-row'],
        ]);

        $builder->add(self::FIELD_CATERING, YesNoType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.catering.title',
            'data' => ($existing?->getCatering() ?? Catering::NO) === Catering::YES,
            'row_attr' => ['class' => 'dz-form-row'],
        ]);

        $builder->add(self::FIELD_CATEGORY, ChoiceType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.category.title',
            'placeholder' => 'drehzettel.category.auto',
            'choices' => [
                'drehzettel.category.workday' => DayCategory::WORKDAY->value,
                'drehzettel.category.saturday' => DayCategory::SATURDAY->value,
                'drehzettel.category.sunday' => DayCategory::SUNDAY->value,
                'drehzettel.category.holiday' => DayCategory::HOLIDAY->value,
            ],
            'choice_translation_domain' => true,
            'data' => $existing?->getCategory()?->value,
            'row_attr' => ['class' => 'dz-form-row'],
        ]);

        $builder->add(self::FIELD_NOTE, TextareaType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.note',
            'data' => $existing?->getNote(),
            'row_attr' => ['class' => 'dz-form-row dz-form-row-last'],
        ]);

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event): void {
                $this->onSubmit($event);
            }
        );
    }

    // Only queues the fields: TimesheetSaveSubscriber writes them once Kimai has saved
    // the entry, against the engagement of its final user/project/date.
    private function onSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        if (!$form->isValid()) {
            return;
        }

        if (!(bool) $form->get(self::FIELD_TOGGLE)->getData()) {
            // Manually turned off: leave any previously saved FilmDay row untouched,
            // rather than deleting data the user might just be hiding for this edit.
            return;
        }

        $breakMinutes = $form->get(self::FIELD_BREAK)->getData();
        $note = $form->get(self::FIELD_NOTE)->getData();
        $note = ($note !== null && trim((string) $note) !== '') ? mb_substr(trim((string) $note), 0, self::MAX_NOTE_LENGTH) : null;

        // Day type and production day are not on this form: they keep their stored value.
        try {
            $patch = FilmDayPatch::fromArray([
                'breakMinutes' => $breakMinutes !== null ? (int) $breakMinutes : null,
                'catering' => (bool) $form->get(self::FIELD_CATERING)->getData(),
                'category' => $form->get(self::FIELD_CATEGORY)->getData(),
                'note' => $note,
            ]);
        } catch (\InvalidArgumentException) {
            return; // out of range: the break field's own constraint reports it
        }

        /** @var Timesheet $timesheet */
        $timesheet = $event->getData();
        $this->pending->put($timesheet, $patch);
    }

    private function dateOf(Timesheet $timesheet): \DateTimeImmutable
    {
        return EngagementService::dateOf($timesheet);
    }
}
