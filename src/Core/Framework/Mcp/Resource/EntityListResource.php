<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(uri: 'shopware://entities', name: 'shopware-entity-list', description: 'List of all registered Shopware entity names')]
#[Package('framework')]
class EntityListResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $entities = [];
        foreach ($this->registry->getDefinitions() as $definition) {
            $entities[] = $definition->getEntityName();
        }

        sort($entities);

        return [
            'uri' => 'shopware://entities',
            'mimeType' => 'application/json',
            'text' => Json::encode($entities),
        ];
    }
}
