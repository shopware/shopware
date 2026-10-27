<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopware://entities',
    name: 'shopware-entity-list',
    description: 'List of all registered Shopware entity names'
)]
class EntityListResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
    {
        $entities = [];
        foreach ($this->registry->getDefinitions() as $definition) {
            $entities[] = $definition->getEntityName();
        }

        sort($entities);

        return McpResourceCacheHint::read([
            'uri' => 'shopware://entities',
            'mimeType' => 'application/json',
            'text' => Json::encode($entities),
        ], $requestContext, McpResourceCacheHint::DEPLOYMENT_TTL_MS);
    }
}
