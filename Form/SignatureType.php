<?php

namespace KimaiPlugin\DrehzettelBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Signature upload. The File constraint checks size and type early;
 * SignatureService checks the image content again before storing it.
 */
final class SignatureType extends AbstractType
{
    public const FIELD = 'signature';

    private const MAX_SIZE = '512k';
    private const MIME_TYPES = ['image/png', 'image/jpeg'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::FIELD, FileType::class, [
            'label' => 'drehzettel.signature.file',
            'help' => 'drehzettel.signature.file_help',
            'attr' => ['accept' => implode(',', self::MIME_TYPES)],
            'constraints' => [
                new NotNull(),
                new File(
                    maxSize: self::MAX_SIZE,
                    mimeTypes: self::MIME_TYPES,
                    maxSizeMessage: 'drehzettel.signature.too_large',
                    mimeTypesMessage: 'drehzettel.signature.invalid',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'drehzettel_signature']);
    }
}
