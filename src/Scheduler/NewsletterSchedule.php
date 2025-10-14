<?php

namespace App\Scheduler;

use App\Scheduler\Message\SendNewsletterMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('send_newsletter')]
final class NewsletterSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron('30 8 * * 1', new SendNewsletterMessage())
            )
            ->stateful($this->cache)
        ;
    }
}
