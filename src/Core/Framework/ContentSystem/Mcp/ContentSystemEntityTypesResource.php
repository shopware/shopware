<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[McpResource(uri: 'shopware://content-system/entity-types', name: 'shopware-content-entity-types', description: 'Entity types that can be used as Experience Studio layout root sources')]
#[Package('framework')]
class ContentSystemEntityTypesResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RootSourceRegistry $rootSourceRegistry,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        return [
            'uri' => 'shopware://content-system/entity-types',
            'mimeType' => 'application/json',
            'text' => Json::encode($this->rootSourceRegistry->entityRootSources()),
        ];
    }
}
