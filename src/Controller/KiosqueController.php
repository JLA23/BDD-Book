<?php
namespace App\Controller;

use App\Entity\KioskCollec;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Livre;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use \App\Entity\User;


class KiosqueController extends AbstractController
{
    /**
     * @Route("/addCollectionKiosque", name="addCollectionKiosque")
     */
    public function addCollectionKiosque(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if ($user) {
            $collecKiosque = new KioskCollec();
            $collecKiosque->setCreateUser($user);
            $collecKiosque->setUpdateUser($user);
            $collecKiosque->setCreateDate(new \DateTime('now'));
            $collecKiosque->setUpdateDate(new \DateTime('now'));

            $form = $this->createFormBuilder($collecKiosque)
                ->add('Nom de la collection', TextType::class)
                ->add('Editeur', TextType::class)
                ->add('Date de début de la collection', Date::class)
                ->add('Date de fin de la collection', Date::class)
                ->add('Nombre de numèros', Integer::class)
                ->add('Encore en publication', Boolean::class)
                ->add('Image', Boolean::class)
                ->add('Commentaire', TextType::class)
                ->add('save', SubmitType::class, ['label' => 'Create Task'])
                ->getForm();
        }

    }

}