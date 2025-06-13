<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\EducatorForm;
use App\Form\UserForm;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Repository\ChildRepository;
use App\Entity\ChildUser;
use Symfony\Component\Security\Csrf\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Psr\Log\LoggerInterface;

#[Route('/user')]
final class UserController extends AbstractController
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/delete-multiple', name: 'app_user_delete_multiple', methods: ['POST'])]
    public function deleteMultiple(Request $request, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $this->logger->info('Requête reçue sur deleteMultiple', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'request_data' => $request->request->all(),
            'content' => $request->getContent(),
        ]);

        try {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('delete_multiple_educators', $token)) {
                $this->addFlash('error', 'Erreur de sécurité : Token CSRF invalide');
                $this->logger->error('Token CSRF invalide pour deleteMultiple');
                return new Response('Erreur de sécurité', Response::HTTP_FORBIDDEN);
            }

            $educatorsJson = $request->request->get('educators', '');
            if (empty($educatorsJson)) {
                $this->logger->warning('Aucun éducateur sélectionné pour la suppression (JSON vide)');
                throw new \InvalidArgumentException('Aucun éducateur sélectionné pour la suppression.');
            }

            $educatorIds = json_decode($educatorsJson, true);
            if (!is_array($educatorIds) || empty($educatorIds)) {
                $this->logger->warning('Format de données JSON invalide pour les IDs des éducateurs', ['json' => $educatorsJson]);
                throw new \InvalidArgumentException('Format de données invalide pour les IDs des éducateurs.');
            }

            $this->logger->info('IDs des éducateurs à supprimer', ['ids' => $educatorIds]);

            // Récupérer les utilisateurs
            $users = $userRepository->findByIds($educatorIds);
            if (empty($users)) {
                $this->logger->warning('Aucun utilisateur trouvé avec les IDs fournis', ['ids' => $educatorIds]);
                throw new \InvalidArgumentException('Aucun utilisateur trouvé avec les IDs fournis.');
            }
            
            // Filtrer pour ne garder que les éducateurs
            $educators = array_filter($users, function($user) {
                return in_array('ROLE_EDUCATOR', $user->getRoles());
            });

            if (empty($educators)) {
                $this->logger->warning('Aucun éducateur valide trouvé parmi les utilisateurs sélectionnés', ['ids' => $educatorIds]);
                throw new \InvalidArgumentException('Aucun éducateur valide trouvé parmi les utilisateurs sélectionnés.');
            }

            // Démarrer une transaction
            $this->entityManager->beginTransaction();

            try {
                foreach ($educators as $educator) {
                    $this->logger->info('Suppression de l\'éducateur', ['id' => $educator->getId(), 'email' => $educator->getEmail()]);
                    // Supprimer d'abord les associations ChildUser
                    foreach ($educator->getChildUsers() as $childUser) {
                        $this->logger->info('Suppression association ChildUser', ['childUserId' => $childUser->getId()]);
                        $this->entityManager->remove($childUser);
                    }
                    
                    // Supprimer les shifts associés
                    foreach ($educator->getShifts() as $shift) {
                        $this->logger->info('Suppression shift', ['shiftId' => $shift->getId()]);
                        $this->entityManager->remove($shift);
                    }
                    
                    // Supprimer l'éducateur
                    $this->entityManager->remove($educator);
                }

                $this->entityManager->flush();
                $this->entityManager->commit();
                
                $this->logger->info(count($educators) . ' éducateur(s) supprimé(s) avec succès');
                
                $this->addFlash('success', count($educators) . ' éducateur(s) ont été supprimé(s) avec succès.');
                return new Response('OK', Response::HTTP_OK);
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $this->logger->error('Erreur lors de la transaction de suppression', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                throw $e;
            }
            
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Erreur InvalidArgument dans deleteMultiple', ['error' => $e->getMessage()]);
            $this->addFlash('warning', $e->getMessage());
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Erreur générale dans deleteMultiple', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression des éducateurs : ' . $e->getMessage());
            return new Response('Erreur serveur : ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger,
        ChildRepository $childRepository,
        LoggerInterface $logger
    ): Response {
        $user = new User();
        $form = $this->createForm(EducatorForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Définir le rôle d'éducateur
            $user->setRoles(['ROLE_EDUCATOR']);

            // Hasher le mot de passe
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('password')->getData()
            );
            $user->setPassword($hashedPassword);

            // Gérer l'upload de l'image
            $pictureFile = $form->get('picture')->getData();
            if ($pictureFile) {
                $originalFilename = pathinfo($pictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$pictureFile->guessExtension();

                try {
                    $pictureFile->move(
                        $this->getParameter('users_pictures_directory'),
                        $newFilename
                    );
                    $user->setPicture('uploads/users/'.$newFilename);
                } catch (\Exception $e) {
                    $logger->error('Erreur lors de l\'upload de l\'image pour un nouvel éducateur', ['error' => $e->getMessage()]);
                    $this->addFlash('danger', 'Une erreur est survenue lors de l\'upload de l\'image.');
                }
            }

            // Sauvegarder l'éducateur
            $entityManager->persist($user);
            $entityManager->flush();

            // Créer les associations avec tous les enfants existants
            $children = $childRepository->findAll();
            foreach ($children as $child) {
                $childUser = new ChildUser();
                $childUser->setChild($child);
                $childUser->setUser($user);
                $childUser->setLien('secondaire'); // Par défaut, on met le lien comme secondaire
                $entityManager->persist($childUser);
            }
            $entityManager->flush();

            $logger->info('Nouvel éducateur créé et associé à tous les enfants', ['userId' => $user->getId()]);
            $this->addFlash('success', 'L\'éducateur a été créé avec succès et associé à tous les enfants.');
            return $this->redirectToRoute('app_planning', ['view' => 'equipe'], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        $form = $this->createForm(UserForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $logger->info('Utilisateur modifié', ['userId' => $user->getId()]);

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            // Supprimer d'abord les associations ChildUser
            foreach ($user->getChildUsers() as $childUser) {
                $logger->info('Suppression association ChildUser lors de la suppression utilisateur', ['childUserId' => $childUser->getId()]);
                $this->entityManager->remove($childUser);
            }
            // Supprimer les shifts associés
            foreach ($user->getShifts() as $shift) {
                $logger->info('Suppression shift lors de la suppression utilisateur', ['shiftId' => $shift->getId()]);
                $this->entityManager->remove($shift);
            }

            $entityManager->remove($user);
            $entityManager->flush();
            $logger->info('Utilisateur supprimé', ['userId' => $user->getId()]);
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
