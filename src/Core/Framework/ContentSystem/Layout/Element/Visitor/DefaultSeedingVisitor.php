<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Seeds each visited element's primitive type defaults into its stored properties: for every primitive property of
 * the element's component type whose default is non-null and whose key the element does not already carry, the
 * default is written via setProperty. An existing value is never overwritten and an unregistered component is left
 * untouched. {@see ContentElement::traverse()} drives the depth-first walk, so a single traverse() seeds the whole
 * subtree.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DefaultSeedingVisitor implements ElementVisitor
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly PrimitiveDefaultProvider $primitiveDefaultProvider,
    ) {
    }

    public function enter(ContentElement $element): void
    {
        if (!$this->registry->has($element->getComponent())) {
            return;
        }

        foreach ($this->primitiveDefaultProvider->forType($this->registry, $element->getComponent()) as $key => $default) {
            if (!$element->hasProperty($key)) {
                $element->setProperty($key, $default);
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function leave(ContentElement $element): void
    {
    }
}
