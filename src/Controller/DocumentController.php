<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Child;
use App\Repository\DocumentRepository;
use App\Repository\ChildRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/documents')]
class DocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentRepository $documentRepository,
        private ChildRepository $childRepository
    ) {}

    #[Route('/', name: 'app_documents_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $roles = $user ? $user->getRoles() : [];
        
        // Les documents généraux sont toujours visibles
        $generalDocuments = $this->documentRepository->findByType('general');
        
        // Pour les documents familiaux, filtrer selon le rôle
        $familyDocuments = [];
        $children = [];
        
        if (in_array('ROLE_PARENT', $roles)) {
            // Pour un parent, ne montrer que les documents de ses enfants
            $children = $this->childRepository->findByParent($user);
            foreach ($children as $child) {
                $documents = $this->documentRepository->findByChild($child);
                if (!empty($documents)) {
                    $familyDocuments[] = [
                        'name' => $child->getName() . ' ' . $child->getLastname(),
                        'documents' => $documents
                    ];
                }
            }
        } else {
            // Pour un admin, montrer tous les documents familiaux
            $children = $this->childRepository->findAll();
            foreach ($children as $child) {
                $documents = $this->documentRepository->findByChild($child);
                if (!empty($documents)) {
                    $familyDocuments[] = [
                        'name' => $child->getName() . ' ' . $child->getLastname(),
                        'documents' => $documents
                    ];
                }
            }
        }

        return $this->render('document/index.html.twig', [
            'generalDocuments' => $generalDocuments,
            'familyDocuments' => $familyDocuments,
            'roles' => $roles,
            'children' => in_array('ROLE_ADMIN', $roles) ? $children : [], // N'envoyer children que pour les admins
        ]);
    }

    #[Route('/add', name: 'app_documents_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function add(Request $request, SluggerInterface $slugger): Response
    {
        $file = $request->files->get('document');
        $type = $request->request->get('type');
        $childId = $request->request->get('child_id');

        if (!$file) {
            $this->addFlash('error', 'Aucun fichier n\'a été uploadé.');
            return $this->redirectToRoute('app_documents_index');
        }

        // Vérification du type de fichier
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', 'Type de fichier non autorisé.');
            return $this->redirectToRoute('app_documents_index');
        }

        // Génération d'un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            // Détermination du dossier de destination
            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/documents/';
            if ($type === 'family' && $childId) {
                $uploadDir .= 'family/'.$childId.'/';
            } else {
                $uploadDir .= 'general/';
            }

            // Création du dossier s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $file->move($uploadDir, $newFilename);

            // Création de l'entité Document
            $document = new Document();
            $document->setFilename($newFilename);
            $document->setOriginalFilename($file->getClientOriginalName());
            $document->setType($type);
            $document->setUploadedBy($this->getUser());

            if ($type === 'family' && $childId) {
                $child = $this->childRepository->find($childId);
                if ($child) {
                    $document->setChild($child);
                }
            }

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le document a été uploadé avec succès.');
        } catch (FileException $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'upload du document.');
        }

        return $this->redirectToRoute('app_documents_index');
    }
} 