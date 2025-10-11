<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $usersData = [
            ['email' => 'user1@example.com', 'password' => 'lolmdr123', 'roles' => ['ROLE_USER']],
            ['email' => 'user2@example.com', 'password' => 'mdrlol456', 'roles' => ['ROLE_USER']],
            ['email' => 'user3@example.com', 'password' => 'password123', 'roles' => ['ROLE_ADMIN']],
            ['email' => 'user4@example.com', 'password' => 'secret456', 'roles' => ['ROLE_USER']],
            ['email' => 'user5@example.com', 'password' => 'azerty789', 'roles' => ['ROLE_USER']],
        ];

        foreach ($usersData as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles($data['roles']);
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $data['password'])
            );
            $manager->persist($user);
        }

        $manager->flush();
    }
}
