<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Reads the declared required privileges for app MCP tools.
 *
 * @internal
 */
#[Package('framework')]
class AppMcpPrivilegeProvider
{
    public function __construct(
        private readonly AppFeatureStorage $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, list<string>> tool-name => ['entity:operation', ...]
     */
    public function getAppToolPrivileges(): array
    {
        try {
            $map = [];
            foreach ($this->storage->forActiveApps(McpToolConfig::class) as $feature) {
                $config = $feature->config;

                $map[$feature->appName . '-' . $config->name] = $config->requiredPrivileges;
            }

            return $map;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to load app MCP tool privileges', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
