<?php

namespace App\Form;

use App\Entity\Game;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $classifications = [
            'PEGI 3' => 'PEGI 3',
            'PEGI 7' => 'PEGI 7',
            'PEGI 12' => 'PEGI 12',
            'PEGI 16' => 'PEGI 16',
            'PEGI 18' => 'PEGI 18',
            'Non classé' => 'Non classé',
        ];

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du jeu',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: The Legend of Zelda'],
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année de sortie',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => date('Y'), 'min' => 1970, 'max' => date('Y') + 2],
            ])
            ->add('editeur', TextType::class, [
                'label' => 'Éditeur',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Nintendo, Sony, EA...'],
            ])
            ->add('developpeur', TextType::class, [
                'label' => 'Développeur',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Naughty Dog, Rockstar...'],
            ])
            ->add('classification', ChoiceType::class, [
                'label' => 'Classification',
                'required' => false,
                'choices' => $classifications,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('genre', TextType::class, [
                'label' => 'Genre',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Action, RPG, Aventure...'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('coverUrl', UrlType::class, [
                'label' => 'URL de la jaquette',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Game::class,
        ]);
    }
}
