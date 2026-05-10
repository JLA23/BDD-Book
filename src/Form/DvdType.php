<?php

namespace App\Form;

use App\Entity\Dvd;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DvdType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Titre du DVD / Blu-ray'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Film' => 'film',
                    'Série' => 'serie',
                ],
                'placeholder' => '-- Sélectionner --',
                'required' => false,
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'Format',
                'choices' => [
                    'DVD' => 'dvd',
                    'Blu-ray' => 'bluray',
                    'Blu-ray 4K' => 'bluray4k',
                ],
                'placeholder' => '-- Sélectionner --',
                'required' => false,
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 2023'],
            ])
            ->add('editeur', TextType::class, [
                'label' => 'Éditeur / Studio',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Disney, Warner...'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('coverUrl', UrlType::class, [
                'label' => 'URL de la jaquette',
                'required' => false,
                'attr' => ['placeholder' => 'https://...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dvd::class,
        ]);
    }
}
