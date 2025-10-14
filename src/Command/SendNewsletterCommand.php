<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Repository\VideoGameRepository;
use App\Service\NewsletterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-newsletter',
    description: 'Envoie la newsletter aux utilisateurs abonnés',
)]
class SendNewsletterCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private VideoGameRepository $videoGameRepository,
        private NewsletterService $newsletterService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $subscribers = $this->userRepository->findSubscribers();
        $gamesNextWeek = $this->videoGameRepository->findGamesNextWeek();

        if (!$subscribers) {
            $io->warning('Aucun utilisateur abonné.');
            return Command::SUCCESS;
        }

        if (!$gamesNextWeek) {
            $io->warning('Aucun jeu à sortir dans les 7 prochains jours.');
            return Command::SUCCESS;
        }

        foreach ($subscribers as $user) {
            $this->newsletterService->sendNewsletter($user, $gamesNextWeek);
            $io->text('Newsletter envoyée à : ' . $user->getEmail());
        }

        $io->success('Toutes les newsletters ont été envoyées.');

        return Command::SUCCESS;
    }
}
