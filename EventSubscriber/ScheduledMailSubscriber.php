<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use KimaiPlugin\DrehzettelBundle\Service\ScheduledMails;
use KimaiPlugin\DrehzettelBundle\Service\SchemaStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Automatic mails without a cron job: after a response was sent, at most every
 * 10 minutes, the due mails go out. A cron running drehzettel:mail:due is more
 * punctual; both together never send twice (MailRecipientRepository::claim()).
 */
final class ScheduledMailSubscriber implements EventSubscriberInterface
{
    private const CACHE_KEY = 'drehzettel_scheduled_mail_check';
    private const INTERVAL_SECONDS = 600;

    public function __construct(
        private readonly ScheduledMails $mails,
        private readonly SchemaStatus $schema,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Missing tables after an update: wait for the install command instead of logging each run.
        if (!$this->schema->isCurrent()) {
            return;
        }

        try {
            // The callback runs only when the entry has expired.
            $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): int {
                $item->expiresAfter(self::INTERVAL_SECONDS);

                return $this->mails->sendDue(new \DateTimeImmutable());
            });
        } catch (\Throwable $e) {
            $this->logger->error('Drehzettel: scheduled mail check failed: {error}', ['error' => $e->getMessage()]);
        }
    }
}
