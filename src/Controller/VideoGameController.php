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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class VideoGameController extends AbstractController
{
    #[Route('/api/v1/video_games', name: 'getVideoGames', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\êtes pas autorisés à acceder à cette ressource')]
    public function getVideoGames(VideoGameRepository $videoGameRepository, SerializerInterface $serializer): JsonResponse
    {
        $videoGames = $videoGameRepository->findAll();
        $jsonVideoGames = $serializer->serialize($videoGames, 'json', ['groups' => ['videogame:read']]);
        return new JsonResponse($jsonVideoGames, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/video_games/{id}', name: 'getVideoGame', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function getVideoGame(int $id, VideoGameRepository $videoGameRepository, SerializerInterface $serializer): JsonResponse
    {
        $videoGame = $videoGameRepository->find($id);
        $jsonVideoGame = $serializer->serialize($videoGame, 'json', ['groups' => ['videogame:read']]);
        return new JsonResponse($jsonVideoGame, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/video_games/{id}', name: 'editVideoGame', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
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
        if(!$editor){
            return $this->json(['error' => 'L\'éditeur est introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $videoGame->setEditor($editor ?? $videoGame->getEditor());

        $categories = $categoryRepository->findBy(['id' => $data['categories']]);
        if(count($categories) !== count($data['categories'])){
            return $this->json(['error' => 'Une ou plusieurs catégories introuvables.'], Response::HTTP_NOT_FOUND);
        }
        foreach($categories as $category){
            $videoGame->addCategory($category ?? $videoGame->getCategories());
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
        public function createVideoGame(
            Request $request,
            EditorRepository $editorRepository,
            CategoryRepository $categoryRepository,
            EntityManager $em,
            ValidatorInterface $validator,
            UrlGeneratorInterface $urlGenerator
        ): JsonResponse {
            $data = json_decode($request->getContent(), true);

            $videoGame = new VideoGame();
            $videoGame->setTitle($data['title']);
            $videoGame->setReleaseDate(new \DateTime($data['releaseDate']));
            $videoGame->setDescription($data['description']);

            $editor = $editorRepository->find($data['editor']);

            if(!$editor){
                return $this->json(['error' => 'Editeur introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $videoGame->setEditor($editor);

            $categories = $categoryRepository->findBy(['id' => $data['categories']]);

            if (count($categories) !== count($data['categories'] ?? [])) {
                return $this->json(['error' => 'Une ou plusieurs catégories introuvables.'], Response::HTTP_NOT_FOUND);
            }

            foreach ($categories as $category) {
                $videoGame->addCategory($category);
            }

            $errors = $validator->validate($videoGame);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }
        
            $em->persist($videoGame);
            $em->flush();

            $location = $urlGenerator->generate('getVideoGame', ['id' => $videoGame->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return $this->json($videoGame, Response::HTTP_CREATED, ["Location" => $location], ['groups' => 'videogame:read']);
        }

    #[Route('/api/v1/video_games/{id}', name: 'deleteVideoGame', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function deleteVideoGame(int $id, EntityManager $em, VideoGameRepository $videoGameRepository): JsonResponse
    {
        $videoGame = $videoGameRepository->find($id);
        if (!$videoGame) {
            return $this->json(['error' => 'Jeu vidéo introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($videoGame);
        $em->flush();

        return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    }
}