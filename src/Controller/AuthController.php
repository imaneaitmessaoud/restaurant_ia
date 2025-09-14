<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\RoleEnum;
use App\Enum\StatutUserEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\JWTService;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    #[Route('/signup', name: 'api_admin_signup', methods: ['POST'])]
    public function signup(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $nom = $data['nom'] ?? null;
        $email = $data['email'] ?? null;
        $telephone = $data['telephone'] ?? null;
        $password = $data['password'] ?? null;

        if (!$nom || !$email || !$telephone || !$password) {
            return new JsonResponse(['error' => 'Données manquantes'], 400);
        }

        if ($userRepository->findByEmail($email)) {
            return new JsonResponse(['error' => 'Email déjà utilisé'], 400);
        }

        if ($userRepository->findByTelephone($telephone)) {
            return new JsonResponse(['error' => 'Téléphone déjà utilisé'], 400);
        }

        $user = new User();
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setTelephone($telephone);
        $user->setRole(RoleEnum::ADMIN); // 🚨 seulement ADMIN
        $user->setStatut(StatutUserEnum::ACTIF);

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Admin créé avec succès',
            'user' => $user->getFullInfo(),
        ], 201);
    }

    #[Route('/signin', name: 'api_admin_signin', methods: ['POST'])]
    public function signin(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTService $jwtService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return new JsonResponse(['error' => 'Email et mot de passe requis'], 400);
        }

        $user = $userRepository->findByEmail($email);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        if (!$user->isAdmin()) {
            return new JsonResponse(['error' => 'Accès réservé aux administrateurs'], 403);
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'Mot de passe incorrect'], 401);
        }

        // ✅ Générer un token avec notre service
        $token = $jwtService->generateToken([
            'user_id' => $user->getId(),
            'email'   => $user->getEmail(),
            'role'    => $user->getRole(),
        ]);

        return new JsonResponse([
            'message' => 'Connexion ADMIN réussie',
            'token' => $token,
            'user' => $user->getFullInfo(),
        ]);
    }

    #[Route('/me', name: 'api_admin_me', methods: ['GET'])]
    public function me(Request $request, JWTService $jwtService): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['error' => 'Token manquant'], 401);
        }

        $token = substr($authHeader, 7);
        $decoded = $jwtService->decodeToken($token);

        if (!$decoded) {
            return new JsonResponse(['error' => 'Token invalide ou expiré'], 401);
        }

        return new JsonResponse([
            'message' => 'Token valide ✅',
            'data' => $decoded,
        ]);
    }

}