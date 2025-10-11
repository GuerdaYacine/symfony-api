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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CategoryController extends AbstractController
{
    #[Route('/api/v1/categories', name: 'getCategories', methods: ['GET'])]
    public function getCategories(CategoryRepository $categoryRepository, SerializerInterface $serializer): JsonResponse
    {
        $categories = $categoryRepository->findAll();
        $jsonCategories = $serializer->serialize($categories, 'json', ['groups' => ['category:read']]);
        return new JsonResponse($jsonCategories, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/categories/{id}', name: 'getCategory', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function getCategory(int $id, CategoryRepository $categoryRepository, SerializerInterface $serializer): JsonResponse
    {
        $category = $categoryRepository->find($id);
        $jsonCategory = $serializer->serialize($category, 'json', ['groups' => ['category:read']]);
        return new JsonResponse($jsonCategory, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/categories/{id}', name: 'editCategory', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
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

        return $this->json(['status' => 'success'], Response::HTTP_OK, ["Location" => $location]);
    }

    #[Route('/api/v1/categories', name: 'createCategory', methods: ['POST'])]
    public function createCategory(
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $category = $serializer->deserialize(
            $request->getContent(),
            Category::class,
            'json',
        );

        $errors = $validator->validate($category);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
    
        $em->persist($category);
        $em->flush();

        $location = $urlGenerator->generate('getCategory', ['id' => $category->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($category, Response::HTTP_CREATED, ["Location" => $location], ['groups' => 'category:read']);
    }

    #[Route('/api/v1/categories/{id}', name: 'deleteCategory', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
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