<?php

namespace App\Form;

use App\Entity\Musique;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MusiqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Titre de l\'album'],
            ])
            ->add('artiste', TextType::class, [
                'label' => 'Artiste',
                'required' => false,
                'attr' => ['placeholder' => 'Nom de l\'artiste ou groupe'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Album' => 'album',
                    'Single' => 'single',
                    'EP' => 'ep',
                    'Compilation' => 'compilation',
                ],
                'placeholder' => '-- Sélectionner --',
                'required' => false,
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'Format',
                'choices' => [
                    'CD' => 'cd',
                    'Vinyle' => 'vinyle',
                    'K7' => 'k7',
                    'Digital' => 'digital',
                ],
                'placeholder' => '-- Sélectionner --',
                'required' => false,
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 2023'],
            ])
            ->add('label', TextType::class, [
                'label' => 'Label',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Universal, Sony...'],
            ])
            ->add('genre', TextType::class, [
                'label' => 'Genre',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Rock, Pop, Jazz...'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('tracklist', TextareaType::class, [
                'label' => 'Liste des pistes',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => "1. Titre de la piste 1\n2. Titre de la piste 2\n..."],
            ])
            ->add('coverUrl', UrlType::class, [
                'label' => 'URL de la pochette',
                'required' => false,
                'attr' => ['placeholder' => 'https://...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Musique::class,
        ]);
    }
}
