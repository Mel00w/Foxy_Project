<?php

namespace App\Controller;

use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

class HomeController extends AbstractController
{
    public function __construct(
        private MessageRepository $messageRepository
    ) {}

    #[Route('/', name: 'home')]
    public function index(Security $security): Response
    {
        // Si l'utilisateur n'est pas connecté, rediriger vers la page de login
        if (!$security->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $unreadCount = $this->messageRepository->countUnreadMessages($security->getUser());

        return $this->render('home/home.html.twig', [
            'unread_count' => $unreadCount
        ]);
    }
}