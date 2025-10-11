<?php

namespace App\Controller;

use App\Entity\Editor;
use App\Repository\EditorRepository;
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

final class EditorController extends AbstractController
{
    #[Route('/api/v1/editors', name: 'getEditors', methods: ['GET'])]
    public function getEditors(EditorRepository $editorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', default: 1);
        $limit = $request->get('limit', default: 2);

        $cacheIdentifier = "getAllEditors-" . $page . "-" . $limit;

        $editors = $cachePool->get($cacheIdentifier,
            function (ItemInterface $item) use ($editorRepository, $page, $limit){
                $item->tag("editorCache");
                return $editorRepository->findAllWithPagination($page, $limit);
            }
            );

        $jsonEditors = $serializer->serialize($editors, 'json', ['groups' => ['editor:read']]);
        return new JsonResponse($jsonEditors, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/editors/{id}', name: 'getEditor', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function getEditor(int $id, EditorRepository $editorRepository, SerializerInterface $serializer): JsonResponse
    {
        $editors = $editorRepository->find($id);
        $jsonEditors = $serializer->serialize($editors, 'json', ['groups' => ['editor:read']]);
        return new JsonResponse($jsonEditors, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/editors/{id}', name: 'editEditor', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function editEditor(
        int $id,
        Request $request,
        EditorRepository $editorRepository,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $editor = $editorRepository->find($id);

        if (!$editor) {
            return $this->json(['error' => 'Éditeur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $updatedEditor = $serializer->deserialize(
            $request->getContent(),
            Editor::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $editor]
        );

        $errors = $validator->validate($updatedEditor);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $location = $urlGenerator->generate('getEditor', ['id' => $updatedEditor->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json(['status' => 'success'], Response::HTTP_OK, ["Location" => $location]);
    }

    #[Route('/api/v1/editors', name: 'createEditor', methods: ['POST'])]
    public function createEditor(
        Request $request,
        EditorRepository $editorRepository,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $editor = $serializer->deserialize(
            $request->getContent(),
            Editor::class,
            'json',
        );

        $errors = $validator->validate($editor);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
    
        $em->persist($editor);
        $em->flush();

        $location = $urlGenerator->generate('getEditor', ['id' => $editor->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($editor, Response::HTTP_CREATED, ["Location" => $location], ['groups' => 'editor:read']);
    }

    #[Route('/api/v1/editors/{id}', name: 'deleteEditor', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function deleteEditor(int $id, EntityManager $em, EditorRepository $editorRepository):JsonResponse
    {
        $editor = $editorRepository->find($id);
        if(!$editor){
            return $this->json(['error' => 'Éditeur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($editor);
        $em->flush();

        return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    }
}
