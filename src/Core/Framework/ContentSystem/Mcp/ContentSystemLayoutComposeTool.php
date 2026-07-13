<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\Api\InsertElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @internal
 */
#[McpTool(name: 'shopware-content-layout-compose', title: 'Compose content layout elements', description: 'Create and configure several nested elements in one Experience Studio draft operation. Each insertion has an alias; later insertions can use that alias as parentAlias.')]
#[McpToolRequires('content_layout:update')]
#[Package('framework')]
class ContentSystemLayoutComposeTool extends McpToolResponse
{
    public function __construct(
        private readonly LayoutMutationController $mutationController,
        private readonly ContentSystemLayoutConfigureTool $configureTool,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $layout, string $insertions, ?string $rootSource = null): string
    {
        $context = $this->contextProvider->getContext();
        if ($error = $this->requirePrivilege($context, 'content_layout:update')) {
            return $error;
        }

        $currentLayout = $this->decodeJsonOrError($layout, 'layout');
        if (\is_string($currentLayout)) {
            return $currentLayout;
        }

        $decodedInsertions = $this->decodeJsonOrError($insertions, 'insertions');
        if (\is_string($decodedInsertions)) {
            return $decodedInsertions;
        }

        $aliases = [];
        foreach ($decodedInsertions as $insertion) {
            if (!\is_array($insertion) || !\is_string($insertion['alias'] ?? null) || !\is_string($insertion['type'] ?? null)) {
                return $this->error('Every insertion requires a unique "alias" and a valid "type".');
            }

            $alias = $insertion['alias'];
            if (isset($aliases[$alias])) {
                return $this->error(\sprintf('Insertion alias "%s" is duplicated.', $alias));
            }

            $parentElementId = \is_string($insertion['parentElementId'] ?? null) ? $insertion['parentElementId'] : null;
            if (\is_string($insertion['parentAlias'] ?? null)) {
                $parentElementId = $aliases[$insertion['parentAlias']] ?? null;
                if ($parentElementId === null) {
                    return $this->error(\sprintf('Parent alias "%s" has not been inserted yet.', $insertion['parentAlias']));
                }
            }

            $response = $this->mutationController->insert(new InsertElementRequest(
                $insertion['type'],
                $currentLayout,
                $parentElementId,
                \is_string($insertion['slot'] ?? null) ? $insertion['slot'] : null,
                \is_int($insertion['index'] ?? null) ? $insertion['index'] : null,
                $rootSource,
                \is_string($insertion['bindingSpecificationId'] ?? null) ? $insertion['bindingSpecificationId'] : null,
            ), $context);
            $mutation = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $elementId = $mutation['affectedElementIds'][0] ?? null;
            if (!\is_string($elementId) || !\is_array($mutation['layout'] ?? null)) {
                return $this->error(\sprintf('Insertion "%s" did not return an element.', $alias));
            }

            $aliases[$alias] = $elementId;
            $currentLayout = $mutation['layout'];

            $properties = \is_array($insertion['properties'] ?? null) ? $insertion['properties'] : [];
            $style = \is_array($insertion['style'] ?? null) ? $insertion['style'] : [];
            if ($properties === [] && $style === []) {
                continue;
            }

            $configured = json_decode(($this->configureTool)(
                json_encode($currentLayout, \JSON_THROW_ON_ERROR),
                $elementId,
                json_encode($properties, \JSON_THROW_ON_ERROR),
                json_encode($style, \JSON_THROW_ON_ERROR),
            ), true, 512, \JSON_THROW_ON_ERROR);
            if (($configured['success'] ?? false) !== true || !\is_array($configured['data']['layout'] ?? null)) {
                return $this->error($configured['error'] ?? \sprintf('Insertion "%s" could not be configured.', $alias));
            }

            $currentLayout = $configured['data']['layout'];
        }

        return $this->success(['layout' => $currentLayout, 'aliases' => $aliases]);
    }
}
