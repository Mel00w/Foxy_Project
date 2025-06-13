<?php

namespace App\Controller;

use App\Entity\Shift;
use App\Repository\UserRepository;
use App\Repository\ShiftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AddShiftController extends AbstractController
{
    #[Route('/planning/add-shift', name: 'app_planning_add_shift', methods: ['POST'])]
    public function addShift(
        Request $request,
        UserRepository $userRepo,
        ShiftRepository $shiftRepo,
        EntityManagerInterface $em
    ): Response {
        $educateurId = $request->request->get('educateur_id');
        $date = $request->request->get('date');
        $start = $request->request->get('start_time');
        $end = $request->request->get('end_time');

        // Vérification des champs requis
        if (!$educateurId || !$date || !$start || !$end) {
            $this->addFlash('danger', 'Tous les champs sont requis');
            return $this->redirectToRoute('app_planning', ['view' => 'equipe']);
        }

        $educateur = $userRepo->find($educateurId);
        if (!$educateur) {
            $this->addFlash('danger', 'Éducateur non trouvé');
            return $this->redirectToRoute('app_planning', ['view' => 'equipe']);
        }

        // Recherche d'un shift existant
        $shift = $shiftRepo->findOneBy([
            'user' => $educateur,
            'date' => new \DateTime($date)
        ]);

        if (!$shift) {
            $shift = new Shift();
            $shift->setUser($educateur);
            $shift->setDate(new \DateTime($date));
        }

        // Mise à jour des horaires
        $startTime = \DateTime::createFromFormat('H:i', $start);
        $endTime = \DateTime::createFromFormat('H:i', $end);

        if (!$startTime || !$endTime) {
            $this->addFlash('danger', 'Format des heures invalide (attendu : HH:MM)');
            return $this->redirectToRoute('app_planning', [
                'week' => (new \DateTime($date))->format('o-\WW'),
                'selectedDay' => $date,
                'view' => 'equipe'
            ]);
        }

        $shift->setStartTime($startTime);
        $shift->setEndTime($endTime);

        $em->persist($shift);
        $em->flush();

        $this->addFlash('success', 'Horaires enregistrés avec succès');
        return $this->redirectToRoute('app_planning', [
            'week' => (new \DateTime($date))->format('o-\WW'),
            'selectedDay' => $date,
            'view' => 'equipe'
        ]);
    }
} 