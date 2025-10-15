<?php

namespace App\Controller;

use App\Entity\VideoGame;
use App\Repository\CategoryRepository;
use App\Repository\EditorRepository;
use App\Repository\VideoGameRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;

final class VideoGameController extends AbstractController
{
    #[Route('/api/v1/video_games', name: 'getVideoGames', methods: ['GET'])]
    #[OA\Tag(name: "Video Games")]
    #[OA\Response(
        response: 200,
        description: "Retourne tous les jeux vidéo avec pagination",
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: VideoGame::class, groups: ['videogame:read']))
        )
    )]
    #[OA\Parameter(name: "page", in: "query", description: "Numéro de page", required: false, schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", in: "query", description: "Nombre de jeux vidéo par page", required: false, schema: new OA\Schema(type: "integer", default: 3))]
    public function getVideoGames(VideoGameRepository $videoGameRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', default: 1);
        $limit = $request->get('limit', default: 3);

        $cacheIdentifier = "getAllVideoGames-" . $page . "-" . $limit;

        $jsonVideoGames = $cachePool->get($cacheIdentifier, function (ItemInterface $item) use ($videoGameRepository, $page, $limit, $serializer) {
            $item->tag("videoGameCache");
            $videoGames = $videoGameRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($videoGames, 'json', ['groups' => ['videogame:read']]);
        });

        return new JsonResponse($jsonVideoGames, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/video_games/{id}', name: 'getVideoGame', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[OA\Tag(name: "Video Games")]
    #[OA\Response(
        response: 200,
        description: "Retourne un jeu vidéo spécifique",
        content: new OA\JsonContent(ref: new Model(type: VideoGame::class, groups: ['videogame:read']))
    )]
    #[OA\Response(response: 404, description: "Jeu vidéo introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID du jeu vidéo", required: true, schema: new OA\Schema(type: "integer"))]
    public function getVideoGame(int $id, VideoGameRepository $videoGameRepository, SerializerInterface $serializer): JsonResponse
    {
        $videoGame = $videoGameRepository->find($id);
        if (!$videoGame) {
            return $this->json(['error' => 'Jeu vidéo introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $jsonVideoGame = $serializer->serialize($videoGame, 'json', ['groups' => ['videogame:read']]);
        return new JsonResponse($jsonVideoGame, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/video_games/{id}', name: 'editVideoGame', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Video Games")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour mettre à jour un jeu vidéo",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "title", type: "string", description: "Titre du jeu vidéo", example: "The Legend of Zelda: Breath of the Wild"),
                new OA\Property(property: "releaseDate", type: "string", format: "date", description: "Date de sortie du jeu", example: "2017-03-03"),
                new OA\Property(property: "description", type: "string", description: "Description du jeu vidéo", example: "Un jeu d'aventure en monde ouvert"),
                new OA\Property(property: "editor", type: "integer", description: "ID de l'éditeur", example: 1),
                new OA\Property(property: "categories", type: "array", items: new OA\Items(type: "integer"), description: "Liste des IDs des catégories", example: "[1, 2, 3]")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Jeu vidéo mis à jour avec succès", content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[OA\Response(response: 404, description: "Jeu vidéo, éditeur ou catégorie introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID du jeu vidéo à modifier", required: true, schema: new OA\Schema(type: "integer"))]
    #[Security(name: "Bearer")]
    public function editVideoGame(
        int $id,
        Request $request,
        VideoGameRepository $videoGameRepository,
        EditorRepository $editorRepository,
        CategoryRepository $categoryRepository,
        EntityManager $em,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $videoGame = $videoGameRepository->find($id);

        if (!$videoGame) {
            return $this->json(['error' => 'Jeu vidéo introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $videoGame->setTitle($data['title'] ?? $videoGame->getTitle());
        if (!empty($data['releaseDate'])) {
            $videoGame->setReleaseDate(new \DateTime($data['releaseDate']));
        }
        $videoGame->setDescription($data['description'] ?? $videoGame->getDescription());

        $editor = $editorRepository->find($data['editor']);
        if (!$editor) {
            return $this->json(['error' => 'L\'éditeur est introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $videoGame->setEditor($editor ?? $videoGame->getEditor());

        $categories = $categoryRepository->findBy(['id' => $data['categories']]);
        if (count($categories) !== count($data['categories'])) {
            return $this->json(['error' => 'Une ou plusieurs catégories introuvables.'], Response::HTTP_NOT_FOUND);
        }
        foreach ($videoGame->getCategories() as $existingCategory) {
            $videoGame->removeCategory($existingCategory);
        }

        foreach ($categories as $category) {
            $videoGame->addCategory($category);
        }

        $errors = $validator->validate($videoGame);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $location = $urlGenerator->generate('getVideoGame', ['id' => $videoGame->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json(['status' => 'success'], Response::HTTP_OK, ["Location" => $location]);
    }

    #[Route('/api/v1/video_games', name: 'createVideoGame', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Video Games")]
    #[OA\RequestBody(
        required: true,
        description: "Données pour créer un nouveau jeu vidéo avec image",
        content: new OA\MediaType(
            mediaType: "multipart/form-data",
            schema: new OA\Schema(
                required: ["title", "releaseDate", "description", "editor", "categories"],
                properties: [
                    new OA\Property(property: "title", type: "string", example: "The Legend of Zelda"),
                    new OA\Property(property: "releaseDate", type: "string", format: "date", example: "2017-03-03"),
                    new OA\Property(property: "description", type: "string", example: "Un jeu d'aventure"),
                    new OA\Property(property: "editor", type: "integer", example: 1),
                    new OA\Property(property: "categories", type: "array", items: new OA\Items(type: "integer"), example: "[1, 2, 3]"),
                    new OA\Property(property: "image", type: "string", format: "binary", description: "Image du jeu vidéo")
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: "Jeu vidéo créé avec succès")]
    #[OA\Response(response: 400, description: "Données invalides")]
    #[Security(name: "Bearer")]
    public function createVideoGame(
        Request $request,
        EditorRepository $editorRepository,
        CategoryRepository $categoryRepository,
        EntityManager $em,
        ValidatorInterface $validator,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $title = $request->request->get('title');
        $releaseDate = $request->request->get('releaseDate');
        $description = $request->request->get('description');
        $editorId = $request->request->get('editor');
        $categoriesIds = json_decode($request->request->get('categories'), true);

        // 📸 Récupère le fichier image
        $imageFile = $request->files->get('image');

        $videoGame = new VideoGame();
        $videoGame->setTitle($title);
        $videoGame->setReleaseDate(new \DateTime($releaseDate));
        $videoGame->setDescription($description);

        $editor = $editorRepository->find($editorId);
        if (!$editor) {
            return $this->json(['error' => 'Editeur introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $videoGame->setEditor($editor);

        $categories = $categoryRepository->findBy(['id' => $categoriesIds]);
        if (count($categories) !== count($categoriesIds ?? [])) {
            return $this->json(['error' => 'Une ou plusieurs catégories introuvables.'], Response::HTTP_NOT_FOUND);
        }

        foreach ($categories as $category) {
            $videoGame->addCategory($category);
        }

        if ($imageFile) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($imageFile->getMimeType(), $allowedMimeTypes)) {
                return $this->json(['error' => 'Format d\'image non autorisé.'], Response::HTTP_BAD_REQUEST);
            }

            if ($imageFile->getSize() > $maxFileSize) {
                return $this->json(['error' => 'L\'image est trop volumineuse (max 5MB).'], Response::HTTP_BAD_REQUEST);
            }

            $newFilename = uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move($this->getParameter('upload_directory'), $newFilename);
                $videoGame->setCoverImage($newFilename);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Erreur lors de l\'upload de l\'image.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $errors = $validator->validate($videoGame);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $em->persist($videoGame);
        $em->flush();

        $location = $urlGenerator->generate('getVideoGame', ['id' => $videoGame->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($videoGame, Response::HTTP_CREATED, ["Location" => $location], ['groups' => 'videogame:write']);
    }

    #[Route('/api/v1/video_games/{id}', name: 'deleteVideoGame', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas accès à cette ressource.")]
    #[OA\Tag(name: "Video Games")]
    #[OA\Response(response: 200, description: "Jeu vidéo supprimé avec succès", content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: "Jeu vidéo introuvable")]
    #[OA\Parameter(name: 'id', in: 'path', description: "ID du jeu vidéo à supprimer", required: true, schema: new OA\Schema(type: "integer"))]
    #[Security(name: "Bearer")]
    public function deleteVideoGame(int $id, EntityManager $em, VideoGameRepository $videoGameRepository): JsonResponse
    {
        $videoGame = $videoGameRepository->find($id);
        if (!$videoGame) {
            return $this->json(['error' => 'Jeu vidéo introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $imageFile = $videoGame->getCoverImage();

        if ($imageFile) {
            $imagePath = $this->getParameter('upload_directory') . '/' . $imageFile;
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $em->remove($videoGame);
        $em->flush();

        return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    }
}
