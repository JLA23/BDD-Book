<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforme une valeur HTML input[type=month] (YYYY-MM) en DateTime (1er du mois).
 */
class MonthYearToDateTimeTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m');
        }

        return null;
    }

    public function reverseTransform(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('La date de parution doit être une chaîne YYYY-MM.');
        }

        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return \DateTime::createFromFormat('Y-m-d', $value . '-01') ?: null;
        }

        try {
            return new \DateTime($value);
        } catch (\Exception) {
            throw new TransformationFailedException(sprintf('« %s » n’est pas un mois valide (attendu : AAAA-MM).', $value));
        }
    }
}
