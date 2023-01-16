<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @package core
 */
#[AsMessageHandler(handles: CleanupWebhookEventLogTask::class)]
final class CleanupWebhookEventLogTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        private WebhookCleanup $webhookCleanup
    ) {
        parent::__construct($repository);
    }

    public function run(): void
    {
        $this->webhookCleanup->removeOldLogs();
    }
}
