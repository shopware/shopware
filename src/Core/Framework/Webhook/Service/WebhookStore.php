<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

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

    /**
     * @param list<string> $roleIds
     *
     * @return list<string>
     */
    public function getRoleIdsUsedByWebhooks(array $roleIds): array
    {
        $usedRoleIds = [];
        foreach ($this->connection->fetchFirstColumn('SELECT `acl_role_ids` FROM `webhook` WHERE `acl_role_ids` IS NOT NULL') as $aclRoleIds) {
            $usedRoleIds = [...$usedRoleIds, ...Json::decodeToList($aclRoleIds)];
        }

        return array_values(array_intersect($roleIds, $usedRoleIds));
    }
}
