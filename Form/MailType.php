<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

// Recipient of the week PDF mail, prefilled with the last one used for the engagement.
final class MailType extends AbstractType
{
    public const FIELD = 'to';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::FIELD, EmailType::class, [
            'label' => 'drehzettel.mail.to',
            'constraints' => [new NotBlank(), new Email()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'drehzettel_week']);
    }
}
