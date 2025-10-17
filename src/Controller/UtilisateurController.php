<?php
// src/Controller/UtilisateurController.php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/utilisateurs')]
class UtilisateurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UtilisateurRepository $utilisateurRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', methods: ['GET'])]
    public function lister(): JsonResponse
    {
        $utilisateurs = $this->utilisateurRepository->findAll();
        return $this->json($utilisateurs, Response::HTTP_OK, [], ['groups' => 'utilisateur:read']);
    }

    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function details(Utilisateur $utilisateur): JsonResponse
    {
        return $this->json($utilisateur, Response::HTTP_OK, [], ['groups' => 'utilisateur:read']);
    }

    #[Route('', methods: ['POST'])]
    public function creer(Request $request): JsonResponse
    {
        $utilisateur = $this->serializer->deserialize($request->getContent(), Utilisateur::class, 'json');

        $errors = $this->validator->validate($utilisateur);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($utilisateur);
        $this->em->flush();

        return $this->json($utilisateur, Response::HTTP_CREATED, [], ['groups' => 'utilisateur:read']);
    }

    #[Route('/{id<\d+>}', methods: ['PUT'])]
    public function modifier(Utilisateur $utilisateur, Request $request): JsonResponse
    {
        $this->serializer->deserialize(
            $request->getContent(),
            Utilisateur::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $utilisateur]
        );

        $errors = $this->validator->validate($utilisateur);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();
        return $this->json($utilisateur, Response::HTTP_OK, [], ['groups' => 'utilisateur:read']);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function supprimer(Utilisateur $utilisateur): JsonResponse
    {
        $this->em->remove($utilisateur);
        $this->em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}