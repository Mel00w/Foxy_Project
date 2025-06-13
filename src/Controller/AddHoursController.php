<?php

namespace App\Controller;

use App\Repository\ChildRepository;
use App\Repository\PresenceRepository;
use App\Entity\Presence;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class AddHoursController extends AbstractController
{
    #[Route('/planning/add-hours', name: 'app_planning_add_hours', methods: ['POST'])]
    public function addHours(
        Request $request,
        ChildRepository $childRepo,
        PresenceRepository $presenceRepo,
        EntityManagerInterface $em
    ): Response {
        $enfantId = $request->request->get('enfant_id');
        $date = $request->request->get('date');
        $start = $request->request->get('start_time');
        $end = $request->request->get('end_time');
        $isAbsent = $request->request->get('is_absent') === "1";

        // Vérification des champs requis
        if (!$enfantId || !$date || (!$isAbsent && (!$start || !$end))) {
            $this->addFlash('danger', 'Tous les champs sont requis');
            return $this->redirectToRoute('app_planning', ['view' => $request->request->get('view', 'enfant')]);
        }

        $enfant = $childRepo->find($enfantId);
        if (!$enfant) {
            $this->addFlash('danger', 'Enfant non trouvé');
            return $this->redirectToRoute('app_planning', ['view' => $request->request->get('view', 'enfant')]);
        }

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
            $arrivalTime = \DateTime::createFromFormat('H:i', $start);
            $exitTime = \DateTime::createFromFormat('H:i', $end);

            if (!$arrivalTime || !$exitTime) {
                $this->addFlash('danger', 'Format des heures invalide (attendu : HH:MM)');
                return $this->redirectToRoute('app_planning', [
                    'week' => (new \DateTime($date))->format('o-\WW'),
                    'selectedDay' => $date,
                    'view' => $request->request->get('view', 'enfant')
                ]);
            }

            $presence->setArrivalTime($arrivalTime);
            $presence->setExitTime($exitTime);
        }

        $em->persist($presence);
        $em->flush();

        $this->addFlash('success', 'Heures enregistrées avec succès');
        return $this->redirectToRoute('app_planning', [
            'week' => (new \DateTime($date))->format('o-\WW'),
            'selectedDay' => $date,
            'view' => $request->request->get('view', 'enfant')
        ]);
    }
}
