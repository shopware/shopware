<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runtime inputs available to a registered mapping-source provider. Providers must read only values they explicitly
 * expose through their candidate catalogue and preserve cache tags through the supplied rendering cache context.
 * `$rootSource` identifies the catalogue whose admitted candidate is being resolved; it is null for legacy direct
 * resolution calls that do not carry a layout root source.
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
        public ?SalesChannelContext $salesChannelContext,
        public ?Request $request,
        public ?RenderingCacheContext $cacheContext,
        public ?string $rootSource = null,
    ) {
    }
}
