<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Reads runtime metadata for app MCP tools from app feature storage: declared
 * required privileges and the toolset group each tool belongs to.
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

    /**
     * Runtime app tools carry no compile-time #[McpToolGroup]; without a group they would fall to
     * the catch-all "other" bucket. Grouping each app's tools under the owning app's technical name
     * gives them a real, enable-able toolset so clients without inline tool promotion can reach them.
     *
     * @return array<string, string> tool-name => group (the owning app's technical name)
     */
    public function getAppToolGroups(): array
    {
        try {
            $map = [];
            foreach ($this->storage->forActiveApps(McpToolConfig::class) as $feature) {
                $map[$feature->appName . '-' . $feature->config->name] = $feature->appName;
            }

            return $map;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to load app MCP tool groups', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
