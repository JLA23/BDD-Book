<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SectionPermissionService
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Vérifie si l'utilisateur courant peut enregistrer dans une section
     */
    public function canRegister(string $section): bool
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return false;
        }

        return $user->canRegisterInSection($section);
    }

    /**
     * Lève une exception si l'utilisateur ne peut pas enregistrer dans la section
     */
    public function denyAccessUnlessCanRegister(string $section): void
    {
        if (!$this->canRegister($section)) {
            throw new AccessDeniedHttpException(
                sprintf("Vous n'avez pas la permission d'enregistrer dans la section %s.", User::SECTIONS[$section] ?? $section)
            );
        }
    }
}
