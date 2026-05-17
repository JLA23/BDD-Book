<?php

namespace App\Form\Type;

use App\Form\DataTransformer\MonthYearToDateTimeTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Champ mois/année via input HTML5 type="month" (valeur YYYY-MM).
 */
class MonthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new MonthYearToDateTimeTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'type' => 'month',
                'class' => 'form-control',
            ],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
