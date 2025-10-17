<?php
// src/Controller/EmpruntController.php

namespace App\Controller;

use App\Entity\Emprunt;
use App\Repository\EmpruntRepository;
use App\Repository\LivreRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api')]
class EmpruntController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmpruntRepository $empruntRepository,
        private LivreRepository $livreRepository,
        private UtilisateurRepository $utilisateurRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Route pour faire une demande d'emprunt de livre.
     */
    #[Route('/emprunts', methods: ['POST'])]
    public function demandeEmprunt(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $livreId = $data['livreId'] ?? null;
        $utilisateurId = $data['utilisateurId'] ?? null;

        if (!$livreId || !$utilisateurId) {
            return $this->json(['message' => 'Les identifiants du livre et de l\'utilisateur sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        $livre = $this->livreRepository->find($livreId);
        $utilisateur = $this->utilisateurRepository->find($utilisateurId);

        if (!$livre) {
            return $this->json(['message' => 'Livre non trouvé.'], Response::HTTP_NOT_FOUND);
        }
        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // Règle 1 : Le livre doit être disponible
        if (!$livre->isDisponible()) {
            $this->logger->warning('Tentative d\'emprunt d\'un livre non disponible', ['livreId' => $livreId]);
            return $this->json(['message' => 'Ce livre est déjà emprunté.'], Response::HTTP_CONFLICT);
        }

        // Règle 2 : L'utilisateur ne peut pas avoir plus de 4 emprunts en cours
        $empruntsEnCours = $this->empruntRepository->count(['utilisateur' => $utilisateur, 'dateRetour' => null]);
        if ($empruntsEnCours >= 4) {
            $this->logger->warning('Tentative d\'emprunt au-delà de la limite', ['utilisateurId' => $utilisateurId]);
            return $this->json(['message' => 'Limite de 4 emprunts atteinte.'], Response::HTTP_BAD_REQUEST);
        }

        // Création de l'emprunt
        $emprunt = new Emprunt();
        $emprunt->setLivre($livre);
        $emprunt->setUtilisateur($utilisateur);
        $emprunt->setDateEmprunt(new \DateTime());
        
        // Mettre à jour la disponibilité du livre
        $livre->setDisponible(false);

        $this->em->persist($emprunt);
        $this->em->flush();

        return $this->json([
            'message' => 'Livre emprunté avec succès.',
            'emprunt' => $emprunt
        ], Response::HTTP_CREATED, [], ['groups' => 'emprunt:read']);
    }

    /**
     * Route pour rendre un livre.
     */
    #[Route('/emprunts/{id<\d+>}/retour', methods: ['PUT'])]
    public function rendreLivre(int $id): JsonResponse
    {
        $emprunt = $this->empruntRepository->findOneBy(['id' => $id, 'dateRetour' => null]);

        if (!$emprunt) {
            return $this->json(['message' => 'Emprunt non trouvé ou livre déjà retourné.'], Response::HTTP_NOT_FOUND);
        }

        $emprunt->setDateRetour(new \DateTime());
        $livre = $emprunt->getLivre();
        $livre->setDisponible(true);

        $this->em->flush();

        return $this->json(['message' => 'Livre retourné avec succès.'], Response::HTTP_OK);
    }

    /**
     * Route pour savoir pour un utilisateur combien d’emprunts il a en cours.
     */
    #[Route('/utilisateurs/{id<\d+>}/emprunts', methods: ['GET'])]
    public function empruntsParUtilisateur(int $id): JsonResponse
    {
        if (!$this->utilisateurRepository->find($id)) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $emprunts = $this->empruntRepository->findBy(
            ['utilisateur' => $id, 'dateRetour' => null],
            ['dateEmprunt' => 'ASC'] // Tri par date de la plus ancienne à la plus récente
        );

        return $this->json($emprunts, Response::HTTP_OK, [], ['groups' => 'emprunt:read']);
    }

    /**
     * Route pour récupérer tous les livres d’un auteur emprunté entre 2 dates définies.
     * NOTE: Cette route pourrait aussi logiquement se trouver dans AuteurController.
     */
    #[Route('/auteurs/{id<\d+>}/livres-empruntes', methods: ['GET'])]
    public function livresEmpruntesParAuteur(int $id, Request $request): JsonResponse
    {
        $dateDebutStr = $request->query->get('date_debut');
        $dateFinStr = $request->query->get('date_fin');
        
        if (!$dateDebutStr || !$dateFinStr) {
            return $this->json(['message' => 'Les paramètres date_debut et date_fin sont requis (format: YYYY-MM-DD).'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $dateDebut = new \DateTime($dateDebutStr);
            $dateFin = new \DateTime($dateFinStr . ' 23:59:59'); // Inclure toute la journée de fin
        } catch (\Exception $e) {
            return $this->json(['message' => 'Format de date invalide. Utilisez YYYY-MM-DD.'], Response::HTTP_BAD_REQUEST);
        }
        
        // Note: la méthode findLivresEmpruntesParAuteurEntreDates doit être créée dans LivreRepository
        $livres = $this->livreRepository->findLivresEmpruntesParAuteurEntreDates($id, $dateDebut, $dateFin);
        
        return $this->json($livres, Response::HTTP_OK, [], ['groups' => 'livre:read']);
    }
}