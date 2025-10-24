<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves provider/consumer data flow with hierarchical context scoping.
 *
 * @internal
 */
#[Package('discovery')]
class DataContextResolver
{
    /**
     * @param iterable<DistributionStrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $strategies
    ) {
    }

    public function resolve(ContentElement $element): void
    {
        $visitor = new ContextResolutionVisitor($this->strategies);
        $element->traverse($visitor);
    }
}
