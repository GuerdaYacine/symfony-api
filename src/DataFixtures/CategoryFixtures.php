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

        foreach ($categories as $i => $categoryData) {
            $category = new Category();
            $category->setName($categoryData);
            $manager->persist($category);

            $this->addReference('category_' . $i, $category);
        }

        $manager->flush();
    }
}
