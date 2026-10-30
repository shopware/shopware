<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Runtime inputs available to a registered mapping-source provider. Providers must read only values they explicitly
 * expose through their candidate catalogue and preserve cache tags through the supplied rendering cache context.
 * The same cache context is required for every mapping in a render, including renders with caching disabled.
 */
#[Package('framework')]
final readonly class MappingSourceResolutionContext
{
    /**
     * @param array<string, mixed> $rootValues root-ambient data keyed by page-level requirement
     * @param array<string, mixed> $loaderValues loader results for the element being resolved
     */
    public function __construct(
        public StoredElement $element,
        public array $rootValues,
        public array $loaderValues,
        public RenderingCacheContext $cacheContext,
    ) {
    }
}
