<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\Mcp\ElementTypeCatalogProjector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-content-layout-element-types',
    title: 'Content Layout Element Types',
    description: 'Catalog of the element types a content layout is built from: the component names to pass as "type"/"newType" to shopware-content-layout-edit (e.g. "Sw:Content:Text"), a summary and usage hints per type, its properties (type, default, required, allowed enum values; primitive properties are set via "properties", class-typed ones are filled from shop data), its slots (name, maxElements, allowList of child types; no allowList means any type), plus the preset ids usable with the insert-preset operation. Read-only.'
)]
#[McpToolGroup('content-layout')]
#[McpToolRequires('content_layout:read')]
class ContentLayoutElementTypesTool extends McpToolResponse
{
    public function __construct(
        private readonly ElementTypeCatalogProjector $catalogProjector,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(): string
    {
        if ($error = $this->requirePrivilege($this->contextProvider->getContext(), 'content_layout:read')) {
            return $error;
        }

        return $this->success($this->catalogProjector->project());
    }
}
