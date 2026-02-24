<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * Indexes ContentSeoRouteDescriptor instances for lookup by entity type.
 *
 * @internal
 *
 * @implements \IteratorAggregate<ContentSeoRouteDescriptor>
 */
#[Package('inventory')]
final readonly class ContentSeoRouteRegistry implements \IteratorAggregate
{
    /**
     * @var array<string, ContentSeoRouteDescriptor>
     */
    private array $byEntityType;

    /**
     * @param iterable<ContentSeoRouteDescriptor> $descriptors
     */
    public function __construct(iterable $descriptors)
    {
        $map = [];
        foreach ($descriptors as $descriptor) {
            $map[$descriptor->definition->getContentLayoutEntityType()] = $descriptor;
        }
        $this->byEntityType = $map;
    }

    public function findByEntityType(string $entityType): ?ContentSeoRouteDescriptor
    {
        return $this->byEntityType[$entityType] ?? null;
    }

    /**
     * @return \ArrayIterator<int, ContentSeoRouteDescriptor>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator(array_values($this->byEntityType));
    }
}
