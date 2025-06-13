<?php

namespace App\Controller;

use App\Entity\Presence;
use App\Repository\ChildRepository;
use App\Repository\PresenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AddChildWeekController extends AbstractController
{
    #[Route('/planning/add-child-week', name: 'app_planning_add_child_week', methods: ['POST'])]
    public function addChildWeek(
        Request $request,
        ChildRepository $childRepo,
        PresenceRepository $presenceRepo,
        EntityManagerInterface $em
    ): Response {
        $enfantId = $request->request->get('enfant_id');
        $startTimes = $request->request->all('start_time');
        $endTimes = $request->request->all('end_time');
        $absents = $request->request->all('absent');

        // Vérification des champs requis
        if (!$enfantId) {
            $this->addFlash('danger', 'Veuillez sélectionner un enfant');
            return $this->redirectToRoute('app_planning');
        }

        $enfant = $childRepo->find($enfantId);
        if (!$enfant) {
            $this->addFlash('danger', 'Enfant non trouvé');
            return $this->redirectToRoute('app_planning');
        }

        // Traitement pour chaque jour de la semaine
        foreach ($startTimes as $date => $startTime) {
            $isAbsent = isset($absents[$date]) && $absents[$date] === "1";
            
            // Recherche d'une présence existante
            $presence = $presenceRepo->findOneBy([
                'child' => $enfant,
                'date' => new \DateTime($date)
            ]);

            if (!$presence) {
                $presence = new Presence();
                $presence->setChild($enfant);
                $presence->setDate(new \DateTime($date));
            }

            // Gestion de l'absence ou des heures
            $presence->setIsPresent(!$isAbsent);

            if ($isAbsent) {
                $presence->setArrivalTime(null);
                $presence->setExitTime(null);
            } else {
                $arrivalTime = \DateTime::createFromFormat('H:i', $startTime);
                $exitTime = \DateTime::createFromFormat('H:i', $endTimes[$date]);

                if (!$arrivalTime || !$exitTime) {
                    $this->addFlash('danger', 'Format des heures invalide pour le ' . $date);
                    continue;
                }

                $presence->setArrivalTime($arrivalTime);
                $presence->setExitTime($exitTime);
            }

            $em->persist($presence);
        }

        try {
            $em->flush();
            $this->addFlash('success', 'Horaires hebdomadaires enregistrés avec succès');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Une erreur est survenue l\'enregistrement');
        }

        // Redirection vers la page de planning avec la semaine en cours
        $currentDate = new \DateTime(array_key_first($startTimes));
        return $this->redirectToRoute('app_planning', [
            'week' => $currentDate->format('o-\WW'),
            'selectedDay' => array_key_first($startTimes)
        ]);
    }
} 