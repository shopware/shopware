<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber\WebhookSubscriberTest
 */
#[Package('framework')]
class WebhookStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function switchIntegration(string $oldIntegrationId, string $newIntegrationId): void
    {
        $this->connection->executeStatement(
            'UPDATE `webhook` SET `owner_integration_id` = :new WHERE `owner_integration_id` = :old',
            ['old' => $oldIntegrationId, 'new' => $newIntegrationId],
        );
    }
}
