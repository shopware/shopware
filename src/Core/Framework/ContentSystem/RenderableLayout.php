<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
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

    public static function fromEntity(ContentLayoutEntity $entity): self
    {
        return self::create(LayoutReference::fromEntity($entity), $entity->getLayout());
    }
}
