<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Registry;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Single authority over the data-mapping catalogue, read by both the introspection endpoint the Administration
 * builds its selection modal from and the write boundary that admits a stored mapping. One registry for both
 * is what keeps an offered candidate acceptable and an acceptable one offered.
 *
 * Decorate this service to override an existing mapping deliberately. Keep catalogue metadata and resolution
 * consistent, preserve exact source identity checks, and forward unrelated sources to the decorated registry.
 */
#[Package('framework')]
abstract class AbstractContentSystemMappingCandidateRegistry implements ResetInterface
{
    abstract public function getDecorated(): self;

    /**
     * Preserve request-local catalogue reset when the registry is decorated.
     */
    public function reset(): void
    {
        $this->getDecorated()->reset();
    }

    /**
     * Metadata dependencies for an attempted mapping, including a rejected or unavailable candidate.
     *
     * @return list<string>
     */
    public function cacheTagsForMapping(string $rootSource, string $path): array
    {
        return $this->getDecorated()->cacheTagsForMapping($rootSource, $path);
    }

    /**
     * Keyed by the candidate's source display name, so the write boundary validates the exact typed source reference.
     * Ambiguous names discovered during runtime catalogue construction are excluded.
     *
     * An unknown root source yields an empty catalogue rather than throwing: a layout bound to a source that
     * offers nothing mappable is a layout with no mappings, not an error.
     *
     * @return array<string, MappingCandidate>
     */
    abstract public function forRootSource(string $rootSource): array;

    /**
     * Resolve an admitted source through the provider that offered it for this root source;
     * explicit result distinguishes a resolved null from no value. Provider errors propagate to normal error handling.
     */
    abstract public function resolveSource(
        string $rootSource,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): MappingSourceResolution;
}
