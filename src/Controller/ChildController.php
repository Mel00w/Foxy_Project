<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\ChildUser;
use App\Entity\User;
use App\Form\ChildForm;
use App\Repository\ChildRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Csrf\Exception\InvalidCsrfTokenException;

#[Route('/children')]
class ChildController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_children_index', methods: ['GET'])]
    public function index(Request $request, ChildRepository $childRepository): Response
    {
        $search = $request->query->get('search');
        $user = $this->getUser();
        
        // Si l'utilisateur est un parent, ne montrer que ses enfants
        if ($user && in_array('ROLE_PARENT', $user->getRoles())) {
            if ($search) {
                // Pour les parents, on recherche uniquement parmi leurs enfants
                $children = $childRepository->findByParent($user);
                $children = array_filter($children, function($child) use ($search) {
                    return stripos($child->getName(), $search) !== false || 
                           stripos($child->getLastname(), $search) !== false;
                });
            } else {
                $children = $childRepository->findByParent($user);
            }
        } else {
            // Pour les administrateurs, montrer tous les enfants
            if ($search) {
                $children = $childRepository->searchChildren($search);
            } else {
                $children = $childRepository->findAll();
            }
        }

        return $this->render('child/index.html.twig', [
            'children' => $children,
            'search' => $search
        ]);
    }

    #[Route('/new', name: 'app_children_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger,
        TeamRepository $teamRepository
    ): Response {
        $child = new Child();
        $form = $this->createForm(ChildForm::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculer l'âge de l'enfant
            $birthday = $child->getBirthday();
            $today = new \DateTime();
            $age = $birthday->diff($today)->y;

            // Trouver l'équipe appropriée
            $team = $teamRepository->findAppropriateTeam($age);
            if ($team) {
                $child->setTeam($team);
            } else {
                $this->addFlash('warning', 'Aucune équipe appropriée n\'a été trouvée pour l\'âge de l\'enfant (' . $age . ' ans).');
            }

            // Gérer l'upload de l'image
            $pictureFile = $form->get('picture')->getData();
            if ($pictureFile) {
                $originalFilename = pathinfo($pictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$pictureFile->guessExtension();

                try {
                    $pictureFile->move(
                        $this->getParameter('children_pictures_directory'),
                        $newFilename
                    );
                    $child->setPicture('uploads/children/'.$newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Une erreur est survenue lors de l\'upload de l\'image.');
                }
            }

            // Sauvegarder l'enfant
            $entityManager->persist($child);

            // Créer le Parent 1
            $parent1 = new User();
            $parent1->setName($form->get('parent1_name')->getData());
            $parent1->setLastname($form->get('parent1_lastname')->getData());
            $parent1->setEmail($form->get('parent1_email')->getData());
            $parent1->setBirthday($form->get('parent1_birthday')->getData());
            $parent1->setPhone($form->get('parent1_phone')->getData());
            $parent1->setAdress($form->get('parent1_adress')->getData());
            $parent1->setIncome($form->get('parent1_income')->getData());
            $parent1->setRoles(['ROLE_PARENT']);

            // Hasher le mot de passe du Parent 1
            $hashedPassword = $passwordHasher->hashPassword(
                $parent1,
                $form->get('parent1_password')->getData()
            );
            $parent1->setPassword($hashedPassword);

            // Sauvegarder le Parent 1
            $entityManager->persist($parent1);

            // Créer la relation entre l'enfant et le Parent 1
            $parent1ChildUser = new ChildUser();
            $parent1ChildUser->setChild($child);
            $parent1ChildUser->setUser($parent1);
            $parent1ChildUser->setLien($form->get('parent1_lien')->getData());
            $entityManager->persist($parent1ChildUser);

            // Créer le Parent 2 si les informations sont fournies
            if ($form->get('parent2_email')->getData()) {
                $parent2 = new User();
                $parent2->setName($form->get('parent2_name')->getData());
                $parent2->setLastname($form->get('parent2_lastname')->getData());
                $parent2->setEmail($form->get('parent2_email')->getData());
                $parent2->setBirthday($form->get('parent2_birthday')->getData());
                $parent2->setPhone($form->get('parent2_phone')->getData());
                $parent2->setAdress($form->get('parent2_adress')->getData());
                $parent2->setIncome($form->get('parent2_income')->getData());
                $parent2->setRoles(['ROLE_PARENT']);

                // Hasher le mot de passe du Parent 2
                $hashedPassword = $passwordHasher->hashPassword(
                    $parent2,
                    $form->get('parent2_password')->getData()
                );
                $parent2->setPassword($hashedPassword);

                // Sauvegarder le Parent 2
                $entityManager->persist($parent2);

                // Créer la relation entre l'enfant et le Parent 2
                $parent2ChildUser = new ChildUser();
                $parent2ChildUser->setChild($child);
                $parent2ChildUser->setUser($parent2);
                $parent2ChildUser->setLien($form->get('parent2_lien')->getData());
                $entityManager->persist($parent2ChildUser);
            }

            $entityManager->flush();

            $this->addFlash('success', 'L\'enfant et ses parents ont été créés avec succès.');
            return $this->redirectToRoute('app_children_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('child/new.html.twig', [
            'child' => $child,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_children_show', methods: ['GET'])]
    public function show(Child $child = null): Response
    {
        if (!$child) {
            $this->addFlash('danger', 'L\'enfant demandé n\'existe pas.');
            return $this->redirectToRoute('app_children_index');
        }

        return $this->render('child/show.html.twig', [
            'child' => $child,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_children_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Child $child = null, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        if (!$child) {
            $this->addFlash('danger', 'L\'enfant demandé n\'existe pas.');
            return $this->redirectToRoute('app_children_index');
        }

        $form = $this->createForm(ChildForm::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de l'image
            $pictureFile = $form->get('picture')->getData();
            if ($pictureFile) {
                $originalFilename = pathinfo($pictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$pictureFile->guessExtension();

                try {
                    $pictureFile->move(
                        $this->getParameter('children_pictures_directory'),
                        $newFilename
                    );
                    $child->setPicture('uploads/children/'.$newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Une erreur est survenue lors de l\'upload de l\'image.');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Les modifications ont été enregistrées avec succès.');
            return $this->redirectToRoute('app_children_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('child/edit.html.twig', [
            'child' => $child,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_children_delete', methods: ['POST'])]
    public function delete(Request $request, Child $child = null, EntityManagerInterface $entityManager): Response
    {
        if (!$child) {
            $this->addFlash('danger', 'L\'enfant demandé n\'existe pas.');
            return $this->redirectToRoute('app_children_index');
        }

        if ($this->isCsrfTokenValid('delete'.$child->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($child);
            $entityManager->flush();
            $this->addFlash('success', 'L\'enfant a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_children_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/children/delete-multiple', name: 'app_children_delete_multiple', methods: ['POST'])]
    public function deleteMultiple(Request $request, ChildRepository $childRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_multiple_children', $token)) {
            throw new InvalidCsrfTokenException('Token CSRF invalide');
        }

        $childrenIds = $request->request->all('children');
        if (empty($childrenIds)) {
            $this->addFlash('warning', 'Aucun enfant sélectionné pour la suppression.');
            return $this->redirectToRoute('app_planning');
        }

        $children = $childRepository->findBy(['id' => $childrenIds]);
        foreach ($children as $child) {
            // Supprimer d'abord les associations ChildUser
            foreach ($child->getChildUsers() as $childUser) {
                $this->entityManager->remove($childUser);
            }
            // Supprimer les présences associées
            foreach ($child->getPresences() as $presence) {
                $this->entityManager->remove($presence);
            }
            // Supprimer l'enfant
            $this->entityManager->remove($child);
        }

        $this->entityManager->flush();
        $this->addFlash('success', count($children) . ' enfant(s) ont été supprimé(s) avec succès.');

        return $this->redirectToRoute('app_planning');
    }
}
