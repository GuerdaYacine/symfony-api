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
        $roles = [
            0 => "ROLE_USER",
            1 => "ROLE_ADMIN"
        ];

        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $user->setEmail($faker->email);
            $email = $user->getEmail();
            $nameAndEmail = explode('@', $email);
            $password = $nameAndEmail[0];
            $user->setRoles([$faker->randomElement($roles)]);
            // reprenez tout ce qui est contenue avant le @ dans l'email
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $password)
            );
            $user->setSubscriptionToNewsletter($faker->boolean);
            $manager->persist($user);
        }

        $manager->flush();
    }
}
