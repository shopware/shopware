<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Resource;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Registers app-provided MCP resources with the MCP server registry at build time.
 */
#[Package('framework')]
class AppMcpResourceLoader extends AbstractAppMcpLoader
{
    protected function fetchRows(): array
    {
        $locale = $this->systemLocale();
        $features = $this->storage->forActiveApps(McpResourceConfig::class);

        $rows = [];

        foreach ($features as $feature) {
            if (!$feature->appHasSecret) {
                continue;
            }

            $config = $feature->config;

            $rows[] = [
                ...$config->toArray(),
                'app_name' => $feature->appName,
                'label' => $config->label->forLocale($locale),
                'description' => $config->description->forLocale($locale),
            ];
        }

        return $rows;
    }

    protected function registerCapability(RegistryInterface $registry, array $row): void
    {
        $appName = (string) $row['app_name'];
        $name = (string) $row['name'];
        $resourceName = $this->capabilityName($appName, $name);

        if ($this->isReservedName($resourceName, $appName, 'resource')) {
            return;
        }

        $description = $this->resolveDescription($row, $resourceName);
        $mimeType = isset($row['mimeType']) ? (string) $row['mimeType'] : null;

        $resource = new Resource(
            uri: (string) $row['uri'],
            name: $resourceName,
            description: $description,
            mimeType: $mimeType,
        );

        $url = (string) $row['url'];
        $uri = (string) $row['uri'];

        $registry->registerResource($resource, function (RequestContext $context) use ($resourceName, $appName, $url, $uri): string {
            return $this->executor->execute($resourceName, $appName, $url, ['uri' => $uri]);
        }, true);
    }
}
