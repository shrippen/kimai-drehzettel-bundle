<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use KimaiPlugin\DrehzettelBundle\Domain\MailSchedule;
use KimaiPlugin\DrehzettelBundle\Domain\MailTemplate;
use KimaiPlugin\DrehzettelBundle\Enum\MailRhythm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Mail settings of an engagement: recipient, text template with {placeholders},
 * automatic sending (off, weekly on a weekday, monthly on the 1st, at an hour).
 * Data: array with the FIELD_* keys. Empty subject/body mean the default text.
 */
final class MailSettingsType extends AbstractType
{
    public const FIELD_TO = 'to';
    public const FIELD_SUBJECT = 'subject';
    public const FIELD_BODY = 'body';
    public const FIELD_RHYTHM = 'rhythm';
    public const FIELD_WEEKDAY = 'weekday';
    public const FIELD_HOUR = 'hour';

    private const BODY_ROWS = 10;
    // Kimai's translation keys of the weekdays, by ISO number.
    private const WEEKDAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::FIELD_TO, EmailType::class, [
            'label' => 'drehzettel.mail.to',
            'required' => false,
            'constraints' => [new Email()],
        ]);

        $builder->add(self::FIELD_RHYTHM, ChoiceType::class, [
            'label' => 'drehzettel.mail.rhythm',
            'choices' => [
                'drehzettel.mail.rhythm.off' => MailRhythm::OFF->value,
                'drehzettel.mail.rhythm.weekly' => MailRhythm::WEEKLY->value,
                'drehzettel.mail.rhythm.monthly' => MailRhythm::MONTHLY->value,
            ],
            'help' => 'drehzettel.mail.rhythm_help',
        ]);

        $weekdays = [];
        foreach (self::WEEKDAYS as $number => $name) {
            $weekdays[$name] = $number;
        }
        $builder->add(self::FIELD_WEEKDAY, ChoiceType::class, [
            'label' => 'drehzettel.mail.weekday',
            'choices' => $weekdays,
            'choice_translation_domain' => 'system-configuration', // Kimai's own weekday names
        ]);

        $hours = [];
        for ($hour = 0; $hour <= MailSchedule::MAX_HOUR; ++$hour) {
            $hours[sprintf('%02d:00', $hour)] = $hour;
        }
        $builder->add(self::FIELD_HOUR, ChoiceType::class, [
            'label' => 'drehzettel.mail.hour',
            'choices' => $hours,
            'choice_translation_domain' => false,
        ]);

        $builder->add(self::FIELD_SUBJECT, TextType::class, [
            'label' => 'drehzettel.mail.subject',
            'required' => false,
            'attr' => ['placeholder' => $options['default_subject']],
            'constraints' => [new Length(max: MailTemplate::MAX_SUBJECT_LENGTH)],
        ]);
        $builder->add(self::FIELD_BODY, TextareaType::class, [
            'label' => 'drehzettel.mail.body',
            'required' => false,
            'help' => 'drehzettel.mail.template_help',
            'help_translation_parameters' => ['%placeholders%' => MailTemplate::placeholderList()],
            'attr' => ['rows' => self::BODY_ROWS, 'placeholder' => $options['default_body']],
            'constraints' => [new Length(max: MailTemplate::MAX_BODY_LENGTH)],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'drehzettel_mail_settings',
            'default_subject' => '',
            'default_body' => '',
        ]);
    }
}
