<?php

namespace App\Form;

use App\Entity\BrickCollection;
use App\Entity\BrickMarque;
use App\Entity\BrickSet;
use App\Repository\BrickCollectionRepository;
use App\Repository\BrickMarqueRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BrickSetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du set',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Millennium Falcon'],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 75192'],
            ])
            ->add('marque', EntityType::class, [
                'class' => BrickMarque::class,
                'choice_label' => 'nom',
                'label' => 'Marque',
                'required' => false,
                'placeholder' => '-- Sélectionner une marque --',
                'attr' => ['class' => 'form-control'],
                'query_builder' => function (BrickMarqueRepository $repo) {
                    return $repo->createQueryBuilder('m')->orderBy('m.nom', 'ASC');
                },
            ])
            ->add('collection', EntityType::class, [
                'class' => BrickCollection::class,
                'choice_label' => 'nom',
                'label' => 'Collection / Thème',
                'required' => false,
                'placeholder' => '-- Sélectionner une collection --',
                'attr' => ['class' => 'form-control'],
                'query_builder' => function (BrickCollectionRepository $repo) {
                    return $repo->createQueryBuilder('c')->orderBy('c.nom', 'ASC');
                },
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00', 'step' => '0.01', 'min' => '0'],
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année de sortie',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => date('Y'), 'min' => 1949, 'max' => date('Y') + 2],
            ])
            ->add('nbPieces', IntegerType::class, [
                'label' => 'Nombre de pièces',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0', 'min' => 0],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BrickSet::class,
        ]);
    }
}
