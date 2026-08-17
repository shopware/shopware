<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class RenderableLayout
{
    /**
     * @param list<ContentElement> $elements
     */
    private function __construct(
        public LayoutReference $reference,
        public array $elements,
    ) {
    }

    /**
     * @param list<ContentElement> $elements
     */
    public static function create(LayoutReference $reference, array $elements): self
    {
        return new self($reference, $elements);
    }

    /**
     * The entity holds the stored element model, which serving does not read yet, so the tree is lowered here.
     * The lowering carries no state and no dependencies, and a static factory has nothing to inject it into, so
     * it is built at the point of use rather than reaching the route: passing it in would put the same
     * construction one layer further from the seam it serves, in a route whose arguments a compiler pass owns.
     */
    public static function fromEntity(ContentLayoutEntity $entity): self
    {
        return self::create(
            LayoutReference::fromEntity($entity),
            (new ContentElementLowering())->lowerTree($entity->getLayout())
        );
    }
}
