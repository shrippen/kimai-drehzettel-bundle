<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use App\Entity\Timesheet;
use App\Form\TimesheetAdminEditForm;
use App\Form\TimesheetEditForm;
use App\Form\Type\YesNoType;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Adds the "film day" toggle and film-day fields to Kimai's own timesheet
 * entry form, for entries whose project + activity + user + date fall
 * inside an active Drehzettel engagement
 * (research/ux-flows-film-day-data.md, decision 2026-09-23, form concept A).
 *
 * Kimai's native "break" field is removed from the form in that case.
 * The two break concepts stay fully independent data - FilmDay.breakMinutes
 * is never read from or written to Timesheet::break, this only avoids
 * showing two conflicting "Pause" inputs on the same form at once.
 *
 * A brand-new entry has no project (and no engagement) yet at form-build
 * time, so the five fields are still added - hidden (`dz-hidden`, see
 * EventSubscriber\ThemeSubscriber's stylesheet) - and EventSubscriber\
 * ThemeSubscriber's JS reveals them live once the project/activity picked
 * in the browser resolves to an active engagement (same API this class's
 * counterpart, Controller\Api\DrehzettelApiController, serves external
 * clients from). An *existing* entry with no active engagement at build
 * time still gets nothing added: its project/user/date/activity are
 * already fixed, so nothing can change live for it - see the isEdit guard
 * below. Either way, onSubmit() re-resolves the engagement fresh from the
 * actually submitted data rather than trusting what buildForm() saw, so a
 * brand-new entry's project chosen only in the browser still saves
 * correctly even without the JS reveal (e.g. JS disabled).
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
        private readonly FilmDayService $filmDayService,
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

        $isEdit = $timesheet->getId() !== null;
        $originalEngagement = $this->engagements->activeFor($timesheet);

        // An existing entry's project/user/date/activity are already fixed - if nothing
        // applies at build time, nothing can change for it live either, unlike a
        // brand-new entry whose project/activity are commonly still unset at this point.
        if ($originalEngagement === null && $isEdit) {
            return;
        }

        if ($builder->has('break')) {
            $builder->remove('break');
        }

        $originalDate = $this->dateOf($timesheet);
        $existing = $originalEngagement !== null ? $this->filmDays->findOne($originalEngagement, $originalDate) : null;
        $hidden = $originalEngagement === null;

        // row_attr classes group these five rows visually (amber box, see the sitewide CSS
        // added by EventSubscriber\ThemeSubscriber) - matches form concept A from the workflow
        // artifact (https://claude.ai/artifact/CB2kY9aB66GbHzLTVnTZjS), chosen 2026-09-23.
        // dz-hidden starts a brand-new entry's fields collapsed until ThemeSubscriber's JS
        // confirms a live-picked project/activity resolves to an active engagement.
        $rowClass = static fn (string $extra = ''): string => trim('dz-form-row ' . $extra . ($hidden ? ' dz-hidden' : ''));

        $builder->add(self::FIELD_TOGGLE, YesNoType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.form.film_day',
            'data' => $originalEngagement !== null,
            'row_attr' => ['class' => $rowClass('dz-form-row-first')],
        ]);

        $builder->add(self::FIELD_BREAK, IntegerType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.pdf.break',
            'data' => $existing?->getBreakMinutes(),
            'attr' => ['min' => 0, 'max' => self::MAX_BREAK_MINUTES],
            'row_attr' => ['class' => $rowClass()],
        ]);

        $builder->add(self::FIELD_CATERING, YesNoType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.catering.title',
            'data' => ($existing?->getCatering() ?? Catering::NO) === Catering::YES,
            'row_attr' => ['class' => $rowClass()],
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
            'row_attr' => ['class' => $rowClass()],
        ]);

        $builder->add(self::FIELD_NOTE, TextareaType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'drehzettel.note',
            'data' => $existing?->getNote(),
            'row_attr' => ['class' => $rowClass('dz-form-row-last')],
        ]);

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($isEdit, $originalEngagement, $originalDate): void {
                $this->onSubmit($event, $isEdit, $originalEngagement, $originalDate);
            }
        );
    }

    private function onSubmit(FormEvent $event, bool $isEdit, ?Engagement $originalEngagement, \DateTimeImmutable $originalDate): void
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

        /** @var Timesheet $timesheet */
        $timesheet = $event->getData();

        // Re-resolved, not the build-time $originalEngagement: a brand-new entry's
        // project/activity are commonly only chosen in the browser after the form was
        // built (see this class's own doc comment) - by POST_SUBMIT the submitted data
        // is already mapped onto $timesheet, so this reflects the actual choice.
        $engagement = $this->engagements->activeFor($timesheet);
        if ($engagement === null) {
            return;
        }

        $date = $this->dateOf($timesheet);

        if ($isEdit && $originalEngagement !== null && $date->format('Y-m-d') !== $originalDate->format('Y-m-d')) {
            // Editing moved this entry off its original date - that date's FilmDay row
            // (break/catering/category/note) is orphaned once no other entry of this
            // engagement still lands on it, and must not silently pre-fill whatever
            // entry gets created there next (see FilmDayService::deleteIfOrphaned()).
            // The controller flushes this entity's new begin/project only after this
            // listener runs, so a re-query still finds the not-yet-persisted old row -
            // this timesheet's own id must be excluded by hand, same as a real delete.
            $this->filmDayService->deleteIfOrphaned($originalEngagement, $originalDate, [$timesheet->getId()]);
        }

        $breakMinutes = $form->get(self::FIELD_BREAK)->getData();
        $catering = ((bool) $form->get(self::FIELD_CATERING)->getData()) ? Catering::YES : Catering::NO;
        $categoryValue = $form->get(self::FIELD_CATEGORY)->getData();
        $category = ($categoryValue !== null && $categoryValue !== '') ? DayCategory::from((string) $categoryValue) : null;
        $note = $form->get(self::FIELD_NOTE)->getData();
        $note = ($note !== null && trim((string) $note) !== '') ? mb_substr(trim((string) $note), 0, self::MAX_NOTE_LENGTH) : null;

        $this->filmDayService->save(
            $engagement,
            $date,
            $breakMinutes !== null ? (int) $breakMinutes : null,
            $catering,
            $category,
            DayType::WORKDAY,
            null,
            $note,
        );
    }

    private function dateOf(Timesheet $timesheet): \DateTimeImmutable
    {
        return EngagementService::dateOf($timesheet);
    }
}
