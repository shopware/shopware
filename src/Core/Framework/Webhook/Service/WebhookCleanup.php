<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class WebhookCleanup
{
    private const BATCH_SIZE = 500;

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    public function removeOldLogs(): void
    {
        $entryLifetimeSeconds = $this->systemConfigService->getInt('core.webhook.entryLifetimeSeconds');

        if ($entryLifetimeSeconds === -1) {
            return;
        }

        // Delete older webhook log entries where the webhook won't be called anymore
        $this->deleteLogsOlderThanWithStatus($entryLifetimeSeconds, WebhookEventLogDefinition::STATUS_SUCCESS, WebhookEventLogDefinition::STATUS_FAILED);
        // after double the entry lifetime, we also delete queued entries,
        // because we assume they are stuck in queued state (as we rely on message retry to retry failed webhooks)
        $this->deleteLogsOlderThanWithStatus($entryLifetimeSeconds * 2, WebhookEventLogDefinition::STATUS_QUEUED);
    }

    private function deleteLogsOlderThanWithStatus(int $entryLifetimeSeconds, string ...$status): void
    {
        $deleteBefore = $this->clock
            ->now()
            ->modify("- $entryLifetimeSeconds seconds")
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        do {
            $deleted = $this->connection->executeStatement(
                'DELETE FROM `webhook_event_log` WHERE `created_at` < :before AND `delivery_status` IN (:status) LIMIT :limit',
                [
                    'before' => $deleteBefore,
                    'status' => $status,
                    'limit' => self::BATCH_SIZE,
                ],
                [
                    'limit' => Types::INTEGER,
                    'status' => ArrayParameterType::STRING,
                ]
            );
        } while ($deleted === self::BATCH_SIZE);
    }
}
