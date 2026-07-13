<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @internal
 */
#[McpTool(name: 'shopware-content-layout-preview', title: 'Preview content layout draft', description: 'Use this tool to render an Experience Studio draft layout against a real product, category, or landing page without saving it.')]
#[McpToolRequires('content_layout:read')]
#[Package('framework')]
class ContentSystemPreviewTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentPreviewController $controller,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(
        string $layout,
        string $entityType,
        string $entityId,
        string $salesChannelId,
        ?string $languageId = null,
        ?string $currencyId = null,
        ?string $domainId = null,
        ?string $customerId = null,
    ): string {
        $context = $this->contextProvider->getContext();
        if ($error = $this->requirePrivilege($context, 'content_layout:read')) {
            return $error;
        }

        $decodedLayout = $this->decodeJsonOrError($layout, 'layout');
        if (\is_string($decodedLayout)) {
            return $decodedLayout;
        }

        $response = $this->controller->preview(new ContentPreviewRequest(
            $decodedLayout,
            $entityType,
            $entityId,
            $salesChannelId,
            $languageId,
            $currencyId,
            $domainId,
            $customerId,
        ), $context);
        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $this->success($data);
    }
}
