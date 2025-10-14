<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Editor;
use App\Entity\VideoGame;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class VideoGameFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $videoGames = [
            ['title' => 'Assassin\'s Creed', 'description' => 'Un jeu d\'action et d\'aventure.'],
            ['title' => 'Call of Duty', 'description' => 'FPS militaire intense.'],
            ['title' => 'FIFA 23', 'description' => 'Simulation de football réaliste.'],
            ['title' => 'The Witcher 3', 'description' => 'RPG avec un monde ouvert gigantesque.'],
            ['title' => 'Minecraft', 'description' => 'Jeu de construction et de survie en sandbox.'],
            ['title' => 'League of Legends', 'description' => 'MOBA stratégique en équipe.'],
            ['title' => 'Cyberpunk 2077', 'description' => 'RPG futuriste dans une ville ouverte.'],
            ['title' => 'Red Dead Redemption 2', 'description' => 'Aventure et western en monde ouvert.'],
            ['title' => 'Overwatch', 'description' => 'FPS compétitif par équipe.'],
            ['title' => 'Resident Evil Village', 'description' => 'Survie et horreur.'],
        ];

        foreach ($videoGames as $i => $gameData) {
            $videoGame = new VideoGame();
            $videoGame->setTitle($gameData['title']);
            $videoGame->setReleaseDate($faker->dateTimeBetween('-1 week', '+2 week'));
            $videoGame->setDescription($gameData['description']);

            $videoGame->setEditor(
                $this->getReference('editor_' . $faker->numberBetween(0, 9), Editor::class)
            );

            // 1 à 3 catégories aléatoires
            $categoriesCount = $faker->numberBetween(1, 3);
            $usedIndexes = [];
            for ($j = 0; $j < $categoriesCount; $j++) {
                do {
                    $catIndex = $faker->numberBetween(0, 15);
                } while (in_array($catIndex, $usedIndexes));
                $usedIndexes[] = $catIndex;

                $videoGame->addCategory($this->getReference('category_' . $catIndex, Category::class));
            }

            $manager->persist($videoGame);
            $this->addReference('videoGame_' . $i, $videoGame);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            EditorFixtures::class,
            CategoryFixtures::class,
        ];
    }
}
