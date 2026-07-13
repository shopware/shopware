<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[McpResource(uri: 'shopware://content-system/layout', name: 'shopware-content-layout-reference', description: 'Experience Studio content layout mutation and introspection reference')]
#[Package('framework')]
class ContentSystemLayoutResource
{
    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        return [
            'uri' => 'shopware://content-system/layout',
            'mimeType' => 'application/json',
            'text' => Json::encode([
                'mutationTool' => 'shopware-content-layout-mutate',
                'operations' => ['insert', 'remove', 'move', 'replace', 'duplicate', 'wrap', 'unwrap', 'attach', 'bind'],
                'introspectionEndpoints' => [
                    '/api/_info/content-system-element-types.json',
                    '/api/_info/content-system-style-options.json',
                    '/api/_info/content-system-entity-types.json',
                    '/api/_info/content-system-data-loaders.json',
                ],
            ]),
        ];
    }
}
