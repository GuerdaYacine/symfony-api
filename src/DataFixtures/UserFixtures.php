<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $name = mb_strtolower($faker->firstName()) . mb_strtolower($faker->lastName());
            $user->setEmail($name . "@gmail.com");
            if ($i < 2) {
                $user->setRoles(['ROLE_ADMIN']);
            } else {
                $user->setRoles(['ROLE_USER']);
            }
            // reprenez tout ce qui est contenue avant le @ dans l'email et ajoutez 60 pour avoir le mdp ;)
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $name . "60")
            );
            $manager->persist($user);
        }

        $manager->flush();
    }
}
