<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;

/**
 * A single source binding of a layout, with the root context the source supplies to the layout's top-level elements.
 *
 * @internal
 */
#[Package('framework')]
final readonly class BoundRootContext
{
    /**
     * @param list<ProvidedContext> $providedRootContext
     */
    public function __construct(
        public string $sourceId,
        public array $providedRootContext,
    ) {
    }
}
