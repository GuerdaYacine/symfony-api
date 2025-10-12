<?php

namespace App\DataFixtures;

use App\Entity\Editor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class EditorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $editors = [
            ['name' => 'Nintendo', 'country' => 'Japon'],
            ['name' => 'Electronic Arts', 'country' => 'États-Unis'],
            ['name' => 'Ubisoft', 'country' => 'France'],
            ['name' => 'Bethesda', 'country' => 'États-Unis'],
            ['name' => 'Square Enix', 'country' => 'Japon'],
            ['name' => 'Activision', 'country' => 'États-Unis'],
            ['name' => 'Capcom', 'country' => 'Japon'],
            ['name' => 'Bandai Namco', 'country' => 'Japon'],
            ['name' => 'Rockstar Games', 'country' => 'États-Unis'],
            ['name' => 'Sony Interactive Entertainment', 'country' => 'Japon'],
        ];

        shuffle($editors);

        for ($i = 0; $i < 10; $i++) {
            $editor = new Editor();
            $editor->setName($editors[$i]['name']);
            $editor->setCountry($editors[$i]['country']);
            $manager->persist($editor);

            $this->addReference('editor_' . $i, $editor);
        }

        $manager->flush();
    }
}
