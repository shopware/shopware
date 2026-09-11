<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ScheduledTask;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes cookie consent decisions older than `shopware.cookie_consent.retention_days`.
 *
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: CleanupCookieConsentLogTask::class)]
final class CleanupCookieConsentLogTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly AbstractCookieConsentLogStorage $storage,
        private readonly ClockInterface $clock,
        private readonly int $retentionDays,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->storage->cleanup(
            $this->clock->now()->sub(new \DateInterval(\sprintf('P%dD', $this->retentionDays))),
        );
    }
}
