<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[McpResource(uri: 'shopware://content-system/element-types', name: 'shopware-content-element-types', description: 'Registered Experience Studio content element type specifications')]
#[Package('framework')]
class ContentSystemElementTypesResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $types = [];
        foreach ($this->registry->all() as $type) {
            $types[] = $type->toSchema();
        }

        return [
            'uri' => 'shopware://content-system/element-types',
            'mimeType' => 'application/json',
            'text' => Json::encode($types),
        ];
    }
}
