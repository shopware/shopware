<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[McpResource(uri: 'shopware://content-system/data-loaders', name: 'shopware-content-data-loaders', description: 'Experience Studio data loader source and capability metadata')]
#[Package('framework')]
class ContentSystemDataLoadersResource
{
    public function __construct(private readonly ContentSystemDataLoaderMapResolver $resolver)
    {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $map = $this->resolver->resolve();

        return [
            'uri' => 'shopware://content-system/data-loaders',
            'mimeType' => 'application/json',
            'text' => Json::encode(['capabilities' => $map->sourceToCapabilities, 'configSpecifications' => $map->sourceToConfigSpecifications]),
        ];
    }
}
