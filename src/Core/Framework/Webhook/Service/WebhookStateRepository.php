<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Webhook-level data operations (error counts, active state).
 * Uses RelatedWebhooks to match trunk behavior: updates propagate to all
 * webhooks with the same event name, URL, and live-version config.
 *
 * TODO: In a follow-up, evolve into a WebhookState service that receives a DeliveryResult
 * (successful + httpStatusCode) and owns the health/failure decisions internally. This will
 * become the seam for the Phase 2 endpoint health model (HEALTHY/DEGRADED/SUSPENDED/DISABLED).
 *
 * @internal
 */
#[Package('framework')]
class WebhookStateRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RelatedWebhooks $relatedWebhooks,
    ) {
    }

    /**
     * Increments the error_count for this webhook and all related webhooks.
     * Returns the new error_count for the given webhook.
     */
    public function incrementErrorCount(string $webhookId): int
    {
        $id = Uuid::fromHexToBytes($webhookId);

        $row = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => $id]
        );

        if (!\is_array($row) || !$row['active']) {
            return 0;
        }

        $newCount = (int) $row['error_count'] + 1;
        $this->relatedWebhooks->updateRelated($webhookId, ['error_count' => $newCount], Context::createDefaultContext());

        return $newCount;
    }

    /**
     * Resets error_count to 0 for this webhook and all related webhooks.
     */
    public function resetErrorCount(string $webhookId): void
    {
        $this->relatedWebhooks->updateRelated($webhookId, ['error_count' => 0], Context::createDefaultContext());
    }

    /**
     * Deactivates this webhook and all related webhooks, resetting their error_count.
     */
    public function deactivate(string $webhookId): void
    {
        $this->relatedWebhooks->updateRelated($webhookId, ['error_count' => 0, 'active' => 0], Context::createDefaultContext());
    }
}
