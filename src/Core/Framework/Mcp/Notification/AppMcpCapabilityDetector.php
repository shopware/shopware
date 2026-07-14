<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
class AppMcpCapabilityDetector
{
    public function __construct(
        private readonly AppFeatureStorage $storage,
    ) {
    }

    public function persistedForApp(string $appId): McpListChangedNotificationSet
    {
        return new McpListChangedNotificationSet(
            tools: $this->storage->forApp($appId, McpToolConfig::class) !== [],
            resources: $this->storage->forApp($appId, McpResourceConfig::class) !== [],
            prompts: $this->storage->forApp($appId, McpPromptConfig::class) !== [],
        );
    }

    public function fromMcp(?Mcp $mcp): McpListChangedNotificationSet
    {
        if ($mcp === null) {
            return McpListChangedNotificationSet::none();
        }

        return new McpListChangedNotificationSet(
            tools: $mcp->getTools() !== null && $mcp->getTools()->getTools() !== [],
            resources: $mcp->getResources() !== null && $mcp->getResources()->getResources() !== [],
            prompts: $mcp->getPrompts() !== null && $mcp->getPrompts()->getPrompts() !== [],
        );
    }
}
