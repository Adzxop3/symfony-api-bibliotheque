<?php
// src/Controller/LivreController.php

namespace App\Controller;

use App\Entity\Livre;
use App\Repository\AuteurRepository;
use App\Repository\CategorieRepository;
use App\Repository\LivreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/livres')]
class LivreController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LivreRepository $livreRepository,
        private AuteurRepository $auteurRepository,
        private CategorieRepository $categorieRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', methods: ['GET'])]
    public function lister(): JsonResponse
    {
        $livres = $this->livreRepository->findAll();
        return $this->json($livres, Response::HTTP_OK, [], ['groups' => 'livre:read']);
    }

    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function details(Livre $livre): JsonResponse
    {
        return $this->json($livre, Response::HTTP_OK, [], ['groups' => 'livre:read']);
    }

    #[Route('', methods: ['POST'])]
    public function creer(Request $request): JsonResponse
    {
        $jsonRecu = $request->getContent();
        $data = json_decode($jsonRecu, true);

        $livre = $this->serializer->deserialize($jsonRecu, Livre::class, 'json');

        // Associer l'auteur et la catégorie
        $auteur = $this->auteurRepository->find($data['auteurId'] ?? -1);
        $categorie = $this->categorieRepository->find($data['categorieId'] ?? -1);

        if (!$auteur) {
            return $this->json(['message' => 'Auteur non trouvé.'], Response::HTTP_BAD_REQUEST);
        }
        if (!$categorie) {
            return $this->json(['message' => 'Catégorie non trouvée.'], Response::HTTP_BAD_REQUEST);
        }

        $livre->setAuteur($auteur);
        $livre->setCategorie($categorie);
        $livre->setDisponible(true);

        $errors = $this->validator->validate($livre);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($livre);
        $this->em->flush();

        return $this->json($livre, Response::HTTP_CREATED, [], ['groups' => 'livre:read']);
    }

    #[Route('/{id<\d+>}', methods: ['PUT'])]
    public function modifier(Livre $livre, Request $request): JsonResponse
    {
        $this->serializer->deserialize(
            $request->getContent(),
            Livre::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $livre]
        );

        $errors = $this->validator->validate($livre);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();
        return $this->json($livre, Response::HTTP_OK, [], ['groups' => 'livre:read']);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function supprimer(Livre $livre): JsonResponse
    {
        $this->em->remove($livre);
        $this->em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}