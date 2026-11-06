<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Analyzes context dependencies to determine which ancestors must be kept during tree pruning.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContextDependencyAnalyzer
{
    /**
     * These elements need their ancestors processed first to receive context data.
     */
    public function requiresParentData(StoredElement $element): bool
    {
        return $this->consumers($element) !== [];
    }

    /**
     * @param list<StoredElement> $pathElements Ordered from root to target
     *
     * @return int Index in path where context root is located (0 = root)
     */
    public function findDataRootIndex(array $pathElements): int
    {
        for ($i = \count($pathElements) - 1; $i >= 0; --$i) {
            if (!$this->requiresParentData($pathElements[$i])) {
                // Found element that doesn't need parent data - this is our context root
                return $i;
            }
        }

        // All elements require parent data, must keep from root
        return 0;
    }

    /**
     * Reaching through `contextDefinitions` here rather than on {@see StoredElement} itself: one consumer
     * does not earn a shortcut on the storage model's public surface.
     *
     * @return array<string, ContextConsumer>
     */
    private function consumers(StoredElement $element): array
    {
        return $element->contextDefinitions->getAllConsumers();
    }
}
