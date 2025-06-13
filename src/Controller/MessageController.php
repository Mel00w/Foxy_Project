<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Conversation;
use App\Repository\MessageRepository;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Entity\User;

#[Route('/messages')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageRepository $messageRepository,
        private ConversationRepository $conversationRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('/', name: 'app_messages_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversation = null;
        $messages = [];
        $parentId = $request->query->get('parent_id');
        $lastMessageTime = $request->query->get('last_message');
        $conversationId = $request->query->get('conversation');

        // Si c'est une requête AJAX, toujours renvoyer du JSON
        if ($request->isXmlHttpRequest()) {
            if ($conversationId) {
                $conversation = $this->conversationRepository->find($conversationId);
                if (!$conversation) {
                    return new JsonResponse(['error' => 'Conversation non trouvée'], Response::HTTP_NOT_FOUND);
                }

                // Vérifier que l'utilisateur a accès à la conversation
                if ($conversation->getParent() !== $user && !$conversation->getTeamMembers()->contains($user)) {
                    return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
                }

                // Si lastMessageTime est fourni, ne renvoyer que les nouveaux messages
                if ($lastMessageTime) {
                    try {
                        $lastMessageDateTime = new \DateTimeImmutable($lastMessageTime);
                        $messages = $this->messageRepository->findNewerMessages($conversation, $lastMessageDateTime);
                    } catch (\Exception $e) {
                        return new JsonResponse(['error' => 'Format de date invalide'], Response::HTTP_BAD_REQUEST);
                    }
                } else {
                    // Sinon, renvoyer tous les messages de la conversation
                    $messages = $this->messageRepository->findBy(
                        ['conversation' => $conversation],
                        ['createdAt' => 'ASC']
                    );
                }

                $responseData = [
                    'conversation' => [
                        'id' => $conversation->getId(),
                        'parent' => [
                            'id' => $conversation->getParent()->getId(),
                            'name' => $conversation->getParent()->getName(),
                            'lastname' => $conversation->getParent()->getLastname(),
                        ],
                        'updatedAt' => $conversation->getUpdatedAt()->format('Y-m-d H:i:s'),
                    ],
                    'messages' => array_map(function($message) use ($user) {
                        /** @var User $sender */
                        $sender = $message->getSender();
                        $messageData = [
                            'id' => $message->getId(),
                            'content' => $message->getContent(),
                            'sender' => [
                                'id' => $sender->getId(),
                                'name' => $sender->getName(),
                                'lastname' => $sender->getLastname(),
                            ],
                            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                            'isRead' => $message->isRead(),
                            'isMine' => $sender->getId() === $user->getId(),
                        ];
                        
                        // Ajouter les informations sur le fichier joint si présent
                        if ($message->hasAttachment()) {
                            $messageData['attachment'] = [
                                'filename' => $message->getAttachmentFilename(),
                                'originalName' => $message->getAttachmentOriginalName(),
                                'mimeType' => $message->getAttachmentMimeType(),
                            ];
                        }
                        
                        return $messageData;
                    }, $messages),
                ];

                return new JsonResponse($responseData);
            }
            return new JsonResponse(['error' => 'ID de conversation manquant'], Response::HTTP_BAD_REQUEST);
        }

        // Pour le chargement initial de la page (non-AJAX)
        if (in_array('ROLE_PARENT', $user->getRoles())) {
            $conversation = $this->conversationRepository->findConversationByParent($user);
            if ($conversation) {
                $messages = $this->messageRepository->findBy(
                    ['conversation' => $conversation],
                    ['createdAt' => 'ASC']
                );
            }
        } else {
            // Pour les admin/éducateurs, récupérer toutes les conversations
            $allConversations = $this->conversationRepository->findConversationsByTeamMember($user);
            
            // Regrouper les conversations par parent et ne garder que la plus récente
            $conversationsByParent = [];
            foreach ($allConversations as $conv) {
                $parentId = $conv->getParent()->getId();
                if (!isset($conversationsByParent[$parentId]) || 
                    $conv->getUpdatedAt() > $conversationsByParent[$parentId]->getUpdatedAt()) {
                    $conversationsByParent[$parentId] = $conv;
                }
            }
            $conversations = array_values($conversationsByParent);
            
            if ($parentId && in_array('ROLE_ADMIN', $user->getRoles())) {
                $parent = $this->userRepository->find($parentId);
                if ($parent && in_array('ROLE_PARENT', $parent->getRoles())) {
                    $conversation = $this->conversationRepository->findConversationByParent($parent);
                    if (!$conversation) {
                        $conversation = new Conversation();
                        $conversation->setParent($parent);
                        $conversation->addTeamMember($user);
                        $this->entityManager->persist($conversation);
                        $this->entityManager->flush();
                        $conversations[] = $conversation;
                    }
                }
            } else if ($conversationId) {
                $conversation = $this->conversationRepository->find($conversationId);
            } else if (!empty($conversations)) {
                $conversation = $conversations[0];
            }

            if ($conversation) {
                $messages = $this->messageRepository->findBy(
                    ['conversation' => $conversation],
                    ['createdAt' => 'ASC']
                );
            }
        }

        // Récupérer la liste des parents pour le menu déroulant (uniquement pour les admins)
        $parents = [];
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $parents = $this->userRepository->findByRole('ROLE_PARENT');
        }

        return $this->render('message/index.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'conversations' => $conversations ?? [],
            'parents' => $parents,
            'selectedParentId' => $parentId,
        ]);
    }

    #[Route('/send', name: 'app_messages_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $content = $request->request->get('content');
        $conversationId = $request->request->get('conversation_id');

        if (!$content) {
            return new JsonResponse(['error' => 'Le message ne peut pas être vide'], Response::HTTP_BAD_REQUEST);
        }

        $conversation = null;
        if ($conversationId) {
            $conversation = $this->conversationRepository->find($conversationId);
        }

        if (!$conversation) {
            if (in_array('ROLE_PARENT', $user->getRoles())) {
                // Créer une nouvelle conversation pour le parent
                $conversation = new Conversation();
                /** @var User $parentUser */
                $parentUser = $user;
                $conversation->setParent($parentUser);
                
                // Ajouter tous les éducateurs et l'admin à la conversation
                $teamMembers = $this->userRepository->findByRole('ROLE_EDUCATOR');
                $admin = $this->userRepository->findByRole('ROLE_ADMIN')[0] ?? null;
                
                foreach ($teamMembers as $educator) {
                    /** @var User $educatorUser */
                    $educatorUser = $educator;
                    $conversation->addTeamMember($educatorUser);
                }
                if ($admin) {
                     /** @var User $adminUser */
                     $adminUser = $admin;
                    $conversation->addTeamMember($adminUser);
                }

                $this->entityManager->persist($conversation);
                $this->entityManager->flush(); // Flush pour obtenir l'ID de la conversation
            } else {
                return new JsonResponse(['error' => 'Conversation non trouvée'], Response::HTTP_NOT_FOUND);
            }
        }

        $message = new Message();
        $message->setContent($content);
        $message->setSender($user);
        $message->setConversation($conversation);

        // Mettre à jour la conversation
        $conversation->addMessage($message);
        $conversation->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($message);
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'sender' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'lastname' => $user->getLastname(),
            ],
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            'conversation_id' => $conversation->getId(), // Ajout de l'ID de la conversation
            'conversation' => [
                'id' => $conversation->getId(),
                'parent' => [
                    'id' => $conversation->getParent()->getId(),
                    'name' => $conversation->getParent()->getName(),
                    'lastname' => $conversation->getParent()->getLastname(),
                ],
                'updatedAt' => $conversation->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    #[Route('/mark-read/{id}', name: 'app_messages_mark_read', methods: ['POST'])]
    public function markAsRead(Message $message): JsonResponse
    {
        $user = $this->getUser();
        $conversation = $message->getConversation();

        // Vérifier que l'utilisateur a accès à la conversation
        if ($conversation->getParent() !== $user && !$conversation->getTeamMembers()->contains($user)) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        $message->setIsRead(true);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/unread-count', name: 'app_messages_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['count' => 0], Response::HTTP_OK, [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        }

        $count = $this->messageRepository->countUnreadMessages($user);
        
        // Forcer la mise à jour de l'EntityManager pour s'assurer que les changements sont pris en compte
        $this->entityManager->clear();
        
        return new JsonResponse(['count' => $count], Response::HTTP_OK, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    #[Route('/send-attachment', name: 'app_messages_send_attachment', methods: ['POST'])]
    public function sendWithAttachment(Request $request, SluggerInterface $slugger): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $content = $request->request->get('content', '');
        $conversationId = $request->request->get('conversation_id');
        $file = $request->files->get('attachment');

        if (!$file) {
            return new JsonResponse(['error' => 'Aucun fichier n\'a été fourni'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que le fichier est valide et lisible
        if (!$file->isValid()) {
            return new JsonResponse(['error' => 'Le fichier uploadé n\'est pas valide'], Response::HTTP_BAD_REQUEST);
        }

        if (!$file->isReadable()) {
            return new JsonResponse(['error' => 'Le fichier uploadé n\'est pas lisible'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification de la taille du fichier (5MB max)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['error' => 'Le fichier est trop volumineux (maximum 5MB)'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() === 0) {
            return new JsonResponse(['error' => 'Le fichier est vide'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification du type de fichier
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        try {
            $mimeType = $file->getMimeType();
            if (!$mimeType || !in_array($mimeType, $allowedMimeTypes)) {
                return new JsonResponse(['error' => 'Type de fichier non autorisé. Types acceptés : PDF, Word, Excel, images (JPEG, PNG, GIF), texte'], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Impossible de déterminer le type de fichier'], Response::HTTP_BAD_REQUEST);
        }

        $conversation = null;
        if ($conversationId) {
            $conversation = $this->conversationRepository->find($conversationId);
        }

        if (!$conversation) {
            if (in_array('ROLE_PARENT', $user->getRoles())) {
                // Créer une nouvelle conversation pour le parent
                $conversation = new Conversation();
                /** @var User $parentUser */
                $parentUser = $user;
                $conversation->setParent($parentUser);
                
                // Ajouter tous les éducateurs et l'admin à la conversation
                $teamMembers = $this->userRepository->findByRole('ROLE_EDUCATOR');
                $admin = $this->userRepository->findByRole('ROLE_ADMIN')[0] ?? null;
                
                foreach ($teamMembers as $educator) {
                    /** @var User $educatorUser */
                    $educatorUser = $educator;
                    $conversation->addTeamMember($educatorUser);
                }
                if ($admin) {
                     /** @var User $adminUser */
                     $adminUser = $admin;
                    $conversation->addTeamMember($adminUser);
                }

                $this->entityManager->persist($conversation);
                $this->entityManager->flush();
            } else {
                return new JsonResponse(['error' => 'Conversation non trouvée'], Response::HTTP_NOT_FOUND);
            }
        }

        try {
            // Génération d'un nom de fichier unique
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

            // Détermination du dossier de destination
            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/messages/';
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new FileException('Impossible de créer le dossier d\'upload');
                }
            }

            // Vérifier que le dossier est accessible en écriture
            if (!is_writable($uploadDir)) {
                throw new FileException('Le dossier d\'upload n\'est pas accessible en écriture');
            }

            // Déplacer le fichier
            $file->move($uploadDir, $newFilename);

            // Vérifier que le fichier a bien été déplacé
            $finalPath = $uploadDir . $newFilename;
            if (!file_exists($finalPath)) {
                throw new FileException('Le fichier n\'a pas pu être sauvegardé');
            }

            // Création du message avec le fichier joint
            $message = new Message();
            $message->setContent($content ?: 'Fichier joint');
            $message->setSender($user);
            $message->setConversation($conversation);
            $message->setAttachmentFilename($newFilename);
            $message->setAttachmentOriginalName($file->getClientOriginalName());
            $message->setAttachmentMimeType($mimeType);

            // Mettre à jour la conversation
            $conversation->addMessage($message);
            $conversation->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($message);
            $this->entityManager->persist($conversation);
            $this->entityManager->flush();

            return new JsonResponse([
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'lastname' => $user->getLastname(),
                ],
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                'conversation_id' => $conversation->getId(),
                'attachment' => [
                    'filename' => $message->getAttachmentFilename(),
                    'originalName' => $message->getAttachmentOriginalName(),
                    'mimeType' => $message->getAttachmentMimeType(),
                ],
                'conversation' => [
                    'id' => $conversation->getId(),
                    'parent' => [
                        'id' => $conversation->getParent()->getId(),
                        'name' => $conversation->getParent()->getName(),
                        'lastname' => $conversation->getParent()->getLastname(),
                    ],
                    'updatedAt' => $conversation->getUpdatedAt()->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'upload du fichier : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Une erreur inattendue est survenue : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/download-attachment/{id}', name: 'app_messages_download_attachment', methods: ['GET'])]
    public function downloadAttachment(Message $message): Response
    {
        $user = $this->getUser();
        $conversation = $message->getConversation();

        // Vérifier que l'utilisateur a accès à la conversation
        if ($conversation->getParent() !== $user && !$conversation->getTeamMembers()->contains($user)) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }

        if (!$message->hasAttachment()) {
            throw $this->createNotFoundException('Aucun fichier joint à ce message');
        }

        $filePath = $this->getParameter('kernel.project_dir').'/public/uploads/messages/'.$message->getAttachmentFilename();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier non trouvé');
        }

        return $this->file($filePath, $message->getAttachmentOriginalName());
    }

    #[Route('/test-upload', name: 'app_messages_test_upload', methods: ['GET'])]
    public function testUpload(): Response
    {
        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/messages/';
        
        $testResults = [
            'directory_exists' => file_exists($uploadDir),
            'directory_writable' => is_writable($uploadDir),
            'directory_readable' => is_readable($uploadDir),
            'directory_path' => $uploadDir,
            'files_in_directory' => file_exists($uploadDir) ? count(scandir($uploadDir)) - 2 : 0, // -2 pour . et ..
        ];

        return new JsonResponse($testResults);
    }

    #[Route('/test-attachments', name: 'app_messages_test_attachments', methods: ['GET'])]
    public function testAttachments(): Response
    {
        $messagesWithAttachments = $this->messageRepository->createQueryBuilder('m')
            ->where('m.attachmentFilename IS NOT NULL')
            ->andWhere('m.attachmentFilename != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($messagesWithAttachments as $message) {
            $result[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'attachmentFilename' => $message->getAttachmentFilename(),
                'attachmentOriginalName' => $message->getAttachmentOriginalName(),
                'attachmentMimeType' => $message->getAttachmentMimeType(),
                'hasAttachment' => $message->hasAttachment(),
                'sender' => $message->getSender()->getName() . ' ' . $message->getSender()->getLastname(),
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse([
            'total_messages_with_attachments' => count($result),
            'messages' => $result
        ]);
    }
} 