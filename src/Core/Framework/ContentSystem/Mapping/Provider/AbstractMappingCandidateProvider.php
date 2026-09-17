<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Provider;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;

/**
 * Contributes the mapping candidates one root source offers. Tag an implementation
 * `content_system.mapping_candidate_provider` to have it aggregated by the registry.
 *
 * A provider is asked about one root source at a time, so a provider may serve exactly one (the entity's own
 * provider, owned by the module that owns the entity) or many (a cross-cutting provider that offers the same
 * shape of data for every layout type).
 *
 * Every path a provider returns must resolve through `ContextPathResolver` against the root-ambient data of
 * the root sources it claims, and must be safe to serve publicly — see {@see MappingCandidate} for why both
 * are the provider's responsibility rather than something the framework can check for it.
 */
#[Package('framework')]
abstract class AbstractMappingCandidateProvider
{
    abstract public function supports(string $rootSource): bool;

    /**
     * Called only for a root source this provider answered `true` for.
     *
     * @return list<MappingCandidate>
     */
    abstract public function provide(string $rootSource): array;

    /**
     * Whether this provider can resolve the supplied non-root source reference. Candidate catalogues remain the
     * admission boundary: the renderer calls this only on the provider that offered the stored reference for the
     * current root source.
     */
    public function supportsSource(MappingSourceReference $source): bool
    {
        return false;
    }

    /**
     * Resolve an admitted non-root reference. Returning null means the provider is unavailable or has no value;
     * the renderer omits the mapped property and continues rendering.
     */
    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): mixed
    {
        return null;
    }
}
