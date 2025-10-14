<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;

final class UserController extends AbstractController
{
    #[Route('/api/v1/users', name: 'getUsers', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Users")]
    #[OA\Response(
        response: 200,
        description: "Retourne tous les utilisateurs avec pagination",
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class, groups: ['user:read']))
        )
    )]
    #[OA\Parameter(name: "page", in: "query", description: "Numéro de page", required: false, schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", in: "query", description: "Nombre d'utilisateurs par page", required: false, schema: new OA\Schema(type: "integer", default: 3))]
    #[Security(name: 'Bearer')]
    public function getUsers(
        UserRepository $userRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $cacheIdentifier = "getAllUsers-" . $page . "-" . $limit;

        $jsonUsers = $cachePool->get($cacheIdentifier, function (ItemInterface $item) use ($userRepository, $page, $limit, $serializer) {
            $item->tag("userCache");
            $users = $userRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($users, 'json', ['groups' => ['user:read']]);
        });

        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/users/{id}', name: 'getUser', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Users")]
    #[OA\Response(
        response: 200,
        description: "Retourne un utilisateur spécifique",
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:read']))
    )]
    #[OA\Response(response: 404, description: "Utilisateur introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID de l'utilisateur", required: true, schema: new OA\Schema(type: "integer"))]
    #[Security(name: "Bearer")]
    public function getOneUser(int $id, UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => ['user:read']]);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/users', name: 'createUser', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Users")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour créer un utilisateur",
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(property: "email", type: "string", description: "Email de l'utilisateur", example: "user@example.com"),
                new OA\Property(property: "password", type: "string", description: "Mot de passe", example: "Password123&*"),
                new OA\Property(property: "roles", type: "array", description: "Rôles de l'utilisateur", items: new OA\Items(type: "string"), default: ["ROLE_USER"], example: ["ROLE_USER"]),
                new OA\Property(property: "subscription_to_newsletter", type: "boolean", description: "Abonné à la newsletter", example: true, default: false)
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Utilisateur créé avec succès", content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:write'])))]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[Security(name: "Bearer")]
    public function createUser(
        Request $request,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->persist($user);
        $em->flush();

        $location = $urlGenerator->generate('getUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($user, Response::HTTP_CREATED, ["Location" => $location], ['groups' => 'user:write']);
    }

    #[Route('/api/v1/users/{id}', name: 'editUser', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Users")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour mettre à jour un utilisateur",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "email", type: "string", description: "Nouvel email", example: "new@example.com"),
                new OA\Property(property: "password", type: "string", description: "Nouveau mot de passe", example: "NewP@ssw0rd"),
                new OA\Property(property: "roles", type: "array", description: "Nouveaux rôles", items: new OA\Items(type: "string"), example: ["ROLE_ADMIN"]),
                new OA\Property(property: "subscription_to_newsletter", type: "boolean", description: "Abonné à la newsletter", example: true, default: false)

            ]
        )
    )]
    #[OA\Response(response: 200, description: "Utilisateur mis à jour avec succès", content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:write'])))]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[OA\Response(response: 404, description: "Utilisateur introuvable")]
    #[Security(name: "Bearer")]
    public function editUser(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $updatedUser = $serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $user]
        );

        $data = json_decode($request->getContent(), true);

        if (isset($data['password'])) {
            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }
            $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $location = $urlGenerator->generate('getUser', ['id' => $updatedUser->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($updatedUser, Response::HTTP_OK, ["Location" => $location], ['groups' => 'user:write']);
    }

    #[Route('/api/v1/users/{id}', name: 'deleteUser', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Users")]
    #[OA\Response(response: 200, description: "Utilisateur supprimé avec succès", content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: "Utilisateur introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID de l'utilisateur à supprimer", required: true, schema: new OA\Schema(type: "integer"))]
    #[Security(name: "Bearer")]
    public function deleteUser(int $id, EntityManager $em, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    }
}
