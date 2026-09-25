<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use App\Form\Type\DatePickerType;
use App\Form\Type\ProjectType;
use App\Form\Type\UserType;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Engagement create/edit, shown in Kimai's modal.
 *
 * Option "rulesets" (name => key) switches to create mode: user, project and
 * ruleset are only chosen once, afterwards the rules page edits the snapshot.
 * Option "currency" is the customer currency of the project (edit), or null.
 */
final class EngagementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $create = $options['rulesets'] !== null;

        if ($create) {
            $builder->add('user', UserType::class, ['label' => 'drehzettel.user']);
            $builder->add('project', ProjectType::class, ['label' => 'drehzettel.project']);
            $builder->add('ruleset', ChoiceType::class, [
                'label' => 'drehzettel.ruleset',
                'choices' => $options['rulesets'],
                'choice_translation_domain' => false,
            ]);
        }

        $builder->add('role', TextType::class, [
            'label' => 'drehzettel.role',
            'help' => 'drehzettel.role_help',
            'attr' => ['maxlength' => 100],
        ]);

        $builder->add('payKind', EnumType::class, [
            'label' => 'drehzettel.pay_kind',
            'class' => PayKind::class,
            'choice_label' => static fn (PayKind $kind): string => 'drehzettel.pay_kind.' . $kind->value,
        ]);

        $money = ['currency' => $options['currency'] ?? false, 'required' => false];
        $builder->add('gage', MoneyType::class, $money + ['label' => 'drehzettel.gage']);
        $builder->add('cateringDeduction', MoneyType::class, $money + ['label' => 'drehzettel.catering_deduction']);

        $builder->add('validFrom', DatePickerType::class, ['label' => 'drehzettel.valid_from', 'input' => 'datetime_immutable']);
        $builder->add('validTo', DatePickerType::class, [
            'label' => 'drehzettel.valid_to',
            'input' => 'datetime_immutable',
            'required' => false,
            'help' => 'drehzettel.valid_to_help',
        ]);

        // A new engagement gets AZV by default rule (TV FFS 2024, start from 2025-05-01); edit overrides it.
        if (!$create) {
            $builder->add('azv', CheckboxType::class, [
                'label' => 'drehzettel.azv.eligible',
                'help' => 'drehzettel.azv.eligible_help',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EngagementData::class,
            'rulesets' => null,
            'currency' => null,
            'csrf_token_id' => 'drehzettel_engagement',
            'validation_groups' => static fn ($form): array => $form->getConfig()->getOption('rulesets') !== null ? ['Default', 'create'] : ['Default'],
        ]);
        $resolver->setAllowedTypes('rulesets', ['null', 'array']);
        $resolver->setAllowedTypes('currency', ['null', 'string']);
    }
}
