<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;

/**
 * Owns webhook health. To be expanded in Phase 2 (#16565).
 *
 * @internal
 */
#[Package('framework')]
class WebhookHealthService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RelatedWebhooks $relatedWebhooks,
    ) {
    }

    /**
     * Increments error_count and applies the strategy atomically. No-op if the webhook is missing or inactive.
     */
    public function recordFailure(string $webhookId, WebhookFailureStrategy $strategy): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        if (!\is_array($row) || !$row['active']) {
            return;
        }

        $newCount = (int) $row['error_count'] + 1;

        $params = $strategy === WebhookFailureStrategy::DisableOnThreshold && $newCount >= WebhookFailureStrategy::MAX_ERROR_COUNT
            ? ['error_count' => 0, 'active' => 0]
            : ['error_count' => $newCount];

        $this->relatedWebhooks->updateRelated($webhookId, $params, Context::createDefaultContext());
    }

    public function resetErrorCount(string $webhookId): void
    {
        $this->relatedWebhooks->updateRelated($webhookId, ['error_count' => 0], Context::createDefaultContext());
    }
}
