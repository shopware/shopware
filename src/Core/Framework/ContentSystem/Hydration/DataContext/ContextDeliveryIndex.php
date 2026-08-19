<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataContext;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * What every element of one forest received, by element id. Computed once by
 * {@see ContextDeliveryResolver::resolve()} and read back afterwards; nothing here computes on read, which is
 * what separates an index from a resolver.
 *
 * Total over the forest it was built from: every element has an entry, including a root and including an
 * element that received nothing, which carries an empty {@see ContextDelivery} rather than being absent. A
 * consumer walking that same forest therefore never misses, and "no entry" means the id did not come from
 * this forest rather than that the element received nothing.
 *
 * Element ids are unique across all roots of a layout, so one flat map covers a multi-root forest without
 * the roots colliding.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContextDeliveryIndex
{
    /**
     * @param array<string, ContextDelivery> $deliveries keyed by {@see StoredElement::$id}
     */
    public function __construct(
        private array $deliveries = [],
    ) {
    }

    public function has(string $elementId): bool
    {
        return \array_key_exists($elementId, $this->deliveries);
    }

    /**
     * @return array<string, ContextDelivery>
     */
    public function all(): array
    {
        return $this->deliveries;
    }
}
