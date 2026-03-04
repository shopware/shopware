<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataContext;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves provider/consumer data flow with hierarchical context scoping.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DataContextResolver
{
    public function __construct(
        private readonly ContextPathResolver $pathResolver
    ) {
    }

    public function resolve(ContentElement $element): void
    {
        $visitor = new ContextResolutionVisitor($this->pathResolver);
        $element->traverse($visitor);
    }
}
