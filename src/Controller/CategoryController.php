<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

final class CategoryController extends AbstractController
{
    #[Route('/api/v1/categories', name: 'getCategories', methods: ['GET'])]
    #[OA\Tag(name: "Categories")]
    #[OA\Response(
        response: 200,
        description: "Retourne toutes les catégories avec pagination",
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Category::class, groups: ['category:read']))
        )
    )]
    #[OA\Parameter(name: "page", in: "query", description: "Numéro de page", required: false, schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", in: "query", description: "Nombre de catégories par page", required: false, schema: new OA\Schema(type: "integer", default: 3))]
    public function getCategories(
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $cacheIdentifier = "getCategories-" . $page . "-" . $limit;

        $jsonCategories = $cachePool->get($cacheIdentifier, function (ItemInterface $item) use ($categoryRepository, $page, $limit, $serializer) {
            $item->tag("categoryCache");
            $categories = $categoryRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($categories, 'json', ['groups' => ['category:read']]);
        });

        return new JsonResponse($jsonCategories, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/categories/{id}', name: 'getCategory', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[OA\Tag(name: "Categories")]
    #[OA\Response(
        response: 200,
        description: "Retourne une catégorie spécifique",
        content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['category:read']))
    )]
    #[OA\Response(response: 404, description: "Catégorie introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID de la catégorie", required: true, schema: new OA\Schema(type: "integer"))]
    public function getCategory(int $id, CategoryRepository $categoryRepository, SerializerInterface $serializer): JsonResponse
    {
        $category = $categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Catégorie introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $jsonCategory = $serializer->serialize($category, 'json', ['groups' => ['category:read']]);
        return new JsonResponse($jsonCategory, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/categories/{id}', name: 'editCategory', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Categories")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour mettre à jour une catégorie",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "name", type: "string", description: "Nom de la catégorie", example: "Action")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Catégorie mise à jour avec succès", content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['category:write'])))]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[OA\Response(response: 404, description: "Catégorie introuvable")]
    #[Security(name: "Bearer")]
    public function editCategory(
        int $id,
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $category = $categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Catégorie introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $updatedCategory = $serializer->deserialize(
            $request->getContent(),
            Category::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $category]
        );

        $errors = $validator->validate($updatedCategory);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $location = $urlGenerator->generate('getCategory', ['id' => $updatedCategory->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($updatedCategory, Response::HTTP_OK, ["Location" => $location], ['groups' => 'category:write']);
    }

    #[Route('/api/v1/categories', name: 'createCategory', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Categories")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour créer une nouvelle catégorie",
        content: new OA\JsonContent(
            required: ["name"],
            properties: [
                new OA\Property(property: "name", type: "string", description: "Nom de la catégorie", example: "Action")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Catégorie créée avec succès", content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['category:write'])))]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[Security(name: "Bearer")]
    public function createCategory(
        Request $request,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $category = $serializer->deserialize($request->getContent(), Category::class, 'json');

        $errors = $validator->validate($category);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->persist($category);
        $em->flush();

        $location = $urlGenerator->generate('getCategory', ['id' => $category->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($category, Response::HTTP_CREATED, ["Location" => $location], ['groups' => 'category:write']);
    }

    #[Route('/api/v1/categories/{id}', name: 'deleteCategory', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Categories")]
    #[OA\Response(response: 200, description: "Catégorie supprimée avec succès", content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: "Catégorie introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID de la catégorie à supprimer", required: true, schema: new OA\Schema(type: "integer"))]
    #[Security(name: "Bearer")]
    public function deleteCategory(int $id, EntityManager $em, CategoryRepository $categoryRepository): JsonResponse
    {
        $category = $categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Catégorie introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($category);
        $em->flush();

        return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    }
}
