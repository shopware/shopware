<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[McpResource(uri: 'shopware://content-system/style-options', name: 'shopware-content-style-options', description: 'Available Experience Studio style option specifications')]
#[Package('framework')]
class ContentSystemStyleOptionsResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContentSystemStyleOptionRegistry $registry,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $options = [];
        foreach ($this->registry->allResolved() as $name => $option) {
            $options[$name] = $option->toSchema();
        }

        return [
            'uri' => 'shopware://content-system/style-options',
            'mimeType' => 'application/json',
            'text' => Json::encode($options),
        ];
    }
}
