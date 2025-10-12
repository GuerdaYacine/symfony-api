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
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;

final class EditorController extends AbstractController
{
    #[Route('/api/v1/editors', name: 'getEditors', methods: ['GET'])]
    #[OA\Tag(name: "Editors")]
    #[OA\Response(
        response: 200,
        description: "Retourne tous les éditeurs avec pagination",
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Editor::class, groups: ['editor:read']))
        )
    )]
    #[OA\Parameter(name: "page", in: "query", description: "Numéro de page", required: false, schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", in: "query", description: "Nombre d'éditeurs par page", required: false, schema: new OA\Schema(type: "integer", default: 3))]
    public function getEditors(
        EditorRepository $editorRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $cacheIdentifier = "getAllEditors-" . $page . "-" . $limit;

        $jsonEditors = $cachePool->get($cacheIdentifier, function (ItemInterface $item) use ($editorRepository, $page, $limit, $serializer) {
            $item->tag("editorCache");
            $editors = $editorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($editors, 'json', ['groups' => ['editor:read']]);
        });

        return new JsonResponse($jsonEditors, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/editors/{id}', name: 'getEditor', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[OA\Tag(name: "Editors")]
    #[OA\Response(
        response: 200,
        description: "Retourne un éditeur spécifique",
        content: new OA\JsonContent(ref: new Model(type: Editor::class, groups: ['editor:read']))
    )]
    #[OA\Response(response: 404, description: "Éditeur introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID de l'éditeur", required: true, schema: new OA\Schema(type: "integer"))]
    public function getEditor(int $id, EditorRepository $editorRepository, SerializerInterface $serializer): JsonResponse
    {
        $editor = $editorRepository->find($id);
        if (!$editor) {
            return $this->json(['error' => 'Éditeur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $jsonEditor = $serializer->serialize($editor, 'json', ['groups' => ['editor:read']]);
        return new JsonResponse($jsonEditor, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/editors/{id}', name: 'editEditor', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Editors")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour mettre à jour un éditeur",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "name", type: "string", description: "Nom de l'éditeur", example: "Ubisoft"),
                new OA\Property(property: "country", type: "string", description: "Pays de l'éditeur", example: "France")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Éditeur mis à jour avec succès", content: new OA\JsonContent(ref: new Model(type: Editor::class, groups: ['editor:write'])))]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[OA\Response(response: 404, description: "Éditeur introuvable")]
    #[Security(name: "Bearer")]
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

        return $this->json($updatedEditor, Response::HTTP_OK, ["Location" => $location], ['groups' => 'editor:write']);
    }

    #[Route('/api/v1/editors', name: 'createEditor', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Editors")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour créer un nouvel éditeur",
        content: new OA\JsonContent(
            required: ["name", "country"],
            properties: [
                new OA\Property(property: "name", type: "string", description: "Nom de l'éditeur", example: "Ubisoft"),
                new OA\Property(property: "country", type: "string", description: "Pays de l'éditeur", example: "France")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Éditeur créé avec succès", content: new OA\JsonContent(ref: new Model(type: Editor::class, groups: ['editor:write'])))]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[Security(name: "Bearer")]
    public function createEditor(
        Request $request,
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $editor = $serializer->deserialize($request->getContent(), Editor::class, 'json');

        $errors = $validator->validate($editor);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->persist($editor);
        $em->flush();

        $location = $urlGenerator->generate('getEditor', ['id' => $editor->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($editor, Response::HTTP_CREATED, ["Location" => $location], ['groups' => 'editor:write']);
    }

    #[Route('/api/v1/editors/{id}', name: 'deleteEditor', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Editors")]
    #[OA\Response(response: 200, description: "Éditeur supprimé avec succès", content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: "Éditeur introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID de l'éditeur à supprimer", required: true, schema: new OA\Schema(type: "integer"))]
    #[Security(name: "Bearer")]
    public function deleteEditor(int $id, EntityManager $em, EditorRepository $editorRepository): JsonResponse
    {
        $editor = $editorRepository->find($id);
        if (!$editor) {
            return $this->json(['error' => 'Éditeur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($editor);
        $em->flush();

        return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    }
}
