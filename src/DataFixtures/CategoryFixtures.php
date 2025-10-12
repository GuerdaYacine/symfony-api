<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        $categories = [
            'Action',
            'Aventure',
            'RPG',
            'MMORPG',
            'FPS',
            'TPS',
            'Stratégie',
            'Simulation',
            'Sport',
            'Course',
            'Survie',
            'Sandbox',
            'Puzzle',
            'Horreur',
            'Combat',
        ];

        shuffle($categories);

        for ($i = 0; $i < 10; $i++) {
            $category = new Category();
            $category->setName($categories[$i]);
            $manager->persist($category);

            $this->addReference('category_' . $i, $category);
        }

        $manager->flush();
    }
}
