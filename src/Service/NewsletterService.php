<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class NewsletterService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig
    ) {}

    public function sendNewsletter(User $user, array $games): void
    {
        $email = (new TemplatedEmail())
            ->from('no-reply@monsite.com')
            ->to($user->getEmail())
            ->subject('Nouvelle sortie de jeux vidéo cette semaine !')
            ->htmlTemplate('emails/newsletter.html.twig')
            ->context([
                'user' => $user,
                'games' => $games,
            ]);

        $this->mailer->send($email);

        sleep(10);
    }
}
