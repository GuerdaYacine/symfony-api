<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Editor;
use App\Entity\VideoGame;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // --- Categories ---
        $categoriesData = ['Action', 'Aventure', 'RPG', 'FPS', 'Stratégie'];
        $categories = [];
        foreach ($categoriesData as $name) {
            $category = new Category();
            $category->setName($name);
            $manager->persist($category);
            $categories[] = $category;
        }

        // --- Editors ---
        $editorsData = [
            ['name' => 'Ubisoft', 'country' => 'France'],
            ['name' => 'Nintendo', 'country' => 'Japon'],
            ['name' => 'EA', 'country' => 'USA'],
        ];
        $editors = [];
        foreach ($editorsData as $data) {
            $editor = new Editor();
            $editor->setName($data['name']);
            $editor->setCountry($data['country']);
            $manager->persist($editor);
            $editors[] = $editor;
        }

        // --- VideoGames ---
        $gamesData = [
            ['title' => 'Assassin\'s Creed', 'release' => '2007-11-13', 'desc' => 'Un jeu d\'action et d\'aventure.'],
            ['title' => 'Zelda: Breath of the Wild', 'release' => '2017-03-03', 'desc' => 'Un RPG épique dans un monde ouvert.'],
            ['title' => 'FIFA 21', 'release' => '2020-10-09', 'desc' => 'Simulation de football réaliste.'],
            ['title' => 'Call of Duty', 'release' => '2003-10-29', 'desc' => 'FPS populaire et intense.'],
            ['title' => 'Civilization VI', 'release' => '2016-10-21', 'desc' => 'Jeu de stratégie au tour par tour.'],
        ];

        foreach ($gamesData as $index => $data) {
            $game = new VideoGame();
            $game->setTitle($data['title']);
            $game->setReleaseDate(new \DateTime($data['release']));
            $game->setDescription($data['desc']);

            // Assign an editor (cycle through editors)
            $game->setEditor($editors[$index % count($editors)]);

            // Assign 1-2 random categories
            $game->addCategory($categories[$index % count($categories)]);
            if ($index % 2 === 0 && count($categories) > 1) {
                $game->addCategory($categories[($index + 1) % count($categories)]);
            }

            $manager->persist($game);
        }

        $manager->flush();
    }
}
