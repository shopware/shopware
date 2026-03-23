<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Log\Package;

/**
 * Exposes the live Shopware OpenAPI schema as an MCP resource.
 *
 * Consuming this resource instead of the static versioned docs reveals
 * plugin-added endpoints and fields that are not in the published spec.
 *
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(
    uri: 'shopware://openapi-schema/admin-api',
    name: 'shopware-openapi-admin',
    description: 'Full OpenAPI 3 schema for the Shopware Admin API as exposed by this running instance — includes plugin-contributed endpoints and fields not present in the versioned documentation.',
)]
#[Package('framework')]
class OpenApiSchemaResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionService $definitionService,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $schema = $this->definitionService->generate(
            'openapi-3',
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API,
        );

        return [
            'uri' => 'shopware://openapi-schema/admin-api',
            'mimeType' => 'application/json',
            'text' => json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
        ];
    }
}
