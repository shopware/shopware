<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @internal
 */
#[McpTool(name: 'shopware-content-layout-configure', title: 'Configure content layout element', description: 'Set or merge properties and style options on one element in the current Experience Studio draft layout. Use this after structural insertion to set authored text, colors, spacing, and alignment.')]
#[McpToolRequires('content_layout:update')]
#[Package('framework')]
class ContentSystemLayoutConfigureTool extends McpToolResponse
{
    public function __construct(private readonly McpContextProvider $contextProvider)
    {
    }

    public function __invoke(string $layout, string $elementId, string $properties = '{}', string $style = '{}'): string
    {
        $context = $this->contextProvider->getContext();
        if ($error = $this->requirePrivilege($context, 'content_layout:update')) {
            return $error;
        }

        $decodedLayout = $this->decodeJsonOrError($layout, 'layout');
        if (\is_string($decodedLayout)) {
            return $decodedLayout;
        }

        $decodedProperties = $this->decodeJsonOrError($properties, 'properties');
        if (\is_string($decodedProperties)) {
            return $decodedProperties;
        }

        $decodedStyle = $this->decodeJsonOrError($style, 'style');
        if (\is_string($decodedStyle)) {
            return $decodedStyle;
        }

        if (!$this->configure($decodedLayout, $elementId, $decodedProperties, $decodedStyle)) {
            return $this->error(\sprintf('Element "%s" was not found in the draft layout.', $elementId));
        }

        return $this->success(['layout' => $decodedLayout, 'affectedElementIds' => [$elementId]]);
    }

    /**
     * @param array<mixed> $elements
     * @param array<mixed> $properties
     * @param array<mixed> $style
     */
    private function configure(array &$elements, string $elementId, array $properties, array $style): bool
    {
        foreach ($elements as &$element) {
            if (!\is_array($element)) {
                continue;
            }

            if (($element['id'] ?? null) === $elementId) {
                $element['properties'] = array_replace(
                    \is_array($element['properties'] ?? null) ? $element['properties'] : [],
                    $properties,
                );
                $element['style'] = array_replace_recursive(
                    \is_array($element['style'] ?? null) ? $element['style'] : [],
                    $style,
                );

                return true;
            }

            if (!\is_array($element['slots'] ?? null)) {
                continue;
            }

            foreach ($element['slots'] as &$slotElements) {
                if (\is_array($slotElements) && $this->configure($slotElements, $elementId, $properties, $style)) {
                    return true;
                }
            }
            unset($slotElements);
        }
        unset($element);

        return false;
    }
}
