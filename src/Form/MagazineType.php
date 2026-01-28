<?php

namespace App\Form;

use App\Entity\KioskCollec;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class MagazineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du magazine',
                'attr' => ['class' => 'form-control']
            ])
            ->add('editeur', TextType::class, [
                'label' => 'Éditeur',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('debpub', DateType::class, [
                'label' => 'Date de début de publication',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('findeb', DateType::class, [
                'label' => 'Date de fin de publication',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('nbnum', IntegerType::class, [
                'label' => 'Nombre de numéros',
                'attr' => ['class' => 'form-control']
            ])
            ->add('statut', CheckboxType::class, [
                'label' => 'Encore en publication',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image (couverture ou logo)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF, WEBP)',
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => KioskCollec::class,
        ]);
    }
}
