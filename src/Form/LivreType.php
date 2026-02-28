<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Collection;
use App\Entity\Edition;
use App\Entity\Livre;
use App\Entity\Monnaie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class LivreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'Le titre est obligatoire'])],
            ])
            ->add('isbn', TextType::class, [
                'label' => 'ISBN',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'nom',
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => '-- Choisir --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('collection', EntityType::class, [
                'class' => Collection::class,
                'choice_label' => 'nom',
                'label' => 'Collection',
                'required' => false,
                'placeholder' => '-- Choisir --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('edition', EntityType::class, [
                'class' => Edition::class,
                'choice_label' => 'nom',
                'label' => 'Éditeur',
                'required' => false,
                'placeholder' => '-- Choisir --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('numero', IntegerType::class, [
                'label' => 'Numéro',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('cycle', TextType::class, [
                'label' => 'Cycle / Série',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('tome', IntegerType::class, [
                'label' => 'Tome',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('pages', IntegerType::class, [
                'label' => 'Nombre de pages',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('prixBase', NumberType::class, [
                'label' => 'Prix de base',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('monnaie', EntityType::class, [
                'class' => Monnaie::class,
                'choice_label' => 'libelle',
                'label' => 'Monnaie',
                'required' => false,
                'placeholder' => '-- Choisir --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('resume', TextareaType::class, [
                'label' => 'Résumé',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 5],
            ])
            ->add('amazon', TextType::class, [
                'label' => 'Lien Amazon',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('auteurs', TextType::class, [
                'label' => 'Auteur(s)',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Séparés par des virgules'],
            ])
            ->add('dateAchat', DateType::class, [
                'label' => 'Date d\'acquisition',
                'mapped' => false,
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('prixAchat', NumberType::class, [
                'label' => 'Prix d\'achat',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'État, particularités, notes personnelles...'],
            ])
            ->add('newCategory', TextType::class, [
                'label' => 'Nouvelle catégorie',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom de la nouvelle catégorie'],
            ])
            ->add('newCollection', TextType::class, [
                'label' => 'Nouvelle collection',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom de la nouvelle collection'],
            ])
            ->add('newEdition', TextType::class, [
                'label' => 'Nouvel éditeur',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom du nouvel éditeur'],
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'URL de l\'image',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://...'],
            ])
            ->add('imageFile', FileType::class, [
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
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Livre::class,
        ]);
    }
}
