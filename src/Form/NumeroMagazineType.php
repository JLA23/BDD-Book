<?php

namespace App\Form;

use App\Entity\KioskNum;
use App\Entity\Monnaie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class NumeroMagazineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('num', IntegerType::class, [
                'label' => 'Numéro',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateParution', DateType::class, [
                'label' => 'Date de parution (mois/année)',
                'required' => false,
                'widget' => 'single_text',
                'format' => 'yyyy-MM',
                'html5' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'AAAA-MM']
            ])
            ->add('EAN', TextType::class, [
                'label' => 'Code EAN',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix',
                'required' => false,
                'scale' => 2,
                'attr' => ['class' => 'form-control']
            ])
            ->add('monnaie', EntityType::class, [
                'class' => Monnaie::class,
                'choice_label' => 'libelle',
                'label' => 'Monnaie',
                'required' => false,
                'placeholder' => 'Sélectionner une monnaie',
                'attr' => ['class' => 'form-control']
            ])
            ->add('couvertureFile', FileType::class, [
                'label' => 'Image de couverture',
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
            ->add('description', TextareaType::class, [
                'label' => 'Résumé',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => KioskNum::class,
        ]);
    }
}
