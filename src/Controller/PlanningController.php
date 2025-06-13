<?php

namespace App\Controller;

use App\Repository\ChildRepository;
use App\Repository\PresenceRepository;
use App\Repository\UserRepository;
use App\Repository\ShiftRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'app_planning')]
    public function index(
        Request $request, 
        ChildRepository $childRepo, 
        PresenceRepository $presenceRepo,
        UserRepository $userRepo,
        ShiftRepository $shiftRepo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $roles = $this->getUser() ? $this->getUser()->getRoles() : [];

        // Vue active (enfant ou equipe)
        $view = $request->query->get('view', 'enfant');

        // Semaine sélectionnée (format: 2024-W21)
        $weekParam = $request->query->get('week');
        $now = new \DateTime();
        if ($weekParam) {
            $date = new \DateTime();
            $date->setISODate(substr($weekParam, 0, 4), substr($weekParam, 6, 2));
        } else {
            $date = clone $now;
        }
        $startOfWeek = (clone $date)->modify('monday this week');
        $weekNumber = $startOfWeek->format('W');
        $year = $startOfWeek->format('Y');

        // Générer les jours de la semaine (lundi à vendredi)
        $weekDays = [];
        for ($i = 0; $i < 5; $i++) {
            $day = (clone $startOfWeek)->modify("+$i days");
            $weekDays[] = [
                'name' => $day->format('l'),
                'date' => $day->format('Y-m-d'),
                'display' => $day->format('d M'),
                'isToday' => $day->format('Y-m-d') === $now->format('Y-m-d'),
            ];
        }

        // Jour sélectionné (par défaut lundi)
        $selectedDay = $request->query->get('selectedDay');
        if (!$selectedDay && count($weekDays) > 0) {
            $selectedDay = $weekDays[0]['date'];
        }

        // Données pour la vue enfants
        $enfantsData = [];
        // Récupérer les enfants en fonction du rôle de l'utilisateur
        if (in_array('ROLE_ADMIN', $roles)) {
            $enfants = $childRepo->findAll(); // Les admins voient tous les enfants
        } else {
            // Pour les éducateurs, récupérer les enfants associés via ChildUser
            $enfants = [];
            $childUsers = $this->getUser()->getChildUsers();
            foreach ($childUsers as $childUser) {
                $enfants[] = $childUser->getChild();
            }
        }
        
        foreach ($enfants as $enfant) {
            $presences = $presenceRepo->findByChildAndWeek($enfant, $year, $weekNumber);
            $jours = [];
            foreach ($weekDays as $day) {
                $presence = null;
                foreach ($presences as $p) {
                    if ($p->getDate()->format('Y-m-d') === $day['date']) {
                        $presence = $p;
                        break;
                    }
                }
                $jours[] = [
                    'present' => $presence !== null && $presence->isPresent(),
                    'start' => $presence && $presence->getArrivalTime() ? $presence->getArrivalTime()->format('H:i') : null,
                    'end' => $presence && $presence->getExitTime() ? $presence->getExitTime()->format('H:i') : null,
                    'date' => $day['date'],
                ];
            }
            $enfantData = [
                'id' => $enfant->getId(),
                'name' => $enfant->getName(),
                'lastname' => $enfant->getLastname(),
                'jours' => $jours,
                'picture' => $enfant->getPicture(),
                'childUsers' => $enfant->getChildUsers()->map(function($childUser) {
                    return [
                        'user' => $childUser->getUser()
                    ];
                })->toArray()
            ];
            $enfantsData[] = $enfantData;
        }

        // Récupérer tous les éducateurs (pour toutes les vues)
        $educateurs = $userRepo->findByRole('ROLE_EDUCATOR');
        if (empty($educateurs)) {
            $this->addFlash('warning', 'Aucun éducateur trouvé. Vérifiez que les utilisateurs ont bien le rôle ROLE_EDUCATOR.');
        }

        // Données pour la vue équipe
        $educateursData = [];
        if ($view === 'equipe') {
            foreach ($educateurs as $educateur) {
                $shifts = $shiftRepo->findByUserAndWeek($educateur, $year, $weekNumber);
                $educateursData[] = [
                    'id' => $educateur->getId(),
                    'nom' => $educateur->getLastname(),
                    'prenom' => $educateur->getName(),
                    'shifts' => $shifts,
                    'picture' => $educateur->getPicture(),
                ];
            }
        }

        return $this->render('planning/planning.html.twig', [
            'roles' => $roles,
            'weekDays' => $weekDays,
            'enfants' => $enfantsData,  // Passer les données formatées des enfants
            'educateurs' => $educateurs,
            'educateursData' => $educateursData,
            'week' => $weekNumber,
            'year' => $year,
            'selectedDay' => $selectedDay,
            'view' => $view,
        ]);
    }
}