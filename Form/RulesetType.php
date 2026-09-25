<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use App\Form\Type\TimePickerType;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetFormMapper;
use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;
use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;
use KimaiPlugin\DrehzettelBundle\Enum\SurchargeBasis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ruleset editor on the flat data of RulesetFormMapper (hours and percent).
 * Field names are the mapper keys, so the form data goes straight into
 * RulesetFormMapper::fromForm().
 *
 * Percent and minute fields suggest the common values through a datalist
 * (rendered by ruleset_form.html.twig); any other value can be typed in.
 */
final class RulesetType extends AbstractType
{
    public const LIST_PERCENT = 'dz-presets-percent';
    public const LIST_BREAK = 'dz-presets-break';

    private const MAX_MINUTES = 720;
    private const MAX_PERCENT = 1000;
    private const MAX_DAY_HOURS = 24;
    private const MAX_WEEK_HOURS = 168;
    private const DAILY_STEP = 0.25;
    private const WEEKLY_STEP = 0.5;
    private const PERCENT_STEP = 0.01;
    private const CLOCK = 'H:i';
    private const CATEGORIES = ['saturday', 'sunday', 'holiday'];
    private const TIER_KINDS = ['dailyTier' => RulesetFormMapper::DAILY_TIER_SLOTS, 'weeklyTier' => RulesetFormMapper::WEEKLY_TIER_SLOTS];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['with_name']) {
            $builder->add('name', TextType::class, [
                'label' => 'drehzettel.ruleset.name',
                'constraints' => [new NotBlank()],
                'attr' => ['maxlength' => 100],
            ]);
        }

        // Break
        $builder->add('defaultBreakMinutes', IntegerType::class, $this->minutes('drehzettel.rules.default_break'));
        $builder->add('breakRule', ChoiceType::class, [
            'label' => 'drehzettel.rules.break_rule',
            'choices' => $this->enumChoices(BreakRule::cases(), 'drehzettel.rules.break_rule.'),
        ]);
        $builder->add('freeBreakMinutes', IntegerType::class, $this->minutes('drehzettel.rules.free_break'));

        // Rounding
        foreach (['work' => 'drehzettel.rules.work_rounding', 'surcharge' => 'drehzettel.rules.surcharge_rounding'] as $prefix => $label) {
            $builder->add($prefix . 'RoundingUnit', ChoiceType::class, [
                'label' => $label,
                'choices' => $this->enumChoices(RoundingUnit::cases(), 'drehzettel.rounding.unit.'),
            ]);
            $builder->add($prefix . 'RoundingMode', ChoiceType::class, [
                'label' => 'drehzettel.rules.rounding_mode',
                'choices' => $this->enumChoices(RoundingMode::cases(), 'drehzettel.rounding.mode.'),
            ]);
        }

        // Daily and weekly tiers: an empty slot is not used
        foreach (self::TIER_KINDS as $prefix => $slots) {
            $step = $prefix === 'dailyTier' ? self::DAILY_STEP : self::WEEKLY_STEP;
            $max = $prefix === 'dailyTier' ? self::MAX_DAY_HOURS : self::MAX_WEEK_HOURS;
            for ($i = 1; $i <= $slots; ++$i) {
                $builder->add($prefix . $i . 'After', NumberType::class, $this->hours('drehzettel.rules.after_hours', $step, $max, false) + [
                    'label_translation_parameters' => ['%tier%' => $i],
                ]);
                $builder->add($prefix . $i . 'Percent', NumberType::class, $this->percent('drehzettel.rules.surcharge', false));
            }
        }
        $builder->add('weeklyGageHours', NumberType::class, $this->hours('drehzettel.rules.weekly_gage_hours', 1, self::MAX_WEEK_HOURS, true));
        $builder->add('dailyGageHours', NumberType::class, $this->hours('drehzettel.rules.daily_gage_hours', 1, self::MAX_DAY_HOURS, true));
        $builder->add('minDayHours', NumberType::class, $this->hours('drehzettel.rules.min_day_hours', self::DAILY_STEP, self::MAX_DAY_HOURS, true));

        // Night
        foreach (['nightFrom' => 'drehzettel.rules.night_from', 'nightTo' => 'drehzettel.rules.night_to'] as $name => $label) {
            $builder->add($name, TimePickerType::class, ['label' => $label, 'constraints' => [new NotBlank()]]);
            $builder->get($name)->addModelTransformer(self::clockTransformer());
        }
        $builder->add('nightPercent', NumberType::class, $this->percent('drehzettel.rules.night_percent', true));

        // Saturday, Sunday, holiday
        foreach (self::CATEGORIES as $category) {
            $builder->add($category . 'Percent', NumberType::class, $this->percent('drehzettel.category.' . $category, false));
            $builder->add($category . 'Basis', ChoiceType::class, [
                'label' => 'drehzettel.rules.basis',
                'choices' => $this->enumChoices(SurchargeBasis::cases(), 'drehzettel.rules.basis.'),
            ]);
        }

        // 6th and 7th day: empty pools the day into weekly overtime
        $builder->add('sixthDayPercent', NumberType::class, $this->percent('drehzettel.rules.sixth_day', false));
        $builder->add('seventhDayPercent', NumberType::class, $this->percent('drehzettel.rules.seventh_day', false));
        $builder->add('streakMode', ChoiceType::class, [
            'label' => 'drehzettel.rules.streak_mode',
            'choices' => $this->enumChoices(StreakMode::cases(), 'drehzettel.rules.streak_mode.'),
            'help' => 'drehzettel.rules.streak_mode.help',
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $this->checkTierOrder($event->getForm());
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'with_name' => false,
            'csrf_token_id' => 'drehzettel_ruleset',
        ]);
        $resolver->setAllowedTypes('with_name', 'bool');
    }

    // Tiers must start in ascending order, otherwise a percentage would move to another tier
    // when Ruleset sorts them. RulesetFormMapper::fromForm() rejects it again as a last guard.
    private function checkTierOrder(FormInterface $form): void
    {
        foreach (self::TIER_KINDS as $prefix => $slots) {
            $previous = null;
            for ($i = 1; $i <= $slots; ++$i) {
                $after = $form->get($prefix . $i . 'After')->getData();
                $percent = $form->get($prefix . $i . 'Percent')->getData();
                if ($after === null || $percent === null) {
                    continue;
                }
                if ($previous !== null && $after <= $previous['after']) {
                    $form->get($prefix . $i . 'After')->addError(new FormError($this->translator->trans(
                        'drehzettel.rules.tier_order',
                        ['%tier%' => $previous['tier'], '%after%' => $previous['after']],
                        'validators',
                    )));
                }
                $previous = ['tier' => $i, 'after' => $after];
            }
        }
    }

    // Mapper clock "22:00" <-> DateTime of Kimai's TimePickerType (shown in the user's time format).
    private static function clockTransformer(): CallbackTransformer
    {
        return new CallbackTransformer(
            static fn (?string $clock): ?\DateTime => $clock === null || $clock === '' ? null : (\DateTime::createFromFormat(self::CLOCK, $clock) ?: null),
            static fn (?\DateTimeInterface $time): ?string => $time?->format(self::CLOCK),
        );
    }

    /**
     * @param list<\BackedEnum> $cases
     * @return array<string, int|string>
     */
    private function enumChoices(array $cases, string $prefix): array
    {
        $choices = [];
        foreach ($cases as $case) {
            $choices[$prefix . $case->value] = $case->value;
        }

        return $choices;
    }

    /**
     * @return array<string, mixed>
     */
    private function minutes(string $label): array
    {
        return [
            'label' => $label,
            'attr' => ['min' => 0, 'max' => self::MAX_MINUTES, 'list' => self::LIST_BREAK],
            'constraints' => [new NotBlank(), new Range(min: 0, max: self::MAX_MINUTES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hours(string $label, float $step, int $max, bool $required): array
    {
        $constraints = [new Range(min: 0, max: $max)];
        if ($required) {
            $constraints[] = new NotBlank();
        }

        return [
            'label' => $label,
            'required' => $required,
            'html5' => true,
            'attr' => ['min' => 0, 'max' => $max, 'step' => $step],
            'constraints' => $constraints,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function percent(string $label, bool $required): array
    {
        $constraints = [new Range(min: 0, max: self::MAX_PERCENT)];
        if ($required) {
            $constraints[] = new NotBlank();
        }

        return [
            'label' => $label,
            'required' => $required,
            'html5' => true,
            'attr' => ['min' => 0, 'max' => self::MAX_PERCENT, 'step' => self::PERCENT_STEP, 'list' => self::LIST_PERCENT],
            'help' => $required ? null : 'drehzettel.rules.empty_unused',
            'constraints' => $constraints,
        ];
    }
}
