<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use KimaiPlugin\DrehzettelBundle\Domain\MailTemplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// Week PDF mail: recipient, subject and text, prefilled from the engagement's template.
final class MailType extends AbstractType
{
    public const FIELD = 'to';
    public const FIELD_SUBJECT = 'subject';
    public const FIELD_BODY = 'body';

    private const BODY_ROWS = 10;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::FIELD, EmailType::class, [
            'label' => 'drehzettel.mail.to',
            'constraints' => [new NotBlank(), new Email()],
        ]);
        $builder->add(self::FIELD_SUBJECT, TextType::class, [
            'label' => 'drehzettel.mail.subject',
            'constraints' => [new NotBlank(), new Length(max: MailTemplate::MAX_SUBJECT_LENGTH)],
        ]);
        $builder->add(self::FIELD_BODY, TextareaType::class, [
            'label' => 'drehzettel.mail.body',
            'help' => 'drehzettel.mail.dialog_help',
            'attr' => ['rows' => self::BODY_ROWS],
            'constraints' => [new NotBlank(), new Length(max: MailTemplate::MAX_BODY_LENGTH)],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'drehzettel_week']);
    }
}
