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
 * Decorate this service to override an existing mapping through {@see resolveAdmittedSource()}. The final
 * entry point enforces exact catalogue identity and records metadata dependencies before invoking that hook.
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
     * Resolve only an exact catalogued source, including configuration, even through decorators or direct calls.
     * Property admission and runtime value-type checks remain the caller's concern.
     * The explicit result distinguishes a resolved null from no value. Provider errors propagate normally.
     */
    final public function resolveSource(
        string $rootSource,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): MappingSourceResolution {
        $path = $source->displayName();
        $context->cacheContext->addTags($this->cacheTagsForMapping($rootSource, $path));
        $candidate = $this->forRootSource($rootSource)[$path] ?? null;
        if ($candidate === null || !$candidate->source->isSameAs($source)) {
            return MappingSourceResolution::noValue();
        }

        $context->cacheContext->addTags($candidate->cacheTags);

        return $this->resolveAdmittedSource($rootSource, $source, $context);
    }

    /**
     * Receives an exact source admitted by this registry's catalogue, with metadata dependencies already recorded.
     * Override to customize a value; forward unrelated sources to the decorated registry's public resolveSource().
     * Plugins remain responsible for the safety of returned values and any additional data dependencies.
     */
    protected function resolveAdmittedSource(
        string $rootSource,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): MappingSourceResolution {
        return $this->getDecorated()->resolveSource($rootSource, $source, $context);
    }
}
