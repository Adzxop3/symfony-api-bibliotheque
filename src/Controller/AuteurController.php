<?php
// src/Controller/AuteurController.php

namespace App\Controller;

use App\Entity\Auteur;
use App\Repository\AuteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/auteurs')]
class AuteurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuteurRepository $auteurRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', methods: ['GET'])]
    public function lister(): JsonResponse
    {
        $auteurs = $this->auteurRepository->findAll();
        return $this->json($auteurs, Response::HTTP_OK, [], ['groups' => 'auteur:read']);
    }

    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function details(Auteur $auteur): JsonResponse
    {
        return $this->json($auteur, Response::HTTP_OK, [], ['groups' => 'auteur:read']);
    }

    #[Route('', methods: ['POST'])]
    public function creer(Request $request): JsonResponse
    {
        $auteur = $this->serializer->deserialize($request->getContent(), Auteur::class, 'json');

        $errors = $this->validator->validate($auteur);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($auteur);
        $this->em->flush();

        return $this->json($auteur, Response::HTTP_CREATED, [], ['groups' => 'auteur:read']);
    }

    #[Route('/{id<\d+>}', methods: ['PUT'])]
    public function modifier(Auteur $auteur, Request $request): JsonResponse
    {
        $this->serializer->deserialize(
            $request->getContent(),
            Auteur::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $auteur]
        );

        $errors = $this->validator->validate($auteur);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();
        return $this->json($auteur, Response::HTTP_OK, [], ['groups' => 'auteur:read']);
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function supprimer(Auteur $auteur): JsonResponse
    {
        $this->em->remove($auteur);
        $this->em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}