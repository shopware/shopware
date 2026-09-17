<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Provider;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
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
}
