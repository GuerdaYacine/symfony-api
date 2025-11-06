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
            ->htmlTemplate('emails/newsletter.html.twig');

            $embeddedImages = [];
            foreach ($games as $index => $game) {
                if ($game->getCoverImage()) {
                    $path = 'public/uploads/video_games/'. $game->getCoverImage();

                    if (file_exists($path)) {
                        $cid = 'game_' . $index;
                        $email->embedFromPath($path, $cid);
                        $embeddedImages[$game->getId()] = $cid;
                    }
                }
            }

            $email->context([
                'user' => $user,
                'games' => $games,
                'embeddedImages' => $embeddedImages,
            ]);

            $this->mailer->send($email);
            sleep(10);
    }
}
