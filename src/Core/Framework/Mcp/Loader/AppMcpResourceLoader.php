<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Resource;
use Mcp\Server\RequestContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Loads app-provided MCP resources from the database and registers them
 * with the MCP server registry at build time.
 */
#[Package('framework')]
class AppMcpResourceLoader extends AbstractAppMcpLoader
{
    protected function fetchRows(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                r.name,
                r.uri,
                r.url,
                r.mime_type,
                a.name AS app_name,
                a.app_secret,
                rt.label,
                rt.description
            FROM app_mcp_resource r
            INNER JOIN app a ON r.app_id = a.id AND a.active = 1
            LEFT JOIN app_mcp_resource_translation rt
                ON r.id = rt.app_mcp_resource_id
                AND rt.language_id = UNHEX(:languageId)
            WHERE a.app_secret IS NOT NULL
            ORDER BY a.name, r.name',
            ['languageId' => Defaults::LANGUAGE_SYSTEM],
        );
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
        $mimeType = isset($row['mime_type']) ? (string) $row['mime_type'] : null;

        $resource = new Resource(
            uri: (string) $row['uri'],
            name: $resourceName,
            description: $description,
            mimeType: $mimeType,
        );

        $appSecret = (string) $row['app_secret'];
        $url = (string) $row['url'];
        $uri = (string) $row['uri'];

        $registry->registerResource($resource, function (RequestContext $context) use ($resourceName, $appSecret, $url, $uri): string {
            return $this->executor->execute($resourceName, $appSecret, $url, ['uri' => $uri]);
        }, true);
    }
}
