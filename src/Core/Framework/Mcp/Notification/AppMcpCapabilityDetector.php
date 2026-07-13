<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
class AppMcpCapabilityDetector
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function persistedForApp(string $appId): McpListChangedNotificationSet
    {
        $appId = Uuid::fromHexToBytes($appId);

        return new McpListChangedNotificationSet(
            tools: $this->hasCapabilityRows('app_mcp_tool', $appId),
            resources: $this->hasCapabilityRows('app_mcp_resource', $appId),
            prompts: $this->hasCapabilityRows('app_mcp_prompt', $appId),
        );
    }

    public function fromMcp(?Mcp $mcp): McpListChangedNotificationSet
    {
        if ($mcp === null) {
            return McpListChangedNotificationSet::none();
        }

        return new McpListChangedNotificationSet(
            tools: $mcp->getTools() !== null && \count($mcp->getTools()->getTools()) > 0,
            resources: $mcp->getResources() !== null && \count($mcp->getResources()->getResources()) > 0,
            prompts: $mcp->getPrompts() !== null && \count($mcp->getPrompts()->getPrompts()) > 0,
        );
    }

    private function hasCapabilityRows(string $table, string $appId): bool
    {
        return $this->connection->fetchOne(
            \sprintf('SELECT 1 FROM `%s` WHERE `app_id` = :appId LIMIT 1', $table),
            ['appId' => $appId],
        ) !== false;
    }
}
