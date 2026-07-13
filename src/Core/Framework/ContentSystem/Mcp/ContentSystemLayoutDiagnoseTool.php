<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\Api\ContentDiagnoseController;
use Shopware\Core\Framework\ContentSystem\Api\ContentDiagnoseRequest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @internal
 */
#[McpTool(name: 'shopware-content-layout-diagnose', title: 'Diagnose content layout draft', description: 'Use this tool to check an Experience Studio draft layout for structural and resolvability problems without changing or saving it.')]
#[McpToolRequires('content_layout:read')]
#[Package('framework')]
class ContentSystemLayoutDiagnoseTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentDiagnoseController $controller,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $layout, ?string $rootSource = null): string
    {
        $context = $this->contextProvider->getContext();
        if ($error = $this->requirePrivilege($context, 'content_layout:read')) {
            return $error;
        }

        $decodedLayout = $this->decodeJsonOrError($layout, 'layout');
        if (\is_string($decodedLayout)) {
            return $decodedLayout;
        }

        $response = $this->controller->diagnose(new ContentDiagnoseRequest($decodedLayout, $rootSource), $context);
        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $this->success($data);
    }
}
