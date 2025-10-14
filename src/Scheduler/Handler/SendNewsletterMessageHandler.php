<?php

namespace App\Scheduler\Handler;

use App\Repository\UserRepository;
use App\Repository\VideoGameRepository;
use App\Scheduler\Message\SendNewsletterMessage;
use App\Service\NewsletterService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendNewsletterMessageHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private VideoGameRepository $videoGameRepository,
        private NewsletterService $newsletterService
    ) {}

    public function __invoke(SendNewsletterMessage $message)
    {
        $subscribers = $this->userRepository->findSubscribers();

        $gamesNextWeek = $this->videoGameRepository->findGamesNextWeek();

        if (!$subscribers || !$gamesNextWeek) {
            return;
        }

        foreach ($subscribers as $user) {
            $this->newsletterService->sendNewsletter($user, $gamesNextWeek);
        }
    }
}
