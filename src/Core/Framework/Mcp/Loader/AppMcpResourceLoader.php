<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\ResourceDefinition;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Registers app-provided MCP resources with the MCP server registry at build time.
 *
 * @extends AbstractAppMcpLoader<McpResourceConfig>
 */
#[Package('framework')]
class AppMcpResourceLoader extends AbstractAppMcpLoader
{
    protected function getConfigClass(): string
    {
        return McpResourceConfig::class;
    }

    protected function registerCapability(RegistryInterface $registry, AppFeature $feature, string $locale): void
    {
        if (!$feature->appHasSecret) {
            return;
        }

        $appName = $feature->appName;
        $config = $feature->config;
        $resourceName = $this->capabilityName($appName, $config->name);

        if ($this->isReservedName($resourceName, $appName, 'resource')) {
            return;
        }

        $resource = new ResourceDefinition(
            uri: $config->uri,
            name: $resourceName,
            description: $this->resolveDescription(
                $config->description->forLocale($locale),
                $config->label->forLocale($locale),
                $resourceName,
            ),
            mimeType: $config->mimeType,
        );

        $url = $config->url;
        $uri = $config->uri;

        $registry->registerResource($resource, function (RequestContext $context) use ($resourceName, $appName, $url, $uri): string {
            return $this->executor->execute($resourceName, $appName, $url, ['uri' => $uri]);
        });
    }
}
