<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Registry;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;

/**
 * Single authority over the data-mapping catalogue, read by both the introspection endpoint the Administration
 * builds its selection modal from and the write boundary that admits a stored mapping. One registry for both
 * is what keeps an offered candidate acceptable and an acceptable one offered.
 */
#[Package('framework')]
abstract class AbstractContentSystemMappingCandidateRegistry
{
    abstract public function getDecorated(): self;

    /**
     * Keyed by the candidate's source display name, so the write boundary validates the exact typed source reference.
     *
     * An unknown root source yields an empty catalogue rather than throwing: a layout bound to a source that
     * offers nothing mappable is a layout with no mappings, not an error.
     *
     * @return array<string, MappingCandidate>
     */
    abstract public function forRootSource(string $rootSource): array;

    /**
     * Resolve an admitted non-root source through the provider that offered it for this root source;
     * a missing provider/value is represented by null.
     */
    abstract public function resolveSource(
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): mixed;

    /**
     * Resolve a source through the provider that offered it for the specified root source. Passing the root
     * source through the existing resolution context keeps this compatible with decorators that forward
     * `resolveSource()` to the decorated registry.
     */
    public function resolveSourceForRootSource(
        ?string $rootSource,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): mixed {
        if ($rootSource === null) {
            return null;
        }

        return $this->resolveSource(
            $source,
            new MappingSourceResolutionContext(
                $context->element,
                $context->rootValues,
                $context->loaderValues,
                $context->salesChannelContext,
                $context->request,
                $context->cacheContext,
                $rootSource,
            ),
        );
    }
}
