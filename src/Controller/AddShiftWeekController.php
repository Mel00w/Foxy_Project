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

class AddShiftWeekController extends AbstractController
{
    #[Route('/planning/add-shift-week', name: 'app_planning_add_shift_week', methods: ['POST'])]
    public function addShiftWeek(
        Request $request,
        UserRepository $userRepo,
        ShiftRepository $shiftRepo,
        EntityManagerInterface $em
    ): Response {
        $educateurId = $request->request->get('educateur_id');
        $startTimes = $request->request->all('start_time');
        $endTimes = $request->request->all('end_time');

        // Vérification des champs requis
        if (!$educateurId) {
            $this->addFlash('danger', 'Veuillez sélectionner un éducateur');
            return $this->redirectToRoute('app_planning', ['view' => 'equipe']);
        }

        $educateur = $userRepo->find($educateurId);
        if (!$educateur) {
            $this->addFlash('danger', 'Éducateur non trouvé');
            return $this->redirectToRoute('app_planning', ['view' => 'equipe']);
        }

        // Traitement pour chaque jour de la semaine
        foreach ($startTimes as $date => $startTime) {
            if (!$startTime || !isset($endTimes[$date]) || !$endTimes[$date]) {
                continue; // Ignorer les jours sans horaires complets
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
            $startDateTime = \DateTime::createFromFormat('H:i', $startTime);
            $endDateTime = \DateTime::createFromFormat('H:i', $endTimes[$date]);

            if (!$startDateTime || !$endDateTime) {
                $this->addFlash('danger', 'Format des heures invalide pour le ' . $date);
                continue;
            }

            $shift->setStartTime($startDateTime);
            $shift->setEndTime($endDateTime);

            $em->persist($shift);
        }

        try {
            $em->flush();
            $this->addFlash('success', 'Horaires hebdomadaires enregistrés avec succès');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Une erreur est survenue lors de l\'enregistrement');
        }

        // Redirection vers la page de planning avec la semaine en cours
        $currentDate = new \DateTime(array_key_first($startTimes));
        return $this->redirectToRoute('app_planning', [
            'week' => $currentDate->format('o-\WW'),
            'selectedDay' => array_key_first($startTimes),
            'view' => 'equipe'
        ]);
    }

    #[Route('/planning/get-shifts/{educateurId}', name: 'app_planning_get_shifts', methods: ['GET'])]
    public function getShifts(
        int $educateurId,
        Request $request,
        UserRepository $userRepo,
        ShiftRepository $shiftRepo
    ): Response {
        $weekParam = $request->query->get('week');
        if (!$weekParam) {
            return $this->json(['error' => 'Semaine non spécifiée'], 400);
        }

        $educateur = $userRepo->find($educateurId);
        if (!$educateur) {
            return $this->json(['error' => 'Éducateur non trouvé'], 404);
        }

        $year = substr($weekParam, 0, 4);
        $week = substr($weekParam, 6, 2);
        $shifts = $shiftRepo->findByUserAndWeek($educateur, $year, $week);

        $shiftsData = array_map(function($shift) {
            return [
                'date' => $shift->getDate()->format('Y-m-d'),
                'start_time' => $shift->getStartTime()->format('H:i'),
                'end_time' => $shift->getEndTime()->format('H:i')
            ];
        }, $shifts);

        return $this->json($shiftsData);
    }
} 