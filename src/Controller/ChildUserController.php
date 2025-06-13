<?php

namespace App\Controller;

use App\Entity\ChildUser;
use App\Entity\Child;
use App\Entity\User;
use App\Form\ChildUserForm;
use App\Repository\ChildRepository;
use App\Repository\UserRepository;
use App\Repository\ChildUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/child-user')]
#[IsGranted('ROLE_ADMIN')]
class ChildUserController extends AbstractController
{
    #[Route('/', name: 'app_child_user_index', methods: ['GET'])]
    public function index(ChildUserRepository $childUserRepository): Response
    {
        return $this->render('child_user/index.html.twig', [
            'child_users' => $childUserRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_child_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        ChildRepository $childRepository,
        UserRepository $userRepository
    ): Response {
        $childUser = new ChildUser();
        $form = $this->createForm(ChildUserForm::class, $childUser, [
            'children' => $childRepository->findAll(),
            'educators' => $userRepository->findByRole('ROLE_EDUCATOR')
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($childUser);
            $entityManager->flush();

            $this->addFlash('success', 'L\'association a été créée avec succès.');
            return $this->redirectToRoute('app_child_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('child_user/new.html.twig', [
            'child_user' => $childUser,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_child_user_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        ChildUser $childUser, 
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$childUser->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($childUser);
            $entityManager->flush();
            $this->addFlash('success', 'L\'association a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_child_user_index', [], Response::HTTP_SEE_OTHER);
    }
} 